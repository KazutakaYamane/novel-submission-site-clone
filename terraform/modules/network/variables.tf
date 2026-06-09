variable "project" {
  description = "Project name; used as the resource name prefix."
  type        = string
}

variable "environment" {
  description = "Environment name (e.g. prod)."
  type        = string
}

variable "vpc_cidr" {
  description = "CIDR block for the VPC."
  type        = string
}

variable "azs" {
  description = "Availability Zones. RDS subnet group は Single-AZ 運用でも 2AZ 必須。"
  type        = list(string)

  validation {
    condition     = length(var.azs) >= 2
    error_message = "RDS subnet group requires at least 2 Availability Zones."
  }
}

variable "public_subnet_cidrs" {
  description = "CIDR blocks for public subnets. 個数は azs と一致させる。"
  type        = list(string)

  validation {
    condition     = length(var.public_subnet_cidrs) >= 2
    error_message = "At least 2 public subnets are required (one per AZ)."
  }
}

variable "private_subnet_cidrs" {
  description = "CIDR blocks for private subnets. 個数は azs と一致させる。"
  type        = list(string)

  validation {
    condition     = length(var.private_subnet_cidrs) >= 2
    error_message = "At least 2 private subnets are required (one per AZ)."
  }
}
