terraform {
  # ロックは S3 ネイティブロック(use_lockfile)。DynamoDB は使わない。
  backend "s3" {
    bucket       = "novel-submission-site-clone-tfstate-107155696364"
    key          = "prod/terraform.tfstate"
    region       = "ap-northeast-1"
    use_lockfile = true
    encrypt      = true
  }
}
