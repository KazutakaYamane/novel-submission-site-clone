import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, it } from "vitest";
import { ErrorBox } from "./error-box";

describe("ErrorBox", () => {
  it("同じ文言が複数の欄から返っても 1 回だけ出す", () => {
    const html = renderToStaticMarkup(<ErrorBox messages={["入力してください", "入力してください", "不正です"]} />);
    expect(html.match(/<p>/g)).toHaveLength(2);
  });

  it("文言が無ければ何も出さない", () => {
    expect(renderToStaticMarkup(<ErrorBox messages={[]} />)).toBe("");
  });
});
