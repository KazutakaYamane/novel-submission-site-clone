import { describe, expect, it } from "vitest";
import type { PublishedWork } from "@/lib/api/works";
import { workMetadata } from "./metadata";

const work: PublishedWork = {
  id: "0192f0a0-0000-7000-8000-000000000001",
  title: "タイトル",
  authorName: "山田太郎",
  catchphrase: "キャッチコピー",
  introduction: "あらすじの本文",
  genre: "sf",
  status: "ongoing",
  ratingFlags: [],
  tags: [],
  lastEpisodePublishedAt: null,
  episodes: [],
};

describe("workMetadata", () => {
  it("タイトルとキャッチコピーを、ページと OGP(og:type=book)に使う", () => {
    expect(workMetadata(work)).toEqual({
      title: "タイトル",
      description: "キャッチコピー",
      openGraph: {
        title: "タイトル",
        description: "キャッチコピー",
        type: "book",
        url: "/works/0192f0a0-0000-7000-8000-000000000001",
      },
    });
  });

  it("キャッチコピーが無ければ、あらすじの先頭 120 字を説明文にする", () => {
    const introduction = `${"あ".repeat(100)}\n\n${"い".repeat(100)}`;
    const metadata = workMetadata({ ...work, catchphrase: null, introduction });
    const expected = `${"あ".repeat(100)} ${"い".repeat(18)}…`;
    expect(metadata.description).toBe(expected);
    expect(metadata.openGraph?.description).toBe(expected);
  });
});
