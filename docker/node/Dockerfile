# syntax=docker/dockerfile:1.7
# ------------------------------------------------------------
# Next.js 開発サーバ用(ローカル開発のHMRのみ)
# - 本番は GitHub Actions で Next.js を ARM64 イメージ(output: 'standalone')に
#   ビルドし、独立した ECS サービス(方式A)としてデプロイする。
# - 本番の静的アセット(/_next/static)は S3 sync + CloudFront 配信。
# - 本番イメージはこの Dockerfile ではなく frontend/ 側で別途ビルドする想定。
#   (この dev ステージは ECS には載せない)
# ------------------------------------------------------------
FROM node:22-alpine AS dev

RUN apk add --no-cache git tzdata \
    && cp /usr/share/zoneinfo/Asia/Tokyo /etc/localtime

WORKDIR /app

# Next.js dev server のデフォルトポート
EXPOSE 3000

# 依存解決はホスト側からマウントしてから行う想定なので、
# Dockerfile では何もしない(初回 compose up で npm ci を流す)。
# next dev を 0.0.0.0 で待受し、コンテナ外(ホストブラウザ)から到達可能にする。
CMD ["sh", "-c", "npm ci && npm run dev -- -H 0.0.0.0 -p 3000"]
