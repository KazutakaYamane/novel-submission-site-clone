# terraform/modules/ecs-service

API 層(Laravel)とレンダリング層(Next.js)の実行基盤一式を構築するモジュール。

## 構成

- **ACM(ap-northeast-1)**: `<domain>` + `*.<domain>`(ワイルドカードは CloudFront → ALB のオリジン用 `origin.<domain>` をカバーするため)。DNS 検証レコードも自動作成
- **`origin.<domain>` A(Alias) → ALB**: CloudFront がオリジンとして使う FQDN
- **Cloud Map HTTP 名前空間**: Service Connect 用(ADR-INFRA 決定7)
- **ALB + 2 Target Group + Listener**: `default` → Next.js TG(:3000) / `/api/*` → Laravel TG(:80)。ヘルスチェックは web=`/`、api=`/health`(nginx の DB 非依存 200)
- **ECS Cluster(Fargate)** + **2 Service**(`terraform-aws-modules/ecs/aws//modules/service` v6 を薄くラップ):

| Service | コンテナ | サイズ | Service Connect |
|---|---|---|---|
| web (Next.js) | web(:3000) | 0.5vCPU/1GB Graviton | クライアント専用(`http://api:80` を呼ぶ) |
| api (Laravel) | nginx(:80) + app(php-fpm:9000) | 0.25vCPU/0.5GB Graviton | サーバー(`api:80` として公開) |

- **Auto Scaling**: 1〜2 タスク(モジュール既定の CPU/メモリ target tracking)

## 設計判断

- **registry モジュールの service サブモジュールを薄くラップ**(§6.2): Task/Exec IAM ロール・CloudWatch Log Group(保持7日)・Application Auto Scaling の組み立てを任せ、このモジュールは「何をどう動かすか」(コンテナ定義・Service Connect・LB 紐付け)だけを記述する。
- **SG はモジュールに作らせない**: network モジュールの分離 SG(web / api)を使い、East-West・RDS・Redis の到達制御を一箇所で見せる。
- **nginx の upstream は `PHP_FPM_HOST` env で切替**: awsvpc はタスク内コンテナ間が localhost 通信だがコンテナ名の DNS 解決はない。ローカル compose では `app`、Fargate では `127.0.0.1` を注入する(knowledge doc §7.3 の「名前を揃えれば解決される」は誤りだったため補正)。
- **機密は Secrets Manager の ARN 参照で注入**: APP_KEY / DB_PASSWORD / REDIS_URL。Task Definition に平文を書かない(§11.1)。
- **enable_execute_command**: 障害調査用に ECS Exec を有効化(runbook と対応)。

## 主要 outputs

| 名前 | 用途 |
|---|---|
| `origin_domain_name` | cloudfront モジュールのオリジン FQDN |
| `alb_dns_name` / `alb_zone_id` | デバッグ・追加レコード用 |
| `cluster_name` / `web_service_name` / `api_service_name` | デプロイ(`aws ecs update-service`) |
| `certificate_arn` | ALB 用 ACM(検証済み) |
