# Stitch screens

Exports from the Stitch project "Wellms E-Learning Experience System" (`9081291570384881657`),
fetched on 2026-10-08. Each folder holds the screen's exported HTML (`code.html`) and a full-page
screenshot (`screen.webp`). The prompts behind them are in [`../../experiences.md`](../../experiences.md).

The HTML is reference material only: it loads Tailwind from a CDN and Google-hosted images, and it is
not part of the app. Implementations convert it to CSS Modules on `--ulams-*` variables (ADR 0004).

| Folder | Stitch title | Tenant | Page type | Device | Implemented in |
|---|---|---|---|---|---|
| `coffee-landing` | The Coffee Atlas — Editorial Landing | coffee | Landing | Desktop | `src/pages/landing/coffee` |
| `coffee-course-detail` | The Coffee Atlas — Course Detail & Syllabus | coffee | Course page | Desktop | follow-up |
| `coffee-lesson-player` | The Coffee Atlas — Lesson Player: Ratios & Extraction | coffee | Lesson player | Desktop | follow-up |
| `coffee-exam-project-conferral` | The Coffee Atlas — Examination, Project & Conferral | coffee | Quiz, project, finish | Desktop | follow-up |
| `coffee-css-tokens` | The Coffee Atlas — CSS Design Tokens | coffee | Tokens (`tokens.css`) | — | reference |
| `coffee-tailwind-config` | The Coffee Atlas — Tailwind Configuration | coffee | Tokens (`tailwind.config.js`) | — | reference |
| `oncall-landing` | On-Call — Incident Command for Platform Engineers (Landing) | oncall | Landing | Desktop | `src/pages/landing/oncall` |
| `oncall-cohort-dashboard` | On-Call — Cohort 04 Dashboard & Syllabus | oncall | Course page / dashboard | Desktop | follow-up |
| `oncall-lesson-player` | On-Call — Lesson Player: SLO Math & Error Budgets | oncall | Lesson player | Desktop | follow-up |
| `oncall-exam-project-conferral` | On-Call — Examination, Postmortem Project & Credential Conferral | oncall | Quiz, project, finish | Desktop | follow-up |
| `nightsky-landing` | Night Sky Explorers — Gamified Astronomy Adventure (Landing) | nightsky | Landing | Desktop | `src/pages/landing/nightsky` |
| `nightsky-mission-map` | Night Sky Explorers — Mission Map & Cosmic Quest | nightsky | Course page (mission map) | Mobile | follow-up |
| `nightsky-topic-player` | Night Sky Explorers — Topic Player: Why Does the Moon Change Shape? | nightsky | Lesson player | Mobile | follow-up |
| `nightsky-quiz-project-finish` | Night Sky Explorers — Capstone Quiz, Star Map & Course Finish | nightsky | Quiz, project, finish | Mobile | follow-up |

Every prompt in `experiences.md` (landing, course page, lesson player, quiz/project/finish for each
of the three experiences) has a screen, so no extra screens were generated.

## Notes

- **Screenshots.** The desktop screenshots were rendered locally from `code.html` at 1440 px wide
  (Stitch's own desktop screenshot URLs need a signed-in Google session). The three mobile screenshots
  are Stitch's own (780 px wide, 2x of 390 px). To keep the repository small they are stored as lossy
  WebP (`cwebp -q 72 -m 6`), desktop ones scaled down to 1200 px wide; render `code.html` again for a
  full-resolution reference.
- **Missing HTML.** `nightsky-mission-map` has a screenshot only: its HTML download requires a
  Google sign-in that could not be completed from the export script.
- **Images.** Images that the landings use were downloaded from Stitch (generated, no third-party
  licence) and saved as WebP under `front/public/landing/<tenant>/`. Nothing hotlinks Google-hosted URLs.
- **Fonts.** Coffee: Playfair Display + Plus Jakarta Sans. On-Call: Space Grotesk + Inter + JetBrains
  Mono. Night Sky: Comfortaa + Quicksand. The Night Sky preset still names Baloo 2; the landing loads
  its own fonts.
