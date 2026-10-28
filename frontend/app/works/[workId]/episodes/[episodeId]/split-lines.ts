// フォームから送った本文は改行が CRLF になり、API から入れた本文は LF のこともあるので、どれも 1 つの改行として扱う
export function splitLines(body: string): string[] {
  const lines = body.split(/\r\n|\r|\n/);
  // 末尾の改行は最後の行を終えるだけで、空行ではない
  if (lines.at(-1) === "") lines.pop();
  return lines;
}
