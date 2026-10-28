import { describe, expect, it } from "vitest";
import type { PublishedEpisode } from "@/lib/api/episodes";
import { episodeMetadata } from "./metadata";

const WORK_ID = "0192f0a0-0000-7000-8000-000000000001";
const EPISODE_ID = "0192f0a0-0000-7000-8000-000000000012";

const episode: PublishedEpisode = {
  id: EPISODE_ID,
  title: "第2話",
  body: "本文の一行目",
  work: { id: WORK_ID, title: "星を継ぐ者たち" },
  previous: null,
  next: null,
};

describe("episodeMetadata", () => {
  it("話と作品のタイトル、本文の先頭を、ページと OGP(og:type=article)に使う", () => {
    expect(episodeMetadata(episode)).toEqual({
      title: "第2話 - 星を継ぐ者たち",
      description: "本文の一行目",
      openGraph: {
        title: "第2話 - 星を継ぐ者たち",
        description: "本文の一行目",
        type: "article",
        url: `/works/${WORK_ID}/episodes/${EPISODE_ID}`,
      },
    });
  });

  it("本文の先頭 120 字を、改行を空白にまとめて説明文にする", () => {
    const body = `${"あ".repeat(100)}\r\n\r\n${"い".repeat(100)}`;
    const metadata = episodeMetadata({ ...episode, body });
    const expected = `${"あ".repeat(100)} ${"い".repeat(18)}…`;
    expect(metadata.description).toBe(expected);
    expect(metadata.openGraph?.description).toBe(expected);
  });
});
