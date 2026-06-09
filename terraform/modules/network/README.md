# terraform/modules/network

VPC・サブネット・ルートテーブル・Security Group をまとめて構築するモジュール。

## 構成

- **VPC**(`var.vpc_cidr`)+ Internet Gateway
- **Public subnet × 2 AZ**: ECS Service(Fargate / Graviton)配置用。`map_public_ip_on_launch = true`。NAT Gateway 不使用(§6.2)で外向き通信は Public IP に任せる。
- **Private subnet × 2 AZ**: RDS / ElastiCache 配置用。インターネット経路なし。RDS subnet group は Single-AZ 運用でも 2AZ 必須(§11.1)。
- **Security Group 3つ**: トポロジ `internet → ALB → ECS → RDS` を SG レベルで段階的に絞り込み:

| SG | ingress | egress |
|---|---|---|
| `alb` | 0.0.0.0/0:443 | all |
| `ecs` | alb-sg:80 | all(ECR / CloudWatch / Secrets Manager / RDS 取得用)|
| `rds` | ecs-sg:3306 | なし(レスポンスは stateful) |

## 主要 outputs

| 名前 | 用途 |
|---|---|
| `vpc_id` | 他モジュール全般 |
| `public_subnet_ids` | ALB / ECS Service |
| `private_subnet_ids` | RDS subnet group |
| `alb_security_group_id` | ALB モジュール |
| `ecs_security_group_id` | ECS Service モジュール |
| `rds_security_group_id` | RDS モジュール |

## 設計判断

- **SG ルールは新しい `aws_vpc_security_group_*_rule` リソースで個別管理**: 旧 inline `ingress` / `egress` ブロックは差分検知が弱く、provider 6.x の推奨と乖離する。
- **AZ 個数の最小値を validation で 2 に固定**: 単一 AZ で apply されると RDS subnet group が後で失敗する。早めにエラー化する。
