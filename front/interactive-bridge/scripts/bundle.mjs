// Builds dist/interactive-bridge.js: one minified ESM file with the MIT header (package side only).
import { build } from "esbuild";
import { mkdirSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
mkdirSync(join(root, "dist"), { recursive: true });

const banner = `/*! @ulams/interactive-bridge (ulams-ix v1) - MIT License - Copyright (c) 2026 ulams contributors - https://github.com/ulams-dev/ulams/tree/main/front/interactive-bridge */`;

await build({
  entryPoints: [join(root, "src/bundle-entry.ts")],
  outfile: join(root, "dist/interactive-bridge.js"),
  bundle: true,
  minify: true,
  format: "esm",
  target: "es2020",
  legalComments: "none",
  banner: { js: banner },
});
