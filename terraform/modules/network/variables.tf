variable "project" {
  description = "Project name; used as the resource name prefix."
  type        = string
}

variable "environment" {
  description = "Environment name (e.g. prod)."
  type        = string
}

variable "vpc_cidr" {
  description = "CIDR block for the VPC. Subnets are carved as /24s: public = netnum 0.., private = netnum 10.. (one per AZ)."
  type        = string
}

variable "azs" {
  description = "Availability Zones. One public and one private subnet is created per AZ."
  type        = list(string)

  validation {
    condition     = length(var.azs) >= 2
    error_message = "RDS subnet group requires at least 2 Availability Zones."
  }
}
