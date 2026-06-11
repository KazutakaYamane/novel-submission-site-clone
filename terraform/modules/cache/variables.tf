variable "project" {
  description = "Project name; used as the resource name prefix."
  type        = string
}

variable "environment" {
  description = "Environment name (e.g. prod)."
  type        = string
}

variable "private_subnet_ids" {
  description = "Private subnet IDs for the ElastiCache subnet group."
  type        = list(string)
}

variable "security_group_id" {
  description = "Security Group ID for ElastiCache (network モジュールの redis_security_group_id)。"
  type        = string
}

variable "node_type" {
  description = "ElastiCache node type. ISR 共有 + Laravel session/cache/queue の低トラフィック想定。"
  type        = string
  default     = "cache.t4g.micro"
}

variable "engine_version" {
  description = "Redis engine version."
  type        = string
  default     = "7.1"
}
