import { describe, expect, it } from "vitest";
import { summarize } from "./summarize";

describe("summarize", () => {
  it("改行や連続した空白を 1 つの空白にまとめ、前後の空白を除く", () => {
    expect(summarize("  一行目\n\n二行目\t 三行目  ", 100)).toBe("一行目 二行目 三行目");
  });

  it("max 字以内ならそのまま返す", () => {
    expect(summarize("あいうえお", 5)).toBe("あいうえお");
  });

  it("max 字を超えたら、… を含めて max 字に切る", () => {
    expect(summarize("あいうえおか", 5)).toBe("あいうえ…");
  });

  it("サロゲートペアの文字を途中で切らない", () => {
    expect(summarize("𠮷𠮷𠮷𠮷𠮷𠮷", 3)).toBe("𠮷𠮷…");
  });
});
