locals {
  name = "${var.project}-${var.environment}"

  # ALB / Target Group は名前が 32 文字制限のため、短縮プレフィックスを使う
  # (フル名の novel-submission-site-clone-prod-alb は 36 文字で超過する)
  lb_name = "${var.short_name}-${var.environment}"

  # CloudFront → ALB のオリジン用ホスト名。viewer 向けの var.domain_name とは別に、
  # ALB を直接指す FQDN を切る(cloudfront モジュールがこの名前をオリジンに使う)。
  origin_domain_name = "origin.${var.domain_name}"
}

# ---------------------------------------------------------------------------
# ACM (ap-northeast-1) — ALB 用
# ---------------------------------------------------------------------------
# CloudFront 用の us-east-1 証明書は cloudfront モジュール側(provider alias)。
# ワイルドカード SAN で origin.<domain> もカバーする。

resource "aws_acm_certificate" "alb" {
  domain_name               = var.domain_name
  subject_alternative_names = ["*.${var.domain_name}"]
  validation_method         = "DNS"

  lifecycle {
    create_before_destroy = true
  }

  tags = { Name = "${local.name}-alb-cert" }
}

# apex と *.apex の検証レコードは同一値になるため allow_overwrite で吸収する。
resource "aws_route53_record" "alb_cert_validation" {
  for_each = {
    for dvo in aws_acm_certificate.alb.domain_validation_options : dvo.domain_name => {
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

resource "aws_acm_certificate_validation" "alb" {
  certificate_arn         = aws_acm_certificate.alb.arn
  validation_record_fqdns = [for r in aws_route53_record.alb_cert_validation : r.fqdn]
}

# CloudFront → ALB のオリジン用レコード。viewer 向け(<domain> → CloudFront)とは
# 別系統で、ALB を直接指す。TLS の SNI はこの名前で行われるため、ワイルドカード SAN
# (*.<domain>)がこの FQDN をカバーしている必要がある。
resource "aws_route53_record" "origin" {
  zone_id = var.zone_id
  name    = local.origin_domain_name
  type    = "A"

  alias {
    name                   = aws_lb.this.dns_name
    zone_id                = aws_lb.this.zone_id
    evaluate_target_health = true
  }
}

# ---------------------------------------------------------------------------
# Cloud Map HTTP 名前空間(Service Connect 用)
# ---------------------------------------------------------------------------
# East-West(Next.js SSR/ISR → Laravel API)を VPC 内に閉じる(ADR-INFRA 決定7)。
# HTTP 名前空間は DNS レコードを持たず、Service Connect の論理名解決にのみ使う。

resource "aws_service_discovery_http_namespace" "this" {
  name        = local.name
  description = "Service Connect namespace (East-West: web -> api)"
}

# ---------------------------------------------------------------------------
# ALB + Target Groups + Listener
# ---------------------------------------------------------------------------
# 単一の公開 ALB でパス振り分け: default → Next.js TG / /api/* → Laravel TG。
# 作成順序は TG → Listener → ECS Service(load_balancer 参照)。§11.1。

resource "aws_lb" "this" {
  name               = "${local.lb_name}-alb"
  load_balancer_type = "application"
  internal           = false
  subnets            = var.public_subnet_ids
  security_groups    = [var.alb_security_group_id]

  # fastcgi_read_timeout(60s)と揃える
  idle_timeout = 60

  tags = { Name = "${local.name}-alb" }
}

resource "aws_lb_target_group" "web" {
  name        = "${local.lb_name}-web"
  vpc_id      = var.vpc_id
  target_type = "ip" # Fargate(awsvpc)は ip ターゲット固定
  port        = 3000
  protocol    = "HTTP"

  health_check {
    path                = "/"
    matcher             = "200"
    interval            = 30
    timeout             = 5
    healthy_threshold   = 2
    unhealthy_threshold = 3
  }

  deregistration_delay = 30

  tags = { Name = "${local.name}-web-tg" }
}

resource "aws_lb_target_group" "api" {
  name        = "${local.lb_name}-api"
  vpc_id      = var.vpc_id
  target_type = "ip"
  port        = 80
  protocol    = "HTTP"

  health_check {
    # nginx が DB 非依存で 200 を返す静的エンドポイント(docker/nginx/default.conf)
    path                = "/health"
    matcher             = "200"
    interval            = 30
    timeout             = 5
    healthy_threshold   = 2
    unhealthy_threshold = 3
  }

  deregistration_delay = 30

  tags = { Name = "${local.name}-api-tg" }
}

resource "aws_lb_listener" "https" {
  load_balancer_arn = aws_lb.this.arn
  port              = 443
  protocol          = "HTTPS"
  ssl_policy        = "ELBSecurityPolicy-TLS13-1-2-2021-06"
  # 検証完了済みの証明書を使う(validation を待たずに listener を作ると失敗する)
  certificate_arn = aws_acm_certificate_validation.alb.certificate_arn

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.web.arn
  }

  tags = { Name = "${local.name}-https" }
}

resource "aws_lb_listener_rule" "api" {
  listener_arn = aws_lb_listener.https.arn
  priority     = 10

  condition {
    path_pattern {
      values = ["/api/*"]
    }
  }

  action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.api.arn
  }

  tags = { Name = "${local.name}-api-rule" }
}

# ---------------------------------------------------------------------------
# ECS Cluster
# ---------------------------------------------------------------------------

resource "aws_ecs_cluster" "this" {
  name = local.name

  setting {
    # Container Insights はフェーズ4(観測性)で有効化を検討。ログ量課金を抑える
    name  = "containerInsights"
    value = "disabled"
  }

  tags = { Name = local.name }
}

resource "aws_ecs_cluster_capacity_providers" "this" {
  cluster_name       = aws_ecs_cluster.this.name
  capacity_providers = ["FARGATE"]

  default_capacity_provider_strategy {
    capacity_provider = "FARGATE"
    weight            = 100
  }
}
