# Visual regression harness

Full-page screenshots of the learner front-end (`front/`) and the admin panel (`admin/`).
Capture them before a refactor, capture them again after it, and compare the two runs pixel by pixel.
It was written for the styled-components → CSS variables migration, but it works for any change to the UI.

It needs no extra dependencies.
It uses the repo's `@playwright/test` (Chromium) and the pixelmatch and pngjs copies that ship inside `playwright-core`.
These are the same comparator and decoder that `toHaveScreenshot` uses.

## Prerequisites

- The API stack is running (`yarn dev:api`).
  The platform is at `http://api.localhost`, and the tenants are at `http://<slug>.localhost`.
- The dev servers are running (`corepack yarn dev` from the repo root):
  - front on `:3000`
  - admin on `:8000`
  - Caddy serving `http://<slug>.app.localhost` (front) and `http://<slug>.admin.localhost` (admin)
- Playwright's Chromium is installed once with `node_modules/.bin/playwright install chromium`.

## Usage (from the repo root)

```bash
# 1. One-off data setup. It is idempotent and touches only the dev databases.
#    - Enrols each target's student in the course that gets screenshotted.
#    - Creates a markdown static page with the slug "visual-regression" on every target.
node front/tests/visual/visual.mjs prepare

# 2. Before the change
node front/tests/visual/visual.mjs capture --out /tmp/visual/baseline

# 3. After the change
node front/tests/visual/visual.mjs capture --out /tmp/visual/after

# 4. Diff the two runs
node front/tests/visual/visual.mjs compare --base /tmp/visual/baseline --after /tmp/visual/after \
  --report /tmp/visual/report --threshold 0.5
```

Keep the output outside the repo.

`compare` does the following:
- It writes `report.html` (side by side: base / after / diff), `report.md` and `report.json`.
- It saves diff PNGs under `<report>/diff/`.
- It prints the screenshots whose share of differing pixels is above `--threshold` (percent, default `0.5`), or whose size changed.
- It exits with code 1 when any screenshot is above the threshold.
- When the two images differ in size, it pads the smaller one with magenta. A page that grew or shrank therefore always shows up.

### Options for `capture`

| Option | Default | Meaning |
|---|---|---|
| `--out <dir>` | required | Output directory. Files are written as `<target>/<role>/<page>@<viewport>.png`, plus a `manifest.json`. |
| `--targets a,b` | all | Which targets to capture: `front-platform`, `front-coffee`, `front-oncall`, `admin-platform` |
| `--only <regex>` | all | Capture only the ids that match `target/role/page`, e.g. `--only 'admin.*program'` |
| `--viewports` | `desktop,mobile` | `desktop` is 1440×900. `mobile` is 390×844 with touch. |
| `--locale` | `pl-PL` | Browser locale. Keep it the same across runs. |
| `--fixed-time <iso>` | off | Freezes `Date.now()` in the page. Also settable with the `VISUAL_FIXED_TIME` env var. |

### Environment

| Variable | Default |
|---|---|
| `VISUAL_FRONT_URL` | `http://localhost:3000` (platform front) |
| `VISUAL_ADMIN_URL` | `http://localhost:8000` (platform admin) |
| `VISUAL_PLATFORM_API` | `http://api.localhost` |
| `VISUAL_TENANTS` | `coffee,oncall`. Tenant fronts are `http://<slug>.app.localhost`. |
| `VISUAL_ADMIN_EMAIL` / `VISUAL_ADMIN_PASSWORD` | `admin@ulams.app` / `secret` |
| `VISUAL_PLATFORM_STUDENT_EMAIL` / `_PASSWORD` | `sam.okafor@demo.ulams.app` / demo password |
| `TENANT_DEMO_PASSWORD` | Read from `api/.env.example` when unset. It is never printed. |

## What gets captured

Pages are defined in `pages.mjs`. Ids are resolved from the API at run time:
- the course is the first non-E2E course;
- the GIFT quiz and rich-text topics come from that course's program;
- the static page is `visual-regression`.

Front targets are `front-platform`, `front-coffee` and `front-oncall`. Pages are captured as a guest (G) and/or as a logged-in student (S):

- home (G, S)
- `/courses` (G, S)
- course detail (G, S)
- lesson player `/course/:id` (S)
- login (G)
- register (G)
- `/user/my-profile`, which also holds the "my courses" tabs (S)
- my-certificates (S)
- my-orders (S)
- my-data (S)
- cart (S)
- webinars (G, S)
- consultations (G, S)
- the `visual-regression` static page (G)
- 404 (G)
- `/events` and `/tutors` (G). These routes are commented out in `front/src/components/Routes/index.tsx`, so today both render the 404 / static-page fallback. They stay in the list so that re-enabling them shows up as a diff.

The admin target is `admin-platform`:

- login (guest)
- welcome dashboard
- courses list
- course edit: the attributes tab, which has the markdown editor
- course edit: the program tab
- program → rich-text topic (markdown editor)
- program → GIFT quiz topic (GIFT question editor)
- users list
- settings
- H5P list
- static pages list
- page editor (markdown editor)
- new page

## Determinism

The harness takes these steps so that two runs of an unchanged app produce the same pixels:
- **Fixed browser settings.** Fixed viewports, `deviceScaleFactor: 1`, UTC timezone, a fixed locale, light colour scheme and `reducedMotion: reduce`.
- **No motion.** An init script injects CSS that zeroes every animation and transition, hides scrollbars and carets, and hides embedded video. Screenshots are taken with `animations: 'disabled'`.
- **Seeded randomness.** `Math.random` is replaced with a seeded generator.
- **Waiting for a settled page.** Before each shot the harness waits for:
  - the network to go idle;
  - skeletons and spinners to disappear;
  - a scroll through the page, so that lazy images and intersection-observer content load;
  - `document.fonts.ready` and all `<img>` elements to finish loading;
  - a short final delay.
- **Volatile regions are masked.** They are painted `#FF00FF` in every run, so they never count as diff. Masks are lists of selectors at config, target or page level (`mask: [...]`). Use `hide: [...]` to make an element invisible without moving the layout.
- **Separate logins.** Each role logs in once per target through the real login form. The storage state is then reused for every page.

### Noise

Run `capture` twice against the same code and `compare` the two runs to measure the noise floor.

The first measurement was taken on 2026-10-08: 158 screenshots, two back-to-back runs.
- 157 screenshots were pixel-identical.
- One screenshot differed by 0.095%: `front-coffee/student/course-detail@mobile`, where text anti-aliasing in the program sidebar varied.
- The mean diff was 0.001%.

The default `--threshold 0.5` therefore leaves a 5× margin over the noise.

What noise remains comes from data, not from rendering:
- the API being re-seeded between runs;
- new notifications (the admin header badge is masked);
- dates relative to "today", such as the admin dashboard chart axis;
- the demo seeder still running.

Run `prepare` before both captures and avoid re-seeding between them.

## Adding a page

Add an entry to `frontPages()` or `adminPages()` in `pages.mjs`:

```js
{ name: 'my-page', path: '/#/my/path', roles: ['guest', 'student'], mask: ['.clock'], waitFor: '.loaded', extraWaitMs: 500 }
```

`path` can be a function `(target) => string`. It reads the ids that were resolved into `target.ids`.
