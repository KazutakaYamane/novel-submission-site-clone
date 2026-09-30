# terraform/environments/prod-persistent

prod を destroy しても残しておくリソースの root。原則として destroy しない。

| ファイル | リソース | 残す理由 |
|---|---|---|
| `dns.tf` | サブドメインの Route 53 hosted zone | 作り直すと NS が変わり、親ゾーンへの再登録が必要になる |
| `ecr.tf` | ECR 3リポジトリ(laravel-app / laravel-nginx / nextjs) | push 済みイメージを失わない |
| `github-oidc.tf` | GitHub Actions 用 OIDC プロバイダと deploy / plan ロール | prod がない間も CI が認証・push できる |

## 前提

`terraform/bootstrap/` を apply 済み(state 用 S3 バケット)。

## 手順(初回)

```bash
cp terraform.tfvars.example terraform.tfvars   # domain_name を編集
terraform init
terraform apply
terraform output subdomain_name_servers
```

