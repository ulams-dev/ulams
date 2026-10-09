// Reads the tour's steps (ids, English and Polish titles and texts) straight from src/ui/tour.ts, so the
// manifest can never drift from the code. tour.ts only touches the DOM inside the Tour class, so the
// bundled module can be imported in Node.
import { build } from "esbuild";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");

/** @returns {Promise<Array<{id: string, title: {en: string, pl: string}, text: {en: string, pl: string}}>>} */
export async function readSteps() {
  const out = await build({
    entryPoints: [join(root, "src/ui/tour.ts")],
    bundle: true,
    write: false,
    format: "esm",
    platform: "node",
    logLevel: "silent",
  });
  const code = out.outputFiles[0].text;
  const mod = await import(`data:text/javascript;base64,${Buffer.from(code).toString("base64")}`);
  const { STEPS, PL } = mod;
  return STEPS.map((s) => {
    const pl = PL[s.id];
    if (!pl) throw new Error(`step "${s.id}" has no Polish translation`);
    return { id: s.id, title: { en: s.title, pl: pl.title }, text: { en: s.body, pl: pl.body } };
  });
}
