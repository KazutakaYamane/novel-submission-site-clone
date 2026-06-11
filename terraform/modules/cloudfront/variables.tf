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
  description = "ALB オリジンの FQDN(ecs-service モジュールの origin_domain_name = origin.<domain>)。"
  type        = string
}
