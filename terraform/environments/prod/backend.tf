terraform {
  # bootstrap/ で作成した S3 に state を保存する。ロックは S3 ネイティブロック
  # (use_lockfile=true: <key>.tflock を条件付き書き込みで生成)を使い、DynamoDB は使わない。
  # 値は `terraform -chdir=terraform/bootstrap output backend_snippet` で確認可能。
  backend "s3" {
    bucket       = "novel-submission-site-clone-tfstate-107155696364"
    key          = "prod/terraform.tfstate"
    region       = "ap-northeast-1"
    use_lockfile = true
    encrypt      = true
  }
}
