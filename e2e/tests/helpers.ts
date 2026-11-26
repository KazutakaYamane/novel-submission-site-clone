import { expect, type Locator, type Page } from "@playwright/test";

// ハイドレーション前に送信すると素のフォーム送信になるため、data-hydrated を待ってから操作する
// クライアント遷移の直後は前のページのフォームも残るので、送信ボタンでフォームを特定する。
// トップのデモのボタンはヘッダーと本文の 2 か所にあるので、within で探す範囲を絞る
export async function waitForForm(page: Page, button: string, within: Page | Locator = page) {
  await expect(within.locator("form[data-hydrated]", { has: page.getByRole("button", { name: button }) })).toBeVisible();
}

// デモ作者で始めて作品の一覧へ移り、ヘッダーに出る作者名を返す。デモ作者は初期作品を 1 つ持って始まる
export async function startDemoAuthor(page: Page): Promise<string> {
  await page.goto("/login");
  const main = page.getByRole("main");
  await waitForForm(page, "デモ作者として始める", main);
  await main.getByRole("button", { name: "デモ作者として始める" }).click();
  await expect(page).toHaveURL(/\/my\/works\/[0-9a-f-]{36}$/);
  await page.goto("/my/works");
  const name = page.getByRole("banner").getByTestId("header-user-name");
  await expect(name).toHaveText(/^デモ作者-/);
  return (await name.textContent()) ?? "";
}

// 作品を作り、編集画面の URL を返す
export async function createWork(page: Page, title: string): Promise<string> {
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

export const notFoundHeading = (page: Page) => page.getByRole("heading", { name: "ページが見つかりません" });

// 話を追加して本文を書き、公開する。公開ページのパスを返す
export async function publishNewEpisode(page: Page, editPath: string, title: string, body: string): Promise<string> {
  await page.goto(editPath);
  await waitForForm(page, "話を追加");
  await page.getByLabel("話のタイトル").fill(title);
  await page.getByRole("button", { name: "話を追加" }).click();
  await expect(page).toHaveURL(new RegExp(`${editPath}/episodes/[0-9a-f-]{36}$`));
  const path = new URL(page.url()).pathname;
  await waitForForm(page, "下書き保存");
  await page.getByLabel("本文").fill(body);
  await page.getByRole("button", { name: "公開" }).click();
  await expect(page.getByTestId("episode-status")).toHaveText("公開中");
  return path.replace("/my/works/", "/works/");
}

// 破棄は worker 経由の非同期なので、反映されるまで開き直す
export async function reloadUntil(page: Page, path: string, assertion: () => Promise<void>) {
  await expect(async () => {
    await page.goto(path);
    await assertion();
  }).toPass();
}
