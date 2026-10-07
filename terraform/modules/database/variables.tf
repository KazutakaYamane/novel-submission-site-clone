variable "project" {
  description = "Project name; used as the resource name prefix."
  type        = string
}

variable "environment" {
  description = "Environment name (e.g. prod)."
  type        = string
}

variable "private_subnet_ids" {
  description = "Private subnet IDs for the DB subnet group."
  type        = list(string)

  validation {
    condition     = length(var.private_subnet_ids) >= 2
    error_message = "RDS subnet group requires subnets in at least 2 Availability Zones."
  }
}

variable "security_group_id" {
  description = "Security Group ID for RDS."
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
  description = "マスターパスワード。"
  type        = string
  sensitive   = true
}

variable "instance_class" {
  description = "RDS インスタンスクラス(Graviton)。"
  type        = string
  default     = "db.t4g.micro"
}

variable "engine_version" {
  description = "MySQL エンジンバージョン。"
  type        = string
  default     = "8.0"
}

variable "allocated_storage" {
  description = "ストレージサイズ(GB, gp3)。"
  type        = number
  default     = 20
}

variable "multi_az" {
  description = "RDS を Multi-AZ 構成にするか。"
  type        = bool
  default     = false
}

variable "deletion_protection" {
  description = "RDS の削除保護。true の間は terraform destroy が失敗する。"
  type        = bool
  default     = false
}

variable "skip_final_snapshot" {
  description = "destroy 時に最終スナップショットを省略するか。"
  type        = bool
  default     = true
}
