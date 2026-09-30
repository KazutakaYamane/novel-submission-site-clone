locals {
  name = "${var.project}-${var.environment}"
}

resource "aws_db_subnet_group" "this" {
  name        = "${local.name}-mysql"
  description = "Private subnets for RDS MySQL"
  subnet_ids  = var.private_subnet_ids

  tags = { Name = "${local.name}-mysql-subnet-group" }
}

resource "aws_db_parameter_group" "this" {
  name   = "${local.name}-mysql80"
  family = "mysql8.0"
  # RDS の description は ASCII のみ受け付ける
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

  # JST 早朝にバックアップ → メンテの順で連続させる
  backup_retention_period = 7
  backup_window           = "18:00-18:30" # UTC = JST 3:00-3:30
  maintenance_window      = "mon:19:00-mon:20:00"

  auto_minor_version_upgrade = true
  apply_immediately          = true

  skip_final_snapshot       = var.skip_final_snapshot
  final_snapshot_identifier = var.skip_final_snapshot ? null : "${local.name}-mysql-final"
  deletion_protection       = false

  tags = { Name = "${local.name}-mysql" }
}
