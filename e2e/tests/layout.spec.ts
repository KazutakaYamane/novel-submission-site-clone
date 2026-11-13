import { expect, test, type Page } from "@playwright/test";

const SITE_NAME = "novel-site-clone";
const REPOSITORY_URL = "https://github.com/KazutakaYamane/novel-submission-site-clone";

async function box(page: Page, selector: Parameters<Page["locator"]>[0]) {
  const b = await page.locator(selector).first().boundingBox();
  if (!b) throw new Error(`${selector} が表示されていない`);
  return b;
}

test.describe("PC の幅(1280px)", () => {
  test.use({ viewport: { width: 1280, height: 800 } });

  test("ヘッダーの帯は高さ 50px で上に固定され、中身は最大幅 1185px・左右 30px の中に入る", async ({ page }) => {
    await page.goto("/login");
    const header = page.getByRole("banner");
    await expect(header).toHaveCSS("position", "fixed");
    expect(await header.boundingBox()).toMatchObject({ y: 0, height: 50, width: 1280 });

    const logoLink = header.getByRole("link", { name: SITE_NAME });
    await expect(logoLink).toHaveAttribute("href", "/");
    // (1280 - 1185) / 2 + 30
    expect(await logoLink.boundingBox()).toMatchObject({ x: 77.5, width: 130, height: 50 });
    expect(await box(page, "header a svg")).toMatchObject({ width: 130, height: 25 });
  });

  test("本文はヘッダーの下から始まり、幅は最大 1185px", async ({ page }) => {
    await page.goto("/login");
    const main = await box(page, "main");
    expect(main.y).toBe(50);
    expect(main.width).toBe(1185);
  });
});

test.describe("スマホの幅(390px)", () => {
  test.use({ viewport: { width: 390, height: 844 } });

  test("ヘッダーの帯は高さ 79px(34px + 45px)で、ロゴは 83×16", async ({ page }) => {
    await page.goto("/login");
    const header = page.getByRole("banner");
    await expect(header).toHaveCSS("position", "fixed");
    expect(await header.boundingBox()).toMatchObject({ y: 0, height: 79, width: 390 });

    const logoLink = header.getByRole("link", { name: SITE_NAME });
    expect(await logoLink.boundingBox()).toMatchObject({ x: 0, width: 93, height: 34 });
    expect(await box(page, "header a svg")).toMatchObject({ x: 10, width: 83, height: 16 });
  });

  test("本文はヘッダーの下から始まり、幅いっぱいに広がる", async ({ page }) => {
    await page.goto("/login");
    const main = await box(page, "main");
    expect(main.y).toBe(79);
    expect(main.width).toBe(390);
  });
});

test("スクロールして表示した要素が、固定したヘッダーの下に隠れない", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 300 });
  await page.goto("/login");
  const button = page.getByRole("button", { name: "ログイン" });
  // 入力の検証で失敗した欄やページ内リンクの先へ、ブラウザが要素を上端に合わせてスクロールするときと同じ動き
  await button.evaluate((el) => el.scrollIntoView({ block: "start" }));
  // スクロール量は整数に丸められ、行間 25.2px のため要素の位置は小数になるので、1px 未満の差は許す
  expect((await button.boundingBox())?.y).toBeCloseTo(50, 0);
});

test("body の文字は 14px・行間 1.8・#222", async ({ page }) => {
  await page.goto("/login");
  const body = page.locator("body");
  await expect(body).toHaveCSS("font-size", "14px");
  await expect(body).toHaveCSS("line-height", "25.2px");
  await expect(body).toHaveCSS("color", "rgb(34, 34, 34)");
});

test("フッターに非公式のクローンである旨と、リポジトリへのリンクがある", async ({ page }) => {
  await page.goto("/login");
  const footer = page.getByRole("contentinfo");
  await expect(footer).toContainText("非公式のクローン");
  await expect(footer).toContainText("カクヨム・株式会社KADOKAWA・株式会社はてなとは関係ありません");
  await expect(footer).toContainText("個人が学習とポートフォリオのために作ったもの");
  await expect(footer.getByRole("link", { name: "GitHub" })).toHaveAttribute("href", REPOSITORY_URL);
});

test("ページに noindex が付き、robots.txt はクロールを禁止しない", async ({ page, request }) => {
  await page.goto("/login");
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute("content", "noindex, nofollow");

  // クロールを禁止すると、検索エンジンがページの noindex を読めず、リンク先の URL だけが検索結果に載る
  const res = await request.get("/robots.txt");
  expect(await res.text()).not.toMatch(/^Disallow:\s*\/\s*$/m);
});
