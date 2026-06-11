# terraform/modules/network

VPC・サブネット・ルートテーブル・Security Group をまとめて構築するモジュール。

## 構成

- **VPC**(`var.vpc_cidr`)+ Internet Gateway
- **Public subnet × 2 AZ**: ECS Service(Fargate / Graviton)配置用。`map_public_ip_on_launch = true`。NAT Gateway 不使用(§6.2)で外向き通信は Public IP に任せる。
- **Private subnet × 2 AZ**: RDS / ElastiCache 配置用。インターネット経路なし。RDS subnet group は Single-AZ 運用でも 2AZ 必須(§11.1)。
- **Security Group 5つ**(方式A: web / api の独立 ECS サービス。ADR-INFRA 決定6〜8):

| SG | ingress | egress |
|---|---|---|
| `alb` | 0.0.0.0/0:443 | all |
| `ecs-web` (Next.js) | alb-sg:3000 | all(ECR / CloudWatch / Service Connect / Redis 用)|
| `ecs-api` (Laravel) | alb-sg:80、**ecs-web-sg:80(East-West / Service Connect)** | all |
| `rds` | **ecs-api-sg:3306 のみ**(web は API 契約面越しにしか DB に届かない) | なし(レスポンスは stateful) |
| `redis` | ecs-web-sg:6379(ISR 共有キャッシュ)、ecs-api-sg:6379(session/cache/queue) | なし |

## 主要 outputs

| 名前 | 用途 |
|---|---|
| `vpc_id` | 他モジュール全般 |
| `public_subnet_ids` | ALB / ECS Service |
| `private_subnet_ids` | RDS / ElastiCache subnet group |
| `alb_security_group_id` | ecs-service モジュール(ALB) |
| `ecs_web_security_group_id` | ecs-service モジュール(Next.js Service) |
| `ecs_api_security_group_id` | ecs-service モジュール(Laravel Service) |
| `rds_security_group_id` | database モジュール |
| `redis_security_group_id` | cache モジュール |

## 設計判断

- **SG ルールは新しい `aws_vpc_security_group_*_rule` リソースで個別管理**: 旧 inline `ingress` / `egress` ブロックは差分検知が弱く、provider 6.x の推奨と乖離する。
- **AZ 個数の最小値を validation で 2 に固定**: 単一 AZ で apply されると RDS subnet group が後で失敗する。早めにエラー化する。
