# syntax=docker/dockerfile:1.7
# ------------------------------------------------------------
# Vite 開発サーバ用(ローカル開発のみ)
# - 本番ではビルド成果物を public/build/ に出して Nginx から配信
# - ECS 本番タスクには不要
# ------------------------------------------------------------
FROM node:22-alpine AS dev

RUN apk add --no-cache git tzdata \
    && cp /usr/share/zoneinfo/Asia/Tokyo /etc/localtime

WORKDIR /var/www/html

# HMR 用のポート(vite のデフォルト)
EXPOSE 5173

# 依存解決はホスト側からマウントしてから行う想定なので、
# Dockerfile では何もしない(初回 compose up で npm ci を流す)
CMD ["sh", "-c", "npm ci && npm run dev -- --host 0.0.0.0"]
