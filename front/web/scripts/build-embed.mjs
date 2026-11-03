/**
 * Bundles the embeddable widgets that plain pages load with one script tag into `src/embed/` (not committed); the
 * route `src/pages/embed/[file].ts` serves them with CORS headers. Today: `ulams-certificate-badge.js` (no dependencies, ES module).
 * Runs before `astro build` and `astro dev`; the output is not committed.
 */
import { build } from "esbuild";
import { fileURLToPath } from "node:url";

const root = fileURLToPath(new URL("..", import.meta.url));

await build({
  entryPoints: { "ulams-certificate-badge": fileURLToPath(new URL("../../ui/src/elements/certificate-badge.ts", import.meta.url)) },
  outdir: `${root}src/embed`,
  bundle: true,
  format: "esm",
  target: "es2020",
  minify: true,
  logLevel: "warning",
});
