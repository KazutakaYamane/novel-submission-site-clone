# ---------------------------------------------------------------------------
# prod-persistent root が管理するリソースへの参照
# ---------------------------------------------------------------------------
# hosted zone と ECR は「作業時 apply → 終了時 destroy」運用で生き残らせるため
# ../prod-persistent に分離した(2026-06-11)。
#   - zone を destroy すると NS 4 値が変わり、親アカウントへの再委譲が必要になる
#   - ECR を destroy すると push 済みイメージを失う
# この root の apply には prod-persistent が apply 済みであることが前提。
# terraform_remote_state ではなく data source を使う(state ファイル構造への依存を
# 避け、参照が AWS API に対して常に最新になる)。

data "aws_route53_zone" "this" {
  name         = var.domain_name
  private_zone = false
}

data "aws_ecr_repository" "this" {
  for_each = toset(["laravel-app", "laravel-nginx", "nextjs"])

  name = "${var.project}/${each.key}"
}
