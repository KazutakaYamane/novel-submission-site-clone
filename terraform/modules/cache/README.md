# terraform/modules/cache

ElastiCache(Redis)を構築するモジュール

## 構成

- `aws_elasticache_subnet_group`: private subnet × 2AZ
- `aws_elasticache_replication_group`: Redis 7.1 / `cache.t4g.micro` / シングルノード / cluster mode 無効

## 主要 outputs

| 名前 | 用途 |
|---|---|
| `redis_url` | `redis://host:port`。ECS タスクの `REDIS_URL` |
| `primary_endpoint_address` | デバッグ用 |
