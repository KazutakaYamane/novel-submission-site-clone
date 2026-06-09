variable "project" {
  description = "Project name; used as the resource name prefix."
  type        = string
}

variable "environment" {
  description = "Environment name (e.g. prod)."
  type        = string
}

variable "recovery_window_in_days" {
  description = "Secrets Manager の削除猶予期間。prod 既定 7 日。ポートフォリオで destroy/recreate を繰り返す段階では 0 に上書きすると同名再作成のロックを回避できる(7〜30 日内に同名で再作成しようとすると ResourceExistsException になる)。"
  type        = number
  default     = 7

  validation {
    condition     = var.recovery_window_in_days == 0 || (var.recovery_window_in_days >= 7 && var.recovery_window_in_days <= 30)
    error_message = "recovery_window_in_days は 0、または 7〜30 の範囲で指定してください(AWS の制約)。"
  }
}
