# Spike results: visual e-mail editor library (M0)

Plan: `docs/plans/email-visual-editor.md` (approved with the recommended defaults Q1-Q8). Decision record:
`docs/decisions/0107-visual-email-editor-library.md` (Proposed). Run on 2026-10-21, time-boxed, throwaway
prototypes in `spike/email-editor/` (not in the workspaces, not in CI, not wired into the admin app; the PR can
be merged as docs only).

## Recommendation

**GrapesJS 0.23.8 + grapesjs-mjml 1.0.8, with four conditions**, and **one decision for the owner: the
bundle-size gate of the plan was tripped.**

1. The editor chunk is about 660 KB gzip against the plan's budget of 350 KB (+89 %, the decision rule allows
   +50 % = 525 KB). EmailBuilder.js is larger still (767 KB gzip) and does not keep MJML, so the gate does not
   favour the alternative. Roughly half of the GrapesJS chunk is `mjml-browser` (353 KB gzip). Recommendation:
   keep GrapesJS and move the budget to 700 KB gzip, because the chunk loads only when an admin opens the visual
   tab of an e-mail template, and Code mode (no new dependency, M2) stays the default path. If the owner wants
   the 350 KB budget enforced, neither candidate qualifies and M3 should be dropped, keeping M1 and M2.
2. Never feed the editor raw stored MJML without the representability check of plan 4.6. The spike found three
   silent-loss cases (below). The check as designed (parse, load, export, compare normalised trees) detects all
   three; it must additionally normalise self-closing tags first and ignore the `<tbody>` the browser inserts.
3. Replace GrapesJS defaults that break offline and i18n: `cssIcons: ''` (default loads Font Awesome 4.7 from
   cdnjs) and `i18n.detectLocale: false` (otherwise the locale falls back to `en`).
4. The editor is not keyboard operable out of the box (blocks and panel buttons are not focusable). Plan 4.8
   already assumes a keyboard alternative; the spike confirms it is required work in M5, not polish.

The decision rule "GrapesJS unless template 3 loses markup that the 4.6 check cannot detect" is satisfied: every
loss found is detectable.

## What was built

| Prototype | Path | What it does |
|---|---|---|
| GrapesJS + grapesjs-mjml | `spike/email-editor/grapes/` | Vite page that lazily imports the editor, loads a fixture, exports MJML. Scripts: `roundtrip.mjs` (fidelity), `audit.mjs` and `kbd.mjs` (axe, keyboard, CSP, offline), `i18n.mjs` |
| EmailBuilder.js | `spike/email-editor/emailbuilder/` | The upstream example app (`usewaypoint/email-builder-js`, `examples/vite-emailbuilder-mui`, commit of 2026-02-09) built unchanged; only `audit.mjs` and `render-test.mjs` are tracked |
| Fixtures | `spike/email-editor/fixtures/` | The three templates of the plan plus two more, see below |

Fixtures (all MJML):

1. `1-reset-password.mjml`: `ResetPasswordVariables` default, wrapped by `EmailVariables::wrapWithMjml` (text,
   button with `@VarActionLink` in `href`).
2. `2-training-due-soon.mjml`: compliance `TrainingDueSoonVariables` default.
3. `3-branded-handwritten.mjml`: `lang="pl"`, `mj-head` with `mj-title`, `mj-preview`, `mj-font`,
   `mj-attributes` (`mj-all`, `mj-text`, `mj-button`, `mj-class`), two `mj-style` (one `inline`), an HTML comment,
   `mj-raw` with an HTML table, `${VarUserName}`, Polish and Chinese text, a bare `@GlobalSettingsMailFooterMjml`
   token. Written the way the MJML docs write it, with self-closing head tags.
3b. `3b-branded-explicit-close.mjml`: the same with every `<mj-x ... />` rewritten as `<mj-x ...></mj-x>`.
4. `4-stress-constructs.mjml` (added): `mj-wrapper`, `mj-group`, `mj-table`, `mj-social`, `mj-accordion`,
   `mj-navbar`, `mj-hero`, `mj-divider`, `mj-spacer`, `mj-breakpoint`, `mj-html-attributes`, `<br/>`, `&copy;`,
   Outlook conditional comments inside `mj-raw`.

Fixtures 1 and 2 are hand-assembled from the PHP defaults (English text); the global-settings token is not
expanded because the plan keeps partials out of the editor.

## Results

### Round trip (GrapesJS)

Method: `editor.setComponents(mjml)`, `editor.getHtml()` (the plugin returns MJML), compare normalised trees
(attribute order and whitespace ignored; comments, `mj-raw`, `mj-head` children compared exactly), then render
original and exported MJML with `mjml` **4.17.1** (the version of `deploy/stack/mjml`) and compare the HTML.
Raw output is in `spike/email-editor/grapes/out/` (`roundtrip.json`, `*.exported.mjml`).

| Fixture | Tree equal | Server HTML equal | Result |
|---|---|---|---|
| 1 reset password | yes | yes | Survives. Export is one minified line (503 to 458 bytes), so the first visual save rewrites the whole text |
| 2 training due soon | yes | yes | Survives |
| 3 branded, self-closing head tags | **no** | **no** | **Silent corruption**: `mj-attributes` and `mj-style` end up nested inside `mj-font` (and `mj-text`, `mj-button`, `mj-class` inside `mj-all`). 14 lines lost, 15 added, 3 MJML validation errors, fonts, default attributes and styles stop working |
| 3b branded, explicit close tags | no, one benign diff | yes after normalising | Head, comment, `lang`, `mj-class`, `css-class`, `${VarUserName}`, `@Var...` in attributes, PL/ZH text, global-settings token all survive. The only differences: the browser parser inserts `<tbody>` in the `mj-raw` table and adds a `;` to its inline style |
| 4 stress | no | no | `mj-table` content is **flattened to text** (`CourseDue@VarCourseTitle@VarDueDate`, the rows and cells are gone) and `&copy;` becomes `©`. `mj-wrapper`, `mj-group`, `mj-hero`, `mj-social`, `mj-accordion`, `mj-navbar`, `mj-breakpoint`, `mj-html-attributes` and the Outlook conditional comments survive |

Lost or altered constructs found:

- Self-closing MJML tags (`<mj-font ... />`, `<mj-all ... />`, `<mj-class ... />`, `<mj-image ... />`): the plugin
  parses with the browser HTML parser, which ignores self-closing syntax on custom elements, so siblings become
  children. This is valid MJML that the production renderer accepts and that the docs use. It is the most
  dangerous finding because the damage is structural, and invisible in the canvas for head tags.
- `mj-table` inner `<tr>/<th>/<td>`: dropped by the HTML parser (table fix-up outside a table).
- Raw HTML inside `mj-raw`: re-serialised by the browser (`<tbody>` inserted, `;` added). Equivalent in e-mail
  clients, not byte-equal.
- Entities decoded (`&copy;` to `©`); fine for UTF-8 mail.
- Whitespace and formatting: everything is rewritten onto one line (diff noise, not loss).

How the plan's 4.6 check copes: tree comparison flags all of the above. Two changes to the design: (a) normalise
self-closing tags before loading (an XML-aware pass, then the check compares against the normalised source), so
fixture 3 can open in Visual mode instead of always falling to Code mode; (b) treat `<tbody>`/trailing `;` inside
`mj-raw` as equal. `mj-table` stays "Code mode only".

Variable tokens (`@VarActionLink` in `href`, `${VarUserName}`, `@GlobalSettings...` as bare text) were preserved
in every case. Variable chips (plan 4.4) were **not prototyped**; GrapesJS supports custom component types with
`toHTML`, so the approach stands but is unverified.

Server compatibility: exported MJML for fixtures 1, 2 and 3b rendered with 4.17.1 gives the same HTML as the
original. The canvas uses `mjml-browser` 4.18.0 (see the install issue below), one minor above the service.

### Round trip (EmailBuilder.js)

- There is **no MJML input or output**. The package renders its own JSON document to HTML
  (`renderToStaticMarkup`). 0 of 3 templates can be loaded; an importer does not exist and would have to be
  written and maintained by us.
- Hand-mapping fixtures 1 and 2 to `Text`/`Heading`/`Button` blocks works, tokens `@VarActionLink` and
  `${VarUserName}` pass through (`render-test.mjs`). Fixture 3 has no equivalent for `mj-head`, `mj-attributes`,
  `mj-class`, `mj-style`, `lang`, preview text, and the footer partial becomes plain text; the `Html` block is
  an escape hatch for body markup only. The output has no `<head>`, `<title>` or `lang`.
- Choosing it contradicts the owner decision "keep MJML as the stored format and renderer" and would bypass
  `MjmlService` and the stored `contentHtml` pipeline.

### Accessibility of the editor UI

axe 4.14 via Playwright (WCAG 2.0/2.1/2.2 A and AA tags), Chromium, template loaded.

| | GrapesJS (scope `#gjs`, blocks panel open) | EmailBuilder.js (whole page) |
|---|---|---|
| axe violations | **2 rules**: `color-contrast` (serious, 16 nodes, style manager headers), `scrollable-region-focusable` (serious, 1) | **4 rules, 17 nodes**: `button-name` (critical, 14 icon buttons), `image-alt` (critical, 1), `label` (critical, 1), `link-name` (serious, 1) |
| Keyboard | Panel buttons are `<span>` (0 of 15 focusable, 0 with `aria-label`, 14 with `title`); blocks are `<div draggable>` (0 of 15 focusable, no role). After starting at the first panel button and pressing Tab 80 times, focus never reached a panel button or a block; only the 27 style-manager inputs and the canvas iframe were reachable. **A block cannot be added without a mouse.** | Native MUI buttons and inputs: 34 tab stops, reachable; reordering and adding blocks are buttons, not drag only |
| Other | Canvas iframe has `title="Editor canvas"` | Page `<title>` is the upstream product name (we would fork anyway) |

axe sees only what is rendered at scan time (GrapesJS panels other than blocks were not scanned, and
drag-and-drop was not exercised), so the numbers are a floor. Neither editor reaches AA unmodified. EmailBuilder
is closer on keyboard operability, GrapesJS has fewer axe rules failing but a hard keyboard blocker. Code mode
remains the accessible path (R6 of the plan).

### Bundle size (Vite 8 production build, minified, lazy chunk)

| | Raw | Gzip |
|---|---|---|
| GrapesJS core chunk | 1,107 KB | 293 KB |
| grapesjs-mjml chunk (includes `mjml-browser`) | 1,228 KB | 353 KB |
| CSS (`grapes.min.css`) | 57 KB | 12 KB |
| Locale chunks (pl, zh, entry) | 9 KB | 5 KB |
| **GrapesJS total** | **about 2.4 MB** | **about 660 KB** (budget 350 KB, gate 525 KB) |
| EmailBuilder.js example app (one chunk: MUI, emotion, highlight.js, prettier, zustand, blocks) | 2,506 KB | 767 KB |

Not measured: brotli, and the admin's Umi/webpack build (the Vite numbers are comparable, not identical).
Possible reductions, not tried in the time box: drop `highlight.js`/`prettier` from a forked EmailBuilder app;
for GrapesJS, none cheap, because `mjml-browser` is vendored inside the plugin bundle.

### CSP needs (Chromium, `default-src 'none'` baseline)

| Need | GrapesJS | EmailBuilder.js |
|---|---|---|
| Inline styles | **yes**: `style-src-attr` and `style-src-elem` inline violations | **yes**: emotion injects `<style>` (`style-src-elem` inline) |
| `eval` | **violation reported** under `script-src 'self'`; in the smoke test (load, export) the editor still worked with eval blocked. Source not traced; other features may need it | none |
| Workers | none | none |
| Iframes | one same-origin canvas iframe (worked with `frame-src 'self'`) | none |
| Remote requests | Font Awesome 4.7 CSS from cdnjs by default | highlight.js CSS from cdnjs, hard-coded in `index.html` |

The admin CSP already allows `'unsafe-inline'` and `'unsafe-eval'` (ADR 0044), so no new relaxation, as the plan
required. The canvas iframe is same-origin, which is why 4.7 strips scripts and unsafe attributes before loading.

### Offline / no CDN

Blocking every non-localhost request: GrapesJS attempts exactly one editor-owned request, the Font Awesome CSS
(fix: `cssIcons: ''` and bundle the icon CSS, or replace the few icons; Font Awesome 4.7 is OFL-1.1 for the font
and MIT for the CSS, to be added to the dependency table only if kept). The other attempts in the run came from
the template under test (`mj-font` Google Fonts URL, example image). EmailBuilder attempts the highlight.js CSS.
Both work offline otherwise.

### Polish and Chinese

- Content: Polish and Chinese text typed or loaded round-trips unchanged in both.
- GrapesJS UI, with `i18n.detectLocale: false` (without it the locale stayed `en`): **Polish fully translated**
  (core plus the plugin's `pl`: panel titles, blocks such as "1 Kolumna", "Tekst", "Przycisk", style sectors).
  **Chinese**: core translated (panel titles, style sectors), the plugin ships no `zh`, so its blocks, undo, redo
  and import remain English (about 25 strings to add in `admin/src/locales/zh-CN/emailEditor.ts`, as the plan
  already expects). French has a plugin locale.
- EmailBuilder.js: no i18n layer; every label is hard-coded English in the forked app.

### Licences of the transitive packages

`license-checker-rseidelsohn --production` on a clean install of each candidate.

- GrapesJS + grapesjs-mjml: 16 packages, **MIT 13, BSD-3-Clause 2 (`grapesjs`, `grapesjs-mjml`), ISC 1 (the
  scratch root)**. Packages: backbone, backbone-undo, underscore, codemirror 5, codemirror-formatting,
  html-entities, promise-polyfill, mjml-browser, and five `@types/*` (MIT, types only, pulled in as ordinary
  dependencies by the plugin). `mjml-browser` is a vendored bundle, so its own dependencies are invisible to the
  checker; the `mjml@4.18.0` tree is an upper bound for them: 150 packages, MIT 108, ISC 21, BSD-2-Clause 15,
  BlueOak-1.0.0 4 (glob helpers used by the CLI only, not in the browser bundle), BSD-3-Clause 1, `slick`
  "MIT*" 1. No copyleft.
- EmailBuilder.js example app: 106 packages, MIT 99, BSD-3-Clause 4, ISC 2, `assignment` "MIT*" 1. No copyleft.
- `@axe-core/playwright` (dev only): MPL-2.0, as in `front/web`.

### Install issue worth knowing (risk R1)

`npm i grapesjs-mjml` **fails today** (`ETARGET: No matching version found for @types/mjml-core@~4.18`):
`grapesjs-mjml` depends on `@types/mjml ^4.7.4`, which resolves to `@types/mjml@4.18.0`, which requires an
`@types/mjml-core` 4.18 that does not exist on the registry. Workaround used in the spike: an `overrides` entry
(`"@types/mjml-core": "4.15.2"`), types only, no runtime effect; with it `mjml-browser` resolves to 4.18.0. Not
tested with yarn, which the monorepo uses; the equivalent is `resolutions`. This strengthens the plan's fallback
of vendoring the plugin under `admin/src/lib/grapesjs-mjml` if upstream does not fix it.

### Effort to embed in antd

- GrapesJS: framework-agnostic, mounts into a `<div>`, global `.gjs-*` CSS that does not collide with antd,
  imperative API (a `useEffect` wrapper, `destroy()` on unmount as in plan 4.2). Own look, not antd. Moderate.
- EmailBuilder.js: an application to fork, MUI 5 + emotion + zustand beside antd (second design system), plus an
  MJML importer/exporter we would have to build. High.

## Decision summary against the plan's rule

| Criterion | GrapesJS + grapesjs-mjml | EmailBuilder.js |
|---|---|---|
| Keeps MJML, existing templates load | yes, with the 4.6 check | no |
| Template 3 loses undetectable markup | no (all losses detectable) | n/a (cannot load) |
| Chunk within budget +50 % | **no, +89 %** | no, +119 % |
| Keyboard | blocker, needs custom work | usable |
| PL / ZH | PL complete, ZH partial | none |
| CSP | no new relaxation | no new relaxation |
| Offline | one default CDN request, fixable | one CDN request in index.html, fixable |

## Reproduce

```
cd spike/email-editor/grapes
npm install                  # overrides entry works around the @types/mjml-core problem
npx vite build && npx vite preview --port 5199 --strictPort &
node roundtrip.mjs && node audit.mjs && node kbd.mjs && node i18n.mjs
```

EmailBuilder: copy `examples/vite-emailbuilder-mui` of `usewaypoint/email-builder-js` into
`spike/email-editor/emailbuilder/`, replace its `tsconfig.json` with the tracked one (the upstream one extends a
file outside the folder), `npm i`, `npx vite build --base /`, then `npm i --no-save @playwright/test
@axe-core/playwright` and `node audit.mjs`, `node render-test.mjs`.

## Not done in the time box

Variable chips, the 4.6 check itself, keyboard alternative, real antd embedding, the global
footer partial after splicing (only the bare token was tested), `mj-include` (the plan rejects it), brotli sizes,
yarn resolution of the `@types` problem.
