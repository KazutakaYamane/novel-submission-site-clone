import { expect, test, type Locator, type Page } from "@playwright/test";
import { createWork, publishNewEpisode, reloadUntil, signUp } from "./helpers";

const newWorks = (page: Page) => page.getByRole("region", { name: "新着の更新作品" });

async function box(locator: Locator) {
  const b = await locator.boundingBox();
  if (!b) throw new Error("表示されていない");
  return b;
}

test("トップにサイトの説明、デモのボタン、リセットの時刻が出る", async ({ page }) => {
  await page.goto("/");
  const hero = page.getByRole("main").getByRole("region", { name: "novel-site-clone" });
  await expect(hero.getByRole("heading", { level: 1 })).toHaveText("novel-site-clone");
  await expect(hero).toContainText("登録せずに");
  await expect(hero.getByRole("button", { name: "デモ作者として始める" })).toBeVisible();
  await expect(hero.getByRole("button", { name: "デモ読者として始める" })).toBeVisible();
  await expect(hero).toContainText("データは毎日 4:00 にリセットされます");
});

test("ログイン中は、トップの帯にデモのボタンを出さない", async ({ page }) => {
  await signUp(page, "E2E 読者");
  await page.goto("/");
  const hero = page.getByRole("main").getByRole("region", { name: "novel-site-clone" });
  await expect(hero).toContainText("データは毎日 4:00 にリセットされます");
  await expect(newWorks(page).getByRole("listitem").first()).toBeVisible();
  await expect(hero.getByRole("button", { name: /デモ/ })).toHaveCount(0);
});

test("トップにランキングとジャンルへの導線を出さない", async ({ page }) => {
  await page.goto("/");
  await expect(newWorks(page).getByRole("listitem").first()).toBeVisible();
  // 作品のタイトルに「ランキング」が入ることがあるので、名前は新着の一覧の外のリンクだけで見る
  for (const area of [page.getByRole("banner"), page.getByRole("region", { name: "novel-site-clone" }), page.getByRole("contentinfo")]) {
    await expect(area.getByRole("link", { name: /ランキング|ジャンル/ })).toHaveCount(0);
  }
  await expect(page.locator('a[href^="/rankings"], a[href^="/search"]')).toHaveCount(0);
});

test("新着の作品の行に、キャッチコピー・作者名・ジャンル・連載状態・話数・最終更新日が出て、タイトルが作品ページへのリンクになる", async ({
  page,
}) => {
  const title = `トップに出る作品 ${Date.now()}`;
  await signUp(page, "E2E 作者");
  const editPath = await createWork(page, title);
  await publishNewEpisode(page, editPath, "第1話", "本文");

  // 公開の破棄は worker 経由の非同期なので、反映されるまで開き直す
  const link = newWorks(page).getByRole("link", { name: title });
  await reloadUntil(page, "/", () => expect(link).toBeVisible({ timeout: 1000 }));
  await expect(link).toHaveAttribute("href", editPath.replace("/my/works/", "/works/"));

  const item = newWorks(page).getByRole("listitem").filter({ has: page.getByRole("link", { name: title }) });
  await expect(item).toContainText("最後の星が消える夜に");
  await expect(item).toContainText(`${title}／E2E 作者`);
  await expect(item).toContainText("SF");
  await expect(item).toContainText("連載中 1話");
  const today = new Intl.DateTimeFormat("ja-JP", { timeZone: "Asia/Tokyo", dateStyle: "long" }).format(new Date());
  await expect(item).toContainText(`${today} 更新`);
});

test.describe("PC の幅(1280px)", () => {
  test.use({ viewport: { width: 1280, height: 800 } });

  test("新着の見出しは 1 文字ずつ 25×25 の枠に入り、本文の枠の左から 32px に置く", async ({ page }) => {
    await page.goto("/");
    const chars = newWorks(page).getByRole("heading", { name: "新着の更新作品" }).locator("span");
    await expect(chars).toHaveCount(7);
    await expect(chars.first()).toHaveCSS("font-size", "18px");
    await expect(chars.first()).toHaveCSS("font-weight", "700");
    // (1280 - 1185) / 2 + 32
    expect(await box(chars.first())).toMatchObject({ x: 79.5, width: 25, height: 25 });
    // 隣の枠と線を重ねる
    expect((await box(chars.nth(1))).x).toBe(79.5 + 24);
  });

  test("作品の行は主の列(789px)に 2 列で並び、padding 16px、文字はキャッチコピー 16px・作品名 14px・メタ情報 12.6px", async ({
    page,
  }) => {
    await page.goto("/");
    const list = newWorks(page).getByRole("list");
    expect(await box(list)).toMatchObject({ x: 79.5, width: 789 });

    const items = list.getByRole("listitem");
    const first = await box(items.nth(0));
    const second = await box(items.nth(1));
    expect(first.width).toBeCloseTo(394.5, 0);
    expect(second.x).toBeCloseTo(first.x + first.width, 0);
    expect(second.y).toBe(first.y);
    await expect(items.first()).toHaveCSS("padding", "16px");
    await expect(items.first()).toHaveCSS("border-right", "1px solid rgb(221, 221, 221)");
    await expect(items.first()).toHaveCSS("border-bottom", "1px solid rgb(221, 221, 221)");

    const catchphrase = items.first().locator("p").first();
    await expect(catchphrase).toHaveCSS("font-size", "16px");
    await expect(catchphrase).toHaveCSS("font-weight", "700");
    await expect(catchphrase).toHaveCSS("line-height", "19.2px");
    const titleLink = items.first().getByRole("link");
    await expect(titleLink).toHaveCSS("font-size", "14px");
    await expect(titleLink).toHaveCSS("font-weight", "700");
    await expect(titleLink).toHaveCSS("color", "rgb(34, 34, 34)");
    const meta = items.first().locator("p").last();
    await expect(meta).toHaveCSS("font-size", "12.6px");
    await expect(meta).toHaveCSS("color", "rgb(102, 102, 102)");
  });

  test("最上部の帯は上下に padding 32px", async ({ page }) => {
    await page.goto("/");
    const hero = page.getByRole("region", { name: "novel-site-clone" });
    await expect(hero).toHaveCSS("padding-top", "32px");
    await expect(hero).toHaveCSS("padding-bottom", "32px");
    await expect(hero.getByRole("heading", { level: 1 })).toHaveCSS("color", "rgb(9, 144, 204)");
  });
});

test.describe("スマホの幅(390px)", () => {
  test.use({ viewport: { width: 390, height: 844 } });

  test("新着の見出しは 16px で、左右 16px の中に入る", async ({ page }) => {
    await page.goto("/");
    const chars = newWorks(page).getByRole("heading", { name: "新着の更新作品" }).locator("span");
    await expect(chars.first()).toHaveCSS("font-size", "16px");
    expect(await box(chars.first())).toMatchObject({ x: 16, width: 25, height: 25 });
  });

  test("作品の行は 1 列で、右だけ padding 16px、メタ情報は 11.5px", async ({ page }) => {
    await page.goto("/");
    const list = newWorks(page).getByRole("list");
    expect(await box(list)).toMatchObject({ x: 16, width: 358 });
    const items = list.getByRole("listitem");
    const first = await box(items.nth(0));
    const second = await box(items.nth(1));
    expect(first.width).toBe(358);
    expect(second.y).toBeGreaterThanOrEqual(first.y + first.height);
    await expect(items.first()).toHaveCSS("padding", "16px 16px 16px 0px");
    await expect(items.first()).toHaveCSS("border-right-width", "0px");
    await expect(items.first().locator("p").last()).toHaveCSS("font-size", "11.5px");
  });

  test("最上部の帯の見出しは 20px・太さ 800、padding は上 24px・下 32px", async ({ page }) => {
    await page.goto("/");
    const hero = page.getByRole("region", { name: "novel-site-clone" });
    await expect(hero).toHaveCSS("padding-top", "24px");
    await expect(hero).toHaveCSS("padding-bottom", "32px");
    const h1 = hero.getByRole("heading", { level: 1 });
    await expect(h1).toHaveCSS("font-size", "20px");
    await expect(h1).toHaveCSS("font-weight", "800");
  });
});
