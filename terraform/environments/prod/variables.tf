variable "project" {
  description = "Project name used as the prefix for all resources."
  type        = string
  default     = "novel-submission-site-clone"
}

variable "environment" {
  description = "Environment name (prod)."
  type        = string
  default     = "prod"
}

variable "region" {
  description = "Primary AWS region."
  type        = string
  default     = "ap-northeast-1"
}

# ---------------------------------------------------------------------------
# Networking
# ---------------------------------------------------------------------------

variable "vpc_cidr" {
  description = "CIDR block for the VPC."
  type        = string
  default     = "10.0.0.0/16"
}

variable "azs" {
  description = "Availability Zones (at least 2)."
  type        = list(string)
  default     = ["ap-northeast-1a", "ap-northeast-1c"]
}

# ---------------------------------------------------------------------------
# DNS / TLS
# ---------------------------------------------------------------------------

variable "domain_name" {
  description = "公開ドメイン名。prod-persistent で作成した hosted zone と同じ名前。"
  type        = string
}

# ---------------------------------------------------------------------------
# Deploy
# ---------------------------------------------------------------------------

variable "image_tag" {
  description = "ECS にデプロイするイメージのタグ。"
  type        = string
  default     = "latest"
}

# ---------------------------------------------------------------------------
# Secrets
# ---------------------------------------------------------------------------

variable "secrets_recovery_window_in_days" {
  description = "Secrets Manager の削除猶予期間(0 または 7〜30)。destroy 直後に同名で再作成できるよう 0。"
  type        = number
  default     = 0
}
