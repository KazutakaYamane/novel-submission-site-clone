import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, it } from "vitest";
import { EpisodeText } from "./episode-text";
import styles from "./episode-text.module.css";

const em = (...chars: string[]) => `<em class="${styles.emphasis}">${chars.map((c) => `<span>${c}</span>`).join("")}</em>`;

describe("EpisodeText", () => {
  it("1 行を 1 つの <p> にし、空行は <p><br/></p> にする", () => {
    expect(renderToStaticMarkup(<EpisodeText body={"一行目\r\n\r\n二行目"} />)).toBe(
      "<div><p>一行目</p><p><br/></p><p>二行目</p></div>",
    );
  });

  it("半角空白やタブだけの行も、空行として <p><br/></p> にする", () => {
    expect(renderToStaticMarkup(<EpisodeText body={"一行目\n  \t\n二行目"} />)).toBe(
      "<div><p>一行目</p><p><br/></p><p>二行目</p></div>",
    );
  });

  it("本文の <script> をタグにせず、エスケープした文字として出す", () => {
    const html = renderToStaticMarkup(<EpisodeText body={'<script>alert("x")</script>'} />);
    expect(html).not.toContain("<script>");
    expect(html).toBe("<div><p>&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;</p></div>");
  });

  it("ルビを <ruby>親文字<rp>(</rp><rt>ルビ</rt><rp>)</rp></ruby> にする", () => {
    expect(renderToStaticMarkup(<EpisodeText body={"今日は｜ロンドン《倫敦》へ"} />)).toBe(
      "<div><p>今日は<ruby>ロンドン<rp>(</rp><rt>倫敦</rt><rp>)</rp></ruby>へ</p></div>",
    );
  });

  it("傍点を <em> にし、中の 1 文字ずつを <span> で包む", () => {
    expect(renderToStaticMarkup(<EpisodeText body={"あの《《霧雨》》の夜"} />)).toBe(
      `<div><p>あの${em("霧", "雨")}の夜</p></div>`,
    );
  });

  it("傍点のサロゲートペアの漢字と、異体字セレクタの付いた漢字は、それぞれ 1 文字として 1 つの <span> に入れる", () => {
    expect(renderToStaticMarkup(<EpisodeText body={"《《𠮟葛\u{E0100}》》"} />)).toBe(
      `<div><p>${em("𠮟", "葛\u{E0100}")}</p></div>`,
    );
  });

  it("ルビ・傍点の中の <script> もタグにせず、エスケープした文字として出す", () => {
    const html = renderToStaticMarkup(<EpisodeText body={"｜<script>《<script>》と《《<script>》》"} />);
    expect(html).not.toContain("<script>");
    expect(html).toBe(
      `<div><p><ruby>&lt;script&gt;<rp>(</rp><rt>&lt;script&gt;</rt><rp>)</rp></ruby>と${em("&lt;", ..."script", "&gt;")}</p></div>`,
    );
  });
});
