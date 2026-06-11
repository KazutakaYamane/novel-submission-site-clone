output "db_password_secret_arn" {
  description = "RDS master password を保持する Secrets Manager のシークレット ARN。ECS task definition の secrets ブロックから参照する。"
  value       = aws_secretsmanager_secret.db_password.arn
}

output "db_password_secret_name" {
  description = "RDS master password シークレットの名前(ID)。"
  value       = aws_secretsmanager_secret.db_password.name
}

# database モジュールに master_password として渡すためだけの sensitive 出力。
# 用途を絞ること(ECS への注入は ARN 経由で行い、平文値を間接的に Task Definition に
# 埋め込んではいけない)。
output "db_password_value" {
  description = "RDS master password の平文値。database モジュールの master_password 引数にのみ使用する。"
  value       = random_password.db_master.result
  sensitive   = true
}

output "app_key_secret_arn" {
  description = "Laravel APP_KEY を保持する Secrets Manager のシークレット ARN。ECS task definition の secrets ブロックから APP_KEY 環境変数として注入する。"
  value       = aws_secretsmanager_secret.app_key.arn
}

output "app_key_secret_name" {
  description = "Laravel APP_KEY シークレットの名前(ID)。"
  value       = aws_secretsmanager_secret.app_key.name
}

output "redis_url_secret_arn" {
  description = "REDIS_URL を保持する Secrets Manager のシークレット ARN。Laravel / Next.js 両タスクの secrets ブロックから注入する。"
  value       = aws_secretsmanager_secret.redis_url.arn
}

output "redis_url_secret_name" {
  description = "REDIS_URL シークレットの名前(ID)。"
  value       = aws_secretsmanager_secret.redis_url.name
}
