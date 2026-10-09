// Writes ulams-interactive.json from data/steps.json (dev only, output committed; check.mjs fails when they differ):
//   node scripts/build-manifest.mjs
// The step ids, titles and texts come from the data the page itself renders, so the text alternative the
// lesson page shows can never drift from the figures on the map.
import { readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const stripTags = (s) => String(s).replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").replace(/ ([.,;:])/g, "$1").trim();

export function buildManifest() {
  const data = JSON.parse(readFileSync(join(root, "data", "steps.json"), "utf8"));
  const text = (st, lang) => {
    if (st.type === "hero") return { title: stripTags(data.hero.title[lang]), text: `${stripTags(data.hero.sub[lang])}. ${stripTags(data.hero.lede[lang])}` };
    if (st.type === "chapter") return { title: st.title[lang], text: st.lede[lang] };
    return { title: st.title[lang], text: stripTags(st.body[lang]) };
  };
  const steps = data.steps.map((st) => ({
    id: st.id,
    title: { en: text(st, "en").title, pl: text(st, "pl").title },
    text: { en: text(st, "en").text, pl: text(st, "pl").text },
    poster: `posters/${st.id}.webp`,
  }));
  return {
    $schema: "https://ulams.dev/schemas/ulams-interactive/v1.json",
    id: "poland",
    title: { en: "Poland, measured: a map and charts", pl: "Polska w liczbach: mapa i wykresy" },
    version: "1.0.0",
    entry: "index.html",
    licence: "MIT",
    attribution:
      "© 2026 Mateusz Wojczal. Code: MIT. Text and data: CC BY 4.0. Map: Natural Earth (public domain) through world-atlas (ISC). Fonts: Barlow, Barlow Semi Condensed, JetBrains Mono (SIL OFL 1.1). Every figure links to the institution that published it.",
    source: { url: "https://github.com/ulams-dev/ulams/tree/main/demo-content/poland", ref: "main" },
    locales: ["en", "pl"],
    defaultLocale: "en",
    bridge: 1,
    capabilities: { steps: true, reducedMotion: true, score: false, background: true },
    requires: [],
    network: [],
    steps,
    a11y: {
      keyboard:
        "The map is a picture: the places on it are listed as text for screen readers, and the figures are charts with data tables. Step navigation is provided by the lesson page; on a narrow screen a native button shows or hides the figures panel.",
      notes:
        "Every step has a text alternative in English and Polish and a still poster. With reduced motion the map stops moving and the charts show their final values.",
    },
  };
}

if (import.meta.url === `file://${process.argv[1]}`) {
  writeFileSync(join(root, "ulams-interactive.json"), `${JSON.stringify(buildManifest(), null, 2)}\n`);
  console.log("wrote ulams-interactive.json");
}
