locals {
  name = "${var.project}-${var.environment}"
}

# ---------------------------------------------------------------------------
# ElastiCache (Redis)
# ---------------------------------------------------------------------------
# 用途(ADR-INFRA 決定8):
#   - Next.js の ISR 共有キャッシュ(custom cacheHandler の backend)。
#     Fargate の .next/cache はタスクローカル・揮発のため、タスク横断の共有ストアが必須。
#   - Laravel の session / cache / queue。
#
# 設計判断:
#   - Single-AZ・レプリカなし(num_cache_clusters = 1)。キャッシュ用途であり、
#     ノード喪失時は ISR 再生成 / セッション再ログインで復旧可能。Multi-AZ の
#     固定費増(約2倍)に見合う可用性要件がない。
#   - 暗号化(at-rest / in-transit)と AUTH は使わない。private subnet + SG で
#     ECS タスク(web/api)からのみ到達可能に絞っており、TLS を入れると
#     phpredis / ioredis 双方の接続設定が複雑になる割に、この構成では脅威が変わらない。
#   - cluster mode は無効(シングルシャードで足りる規模。クライアント設定も単純)。

resource "aws_elasticache_subnet_group" "this" {
  name        = "${local.name}-redis"
  description = "Private subnets for Redis (ISR shared cache + Laravel session/cache/queue)"
  subnet_ids  = var.private_subnet_ids
}

resource "aws_elasticache_replication_group" "this" {
  replication_group_id = "${local.name}-redis"
  description          = "ISR shared cache (Next.js cacheHandler) + Laravel session/cache/queue"

  engine               = "redis"
  engine_version       = var.engine_version
  node_type            = var.node_type
  num_cache_clusters   = 1
  parameter_group_name = "default.redis7"
  port                 = 6379

  subnet_group_name  = aws_elasticache_subnet_group.this.name
  security_group_ids = [var.security_group_id]

  automatic_failover_enabled = false
  multi_az_enabled           = false
  at_rest_encryption_enabled = false
  transit_encryption_enabled = false

  # キャッシュ用途のためスナップショット不要(復旧は再生成で足りる)
  snapshot_retention_limit = 0

  # メンテナンスはアクセスの少ない早朝(JST 火曜 4:00-5:00 = UTC 月曜 19:00-20:00)
  maintenance_window = "mon:19:00-mon:20:00"

  apply_immediately = true

  tags = { Name = "${local.name}-redis" }
}
