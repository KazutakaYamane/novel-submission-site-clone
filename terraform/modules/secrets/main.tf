locals {
  name = "${var.project}-${var.environment}"
}

# RDS の master_password には '/', '@', '"', スペースを使えないため、特殊文字を絞る。
resource "random_password" "db_master" {
  length           = 32
  special          = true
  override_special = "!#$%&*+-=?_"
  min_lower        = 2
  min_upper        = 2
  min_numeric      = 2
  min_special      = 2
}

# CloudFront が ALB への転送時に付けるヘッダーの共有値。ALB 側で一致を検証する。
# ヘッダーに使うため特殊文字を含めない。
resource "random_password" "origin_verify" {
  length  = 32
  special = false
}

# php artisan key:generate と同じ "base64:<32 bytes>" 形式にする。
resource "random_id" "app_key" {
  byte_length = 32
}

resource "aws_secretsmanager_secret" "db_password" {
  name                    = "${local.name}/rds/master-password"
  description             = "RDS for MySQL master password."
  recovery_window_in_days = var.recovery_window_in_days
}

resource "aws_secretsmanager_secret_version" "db_password" {
  secret_id     = aws_secretsmanager_secret.db_password.id
  secret_string = random_password.db_master.result
}

resource "aws_secretsmanager_secret" "app_key" {
  name                    = "${local.name}/laravel/app-key"
  description             = "Laravel APP_KEY (base64:...)."
  recovery_window_in_days = var.recovery_window_in_days
}

resource "aws_secretsmanager_secret_version" "app_key" {
  secret_id     = aws_secretsmanager_secret.app_key.id
  secret_string = "base64:${random_id.app_key.b64_std}"
}
