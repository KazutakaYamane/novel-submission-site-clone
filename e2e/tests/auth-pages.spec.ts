import { expect, test, type Locator, type Page } from "@playwright/test";
import { startDemoAuthor, waitForForm } from "./helpers";

// 値は本家のメールのログイン(/auth/login/email)・ログイン方式のボタンから採った
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

  test("/login は見出し 22px、14px の説明、328×48 の白い丸いデモのボタンを中央に縦に並べる", async ({ page }) => {
    await page.goto("/login");
    await expectHeading(page, "ログイン");
    await expect(
      page.getByRole("main").getByText("個人情報を保持しないため、会員登録とログインは無効にしています。"),
    ).toHaveCSS("font-size", "14px");
    const author = await expectDemoButtons(page, 328);
    // (1280 - 328) / 2
    expect(author.x).toBe(476);
  });

  test("/session-expired は見出し・説明・デモのボタンを /login と同じ部品で並べる", async ({ page, context }) => {
    await context.addCookies([{ name: "auth_token", value: "1|invalid", domain: "web", path: "/" }]);
    await page.goto("/my/works");
    await expect(page).toHaveURL("/session-expired");
    await expectHeading(page, "ログインの有効期限が切れました");
    await expect(page.getByRole("main").getByText("データは毎日 4:00 にリセットされます")).toHaveCSS("font-size", "14px");
    await expectDemoButtons(page, 328);
  });
});

test.describe("スマホの幅(390px)", () => {
  test.use({ viewport: { width: 390, height: 844 } });

  test("/login のデモのボタンは左右 16px を残して幅いっぱいに広がる", async ({ page }) => {
    await page.goto("/login");
    expect((await expectDemoButtons(page, 358)).x).toBe(16);
  });

  test("/session-expired のデモのボタンは左右 16px を残して幅いっぱいに広がる", async ({ page }) => {
    await page.goto("/session-expired");
    expect((await expectDemoButtons(page, 358)).x).toBe(16);
  });
});

test("ワークスペースの入力欄の下のエラーは、本家のエラーと同じ赤で出る", async ({ page }) => {
  await startDemoAuthor(page);
  await page.goto("/my/works/new");
  await waitForForm(page, "作品を作る");
  await page.getByLabel("あらすじ").fill("あらすじの本文");
  await page.getByLabel("ジャンル").selectOption("sf");
  await page.getByRole("button", { name: "作品を作る" }).click();

  const alert = page.getByRole("main").getByRole("alert");
  await expect(alert).toHaveText("タイトルを入力してください");
  await expect(alert).toHaveCSS("color", ERROR);
});
