import { defineConfig, globalIgnores } from "eslint/config";
import nextVitals from "eslint-config-next/core-web-vitals";
import nextTs from "eslint-config-next/typescript";

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
    },
  },
  {
    files: ["lib/api/client.ts"],
    rules: { "no-restricted-globals": "off" },
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
