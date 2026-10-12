# 0097. Split the public product landing into Overview and Comparison

- Status: Proposed
- Date: 2026-10-12
- Plan: `docs/plans/phase-5-reference-frontend.md` (Batch 4)

## Context and problem statement

The platform product landing grew to mix product explanation, demonstrations and a dense competitor
matrix. The reviewed Stitch campaign and comparison screens also contained claims and figures that
were not supported by the repository. Search and sharing metadata varied by locale, and the existing
`ULAMS_LANDING_STATUS=final` mode could present roadmap capabilities as already shipped.

## Decision

- **Publish two pages per supported language.** Overview stays at `/`, `/pl/` and `/zh/`. Comparison
  is available at `/comparison/`, `/pl/comparison/` and `/zh/comparison/`. The shared catalogue keeps
  localized content parallel, and the language switcher stays on the same page.
- **Keep product claims traceable.** Use existing product documents, workflow and feature data. Keep
  the sourced comparison dataset and its checked dates and source links. Do not copy unsupported
  Stitch claims, counts, customer names, ratings, offers, certifications or security guarantees.
- **Show actual product status on public pages.** The platform landing always renders the status in
  source data, including labels for planned work. Remove `ULAMS_LANDING_STATUS` from the web runtime
  configuration. Keep the pure status transforms available for tests and draft previews. This
  supersedes the display-mode behavior described in ADR 0096 for public product pages.
- **Make each route independently indexable.** Render a localized title and description, canonical,
  `hreflang` alternates including `x-default`, Open Graph and X card metadata, and verified JSON-LD.
  Serve `robots.txt` and a sitemap containing only the six canonical page URLs and their alternates on
  the platform host; return 404 on tenant hosts.
- **Use native semantics and contained table scrolling.** Pages keep one descriptive `h1`, landmarks,
  native links and a comparison table with a caption and scoped headers. On narrow screens, a
  keyboard-focusable region contains the table's horizontal overflow.

## Consequences

- Overview leads with the product promise, author review workflow and demos. Comparison holds the
  stakeholder and architecture summaries and the detailed feature matrix.
- Search engines and social previews get a distinct canonical identity for each page and language.
- Roadmap labels cannot be hidden on public product pages through an environment variable.
- The sitemap is deliberately limited to the public product surface; tenant course pages remain
  tenant-owned and outside this index.
- Comparison claims still need periodic review against their cited sources and check dates.

## Alternatives considered

- **Keep the table on Overview and add a separate copy on Comparison.** Rejected: it duplicates a
  dense section and leaves the main page overloaded.
- **Keep the `final` display mode for public marketing.** Rejected: it lets the landing claim that
  planned work is available, weakening product and comparison accuracy.
- **Use generated competitor details from the Stitch screens.** Rejected: the screens contain facts
  that cannot be confirmed in source data.
- **Add an SEO or sitemap dependency.** Rejected: Astro SSR and the existing URL helpers cover the
  small fixed set of canonical platform routes without a runtime dependency.
