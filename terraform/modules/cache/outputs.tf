output "primary_endpoint_address" {
  description = "Redis プライマリエンドポイントのホスト名。"
  value       = aws_elasticache_replication_group.this.primary_endpoint_address
}

output "port" {
  description = "Redis ポート。"
  value       = aws_elasticache_replication_group.this.port
}

output "redis_url" {
  description = "redis://host:port 形式の接続 URL。secrets モジュール経由で ECS タスクに注入する。"
  value       = "redis://${aws_elasticache_replication_group.this.primary_endpoint_address}:${aws_elasticache_replication_group.this.port}"
}
