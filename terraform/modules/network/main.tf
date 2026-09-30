locals {
  name = "${var.project}-${var.environment}"
}

# ---------------------------------------------------------------------------
# VPC
# ---------------------------------------------------------------------------

resource "aws_vpc" "this" {
  cidr_block           = var.vpc_cidr
  enable_dns_hostnames = true
  enable_dns_support   = true

  tags = { Name = "${local.name}-vpc" }
}

resource "aws_internet_gateway" "this" {
  vpc_id = aws_vpc.this.id

  tags = { Name = "${local.name}-igw" }
}

# ---------------------------------------------------------------------------
# Subnets
# ---------------------------------------------------------------------------

# NAT を置かないため、ECS タスクは public subnet で Public IP を持って外向き通信する。
resource "aws_subnet" "public" {
  count = length(var.azs)

  vpc_id                  = aws_vpc.this.id
  cidr_block              = cidrsubnet(var.vpc_cidr, 8, count.index)
  availability_zone       = var.azs[count.index]
  map_public_ip_on_launch = true

  tags = {
    Name = "${local.name}-public-${var.azs[count.index]}"
    Tier = "public"
  }
}

resource "aws_subnet" "private" {
  count = length(var.azs)

  vpc_id            = aws_vpc.this.id
  cidr_block        = cidrsubnet(var.vpc_cidr, 8, 10 + count.index)
  availability_zone = var.azs[count.index]

  tags = {
    Name = "${local.name}-private-${var.azs[count.index]}"
    Tier = "private"
  }
}

# ---------------------------------------------------------------------------
# Route tables
# ---------------------------------------------------------------------------

resource "aws_route_table" "public" {
  vpc_id = aws_vpc.this.id

  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.this.id
  }

  tags = { Name = "${local.name}-public-rt" }
}

resource "aws_route_table_association" "public" {
  count = length(aws_subnet.public)

  subnet_id      = aws_subnet.public[count.index].id
  route_table_id = aws_route_table.public.id
}

resource "aws_route_table" "private" {
  vpc_id = aws_vpc.this.id

  tags = { Name = "${local.name}-private-rt" }
}

resource "aws_route_table_association" "private" {
  count = length(aws_subnet.private)

  subnet_id      = aws_subnet.private[count.index].id
  route_table_id = aws_route_table.private.id
}

# ---------------------------------------------------------------------------
# Security Groups(ルール一覧は README.md)
# ---------------------------------------------------------------------------

# CloudFront を通らずに ALB へ届くと、nginx が信頼する CloudFront-Viewer-Address を偽装できる。
# ALB の ingress を CloudFront のオリジン向け IP に絞る
data "aws_ec2_managed_prefix_list" "cloudfront_origin_facing" {
  name = "com.amazonaws.global.cloudfront.origin-facing"
}

resource "aws_security_group" "alb" {
  name        = "${local.name}-alb"
  description = "ALB ingress from internet"
  vpc_id      = aws_vpc.this.id

  tags = { Name = "${local.name}-alb-sg" }
}

resource "aws_vpc_security_group_ingress_rule" "alb_https" {
  security_group_id = aws_security_group.alb.id
  prefix_list_id    = data.aws_ec2_managed_prefix_list.cloudfront_origin_facing.id
  from_port         = 443
  to_port           = 443
  ip_protocol       = "tcp"
  description       = "HTTPS from CloudFront origin-facing IPs"
}

resource "aws_vpc_security_group_egress_rule" "alb_all" {
  security_group_id = aws_security_group.alb.id
  cidr_ipv4         = "0.0.0.0/0"
  ip_protocol       = "-1"
  description       = "All egress"
}

# ----- ECS: web(Next.js) -----

resource "aws_security_group" "ecs_web" {
  name        = "${local.name}-ecs-web"
  description = "ECS tasks for Next.js (web)"
  vpc_id      = aws_vpc.this.id

  tags = { Name = "${local.name}-ecs-web-sg" }
}

resource "aws_vpc_security_group_ingress_rule" "web_from_alb" {
  security_group_id            = aws_security_group.ecs_web.id
  referenced_security_group_id = aws_security_group.alb.id
  from_port                    = 3000
  to_port                      = 3000
  ip_protocol                  = "tcp"
  description                  = "Next.js server port from ALB"
}

resource "aws_vpc_security_group_egress_rule" "web_all" {
  security_group_id = aws_security_group.ecs_web.id
  cidr_ipv4         = "0.0.0.0/0"
  ip_protocol       = "-1"
  description       = "All egress (ECR, CloudWatch, Service Connect to api, Redis, etc.)"
}

# ----- ECS: api(Laravel = nginx + php-fpm) -----

resource "aws_security_group" "ecs_api" {
  name        = "${local.name}-ecs-api"
  description = "ECS tasks for Laravel API (nginx + php-fpm)"
  vpc_id      = aws_vpc.this.id

  tags = { Name = "${local.name}-ecs-api-sg" }
}

resource "aws_vpc_security_group_ingress_rule" "api_from_alb" {
  security_group_id            = aws_security_group.ecs_api.id
  referenced_security_group_id = aws_security_group.alb.id
  from_port                    = 80
  to_port                      = 80
  ip_protocol                  = "tcp"
  description                  = "Nginx port from ALB (North-South: /api/*)"
}

# Service Connect は ingressPortOverride を使わないため、許可ポートはコンテナポート(8080)のまま。
# 8080 には Next.js タスクからしか届かないことを前提に、nginx は 8080 でだけユーザー IP のヘッダーを信頼する
resource "aws_vpc_security_group_ingress_rule" "api_from_web" {
  security_group_id            = aws_security_group.ecs_api.id
  referenced_security_group_id = aws_security_group.ecs_web.id
  from_port                    = 8080
  to_port                      = 8080
  ip_protocol                  = "tcp"
  description                  = "Nginx port from Next.js tasks (East-West: Service Connect)"
}

resource "aws_vpc_security_group_egress_rule" "api_all" {
  security_group_id = aws_security_group.ecs_api.id
  cidr_ipv4         = "0.0.0.0/0"
  ip_protocol       = "-1"
  description       = "All egress (ECR, CloudWatch, Secrets Manager, RDS, Redis, etc.)"
}

# ----- RDS -----

resource "aws_security_group" "rds" {
  name        = "${local.name}-rds"
  description = "RDS for MySQL ingress from Laravel ECS tasks"
  vpc_id      = aws_vpc.this.id

  tags = { Name = "${local.name}-rds-sg" }
}

resource "aws_vpc_security_group_ingress_rule" "rds_from_api" {
  security_group_id            = aws_security_group.rds.id
  referenced_security_group_id = aws_security_group.ecs_api.id
  from_port                    = 3306
  to_port                      = 3306
  ip_protocol                  = "tcp"
  description                  = "MySQL from Laravel ECS tasks"
}

# ----- ElastiCache(Redis) -----

resource "aws_security_group" "redis" {
  name        = "${local.name}-redis"
  description = "ElastiCache Redis ingress from ECS tasks (web + api)"
  vpc_id      = aws_vpc.this.id

  tags = { Name = "${local.name}-redis-sg" }
}

resource "aws_vpc_security_group_ingress_rule" "redis_from_web" {
  security_group_id            = aws_security_group.redis.id
  referenced_security_group_id = aws_security_group.ecs_web.id
  from_port                    = 6379
  to_port                      = 6379
  ip_protocol                  = "tcp"
  description                  = "Redis from Next.js tasks (ISR shared cache)"
}

resource "aws_vpc_security_group_ingress_rule" "redis_from_api" {
  security_group_id            = aws_security_group.redis.id
  referenced_security_group_id = aws_security_group.ecs_api.id
  from_port                    = 6379
  to_port                      = 6379
  ip_protocol                  = "tcp"
  description                  = "Redis from Laravel tasks (session/cache/queue)"
}
