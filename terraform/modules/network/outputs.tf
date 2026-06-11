output "vpc_id" {
  description = "ID of the VPC."
  value       = aws_vpc.this.id
}

output "vpc_cidr_block" {
  description = "CIDR block of the VPC."
  value       = aws_vpc.this.cidr_block
}

output "public_subnet_ids" {
  description = "IDs of public subnets (ECS Service / ALB を配置)。"
  value       = aws_subnet.public[*].id
}

output "private_subnet_ids" {
  description = "IDs of private subnets (RDS / ElastiCache を配置)。"
  value       = aws_subnet.private[*].id
}

output "alb_security_group_id" {
  description = "Security Group ID for the ALB."
  value       = aws_security_group.alb.id
}

output "ecs_web_security_group_id" {
  description = "Security Group ID for Next.js (web) ECS tasks."
  value       = aws_security_group.ecs_web.id
}

output "ecs_api_security_group_id" {
  description = "Security Group ID for Laravel (api) ECS tasks."
  value       = aws_security_group.ecs_api.id
}

output "rds_security_group_id" {
  description = "Security Group ID for RDS."
  value       = aws_security_group.rds.id
}

output "redis_security_group_id" {
  description = "Security Group ID for ElastiCache (Redis)."
  value       = aws_security_group.redis.id
}
