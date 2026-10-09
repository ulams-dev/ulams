import { defineConfig } from "tsup";

export default defineConfig({
  entry: { ulams: "src/bin.ts" },
  format: ["esm"],
  target: "node22",
  platform: "node",
  outDir: "dist",
  clean: true,
  outExtension: () => ({ js: ".mjs" }),
  sourcemap: false,
  // The SDK is private TypeScript source: inline it. Runtime deps stay external.
  noExternal: ["@ulams/sdk", "@ag-ui/core"],
  external: ["zod", "yaml", "@modelcontextprotocol/server", "@modelcontextprotocol/node"],
  banner: { js: "#!/usr/bin/env node" },
});
