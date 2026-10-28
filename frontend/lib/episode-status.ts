import type { components } from "@/lib/api/schema";

type EpisodeStatus = components["schemas"]["EpisodeStatus"];

// Record にして、予約公開(scheduled)が API に増えたら型検査で気づけるようにする
const EPISODE_STATUS_LABELS: Record<EpisodeStatus, string> = {
  draft: "下書き",
  published: "公開中",
};

export function episodeStatusLabel(status: EpisodeStatus): string {
  return EPISODE_STATUS_LABELS[status];
}
