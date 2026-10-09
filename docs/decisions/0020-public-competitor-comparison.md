# 0020. Public comparison with other learning platforms: sourced data, neutral values

- Status: Accepted (2026-10-09)
- Date: 2026-10-09

## Context and problem statement

The product owner wants the platform landing (`app.localhost`) to compare ulams with the systems
buyers weigh it against. A comparison table is a public claim about other companies' products: it
must be accurate, checkable and fair, and must not misuse trademarks.

## Decision

- The table compares ulams with Moodle, Canvas LMS (open source), Open edX, TalentLMS (hosted SaaS
  LMS), Thinkific (course-creator platform) and LearnDash (WordPress plugin) on 15 product rows.
- The data lives in `front/web/src/data/comparison.json`; every cell is
  `{value, note, source, checkedAt}`. Competitor cells are researched only from official sources:
  vendor documentation, pricing pages, licence files and official plugin directories.
- Values are neutral: Yes, No, Partial, Via plugin, Paid add-on, Not documented. "No" only when an
  official source confirms the absence; otherwise "Not documented". Licence and pricing are short
  factual phrases. Notes are short and descriptive, no marketing wording about competitors.
- ulams cells are honest: built features say Yes or Partial, roadmap items say Coming (Living Course,
  AI course generation, cited diffs, adaptive paths, generative UI), with sources in our repository.
- The page shows "As of <month year>" and a Sources disclosure listing every URL with its check date.
  A unit test fails when a cell has no https source or date, or when a competitor cell says Coming.
- Plain product names only, no logos; a line says the names are trademarks of their owners.
- Rendered by a catalogue component, `ComparisonTable` (no JavaScript).

## Amended (2026-10-09): enterprise group

The product owner asked for enterprise learning products. The table now has two groups behind a
CSS-only segmented control (radio inputs, no JavaScript), ulams highlighted in both:

- Open source & creator platforms: the six products above, 15 rows.
- Enterprise suites: Articulate 360 (authoring suite with light hosted distribution through Reach),
  Docebo, Cornerstone, SAP SuccessFactors Learning, Absorb LMS and 360Learning, on the same 15 rows
  plus five enterprise rows: data residency, SSO (SAML / OIDC), SCIM provisioning, built-in authoring
  tool and content library / marketplace. "AI authoring" and "open API" reuse the AI course generation
  and Headless API rows.
- The rules above are unchanged: official sources only, neutral values, "No" only when an official
  source states the absence. Where a vendor's help centre could not be read, the cell says Not
  documented. ulams cells for the new rows come from the code and the roadmap (SSO Partial: Google and
  Facebook sign-in only; SCIM Coming). Workday Learning is not included: its documentation is gated.
- Data model: top-level `groups`, rows and systems carry an optional/required `groups` list; the unit
  test checks every row of a system's groups.

## Amended (2026-10-09): developer and headless focus

The product owner asked to compare on what the other products lack: REST API, CLI, MCP, headless
course management. The table is now grouped under section header rows, in this order: Developer &
headless (REST API, headless course management, published OpenAPI spec, typed TypeScript SDK, CLI for
authors and developers, MCP server, webhooks, course-as-code / Git sync, self-hosting, generative UI),
AI, Content standards, Business. Both groups get the same sections; the page intro says ulams is built
for developers and AI agents first.

- The sourcing rules are unchanged. Every new cell was researched from official sources for all twelve
  competitors; where capability exists the table says so (for example Moodle and Open edX have admin
  or operator CLIs and Open edX publishes an OpenAPI spec, Canvas and Open edX have extensive REST APIs,
  LearnDash, Docebo and 360Learning have official MCP servers). "No" only with an official statement;
  otherwise "Not documented". Third-party MCP servers do not count as official: "Via plugin" only for
  a plugin in the vendor's own plugin directory or a platform plugin, with the note saying so.
- "CLI" means a tool for authors and developers to manage content. An operator or admin CLI (ulams
  `php artisan ulams:*`, Moodle admin scripts, Tutor) is "Partial" for everyone, ulams included.
- ulams cells are checked against `main`: REST API, headless course management, OpenAPI and the
  TypeScript SDK are Yes (the SDK is in the repository, not yet on npm; OpenAPI coverage is still being
  completed); CLI is Partial; MCP, webhooks and course-as-code are Coming (roadmap 7.1, 7.3, 7.5).
  The data keeps the true status; any display mode that shows planned items as delivered must not change it.
- Data model: top-level `sections`, and each row names its `section`. The component renders one `tbody`
  per section with a `th scope="rowgroup"` header row.

## Consequences

- Good: every claim can be traced and re-checked; corrections are a data change.
- Bad: the facts age. Re-check the sources before each release or at least quarterly and update
  `checkedAt` and `asOf`.
