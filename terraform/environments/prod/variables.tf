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
  description = "Primary AWS region. CloudFront 用 ACM のみ us-east-1 に切る(providers.tf を参照)。"
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
  description = "Availability Zones. RDS subnet group は Single-AZ 運用でも 2AZ 分が必須(§11.1)。"
  type        = list(string)
  default     = ["ap-northeast-1a", "ap-northeast-1c"]
}

variable "public_subnet_cidrs" {
  description = "CIDR blocks for public subnets. ECS タスクをここに配置し Public IP を付ける(NAT 不使用、§6.2)。"
  type        = list(string)
  default     = ["10.0.0.0/24", "10.0.1.0/24"]
}

variable "private_subnet_cidrs" {
  description = "CIDR blocks for private subnets. RDS / ElastiCache 用。"
  type        = list(string)
  default     = ["10.0.10.0/24", "10.0.11.0/24"]
}

# ---------------------------------------------------------------------------
# DNS / TLS
# ---------------------------------------------------------------------------

variable "domain_name" {
  description = "公開ドメイン名(例: novel-submission-site-clone.example.com)。Route 53 public hosted zone が同一アカウントに存在する前提。"
  type        = string
}

# ---------------------------------------------------------------------------
# Secrets
# ---------------------------------------------------------------------------

variable "secrets_recovery_window_in_days" {
  description = "Secrets Manager の削除猶予期間。0 または 7〜30。ポートフォリオで destroy/recreate を繰り返す段階では 0 に上書きすると同名再作成のロックを回避できる。詳細は modules/secrets/README.md。"
  type        = number
  default     = 7
}
