import { expect, test, type Locator, type Page } from "@playwright/test";
import { createWork, startDemoAuthor, publishNewEpisode, reloadUntil } from "./helpers";

// 本家の話ページから採取した寸法と比べる。PC は 1280×800、スマホは 390×844
const WORK_TITLE = "話ページを確かめる作品";
const SECOND_BODY = "　一行目の本文\n\n｜ロンドン《倫敦》の《《霧》》\n四行目の本文";

let workPath: string;
let episodePaths: string[];

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  const page = await context.newPage();
  await startDemoAuthor(page);
  const editPath = await createWork(page, WORK_TITLE);
  workPath = editPath.replace("/my/works/", "/works/");
  episodePaths = [
    await publishNewEpisode(page, editPath, "第1話 はじまり", "第1話の本文"),
    await publishNewEpisode(page, editPath, "第2話 なかほど", SECOND_BODY),
    await publishNewEpisode(page, editPath, "第3話 おわり", "第3話の本文"),
  ];
  await context.close();
});

const body = (page: Page) => page.getByRole("article").getByTestId("episode-text");

// 前後の話は他の話の公開で変わるので、3 話目の公開が反映されるまで開き直す
async function openSecond(page: Page) {
  await reloadUntil(page, episodePaths[1], () =>
    expect(page.getByRole("link", { name: /^次の話/ })).toBeVisible({ timeout: 1000 }),
  );
}

async function height(locator: Locator) {
  const b = await locator.boundingBox();
  if (!b) throw new Error(`${locator} が表示されていない`);
  return b.height;
}

test.describe("PC の幅(1280px)", () => {
  test.use({ viewport: { width: 1280, height: 800 } });

  test("上に高さ 50px の話ページの帯を固定し、作品名と話の題名、作品ページへ戻るリンクを出す", async ({ page }) => {
    await openSecond(page);
    const bar = page.getByRole("banner");
    await expect(bar).toHaveCSS("position", "fixed");
    expect(await bar.boundingBox()).toMatchObject({ y: 0, height: 50, width: 1280 });
    // サイト共通のヘッダーではないので、ログインの導線を出さない
    await expect(bar.getByRole("link", { name: "ログイン" })).toHaveCount(0);
    await expect(bar.getByText(WORK_TITLE)).toBeVisible();
    await expect(bar.getByText("第2話 なかほど")).toBeVisible();
    await expect(bar.getByText(WORK_TITLE)).toHaveCSS("font-size", "12px");
    await expect(bar.getByText("第2話 なかほど")).toHaveCSS("font-size", "11px");
    await expect(bar.getByRole("link", { name: "作品ページに戻る" })).toHaveAttribute("href", workPath);
    await expect(bar.getByRole("link", { name: "目次" })).toHaveAttribute("href", workPath);
  });

  test("作品名は 42px・太字・行の高さ 1.2、話の題名は 24.5px", async ({ page }) => {
    await openSecond(page);
    const main = page.getByRole("main");
    const workTitle = main.getByRole("link", { name: WORK_TITLE });
    await expect(workTitle).toHaveAttribute("href", workPath);
    await expect(workTitle).toHaveCSS("font-size", "42px");
    await expect(workTitle).toHaveCSS("font-weight", "700");
    await expect(workTitle).toHaveCSS("line-height", "50.4px");
    await expect(main.getByRole("heading", { level: 1 })).toHaveText("第2話 なかほど");
    await expect(main.getByRole("heading", { level: 1 })).toHaveCSS("font-size", "24.5px");
  });

  test("本文は明朝体の 17.5px・行の高さ 31.5px・最大幅 665px で、段落の間に余白を空けない", async ({ page }) => {
    await openSecond(page);
    const text = body(page);
    expect((await text.boundingBox())?.width).toBe(665);
    const first = text.locator("p").first();
    await expect(first).toHaveCSS("font-size", "17.5px");
    await expect(first).toHaveCSS("line-height", "31.5px");
    await expect(first).toHaveCSS("margin-top", "0px");
    await expect(first).toHaveCSS("margin-bottom", "0px");
    await expect(first).toHaveCSS("font-family", /^游明朝/);
  });

  test("ルビは本文の半分の 8.75px で、ルビを含む行と空行の高さは 1 行分の 31.5px", async ({ page }) => {
    await openSecond(page);
    const text = body(page);
    await expect(text.locator("rt")).toHaveCSS("font-size", "8.75px");
    expect(await height(text.locator("p").nth(2))).toBe(31.5);
    expect(await height(text.locator("p").nth(1))).toBe(31.5);
  });

  test("本文の下に、次の話(ラベル 14px・題名 21px)と前の話へのリンクを出す", async ({ page }) => {
    await openSecond(page);
    const next = page.getByRole("link", { name: /^次の話/ });
    await expect(next).toHaveAttribute("href", episodePaths[2]);
    await expect(next.getByText("次の話")).toHaveCSS("font-size", "14px");
    await expect(next.getByText("第3話 おわり")).toHaveCSS("font-size", "21px");
    await expect(page.getByRole("link", { name: /^前の話/ })).toHaveAttribute("href", episodePaths[0]);
  });
});

test.describe("スマホの幅(390px)", () => {
  test.use({ viewport: { width: 390, height: 844 } });

  test("帯は高さ 50px で、作品名と話の題名を出さない", async ({ page }) => {
    await openSecond(page);
    const bar = page.getByRole("banner");
    expect(await bar.boundingBox()).toMatchObject({ y: 0, height: 50, width: 390 });
    await expect(bar.getByText(WORK_TITLE)).toBeHidden();
    await expect(bar.getByRole("link", { name: "作品ページに戻る" })).toBeVisible();
    await expect(bar.getByRole("link", { name: "目次" })).toBeVisible();
  });

  test("作品名は 22.75px、話の題名は 16.25px", async ({ page }) => {
    await openSecond(page);
    const main = page.getByRole("main");
    await expect(main.getByRole("link", { name: WORK_TITLE })).toHaveCSS("font-size", "22.75px");
    await expect(main.getByRole("heading", { level: 1 })).toHaveCSS("font-size", "16.25px");
  });

  test("本文は 16px・行の高さ 28.8px・左右 20px を空けた幅 350px", async ({ page }) => {
    await openSecond(page);
    const text = body(page);
    expect(await text.boundingBox()).toMatchObject({ x: 20, width: 350 });
    await expect(text.locator("p").first()).toHaveCSS("font-size", "16px");
    await expect(text.locator("p").first()).toHaveCSS("line-height", "28.8px");
  });

  test("ルビは 8px で、ルビを含む行と空行の高さは 1 行分の 28.8px", async ({ page }) => {
    await openSecond(page);
    const text = body(page);
    await expect(text.locator("rt")).toHaveCSS("font-size", "8px");
    expect(await height(text.locator("p").nth(2))).toBeCloseTo(28.8, 1);
    expect(await height(text.locator("p").nth(1))).toBeCloseTo(28.8, 1);
  });

  test("次の話のラベルは 13px、題名は 16.25px", async ({ page }) => {
    await openSecond(page);
    const next = page.getByRole("link", { name: /^次の話/ });
    await expect(next.getByText("次の話")).toHaveCSS("font-size", "13px");
    await expect(next.getByText("第3話 おわり")).toHaveCSS("font-size", "16.25px");
  });
});

test("最初の話に前の話へのリンク、最後の話に次の話へのリンクを出さない", async ({ page }) => {
  await reloadUntil(page, episodePaths[0], () =>
    expect(page.getByRole("link", { name: /^次の話/ })).toBeVisible({ timeout: 1000 }),
  );
  await expect(page.getByRole("link", { name: /^前の話/ })).toHaveCount(0);

  await page.goto(episodePaths[2]);
  await expect(page.getByRole("link", { name: /^前の話/ })).toBeVisible();
  await expect(page.getByRole("link", { name: /^次の話/ })).toHaveCount(0);
});

test("前後の話がどちらも無い話には、前後の話の帯を出さない", async ({ page, browser }) => {
  const context = await browser.newContext();
  const author = await context.newPage();
  await startDemoAuthor(author);
  const editPath = await createWork(author, "1 話だけの作品");
  const path = await publishNewEpisode(author, editPath, "第1話", "ただ一つの話の本文");
  await context.close();

  await reloadUntil(page, path, () => expect(page.getByText("ただ一つの話の本文")).toBeVisible({ timeout: 1000 }));
  await expect(page.getByRole("navigation", { name: "前後の話" })).toHaveCount(0);
});
