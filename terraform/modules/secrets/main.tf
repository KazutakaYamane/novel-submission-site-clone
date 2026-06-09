locals {
  name = "${var.project}-${var.environment}"
}

# ---------------------------------------------------------------------------
# Random values
# ---------------------------------------------------------------------------
# RDS マスターパスワードと Laravel APP_KEY を Terraform 側で生成し、Secrets Manager
# に保存する。生成値は state に残るが、state は S3 (SSE-S3 / TLS 強制 / バージョニング)
# に置く前提のため平文露出は遮断される(bootstrap モジュール参照)。

# RDS for MySQL マスターパスワード。
# RDS の制約: '/', '@', '"', スペースは master_password に使えない(§11.1 補足)。
# random_password の特殊文字を上の禁止集合と被らないものだけに絞る。
resource "random_password" "db_master" {
  length           = 32
  special          = true
  override_special = "!#$%&*+-=?_"
  min_lower        = 2
  min_upper        = 2
  min_numeric      = 2
  min_special      = 2
}

# Laravel APP_KEY: "base64:" + base64(32 random bytes)。
# php artisan key:generate と同じ書式に揃える。
resource "random_id" "app_key" {
  byte_length = 32
}

# ---------------------------------------------------------------------------
# Secrets Manager
# ---------------------------------------------------------------------------
# KMS は AWS マネージドキー(aws/secretsmanager)を使う。
# CMK は月 $1/key かかり、ポートフォリオでは過剰投資。CMK が必要になるのは
# クロスアカウント共有や厳格な監査要件が出てきたタイミング。

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
