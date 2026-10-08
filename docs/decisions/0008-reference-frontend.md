# 0008. Reference frontend: Astro SSR, framework-free SDK, schema-described UI catalogue

- Status: Proposed
- Date: 2026-10-08

## Context and problem statement

The three demo academies (coffee, oncall, nightsky) ran on the old EscolaLMS React front
(`front/src`): a client-rendered single-page app that downloads several hundred KB of JS, waits for
settings and courses before it paints anything, and keeps state in React context. The demos were slow,
janky and did not sell the product. The roadmap also needs:

- server-rendered course sales pages (SSR/SEO, Phase 5.1);
- a TypeScript SDK from the OpenAPI spec that is not tied to a framework (5.2, 7.3);
- web components and a UI catalogue that agents fill in (generative UI, 2.7: A2UI-shaped documents,
  schema-validated props, fallbacks);
- themes as CSS custom properties (ADR 0004).

## Decision

A new reference frontend in `front/web` (`@ulams/web`), with two new workspaces next to it:

| Workspace | Package | What it is |
|---|---|---|
| `front/web` | `@ulams/web` | Astro 5 app, SSR (`output: "server"`, Node adapter), TypeScript strict |
| `front/sdk` | `@ulams/sdk` | Plain TypeScript API client on `fetch`; Node and browser |
| `front/ui` | `@ulams/ui` | UI catalogue: Astro components + web components, one JSON-schema registry |

**Rendering.** Pages are HTML rendered on the server with zero JS by default. Interactivity comes from
small vanilla web components (`<ulams-quiz>`, `<ulams-h5p>`, `<ulams-video>`, `<ulams-progress>`),
bundled only on the pages that use them. No React, no context, no CSS-in-JS.

**Navigation.** Prefetch on hover (Astro prefetch) and prerender in Chromium (speculation rules via
`experimental.clientPrerender`); page transitions are the browser's native cross-document View
Transitions (`@view-transition { navigation: auto }`), with Astro's `view-transition-name` on shared
elements. We do not ship Astro's `<ClientRouter />`: the native transitions cost no JS, keep each page a
real document (simpler islands, no re-init bugs) and degrade to an instant navigation in browsers
without support. This is the one deviation from "Astro View Transitions" as first written.

**Tenancy.** The tenant comes from the `Host` header with the existing rules
(`front/src/lib/tenant/resolveApiUrl.ts`, re-exported by the SDK): `coffee.app.localhost` → API
`http://coffee.localhost`. The server calls the tenant API directly (through Caddy on the host).
Public data (settings, courses, course detail, tutors, events, products) is cached in memory per
tenant, stale-while-revalidate (fresh 45 s, served stale up to 30 min while refreshing), and warmed
when the server starts, so first paint never waits on PHP.

**Platform host.** Hosts in `ULAMS_PLATFORM_HOSTS` (`app.localhost`, `localhost`) are not tenants:
they serve the ulams product landing (a catalogue document in the neutral `platform` theme) with the
demo academies. Tenant fronts and admins are linked by host rules, so the same build serves the
platform and every tenant.

**Tenant look.** The theme preset and the accent colour come from the tenant's API settings
(`theme.theme`, `theme.accent`) and are rendered as CSS variables on the server; the accent is
darkened or lightened as needed to keep WCAG AA contrast for text.

**Auth.** The session token lives in an httpOnly, SameSite=Lax cookie set by the server. Browser
islands call the API through a small backend-for-frontend (`/bff/api/…`) with an allow-list of
learner calls and an Origin check on writes; scripts never see the token. Demo mode: the server calls
`POST /api/demo/login {role: "student"}` and falls back to a password login with
`DEMO_STUDENT_EMAIL` / `DEMO_STUDENT_PASSWORD` from the server environment (never in front code). Every
visitor of a demo tenant is the same seeded student, so one token per tenant is cached and shared;
a 401 (hourly reset) triggers one re-login.

**UI catalogue for agents.** `front/ui/src/registry.ts` lists every component with a description for
the model, a JSON Schema for its props (flat, explicit enums, defaults, safe-link format) and a
plain-text fallback. A page is a document `{component, props, children}` with optional
`{"$data": "/json/pointer"}` bindings into a data model built from the API. One `<Render doc data />`
validates, applies defaults and renders; unknown components and invalid props become text and never
break the page. The three landings are such documents (`front/web/src/docs/<tenant>.json`): the shape
the Course Builder will generate. The course page and the lesson player build their documents in code
from API data with the same catalogue.

This is the *tree* form of an A2UI v0.9 surface. A2UI streams a flat component list with ids and a
separate data model; converting our tree to and from that list is mechanical (the `id` field is
already supported) and belongs to the AG-UI transport (ADR 0011), which validates model output
against this same catalogue (`yarn workspace @ulams/ui catalogue` prints it as JSON). No CopilotKit renderer: the
reference frontend renders its own catalogue, which keeps self-hosting free of extra runtime services.

**SDK types.** Request paths are typed by `openapi-typescript` output from the l5-swagger spec
(`api/storage/api-docs/api-docs.json`). The spec documents almost no response bodies, so the response
types the SDK uses are hand-written (marked TODO until the spec is complete, 7.3).

## New dependencies

| Package | Licence | Why | Size / runtime impact |
|---|---|---|---|
| `astro` 5.18, `@astrojs/node` 9.5 | MIT | SSR framework and Node adapter; actively maintained | server only; ~1 KB gz client prefetch script |
| `sharp` 0.34 | Apache-2.0 | Astro image service (WebP, responsive widths) | server only (native binary) |
| `marked` 18 | MIT | Markdown for course content, raw HTML escaped | server only, ~40 KB |
| `katex` 0.16 | MIT | Math rendered to MathML on the server | server only; no client CSS/fonts |
| `hls.js` 1.7 (light build) | Apache-2.0 | HLS video in Chromium/Firefox | lazy, loaded on first play only (118 KB gz) |
| `openapi-typescript` 7.13 (dev) | MIT | Types from the OpenAPI spec | dev only |
| `@astrojs/check` 0.9, `@emnapi/runtime` (dev) | MIT | `astro check`; the runtime is a missing peer of its WASM parser | dev only |
| `vitest` 3.2, `@playwright/test` 1.48, `eslint`, `typescript-eslint`, `typescript` 5.9 (dev) | MIT / Apache-2.0 | tests, lint, typecheck; versions already in the lockfile where possible | dev only |

Fonts are fetched from Google once at build time by Astro's font API and self-hosted (no request to
Google at runtime), with `font-display: swap`. The fallback faces (`size-adjust`, ascent and descent
overrides on Arial, Times New Roman and Courier New) are measured from the font files and kept in
`front/ui/src/styles/fallbacks.css`: Astro's generated fallbacks were wrong for these fonts (for
example 170 % `size-adjust` for Space Grotesk), which caused visible reflow. `@axe-core/playwright`
4.10 (MPL-2.0, dev only, same axe-core 4.10 line already in the lockfile) runs the WCAG scan in e2e. Astro 7 exists; we stay on the 5.x line as
decided and upgrade in a separate step.

## Consequences

- Good: landing and course pages ship about 3 KB of JS (gzip) and paint in about 0.1 s locally from the
  production build (numbers in `docs/plans/phase-5-reference-frontend.md`).
- Good: the SDK, the catalogue and the theme tokens are framework-free and usable by agents, web
  components and the future Course Builder.
- Good: H5P stays an isolated GPL service (ADR 0003): the frontend frames it through a same-origin
  proxy and never bundles it.
- Bad: two frontends until the reference app reaches parity with `front/src` (cart and checkout,
  account area, webinars, consultations); the old front stays for those.
- Bad: one in-memory cache per process; several instances need a shared cache or a CDN in front.
- Bad: `Render` imports every catalogue component, so every rendered page inlines the whole catalogue
  CSS (about 20 KB gzip in the HTML). Acceptable now; split per used component when the catalogue grows.
