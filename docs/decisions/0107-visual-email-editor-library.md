# 0107. Visual e-mail editor: GrapesJS with grapesjs-mjml on top of MJML

- Status: Accepted
- Date: 2026-10-21
- Plan: `docs/plans/email-visual-editor.md` (milestone M0), results in `docs/plans/email-visual-editor-spike.md`

## Context and problem statement

Tenant admins edit e-mail templates as raw MJML in a textarea and preview them on an external site. The plan
adds a visual editor while MJML stays the stored format and the renderer (owner decision: hand-written MJML must
keep working and is never silently rewritten). Commercial embeddable editors are out because of self-hosting and
air-gapped installs. Two open-source candidates were measured in a spike on three real templates plus a stress
template: GrapesJS with `grapesjs-mjml`, and EmailBuilder.js (Waypoint).

## Considered options

1. GrapesJS 0.23.8 + grapesjs-mjml 1.0.8 (both BSD-3-Clause), reads and writes MJML.
2. EmailBuilder.js (MIT), own JSON document rendered to HTML, no MJML in or out.
3. Do not add a visual editor; ship only code view with live preview (plan milestones M1 and M2).

## Decision

Option 1, with conditions. Option 3 stays the fallback and ships first (M1, M2) regardless.

- Visual mode is lazy loaded and only offered when the representability check of plan 4.6 passes. The check
  normalises self-closing tags first and ignores the `<tbody>` and trailing `;` the browser parser adds in
  `mj-raw`; anything else that differs (for example `mj-table`) opens the template in Code mode.
- GrapesJS runs with `cssIcons: ''` (no CDN), `i18n.detectLocale: false`, `storageManager: false`.
- Keyboard alternatives for adding and moving blocks are part of M5, not optional.
- Install workaround for the broken `@types/mjml-core ~4.18` range (`overrides`/`resolutions`), or vendoring
  the plugin under `admin/src/lib/grapesjs-mjml` if upstream does not fix it.
- The plan's size budget for the editor chunk (350 KB gzip, gate 525 KB) is replaced by **700 KB gzip**, because
  the measured chunk is about 660 KB (half of it `mjml-browser`) and the alternative is larger (767 KB) and
  drops MJML. The owner accepted this budget on 2026-10-21 (PR #328).
- Order of delivery, accepted with the budget: the code-only editor ships first (M1 server safety, M2 code view
  and live preview), then visual mode (M3 to M5), with keyboard alternatives required in M5.

## Consequences

- Good: existing and hand-written MJML keeps working; no new stored format; all losses found in the spike are
  detectable, so nothing is rewritten without a diff and confirmation (plan Q6).
- Good: no new CSP relaxation (the admin CSP already allows inline styles and `eval`), no workers, one
  same-origin canvas iframe; Polish UI is complete, only about 25 Chinese strings are ours to write.
- Good: licences are permissive (MIT, BSD-3-Clause, ISC; no copyleft); BSD-3-Clause notices must be kept for
  `grapesjs` and `grapesjs-mjml`.
- Bad: a heavy lazy chunk (about 660 KB gzip) loaded when an admin opens the visual tab.
- Bad: the editor is not keyboard operable out of the box and has a serious colour-contrast axe rule in its
  style manager; we own the fixes, and Code mode remains the accessible path.
- Bad: `grapesjs-mjml` is slow moving, MJML 4 only, and does not install cleanly today; mitigated by pinning,
  an override, and the option to vendor it. The canvas (`mjml-browser` 4.18.0) and the service (4.17.1) differ by
  one minor until plan Q4 aligns them.
- Bad: the first visual save reformats a template onto one line; the diff-and-confirm step covers it.
- Open: variable chips (plan 4.4) are supported by the GrapesJS API but were not prototyped; M4 may revisit.
