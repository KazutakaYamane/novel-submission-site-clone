import { splitLines } from "./split-lines";

// HTML は半角空白とタブを詰めるので、それだけの <p> は高さが無くなり空行が消える。全角空白は詰めないので含めない
const BLANK_LINE = /^[ \t]*$/;

// 本文は作者が書いた任意の文字列。HTML の文字列を組み立てず React のテキストにして、エスケープを React に任せる
export function EpisodeText({ body }: { body: string }) {
  return (
    <div>
      {splitLines(body).map((line, i) => (
        // 行は並べ替わらず、同じ本文からは同じ並びになるので、位置をキーにする
        <p key={i}>{BLANK_LINE.test(line) ? <br /> : line}</p>
      ))}
    </div>
  );
}
