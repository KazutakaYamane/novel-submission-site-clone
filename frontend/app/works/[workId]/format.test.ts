import { describe, expect, it } from "vitest";
import { formatDate } from "./format";

describe("formatDate", () => {
  it("Asia/Tokyo の日付で「YYYY年M月D日」にする", () => {
    expect(formatDate("2026-10-09T03:00:00+00:00")).toBe("2026年10月9日");
  });

  it("UTC では前日でも、日本時間で日付が変わっていれば翌日になる", () => {
    expect(formatDate("2026-10-08T15:00:00+00:00")).toBe("2026年10月9日");
  });
});
