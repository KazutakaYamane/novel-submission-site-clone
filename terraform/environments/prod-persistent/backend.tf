terraform {
  # prod 本体とは別の state に分離する。
  # 理由: 「作業時 apply → 終了時 destroy」運用で prod を destroy しても、
  #   - hosted zone(destroy すると NS 4 値が変わり、親アカウントへの再委譲が必要になる)
  #   - ECR(イメージの再 push が必要になる)
  # を生き残らせるため。この root は原則 destroy しない。維持コストは zone $0.50/月 +
  # ECR ストレージ(直近10イメージ)のみ。
  backend "s3" {
    bucket       = "novel-submission-site-clone-tfstate-107155696364"
    key          = "prod-persistent/terraform.tfstate"
    region       = "ap-northeast-1"
    use_lockfile = true
    encrypt      = true
  }
}
