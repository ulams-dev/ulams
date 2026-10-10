// Builds ulams-interactive.json for the gravity package (ADR 0086). The steps come from the code.
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { readSteps } from "./steps.mjs";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
export const version = JSON.parse(readFileSync(join(root, "package.json"), "utf8")).version;

/** The steps the landing hero cycles through: the solar system orbiting, with no tour panel (ADR 0093). */
export const SHOWCASE_STEPS = ["solar-system", "venus-rose", "sun-moving", "resonance"];

/** @param {{posters?: boolean}} options posters: reference posters/<id>.webp for every step */
export async function buildManifest({ posters = true } = {}) {
  const steps = (await readSteps()).map((s) => ({ ...s, ...(posters ? { poster: `posters/${s.id}.webp` } : {}) }));
  return {
    $schema: "https://ulams.dev/schemas/ulams-interactive/v1.json",
    id: "gravity",
    title: { en: "Gravity: a guided solar system", pl: "Grawitacja: Układ Słoneczny z przewodnikiem" },
    version,
    entry: "index.html",
    licence: "MIT",
    attribution:
      "© 2026 Mateusz Wojczal. Earth texture: Solar System Scope, CC BY 4.0. Three.js (MIT). Inter and Roboto Mono (SIL OFL 1.1).",
    source: { url: "https://github.com/ulams-dev/ulams/tree/main/demo-content/gravity", ref: "main" },
    locales: ["en", "pl"],
    defaultLocale: "en",
    bridge: 1,
    capabilities: { steps: true, reducedMotion: false, score: false, background: true },
    requires: ["webgl"],
    network: [],
    steps,
    ...(posters ? { showcase: { steps: SHOWCASE_STEPS, poster: "posters/showcase.webp" } } : {}),
    a11y: {
      keyboard:
        "The 3D view itself is pointer-only (drag to rotate). The time-speed slider, the Lagrange shot buttons and the stopped-galaxy buttons are native controls and work with the keyboard. The lesson page provides the stepper.",
      notes:
        "Every step has a text alternative in English and Polish, and a still poster that the lesson page shows when the system asks for reduced motion or WebGL is missing.",
    },
  };
}
