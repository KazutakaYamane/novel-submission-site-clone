// OGP の説明文は 1 行で読まれるので、改行を空白にまとめる
export function summarize(text: string, max: number): string {
  // サロゲートペアの途中で切らないよう、コードポイント単位で数える
  const chars = Array.from(text.replace(/\s+/g, " ").trim());
  if (chars.length <= max) return chars.join("");
  return chars.slice(0, max - 1).join("") + "…";
}
