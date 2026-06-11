output "zone_id" {
  description = "サブドメイン hosted zone の ID(prod 側は data source で参照するため直接は使わないが、確認用に出力)。"
  value       = aws_route53_zone.this.zone_id
}

output "subdomain_name_servers" {
  description = "【apply 後の手動作業】この NS 4 値を、親アカウントの kyyk517.com ゾーンにサブドメインの NS レコードとして登録する。委譲が効くまで ACM の DNS 検証(= prod 側の apply)は完了しない。"
  value       = aws_route53_zone.this.name_servers
}

output "ecr_repository_urls" {
  description = "ECR リポジトリ URL(手動 push / CI から参照)。"
  value       = { for k, repo in aws_ecr_repository.this : k => repo.repository_url }
}

output "github_deploy_role_arn" {
  description = "GitHub Actions の deploy ワークフローが引き受けるロール ARN(deploy.yml の role-to-assume)。"
  value       = aws_iam_role.github_deploy.arn
}

output "github_plan_role_arn" {
  description = "GitHub Actions の terraform plan ワークフローが引き受けるロール ARN(tf-plan.yml の role-to-assume)。"
  value       = aws_iam_role.github_plan.arn
}
