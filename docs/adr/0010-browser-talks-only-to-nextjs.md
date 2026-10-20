# 0010: ブラウザは Next.js とだけ通信する

- ステータス: 採用(実装前)
- 日付: 2026-10-15
- 置き換えるもの: [0001](0001-ecs-fargate.md) の構成図の`/api/*`、[0003](0003-ssr-calls-api-via-service-connect.md)、[0007](0007-prod-cost-compromises.md) のSGのうちALBからapiへの80番

## 採用

ブラウザは Next.js とだけ通信し、更新は Server Actions で送る。Laravel は Service Connect の内側に置き、CloudFront と ALB に `/api/*` の経路を置かない。

