// Lint: the generated manifest is valid, and every step has a title and a text in English and Polish.
import { validateManifest } from "../../scripts/lib/manifest.mjs";
import { buildManifest } from "./manifest.mjs";

const manifest = await buildManifest();
const problems = validateManifest(manifest);
if (problems.length) {
  console.error(problems.map((p) => `  ${p}`).join("\n"));
  process.exit(1);
}
console.log(`gravity manifest OK: ${manifest.steps.length} steps, ${manifest.locales.join("+")}`);
