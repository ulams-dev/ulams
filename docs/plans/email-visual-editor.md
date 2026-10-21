# Plan: visual e-mail template editor on top of MJML

Status: **draft, waiting for owner approval.** No implementation starts before approval.

## 1. Goal

Tenant admins can design e-mail templates visually (drag blocks, edit text in place, insert
variables, preview with sample data, send a test to themselves) without writing MJML, while MJML
stays the stored format and the renderer. Hand-written MJML keeps working and is never silently
rewritten or truncated.

Owner decision this plan builds on (to be recorded in "Decisions made" and in the ADR drafted at the
end of the spike, milestone M0): **keep MJML** (MIT, already integrated, portable, existing templates
stay valid) and **add a visual editor on top of it**; do not replace MJML with a JSON block format.
Commercial embeddable editors (Unlayer, BEE, Topol) are out: proprietary or hosted, which conflicts
with self-hosting and air-gapped installs (spec principle 8, "easy to self-host").

## 2. Scope and TODO items covered

No existing TODO item covers this. The closest, Phase 5 "Admin UX: templates, guided empty states,
sample course, bulk operations, saved filters", means course templates for time to first course
(spec 5.4), not e-mail templates. Proposed new item, under Phase 5 next to it (added to
`docs/ROADMAP-TODO.md` only after approval):

```
- [ ] (new) Visual e-mail template editor for tenant admins on top of MJML: MJML stays the stored
      format and renderer, code view fallback for markup the editor cannot represent, preview with
      sample data, test send to self, EN/PL/ZH editor UI (see docs/plans/email-visual-editor.md)
```

Related existing items that this plan touches but does not tick:

- Phase 5 "**WCAG 2.2 AA** + European Accessibility Act; axe in CI": the admin editor page gets an
  axe check (staff UI, not learner UI, so this is good practice, not the non-negotiable).
- 0.1c (done) "mjml: the `mjml` compose service is not on the `ulams` network…": M1 finishes the
  hardening it started (client timeouts, no includes on every renderer path).

In scope: the e-mail channel (`templates-email`) only. Out of scope: SMS and PDF templates (PDF has
its own pdfme track, Phase 6), AI-drafted e-mails (would need the "AI proposes, author approves"
diff flow; separate item if wanted), MJML 5 upgrade (separate decision, see risks), bulk
notifications screen (`admin/src/pages/Users/BulkNotifications`) beyond reusing the same field.

## 3. Current state

### What admins edit today

- Admin route `/configuration/templates/:template/:id` (`admin/config/routes.ts:552-567`), page
  `admin/src/pages/Templates/form.tsx`. Sections come from the channel: for e-mail `title` (text,
  the subject), `content` (MJML) and `contentHtml` (read-only, pre-rendered).
- The `content` section is rendered by `admin/src/components/TemplateFields/index.tsx`, case
  `'mjml'`: a plain `AutoCompleteArea` (textarea with `@Var…` autocomplete), a link to
  `https://mjml.io/try-it-live` (an external site, so admins paste their template into a third-party
  page to see it), and the required variables as red tags. There is no in-app visual preview.
- **Preview** (`admin/src/pages/Templates/components/PreviewButton.tsx` →
  `GET /api/admin/templates/{id}/preview`) sends the *saved* template, filled with mocked variables,
  to the current admin's own address (`EmailChannel::preview`). It cannot preview unsaved changes.
- Global reusable parts (header, footer) are settings in the `mail` group; each public setting
  becomes a variable `@GlobalSettings<Key><Type>` (`api/packages/templates/src/Core/SettingsVariables.php`)
  and its MJML is spliced into the template text before rendering.
- Admin locales: `admin/src/locales/{en-US,pl-PL,fr-FR}` are complete; `zh-CN` is a stub (751 B, no
  `templates.ts`). Admin UI uses antd 4 / ProComponents, React 18, Umi (`admin/package.json`).

### How rendering, pre-rendering and variables work

- `Template::register(Event, EmailChannel::class, Variables::class)`: 18 registrations in
  `api/packages/templates-email`, 1 in `api/packages/compliance`
  (`compliance/src/Mail/*Variables.php`, extending `Ulams\TemplatesEmail\Core\EmailVariables`).
  A variable class declares `variables()`, `mockedVariables()` (sample data), `variablesFromEvent()`,
  `requiredVariables()`, `requiredVariablesInSection()`, `defaultSectionsContent()`
  (`templates/src/Contracts/TemplateVariableContract.php`). `GET /api/admin/templates/variables`
  returns the events with their tokens.
- Variables are written `@VarName`; `${VarName}` is accepted too
  (`TemplateVariablesService::convertVarNameToAllFormats`).
- On save, `EmailChannel::processTemplateAfterSaving` splices the global settings, then: if the
  content contains `<mjml>`, renders it with `MjmlService`; otherwise treats it as HTML and runs
  HTMLPurifier. The result is stored in the `contentHtml` section (pre-rendered, so sending does not
  call the renderer per message).
- On send, `TemplateService::generateContentUsingVariables` does `strtr` of the variable values
  into the stored HTML. **Values are not HTML-escaped** in the e-mail channel (only the pdfme JSON
  branch escapes). Pre-existing issue, see risk R7.
- `MjmlService::renderer()`: `MJML_API_URL` (HTTP service) wins, then `MJML_BINARY_PATH` if
  executable, else `PlainTextRenderer` with a one-time warning.
- HTTP client `templates-email/src/Services/CurlApi.php`: `CURLOPT_TIMEOUT => 0` (no timeout),
  `CURLOPT_FOLLOWLOCATION => true`, `CURLOPT_MAXREDIRS => 10`, MJML `errors` in the response are
  dropped. M1 fixes these.
- Validation: `Rules/MjmlRule.php` only checks `<mjml>…</mjml>` and is used only for the
  administrable default wrapper config; template content is checked only for required variables.

### The render service

- `deploy/stack/mjml/server.mjs` (ADR 0102): Node 22, `mjml` **4.17.1**, `POST /v1/render`,
  body limit 1 MiB, `validationLevel: "soft"`, `ignoreIncludes: true`, returns `{html, errors,
  mjml_version}`. Internal port 15500, not exposed by the gateway. Built by
  `.github/workflows/publish.yml` for amd64 and arm64.
- Drift: `api/docker-compose.yml:267` and `deploy/vps-cloudflare/compose.yml:372` still run
  `danihodovic/mjml-server:4.15.3`, `front/docs-site/examples/production/docker-compose.yml:299` runs
  `danihodovic/mjml-server:latest`, and `front/docs-site/.../operators/email.mdx` documents that
  image. So three MJML versions are live (4.15.3, 4.17.1, latest). See open question Q4.
- The binary renderer path (`Mjml/BinaryRenderer.php`, qferrer CLI) does **not** pass
  `ignoreIncludes`, so `<mj-include path="/etc/…">` would read local files on that path. Only used
  when an operator sets `MJML_BINARY_PATH`; still closed in M1.

### MJML actually in use

Default contents use only `mj-section`, `mj-column`, `mj-text`, `mj-button` (most built by
`EmailVariables::wrapWithMjml`, which wraps the event text in the configurable default template).
Tenants may have hand-written templates with `mj-head`, `mj-attributes`, `mj-style`, `mj-raw`,
comments and global-settings partials; we cannot see tenant databases, so the editor must assume
any valid MJML 4 can arrive.

### Docs

`front/docs-site/src/content/docs/admin/templates.mdx` (admin guide),
`front/docs-site/src/content/docs/operators/email.mdx` (operators),
`api/packages/templates-email/{README.md,ADMIN.md}` (package docs, still showing upstream
screenshots).

## 4. Design

### 4.1 Candidates (decided by the M0 spike)

| | GrapesJS + `grapesjs-mjml` | EmailBuilder.js (Waypoint) |
|---|---|---|
| Format | Reads and writes MJML; renders the canvas with `mjml-browser` | Own JSON document → HTML; no MJML in or out |
| Licence | `grapesjs` 0.23.8 **BSD-3-Clause**, `grapesjs-mjml` 1.0.8 **BSD-3-Clause** (not MIT as first assumed; permissive, notice must be kept) | `@usewaypoint/email-builder` 0.0.9 MIT |
| Maintenance (checked 2026-10-21) | core: release 2026-10-15, ~26k stars, 32 open issues; mjml plugin: release 2026-03-13 (yearly cadence), last push 2026-06-15, ~720 stars, 37 open issues | npm release 2026-01-09, last push 2026-02-09, ~1.8k stars, 52 open issues, version 0.0.x |
| MJML version | plugin depends on `mjml-browser ^4.18.0` (MJML 4 line; no MJML 5 support yet). Our service is 4.17.1 | n/a |
| Editor UI | full editor (canvas, layers, style manager, asset manager, code import/export) | the npm package is the renderer; the editor is the example app to fork (MUI 5, emotion, zustand, highlight.js, prettier) — MUI is a second design system next to antd |
| i18n | core has `pl` and `zh` locales; the mjml plugin has `pl`, **no `zh`** (we ship our own strings) | none; strings live in the forked app |
| Existing templates | load as is (to be proven on template 3) | need an MJML → JSON importer that does not exist; anything unmapped is lost |
| Fit with owner decision | direct | contradicts "keep MJML" for visual templates; kept as the documented alternative |

Recommended default going into the spike: **GrapesJS + grapesjs-mjml**, unless it fails the
round-trip criterion on template 3 in a way the code-view fallback cannot contain.

### 4.2 Editor embedding in the admin app

- New component `admin/src/components/EmailTemplateEditor/` with three modes (tabs):
  **Visual**, **Code**, **Preview**. `TemplateFields` case `'mjml'` renders it instead of the bare
  `AutoCompleteArea`; the form still owns the value through a hidden `ProFormTextArea`, so
  validation, unsaved-changes prompt and submit are unchanged.
- The visual editor is code-split: `import('grapesjs')`, `import('grapesjs-mjml')` inside the
  component (same pattern as `components/PdfEditor/index.tsx:113` for `@pdfme/ui`), so the admin
  main bundle does not grow. CSS imported from the package, not from a CDN.
- Mounted in a fixed-height container in the form; one editor instance per page; destroyed on
  unmount (`editor.destroy()`) to avoid leaking the canvas iframe.
- GrapesJS storage manager disabled (`storageManager: false`); the form is the only store. No
  localStorage autosave of drafts (templates are tenant data, shared admin machines).
- Feature switch: a public setting `templates_email.visual_editor` (default `true`) lets an
  operator fall back to code-only if a regression appears; read like the existing
  `hideTemplateTab-*` settings.

### 4.3 Loading and saving MJML

- Load: `editor.setComponents(mjml)` only after the representability check (4.6) passes;
  otherwise the template opens in Code mode.
- Save: `editor.getHtml()` from the mjml plugin returns MJML; the component writes it to the form
  field on every `update` event (debounced 300 ms). The server path is unchanged: save →
  `processTemplateAfterSaving` → render → `contentHtml`.
- Switching Visual → Code shows the exported MJML; Code → Visual re-runs the check. Edits in Code
  mode are never reformatted unless the admin switches to Visual and confirms.
- New templates still start from `defaultSectionsContent()` of the event's variable class.

### 4.4 Variables insertion

- A side panel lists the event's variables from `GET /api/admin/templates/variables`
  (event variables first, then global settings), with the required ones marked and a live
  "missing required variables" list computed with the same rule as the server
  (`@Var` or `${Var}` form).
- Visual mode: a custom GrapesJS inline component type `ulams-variable` rendered as a
  non-editable chip; it exports as the plain token `@VarName`, so stored MJML is unchanged and the
  server code needs no change. Clicking a chip-panel entry inserts at the caret in the selected
  `mj-text`; for attributes that take URLs (`mj-button href`, `mj-image src`) the trait input offers
  the same list.
- Global settings partials (`@GlobalSettingsMail…`) that hold MJML are shown as locked blocks with
  a "edit in Settings" link, never expanded into the template (expanding would freeze the shared
  footer into every template).
- Code mode keeps `AutoCompleteArea`-style completion (reuse `components/AutoCompleteArea`).

### 4.5 Preview with sample data and test send

New endpoints in `api/packages/templates-email/src/routes.php` (today empty), controller
`Http/Controllers/EmailTemplatePreviewController.php`, both `auth:api` + `template_update`
permission (via a FormRequest using `TemplatePolicy`), throttled `throttle:30,1` per user:

- `POST /api/admin/templates/email/render` — body `{event, title, content}` (draft, not saved).
  Resolves the variable class for `event` + `EmailChannel`, splices global settings, fills
  `mockedVariables($request->user())`, renders through `MjmlService`, returns
  `{html, title, errors[], missing_variables[]}`. Nothing is stored, nothing is sent.
- `POST /api/admin/templates/email/test-send` — same body; renders as above and sends **only to
  the authenticated admin's own address** (no recipient field, so it cannot be used as a spam
  relay), subject prefixed `[Test]`. Throttled tighter, `throttle:5,1`.
  The existing `GET /api/admin/templates/{id}/preview` stays for saved templates and API
  compatibility.

Sample data comes from the existing `mockedVariables()` of each variable class, so every
registered event (including compliance) gets a preview without new code. Admins can override a
sample value in the panel for this preview only (sent as `sample_overrides`, validated to the
event's variable names, strings ≤ 500 chars).

Preview pane in the admin: `<iframe sandbox="" srcdoc={html} title="…">`, see 4.7.

### 4.6 Round trip of markup the editor cannot represent

Rule: **never silently lose markup.**

1. Representability check before loading into Visual mode, in
   `admin/src/components/EmailTemplateEditor/roundTrip.ts`: parse the MJML, load into a headless
   GrapesJS instance, export, and compare normalised trees (attribute order, whitespace and
   self-closing differences ignored; comments, `mj-raw`, `mj-head` children, unknown tags and
   attributes compared exactly).
2. Equal → Visual mode allowed.
3. Different → the template opens in Code mode with a banner listing what the visual editor would
   drop or change (e.g. "`<mj-raw>` at line 12, `mj-class` on `mj-text`"), and Visual mode is
   offered only as an explicit "Convert and review" action that shows a text diff of old vs new
   MJML and requires confirmation. Without confirmation the original text is saved byte for byte.
4. Server-side safety net: the admin sends `editor_mode` (`visual`|`code`) with the update; no
   server rewrite of MJML ever happens (the server stores what it receives), so the only loss path
   is the client, covered by the check and the tests in section 7.
5. Plain HTML content (templates without `<mjml>`, which the server purifies as HTML) always opens
   in Code mode.

### 4.7 Sanitisation and security of admin-provided MJML/HTML

Admin MJML is authored by staff but must still be treated as untrusted: another admin of the
same tenant previews it (stored XSS between admins), and imported templates can come from
anywhere.

- **No local file includes.** Reject `<mj-include` on save and on render with a validation error
  (new rule `Rules/MjmlContentRule.php`, also enforcing `<mjml>…</mjml>` and size ≤ 512 KiB).
  The HTTP service already ignores includes; the binary path does not, so the rule closes it for
  every renderer. `mjml-browser` in the canvas cannot read files anyway.
- **Bounded requests.** `CurlApi`: `CURLOPT_CONNECTTIMEOUT 3`, `CURLOPT_TIMEOUT 10`,
  `CURLOPT_FOLLOWLOCATION false`, `CURLOPT_PROTOCOLS` HTTP/HTTPS only; MJML `errors` returned to the
  caller so the admin sees them. The render endpoint rejects bodies over 512 KiB before calling the
  service (the service itself caps at 1 MiB). Throttles per 4.5.
- **Preview iframe.** Rendered HTML is shown only in `<iframe sandbox="">` with `srcdoc`: no
  scripts, opaque origin, no access to the admin's token in localStorage, no forms, no top
  navigation. Never `dangerouslySetInnerHTML`. The GrapesJS canvas iframe is same-origin by design,
  so the editor strips `<script>`, `on*` attributes and `javascript:` URLs from `mj-raw`/`mj-text`
  content before loading it into the canvas (GrapesJS `parser.optionsHtml` with
  `allowScripts: false`, `allowUnsafeAttr: false`, `allowUnsafeAttrValue: false`), and the
  stored text keeps whatever the admin typed in Code mode (e-mail clients strip scripts; we never
  execute it in an admin origin).
- **CSP.** The admin CSP (`front/cli/src/stack/caddy-snippets.ts`, `app_csp_admin`, ADR 0044)
  already allows `'unsafe-inline'`/`'unsafe-eval'` scripts and inline styles; the spike records
  whether GrapesJS needs either (goal: no new CSP relaxation). Remote images in previews load
  under the existing `img-src https:`.
- **Tenant isolation.** Tenants have separate databases and Passport keys (ADR 0007); the new
  endpoints read only the current tenant's settings and variable classes and write nothing. A
  `TenantIsolationTest` (pattern of `compliance/tests/Api/TenantIsolationTest.php`) proves a token
  of another tenant is rejected on both endpoints. The MJML service is stateless and shared; no
  tenant data is cached in it.
- **Variable values** (R7): the editor does not change the existing unescaped substitution;
  M1 adds a test that documents it and the plan proposes escaping as a separate fix.

### 4.8 Accessibility

Of the editor (staff UI):

- Keyboard reachable: tab order across mode tabs, block list, canvas, layer tree, traits; GrapesJS
  drag-and-drop gets a keyboard alternative ("Add block" menu inserting after the selected
  component, and move up/down buttons on the toolbar). Known upstream gaps (tooltip on hover only)
  are covered by visible labels.
- `aria-label` on icon-only buttons through our locale strings; visible focus; the preview iframe
  has a `title`.
- Playwright + axe on the editor page in all three modes (section 7); target zero serious or
  critical violations in our own markup, upstream violations listed with an issue link.

Of the generated e-mails:

- On render, an e-mail accessibility check returns warnings (not blockers) next to MJML errors:
  missing `alt` on `mj-image` (decorative images need `alt=""` explicitly), missing `lang` on
  `<mjml>` (MJML 4 supports `<mjml lang="…">`; default to the template's language, see 4.9), button
  text that is only a URL, heading order inside `mj-text`, text/background contrast below 4.5:1 for
  colours set by attributes. Implemented server-side in
  `templates-email/src/Accessibility/EmailA11yChecker.php` so the CLI and API share it.
- Default templates get `lang` and `dir` attributes and `alt` texts in M5.

### 4.9 i18n

- Editor UI: EN/PL/ZH. GrapesJS core `pl` and `zh` locales are imported from the package; the
  mjml plugin's missing `zh` and all our own strings (panel titles, banners, a11y warnings) go in
  `admin/src/locales/{en-US,pl-PL,zh-CN}/emailEditor.ts`. The editor locale follows the admin
  locale (`getLocale()` from Umi). French gets the upstream `fr` strings and our EN fallback.
- Per-language templates (optional milestone M6, owner decision Q3): today a template has no
  language and default contents are translated once at seed time (`Lang::get` in the variable
  classes). Proposal: nullable `locale` column on `templates`; the listener picks, for the
  event's channel, the default template whose `locale` matches the course language, then the
  tenant default language, then the template with no locale (same order as ADR 0106 for the
  learner UI; users have no own locale today). The editor gets a language selector and "copy from
  EN" action.

### 4.10 Self-hosting and offline

- All editor code, CSS, fonts and icons come from npm into the admin build. No CDN, no remote
  config, no telemetry. GrapesJS is used as open-source core only; its proprietary "Studio SDK"
  and any licence-key products are not used.
- The default `blockManager` blocks and the asset manager get no remote URLs; the asset manager
  uploads images through the existing admin file upload and inserts public URLs of the tenant
  storage (needed because e-mail clients fetch images remotely).
- The canvas renders MJML in the browser (`mjml-browser`), so editing works even when the MJML
  service is down; saving still needs the service for the authoritative HTML, as today.
- Air-gapped check: the M3 Playwright run blocks all non-localhost requests and fails on any
  attempt.

## 5. New dependencies with justification

| Package | Where | Licence | Maintenance | Size | Self-hosting impact |
|---|---|---|---|---|---|
| `grapesjs` 0.23.x (pinned) | admin, runtime, lazy chunk | BSD-3-Clause | active, release 2026-10-15 | npm unpacked 12.5 MB; shipped chunk to be measured in M0 (`npm run analyze`); budget ≤ 350 KB gzip for the editor chunk | none: bundled; transitive deps `backbone`, `underscore`, `codemirror@5`, `backbone-undo`, `html-entities`, `promise-polyfill`, `codemirror-formatting` (licences listed by `license-checker` in M0) |
| `grapesjs-mjml` 1.0.x (pinned) | admin, runtime, lazy chunk | BSD-3-Clause | slower (yearly releases); fallback is to vendor it as a package under `admin/src/lib/grapesjs-mjml` (as done for `src/lib/markdown-editor`) | npm unpacked 4.1 MB incl. `mjml-browser` | none; pulls `mjml-browser ^4.18` (MIT) → `html-minifier-terser` (MIT, pulls `terser` BSD-2-Clause, `clean-css` MIT), `buffer`, `process`, `path-browserify` (MIT) |
| `@axe-core/playwright` 4.13.0 | admin, devDependency | MPL-2.0 (unchanged files, dev only) | active | dev only | none; same version as `front/web` |

BSD-3-Clause requires keeping the copyright notice: add both packages to the admin third-party
notices (`admin/LICENSE` companion or the generated notices file used by the docs site, decided
in M3). If M0 chooses EmailBuilder.js instead, this table is replaced (MUI and its deps would be
the main cost) and the plan comes back for approval.

Optional, server: bump `deploy/stack/mjml/package.json` from `mjml` 4.17.1 to the latest 4.x
(4.18+) so the canvas (`mjml-browser ^4.18`) and the authoritative renderer use the same minor
line (Q4).

## 6. Migrations

None for M0–M5. M6 (per-language templates, only if Q3 = yes): one migration in
`api/packages/templates/database/migrations/2026_10_xx_000001_add_locale_to_templates_table.php`
adding nullable `locale` (string 10, indexed); existing rows stay `NULL` and keep working as the
fallback. Tenant migrations run through the normal `ulams:tenant` migration step (ADR 0007).

## 7. Tests

- **Unit (PHP)** `templates-email/tests/Unit/`: `MjmlContentRuleTest` (accepts valid MJML, rejects
  `mj-include` in any case/spacing, rejects > 512 KiB), `CurlApiTest` (timeout and no-redirect
  options set; MJML errors surfaced), `EmailA11yCheckerTest` (one case per warning).
- **Feature/API (PHP)** `templates-email/tests/Api/EmailTemplatePreviewTest.php`: render returns
  HTML with mocked values for a registered event and for a compliance event; missing required
  variables reported; nothing stored; test-send goes only to the caller (`Mail::fake`), ignores any
  recipient in the body; permission denied for a user without `template_update`; throttling;
  `sample_overrides` limited to the event's variables.
- **Tenant isolation** `templates-email/tests/Api/TenantIsolationTest.php`: a token of another
  tenant is rejected on both new endpoints.
- **Render round trip** (admin, Jest, `src/components/EmailTemplateEditor/roundTrip.test.ts`):
  fixtures in `api/packages/templates-email/tests/fixtures/mjml/` (all default contents dumped by a
  small test helper, plus hand-written ones with `mj-head`, `mj-attributes`, `mj-class`,
  `mj-style`, `mj-raw`, comments, `${Var}` tokens, global-settings partials): representable
  fixtures survive import → export unchanged after normalisation; non-representable ones are
  flagged with the right reasons. Admin Jest is broken at baseline (TODO note under Phase 6
  pdfme); if still broken, these run as a Playwright test in a page that loads the module.
- **Server render parity** (PHP, `tests/Feature/RoundTripRenderTest.php`, needs the MJML service,
  skipped when `MJML_API_URL` is unset): for each fixture, HTML rendered from the original MJML and
  from the editor-exported MJML (exported files committed by the Jest step) is equal after
  whitespace normalisation.
- **Playwright** (`admin/src/e2e/emailTemplateEditor.e2e.spec.ts`): open an e-mail template, add a
  text block and a button in Visual mode, insert a variable chip, see it in Code mode as
  `@VarName`, preview shows the sample value, save, reload, content equal; open the hand-written
  fixture → lands in Code mode with the banner; keyboard-only path adds a block; axe scan in each
  mode; network guard fails on any non-localhost request; locale switch to PL and ZH shows
  translated panel labels.
- Existing suites stay green: `templates`, `templates-email`, `compliance` PHPUnit, admin lint/tsc.

## 8. Milestones

Each milestone is one PR (or a few small commits), demoable on its own.

**M0. Spike: GrapesJS-MJML vs EmailBuilder.js (time-box 2 days), decision.**
Throwaway branch `phase-5/email-editor-spike`, never merged; output is a short report appended to
this plan plus an ADR draft (`docs/decisions/0107-visual-email-editor-on-mjml.md`, or the next free
number, status Proposed). Three real templates:
1. `ResetPasswordVariables` default (text, button, `@VarActionLink` in `href`);
2. compliance `TrainingDueSoonVariables` default (required `@VarCourseTitle`, dates);
3. a hand-written branded template (fixture) with `mj-head`/`mj-attributes`/`mj-style`, `mj-raw`,
   a comment, `${VarUserName}`, and a `@GlobalSettingsMail…` footer partial.
Measured per candidate: round-trip fidelity (diff of exported MJML and of server-rendered HTML),
variable chip feasibility, keyboard path and axe count, editor chunk size (gzip), CSP needs, PL/ZH
coverage, full transitive licence list, compatibility with MJML 4.17 server output, effort to
embed in antd. Decision rule: GrapesJS unless template 3 loses markup that the 4.6 check cannot
detect, or the chunk exceeds the budget by > 50 %. **Stop for owner approval of the ADR.**

**M1. Server: draft render, test send, hardening.** Endpoints of 4.5, `MjmlContentRule`,
`CurlApi` bounds, MJML errors surfaced on save, a11y checker (warnings only), tests incl. tenant
isolation. Demo: `ulams` CLI or curl renders a draft and returns warnings; test e-mail arrives in
MailHog.

**M2. Code view + live preview (no new dependency).** `EmailTemplateEditor` with Code and Preview
modes, variables panel, sandboxed iframe preview of unsaved changes, test-send button; removes the
link to mjml.io. Demo: edit MJML, see the e-mail update next to it, send a test.

**M3. Visual mode.** Lazy-loaded editor from the M0 decision, load/save MJML, representability
check and fallback banner, convert-with-diff, feature setting, offline network guard, licence
notices. Demo: build a template from blocks; open the branded fixture and see the fallback.

**M4. Variables and partials in Visual mode, images.** Variable chips, URL trait picker,
locked global partials, asset manager on tenant storage, required-variable live list.

**M5. Accessibility and i18n.** Keyboard alternatives for drag-and-drop, axe in all modes,
EN/PL/ZH strings (`zh` for the plugin), `lang`/`alt` in default contents, a11y warnings shown in
the editor. Docs: `admin/templates.mdx`, `operators/email.mdx` (fix the image drift),
`templates-email/ADMIN.md`.

**M6 (optional, Q3). Per-language templates.** Migration, selection order, language selector,
seeded PL/ZH defaults, tests.

## 9. Risks

- **R1 grapesjs-mjml cadence.** Yearly releases and MJML 4 only. Mitigation: pin versions; if it
  stalls, vendor it under `admin/src/lib/` (BSD-3 allows it). The stored format is plain MJML, so
  switching editors later loses nothing.
- **R2 MJML 5.** Upstream MJML is at 5.4.1 (6.0 alpha out); the plugin targets 4.18. Staying on the
  4.x line for both canvas and service is consistent; an MJML 5 upgrade is a separate decision that
  must re-run the round-trip suite.
- **R3 Canvas vs server render drift.** Canvas uses `mjml-browser`, the e-mail uses the service.
  Mitigation: the Preview tab always shows the server HTML; align minor versions (Q4).
- **R4 Silent markup loss.** Mitigated by 4.6 and the round-trip tests; the check errs on the side
  of Code mode.
- **R5 Bundle size and admin performance.** Lazy chunk, budget in M0.
- **R6 Accessibility of the upstream editor** may not reach WCAG 2.2 AA (drag-and-drop, hover-only
  tooltips). Staff UI, so not the learner non-negotiable, but we document gaps and keep Code mode
  as a fully accessible path.
- **R7 Unescaped variable values in e-mail HTML (pre-existing).** A learner named
  `<a href=…>` lands as markup in e-mails sent to admins. Out of scope for the editor, proposed as
  a separate `(new)` fix (escape in `generateContentUsingVariables` for the e-mail channel except
  variables declared as HTML, e.g. global markdown settings).
- **R8 Admin Jest broken at baseline** delays the round-trip unit tests; Playwright fallback noted.

## 10. Open questions (recommended default in bold)

- **Q1** Editor library after the spike: **GrapesJS + grapesjs-mjml**, EmailBuilder.js documented
  as the alternative in the ADR.
- **Q2** Who may use the visual editor: **everyone with `template_update`**; no new permission.
- **Q3** Per-language templates (M6, one migration): **yes, but after M5**, selection by course
  language then tenant default (ADR 0106 order).
- **Q4** MJML version alignment: **move every compose file and the docs example to the native
  `deploy/stack/mjml` image and bump it to the latest 4.x** in M1; MJML 5 later, separately.
- **Q5** Test send recipients: **only the signed-in admin's own address**; no free-text
  recipient.
- **Q6** Non-representable templates: **open in Code mode with an explicit convert-with-diff
  action**, never automatic conversion.
- **Q7** Feature switch default: **visual editor on by default**, operator can turn it off with
  `templates_email.visual_editor`.
- **Q8** Variable escaping (R7): **fix in a separate small PR before M3**, since the editor makes
  templates easier to change.
