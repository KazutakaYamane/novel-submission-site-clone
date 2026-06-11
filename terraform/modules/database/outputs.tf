output "endpoint_address" {
  description = "RDS エンドポイントのホスト名(ポートを含まない)。ECS タスクの DB_HOST に渡す。"
  value       = aws_db_instance.this.address
}

output "port" {
  description = "RDS ポート。"
  value       = aws_db_instance.this.port
}

output "db_name" {
  description = "データベース名。ECS タスクの DB_DATABASE に渡す。"
  value       = aws_db_instance.this.db_name
}

output "master_username" {
  description = "マスターユーザー名。ECS タスクの DB_USERNAME に渡す。"
  value       = aws_db_instance.this.username
}
