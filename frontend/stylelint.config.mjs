// frontend/AGENTS.md のスタイルの規則のうち、機械で確かめられるもの。
// 色と文字の大きさは globals.css の CSS 変数だけを使い、値を直に書かない
const valueRules = {
  "color-no-hex": true,
  "color-named": "never",
  "function-disallowed-list": ["rgb", "rgba", "hsl", "hsla", "hwb", "lab", "lch", "oklab", "oklch", "color"],
  // font の一括指定は文字の大きさを含むので、font-size を個別に書かせる
  "property-disallowed-list": ["font"],
  "declaration-property-value-allowed-list": { "font-size": ["/^var\\(--/"] },
};

/** @type {import("stylelint").Config} */
const config = {
  ignoreFiles: [".next/**", "node_modules/**"],
  rules: valueRules,
  overrides: [
    {
      // CSS 変数を定義する場所なので、値を直に書く
      files: ["app/globals.css"],
      rules: Object.fromEntries(Object.keys(valueRules).map((rule) => [rule, null])),
    },
  ],
};

export default config;
