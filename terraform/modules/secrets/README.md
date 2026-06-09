# terraform/modules/secrets

RDS マスターパスワードと Laravel APP_KEY を Terraform 側で生成し、AWS Secrets Manager に保存するモジュール。

## 何を作るか

| リソース | 値 | 用途 |
|---|---|---|
| `random_password.db_master` | 32 文字、`!#$%&*+-=?_` 限定の特殊文字 | RDS の master_password。RDS 禁止文字(`/ @ " ` ` スペース)を避けるため特殊文字集合を絞る |
| `random_id.app_key` | 32 バイト乱数 | Laravel APP_KEY の原データ。`base64:<b64_std>` 形式で保存(`php artisan key:generate` と同じ書式) |
| `aws_secretsmanager_secret.db_password` | name: `<project>-<env>/rds/master-password` | RDS パスワード |
| `aws_secretsmanager_secret.app_key` | name: `<project>-<env>/laravel/app-key` | Laravel APP_KEY |

KMS は AWS マネージドキー(`aws/secretsmanager`)。CMK は月 $1/key かかり、本プロジェクトの要件では過剰投資のため不採用。CMK は将来的にクロスアカウント共有や厳格な監査要件が出た時点で導入する。

## 設計判断

### なぜ Terraform 側で生成して Secrets Manager に保存するのか

RDS の master_password は RDS リソース作成時に必須で、後から「シークレットだけ後付け」だと差し替えの手戻りが大きい(§11.1)。最初から:

1. `random_password` で Terraform が乱数生成
2. Secrets Manager に保存
3. その平文を database モジュールに `master_password` として渡す
4. ECS Task Definition には ARN 経由で注入

の経路を用意することで、後付け時の差し替えコストを回避する。

### RDS の `manage_master_user_password` を使わない理由

AWS は RDS 側で `manage_master_user_password = true` にすると Secrets Manager のシークレットを自動作成・自動ローテーションする機能を提供している。シンプルだが本プロジェクトでは不採用:

- ARN を明示的に Terraform 管理下に置けず、IaC で「どこに何があるか」を可視化するというポートフォリオの目的(§6.2)と整合しない
- ローテーションは MVP では不要(将来必要になったらこのモジュールに rotation lambda + schedule を足せばよい)

ただしこの判断はトレードオフがあり、運用負荷を最小化する観点では `manage_master_user_password` の方が優位。要件が変われば再評価する。

### `db_password_value` を sensitive 出力する理由

database モジュールは RDS リソースの `password` 引数に平文を要求する(`password_wo` や Secrets Manager 参照はまだ標準パターンになっていない)。secrets → database のモジュール間配線として **sensitive output のまま渡す** のが現状のベター。

ECS Task Definition への注入は **ARN 経由**(`secrets` ブロック)で行うこと。`db_password_value` を environment ブロックなどに渡すと plaintext が Task Definition に焼き付いてしまう。

### `recovery_window_in_days` の既定値

prod 想定で 7 日を既定とした。ポートフォリオで `destroy/recreate` を繰り返す検証フェーズでは、同名再作成が 7〜30 日ロックされて詰むことがあるため、その間だけ `recovery_window_in_days = 0` に上書きすると運用が滑らかになる。AWS 側の制約により `0` か `7〜30` のみが受理される(`variables.tf` で validation 済み)。

## 主要 outputs

| 名前 | sensitive | 用途 |
|---|---|---|
| `db_password_secret_arn` | × | ECS Task Definition の `secrets` ブロック |
| `db_password_secret_name` | × | 運用時の参照(AWS CLI で `get-secret-value` 等) |
| `db_password_value` | ○ | database モジュールへの master_password 配線 **のみ** |
| `app_key_secret_arn` | × | ECS Task Definition の `secrets` ブロック(APP_KEY 注入) |
| `app_key_secret_name` | × | 運用時の参照 |
