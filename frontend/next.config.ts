import path from "node:path";
import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: "standalone",
  // standalone は next build 時の設定を server.js に焼き込むため、REDIS_URL の有無では分岐しない
  // (ビルド時は REDIS_URL がない)。cache-handler.mjs が実行時に REDIS_URL を見る。
  // process.cwd() 基準で解決されるため、Dockerfile は cache-handler.mjs を WORKDIR 直下に置く
  cacheHandler: path.resolve(process.cwd(), "cache-handler.mjs"),
  cacheMaxMemorySize: 0,
  async rewrites() {
    // 本番は CloudFront/ALB が /api/* を Laravel に振り分けるため、API_REWRITE_URL は設定しない
    const target = process.env.API_REWRITE_URL;
    if (!target) return [];
    return [{ source: "/api/:path*", destination: `${target}/api/:path*` }];
  },
};

export default nextConfig;
