# syntax=docker/dockerfile:1.7
# ------------------------------------------------------------
# Next.js: ローカル開発(HMR)用 dev と本番(ECS 独立サービス=方式A)用 prod
# - dev : compose 用。ソースは bind mount、npm ci + next dev を起動時に実行
# - deps/build/prod: 本番イメージ。ARM64(Graviton)向けに
#   `docker buildx build --platform linux/arm64 --target prod` でビルドする。
#   output: 'standalone'(next.config.ts)で server.js + 最小 node_modules を生成。
#   sharp(next の optionalDependencies)は npm ci を実行したプラットフォームの
#   ネイティブバイナリ(@img/sharp-linux-arm64)が入る
# - /_next/static は S3 + CloudFront 配信が本線だが、ALB 直叩きやローカル検証
#   でも動くようイメージにも同梱する。
# ------------------------------------------------------------
FROM node:22-alpine AS base

RUN apk add --no-cache tzdata \
    && cp /usr/share/zoneinfo/Asia/Tokyo /etc/localtime

WORKDIR /app

# ------------------------------------------------------------
# dev: ローカル開発(HMR)のみ。ECS には載せない
# ------------------------------------------------------------
FROM base AS dev

RUN apk add --no-cache git

# Next.js dev server のデフォルトポート
EXPOSE 3000

# 依存解決はホスト側からマウントしてから行う想定なので、
# Dockerfile では何もしない(初回 compose up で npm ci を流す)。
# next dev を 0.0.0.0 で待受し、コンテナ外(ホストブラウザ)から到達可能にする。
CMD ["sh", "-c", "npm ci && npm run dev -- -H 0.0.0.0 -p 3000"]

# ------------------------------------------------------------
# deps: 依存解決(lockfile が変わらない限りキャッシュされる)
# ------------------------------------------------------------
FROM base AS deps

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

# ------------------------------------------------------------
# build: next build(standalone 出力)
# ------------------------------------------------------------
FROM base AS build

ENV NEXT_TELEMETRY_DISABLED=1
# API_REWRITE_URL は渡さない。本番の /api/* は CloudFront/ALB が Laravel へ直接振り分けるため、
# next.config.ts は API_REWRITE_URL が未設定なら /api/* の rewrites を作らない。
# rewrites の転送先に INTERNAL_API_URL(8080)を使うと、ブラウザが付けた X-User-IP が信頼されてしまう

COPY --from=deps /app/node_modules ./node_modules
COPY frontend/ ./
RUN npm run build

# ------------------------------------------------------------
# prod: 実行ランタイム(ECS Fargate / Graviton)
# ------------------------------------------------------------
FROM base AS prod

ENV NODE_ENV=production \
    NEXT_TELEMETRY_DISABLED=1 \
    HOSTNAME=0.0.0.0 \
    PORT=3000 \
    INTERNAL_API_URL=http://api:8080

# root で動かさない(Task Definition の readonlyRootFilesystem と合わせた防御)
RUN addgroup -S nextjs && adduser -S nextjs -G nextjs

# standalone 一式(server.js + トレースされた node_modules + .next/server)
COPY --from=build --chown=nextjs:nextjs /app/.next/standalone ./
# 静的アセット(本線は S3 + CloudFront。ALB 直叩き・ローカル検証用に同梱)
COPY --from=build --chown=nextjs:nextjs /app/.next/static ./.next/static
# ISR cacheHandler。next.config.ts が process.cwd() 直下のパスを焼き込むため、
# WORKDIR(/app)直下に必ず置く(トレース漏れに備えて明示 COPY)
COPY --from=build --chown=nextjs:nextjs /app/cache-handler.mjs ./cache-handler.mjs

USER nextjs

EXPOSE 3000

CMD ["node", "server.js"]
