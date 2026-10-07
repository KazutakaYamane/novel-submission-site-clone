output "db_password_secret_arn" {
  description = "RDS master password のシークレット ARN。"
  value       = aws_secretsmanager_secret.db_password.arn
}

output "db_password_secret_name" {
  description = "RDS master password シークレットの名前(ID)。"
  value       = aws_secretsmanager_secret.db_password.name
}

output "db_password_value" {
  description = "RDS master password の平文値。database モジュールへの受け渡し専用で、ECS には ARN で注入すること。"
  value       = random_password.db_master.result
  sensitive   = true
}

output "app_key_secret_arn" {
  description = "Laravel APP_KEY のシークレット ARN。"
  value       = aws_secretsmanager_secret.app_key.arn
}

output "app_key_secret_name" {
  description = "Laravel APP_KEY シークレットの名前(ID)。"
  value       = aws_secretsmanager_secret.app_key.name
}

output "origin_verify_value" {
  description = "CloudFront → ALB の転送元検証ヘッダーの値。cloudfront(付与)と ecs-service(検証)モジュールへの受け渡し専用。"
  value       = random_password.origin_verify.result
  sensitive   = true
}
