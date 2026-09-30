locals {
  name = "${var.project}-${var.environment}"
}

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

  snapshot_retention_limit = 0

  # JST 火曜 4:00-5:00
  maintenance_window = "mon:19:00-mon:20:00"

  apply_immediately = true

  tags = { Name = "${local.name}-redis" }
}
