# ../prod-persistent が管理するリソース。この root より先に apply しておくこと。
# terraform_remote_state ではなく data source で引き、相手の state 構造に依存しない。

data "aws_route53_zone" "this" {
  name         = var.domain_name
  private_zone = false
}

data "aws_ecr_repository" "this" {
  for_each = toset(["laravel-app", "laravel-nginx", "nextjs"])

  name = "${var.project}/${each.key}"
}
