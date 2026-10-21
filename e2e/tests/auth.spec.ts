import { expect, test, type Page } from "@playwright/test";

// ハイドレーション前に送信すると素のフォーム送信になるため、data-hydrated を待ってから操作する
// クライアント遷移の直後は前のページのフォームも残るので、送信ボタンでフォームを特定する
async function waitForForm(page: Page, button: string) {
  await expect(page.locator("form[data-hydrated]", { has: page.getByRole("button", { name: button }) })).toBeVisible();
}

function uniqueEmail(): string {
  return `e2e-${Date.now()}-${Math.random().toString(36).slice(2, 8)}@example.com`;
}

test("登録 → ログアウト → 同じ資格情報でログインすると /my/works に名前が出る", async ({ page }) => {
  const email = uniqueEmail();

  await page.goto("/signup");
  await waitForForm(page, "登録する");
  await page.getByLabel("名前").fill("E2E 太郎");
  await page.getByLabel("メールアドレス").fill(email);
  await page.getByLabel("パスワード").fill("password123");
  await page.getByRole("button", { name: "登録する" }).click();

  await expect(page).toHaveURL("/my/works");
  await expect(page.getByTestId("current-user")).toHaveText("E2E 太郎 さん");

  await page.goto("/login");
  await expect(page).toHaveURL("/my/works");

  await waitForForm(page, "ログアウト");
  await page.getByRole("button", { name: "ログアウト" }).click();
  await expect(page).toHaveURL("/login");

  await waitForForm(page, "ログイン");
  await page.getByLabel("メールアドレス").fill(email);
  await page.getByLabel("パスワード").fill("password123");
  await page.getByRole("button", { name: "ログイン" }).click();

  await expect(page).toHaveURL("/my/works");
  await expect(page.getByTestId("current-user")).toHaveText("E2E 太郎 さん");
});

test("未ログインで /my/works を開くと /login へリダイレクトされる", async ({ page }) => {
  await page.goto("/my/works");
  await expect(page).toHaveURL("/login");
});

test("誤ったパスワードでログインするとエラーが出る", async ({ page }) => {
  const email = uniqueEmail();
  await page.goto("/signup");
  await waitForForm(page, "登録する");
  await page.getByLabel("名前").fill("E2E 花子");
  await page.getByLabel("メールアドレス").fill(email);
  await page.getByLabel("パスワード").fill("password123");
  await page.getByRole("button", { name: "登録する" }).click();
  await expect(page).toHaveURL("/my/works");
  await page.context().clearCookies();

  await page.goto("/login");
  await waitForForm(page, "ログイン");
  await page.getByLabel("メールアドレス").fill(email);
  await page.getByLabel("パスワード").fill("wrong-password");
  await page.getByRole("button", { name: "ログイン" }).click();

  await expect(page.getByRole("main").getByRole("alert")).toHaveText("メールアドレスまたはパスワードが正しくありません");
  await expect(page).toHaveURL("/login");
});

test("期限切れの cookie で /my/works を開くと /session-expired へリダイレクトされ、ログインへのリンクがある", async ({
  page,
  context,
}) => {
  await context.addCookies([{ name: "auth_token", value: "1|invalid", domain: "web", path: "/" }]);
  await page.goto("/my/works");
  await expect(page).toHaveURL("/session-expired");
  await page.getByRole("link", { name: "ログインする" }).click();
  await expect(page).toHaveURL("/login");
});
