import { expect, test } from "@playwright/test";
import { waitForForm } from "./helpers";

test("ゲストがデモ作者で始めると初期作品の編集画面へ移り、ヘッダーにデモ作者の名前が出る", async ({ page }) => {
  await page.goto("/");
  const header = page.getByRole("banner");
  await waitForForm(page, "デモ作者として始める");
  await header.getByRole("button", { name: "デモ作者として始める" }).click();

  await expect(page).toHaveURL(/\/my\/works\/[0-9a-f-]{36}$/);
  await expect(header.getByTestId("header-user-name")).toHaveText(/^デモ作者-/);
  await expect(header.getByRole("link", { name: "作品の管理" })).toHaveAttribute("href", "/my/works");
  await expect(header.getByRole("button", { name: "ログアウト" })).toBeVisible();
  await expect(header.getByRole("button", { name: /デモ/ })).toHaveCount(0);
});

test("ゲストがデモ読者で始めると / へ移る", async ({ page }) => {
  await page.goto("/login");
  await waitForForm(page, "デモ読者として始める");
  await page.getByRole("banner").getByRole("button", { name: "デモ読者として始める" }).click();

  await expect(page).toHaveURL("/");
  await expect(page.getByRole("banner").getByTestId("header-user-name")).toHaveText(/^デモ読者-/);
});

test("ゲストのヘッダーにログイン・新規登録のリンクとデモのボタンがある", async ({ page }) => {
  await page.goto("/");
  const header = page.getByRole("banner");
  await expect(header.getByRole("link", { name: "ログイン" })).toHaveAttribute("href", "/login");
  await expect(header.getByRole("link", { name: /^新規登録/ })).toHaveAttribute("href", "/signup");
  await expect(header.getByRole("button", { name: "デモ作者として始める" })).toBeVisible();
  await expect(header.getByRole("button", { name: "デモ読者として始める" })).toBeVisible();
});

test("無効なトークンの cookie で /my/works を開くと、/session-expired にリセットの説明とデモのボタンが出る", async ({
  page,
  context,
}) => {
  await context.addCookies([{ name: "auth_token", value: "1|invalid", domain: "web", path: "/" }]);
  await page.goto("/my/works");

  await expect(page).toHaveURL("/session-expired");
  const main = page.getByRole("main");
  await expect(main).toContainText("データは毎日 4:00 にリセットされます");
  await expect(main.getByRole("button", { name: "デモ作者として始める" })).toBeVisible();
  await expect(main.getByRole("button", { name: "デモ読者として始める" })).toBeVisible();
  await expect(main.getByRole("link", { name: "ログインする" })).toHaveAttribute("href", "/login");
  // Laravel が 401 を返すトークンでは、ヘッダーは未ログインの表示になる
  await expect(page.getByRole("banner").getByRole("link", { name: "ログイン" })).toBeVisible();
});

test("ログイン中にトークンが無効になると、ヘッダーのリンクで移った先のヘッダーは未ログインの表示になる", async ({
  page,
  context,
}) => {
  await page.goto("/");
  await waitForForm(page, "デモ読者として始める");
  const header = page.getByRole("banner");
  await header.getByRole("button", { name: "デモ読者として始める" }).click();
  await expect(header.getByTestId("header-user-name")).toHaveText(/^デモ読者-/);

  // 日次リセットでトークンが消えた状態。ページを開いたまま、クライアントの遷移で移る
  await context.addCookies([{ name: "auth_token", value: "1|invalid", domain: "web", path: "/" }]);
  await header.getByRole("link", { name: "作品の管理" }).click();

  await expect(page).toHaveURL("/session-expired");
  await expect(header.getByRole("link", { name: "ログイン" })).toBeVisible();
  await expect(header.getByTestId("header-user-name")).toHaveCount(0);
});
