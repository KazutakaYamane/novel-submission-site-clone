# terraform/modules/network

VPC・サブネット・ルートテーブル・Security Group をまとめて構築するモジュール。

## 構成
| SG | ingress | egress |
|---|---|---|
| `alb` | CloudFront のオリジン向け prefix list:443 | all |
| `ecs-web` (Next.js) | alb-sg:3000 | all |
| `ecs-api` (Laravel) | alb-sg:80、ecs-web-sg:8080(East-West / Service Connect) | all |
| `rds` | ecs-api-sg:3306  | なし |
| `redis` | ecs-web-sg:6379(ISR 共有キャッシュ)、ecs-api-sg:6379(session/cache/queue) | なし |

RDS / Redis は SG がステートフルなので egress ルールを持たない。

## 主要 outputs

| 名前 | 用途 |
|---|---|
| `public_subnet_ids` | ALB / ECS Service |
| `private_subnet_ids` | RDS / ElastiCache subnet group |
| `alb_security_group_id` / `ecs_web_security_group_id` / `ecs_api_security_group_id` | ecs-service モジュール |
| `rds_security_group_id` | database モジュール |
| `redis_security_group_id` | cache モジュール |
