import { expect, test, type Locator, type Page } from "@playwright/test";
import { signUp, uniqueEmail, waitForForm } from "./helpers";

// 値は本家のメールのログイン(/auth/login/email)・会員登録(/auth/signup)・ログイン方式のボタンから採った
const BLUE = "rgb(75, 170, 224)";
const GRAY = "rgb(153, 153, 153)";
const ERROR = "rgb(193, 27, 51)";

async function bounds(locator: Locator) {
  const b = await locator.boundingBox();
  if (!b) throw new Error("表示されていない");
  return b;
}

async function expectHeading(page: Page, name: string) {
  const heading = page.getByRole("main").getByRole("heading", { level: 1, name });
  await expect(heading).toHaveCSS("font-size", "22px");
  await expect(heading).toHaveCSS("font-weight", "700");
}

async function expectTextInput(input: Locator, width: number) {
  expect(await bounds(input)).toMatchObject({ width, height: 56 });
  await expect(input).toHaveCSS("border-top-width", "1px");
  await expect(input).toHaveCSS("border-top-color", GRAY);
  await expect(input).toHaveCSS("border-radius", "4px");
  await expect(input).toHaveCSS("font-size", "14px");
}

async function expectSubmitButton(button: Locator, width: number) {
  expect(await bounds(button)).toMatchObject({ width, height: 48 });
  await expect(button).toHaveCSS("border-radius", "9999px");
  await expect(button).toHaveCSS("background-color", BLUE);
  await expect(button).toHaveCSS("color", "rgb(255, 255, 255)");
  await expect(button).toHaveCSS("font-size", "14px");
}

async function expectDemoButtons(page: Page, width: number) {
  const main = page.getByRole("main");
  const author = main.getByRole("button", { name: "デモ作者として始める" });
  const reader = main.getByRole("button", { name: "デモ読者として始める" });
  for (const button of [author, reader]) {
    expect(await bounds(button)).toMatchObject({ width, height: 48 });
    await expect(button).toHaveCSS("background-color", "rgb(255, 255, 255)");
    await expect(button).toHaveCSS("border-top-width", "1px");
    await expect(button).toHaveCSS("border-top-color", GRAY);
    await expect(button).toHaveCSS("border-radius", "9999px");
    await expect(button).toHaveCSS("font-size", "14px");
  }
  // 本家のログイン方式のボタンと同じく、間を 16px あけて縦に並ぶ
  const a = await bounds(author);
  const r = await bounds(reader);
  expect(r.x).toBe(a.x);
  expect(r.y).toBe(a.y + 48 + 16);
  return a;
}

test.describe("PC の幅(1280px)", () => {
  test.use({ viewport: { width: 1280, height: 900 } });

  test("/login は見出し 22px、508×56 の入力欄、14px のラベル、高さ 48 の塗りの丸いボタンで並ぶ", async ({ page }) => {
    await page.goto("/login");
    await waitForForm(page, "ログイン", page.getByRole("main"));
    await expectHeading(page, "ログイン");
    const email = page.getByLabel("メールアドレス");
    await expectTextInput(email, 508);
    await expectTextInput(page.getByLabel("パスワード"), 508);
    await expect(page.locator("label", { hasText: "メールアドレス" })).toHaveCSS("font-size", "14px");
    await expectSubmitButton(page.getByRole("main").getByRole("button", { name: "ログイン" }), 508);
    // (1280 - 508) / 2
    expect((await bounds(email)).x).toBe(386);
  });

  test("入力欄にフォーカスすると、枠が 2px の青になり、中の文字は動かない", async ({ page }) => {
    await page.goto("/login");
    await waitForForm(page, "ログイン", page.getByRole("main"));
    const email = page.getByLabel("メールアドレス");
    const before = await email.evaluate((el) => getComputedStyle(el).paddingLeft);
    await email.focus();
    await expect(email).toHaveCSS("border-top-width", "2px");
    await expect(email).toHaveCSS("border-top-color", BLUE);
    expect(await bounds(email)).toMatchObject({ width: 508, height: 56 });
    // 枠が 1px 太くなる分だけ padding を減らし、文字の位置を保つ
    expect(parseFloat(await email.evaluate((el) => getComputedStyle(el).paddingLeft))).toBe(parseFloat(before) - 1);
  });

  test("ログインに失敗すると、フォームの上に 14px の赤い文言が出る", async ({ page }) => {
    await page.goto("/login");
    const main = page.getByRole("main");
    await waitForForm(page, "ログイン", main);
    await page.getByLabel("メールアドレス").fill(uniqueEmail());
    await page.getByLabel("パスワード").fill("wrong-password");
    await main.getByRole("button", { name: "ログイン" }).click();

    const alert = main.getByRole("alert");
    await expect(alert).toHaveText("メールアドレスまたはパスワードが正しくありません");
    await expect(alert).toHaveCSS("font-size", "14px");
    await expect(alert).toHaveCSS("color", ERROR);
    const a = await bounds(alert);
    expect(a.width).toBe(508);
    expect(a.y + a.height).toBeLessThan((await bounds(page.locator("label", { hasText: "メールアドレス" }))).y);
  });

  test("/signup は見出し・説明・名前・メールアドレス・パスワードの欄・塗りのボタンを 1 画面に並べる", async ({ page }) => {
    await page.goto("/signup");
    const main = page.getByRole("main");
    await waitForForm(page, "登録する", main);
    await expectHeading(page, "メールアドレスで新規登録");
    const description = main.getByText("名前・メールアドレス・パスワードを入力してください");
    await expect(description).toHaveCSS("font-size", "14px");
    for (const label of ["名前", "メールアドレス", "パスワード"]) {
      await expectTextInput(page.getByLabel(label), 508);
    }
    await expectSubmitButton(main.getByRole("button", { name: "登録する" }), 508);
  });

  test("/signup で 422 が返ると、欄の下に赤いエラーが出る", async ({ page }) => {
    await page.goto("/signup");
    const main = page.getByRole("main");
    await waitForForm(page, "登録する", main);
    await page.getByLabel("名前").fill("E2E 太郎");
    await page.getByLabel("メールアドレス").fill(uniqueEmail());
    await page.getByLabel("パスワード").fill("short");
    await main.getByRole("button", { name: "登録する" }).click();

    const alert = main.getByRole("alert");
    await expect(alert).toBeVisible();
    await expect(alert).toHaveCSS("color", ERROR);
    expect((await bounds(alert)).y).toBeGreaterThan((await bounds(page.getByLabel("パスワード"))).y);
    await expect(page.getByLabel("名前")).toHaveValue("E2E 太郎");
  });

  test("/login のデモのボタンは、328×48 の白い丸いボタンで中央に縦に並ぶ", async ({ page }) => {
    await page.goto("/login");
    const author = await expectDemoButtons(page, 328);
    // (1280 - 328) / 2
    expect(author.x).toBe(476);
  });

  test("/session-expired は見出し・説明・デモのボタン・ログインへのリンクを同じ部品で並べる", async ({ page, context }) => {
    await context.addCookies([{ name: "auth_token", value: "1|invalid", domain: "web", path: "/" }]);
    await page.goto("/my/works");
    await expect(page).toHaveURL("/session-expired");
    await expectHeading(page, "ログインの有効期限が切れました");
    await expect(page.getByRole("main").getByText("データは毎日 4:00 にリセットされます")).toHaveCSS("font-size", "14px");
    await expectDemoButtons(page, 328);
    await expect(page.getByRole("main").getByRole("link", { name: "ログインする" })).toHaveCSS("font-size", "14px");
  });
});

test.describe("スマホの幅(390px)", () => {
  test.use({ viewport: { width: 390, height: 844 } });

  test("/login の入力欄・ボタン・デモのボタンは左右 16px を残して幅いっぱいに広がる", async ({ page }) => {
    await page.goto("/login");
    await waitForForm(page, "ログイン", page.getByRole("main"));
    const email = page.getByLabel("メールアドレス");
    await expectTextInput(email, 358);
    expect((await bounds(email)).x).toBe(16);
    const submit = page.getByRole("main").getByRole("button", { name: "ログイン" });
    await expectSubmitButton(submit, 358);
    expect((await bounds(submit)).x).toBe(16);
    expect((await expectDemoButtons(page, 358)).x).toBe(16);
  });

  test("/signup の入力欄とボタンは左右 16px を残して幅いっぱいに広がる", async ({ page }) => {
    await page.goto("/signup");
    await waitForForm(page, "登録する", page.getByRole("main"));
    for (const label of ["名前", "メールアドレス", "パスワード"]) {
      const input = page.getByLabel(label);
      await expectTextInput(input, 358);
      expect((await bounds(input)).x).toBe(16);
    }
    await expectSubmitButton(page.getByRole("main").getByRole("button", { name: "登録する" }), 358);
  });

  test("/session-expired のデモのボタンは左右 16px を残して幅いっぱいに広がる", async ({ page }) => {
    await page.goto("/session-expired");
    expect((await expectDemoButtons(page, 358)).x).toBe(16);
  });
});

test("ワークスペースの入力欄の下のエラーも、ログインのエラーと同じ赤で出る", async ({ page }) => {
  await signUp(page, "E2E 作者");
  await page.goto("/my/works/new");
  await waitForForm(page, "作品を作る");
  await page.getByLabel("あらすじ").fill("あらすじの本文");
  await page.getByLabel("ジャンル").selectOption("sf");
  await page.getByRole("button", { name: "作品を作る" }).click();

  const alert = page.getByRole("main").getByRole("alert");
  await expect(alert).toHaveText("タイトルを入力してください");
  await expect(alert).toHaveCSS("color", ERROR);
});
