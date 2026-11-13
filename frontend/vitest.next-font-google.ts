// next/font/google は next build・next dev がコンパイル時に置き換える API で、Vitest では呼べない。
// どのフォント名でも、Next.js が返すのと同じ形(className・variable・style)を返す
type FontOptions = { variable?: string };

const loadFont = (options: FontOptions = {}) => ({
  className: "",
  variable: options.variable ?? "",
  style: { fontFamily: "" },
});

export const Noto_Sans_JP = loadFont;
export const Noto_Serif_JP = loadFont;
