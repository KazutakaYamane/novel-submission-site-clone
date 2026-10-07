locals {
  name = "${var.project}-${var.environment}"

  # ALB / Target Group の名前は 32 文字制限があり、フル名では超過する
  lb_name = "${var.short_name}-${var.environment}"

  # CloudFront がオリジンとして使う ALB の FQDN(viewer 向けの domain_name とは別)
  origin_domain_name = "origin.${var.domain_name}"
}

# ---------------------------------------------------------------------------
# ACM — ALB 用
# ---------------------------------------------------------------------------
# ALB に直接届く名前は origin.<domain> だけなので、証明書もこの名前のみで発行する。

resource "aws_acm_certificate" "alb" {
  domain_name       = local.origin_domain_name
  validation_method = "DNS"

  lifecycle {
    create_before_destroy = true
  }

  tags = { Name = "${local.name}-alb-cert" }
}

resource "aws_route53_record" "alb_cert_validation" {
  for_each = {
    for dvo in aws_acm_certificate.alb.domain_validation_options : dvo.domain_name => {
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

resource "aws_acm_certificate_validation" "alb" {
  certificate_arn         = aws_acm_certificate.alb.arn
  validation_record_fqdns = [for r in aws_route53_record.alb_cert_validation : r.fqdn]
}

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

resource "aws_service_discovery_http_namespace" "this" {
  name        = local.name
  description = "Service Connect namespace (East-West: web -> api)"
}

# ---------------------------------------------------------------------------
# ALB + Target Groups + Listener
# ---------------------------------------------------------------------------

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
  # validation 完了を待たずに listener を作ると失敗する
  certificate_arn = aws_acm_certificate_validation.alb.certificate_arn

  # プレフィックスリストは全世界の CloudFront を許可するため、他人のディストリビューション
  # からの転送を防ぐ。ヘッダーが一致するルールに当たらないリクエストはここで拒否する
  default_action {
    type = "fixed-response"

    fixed_response {
      content_type = "text/plain"
      message_body = "Forbidden"
      status_code  = "403"
    }
  }

  tags = { Name = "${local.name}-https" }
}

resource "aws_lb_listener_rule" "web" {
  listener_arn = aws_lb_listener.https.arn
  priority     = 100

  condition {
    http_header {
      http_header_name = var.origin_verify_header_name
      values           = [var.origin_verify_header_value]
    }
  }

  action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.web.arn
  }

  tags = { Name = "${local.name}-web-rule" }
}

resource "aws_lb_listener_rule" "api" {
  listener_arn = aws_lb_listener.https.arn
  priority     = 10

  condition {
    path_pattern {
      values = ["/api/*"]
    }
  }

  condition {
    http_header {
      http_header_name = var.origin_verify_header_name
      values           = [var.origin_verify_header_value]
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
