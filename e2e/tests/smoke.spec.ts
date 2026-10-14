import { expect, test } from "@playwright/test";

test("トップページが API 経由の疎通結果を表示する", async ({ page }) => {
  await page.goto("/");
  await expect(page.getByTestId("api-status")).toContainText("API: ok");
});
