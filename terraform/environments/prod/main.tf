# CloudFront → Next.js(ECS) →[Service Connect]→ Laravel(ECS) → RDS / ElastiCache
# hosted zone と ECR は ../prod-persistent が管理する(data.tf)。

module "network" {
  source = "../../modules/network"

  project     = var.project
  environment = var.environment
  vpc_cidr    = var.vpc_cidr
  azs         = var.azs
}

module "cache" {
  source = "../../modules/cache"

  project            = var.project
  environment        = var.environment
  private_subnet_ids = module.network.private_subnet_ids
  security_group_id  = module.network.redis_security_group_id
}

module "secrets" {
  source = "../../modules/secrets"

  project                 = var.project
  environment             = var.environment
  recovery_window_in_days = var.secrets_recovery_window_in_days
}

module "database" {
  source = "../../modules/database"

  project            = var.project
  environment        = var.environment
  private_subnet_ids = module.network.private_subnet_ids
  security_group_id  = module.network.rds_security_group_id
  master_password    = module.secrets.db_password_value
}

module "ecs_service" {
  source = "../../modules/ecs-service"

  project     = var.project
  environment = var.environment

  domain_name = var.domain_name
  zone_id     = data.aws_route53_zone.this.zone_id

  vpc_id                    = module.network.vpc_id
  public_subnet_ids         = module.network.public_subnet_ids
  alb_security_group_id     = module.network.alb_security_group_id
  ecs_web_security_group_id = module.network.ecs_web_security_group_id
  ecs_api_security_group_id = module.network.ecs_api_security_group_id

  web_image       = "${data.aws_ecr_repository.this["nextjs"].repository_url}:${var.image_tag}"
  api_app_image   = "${data.aws_ecr_repository.this["laravel-app"].repository_url}:${var.image_tag}"
  api_nginx_image = "${data.aws_ecr_repository.this["laravel-nginx"].repository_url}:${var.image_tag}"

  db_host     = module.database.endpoint_address
  db_name     = module.database.db_name
  db_username = module.database.master_username
  redis_url   = module.cache.redis_url

  db_password_secret_arn = module.secrets.db_password_secret_arn
  app_key_secret_arn     = module.secrets.app_key_secret_arn
}

module "cloudfront" {
  source = "../../modules/cloudfront"

  providers = {
    aws           = aws
    aws.us_east_1 = aws.us_east_1
  }

  project     = var.project
  environment = var.environment

  domain_name        = var.domain_name
  zone_id            = data.aws_route53_zone.this.zone_id
  origin_domain_name = module.ecs_service.origin_domain_name
}
