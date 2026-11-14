export type Token =
  | { type: "text"; text: string }
  | { type: "ruby"; base: string; ruby: string }
  | { type: "emphasis"; text: string };

const MAX_BASE_LENGTH = 20;
const MAX_RUBY_LENGTH = 50;

// 々(U+3005)・〇(U+3007)は Script=Han だが、〆(Common)・ヶ(Katakana)は違う。カクヨムの自動ルビはこの 4 字も漢字として親文字に含める
const KANJI = /^(?:\p{Script=Han}|[々〆ヶ〇])$/u;

function isBar(char: string | undefined): boolean {
  return char === "|" || char === "｜";
}

function indexOfPair(chars: string[], char: string, from: number): number {
  for (let i = from; i < chars.length - 1; i++) {
    if (chars[i] === char && chars[i + 1] === char) return i;
  }
  return -1;
}

function indexOfWithin(chars: string[], char: string, from: number, limit: number): number {
  const end = Math.min(chars.length, from + limit);
  for (let i = from; i < end; i++) {
    if (chars[i] === char) return i;
  }
  return -1;
}

export function parseLine(line: string): Token[] {
  // サロゲートペアの漢字を 1 文字として数えるため、コードポイント単位で走査する
  const chars = Array.from(line);
  const tokens: Token[] = [];
  let buffer: string[] = [];
  let barIndex: number | null = null;
  // 本文は 1 行に数百万字入りうる。閉じない《《のたびに行末まで探すと行の長さの 2 乗の時間がかかるので、見つけた》》の位置を使い回す(-1 は行末まで》》がない)
  let emphasisClose = -2;

  const flush = (until: number) => {
    if (until > 0) tokens.push({ type: "text", text: buffer.slice(0, until).join("") });
    buffer = [];
    barIndex = null;
  };

  let i = 0;
  while (i < chars.length) {
    const char = chars[i];

    if (isBar(char) && chars[i + 1] === "《") {
      buffer.push("《");
      i += 2;
      continue;
    }
    if (isBar(char)) {
      // 縦線は《に近いものだけが親文字の始まりになり、それより前の縦線は文字として残る
      barIndex = buffer.length;
      buffer.push(char);
      i++;
      continue;
    }
    if (char !== "《") {
      buffer.push(char);
      i++;
      continue;
    }

    if (chars[i + 1] === "《") {
      if (emphasisClose !== -1 && emphasisClose < i + 2) emphasisClose = indexOfPair(chars, "》", i + 2);
      const close = emphasisClose;
      if (close > i + 2) {
        flush(buffer.length);
        tokens.push({ type: "emphasis", text: chars.slice(i + 2, close).join("") });
        i = close + 2;
      } else {
        buffer.push("《", "《");
        barIndex = null;
        i += 2;
      }
      continue;
    }

    // 上限を超えるルビはどのみちルビにしないので、》は上限の文字数の先までしか探さない
    const close = indexOfWithin(chars, "》", i + 1, MAX_RUBY_LENGTH + 1);
    const ruby = close === -1 ? [] : chars.slice(i + 1, close);
    // カクヨムは縦線から《までが上限を超えると、縦線を文字として残し、自動ルビとして親文字を探す(実画面で確認)
    const bar = barIndex !== null && buffer.length - (barIndex + 1) <= MAX_BASE_LENGTH ? barIndex : null;
    const baseStart = bar === null ? autoBaseStart(buffer) : bar + 1;
    const baseLength = buffer.length - baseStart;
    // 縦線は直後の《…》にだけ効く。ルビにならなくても次の《には持ち越さない
    barIndex = null;
    if (ruby.length === 0 || baseLength === 0) {
      buffer.push(char);
      i++;
      continue;
    }

    const base = buffer.slice(baseStart).join("");
    flush(bar ?? baseStart);
    tokens.push({ type: "ruby", base, ruby: ruby.join("") });
    i = close + 1;
  }

  flush(buffer.length);
  return tokens;
}

// 直前の漢字が上限を超えるときは《に近い側だけを親文字にする(カクヨムの実画面で確認)
function autoBaseStart(buffer: string[]): number {
  let start = buffer.length;
  while (start > 0 && buffer.length - start < MAX_BASE_LENGTH && KANJI.test(buffer[start - 1])) start--;
  return start;
}

// 記法を描画できない場所(OGP の説明文など)向けに、読者に見える本文の文字だけを残す
export function stripNotation(line: string): string {
  return parseLine(line)
    .map((token) => (token.type === "ruby" ? token.base : token.text))
    .join("");
}
