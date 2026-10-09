import js from "@eslint/js";
import tseslint from "typescript-eslint";

export default tseslint.config(
  { ignores: ["src/generated/**", "dist/**", "node_modules/**", "coverage/**", "evals/reports/**"] },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  {
    files: ["scripts/**", "evals/**", "tests/e2e/*.mjs"],
    languageOptions: { globals: { process: "readonly", console: "readonly", fetch: "readonly", URL: "readonly", AbortSignal: "readonly", setTimeout: "readonly" } },
  },
  {
    rules: {
      "@typescript-eslint/no-unused-vars": ["error", { argsIgnorePattern: "^_", varsIgnorePattern: "^_" }],
    },
  }
);
