# syntax=docker/dockerfile:1.7
# ------------------------------------------------------------
# Nginx: php-fpm の前段。本番では ECS タスク内に同居(sidecar)
# ------------------------------------------------------------
FROM nginx:1.27-alpine

# ヘルスチェックに使う curl
RUN apk add --no-cache curl tzdata \
    && cp /usr/share/zoneinfo/Asia/Tokyo /etc/localtime \
    && echo "Asia/Tokyo" > /etc/timezone

# 設定を上書き
RUN rm /etc/nginx/conf.d/default.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# 本番想定: ALB のヘルスチェックは /health を叩く
HEALTHCHECK --interval=10s --timeout=3s --retries=3 \
    CMD curl -fsS http://127.0.0.1/health || exit 1

EXPOSE 80
