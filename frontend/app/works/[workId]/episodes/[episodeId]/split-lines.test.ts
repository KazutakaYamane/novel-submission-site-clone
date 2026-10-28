import { describe, expect, it } from "vitest";
import { splitLines } from "./split-lines";

describe("splitLines", () => {
  it("LF で行に分ける", () => {
    expect(splitLines("一行目\n二行目")).toEqual(["一行目", "二行目"]);
  });

  it("CRLF と CR も 1 つの改行として扱う", () => {
    expect(splitLines("一行目\r\n二行目\r三行目")).toEqual(["一行目", "二行目", "三行目"]);
  });

  it("空行を空文字列の行として残す", () => {
    expect(splitLines("一行目\n\n\n二行目")).toEqual(["一行目", "", "", "二行目"]);
  });

  it("末尾の改行は最後の行の終わりとみなし、空行を足さない", () => {
    expect(splitLines("一行目\n二行目\r\n")).toEqual(["一行目", "二行目"]);
  });

  it("末尾の改行が 2 つなら、空行を 1 つ残す", () => {
    expect(splitLines("一行目\n\n")).toEqual(["一行目", ""]);
  });

  it("行頭の全角空白を残す", () => {
    expect(splitLines("　字下げ")).toEqual(["　字下げ"]);
  });
});
