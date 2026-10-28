import { describe, expect, it } from "vitest";
import { parseWorkForm, splitTags } from "./work-form";

describe("splitTags", () => {
  it("読点・カンマ・改行で分ける", () => {
    expect(splitTags("宇宙、群像劇,SF，ハードSF\n冒険\r\n友情")).toEqual(["宇宙", "群像劇", "SF", "ハードSF", "冒険", "友情"]);
  });

  it("各要素の前後の空白を除き、空の要素を除く", () => {
    expect(splitTags(" 宇宙 、、 ,\n\n群像劇　")).toEqual(["宇宙", "群像劇"]);
  });

  it("空文字なら空の配列を返す", () => {
    expect(splitTags("")).toEqual([]);
  });
});

function form(fields: Record<string, string | string[] | Blob>): FormData {
  const f = new FormData();
  for (const [k, v] of Object.entries(fields)) {
    for (const value of Array.isArray(v) ? v : [v]) f.append(k, value);
  }
  return f;
}

const valid = {
  title: "星を継ぐ者たち",
  catchphrase: "最後の星が消える夜に",
  introduction: "あらすじの本文",
  genre: "sf",
  status: "ongoing",
  rating_flags: ["violence", "cruelty"],
  tags: "宇宙、群像劇",
};

describe("parseWorkForm", () => {
  it("Laravel に送る形にする", () => {
    expect(parseWorkForm(form(valid))).toEqual({
      ok: true,
      input: {
        title: "星を継ぐ者たち",
        catchphrase: "最後の星が消える夜に",
        introduction: "あらすじの本文",
        genre: "sf",
        status: "ongoing",
        rating_flags: ["violence", "cruelty"],
        tags: ["宇宙", "群像劇"],
      },
    });
  });

  it("セルフレイティングとタグが無ければ空の配列にする(更新では全部外す意味になる)", () => {
    const withoutFlags = Object.fromEntries(Object.entries(valid).filter(([k]) => k !== "rating_flags"));
    expect(parseWorkForm(form({ ...withoutFlags, tags: "" }))).toMatchObject({
      ok: true,
      input: { rating_flags: [], tags: [] },
    });
  });

  it("ジャンルが空なら送らず、Laravel の 422 に任せる", () => {
    const parsed = parseWorkForm(form({ ...valid, genre: "" }));
    expect(parsed.ok).toBe(true);
    expect(parsed.ok && parsed.input).not.toHaveProperty("genre");
  });

  it("空のタイトル・あらすじはそのまま送り、Laravel の 422 に任せる", () => {
    expect(parseWorkForm(form({ ...valid, title: "", introduction: "" }))).toMatchObject({
      ok: true,
      input: { title: "", introduction: "" },
    });
  });

  // 貼り付けで届きうる長さなので、どの欄が原因かを入力欄の下に出す
  it.each([
    ["title", { ...valid, title: "あ".repeat(1001) }],
    ["catchphrase", { ...valid, catchphrase: "あ".repeat(1001) }],
    ["introduction", { ...valid, introduction: "あ".repeat(100_001) }],
    ["tags", { ...valid, tags: "あ".repeat(1001) }],
  ])("%s が長すぎるなら、その欄のエラーを返す", (field, fields) => {
    expect(parseWorkForm(form(fields))).toEqual({
      ok: false,
      state: { status: "error", fieldErrors: { [field]: ["入力が長すぎます"] } },
    });
  });

  it("長すぎる欄が複数あれば、それぞれの欄のエラーを返す", () => {
    expect(parseWorkForm(form({ ...valid, title: "あ".repeat(1001), tags: "あ".repeat(1001) }))).toEqual({
      ok: false,
      state: {
        status: "error",
        fieldErrors: { title: ["入力が長すぎます"], tags: ["入力が長すぎます"] },
      },
    });
  });

  it.each([
    ["title が無い", { ...valid, title: undefined }],
    ["title がファイル", { ...valid, title: new Blob(["x"]) }],
    ["genre が enum に無い", { ...valid, genre: "poem" }],
    ["status が enum に無い", { ...valid, status: "paused" }],
    ["status が無い", { ...valid, status: undefined }],
    ["rating_flags に enum に無い値がある", { ...valid, rating_flags: ["violence", "gore"] }],
  ])("%s なら「不正なリクエストです」を返す", (_, fields) => {
    const defined = Object.fromEntries(Object.entries(fields).filter(([, v]) => v !== undefined)) as Record<
      string,
      string | string[] | Blob
    >;
    expect(parseWorkForm(form(defined))).toEqual({
      ok: false,
      state: { status: "error", message: "不正なリクエストです" },
    });
  });
});
