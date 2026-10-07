variable "project" {
  description = "Project name; used as the resource name prefix."
  type        = string
}

variable "environment" {
  description = "Environment name (e.g. prod)."
  type        = string
}

variable "domain_name" {
  description = "公開ドメイン名(viewer 向け。CloudFront の alias)。"
  type        = string
}

variable "zone_id" {
  description = "ACM 検証レコードと alias レコードを作成する Route 53 hosted zone ID。"
  type        = string
}

variable "origin_domain_name" {
  description = "ALB オリジンの FQDN(ecs-service モジュールの origin_domain_name)。"
  type        = string
}

variable "origin_verify_header_name" {
  description = "ALB オリジンへの転送時に付けるヘッダー名。ecs-service モジュールの同名変数と揃える。"
  type        = string
  default     = "X-Origin-Verify"
}

variable "origin_verify_header_value" {
  description = "ALB オリジンへの転送時に付けるヘッダー値。ecs-service モジュールの ALB が一致を検証する。"
  type        = string
  sensitive   = true
}
