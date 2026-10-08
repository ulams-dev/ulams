module.exports = {
  root: true,
  env: { browser: true, es2020: true },
  extends: [
    "eslint:recommended",
    "plugin:@typescript-eslint/recommended",
    "plugin:react-hooks/recommended",
    "plugin:jsx-a11y/recommended",
  ],
  ignorePatterns: ["dist", ".eslintrc.cjs", "src/lib", "web", "sdk", "ui"],
  parser: "@typescript-eslint/parser",
  plugins: ["react-refresh", "jsx-a11y"],
  rules: {
    "react-refresh/only-export-components": [
      "off",
      { allowConstantExport: true },
    ],
    "@typescript-eslint/no-explicit-any": "warn",
    "@typescript-eslint/ban-ts-ignore": "off",
    "@typescript-eslint/ban-ts-comment": "off",
    "@typescript-eslint/triple-slash-reference": "off",
    "@typescript-eslint/no-unused-vars": "warn",
    "@typescript-eslint/ban-types": "off",
    "react-hooks/rules-of-hooks": "warn",
    "no-dupe-else-if": "warn",
    "prefer-spread": "off",
    "@typescript-eslint/no-var-requires": "off",
    "no-extra-boolean-cast": "off",
    // H5P/Lumi is GPL: it runs only in the separate api/h5p service, framed via iframe
    // styled-components was replaced by CSS Modules + --ulams-* variables (src/lib/components/theme/README.md)
    "no-restricted-imports": [
      "error",
      {
        paths: [
          {
            name: "styled-components",
            message:
              "Use a CSS Module and var(--ulams-*) variables (see src/lib/components/theme/README.md).",
          },
        ],
        patterns: [
          {
            group: ["@lumieducation/*", "h5p-*", "@escolalms/h5p-react"],
            message:
              "GPL H5P code must stay in api/h5p; use the H5PFrame iframe wrapper instead.",
          },
          {
            group: ["styled-components/*", "babel-plugin-styled-components"],
            message:
              "Use a CSS Module and var(--ulams-*) variables (see src/lib/components/theme/README.md).",
          },
        ],
      },
    ],
  },
};
