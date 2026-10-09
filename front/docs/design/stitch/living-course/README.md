# Living Course designs (Stitch, Phase 3)

Screens for `docs/plans/phase-3.md` section 11.3, from the Stitch project **"ulams Course Builder"**
(`4264779900987202361`), design system "Editorial Intelligence"
(`assets/f711dc67c76f4846a76c762f05e0ff6e`, described in [`../course-builder/README.md`](../course-builder/README.md)).
Platform-brand studio screens, except `05-learner-notices`, which is a learner page and must be
rendered in the tenant theme when implemented.

Each exported folder holds `code.html` (reference only: Tailwind from a CDN, not part of the app) and
`screen.webp` (1440 px wide, `cwebp -q 72 -m 6`). The prompts used are in [`PROMPTS.md`](PROMPTS.md).

| Folder | Plan screen | Route | Status |
|---|---|---|---|
| `01-sources` | Sources and sync settings | `/studio/s/{id}/sources` | not exported |
| `02-proposal-review` | Update proposal review | `/studio/s/{id}/updates/{p}` | not exported |
| `03-workspace-staleness` | Workspace with staleness signals | `/studio/s/{id}/workspace` | not exported |
| `04-audit-trail` | Audit trail | `/studio/s/{id}/audit` | not exported |
| `05-learner-notices` | Learner lesson with update and re-attempt notices | `/learn/:course/:topic` | not exported |

## Export status (2026-10-09)

All five prompts in `PROMPTS.md` were sent to `generate_screen_from_text` (desktop, design system
above). Every call timed out on the client side, and over the following ~15 minutes neither
`list_screens` nor the project's screen instances showed new screens. A second, shorter request for
the proposal review on the lighter model answered that it had "reviewed the Living Course: Update
proposal review screen", but no such screen was listed either. This matches the Phase 1 export issue
described in the course-builder README.

To finish: open the project in Stitch, check whether the screens titled "ulams Course Builder —
Living Course: …" and "ulams learner lesson page — Living Course notices for learners" exist; export
each into the folder above (`code.html`, `screen.webp`) and record its screen ID in the table.
Otherwise regenerate them from `PROMPTS.md`. Implementation does not depend on the exports: the plan
defines the content, states and components of every screen.

## Notes for implementation

- Use `@ulams/ui` components on `--ulams-*` variables (ADR 0004, ADR 0008); the new components are
  listed in the plan (11.3).
- Status is never shown by colour alone: every pill, tree marker and diff has an icon or text
  (`Changed`, `Removed`, `+`/`−`).
- Copy follows the plan; the mock numbers in prompts (costs, learner counts, hashes) are examples,
  and the UI only shows values the API returns. Model names never appear in the UI.
- Amber stays reserved for citations; staleness uses the neutral/amber banner with an icon and text.
