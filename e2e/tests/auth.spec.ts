import { expect, test } from "@playwright/test";
import { waitForForm } from "./helpers";

test("/login に会員登録とログインを無効にした理由とデモのボタンが出て、入力欄は無い", async ({ page }) => {
  await page.goto("/login");
  const main = page.getByRole("main");
  await expect(main.getByRole("heading", { level: 1, name: "ログイン" })).toBeVisible();
  await expect(main).toContainText("個人情報を保持しないため、会員登録とログインは無効にしています。");
  await expect(main.getByRole("button", { name: "デモ作者として始める" })).toBeVisible();
  await expect(main.getByRole("button", { name: "デモ読者として始める" })).toBeVisible();
  await expect(main.getByRole("textbox")).toHaveCount(0);
  await expect(main.getByRole("link", { name: /会員登録/ })).toHaveCount(0);
});

test("/login のデモ作者のボタンで始めると、初期作品の編集画面へ移り、ヘッダーにデモ作者の名前が出る", async ({
  page,
}) => {
  await page.goto("/login");
  const main = page.getByRole("main");
  await waitForForm(page, "デモ作者として始める", main);
  await main.getByRole("button", { name: "デモ作者として始める" }).click();

  await expect(page).toHaveURL(/\/my\/works\/[0-9a-f-]{36}$/);
  await expect(page.getByRole("banner").getByTestId("header-user-name")).toHaveText(/^デモ作者-/);
});

test("/signup を開くと 404 になる", async ({ page }) => {
  const response = await page.goto("/signup");
  expect(response?.status()).toBe(404);
  await expect(page.getByRole("heading", { name: "ページが見つかりません" })).toBeVisible();
});

test("未ログインで /my/works を開くと /login へリダイレクトされる", async ({ page }) => {
  await page.goto("/my/works");
  await expect(page).toHaveURL("/login");
});

test("ログアウトすると /login へ移り、/my/works を開くと /login へリダイレクトされる", async ({ page }) => {
  await page.goto("/login");
  const main = page.getByRole("main");
  await waitForForm(page, "デモ読者として始める", main);
  await main.getByRole("button", { name: "デモ読者として始める" }).click();
  await expect(page).toHaveURL("/");

  const header = page.getByRole("banner");
  await waitForForm(page, "ログアウト", header);
  await header.getByRole("button", { name: "ログアウト" }).click();
  await expect(page).toHaveURL("/login");

  await page.goto("/my/works");
  await expect(page).toHaveURL("/login");
});
