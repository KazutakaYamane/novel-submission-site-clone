# terraform/environments/prod

prod 環境の本体。`terraform/modules/*` を組み合わせて、作業時に apply、終了時に destroy する。

| モジュール | 内容 |
|---|---|
| `network` | VPC / subnet / SG |
| `cache` | ElastiCache(Redis) |
| `secrets` | Secrets Manager(DB password / APP_KEY) |
| `database` | RDS for MySQL |
| `ecs-service` | ACM(ap-northeast-1)+ ALB + ECS 2サービス + Service Connect |
| `cloudfront` | ACM(us-east-1)+ 静的アセット S3 + CloudFront |

## 前提

1. `terraform/bootstrap/` を apply 済み(state 用 S3 バケット)。
2. `terraform/environments/prod-persistent/` を apply 済みで、出力 `subdomain_name_servers` を親ゾーンに NS として登録済み。委譲が効いていないと ACM の DNS 検証が終わらない。

## 手順

```bash
cp terraform.tfvars.example terraform.tfvars   # domain_name を編集
terraform init
terraform apply
```
