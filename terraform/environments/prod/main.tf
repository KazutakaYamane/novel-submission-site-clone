# prod 環境のエントリポイント。
# 各モジュールをここから呼び出す。フェーズ1 で順次追加していく:
#   - network    : VPC / subnet / SG       ✓
#   - secrets    : Secrets Manager         ✓
#   - database   : RDS for MySQL
#   - ecs-service: ALB + ECS Service + ACM(ap-northeast-1)
#   - cloudfront : S3(SPA) + CloudFront + ACM(us-east-1)+ Route 53

module "network" {
  source = "../../modules/network"

  project              = var.project
  environment          = var.environment
  vpc_cidr             = var.vpc_cidr
  azs                  = var.azs
  public_subnet_cidrs  = var.public_subnet_cidrs
  private_subnet_cidrs = var.private_subnet_cidrs
}

module "secrets" {
  source = "../../modules/secrets"

  project                 = var.project
  environment             = var.environment
  recovery_window_in_days = var.secrets_recovery_window_in_days
}
