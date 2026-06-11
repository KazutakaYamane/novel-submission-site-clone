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

# Public: ECS タスクをここに配置し Public IP で外向き通信(NAT 不使用、§6.2)。
resource "aws_subnet" "public" {
  count = length(var.public_subnet_cidrs)

  vpc_id                  = aws_vpc.this.id
  cidr_block              = var.public_subnet_cidrs[count.index]
  availability_zone       = var.azs[count.index]
  map_public_ip_on_launch = true

  tags = {
    Name = "${local.name}-public-${var.azs[count.index]}"
    Tier = "public"
  }
}

# Private: RDS / ElastiCache 用。インターネット経路なし。
resource "aws_subnet" "private" {
  count = length(var.private_subnet_cidrs)

  vpc_id            = aws_vpc.this.id
  cidr_block        = var.private_subnet_cidrs[count.index]
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

# private に対しては意図的にインターネット経路を引かない。
# RDS / ElastiCache の通信は VPC 内に閉じる。
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
# Security Groups
# ---------------------------------------------------------------------------
# トポロジ(方式A: Next.js / Laravel を独立 ECS サービスに分離。ADR-INFRA 決定6〜8):
#
#   internet --(443)--> ALB ┬--(3000)--> ECS web(Next.js)
#                           └--(80)----> ECS api(nginx + php-fpm)
#   ECS web --(80, East-West / Service Connect)--> ECS api
#   ECS api --(3306)--> RDS
#   ECS web / api --(6379)--> ElastiCache(Redis)
#     (web: ISR 共有キャッシュ cacheHandler / api: Laravel session・cache・queue)
#
# - ECS SG は web / api に分離する。スケール特性・通信経路が異なり、
#   「api は ALB と web からのみ」「RDS は api からのみ」を SG で表現するため。
# - Service Connect の East-West は client(web) Envoy → server(api) Envoy の直接通信。
#   ingressPortOverride を使わないため、許可ポートはコンテナポート(80)と同じ。
# - Egress は ECS のみ all-out(ECR / CloudWatch / Secrets Manager 取得用)。
#   RDS / Redis は egress 不要(応答パケットは SG ステートフルで通る)。
# - TODO(フェーズ2以降の堅牢化): ALB の 443 ingress を CloudFront の
#   managed prefix list(com.amazonaws.global.cloudfront.origin-facing)に絞る。

resource "aws_security_group" "alb" {
  name        = "${local.name}-alb"
  description = "ALB ingress from internet"
  vpc_id      = aws_vpc.this.id

  tags = { Name = "${local.name}-alb-sg" }
}

resource "aws_vpc_security_group_ingress_rule" "alb_https" {
  security_group_id = aws_security_group.alb.id
  cidr_ipv4         = "0.0.0.0/0"
  from_port         = 443
  to_port           = 443
  ip_protocol       = "tcp"
  description       = "HTTPS from internet"
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

# East-West: Next.js の SSR/ISR サーバーサイド fetch(Service Connect 経由)。
# ADR-INFRA 決定7。公開 ALB をヘアピンせず VPC 内に閉じる。
resource "aws_vpc_security_group_ingress_rule" "api_from_web" {
  security_group_id            = aws_security_group.ecs_api.id
  referenced_security_group_id = aws_security_group.ecs_web.id
  from_port                    = 80
  to_port                      = 80
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

# DB へ到達できるのは Laravel(api)のみ。web(Next.js)は API 契約面越しにしか
# データへアクセスできない、という責務境界を SG でも表現する。
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

# web: Next.js ISR 共有キャッシュ(custom cacheHandler)。ADR-INFRA 決定8。
resource "aws_vpc_security_group_ingress_rule" "redis_from_web" {
  security_group_id            = aws_security_group.redis.id
  referenced_security_group_id = aws_security_group.ecs_web.id
  from_port                    = 6379
  to_port                      = 6379
  ip_protocol                  = "tcp"
  description                  = "Redis from Next.js tasks (ISR shared cache)"
}

# api: Laravel の session / cache / queue。
resource "aws_vpc_security_group_ingress_rule" "redis_from_api" {
  security_group_id            = aws_security_group.redis.id
  referenced_security_group_id = aws_security_group.ecs_api.id
  from_port                    = 6379
  to_port                      = 6379
  ip_protocol                  = "tcp"
  description                  = "Redis from Laravel tasks (session/cache/queue)"
}
