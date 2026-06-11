# ---------------------------------------------------------------------------
# 手動作業・デプロイで参照する値
# ---------------------------------------------------------------------------

# NS 4 値(subdomain_name_servers)と ECR URL は ../prod-persistent の output に移管。

output "site_url" {
  description = "公開 URL。"
  value       = "https://${var.domain_name}"
}

output "ecs_cluster_name" {
  description = "ECS クラスタ名(`aws ecs update-service --cluster` に使う)。"
  value       = module.ecs_service.cluster_name
}

output "ecs_service_names" {
  description = "ECS サービス名(web / api)。"
  value = {
    web = module.ecs_service.web_service_name
    api = module.ecs_service.api_service_name
  }
}

output "alb_dns_name" {
  description = "ALB の DNS 名(疎通デバッグ用)。"
  value       = module.ecs_service.alb_dns_name
}

output "cloudfront_distribution_id" {
  description = "CloudFront ディストリビューション ID(invalidation に使う)。"
  value       = module.cloudfront.distribution_id
}

output "static_assets_bucket" {
  description = "Next.js 静的アセット用 S3 バケット名(`aws s3 sync` の宛先)。"
  value       = module.cloudfront.static_assets_bucket
}

output "rds_endpoint" {
  description = "RDS エンドポイント(デバッグ・runbook 用)。"
  value       = module.database.endpoint_address
}

output "redis_endpoint" {
  description = "ElastiCache プライマリエンドポイント(デバッグ・runbook 用)。"
  value       = module.cache.primary_endpoint_address
}
