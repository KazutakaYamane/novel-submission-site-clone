import { describe, expect, it } from "vitest";
import { isUuid } from "./uuid";

describe("isUuid", () => {
  it("小文字の UUID なら true", () => {
    expect(isUuid("0192f0a0-0000-7000-8000-000000000001")).toBe(true);
  });

  it.each([
    ["大文字を含む", "0192F0A0-0000-7000-8000-000000000001"],
    ["ハイフンが無い", "0192f0a0000070008000000000000001"],
    ["前後に余分な文字がある", "x0192f0a0-0000-7000-8000-000000000001"],
    ["パスの文字を含む", "../my/works"],
    ["空文字", ""],
  ])("%s なら false", (_, value) => {
    expect(isUuid(value)).toBe(false);
  });
});
