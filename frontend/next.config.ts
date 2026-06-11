import type { NextConfig } from "next";
import path from "node:path";

// サーバーサイド(SSR/ISR)の fetch 先。
// ローカル: http://nginx:80 / 本番: Service Connect の http://api:80。
// compose.yaml の web.environment.INTERNAL_API_URL で注入される。
const INTERNAL_API_URL = process.env.INTERNAL_API_URL ?? "http://nginx:80";

const nextConfig: NextConfig = {
  // 本番は ARM64 イメージ(独立 ECS サービス=方式A)で動かすため standalone 出力。
  output: "standalone",

  // ISR / Data Cache を Redis(本番: ElastiCache)に置く。
  // Fargate では既定の .next/cache がタスクローカル・揮発のため(ADR-INFRA 決定8)。
  // REDIS_URL が無い環境(ビルド時・ローカル dev)はハンドラ内で in-memory に
  // fallback する。パスはビルド時に standalone へ焼き込まれるため、本番イメージは
  // 同じ場所(WORKDIR 直下)に cache-handler.mjs を配置すること。
  cacheHandler: path.join(process.cwd(), "cache-handler.mjs"),
  // タスク間の一貫性は Redis に寄せるため、Next 内蔵の in-memory キャッシュは無効化
  cacheMaxMemorySize: 0,

  // ブラウザ(クライアント)からの /api は same-origin で受け、ここで Laravel(Nginx)へ
  // プロキシする。これにより CORS 不要。サーバーサイド fetch は直接 INTERNAL_API_URL を叩く。
  async rewrites() {
    return [
      {
        source: "/api/:path*",
        destination: `${INTERNAL_API_URL}/api/:path*`,
      },
    ];
  },
};

export default nextConfig;
