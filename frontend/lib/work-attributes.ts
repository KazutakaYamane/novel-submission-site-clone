import type { components } from "@/lib/api/schema";

export type Genre = components["schemas"]["Genre"];
export type WorkStatus = components["schemas"]["WorkStatus"];
export type RatingFlag = components["schemas"]["RatingFlag"];

// Record にして、API にジャンルが増えたら型検査で気づけるようにする
const GENRE_LABELS: Record<Genre, string> = {
  fantasy: "異世界ファンタジー",
  action: "現代ファンタジー",
  sf: "SF",
  love_story: "恋愛",
  romance: "ラブコメ",
  drama: "現代ドラマ",
  horror: "ホラー",
  mystery: "ミステリー",
  nonfiction: "エッセイ・ノンフィクション",
  history: "歴史・時代・伝奇",
  criticism: "創作論・評論",
  others: "詩・童話・その他",
  maho: "魔法のiらんど",
  fan_fiction: "二次創作",
};

const STATUS_LABELS: Record<WorkStatus, string> = {
  ongoing: "連載中",
  completed: "完結済",
};

const RATING_FLAG_LABELS: Record<RatingFlag, string> = {
  cruelty: "残酷描写有り",
  violence: "暴力描写有り",
  sexual: "性描写有り",
};

// 選択肢の並びと、Server Action の enum の検証に使う
export const GENRES = Object.keys(GENRE_LABELS) as Genre[];
export const WORK_STATUSES = Object.keys(STATUS_LABELS) as WorkStatus[];
export const RATING_FLAGS = Object.keys(RATING_FLAG_LABELS) as RatingFlag[];

export function isGenre(value: string): value is Genre {
  return (GENRES as string[]).includes(value);
}

export function isWorkStatus(value: string): value is WorkStatus {
  return (WORK_STATUSES as string[]).includes(value);
}

export function isRatingFlag(value: string): value is RatingFlag {
  return (RATING_FLAGS as string[]).includes(value);
}

export function genreLabel(genre: Genre): string {
  return GENRE_LABELS[genre];
}

export function statusLabel(status: WorkStatus): string {
  return STATUS_LABELS[status];
}

export function ratingFlagLabel(flag: RatingFlag): string {
  return RATING_FLAG_LABELS[flag];
}

export function ratingFlagLabels(flags: RatingFlag[]): string[] {
  return flags.map(ratingFlagLabel);
}
