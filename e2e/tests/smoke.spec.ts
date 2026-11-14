import { expect, test } from "@playwright/test";

// Next.js から Laravel(INTERNAL_API_URL)を呼べていれば、シードの作品が新着に並ぶ
test("トップページに新着の更新作品が並ぶ", async ({ page }) => {
  await page.goto("/");
  const section = page.getByRole("region", { name: "新着の更新作品" });
  await expect(section.getByRole("heading", { name: "新着の更新作品" })).toBeVisible();
  await expect(section.getByRole("listitem").first()).toBeVisible();
});
