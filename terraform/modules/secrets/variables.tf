variable "project" {
  description = "Project name; used as the resource name prefix."
  type        = string
}

variable "environment" {
  description = "Environment name (e.g. prod)."
  type        = string
}

variable "recovery_window_in_days" {
  description = "Secrets Manager の削除猶予期間(日)。"
  type        = number
  default     = 7

  validation {
    condition     = var.recovery_window_in_days == 0 || (var.recovery_window_in_days >= 7 && var.recovery_window_in_days <= 30)
    error_message = "recovery_window_in_days は 0、または 7〜30 の範囲で指定してください(AWS の制約)。"
  }
}
