# poland: Poland, measured / Polska w liczbach

A scrolling map of Poland and Europe with charts, as an ulams interactive: 40 steps in nine chapters (energy,
prosperity, security, made in Poland, daily life, mobility, health, people, the unfinished work), in English
and Polish. Every figure links to the institution that published it.

It is the owner's own project ([`qunabu/poland-october-2026`](https://github.com/qunabu/poland-october-2026)),
rebuilt for ulams: MIT code, CC BY 4.0 text and data (`LICENSE`, `LICENSE-content`, `NOTICE`). The saved
third-party article, the map copied from it and the screen recording are not used.

## Layout

| File | What |
|---|---|
| `index.html` | the page: CSS, markup, self-hosted fonts (`fonts/`) |
| `app.js` | the code: charts, map overlay (routes, markers, labels), story, step mode, bridge. Plain ES5 apart from one `import` |
| `data/steps.json` | the story: chapters, steps, figures and captions in `{en, pl}`, the source ids of each step |
| `data/sources.json` | the publisher and link behind every figure (primary sources only) |
| `data/world.topo.json` | the world map, regenerated from Natural Earth (`scripts/build-topo.mjs`, under 400 KB) |
| `vendor/` | copies of the bridge (`front/interactive-bridge`) and the map engine (`../shared/atlas.js`), synced by `yarn workspace @ulams/demo-content sync-bridge` |
| `ulams-interactive.json` | the manifest, generated from `data/steps.json` (`scripts/build-manifest.mjs`) |
| `posters/` | one WebP still per step (`scripts/posters.mjs`) |
| `scripts/check.mjs` | the lint: nothing third-party, every figure sourced, sizes, no network, manifest up to date |

## Run it

```bash
npx --yes serve demo-content/poland        # any static server; add ?lang=pl, ?instant (no animation)
yarn workspace @ulams/demo-content test    # unit tests and the lint
yarn workspace @ulams/demo-content test:e2e poland   # Playwright: sandbox, steps, EN/PL, reduced motion, keyboard, axe
node demo-content/scripts/pack.mjs demo-content/poland   # release/poland-ulams.zip, ready to upload
```

On its own it is a scroll story. Inside a lesson page it switches to **step mode**: the page owns the stepper,
the language and the narration; `goToStep` opens one step (the map view and its figures), every change is
reported with `stepChanged`, and `init.chrome` decides the layout: `none` shows the map and a panel of the
figures (a button shows it on a narrow screen), `minimal` shows one story card with Back and Next. The package
never sends `complete`; a topic completes when its step range ends.

## Accessibility

- The map is a picture; the places on it are listed as text for screen readers (`#mlist`) and each chart has
  a data table. The figures panel and the step buttons are native elements.
- Reduced motion (the system setting, `?instant`, or `init.reducedMotion`): the map stops (it draws only when
  something changes), the charts show their final values and the SMIL animations are paused.
  `pause` and `resume` from the lesson page do the same.
- Fonts and the map load from the package; nothing is requested from the network. axe finds no WCAG 2.2 AA
  violation on the panel (desktop and phone) or on the stand-alone page.

## What changed in the figures

The scroll story was once a reply to a September 2026 article; it is now neutral public statistics. Every
figure that rested only on that article, a press report or an encyclopedia was removed or replaced by the
number its publisher gives:

| Step | Before | Now (source) |
|---|---|---|
| `coal` | coal 72.5% to 52.7% (a press report) | coal 79.7% to 61.3% of electricity in the national power system, our calculation from PSE's annual tables; June 2025 renewables bar removed |
| `consumption` | household consumption +125% since 2016 (press) | +31.8% in real terms 2016 to 2025, against +10.4% in the euro area and +10.1% in Germany (Eurostat `nama_10_gdp`) |
| `eight` | 77.5 million people, $27.6k and $13.9k per person (an infographic) | 75.4 and 36.5 million, about $14.3k and $28.4k per person, calculated from the IMF's GDP and population (World Economic Outlook, April 2026) |
| `growth` | Q2 2026 +3.7% (a blog) | Eurostat's flash estimates of 14 August (Poland 3.7%, EU 1.2%, US 2.1%); Statistics Poland's later 3.8% is noted |
| `poverty` | pay PLN 9,229 and venture capital PLN 4.6 bn (an encyclopedia, a press report) | pay in the enterprise sector in June 2026, PLN 9,401.58 (Statistics Poland); the venture-capital figure is removed |
| `defence` | percentages from a press report | the same percentages, from NATO's table of 2026 estimates |
| `yards` | Homar-K plant, Barracuda, "launched on 13 August" (press) | ORP Wicher launched in August 2026 (ministries give 12 and 13 August), ICEYE, Baltic Power, the LNG terminal |
| `safe` | Global Peace Index rank (press) | 22nd, up 23 places (Institute for Economics and Peace) |
| `transit`, `roads` | Chopin 24.1 million and 290 km planned (press) | the same numbers from Warsaw Chopin Airport and GDDKiA |
| `life`, `ledger` | life expectancy and fertility 1.07 (press, the article) | 74.93 and 82.26 years, +8.7 and +7 years since 1990 (Statistics Poland); fertility 1.10 in 2024 (Statistics Poland); the 2027 deficit of 7.1% of GDP (Ministry of Finance); the rows without a primary source are gone |
| `gas`, `food`, `jobs`, `air`, `brands` | EU and Germany gas storage, "self-sufficient", unicorn count, essay remarks | removed; the figures that remain keep their publisher |
| `c-outlook`, `outro` | the outlook chapter | dropped: opinion, not measurement |

Every figure was then re-opened at its publisher (M8b) and the result is in [`FACTCHECK.md`](FACTCHECK.md): which
wordings were corrected (the NATO operations, the Ax-4 mission, the Kraków air sentence, "nearly 2.8 million"
containers), which claims were removed because the cited page does not show them (actual individual consumption, the
Kraków coal ban, "first Pole since 1978", the 32 allies), and which publisher pages could not be read in a script (GAZ-SYSTEM,
ULC, OpenAI), with the reason each is kept.
