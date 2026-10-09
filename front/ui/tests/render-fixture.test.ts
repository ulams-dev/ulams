import { writeFileSync } from "node:fs";
import { describe, it } from "vitest";
import { renderDoc } from "./astro-render.ts";

/**
 * Not a unit test: the Playwright spec front/web/tests/e2e/interactive-frame.spec.ts runs this file
 * (ULAMS_RENDER_IN / ULAMS_RENDER_OUT) to get the server-rendered HTML of the real InteractiveLesson
 * component for its throwaway pages. Without those variables it does nothing.
 */
const input = process.env.ULAMS_RENDER_IN;
const output = process.env.ULAMS_RENDER_OUT;

describe.skipIf(!input || !output)("render fixture pages", () => {
  it("renders the documents of the e2e spec", async () => {
    const docs = JSON.parse(input!) as Record<string, unknown>;
    const out: Record<string, string> = {};
    for (const [name, doc] of Object.entries(docs)) out[name] = await renderDoc(doc as never);
    writeFileSync(output!, JSON.stringify(out));
  });
});
