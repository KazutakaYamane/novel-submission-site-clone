variable "project" {
  description = "Project name; used as the resource name prefix."
  type        = string
}

variable "environment" {
  description = "Environment name (e.g. prod)."
  type        = string
}

variable "short_name" {
  description = "ALB / Target Group 名(32文字制限)に使う短縮プレフィックス。プロジェクト名が長いため。"
  type        = string
  default     = "nsc"
}

# ---------------------------------------------------------------------------
# DNS / TLS
# ---------------------------------------------------------------------------

variable "domain_name" {
  description = "公開ドメイン名(例: novel-portfolio.kyyk517.com)。ALB 用 ACM はこのドメイン + ワイルドカードで発行する(CloudFront → ALB のオリジン用 origin.<domain> をカバーするため)。"
  type        = string
}

variable "zone_id" {
  description = "ACM の DNS 検証レコードを作成する Route 53 hosted zone ID。"
  type        = string
}

# ---------------------------------------------------------------------------
# Networking
# ---------------------------------------------------------------------------

variable "vpc_id" {
  description = "VPC ID。"
  type        = string
}

variable "public_subnet_ids" {
  description = "ALB / ECS タスクを配置する public subnet ID。"
  type        = list(string)
}

variable "alb_security_group_id" {
  description = "ALB 用 SG(network モジュール)。"
  type        = string
}

variable "ecs_web_security_group_id" {
  description = "Next.js(web)タスク用 SG(network モジュール)。"
  type        = string
}

variable "ecs_api_security_group_id" {
  description = "Laravel(api)タスク用 SG(network モジュール)。"
  type        = string
}

# ---------------------------------------------------------------------------
# Images
# ---------------------------------------------------------------------------

variable "web_image" {
  description = "Next.js イメージ URI(タグ込み)。"
  type        = string
}

variable "api_app_image" {
  description = "Laravel(php-fpm)イメージ URI(タグ込み)。"
  type        = string
}

variable "api_nginx_image" {
  description = "Laravel タスクの nginx sidecar イメージ URI(タグ込み)。"
  type        = string
}

# ---------------------------------------------------------------------------
# App wiring
# ---------------------------------------------------------------------------

variable "db_host" {
  description = "RDS エンドポイント(database モジュールの endpoint_address)。"
  type        = string
}

variable "db_name" {
  description = "データベース名。"
  type        = string
}

variable "db_username" {
  description = "DB ユーザー名。"
  type        = string
}

variable "db_password_secret_arn" {
  description = "DB パスワードの Secrets Manager ARN。"
  type        = string
}

variable "app_key_secret_arn" {
  description = "Laravel APP_KEY の Secrets Manager ARN。"
  type        = string
}

variable "redis_url_secret_arn" {
  description = "REDIS_URL の Secrets Manager ARN(web / api 両タスクに注入)。"
  type        = string
}

# ---------------------------------------------------------------------------
# Sizing
# ---------------------------------------------------------------------------

variable "web_cpu" {
  description = "Next.js タスクの CPU(Node は要メモリ。§6.3 の試算は 0.5vCPU/1GB)。"
  type        = number
  default     = 512
}

variable "web_memory" {
  description = "Next.js タスクのメモリ(MiB)。"
  type        = number
  default     = 1024
}

variable "api_cpu" {
  description = "Laravel タスクの CPU(§6.3 の試算は 0.25vCPU/0.5GB)。"
  type        = number
  default     = 256
}

variable "api_memory" {
  description = "Laravel タスクのメモリ(MiB)。"
  type        = number
  default     = 512
}

variable "autoscaling_min_capacity" {
  description = "Auto Scaling の最小タスク数。"
  type        = number
  default     = 1
}

variable "autoscaling_max_capacity" {
  description = "Auto Scaling の最大タスク数。"
  type        = number
  default     = 2
}

variable "log_retention_in_days" {
  description = "CloudWatch Logs の保持期間。デフォルト無期限を避ける(§11.1)。"
  type        = number
  default     = 7
}
