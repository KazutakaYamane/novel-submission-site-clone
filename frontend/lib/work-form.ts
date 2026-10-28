import type { ActionState } from "./action-state";
import { isGenre, isRatingFlag, isWorkStatus, type Genre, type RatingFlag, type WorkStatus } from "./work-attributes";

export type WorkInput = {
  title: string;
  catchphrase: string;
  introduction: string;
  // 未選択は送らず、必須の判定を Laravel の 422 に任せる
  genre?: Genre;
  status: WorkStatus;
  rating_flags: RatingFlag[];
  tags: string[];
};

// 上限は Laravel の max より緩くし、極端に大きい値を Laravel へ送らないためだけに置く
const MAX_LENGTH = 1000;
const MAX_INTRODUCTION_LENGTH = 100_000;

const TEXT_FIELDS = {
  title: MAX_LENGTH,
  catchphrase: MAX_LENGTH,
  introduction: MAX_INTRODUCTION_LENGTH,
  tags: MAX_LENGTH,
} as const;

type ParsedWorkForm = { ok: true; input: WorkInput } | { ok: false; state: Extract<ActionState, { status: "error" }> };

const INVALID = { ok: false, state: { status: "error", message: "不正なリクエストです" } } as const;

// 作品の作成と更新の Server Action が使う。誰でも任意の値を送れる前提で、形だけ検証する
export function parseWorkForm(form: FormData): ParsedWorkForm {
  const texts: Partial<Record<keyof typeof TEXT_FIELDS, string>> = {};
  const fieldErrors: Record<string, string[]> = {};
  for (const [name, maxLength] of Object.entries(TEXT_FIELDS) as [keyof typeof TEXT_FIELDS, number][]) {
    const value = form.get(name);
    if (typeof value !== "string") return INVALID;
    // 貼り付けで届きうる長さなので、「不正なリクエスト」ではなく、どの欄が原因かを入力欄の下に出す
    if (value.length > maxLength) fieldErrors[name] = ["入力が長すぎます"];
    texts[name] = value;
  }
  const genre = form.get("genre");
  const status = form.get("status");
  const ratingFlags = form.getAll("rating_flags");
  if (typeof genre !== "string" || (genre !== "" && !isGenre(genre))) return INVALID;
  if (typeof status !== "string" || !isWorkStatus(status)) return INVALID;
  if (!ratingFlags.every((flag): flag is RatingFlag => typeof flag === "string" && isRatingFlag(flag))) return INVALID;
  if (Object.keys(fieldErrors).length > 0) return { ok: false, state: { status: "error", fieldErrors } };

  const { title = "", catchphrase = "", introduction = "", tags = "" } = texts;
  return {
    ok: true,
    input: {
      title,
      catchphrase,
      introduction,
      ...(genre !== "" && { genre }),
      status,
      rating_flags: ratingFlags,
      tags: splitTags(tags),
    },
  };
}

// 入力欄は 1 つにし、日本語の読点・全角と半角のカンマ・改行のどれで区切ってもよい
export function splitTags(input: string): string[] {
  return input
    .split(/[、,，\r\n]/)
    .map((tag) => tag.trim())
    .filter((tag) => tag !== "");
}
