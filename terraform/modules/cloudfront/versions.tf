terraform {
  required_version = "~> 1.11"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 6.0"
      # CloudFront 用 ACM は us-east-1 必須(§11.1)。呼び出し側で
      # providers = { aws = aws, aws.us_east_1 = aws.us_east_1 } を渡す。
      configuration_aliases = [aws.us_east_1]
    }
  }
}
