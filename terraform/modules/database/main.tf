locals {
  name = "${var.project}-${var.environment}"
}

# ---------------------------------------------------------------------------
# RDS for MySQL
# ---------------------------------------------------------------------------
# 設計判断:
#   - MySQL 8.0: カクヨム実環境と整合(§5.2、セッション5)。全文検索は
#     InnoDB FULLTEXT + ngram parser を第一候補とする。
#   - db.t4g.micro / Single-AZ / gp3 20GB: ポートフォリオの低トラフィック想定。
#     Multi-AZ は固定費が約2倍になり、可用性要件に対して過剰。
#   - private subnet 配置・public アクセス不可。到達できるのは Laravel(api) の
#     ECS SG のみ(network モジュールで表現)。
#   - 文字コードはローカル(compose の mysql:8.0 --character-set-server 指定)と
#     揃えるためパラメータグループで utf8mb4 / utf8mb4_unicode_ci を明示する。

resource "aws_db_subnet_group" "this" {
  name        = "${local.name}-mysql"
  description = "Private subnets for RDS MySQL (2 AZs required even for Single-AZ)"
  subnet_ids  = var.private_subnet_ids

  tags = { Name = "${local.name}-mysql-subnet-group" }
}

resource "aws_db_parameter_group" "this" {
  name   = "${local.name}-mysql80"
  family = "mysql8.0"
  # RDS の description は ASCII 印字可能文字のみ(日本語不可)
  description = "utf8mb4 / utf8mb4_unicode_ci (same as local compose MySQL)"

  parameter {
    name  = "character_set_server"
    value = "utf8mb4"
  }

  parameter {
    name  = "collation_server"
    value = "utf8mb4_unicode_ci"
  }

  tags = { Name = "${local.name}-mysql80-params" }
}

resource "aws_db_instance" "this" {
  identifier = "${local.name}-mysql"

  engine         = "mysql"
  engine_version = var.engine_version
  instance_class = var.instance_class

  db_name  = var.db_name
  username = var.master_username
  password = var.master_password
  port     = 3306

  allocated_storage = var.allocated_storage
  storage_type      = "gp3"
  storage_encrypted = true

  db_subnet_group_name   = aws_db_subnet_group.this.name
  vpc_security_group_ids = [var.security_group_id]
  publicly_accessible    = false
  multi_az               = false

  parameter_group_name = aws_db_parameter_group.this.name

  # バックアップ: 無料枠(DB サイズ分)内の 7 日保持。
  # ウィンドウは JST 早朝(バックアップ → メンテの順に連続させる)
  backup_retention_period = 7
  backup_window           = "18:00-18:30" # UTC = JST 3:00-3:30
  maintenance_window      = "mon:19:00-mon:20:00"

  auto_minor_version_upgrade = true
  apply_immediately          = true

  # ポートフォリオの destroy/recreate サイクル向け(variables.tf 参照)
  skip_final_snapshot       = var.skip_final_snapshot
  final_snapshot_identifier = var.skip_final_snapshot ? null : "${local.name}-mysql-final"
  deletion_protection       = false

  # パスワードは Terraform(random_password)で生成し Secrets Manager 管理のため
  # RDS 側の manage_master_user_password は使わない(二重管理を避ける)

  tags = { Name = "${local.name}-mysql" }
}
