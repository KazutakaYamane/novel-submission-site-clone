import { describe, expect, it } from "vitest";
import {
  GENRES,
  RATING_FLAGS,
  WORK_STATUSES,
  genreLabel,
  isGenre,
  isRatingFlag,
  isWorkStatus,
  ratingFlagLabel,
  ratingFlagLabels,
  statusLabel,
} from "./work-attributes";

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

describe("ratingFlagLabel", () => {
  it("フラグ 1 つの表示名を返す", () => {
    expect(ratingFlagLabel("cruelty")).toBe("残酷描写有り");
    expect(ratingFlagLabel("violence")).toBe("暴力描写有り");
    expect(ratingFlagLabel("sexual")).toBe("性描写有り");
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

describe("isGenre / isWorkStatus / isRatingFlag", () => {
  it("API の enum の値なら true", () => {
    expect(isGenre("fan_fiction")).toBe(true);
    expect(isWorkStatus("completed")).toBe(true);
    expect(isRatingFlag("sexual")).toBe(true);
  });

  it.each(["", "poem", "SF", "toString", "__proto__"])("%s なら false", (value) => {
    expect(isGenre(value)).toBe(false);
    expect(isWorkStatus(value)).toBe(false);
    expect(isRatingFlag(value)).toBe(false);
  });
});

describe("GENRES / WORK_STATUSES / RATING_FLAGS", () => {
  it("選択肢を API の enum の順に並べる", () => {
    expect(GENRES).toEqual([
      "fantasy",
      "action",
      "sf",
      "love_story",
      "romance",
      "drama",
      "horror",
      "mystery",
      "nonfiction",
      "history",
      "criticism",
      "others",
      "maho",
      "fan_fiction",
    ]);
    expect(WORK_STATUSES).toEqual(["ongoing", "completed"]);
    expect(RATING_FLAGS).toEqual(["cruelty", "violence", "sexual"]);
  });
});
