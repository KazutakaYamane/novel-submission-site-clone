import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, it } from "vitest";
import { EpisodeText } from "./episode-text";

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
});
