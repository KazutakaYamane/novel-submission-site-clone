locals {
  name = "${var.project}-${var.environment}"

  origin_id_alb = "alb"
  origin_id_s3  = "static-assets"
}

# ---------------------------------------------------------------------------
# ACM (us-east-1) — CloudFront viewer 証明書
# ---------------------------------------------------------------------------
# ALB 用(ap-northeast-1)とは別に発行が必要(§11.1)。検証 CNAME は ACM の仕様上
# 同一ドメイン・同一アカウントなら同じ名前・値になるため、ALB 側のレコードと
# allow_overwrite で共存させる。

resource "aws_acm_certificate" "viewer" {
  provider = aws.us_east_1

  domain_name       = var.domain_name
  validation_method = "DNS"

  lifecycle {
    create_before_destroy = true
  }

  tags = { Name = "${local.name}-viewer-cert" }
}

resource "aws_route53_record" "viewer_cert_validation" {
  for_each = {
    for dvo in aws_acm_certificate.viewer.domain_validation_options : dvo.domain_name => {
      name   = dvo.resource_record_name
      record = dvo.resource_record_value
      type   = dvo.resource_record_type
    }
  }

  zone_id         = var.zone_id
  name            = each.value.name
  type            = each.value.type
  records         = [each.value.record]
  ttl             = 60
  allow_overwrite = true
}

resource "aws_acm_certificate_validation" "viewer" {
  provider = aws.us_east_1

  certificate_arn         = aws_acm_certificate.viewer.arn
  validation_record_fqdns = [for r in aws_route53_record.viewer_cert_validation : r.fqdn]
}

# ---------------------------------------------------------------------------
# S3: Next.js 静的アセット(/_next/static/*)
# ---------------------------------------------------------------------------
# デプロイ時に `aws s3 sync` で配置し、CloudFront(OAC)経由でのみ読み出す。
# UGC アップロード用バケットとは公開ポリシーが異なるため別バケット(§6.1。
# UGC 用はフェーズ3 で機能実装に応じて追加)。

resource "aws_s3_bucket" "static_assets" {
  # S3 バケット名はグローバル一意。プロジェクト名が十分に固有なので接頭辞で衝突回避
  bucket = "${local.name}-static-assets"

  # destroy/recreate サイクル向け(アセットは再 sync で復元可能)
  force_destroy = true

  tags = { Name = "${local.name}-static-assets" }
}

resource "aws_s3_bucket_public_access_block" "static_assets" {
  bucket = aws_s3_bucket.static_assets.id

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

# CloudFront からの読み出しのみ許可(OAC)。
resource "aws_s3_bucket_policy" "static_assets" {
  bucket = aws_s3_bucket.static_assets.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Sid       = "AllowCloudFrontOAC"
        Effect    = "Allow"
        Principal = { Service = "cloudfront.amazonaws.com" }
        Action    = "s3:GetObject"
        Resource  = "${aws_s3_bucket.static_assets.arn}/*"
        Condition = {
          StringEquals = {
            "AWS:SourceArn" = aws_cloudfront_distribution.this.arn
          }
        }
      }
    ]
  })
}

resource "aws_cloudfront_origin_access_control" "static_assets" {
  name                              = "${local.name}-static-assets"
  description                       = "OAC for Next.js static assets bucket"
  origin_access_control_origin_type = "s3"
  signing_behavior                  = "always"
  signing_protocol                  = "sigv4"
}

# ---------------------------------------------------------------------------
# マネージドポリシー(参照のみ)
# ---------------------------------------------------------------------------
# キャッシュ戦略(§6.2 / §11.1):
#   - default(Next.js ページ): オリジンの Cache-Control を尊重する。ISR ページは
#     s-maxage / stale-while-revalidate でキャッシュされ、個人化 SSR ページは
#     Next.js が返す private / no-store で「キャッシュされない」。
#     【最重要】このビヘイビア分離を誤ると他人のログイン状態を配信する事故になる。
#   - /api/*: キャッシュ完全無効(JSON API)。
#   - /_next/static/*: content hash 付きアセット。長期 immutable キャッシュ。

data "aws_cloudfront_cache_policy" "use_origin_cache_control" {
  # 新しめのマネージドポリシーは旧来の "Managed-" プレフィックスが付かない
  name = "UseOriginCacheControlHeaders-QueryStrings"
}

data "aws_cloudfront_cache_policy" "caching_disabled" {
  name = "Managed-CachingDisabled"
}

data "aws_cloudfront_cache_policy" "caching_optimized" {
  name = "Managed-CachingOptimized"
}

# Host を含む全ヘッダ・cookie・クエリをオリジンに転送する。Laravel / Next.js が
# 正しい Host(公開ドメイン)で URL 生成できるようにするため。
data "aws_cloudfront_origin_request_policy" "all_viewer" {
  name = "Managed-AllViewer"
}

# ---------------------------------------------------------------------------
# CloudFront Distribution
# ---------------------------------------------------------------------------

resource "aws_cloudfront_distribution" "this" {
  enabled         = true
  comment         = "${local.name}: default->Next.js(ALB) / api->Laravel(ALB) / static->S3"
  aliases         = [var.domain_name]
  is_ipv6_enabled = true
  http_version    = "http2and3"

  # 日本のエッジを含める(PriceClass_100 は北米・欧州のみで日本が外れる)
  price_class = "PriceClass_200"

  # ----- Origins -----

  origin {
    origin_id   = local.origin_id_alb
    domain_name = var.origin_domain_name

    custom_origin_config {
      origin_protocol_policy = "https-only"
      https_port             = 443
      http_port              = 80 # 未使用だが必須項目
      origin_ssl_protocols   = ["TLSv1.2"]
    }
  }

  origin {
    origin_id                = local.origin_id_s3
    domain_name              = aws_s3_bucket.static_assets.bucket_regional_domain_name
    origin_access_control_id = aws_cloudfront_origin_access_control.static_assets.id
  }

  # ----- Behaviors -----

  # default: Next.js のページレンダリング(ISR はオリジン Cache-Control を尊重して
  # キャッシュ、個人化 SSR は no-store で非キャッシュ)。
  default_cache_behavior {
    target_origin_id       = local.origin_id_alb
    viewer_protocol_policy = "redirect-to-https"

    allowed_methods = ["GET", "HEAD", "OPTIONS", "PUT", "POST", "PATCH", "DELETE"]
    cached_methods  = ["GET", "HEAD"]

    cache_policy_id          = data.aws_cloudfront_cache_policy.use_origin_cache_control.id
    origin_request_policy_id = data.aws_cloudfront_origin_request_policy.all_viewer.id

    compress = true
  }

  # /api/*: Laravel JSON API。キャッシュ完全無効。
  ordered_cache_behavior {
    path_pattern           = "/api/*"
    target_origin_id       = local.origin_id_alb
    viewer_protocol_policy = "redirect-to-https"

    allowed_methods = ["GET", "HEAD", "OPTIONS", "PUT", "POST", "PATCH", "DELETE"]
    cached_methods  = ["GET", "HEAD"]

    cache_policy_id          = data.aws_cloudfront_cache_policy.caching_disabled.id
    origin_request_policy_id = data.aws_cloudfront_origin_request_policy.all_viewer.id

    compress = true
  }

  # /_next/static/*: content hash 付き静的アセット。S3 オリジン + 長期キャッシュ。
  ordered_cache_behavior {
    path_pattern           = "/_next/static/*"
    target_origin_id       = local.origin_id_s3
    viewer_protocol_policy = "redirect-to-https"

    allowed_methods = ["GET", "HEAD"]
    cached_methods  = ["GET", "HEAD"]

    cache_policy_id = data.aws_cloudfront_cache_policy.caching_optimized.id

    compress = true
  }

  restrictions {
    geo_restriction {
      restriction_type = "none"
    }
  }

  viewer_certificate {
    acm_certificate_arn      = aws_acm_certificate_validation.viewer.certificate_arn
    ssl_support_method       = "sni-only"
    minimum_protocol_version = "TLSv1.2_2021"
  }

  tags = { Name = local.name }
}

# ---------------------------------------------------------------------------
# Route 53: viewer 向け alias
# ---------------------------------------------------------------------------

resource "aws_route53_record" "viewer_a" {
  zone_id = var.zone_id
  name    = var.domain_name
  type    = "A"

  alias {
    name                   = aws_cloudfront_distribution.this.domain_name
    zone_id                = aws_cloudfront_distribution.this.hosted_zone_id
    evaluate_target_health = false
  }
}

resource "aws_route53_record" "viewer_aaaa" {
  zone_id = var.zone_id
  name    = var.domain_name
  type    = "AAAA"

  alias {
    name                   = aws_cloudfront_distribution.this.domain_name
    zone_id                = aws_cloudfront_distribution.this.hosted_zone_id
    evaluate_target_health = false
  }
}
