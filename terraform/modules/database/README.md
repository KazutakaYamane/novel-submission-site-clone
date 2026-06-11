# terraform/modules/database

RDS for MySQL を構築するモジュール。

## 構成

- `aws_db_subnet_group`: private subnet × 2AZ(Single-AZ 運用でも 2AZ 必須。§11.1)
- `aws_db_parameter_group`: `utf8mb4` / `utf8mb4_unicode_ci`(ローカル compose の MySQL と同一設定)
- `aws_db_instance`: MySQL 8.0 / `db.t4g.micro`(Graviton)/ Single-AZ / gp3 20GB / 暗号化あり

## 設計判断

- **MySQL 8.0**: カクヨム実環境と整合(セッション5)。全文検索は InnoDB FULLTEXT + ngram parser を第一候補。
- **Single-AZ**: ポートフォリオの可用性要件に対して Multi-AZ の固定費(約2倍)は過剰。
- **パスワードは secrets モジュール(random_password → Secrets Manager)から渡す**: RDS の `manage_master_user_password`(RDS 管理のローテーション)とは二重管理になるため使わない。ECS への注入は Secrets Manager の ARN 経由で行い、Task Definition に平文を埋め込まない。
- **`skip_final_snapshot = true`(既定)**: destroy/recreate を繰り返すポートフォリオ運用向け。実運用に転用する場合は false。

## 主要 outputs

| 名前 | 用途 |
|---|---|
| `endpoint_address` | ECS タスクの `DB_HOST` |
| `db_name` / `master_username` | ECS タスクの `DB_DATABASE` / `DB_USERNAME` |
