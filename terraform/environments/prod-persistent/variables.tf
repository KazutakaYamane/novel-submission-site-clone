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

variable "domain_name" {
  description = "公開ドメイン名(例: novel-portfolio.kyyk517.com)。この名前で hosted zone を作成し、親ドメインから NS 委譲を受ける。"
  type        = string
}
