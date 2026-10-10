# 0093. A public showcase endpoint and hero interactives on the demo landings

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/interactive-demos.md` (M7)

## Context and problem statement

The three new demo landings (gravity, poland, ulam) should open with the course's own interactive
package as the hero visual, not a picture of it. The landing is public: a visitor has no account and
no token, and `POST /api/interactive/launches/{topic}` needs a logged-in learner with access to the
course. The landing also must not create progress for anybody, and it must work before the
interactive topics exist (the M7 courses are placeholders; the real content arrives in M8 and M9).

## Decision

- **A read-only public endpoint**, `GET /api/interactive/showcase`, throttled (60 requests a minute).
  It returns the same `data` shape as a launch (`url`, `version`, `manifest`, `topic`) for the first
  active Interactive topic (lesson order, then topic order) of the first published course that is
  `public`. It creates no progress and sends no token. It answers 404 when there is no such topic or the
  type is switched off for the tenant, and 503 when the tenant has no content origin.
- **The reference frontend asks for it with a short cache and never fails the landing.** A 404 or an
  error means "no showcase". The landing documents bind the hero's `showcase` prop to `/showcase`.
- **The hero variants `cosmos`, `atlas` and `notebook`** show the interactive inside the hero when a
  showcase exists (a sandboxed frame, a poster and a text version, as for any Interactive topic) and a
  drawn, decorative scene otherwise. The sandbox is the same as for any Interactive topic. (The first
  version reused the `InteractiveLesson` player in the hero; see the amendment below for what replaced
  it.)
- **Nothing private leaves the tenant.** The endpoint reads only the tenant's own database, lists
  only public courses, and exposes only what a launch exposes (the manifest and the content-origin
  URL, which is public by design). A cross-tenant isolation test covers it.

## Consequences

- Good: the landing's hero is the real product, with the same safety properties as the lesson, and the
  landings need no change when M8 and M9 add the interactive topics.
- Good: a tenant with no interactive topic is unaffected (404, drawn scene).
- Bad: one more public endpoint to keep under the throttle and the policy tests. It exposes the
  manifest texts of a public course's first interactive topic, which are already visible to anyone who
  enrols in a free public course.

## Alternatives considered

- **Hard-code the package URL in the landing JSON.** Rejected: the content origin and the package key
  differ per tenant and per version.
- **Reuse the launch endpoint with a guest token.** Rejected: it would create progress rows for a
  shared guest and blur the "no tracking" rule.

## Amendment (2026-10-10): the hero is a self-running showcase, not the lesson player

Playing the lesson player in the hero showed a tool, not a picture: the Ulam spiral came with its
"Step 1 of 4" card, Back and Next, the "N" slider and the checkboxes, and gravity with its whole tour
panel. The hero is now a decorative loop. Three things change, all additive, so every existing
package and host keeps working (`bridge` stays 1).

- **Protocol: `init.showcase` (optional boolean).** It asks the package for a showcase view: no step
  text, no controls, nothing focusable, slow self-running motion, and the page drives the steps with
  `goToStep`. A package that ignores it still runs (with `chrome: none`). The guard and both copies
  of `init.json` accept it.
- **Manifest: `showcase` (optional object).** `steps` (1 to 12 step ids) is the loop; `poster` is the
  still shown first and kept under reduced motion. The API validates that the ids are steps and the
  poster is in the archive, and the showcase endpoint returns both (the poster as an absolute URL).
  A package without it loops its first four steps with the poster of the first.
- **Frontend: a separate `HeroShowcase` component and `<ulams-showcase>` element** replace the
  lesson player in the hero. It paints the still at a fixed aspect ratio (no layout shift) and starts
  the frame only after `load` and an idle moment, so the hero never blocks the LCP; it runs only while
  on screen and while the tab is visible; the picture is `aria-hidden` with a text alternative; the
  frame is `inert` and cannot take focus. Under reduced motion the frame never starts and the still
  stays. The only things to reach are a small "Try it" link to the lesson (`course.previewHref`) and a
  button that stops the motion (WCAG 2.2.2). A package that fails or never answers leaves the still,
  with no error shown.

The three demo packages declare their loops: gravity (`solar-system`, `venus-rose`, `sun-moving`,
`resonance`: the scene alone, no panel, labels or legends), poland (`solar`, `roads`, `parcels`, `gas`:
the map and its layers, no figures panel) and the Ulam spiral (`diagonals`, `primes`: the spiral winds
out number by number, the diagonal outlined). Each has a `posters/showcase.webp`, rendered by the
package's poster script with `?ulams-poster&ulams-showcase`.

Alternatives considered: a new `display: "showcase"` value (rejected: `display` says where the frame
sits, and a hero is inline), reusing `chrome: none` alone (rejected: the Ulam package has no "none"
look that hides its controls and nothing tells a package to loop), and a loop inside each package
(rejected: the page already owns the steps, so one timer there serves every package).
