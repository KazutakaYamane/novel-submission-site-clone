import type { Metadata } from "next";
import type { PublishedEpisode } from "@/lib/api/episodes";
import { stripNotation } from "@/lib/notation";
import { summarize } from "@/lib/summarize";
import { splitLines } from "./split-lines";

const DESCRIPTION_MAX = 120;

export function episodeMetadata(episode: PublishedEpisode): Metadata {
  // 話のタイトルは「第1話」のように作品をまたいで重なるので、作品のタイトルを添える
  const title = `${episode.title} - ${episode.work.title}`;
  const description = summarize(splitLines(episode.body).map(stripNotation).join("\n"), DESCRIPTION_MAX);
  return {
    title,
    description,
    openGraph: {
      title,
      description,
      type: "article",
      url: `/works/${episode.work.id}/episodes/${episode.id}`,
    },
  };
}
