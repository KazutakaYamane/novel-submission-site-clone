# syntax=docker/dockerfile:1.7
# ------------------------------------------------------------
# Nginx: php-fpm の前段。本番では ECS タスク内に同居(sidecar)
# - base: 共通設定(テンプレート + envsubst)
# - dev : compose 用。app/public は bind mount されるため何も焼かない
# - prod: Fargate 用。bind mount 不可のため app/public を焼き込む
#   (docker/app/Dockerfile の base/dev/prod 構成と対応)
# ------------------------------------------------------------
FROM nginx:1.27-alpine AS base

# ヘルスチェックに使う curl
RUN apk add --no-cache curl tzdata \
    && cp /usr/share/zoneinfo/Asia/Tokyo /etc/localtime \
    && echo "Asia/Tokyo" > /etc/timezone

# 設定はテンプレートとして配置する。公式イメージの entrypoint
# (20-envsubst-on-templates.sh)が起動時に envsubst して
# /etc/nginx/conf.d/default.conf を生成する。
# ベースイメージ由来の default.conf は生成物で上書きされるが、明示的に消しておく。
RUN rm /etc/nginx/conf.d/default.conf
COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template

# php-fpm の宛先(compose のサービス名)。prod ステージで上書きする。
ENV PHP_FPM_HOST=app

# 本番想定: ALB のヘルスチェックは /health を叩く
HEALTHCHECK --interval=10s --timeout=3s --retries=3 \
    CMD curl -fsS http://127.0.0.1/health || exit 1

EXPOSE 80

# ------------------------------------------------------------
# dev: compose が ./app/public を read-only bind mount する(compose.yaml)
# ------------------------------------------------------------
FROM base AS dev

# ------------------------------------------------------------
# prod: ECS Fargate の sidecar(laravel-nginx イメージ)
# - Fargate は bind mount 不可のため、try_files / realpath_root が参照する
#   app/public をイメージに焼き込む(php-fpm 側と同一パス /var/www/html/public)
# - ビルド時の前提: コンテキスト直下に app/ がスキャフォールド済みであること
# ------------------------------------------------------------
FROM base AS prod

# awsvpc はタスク内コンテナが localhost を共有する(コンテナ名 DNS 解決なし)。
# Task Definition でも同値を注入しているが、イメージ単体でも本番既定値にしておく。
ENV PHP_FPM_HOST=127.0.0.1

COPY app/public /var/www/html/public
