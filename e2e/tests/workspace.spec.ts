import { expect, test, type Browser, type BrowserContextOptions, type Locator, type Page } from "@playwright/test";
import { createWork, startDemoAuthor, waitForForm } from "./helpers";

// 本家のワークスペース(作品の一覧・作品の管理・話の編集)の画像から読んだ寸法と比べる。PC は 1280×800、スマホは 390×844
const PC = { width: 1280, height: 800 };
const SP = { width: 390, height: 844 };

let storageState: BrowserContextOptions["storageState"];
let editPath: string;
let episodePath: string;
let authorName: string;

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  const page = await context.newPage();
  authorName = await startDemoAuthor(page);
  editPath = await createWork(page, "ワークスペースを確かめる作品");
  await waitForForm(page, "話を追加");
  await page.getByLabel("話のタイトル").fill("第1話");
  await page.getByRole("button", { name: "話を追加" }).click();
  await expect(page).toHaveURL(new RegExp(`${editPath}/episodes/[0-9a-f-]{36}$`));
  episodePath = new URL(page.url()).pathname;
  storageState = await context.storageState();
  await context.close();
});

async function openAsAuthor(browser: Browser, viewport: typeof PC, path: string): Promise<Page> {
  const context = await browser.newContext({ storageState, viewport });
  const page = await context.newPage();
  await page.goto(path);
  return page;
}

// 中身は Suspense の後から届くので、表示されるのを待ってから測る
async function box(locator: Locator) {
  await expect(locator).toBeVisible();
  const b = await locator.boundingBox();
  if (!b) throw new Error(`${locator} が表示されていない`);
  return b;
}

const style = (locator: Locator, property: string) =>
  locator.evaluate((element, name) => getComputedStyle(element).getPropertyValue(name), property);

test.describe("PC の幅(1280px)", () => {
  test("作品の一覧・作品の管理・新しい作品の上に、高さ 45px のワークスペースの帯が出る", async ({ browser }) => {
    for (const path of ["/my/works", "/my/works/new", "EDIT"]) {
      const page = await openAsAuthor(browser, PC, path === "EDIT" ? editPath : path);
      const bar = page.getByRole("banner");
      expect(await box(bar)).toMatchObject({ y: 0, height: 45, width: 1280 });
      await expect(bar).toHaveCSS("border-bottom", "1px solid rgb(229, 229, 229)");
      await expect(bar).toContainText("ワークスペース");
      const nav = bar.getByRole("link", { name: "作品の管理" });
      await expect(nav).toHaveAttribute("href", "/my/works");
      await expect(nav).toHaveCSS("color", "rgb(9, 144, 204)");
      await expect(bar.getByTestId("header-user-name")).toHaveText(authorName);
      await expect(bar.getByRole("button", { name: "ログアウト" })).toBeVisible();
      await page.context().close();
    }
  });

  test("ナビの「作品の管理」は、作品の一覧で aria-current=\"page\" になる", async ({ browser }) => {
    const page = await openAsAuthor(browser, PC, "/my/works");
    await expect(page.getByRole("banner").getByRole("link", { name: "作品の管理" })).toHaveAttribute(
      "aria-current",
      "page",
    );
  });

  test("作品の一覧は幅 800px の中央寄せで、右端から 10px に灰色の「新しい作品を書く」、行は高さ 60px", async ({
    browser,
  }) => {
    const page = await openAsAuthor(browser, PC, "/my/works");
    const create = page.getByRole("link", { name: "新しい作品を書く" });
    await expect(create).toHaveCSS("background-color", "rgb(221, 221, 221)");
    await expect(create).toHaveCSS("border", "1px solid rgb(187, 187, 187)");
    const createBox = await box(create);
    expect(createBox.height).toBe(30);
    // (1280 + 800) / 2 - 10
    expect(createBox.x + createBox.width).toBe(1030);

    const row = page.getByTestId("my-work").first();
    expect(await box(row)).toMatchObject({ x: 240, width: 800, height: 60 });
    const badge = row.getByText("未公開");
    expect(await box(badge)).toMatchObject({ width: 55, height: 16 });
    await expect(badge).toHaveCSS("background-color", "rgb(234, 213, 181)");
  });

  test("作品の管理は幅 768px の中央寄せで、パンくずと「小説設定」の箱がある", async ({ browser }) => {
    const page = await openAsAuthor(browser, PC, editPath);
    const breadcrumb = page.getByRole("navigation", { name: "パンくずリスト" });
    await expect(breadcrumb.getByRole("link", { name: "作品の管理" })).toHaveAttribute("href", "/my/works");
    await expect(breadcrumb).toContainText("ワークスペースを確かめる作品");

    const settings = page.getByRole("region", { name: "小説設定" });
    // (1280 - 768) / 2
    expect(await box(settings)).toMatchObject({ x: 256, width: 768 });
    const heading = settings.getByRole("heading", { name: "小説設定" });
    expect((await box(heading)).height).toBe(31);
    await expect(heading).toHaveCSS("background-color", "rgb(242, 241, 240)");
    await expect(settings.getByLabel("タイトル")).toHaveValue("ワークスペースを確かめる作品");
  });

  test("作品の管理の話の行は高さ 51px で、札・題名・「編集」のリンクが並び、「話を追加」は塗りのボタン", async ({
    browser,
  }) => {
    const page = await openAsAuthor(browser, PC, editPath);
    const add = page.getByRole("button", { name: "話を追加" });
    await expect(add).toHaveCSS("background-color", "rgb(75, 170, 224)");
    await expect(add).toHaveCSS("border", "1px solid rgb(9, 144, 204)");
    expect((await box(add)).height).toBe(30);

    const row = page.getByTestId("episode").first();
    expect((await box(row)).height).toBe(51);
    const badge = row.getByText("下書き");
    expect(await box(badge)).toMatchObject({ width: 55, height: 16 });
    await expect(badge).toHaveCSS("background-color", "rgb(238, 238, 238)");
    await expect(badge).toHaveCSS("color", "rgb(153, 153, 153)");
    const edit = row.getByRole("link", { name: "第1話を編集" });
    await expect(edit).toHaveAttribute("href", episodePath);
    await expect(edit).toHaveText("編集");
    await expect(edit).toHaveCSS("color", "rgb(9, 144, 204)");
    await expect(edit).toHaveCSS("font-size", "12px");
  });

  test("新しい作品は「小説設定」の箱の中のフォームで作り、「作品を作る」は塗りのボタン", async ({ browser }) => {
    const page = await openAsAuthor(browser, PC, "/my/works/new");
    const settings = page.getByRole("region", { name: "小説設定" });
    expect(await box(settings)).toMatchObject({ x: 256, width: 768 });
    const submit = settings.getByRole("button", { name: "作品を作る" });
    await expect(submit).toHaveCSS("background-color", "rgb(75, 170, 224)");
    expect((await box(submit)).height).toBe(30);
  });

  test("話の編集は上に高さ 50px の帯を固定し、戻るリンク・題名・「下書き保存」「公開」を並べる", async ({ browser }) => {
    const page = await openAsAuthor(browser, PC, episodePath);
    await expect(page.getByRole("banner")).toHaveCount(0);
    const bar = page.getByTestId("editor-bar");
    await expect(bar).toHaveCSS("position", "fixed");
    expect(await box(bar)).toMatchObject({ y: 0, height: 50, width: 1280 });
    await expect(bar).toHaveCSS("border-bottom", "1px solid rgb(229, 229, 229)");
    await expect(bar.getByRole("link", { name: "作品の管理に戻る" })).toHaveAttribute("href", editPath);
    await expect(bar.getByText("第1話を編集")).toHaveCSS("font-size", "13px");

    const publish = bar.getByRole("button", { name: "公開" });
    await expect(publish).toHaveCSS("background-color", "rgb(75, 170, 224)");
    const publishBox = await box(publish);
    expect(publishBox.height).toBe(30);
    expect(publishBox.x + publishBox.width).toBe(1280 - 9);
    const save = bar.getByRole("button", { name: "下書き保存" });
    await expect(save).toHaveCSS("background-color", "rgb(221, 221, 221)");
    const saveBox = await box(save);
    expect(saveBox.height).toBe(30);
    expect(saveBox.x + saveBox.width).toBeLessThanOrEqual(publishBox.x);
  });

  test("話の編集の本文の列は幅 665px の中央寄せで、題名は 16px、本文は明朝体", async ({ browser }) => {
    const page = await openAsAuthor(browser, PC, episodePath);
    const body = page.getByLabel("本文");
    // (1280 - 665) / 2
    expect(await box(body)).toMatchObject({ x: 307.5, width: 665 });
    expect(await style(body, "font-family")).toMatch(/^游明朝/);
    await expect(page.getByLabel("タイトル", { exact: true })).toHaveCSS("font-size", "16px");
  });

  test("入力欄の下のエラーは、新しい作品と話の編集で同じ見た目になる", async ({ browser }) => {
    const newWork = await openAsAuthor(browser, PC, "/my/works/new");
    await waitForForm(newWork, "作品を作る");
    await newWork.getByRole("button", { name: "作品を作る" }).click();
    const workError = newWork.getByRole("main").getByRole("alert").first();
    await expect(workError).toBeVisible();

    const editor = await openAsAuthor(browser, PC, episodePath);
    await waitForForm(editor, "下書き保存");
    await editor.getByLabel("タイトル", { exact: true }).fill("");
    await editor.getByRole("button", { name: "下書き保存" }).click();
    const episodeError = editor.getByRole("main").getByRole("alert");
    await expect(episodeError).toHaveText("タイトルを入力してください");

    for (const property of ["color", "font-size", "margin-top"]) {
      expect(await style(episodeError, property)).toBe(await style(workError, property));
    }
    // 本文の黒(#222)と区別がつく色にする
    expect(await style(workError, "color")).not.toBe("rgb(34, 34, 34)");
  });
});

test.describe("スマホの幅(390px)", () => {
  test("ワークスペースの帯は 1 段目 32px と 2 段目のナビ 44px の 2 段", async ({ browser }) => {
    const page = await openAsAuthor(browser, SP, "/my/works");
    const bar = page.getByRole("banner");
    expect(await box(bar)).toMatchObject({ y: 0, height: 76, width: 390 });
    expect((await box(bar.getByTestId("header-user-name"))).y).toBeLessThan(32);
    const nav = await box(bar.getByRole("link", { name: "作品の管理" }));
    expect(nav.y).toBeGreaterThanOrEqual(32);
    expect(nav.y + nav.height).toBeLessThanOrEqual(76);
  });

  test("作品の一覧は幅いっぱいで、行は高さ 70px、「新しい作品を書く」は右端から 6px", async ({ browser }) => {
    const page = await openAsAuthor(browser, SP, "/my/works");
    expect(await box(page.getByTestId("my-work").first())).toMatchObject({ x: 0, width: 390, height: 70 });
    const create = await box(page.getByRole("link", { name: "新しい作品を書く" }));
    expect(create.x + create.width).toBe(390 - 6);
  });

  test("作品の管理の中身は左右 12px の余白に入る", async ({ browser }) => {
    const page = await openAsAuthor(browser, SP, editPath);
    expect(await box(page.getByRole("region", { name: "小説設定" }))).toMatchObject({ x: 12, width: 390 - 24 });
  });

  test("話の編集の帯には題名を出さず、本文の列は左右 16px の余白に入る", async ({ browser }) => {
    const page = await openAsAuthor(browser, SP, episodePath);
    const bar = page.getByTestId("editor-bar");
    expect(await box(bar)).toMatchObject({ y: 0, height: 50, width: 390 });
    await expect(bar.getByText("第1話を編集")).toBeHidden();
    expect(await box(page.getByLabel("本文"))).toMatchObject({ x: 16, width: 390 - 32 });
  });
});

test("話の編集の「非公開に戻す」は、題名と本文を送らない", async ({ browser }) => {
  const page = await openAsAuthor(browser, PC, episodePath);
  await waitForForm(page, "下書き保存");
  await page.getByLabel("本文").fill("非公開に戻すときには送らない本文");
  await page.getByRole("button", { name: "公開" }).click();
  await expect(page.getByTestId("episode-status")).toHaveText("公開中");

  const request = page.waitForRequest((r) => r.method() === "POST" && new URL(r.url()).pathname === episodePath);
  await page.getByRole("button", { name: "非公開に戻す" }).click();
  expect((await request).postData()).not.toContain("非公開に戻すときには送らない本文");
  await expect(page.getByTestId("episode-status")).toHaveText("下書き");
});
