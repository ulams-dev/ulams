# ulam: five small interactives for the Stanisław Ulam course

Plain ES2020 modules with `// @ts-check` and JSDoc types, no framework, no build, each a package the Interactive
topic type plays inline in a sandboxed frame (plan `docs/plans/interactive-demos.md`, section 6.3). English only.
MIT code and CC BY 4.0 text (`LICENSE`, `LICENSE-content`, `CREDITS.md`, shared by the packages in this folder).

| Package | Steps | What the learner does | Completes |
|---|---|---|---|
| [`spiral/`](spiral) | `grid`, `primes`, `diagonals`, `explore` | Writes the integers on a square spiral, marks the primes (a sieve), highlights n² + n + 41 | `complete` after `explore` |
| [`monte-carlo/`](monte-carlo) | `idea`, `throw`, `converge`, `error` | Throws seeded random points into a square to estimate π and watches the error shrink on a log-log chart | `complete` after 10,000 throws |
| [`automaton/`](automaton) | `rule30`, `rule90`, `rule110`, `life`, `ulam-growth` | Runs elementary rules (any number 0 to 255), Conway's Life (editable by keyboard) and a growth rule from one cell | `complete` once three different steps were visited |
| [`scottish-book/`](scottish-book) | `intro` and one step per problem (19, 153, 193) | Reads notebook cards, filters by poser and guesses the outcome of each problem; every guess sends a score | the topic's `on_score` rule (pass 60%), never `complete` |
| [`lwow-map/`](lwow-map) | `lwow`, `princeton`, `harvard`, `madison`, `los-alamos`, `boulder`, `santa-fe` | Follows a journey on a map (the shared map engine, great-circle legs, a schematic inset of central Lwów); the stops are also a button list | the end of the topic's step range (`on_range_end`) |

## How a package is built

- `index.html`, `main.js` (the page: DOM, canvas, controls), `logic.js` (the pure logic: no DOM, so the unit tests
  in `demo-content/tests/unit/` run it in Node), `style.css`, `ulams-interactive.json` (the manifest, the one
  source of the step titles and texts), `posters/` (one WebP still per step, `node scripts/posters.mjs <package>`).
- `vendor/` holds copies of the bridge (`front/interactive-bridge`) and the shared shell
  (`demo-content/shared/ulam-shell.{js,css}`: the step card, Back and Next inside the lesson's step range, the
  bridge wiring, reduced motion, poster mode, the notebook look). `yarn workspace @ulams/demo-content sync-bridge`
  refreshes them and the lint fails when a copy differs.
- The lesson page asks for `chrome: full` when it plays the package inline: the package shows its own step card.
  With `none` (background display) only the interactive shows. `?ulams-poster#<step>` renders a still.
- The package fetches its own manifest, so it holds `ready` back until it knows its steps (`whenReady`).

`scottish-book` and `lwow-map` contain **placeholder text** until the fact sheet is cleared (plan section 7.3, M9a);
their data files (`data/problems.json`, `data/route.json`, `data/places.json`) are filled in M9b. The package
says so on screen and its unit test pins it, so a placeholder cannot ship as a fact.

## Rules every package follows

- Keyboard only works: every control is a native element, and a canvas that holds data is a focusable region
  with arrow-key navigation and a text read-out.
- Reduced motion (the system setting or `init.reducedMotion`): no animation, the final state is drawn.
- Nothing is loaded from the network, nothing is stored in the browser, no model-written code.
- A code size under 150 KB (without posters and data), a zip under 3 MB.
- Playwright checks per package (`tests/e2e/ulam-<name>.spec.mjs`, built on `ulam-common.mjs`): it loads in the
  sandbox, steps advance by keyboard and from the lesson page, a range limits navigation, none chrome and poster
  mode, reduced motion, and axe (WCAG 2.2 AA) on every step, in none chrome and on a phone.
