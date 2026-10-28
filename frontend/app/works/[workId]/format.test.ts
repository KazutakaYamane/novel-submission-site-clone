import { describe, expect, it } from "vitest";
import { formatDate, genreLabel, ratingFlagLabels, statusLabel } from "./format";

describe("genreLabel", () => {
  it.each([
    ["fantasy", "異世界ファンタジー"],
    ["action", "現代ファンタジー"],
    ["sf", "SF"],
    ["love_story", "恋愛"],
    ["romance", "ラブコメ"],
    ["drama", "現代ドラマ"],
    ["horror", "ホラー"],
    ["mystery", "ミステリー"],
    ["nonfiction", "エッセイ・ノンフィクション"],
    ["history", "歴史・時代・伝奇"],
    ["criticism", "創作論・評論"],
    ["others", "詩・童話・その他"],
    ["maho", "魔法のiらんど"],
    ["fan_fiction", "二次創作"],
  ] as const)("%s は「%s」", (genre, label) => {
    expect(genreLabel(genre)).toBe(label);
  });
});

describe("statusLabel", () => {
  it("連載中と完結済を返す", () => {
    expect(statusLabel("ongoing")).toBe("連載中");
    expect(statusLabel("completed")).toBe("完結済");
  });
});

describe("ratingFlagLabels", () => {
  it("フラグごとの表示名を、渡した順で返す", () => {
    expect(ratingFlagLabels(["sexual", "cruelty", "violence"])).toEqual(["性描写有り", "残酷描写有り", "暴力描写有り"]);
  });

  it("フラグが無ければ空の配列を返す", () => {
    expect(ratingFlagLabels([])).toEqual([]);
  });
});

describe("formatDate", () => {
  it("Asia/Tokyo の日付で「YYYY年M月D日」にする", () => {
    expect(formatDate("2026-10-09T03:00:00+00:00")).toBe("2026年10月9日");
  });

  it("UTC では前日でも、日本時間で日付が変わっていれば翌日になる", () => {
    expect(formatDate("2026-10-08T15:00:00+00:00")).toBe("2026年10月9日");
  });
});
