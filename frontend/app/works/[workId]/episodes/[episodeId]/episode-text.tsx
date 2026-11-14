import { parseLine, type Token } from "@/lib/notation";
import styles from "./episode-text.module.css";
import { splitLines } from "./split-lines";

// HTML は半角空白とタブを詰めるので、それだけの <p> は高さが無くなり空行が消える。全角空白は詰めないので含めない
const BLANK_LINE = /^[ \t]*$/;

// 異体字セレクタや結合文字を前の文字と別の <span> にすると字形が崩れうるので、コードポイントではなく書記素で分ける
const graphemes = new Intl.Segmenter("ja", { granularity: "grapheme" });

// 本文は作者が書いた任意の文字列。HTML の文字列を組み立てず React のテキストにして、エスケープを React に任せる
export function EpisodeText({ body }: { body: string }) {
  return (
    <div>
      {splitLines(body).map((line, i) => (
        // 行は並べ替わらず、同じ本文からは同じ並びになるので、位置をキーにする
        <p key={i}>{BLANK_LINE.test(line) ? <br /> : parseLine(line).map(renderToken)}</p>
      ))}
    </div>
  );
}

// トークンも行と同じく並べ替わらないので、位置をキーにする
function renderToken(token: Token, i: number) {
  switch (token.type) {
    case "text":
      return token.text;
    case "ruby":
      // rp は ruby に対応しないブラウザやリーダー表示で「親文字(ルビ)」と読ませるための括弧
      return (
        <ruby key={i}>
          {token.base}
          <rp>(</rp>
          <rt>{token.ruby}</rt>
          <rp>)</rp>
        </ruby>
      );
    case "emphasis":
      return (
        // カクヨムの傍点と同じく 1 文字ずつ <span> で包む。文字は並べ替わらないので位置をキーにする
        <em key={i} className={styles.emphasis}>
          {Array.from(graphemes.segment(token.text), ({ segment }, j) => (
            <span key={j}>{segment}</span>
          ))}
        </em>
      );
  }
}
