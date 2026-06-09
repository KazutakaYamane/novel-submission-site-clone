variable "project" {
  description = "Project name used as the prefix for all resources."
  type        = string
  default     = "novel-submission-site-clone"
}

variable "region" {
  description = "AWS region for the state bucket and lock table."
  type        = string
  default     = "ap-northeast-1"
}

variable "state_bucket_name" {
  description = "Override for the S3 bucket name. Leave null to use \"<project>-tfstate-<account_id>\"."
  type        = string
  default     = null
}

variable "noncurrent_version_expiration_days" {
  description = "Number of days to retain non-current state object versions."
  type        = number
  default     = 90
}
