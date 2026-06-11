variable "project" {
  description = "Project name; used as the resource name prefix."
  type        = string
}

variable "environment" {
  description = "Environment name (e.g. prod)."
  type        = string
}

variable "private_subnet_ids" {
  description = "Private subnet IDs for the DB subnet group. Single-AZ 運用でも 2AZ 分必須(§11.1)。"
  type        = list(string)

  validation {
    condition     = length(var.private_subnet_ids) >= 2
    error_message = "RDS subnet group requires subnets in at least 2 Availability Zones."
  }
}

variable "security_group_id" {
  description = "Security Group ID for RDS (network モジュールの rds_security_group_id)。"
  type        = string
}

variable "db_name" {
  description = "作成するデータベース名。"
  type        = string
  default     = "novel_submission_site"
}

variable "master_username" {
  description = "マスターユーザー名。"
  type        = string
  default     = "novel_submission_site"
}

variable "master_password" {
  description = "マスターパスワード(secrets モジュールの db_password_value)。Task Definition へは平文ではなく Secrets Manager の ARN 経由で注入すること。"
  type        = string
  sensitive   = true
}

variable "instance_class" {
  description = "RDS インスタンスクラス(Graviton)。"
  type        = string
  default     = "db.t4g.micro"
}

variable "engine_version" {
  description = "MySQL エンジンバージョン。カクヨム実環境(MySQL)と整合させる(§5.2)。"
  type        = string
  default     = "8.0"
}

variable "allocated_storage" {
  description = "ストレージサイズ(GB, gp3)。"
  type        = number
  default     = 20
}

variable "skip_final_snapshot" {
  description = "destroy 時に最終スナップショットを省略するか。ポートフォリオの destroy/recreate サイクルでは true が実用的。本番運用に転用する場合は false にすること。"
  type        = bool
  default     = true
}
