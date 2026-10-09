# Personalisation designs (Stitch, Phase 4)

Screens for `docs/plans/phase-4.md` section 11.3. They come from the Stitch project **"ulams Course Builder"**
(`4264779900987202361`), design system "Editorial Intelligence"
(`assets/f711dc67c76f4846a76c762f05e0ff6e`, described in [`../course-builder/README.md`](../course-builder/README.md)).

- Screens 01–03 are learner pages. When implemented, they must render in the tenant theme
  (`--ulams-*`), not in the platform brand.
- Screen 04 is a studio screen and screen 05 is an admin screen. Both use the platform brand.

Each exported folder holds `code.html` and `screen.webp`:

- `code.html` is reference only: it loads Tailwind from a CDN and is not part of the app.
- `screen.webp` is 1440 px wide, encoded with `cwebp -q 72 -m 6`.

The prompts are in [`PROMPTS.md`](PROMPTS.md).

| Folder | Plan screen | Route | Status |
|---|---|---|---|
| `01-learner-lesson-support` | Lesson with personal help and "Why am I seeing this?" | `/learn/:course/:topic` | not exported |
| `02-learner-privacy` | Personalisation and privacy | `/account/personalisation` | not exported |
| `03-learner-tutor` | AI tutor panel | `/learn/:course/:topic` | not exported |
| `04-studio-insights` | Course insights for authors | `/studio/insights/:course` | not exported |
| `05-admin-insights-settings` | Learner Insights tenant settings | admin `/settings/learner-insights` | not exported |

## Export status (2026-10-09)

All five prompts were sent to `generate_screen_from_text` (desktop, design system above):

- Every call timed out on the client side.
- Two later `list_screens` checks showed no new screens.

The Living Course and Phase 1 exports failed the same way.

To finish the exports:

1. Open the project in Stitch.
2. Look for screens titled "ulams Course Builder — Personalisation: …", "ulams learner lesson page — …",
   "ulams learner account page — …" and "ulams admin — Learner Insights settings".
3. Export each one into its folder and record its screen ID in the table.
4. If a screen does not exist, regenerate it from `PROMPTS.md`.

Implementation does not depend on these exports: the plan defines the content, states and components
of every screen.

## Notes for implementation

- Never show status by colour alone. Every status pill and rate bar also carries its number or label
  as text.
- Show the AI label on every AI-generated block a learner sees.
- Show no invented metrics. The copy in the screens is mock data.
