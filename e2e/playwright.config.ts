import { defineConfig } from "@playwright/test";

export default defineConfig({
  testDir: "./tests",
  reporter: [["list"], ["html", { outputFolder: "playwright-report", open: "never" }]],
  use: {
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
  },
  projects: [
    { name: "dev", testIgnore: "cache.spec.ts", use: { baseURL: "http://web:3000" } },
    // dev サーバーは本番ビルドと同じようにはキャッシュしないので、キャッシュ越しの反映は本番ビルドの web-prod で確かめる。
    // e2e-cache サービスは web-prod とネットワークを共有するので、127.0.0.1 が web-prod になる
    { name: "cache", testMatch: "cache.spec.ts", use: { baseURL: "http://127.0.0.1:3000" } },
  ],
});
