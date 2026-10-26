import path from "node:path";
import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: "standalone",
  cacheComponents: true,
  // standalone は next build 時の設定を server.js に焼き込むため、REDIS_URL の有無では分岐しない
  // (ビルド時は REDIS_URL がない)。cache-handlers/redis.mjs が実行時に REDIS_URL を見る。
  // process.cwd() 基準で解決されるため、Dockerfile は cache-handlers/ を WORKDIR 直下に置く
  cacheHandler: path.resolve(process.cwd(), "cache-handlers/incremental.mjs"),
  cacheMaxMemorySize: 0,
  cacheHandlers: {
    default: path.resolve(process.cwd(), "cache-handlers/default.mjs"),
  },
  // E2E のブラウザは http://web:3000 で開く。dev サーバーは localhost 以外からの /_next/* を拒否する
  allowedDevOrigins: ["web"],
  experimental: {
    serverActions: {
      // 既定の 1MB では話の本文が送れない。本文の上限は Laravel が判定する(backend/config/work.php の
      // episode_body_max_bytes = 6990474)ので、ここはそれに multipart の区切りとタイトルの分を足した大きさにする
      bodySizeLimit: 8 * 1024 * 1024,
    },
  },
};

export default nextConfig;
