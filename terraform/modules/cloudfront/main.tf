locals {
  name = "${var.project}-${var.environment}"

  origin_id_alb = "alb"
  origin_id_s3  = "static-assets"
}

# ---------------------------------------------------------------------------
# ACM — CloudFront viewer 証明書(us-east-1 にしか置けない)
# ---------------------------------------------------------------------------

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

  zone_id = var.zone_id
  name    = each.value.name
  type    = each.value.type
  records = [each.value.record]
  ttl     = 60
}

resource "aws_acm_certificate_validation" "viewer" {
  provider = aws.us_east_1

  certificate_arn         = aws_acm_certificate.viewer.arn
  validation_record_fqdns = [for r in aws_route53_record.viewer_cert_validation : r.fqdn]
}

# ---------------------------------------------------------------------------
# S3: Next.js 静的アセット(/_next/static/*)
# ---------------------------------------------------------------------------

resource "aws_s3_bucket" "static_assets" {
  bucket = "${local.name}-static-assets"

  # 中身はデプロイ時の sync で復元できる
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
# マネージドポリシー(キャッシュ動作ごとの方針は README.md)
# ---------------------------------------------------------------------------

data "aws_cloudfront_cache_policy" "caching_disabled" {
  name = "Managed-CachingDisabled"
}

data "aws_cloudfront_cache_policy" "caching_optimized" {
  name = "Managed-CachingOptimized"
}

# next/image の変換結果は URL・幅・品質・Accept(WebP/AVIF の出し分け)ごとに異なる。
# マネージドの CachingOptimized はクエリ文字列をキャッシュキーに含めないため自前で定義する
resource "aws_cloudfront_cache_policy" "next_image" {
  name        = "${local.name}-next-image"
  min_ttl     = 0
  default_ttl = 86400
  max_ttl     = 31536000

  parameters_in_cache_key_and_forwarded_to_origin {
    enable_accept_encoding_gzip   = false
    enable_accept_encoding_brotli = false

    cookies_config {
      cookie_behavior = "none"
    }
    headers_config {
      header_behavior = "whitelist"
      headers {
        items = ["Accept"]
      }
    }
    query_strings_config {
      query_string_behavior = "whitelist"
      query_strings {
        items = ["url", "w", "q"]
      }
    }
  }
}

# Managed-AllViewer は CloudFront-Viewer-Address を送らない。
# nginx(API)と Next.js(SSR が X-User-IP に入れる)がユーザーの IP をこのヘッダーから取る
data "aws_cloudfront_origin_request_policy" "all_viewer_and_cloudfront_headers" {
  name = "Managed-AllViewerAndCloudFrontHeaders-2022-06"
}

# ---------------------------------------------------------------------------
# CloudFront Distribution
# ---------------------------------------------------------------------------

resource "aws_cloudfront_distribution" "this" {
  enabled         = true
  comment         = "${local.name}: default,image->Next.js(ALB) / api->Laravel(ALB) / static->S3"
  aliases         = [var.domain_name]
  is_ipv6_enabled = true
  http_version    = "http2and3"

  # PriceClass_100 には日本のエッジが含まれない
  price_class = "PriceClass_200"

  # ----- Origins -----

  origin {
    origin_id   = local.origin_id_alb
    domain_name = var.origin_domain_name

    custom_header {
      name  = var.origin_verify_header_name
      value = var.origin_verify_header_value
    }

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

  # HTML はエッジに置かない。ISR のキャッシュは Next.js(ElastiCache)の1層だけにし、
  # revalidateTag を即時に効かせる。Next.js が付ける s-maxage もここで無視される
  default_cache_behavior {
    target_origin_id       = local.origin_id_alb
    viewer_protocol_policy = "redirect-to-https"

    allowed_methods = ["GET", "HEAD", "OPTIONS", "PUT", "POST", "PATCH", "DELETE"]
    cached_methods  = ["GET", "HEAD"]

    cache_policy_id          = data.aws_cloudfront_cache_policy.caching_disabled.id
    origin_request_policy_id = data.aws_cloudfront_origin_request_policy.all_viewer_and_cloudfront_headers.id

    compress = true
  }

  ordered_cache_behavior {
    path_pattern           = "/api/*"
    target_origin_id       = local.origin_id_alb
    viewer_protocol_policy = "redirect-to-https"

    allowed_methods = ["GET", "HEAD", "OPTIONS", "PUT", "POST", "PATCH", "DELETE"]
    cached_methods  = ["GET", "HEAD"]

    cache_policy_id          = data.aws_cloudfront_cache_policy.caching_disabled.id
    origin_request_policy_id = data.aws_cloudfront_origin_request_policy.all_viewer_and_cloudfront_headers.id

    compress = true
  }

  ordered_cache_behavior {
    path_pattern           = "/_next/static/*"
    target_origin_id       = local.origin_id_s3
    viewer_protocol_policy = "redirect-to-https"

    allowed_methods = ["GET", "HEAD"]
    cached_methods  = ["GET", "HEAD"]

    cache_policy_id = data.aws_cloudfront_cache_policy.caching_optimized.id

    compress = true
  }

  # next/image の変換結果。TTL は Next.js が返す Cache-Control(images.minimumCacheTTL)に従う
  ordered_cache_behavior {
    path_pattern           = "/_next/image*"
    target_origin_id       = local.origin_id_alb
    viewer_protocol_policy = "redirect-to-https"

    allowed_methods = ["GET", "HEAD"]
    cached_methods  = ["GET", "HEAD"]

    cache_policy_id = aws_cloudfront_cache_policy.next_image.id
  }

  # frontend/public/static/ の静的ファイル。ファイル名にハッシュが入らないため
  # デプロイ時に 1 日の max-age を付けて S3 に置く
  ordered_cache_behavior {
    path_pattern           = "/static/*"
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
