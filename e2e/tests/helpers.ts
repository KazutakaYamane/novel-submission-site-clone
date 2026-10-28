import { expect, type Page } from "@playwright/test";

// ハイドレーション前に送信すると素のフォーム送信になるため、data-hydrated を待ってから操作する
// クライアント遷移の直後は前のページのフォームも残るので、送信ボタンでフォームを特定する
export async function waitForForm(page: Page, button: string) {
  await expect(page.locator("form[data-hydrated]", { has: page.getByRole("button", { name: button }) })).toBeVisible();
}

export function uniqueEmail(): string {
  return `e2e-${Date.now()}-${Math.random().toString(36).slice(2, 8)}@example.com`;
}

export async function signUp(page: Page, name: string) {
  await page.goto("/signup");
  await waitForForm(page, "登録する");
  await page.getByLabel("名前").fill(name);
  await page.getByLabel("メールアドレス").fill(uniqueEmail());
  await page.getByLabel("パスワード").fill("password123");
  await page.getByRole("button", { name: "登録する" }).click();
  await expect(page).toHaveURL("/my/works");
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
