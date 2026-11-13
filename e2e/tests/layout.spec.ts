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

  test("ゲストのヘッダーの右端に、高さ 50px のログインと高さ 34px の丸い新規登録が並ぶ", async ({ page }) => {
    await page.goto("/login");
    const header = page.getByRole("banner");
    const login = header.getByRole("link", { name: "ログイン" });
    const signup = header.getByRole("link", { name: /^新規登録/ });
    await expect(login).toHaveCSS("font-size", "12.6px");
    await expect(login).toHaveCSS("padding-left", "16px");
    await expect(signup).toHaveCSS("border-radius", "9999px");
    await expect(signup).toHaveCSS("background-color", "rgb(75, 170, 224)");
    const loginBox = await login.boundingBox();
    const signupBox = await signup.boundingBox();
    expect(loginBox).toMatchObject({ y: 0, height: 50 });
    expect(signupBox).toMatchObject({ y: 8, height: 34 });
    // 中身の右端は (1280 + 1185) / 2 - 30
    expect(signupBox!.x + signupBox!.width).toBe(1202.5);
    expect(loginBox!.x + loginBox!.width).toBeLessThanOrEqual(signupBox!.x);
  });

  test("デモのボタンはログインの左に、高さ 34px で並ぶ", async ({ page }) => {
    await page.goto("/login");
    const header = page.getByRole("banner");
    const author = await header.getByRole("button", { name: "デモ作者として始める" }).boundingBox();
    const reader = await header.getByRole("button", { name: "デモ読者として始める" }).boundingBox();
    const login = await header.getByRole("link", { name: "ログイン" }).boundingBox();
    expect(author).toMatchObject({ y: 8, height: 34 });
    expect(reader).toMatchObject({ y: 8, height: 34 });
    expect(author!.x + author!.width).toBeLessThanOrEqual(reader!.x);
    expect(reader!.x + reader!.width).toBeLessThanOrEqual(login!.x);
  });

  test("本文はヘッダーの下から始まり、幅は最大 1185px", async ({ page }) => {
    await page.goto("/login");
    const main = await box(page, "main");
    expect(main.y).toBe(50);
    expect(main.width).toBe(1185);
  });
});

test("スマホより少し広い幅(641px)でも、ゲストのヘッダーの項目が画面の中に収まる", async ({ page }) => {
  await page.setViewportSize({ width: 641, height: 800 });
  await page.goto("/login");
  const header = page.getByRole("banner");
  for (const item of [
    header.getByRole("button", { name: "デモ作者として始める" }),
    header.getByRole("button", { name: "デモ読者として始める" }),
    header.getByRole("link", { name: "ログイン" }),
    header.getByRole("link", { name: /^新規登録/ }),
  ]) {
    const b = await item.boundingBox();
    expect(b!.x).toBeGreaterThanOrEqual(0);
    expect(b!.x + b!.width).toBeLessThanOrEqual(641);
  }
  const logo = await header.getByRole("link", { name: SITE_NAME }).boundingBox();
  expect(logo!.width).toBe(130);
  const author = await header.getByRole("button", { name: "デモ作者として始める" }).boundingBox();
  expect(logo!.x + logo!.width).toBeLessThanOrEqual(author!.x);
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

  test("ゲストのヘッダーの 1 段目の右に、高さ 34px のログインと高さ 22px の新規登録が並ぶ", async ({ page }) => {
    await page.goto("/login");
    const header = page.getByRole("banner");
    const login = header.getByRole("link", { name: "ログイン" });
    const signup = header.getByRole("link", { name: /^新規登録/ });
    await expect(login).toHaveCSS("font-size", "11.5px");
    await expect(signup).toHaveCSS("font-size", "11.5px");
    await expect(signup).toHaveCSS("padding-left", "8px");
    await expect(signup).toHaveText("新規登録", { useInnerText: true });
    const loginBox = await login.boundingBox();
    const signupBox = await signup.boundingBox();
    expect(loginBox).toMatchObject({ y: 0, height: 34 });
    expect(signupBox).toMatchObject({ y: 6, height: 22 });
    expect(loginBox!.x + loginBox!.width).toBeLessThanOrEqual(signupBox!.x);
  });

  test("デモのボタンは 2 段目に、幅を分けて並ぶ", async ({ page }) => {
    await page.goto("/login");
    const header = page.getByRole("banner");
    const author = await header.getByRole("button", { name: "デモ作者として始める" }).boundingBox();
    const reader = await header.getByRole("button", { name: "デモ読者として始める" }).boundingBox();
    for (const b of [author!, reader!]) {
      expect(b.y).toBeGreaterThanOrEqual(34);
      expect(b.y + b.height).toBeLessThanOrEqual(79);
    }
    expect(author!.width).toBe(reader!.width);
    expect(author!.x + author!.width).toBeLessThanOrEqual(reader!.x);
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
