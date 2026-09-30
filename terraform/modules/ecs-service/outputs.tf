output "alb_dns_name" {
  description = "ALB の DNS 名(疎通確認用)。"
  value       = aws_lb.this.dns_name
}

output "alb_zone_id" {
  description = "ALB の Route 53 zone ID(alias レコード用)。"
  value       = aws_lb.this.zone_id
}

output "origin_domain_name" {
  description = "CloudFront のオリジンに使う ALB の FQDN(origin.<domain>)。"
  value       = local.origin_domain_name
}

output "certificate_arn" {
  description = "ALB 用 ACM 証明書(ap-northeast-1)の ARN。"
  value       = aws_acm_certificate_validation.alb.certificate_arn
}

output "cluster_name" {
  description = "ECS クラスタ名。デプロイスクリプト / CI から参照。"
  value       = aws_ecs_cluster.this.name
}

output "web_service_name" {
  description = "Next.js サービス名。`aws ecs update-service` に使う。"
  value       = module.web_service.name
}

output "api_service_name" {
  description = "Laravel サービス名。`aws ecs update-service` に使う。"
  value       = module.api_service.name
}
