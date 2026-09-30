terraform {
  # prod を destroy しても残すリソース(hosted zone / ECR / GitHub OIDC)用の state。
  # この root は原則 destroy しない。
  backend "s3" {
    bucket       = "novel-submission-site-clone-tfstate-107155696364"
    key          = "prod-persistent/terraform.tfstate"
    region       = "ap-northeast-1"
    use_lockfile = true
    encrypt      = true
  }
}
