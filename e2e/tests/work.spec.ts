import { expect, test, type Locator, type Page } from "@playwright/test";
import { createWork, publishNewEpisode, reloadUntil, signUp, waitForForm } from "./helpers";

// 本家の作品ページから採取した寸法と比べる。PC は 1280×800、スマホは 390×844
const WORK_TITLE = "作品ページを確かめる作品";

let workPath: string;
let episodePaths: string[];

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  const page = await context.newPage();
  await signUp(page, "E2E 作者");
  const editPath = await createWork(page, WORK_TITLE);
  workPath = editPath.replace("/my/works/", "/works/");
  episodePaths = [
    await publishNewEpisode(page, editPath, "第1話 はじまり", "第1話の本文"),
    await publishNewEpisode(page, editPath, "第2話 なかほど", "第2話の本文"),
    await publishNewEpisode(page, editPath, "第3話 おわり", "第3話の本文"),
  ];
  await context.close();
});

const toc = (page: Page) => page.getByRole("region", { name: "目次" });

// 目次は話の公開で変わるので、3 話目の公開が反映されるまで開き直す
async function openWork(page: Page) {
  await reloadUntil(page, workPath, () =>
    expect(page.getByRole("main").getByRole("link", { name: "第3話 おわり" })).toBeVisible({ timeout: 1000 }),
  );
}

async function box(locator: Locator) {
  const b = await locator.boundingBox();
  if (!b) throw new Error(`${locator} が表示されていない`);
  return b;
}

test.describe("PC の幅(1280px)", () => {
  test.use({ viewport: { width: 1280, height: 800 } });

  test("主の列(左右 32px・上 64px を空ける)と、左線 1px #EEE の幅 440px の目次の列が横に並ぶ", async ({ page }) => {
    await openWork(page);
    // (1280 - 1185) / 2 + 32
    expect(await box(page.getByRole("heading", { level: 1 }))).toMatchObject({ x: 79.5, width: 681 });
    // ヘッダー 50px + 上の余白 64px
    expect((await box(page.getByRole("heading", { level: 1 }))).y).toBe(114);
    const tocBox = await box(toc(page));
    expect(tocBox).toMatchObject({ x: 792.5, width: 440 });
    await expect(toc(page)).toHaveCSS("border-left", "1px solid rgb(238, 238, 238)");
  });

  test("作品名は 35px・太字・行の高さ 1.3、作者名は 14px #222", async ({ page }) => {
    await openWork(page);
    const title = page.getByRole("heading", { level: 1 });
    await expect(title).toHaveText(WORK_TITLE);
    await expect(title).toHaveCSS("font-size", "35px");
    await expect(title).toHaveCSS("font-weight", "700");
    await expect(title).toHaveCSS("line-height", "45.5px");
    const author = page.getByRole("main").getByText("E2E 作者", { exact: true });
    await expect(author).toHaveCSS("font-size", "14px");
    await expect(author).toHaveCSS("color", "rgb(34, 34, 34)");
  });

  test("「1話目から読む」は 1 話目へのリンクで、130×36 の塗りのボタン", async ({ page }) => {
    await openWork(page);
    const read = page.getByRole("link", { name: "1話目から読む" });
    await expect(read).toHaveAttribute("href", episodePaths[0]);
    await expect(read).toHaveCSS("background-color", "rgb(75, 170, 224)");
    await expect(read).toHaveCSS("border", "1px solid rgb(9, 144, 204)");
    await expect(read).toHaveCSS("color", "rgb(255, 255, 255)");
    expect(await box(read)).toMatchObject({ width: 130, height: 36 });
  });

  test("キャッチコピーは 20px・太字・中央揃え、あらすじは 14px・行の高さ 1.8 で改行を保つ", async ({ page }) => {
    await openWork(page);
    const catchphrase = page.getByText("最後の星が消える夜に");
    await expect(catchphrase).toHaveCSS("font-size", "20px");
    await expect(catchphrase).toHaveCSS("font-weight", "700");
    await expect(catchphrase).toHaveCSS("text-align", "center");
    const introduction = page.getByText("あらすじの本文");
    await expect(introduction).toHaveCSS("font-size", "14px");
    await expect(introduction).toHaveCSS("line-height", "25.2px");
    await expect(introduction).toHaveCSS("white-space", "pre-wrap");
  });

  test("タグは背景 #F2F1F0・12.6px・高さ 31px の札で並ぶ", async ({ page }) => {
    await openWork(page);
    for (const name of ["宇宙", "群像劇"]) {
      const tag = page.getByRole("main").getByText(name, { exact: true });
      await expect(tag).toHaveCSS("background-color", "rgb(242, 241, 240)");
      await expect(tag).toHaveCSS("font-size", "12.6px");
      expect((await box(tag)).height).toBe(31);
    }
  });

  test("ジャンル・連載状態と話数・セルフレイティング・最終更新日が 1 行に 14px #666 で並ぶ", async ({ page }) => {
    await openWork(page);
    const meta = page.getByTestId("work-meta");
    await expect(meta).toHaveText(/^SF.*連載中 全3話.*暴力描写有り.*\d{4}年\d{1,2}月\d{1,2}日 更新$/);
    await expect(meta).toHaveCSS("font-size", "14px");
    await expect(meta).toHaveCSS("color", "rgb(102, 102, 102)");
    // 先頭のジャンルと末尾の更新日が同じ行にある
    expect((await box(meta.getByText("SF", { exact: true }))).y).toBe((await box(meta.locator("time"))).y);
  });

  test("目次の話の行は、題名 14px のリンクと公開日 11.5px #666 で、高さ 54px", async ({ page }) => {
    await openWork(page);
    const links = toc(page).getByRole("link");
    await expect(links).toHaveText(["第1話 はじまり", "第2話 なかほど", "第3話 おわり"]);
    await expect(links.first()).toHaveAttribute("href", episodePaths[0]);
    await expect(links.first()).toHaveCSS("font-size", "14px");
    const row = toc(page).getByRole("listitem").first();
    const date = row.getByText(/^\d{4}年\d{1,2}月\d{1,2}日公開$/);
    await expect(date).toHaveCSS("font-size", "11.5px");
    await expect(date).toHaveCSS("color", "rgb(102, 102, 102)");
    expect((await box(row)).height).toBeCloseTo(54, 0);
  });
});

test.describe("タブレットの幅(820px)", () => {
  test.use({ viewport: { width: 820, height: 1180 } });

  test("主の列と目次の列を 745:440 の比で分け、主の列を狭めすぎない", async ({ page }) => {
    await openWork(page);
    // 820 × 745 / 1185 = 515.5。左右 32px を除いた幅
    expect((await box(page.getByRole("heading", { level: 1 }))).width).toBeCloseTo(451.5, 0);
    expect((await box(toc(page))).width).toBeCloseTo(304.5, 0);
  });
});

test.describe("スマホの幅(390px)", () => {
  test.use({ viewport: { width: 390, height: 844 } });

  test("作品名は 18px で左右 16px を空け、キャッチコピーは 16px、メタ情報は 11.5px", async ({ page }) => {
    await openWork(page);
    const title = page.getByRole("heading", { level: 1 });
    await expect(title).toHaveCSS("font-size", "18px");
    expect(await box(title)).toMatchObject({ x: 16, width: 358 });
    await expect(page.getByText("最後の星が消える夜に")).toHaveCSS("font-size", "16px");
    await expect(page.getByTestId("work-meta")).toHaveCSS("font-size", "11.5px");
  });

  test("目次はあらすじとタグの下に入り、話の題名は 14px", async ({ page }) => {
    await openWork(page);
    const tag = await box(page.getByRole("main").getByText("群像劇", { exact: true }));
    const tocBox = await box(toc(page));
    expect(tocBox.y).toBeGreaterThan(tag.y + tag.height);
    expect(tocBox.width).toBe(390);
    await expect(toc(page).getByRole("link").first()).toHaveCSS("font-size", "14px");
  });

  test("「1話目から読む」は 157×36 で、画面の下に固定した帯に入る", async ({ page }) => {
    await openWork(page);
    const read = page.getByRole("link", { name: "1話目から読む" });
    expect(await box(read)).toMatchObject({ width: 157, height: 36 });
    const bar = page.getByTestId("work-actions");
    await expect(bar).toHaveCSS("position", "sticky");
    await expect(bar).toHaveCSS("bottom", "0px");
    // 本家と同じく目次の後ろに置き、ページを読み進める間は画面の下に留める
    const last = await box(toc(page).getByRole("listitem").last());
    expect((await box(bar)).y).toBeGreaterThanOrEqual(last.y + last.height);
  });
});

test("公開済みの話が無い作品には「1話目から読む」を出さない", async ({ page, browser }) => {
  const context = await browser.newContext();
  const author = await context.newPage();
  await signUp(author, "E2E 作者");
  const editPath = await createWork(author, "話を非公開に戻した作品");
  const episodePath = await publishNewEpisode(author, editPath, "第1話", "本文");
  await author.goto(episodePath.replace("/works/", "/my/works/"));
  await waitForForm(author, "非公開に戻す");
  await author.getByRole("button", { name: "非公開に戻す" }).click();
  await expect(author.getByTestId("episode-status")).toHaveText("下書き");
  await context.close();

  const path = editPath.replace("/my/works/", "/works/");
  await reloadUntil(page, path, async () => {
    await expect(page.getByRole("heading", { level: 1 })).toHaveText("話を非公開に戻した作品", { timeout: 1000 });
    await expect(toc(page).getByRole("link")).toHaveCount(0, { timeout: 1000 });
  });
  await expect(page.getByRole("link", { name: "1話目から読む" })).toHaveCount(0);
  await expect(toc(page).getByText("まだ公開された話はありません")).toBeVisible();
  await expect(toc(page).getByRole("list")).toHaveCount(0);
});
