import { describe, expect, it } from "vitest";
import { episodeStatusLabel } from "./episode-status";

describe("episodeStatusLabel", () => {
  it("下書きと公開中を返す", () => {
    expect(episodeStatusLabel("draft")).toBe("下書き");
    expect(episodeStatusLabel("published")).toBe("公開中");
  });
});
