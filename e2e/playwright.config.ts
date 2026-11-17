import { defineConfig } from "@playwright/test";

export default defineConfig({
  testDir: "./tests",
  // test.only を残したまま CI を通すと、その 1 件だけで E2E が成功になる
  forbidOnly: !!process.env.CI,
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
