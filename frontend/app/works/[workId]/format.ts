import type { components } from "@/lib/api/schema";

type Genre = components["schemas"]["Genre"];
type WorkStatus = components["schemas"]["WorkStatus"];
type RatingFlag = components["schemas"]["RatingFlag"];

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

// サーバーのタイムゾーンは UTC なので、読者向けの日付は日本時間に固定する
const DATE_FORMAT = new Intl.DateTimeFormat("ja-JP", {
  timeZone: "Asia/Tokyo",
  year: "numeric",
  month: "long",
  day: "numeric",
});

export function genreLabel(genre: Genre): string {
  return GENRE_LABELS[genre];
}

export function statusLabel(status: WorkStatus): string {
  return STATUS_LABELS[status];
}

export function ratingFlagLabels(flags: RatingFlag[]): string[] {
  return flags.map((flag) => RATING_FLAG_LABELS[flag]);
}

export function formatDate(iso: string): string {
  return DATE_FORMAT.format(new Date(iso));
}
