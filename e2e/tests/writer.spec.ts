import { expect, test, type Browser, type BrowserContext, type Page } from "@playwright/test";
import { uniqueEmail, waitForForm } from "./helpers";

async function signUp(page: Page, name: string) {
  await page.goto("/signup");
  await waitForForm(page, "登録する");
  await page.getByLabel("名前").fill(name);
  await page.getByLabel("メールアドレス").fill(uniqueEmail());
  await page.getByLabel("パスワード").fill("password123");
  await page.getByRole("button", { name: "登録する" }).click();
  await expect(page).toHaveURL("/my/works");
}

// 作品を作り、編集画面の URL を返す
async function createWork(page: Page, title: string): Promise<string> {
  await page.getByRole("link", { name: "新しい作品を書く" }).click();
  await expect(page).toHaveURL("/my/works/new");
  await waitForForm(page, "作品を作る");
  await page.getByLabel("タイトル").fill(title);
  await page.getByLabel("キャッチコピー").fill("最後の星が消える夜に");
  await page.getByLabel("あらすじ").fill("あらすじの本文");
  await page.getByLabel("ジャンル").selectOption("sf");
  await page.getByLabel("タグ").fill("宇宙、群像劇");
  await page.getByLabel("暴力描写有り").check();
  await page.getByRole("button", { name: "作品を作る" }).click();
  await expect(page).toHaveURL(/\/my\/works\/[0-9a-f-]{36}$/);
  return new URL(page.url()).pathname;
}

// browser.newContext() で作ったコンテキストは Playwright が閉じないので、テストごとに閉じる
const openedContexts: BrowserContext[] = [];

test.afterEach(async () => {
  await Promise.all(openedContexts.splice(0).map((context) => context.close()));
});

async function openAsGuest(browser: Browser): Promise<Page> {
  const context = await browser.newContext();
  openedContexts.push(context);
  return context.newPage();
}

const notFoundHeading = (page: Page) => page.getByRole("heading", { name: "ページが見つかりません" });

test("作品を作って話を書き、公開するとゲストに見え、非公開に戻すと見えなくなる", async ({ page, browser }) => {
  await signUp(page, "E2E 作者");
  const editPath = await createWork(page, "星を継ぐ者たち");
  const workId = editPath.split("/").at(-1);

  await page.goto("/my/works");
  await expect(page.getByTestId("my-work").filter({ hasText: "星を継ぐ者たち" })).toContainText("未公開");

  await page.goto(editPath);
  await waitForForm(page, "話を追加");
  await page.getByLabel("話のタイトル").fill("第1話 はじまり");
  await page.getByRole("button", { name: "話を追加" }).click();
  await expect(page).toHaveURL(new RegExp(`/my/works/${workId}/episodes/[0-9a-f-]{36}$`));
  const episodeId = new URL(page.url()).pathname.split("/").at(-1);
  const publicPath = `/works/${workId}/episodes/${episodeId}`;

  await waitForForm(page, "下書き保存");
  await page.getByLabel("本文").fill("本文の一行目\n本文の二行目");
  await page.getByRole("button", { name: "下書き保存" }).click();
  await expect(page.getByRole("status")).toHaveText("保存しました");
  await expect(page.getByTestId("episode-status")).toHaveText("下書き");

  const guest = await openAsGuest(browser);
  await guest.goto(publicPath);
  await expect(notFoundHeading(guest)).toBeVisible();

  await page.getByRole("button", { name: "公開" }).click();
  await expect(page.getByTestId("episode-status")).toHaveText("公開中");
  await expect(page.getByRole("link", { name: "公開ページを見る" })).toHaveAttribute("href", publicPath);

  // キャッシュの破棄は worker 経由の非同期なので、反映されるまで開き直す。
  // 作品ページはまだキャッシュに無いので破棄の前でも新しく出るが、話ページは先に開いた 404 がキャッシュに残っている。
  // 目次のリンクをたどると、破棄の前に prefetch した 404 が出ることがあるので、話ページも開き直して待つ
  await expect(async () => {
    await guest.goto(`/works/${workId}`);
    await expect(guest.getByRole("link", { name: "第1話 はじまり" })).toBeVisible({ timeout: 1000 });
  }).toPass();
  await expect(guest.getByText("宇宙、群像劇")).toBeVisible();
  await expect(async () => {
    await guest.goto(publicPath);
    await expect(guest.getByText("本文の二行目")).toBeVisible({ timeout: 1000 });
  }).toPass();

  await page.goto(editPath);
  await expect(page.getByTestId("episode").filter({ hasText: "第1話 はじまり" })).toContainText("公開中");
  await page.goto("/my/works");
  await expect(page.getByTestId("my-work").filter({ hasText: "星を継ぐ者たち" })).toContainText("公開中");

  await page.goto(publicPath.replace("/works/", "/my/works/"));
  await waitForForm(page, "非公開に戻す");
  await page.getByRole("button", { name: "非公開に戻す" }).click();
  await expect(page.getByTestId("episode-status")).toHaveText("下書き");

  await expect(async () => {
    await guest.goto(publicPath);
    await expect(notFoundHeading(guest)).toBeVisible({ timeout: 1000 });
  }).toPass();
});

test("エディタの完了の表示は直前の操作のものだけを出し、入力を始めると消える", async ({ page }) => {
  await signUp(page, "E2E 作者");
  const editPath = await createWork(page, "表示を確かめる作品");
  await page.goto(editPath);
  await waitForForm(page, "話を追加");
  await page.getByLabel("話のタイトル").fill("第1話");
  await page.getByRole("button", { name: "話を追加" }).click();
  await waitForForm(page, "下書き保存");

  await page.getByLabel("本文").fill("本文");
  await page.getByRole("button", { name: "下書き保存" }).click();
  await expect(page.getByRole("status")).toHaveText("保存しました");
  await page.getByLabel("本文").pressSequentially("を足す");
  await expect(page.getByRole("status")).toHaveCount(0);

  await page.getByRole("button", { name: "公開" }).click();
  await expect(page.getByTestId("episode-status")).toHaveText("公開中");
  await expect(page.getByRole("status")).toHaveText("公開しました");

  await page.getByRole("button", { name: "非公開に戻す" }).click();
  await expect(page.getByTestId("episode-status")).toHaveText("下書き");
  await expect(page.getByRole("status")).toHaveText("非公開に戻しました");
});

test("タイトルが空のまま作品を作ろうとすると、タイトルの欄にエラーが出る", async ({ page }) => {
  await signUp(page, "E2E 作者");
  await page.goto("/my/works/new");
  await waitForForm(page, "作品を作る");
  await page.getByLabel("あらすじ").fill("あらすじの本文");
  await page.getByLabel("ジャンル").selectOption("sf");
  await page.getByRole("button", { name: "作品を作る" }).click();

  await expect(page.getByRole("main").getByRole("alert")).toHaveText("タイトルを入力してください");
  await expect(page).toHaveURL("/my/works/new");
});

test("作品フォームで 422 が返っても、入力した値が残る", async ({ page }) => {
  await signUp(page, "E2E 作者");
  await page.goto("/my/works/new");
  await waitForForm(page, "作品を作る");
  await page.getByLabel("キャッチコピー").fill("残したいキャッチコピー");
  await page.getByLabel("あらすじ").fill("残したいあらすじ");
  await page.getByLabel("ジャンル").selectOption("sf");
  await page.getByLabel("タグ").fill("宇宙、群像劇");
  await page.getByLabel("暴力描写有り").check();
  await page.getByRole("button", { name: "作品を作る" }).click();

  await expect(page.getByRole("main").getByRole("alert")).toHaveText("タイトルを入力してください");
  await expect(page.getByLabel("キャッチコピー")).toHaveValue("残したいキャッチコピー");
  await expect(page.getByLabel("あらすじ")).toHaveValue("残したいあらすじ");
  await expect(page.getByLabel("ジャンル")).toHaveValue("sf");
  await expect(page.getByLabel("タグ")).toHaveValue("宇宙、群像劇");
  await expect(page.getByLabel("暴力描写有り")).toBeChecked();
});

test("エディタで 422 が返っても、書いた本文が残る", async ({ page }) => {
  await signUp(page, "E2E 作者");
  const editPath = await createWork(page, "本文を残す作品");
  await page.goto(editPath);
  await waitForForm(page, "話を追加");
  await page.getByLabel("話のタイトル").fill("第1話");
  await page.getByRole("button", { name: "話を追加" }).click();
  await waitForForm(page, "下書き保存");

  await page.getByLabel("タイトル", { exact: true }).fill("");
  await page.getByLabel("本文").fill("保存できなかった本文");
  await page.getByRole("button", { name: "下書き保存" }).click();

  await expect(page.getByRole("main").getByRole("alert")).toHaveText("タイトルを入力してください");
  await expect(page.getByLabel("本文")).toHaveValue("保存できなかった本文");
  await expect(page.getByLabel("タイトル", { exact: true })).toHaveValue("");
});

test("他人の作品・存在しない作品の編集画面は 404 になる", async ({ page, browser }) => {
  await signUp(page, "E2E 作者");
  const editPath = await createWork(page, "他人には見せない作品");

  const other = await openAsGuest(browser);
  await signUp(other, "E2E 別の人");
  await other.goto(editPath);
  await expect(notFoundHeading(other)).toBeVisible();

  await page.goto("/my/works/0192f0a0-0000-7000-8000-000000000000");
  await expect(notFoundHeading(page)).toBeVisible();
});
