import { defineConfig, globalIgnores } from "eslint/config";
import nextVitals from "eslint-config-next/core-web-vitals";
import nextTs from "eslint-config-next/typescript";

const PAGE_HEADER = new Set(["SiteHeader"]);
const HEADER_ELEMENTS = new Set(["SiteHeader", "SiteHeaderFrame"]);

function rendersElement(node, names) {
  return node.name.type === "JSXIdentifier" && names.has(node.name.name);
}

// frontend/AGENTS.md の「Site header」: layout はクライアントの遷移で描画し直されないので、
// ログイン状態を含むヘッダーは各 page が描画する
const siteHeaderPlugin = {
  rules: {
    "require-in-page": {
      meta: { type: "problem", messages: { missing: "page.tsx・not-found.tsx は <SiteHeader /> を描画する" } },
      create(context) {
        let found = false;
        return {
          JSXOpeningElement(node) {
            if (rendersElement(node, PAGE_HEADER)) found = true;
          },
          "Program:exit"(node) {
            if (!found) context.report({ node, messageId: "missing" });
          },
        };
      },
    },
    "forbid-in-layout": {
      meta: {
        type: "problem",
        messages: { forbidden: "layout はクライアントの遷移で描画し直されないので、ヘッダーは各 page で描画する" },
      },
      create(context) {
        return {
          JSXOpeningElement(node) {
            if (rendersElement(node, HEADER_ELEMENTS)) context.report({ node, messageId: "forbidden" });
          },
        };
      },
    },
  },
};

const eslintConfig = defineConfig([
  ...nextVitals,
  ...nextTs,
  // frontend/AGENTS.md の依存の規則のうち、機械で確かめられるもの
  {
    rules: {
      "import/no-cycle": "error",
      "import/no-restricted-paths": [
        "error",
        {
          zones: [
            {
              target: "./lib/auth.ts",
              from: "./lib/api",
              message: "lib/auth.ts は cookie と遷移先だけを扱い、Laravel を呼ぶ lib/api/ に依存しない",
            },
          ],
        },
      ],
      "no-restricted-globals": [
        "error",
        { name: "fetch", message: "Laravel は lib/api/client.ts の apiFetch・apiFetchAsUser を通して呼ぶ" },
      ],
      "no-restricted-syntax": [
        "error",
        {
          selector: "JSXOpeningElement[name.name='form']",
          message: "フォームは components/form.tsx の Form を使う(<form action> は 422 のときも入力をリセットする)",
        },
      ],
      "react/forbid-dom-props": [
        "error",
        { forbid: [{ propName: "style", message: "スタイルは同じフォルダの <name>.module.css に書く" }] },
      ],
      "react/forbid-component-props": [
        "error",
        { forbid: [{ propName: "style", message: "スタイルは同じフォルダの <name>.module.css に書く" }] },
      ],
    },
  },
  {
    files: ["app/**/page.tsx", "app/**/not-found.tsx"],
    plugins: { "site-header": siteHeaderPlugin },
    rules: { "site-header/require-in-page": "error" },
  },
  {
    files: ["app/**/layout.tsx"],
    plugins: { "site-header": siteHeaderPlugin },
    rules: { "site-header/forbid-in-layout": "error" },
  },
  {
    files: ["lib/api/client.ts"],
    rules: { "no-restricted-globals": "off" },
  },
  {
    files: ["components/form.tsx"],
    rules: { "no-restricted-syntax": "off" },
  },
  // Override default ignores of eslint-config-next.
  globalIgnores([
    // Default ignores of eslint-config-next:
    ".next/**",
    "out/**",
    "build/**",
    "next-env.d.ts",
  ]),
]);

export default eslintConfig;
