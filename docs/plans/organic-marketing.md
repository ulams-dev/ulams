# Plan: organic marketing campaign

Status: **draft, waiting for approval.** Nothing in this plan is published, deployed or posted. Every outward action
is gated on the owner (see "Gates" and "Owner decisions").

Scope: get ulams found and tried by the people it is built for, without paid ads: analytics first, then search,
then launches and communities. Costs: EUR 0 for the baseline; optional paid items are marked.

## 1. Who we are talking to

The landing says "developer and customer education", so the primary buyers are teams that teach a product:

| Audience | What they care about | Our proof |
|---|---|---|
| Developer-education and docs teams | Courses that do not go stale, cited content, an API and CLI, self-hosting | Living Course, cited generation, MCP server, OpenAPI browser, MIT core |
| Customer-education and enablement leads | White-label academies per customer, standards, selling courses, analytics | Tenant per customer, SCORM/cmi5/xAPI/LTI, Stripe/Przelewy24, sales analytics |
| Open-source and self-hosting people | Runs on their hardware, licences, no lock-in | MIT core, GPL parts isolated, Docker Compose, MyDevil and VPS guides |
| AI-agent builders (Claude Code, MCP) | Tools an agent can use safely | `ulams mcp`, dry-run, confirmation tokens, agent audit log |
| Instructional designers and e-learning developers | H5P, SCORM, LiaScript, quizzes, certificates | 54 H5P types, GIFT quizzes, LiaScript, certificates |
| Teachers using the demo topics | The subject itself | Gravity Lab, Poland Measured, The Scottish Book, Coffee Atlas, Night Sky Explorers, On-Call |

One message per audience, one landing. Do not try to say everything everywhere (see section 8).

## 2. Gates (nothing goes out before these)

The project rule is "no publishing until the roadmap is done" (owner, 2026-10-09). Everything below is therefore split
into **prepare (private, can start now)** and **publish (owner opens the gate)**.

| # | Gate | Why |
|---|---|---|
| G1 | Owner approves this plan and opens the publishing gate in writing | Standing rule |
| G2 | Landing runs in `ULAMS_LANDING_STATUS=actual`, or everything it shows has shipped | `final` mode shows roadmap items (AI tutor, risk scoring, Living Course, LTI) as delivered. Posting that to Hacker News is the fastest way to lose credibility |
| G3 | A production domain for the landing and docs (`ulams.app`, docs on a subdomain) | Docs live under `ulams-dev.github.io/ulams/` today, which gives the brand no search equity |
| G4 | Demos hardened and monitored (throwaway data, hourly reset, rate limits, a person on call during launch day) | A launch brings the first real traffic to them |
| G5 | `npm i -g ulams` / container image published (owner decision #77) | "Try the MCP server" must be one command |
| G6 | Licence and data statements reviewed: MIT core, GPL H5P and Adapt services, demo content sources and attributions | Journalists and HN commenters read licences |
| G7 | A privacy page and a cookieless analytics setup (section 3) | EU audience, no consent banner wanted |

## 3. Measurement (do this first)

No analytics exists today (searched `front/`, `admin/`, `api/app`). Without it the campaign cannot be judged.

**Recommendation: cookieless, privacy-first analytics, no consent banner.**

| Option | Notes | Fit |
|---|---|---|
| Cloudflare Web Analytics | Free, cookieless, the zone is already on Cloudflare, one beacon on the static landing | Baseline for the landing |
| Plausible (cloud) or Umami (self-host) | Goals and UTM reports, simple, GDPR-friendly | Recommended for events; Umami self-hosted keeps data ours |
| PostHog | Product analytics and funnels; the Handsontable MCP already connects to it | Later, for in-product funnels, not needed for marketing |
| Google Analytics 4 | Needs consent in the EU, heavier | Not recommended |
| Google Search Console + Bing Webmaster Tools | Search queries, indexing, links | Mandatory, free |

**Events to track** (names are the contract; the landing, docs and demos send the same ones):

| Event | Where |
|---|---|
| `cta_try_demos`, `demo_open_learner`, `demo_open_admin` (+ demo slug) | Landing demo cards |
| `docs_open`, `api_browser_open`, `api_try_request` | Docs, API browser |
| `github_click`, `install_copy` (CLI command copied) | Landing, README, docs |
| `feature_group_open` (+ group id) | Feature index: tells us what people care about |
| `newsletter_signup` | CtaBand newsletter variant (the `mailerlite` package exists) |
| `compare_open` | Comparison table |

**UTM convention** (every link we post is tagged, lowercase):
`utm_source=<site>&utm_medium=<post|comment|listing|email|video>&utm_campaign=<launch-hn|launch-ph|seo|…>&utm_content=<variant>`.
Reddit, HN and Product Hunt strip nothing; LinkedIn keeps them.

**Dashboard:** one page with sessions by source, demo opens, docs visits, GitHub stars (daily snapshot with the GitHub
API), CLI installs (npm downloads once published), newsletter signups, Search Console clicks/impressions/positions,
referring domains. Weekly review (section 10).

**Technical work** (repo, small PRs, tests with each): add the analytics snippet to the static landing build and the
docs site, allow its host in `front/web/src/lib/csp.ts`, send the events through one `track(name, props)` helper, document
it on a docs page, add the privacy page.

## 4. SEO (search engines)

### 4.1 Technical foundations (repo work, prepare now)

| Task | Detail | State |
|---|---|---|
| Submit to Google Search Console and Bing Webmaster Tools | Verify by DNS TXT on the production zone (owner action). Bing also feeds DuckDuckGo, Yahoo and Ecosia | not done |
| `robots.txt` and `sitemap.xml` for the platform landing | The web app has neither (only per-page `noindex`); staging deliberately serves `Disallow: /` and must keep it. Production allows crawling and lists `/`, `/pl/`, `/zh/` | not done |
| Docs sitemap | Starlight already emits `sitemap-index.xml` | done |
| `hreflang`, canonical, Open Graph per language | Already in `platformPage()` for EN/PL/ZH | done |
| Structured data | `ItemList` of the feature groups is in. Add `SoftwareApplication` (name, category, offers free, licence MIT, repo), `Organization`, and `FAQPage` where a real FAQ exists | partial |
| Titles and descriptions | One distinct, 50-60 / 140-160 character pair per page and language | review |
| Core Web Vitals | Landing HTML is ~480 KB on staging: measure with PageSpeed Insights / Lighthouse CI, then trim (lazy sections, image sizes) | measure |
| IndexNow | Ping Bing/Yandex on publish; trivial on the static deploy | not done |
| Docs on a subdomain | `docs.ulams.app` (Cloudflare Pages or GitHub Pages custom domain) instead of the github.io path | gate G3 |
| Social preview and repo metadata | GitHub topics, description, homepage URL, social preview image; README status line still says "Phase 0" | not done |

### 4.2 Content that can rank (long tail, no invented volumes)

Keyword research uses Search Console after two weeks of impressions plus free tools (Google Autocomplete, People Also
Ask, Ahrefs/Semrush free tiers, AlsoAsked). We do not publish volume numbers we have not measured. Starting themes
with the pages that answer them:

| Theme | Page to build |
|---|---|
| "H5P content types" | A page listing the 54 types with a one-line description each (data already in the feature index) |
| "SCORM vs cmi5 vs xAPI" | Explainer with our LRS and player as the worked example |
| "Open source LMS API", "headless LMS" | The API browser plus a "headless LMS" guide |
| "self-hosted LMS Docker / shared hosting / VPS" | The three operator guides, rewritten as tutorials |
| "LTI 1.3 both directions" | How ulams acts as tool and platform |
| "LiaScript LMS", "Adapt Learning SCORM" | Two short guides |
| "Moodle / Canvas / TalentLMS alternative" | Comparison pages from `comparison.json`, factual and dated, no claims we cannot source |
| "MCP server for an LMS", "Claude Code LMS" | MCP guide and a video |
| "xAPI LRS built in" | LRS page |
| "course certificates with QR code" | Certificates page |
| Demo subjects: "how gravity shapes the solar system", "Poland in data", "Scottish Book Ulam" | Each demo gets an indexable summary page with a link into the live course |

Rules: one primary keyword per page, internal links from the feature index to the guide and back, every guide ends with
a "try it" link (demo or CLI), update dates visible, no AI-written filler (the Living Course story is our own proof that
content needs sources).

### 4.3 Links and mentions (earned, not bought)

Directories and lists in section 6, guest posts, conference talks, and being cited in docs of the standards we support
(H5P, LiaScript, Adapt, xAPI/cmi5). No link buying, no comment spam.

## 5. Content engine

One flagship article per month plus short posts. Each piece is written once, published on our site first (canonical),
then adapted for dev.to / Hashnode / LinkedIn with a canonical link back.

| # | Working title | Audience | Hook |
|---|---|---|---|
| 1 | Courses that stay in sync with their sources: how Living Course works | dev/customer education | The differentiator, with a diff screenshot |
| 2 | Every generated sentence cites its source: building a cited course builder | AI builders | Cited generation, "AI proposes, the author approves" |
| 3 | Build a course from the command line with Claude Code and ulams mcp | agent builders | 5-minute demo; the confirmation-token design |
| 4 | Designing an MCP server that is safe to hand an agent | MCP community | Read-only, dry run, confirm tokens, audit log |
| 5 | Video to adaptive HLS with ffmpeg in Laravel (and the temp-folder leak we found) | Laravel devs | The #240 story is a real postmortem |
| 6 | On-demand image resizing API: caching, limits and rate limits | PHP devs | Honest numbers once measured |
| 7 | Running H5P without GPL contamination | OSS/legal-minded devs | ADR 0003 |
| 8 | Multi-tenant Laravel: a database, bucket and key pair per customer | architects | Tenancy package |
| 9 | One LMS, three standards: SCORM, cmi5 and xAPI explained | e-learning | Reference explainer |
| 10 | The H5P content types you can use, with examples | instructional designers | 54-type list |
| 11 | LTI 1.3 in both directions | e-learning devs | |
| 12 | Selling courses: products, subscriptions, vouchers, Stripe and Przelewy24 | course sellers | |
| 13 | Analytics for course teams: what we measure, what is still missing | teachers | Honest list from the feature index |
| 14 | How we evaluate an agent against our MCP server | AI builders | The eval in `front/cli/evals` |
| 15 | Self-hosting an LMS on a shared hosting account | indie/OSS | The MyDevil guide |
| 16 | The "Scottish Book" interactive course: how it was made | math/history | Demo as case study |
| 17 | Gravity Lab: a real-data solar system you learn inside | physics/astronomy | Demo as case study |
| 18 | Poland, Measured: 35 years of public data, cited | data/viz | Demo as case study |
| 19 | Pages as data: why a model never writes page code | frontend/AI | Catalogue and generative UI |
| 20 | What we learned shipping an open LMS from a Wellms fork | OSS | Origin story, credit to EscolaLMS |

Formats beyond text: a 90-second product video, a 5-minute MCP walkthrough, short clips per demo, and a one-page PDF.

## 6. Channels and launches

Rules differ per community and change. **Read each community's current rules the week before posting**; the entries
below are what we expect, not a promise.

### 6.1 Hacker News (Show HN)

- Post as `Show HN: ulams, an open-source LMS whose courses stay in sync with their sources` (draft; plain, no
  superlatives). Link to the live demo or the repo, not a signup page.
- It qualifies because people can try it now: demo logins need no account (student, tutor and admin in one click).
- Author comment within minutes: what it is, why we built it, what is not done (be explicit), tech stack, licence, one
  question for readers. Answer every comment for the first hours, politely, with facts.
- Tue-Thu, European morning to US morning overlap. One submission, no resubmitting, never ask for upvotes or share
  the link in groups to get them (voting rings are against the rules and detected).
- Prepare: the demos on a capacity plan, a "known limitations" page, and answers to the likely first comments (why not
  Moodle/Open edX, GPL and H5P, LLM costs, data privacy, what happens to my content).

### 6.2 Product Hunt

- Schedule at least a week ahead; launch at 00:01 Pacific. Tagline up to 60 characters, a gallery of 4-6 images, a
  short video, a maker comment, a "first comment" with the story and a link to the demos.
- Build a small pre-launch list (newsletter) and tell them the date; ask for honest feedback, not votes.
- Reuse what Hacker News taught us (fix the confusing parts first), so launch it after HN, not before.
- Topics: Open Source, Education, Developer Tools, Artificial Intelligence.

### 6.3 Reddit

Spread over days; one community at a time; disclose that we are the authors; follow the self-promotion ratio rules.

| Community | Angle |
|---|---|
| r/selfhosted | Self-hosting guides, Docker Compose, MIT licence (check whether it needs the "release" flair/day) |
| r/opensource, r/sideproject | Origin and architecture |
| r/laravel, r/PHP | Video/HLS, images, tenancy articles |
| r/elearning, r/instructionaldesign | H5P list, standards, certificates (ask for feedback, do not pitch) |
| r/ClaudeAI, r/mcp | The MCP server and the agent-safety design |
| Subject subreddits for each demo (astronomy, mathematics history, data visualisation) | Share the course as content, with the licence and sources; only where the rules allow it |

### 6.4 Directories, lists and registries (submit once, keep current)

| Type | Targets (verify current criteria and whether they are still maintained) |
|---|---|
| Self-hosting | awesome-selfhosted (PR; needs a documented, FOSS, self-hostable app), selfh.st apps, Docker Hub / GHCR listing |
| Open-source alternatives | AlternativeTo, OpenAlternative, LibHunt, Slant, StackShare |
| Product launch sites | Product Hunt, BetaList, Uneed, Peerlist, DevHunt, Indie Hackers product page |
| Laravel / PHP | Laravel News (submit a link), Packagist (for packages we publish), awesome-laravel, Laracasts forum, Laravel.io |
| MCP and AI agents | Official MCP registry, awesome-mcp-servers lists, Smithery, Glama, other MCP directories, Claude Code plugin/skill listings |
| E-learning directories | eLearning Industry (LMS listing: check cost), Capterra / GetApp / G2 / SourceForge (reviews matter; only genuine ones), Open Source LMS lists, OER / EdTech open lists |
| Code and docs communities | dev.to, Hashnode, Medium publications, DEV and Hashnode newsletters |

### 6.5 LinkedIn and e-learning communities

- **Company page** (ulams) and the **founder profile**: the founder profile carries most reach. Cadence: 2-3 posts per
  week for 12 weeks, native posts (text, carousel PDF, short video), links in the first comment.
- **Groups and communities** (join, contribute first, then share): eLearning Industry, Instructional Design and
  e-Learning professional groups, xAPI/cmi5 (ADL) community, H5P community forum (we are an H5P user: contribute
  answers, mention ulams only when relevant), LiaScript community, Adapt Learning community, Learning Technologies / DevLearn / OEB attendee groups,
  Polish e-learning groups (Polish landing exists), Laravel and PHP user groups.
- **Newsletters and sites**: pitch short guest posts or a "tool" mention to e-learning and developer-education
  newsletters (Learning Guild, eLearning Learning, Training Industry, DevRel and docs newsletters such as Write the Docs).
- **Conferences and meetups**: talks or lightning talks (Write the Docs, Laracon EU/Online, local Laravel meetups, a
  Claude/MCP meetup, e-learning meetups), with the demo as the slide.

### 6.6 Video and social

YouTube (long: MCP walkthrough, Living Course demo; short: one feature per clip), X/Bluesky/Mastodon for developers
(thread per article), Loom links in docs. One recorded demo is reused as the Product Hunt video, the README GIF and
the landing hero.

### 6.7 Own channels

- **Newsletter** (MailerLite, already integrated): one signup block on the landing and docs; a monthly note with the
  changelog and one article.
- **GitHub**: topics (`lms`, `headless-lms`, `laravel`, `h5p`, `scorm`, `xapi`, `mcp`, `elearning`), a real description
  and homepage, a README with a GIF and a 3-step quick start, releases with notes, Discussions on, `good first issue`
  labels, a `CONTRIBUTING` that works, and a public roadmap view of `ROADMAP-TODO`.
- **Docs**: "Try it" links on every page; an RSS feed of the changelog.

## 7. Calendar (12 weeks, starts when the gate opens; weeks before it are prepare-only)

| Week | Prepare (private) | Publish |
|---|---|---|
| -4 to -1 | Analytics and events, privacy page, robots/sitemap, structured data, GitHub metadata, README and demo video, press kit, answer FAQ, 4 articles drafted, accounts created (Search Console, Bing, Product Hunt, LinkedIn page, MailerLite list) | none |
| 1 | Soft launch to 10-20 friendly people for feedback; fix the top issues | Search Console and Bing verified, sitemaps submitted, directories batch 1 (AlternativeTo, awesome lists PRs) |
| 2 | Freeze changes before launch day | **Show HN** (Tue-Thu), then answer all day; LinkedIn founder post the same day |
| 3 | Write the HN retrospective | Reddit wave 1 (r/selfhosted, r/opensource) on separate days; article 1 and 3 live |
| 4 | Product Hunt assets final | **Product Hunt launch**; newsletter announces it; LinkedIn company post |
| 5-6 | | Reddit wave 2 (r/laravel, r/PHP, r/elearning, r/ClaudeAI), MCP registries and directories batch 2, article 5 and 7 |
| 7-8 | | E-learning communities and newsletters pitches, guest posts, first conference CFP submissions |
| 9-10 | | Comparison and standards pages, demo case studies, YouTube series |
| 11-12 | Review: what worked, per-channel cost of attention | Second push for the best channel; plan the next quarter |

## 8. Messaging

One sentence (the landing's): *Courses that stay true to their sources: an open-source, AI-native LMS with cited generation,
a site per customer and standards built in.*

Draft hooks per channel (drafts, owner edits before use; none is posted by us):

- Hacker News: `Show HN: ulams, an open-source LMS whose courses stay in sync with their sources`
- Product Hunt tagline: `Open-source LMS whose courses stay in sync` (under 60 characters)
- LinkedIn (customer education): "Your product ships every week. Your courses do not. Here is how we keep them in sync."
- r/selfhosted: "ulams, a self-hostable LMS (MIT): Docker Compose and shared-hosting guides, H5P, SCORM, xAPI"
- MCP communities: "An MCP server for an LMS, with dry-run and confirmation tokens so an agent cannot delete a course by surprise"

Rules for every message: say what is built and what is not (the feature index marks Coming); disclose that we are the
authors; no invented numbers, benchmarks or customer claims; credit EscolaLMS Wellms (origin), Lumi (H5P), LiaScript,
Adapt, pdfme and the demo-content sources.

## 9. Assets checklist

| Asset | Status |
|---|---|
| Logo and brand (Orbital Folio) | exists |
| OG images per language | exists for the landing; per-article template needed |
| Screenshots | docs screenshots exist; a landing set for launch sites needed |
| 90-second product video, 5-minute MCP walkthrough, README GIF | to make |
| One-page PDF and press kit (logos, screenshots, facts, licence, contact) | to make |
| Demo accounts and a launch-day runbook (capacity, reset, who watches) | to make |
| "Known limitations" and FAQ page | to make |
| Comparison data with sources and dates | `comparison.json` exists; sources to add |

## 10. KPIs and review

No baselines exist; set targets after two weeks of data. Track weekly:

| Area | Metrics |
|---|---|
| Reach | Sessions by source/medium/campaign, new vs returning |
| Interest | Feature groups opened, docs visits, API browser use |
| Activation | Demo opens (learner/admin), CLI copies and installs, newsletter signups |
| Community | GitHub stars, forks, issues, discussions; HN/PH/Reddit comments answered within 2 hours on launch days |
| Search | Impressions, clicks, average position, indexed pages, referring domains |
| Quality | Time to first response, bug reports from launches, sentiment notes |

Review every Monday (30 minutes): what moved, what to repeat, what to drop. A channel that brings nothing after two
honest attempts stops.

## 11. Risks

| Risk | Mitigation |
|---|---|
| Claiming unbuilt features | Gate G2; feature index statuses; no screenshots of unbuilt UI |
| Launch traffic breaks the demos | Gate G4; static landing on Cloudflare Pages; capacity test; runbook |
| Community backlash for self-promotion | Contribute first, disclose, follow rules, one channel at a time |
| Legal: GPL parts, demo-content licences, competitor comparisons | Gate G6; dated, sourced comparison; attributions |
| Founder time (everything depends on one person) | Cap at the hours the owner chooses; batch content; reuse every asset |
| Vote manipulation or fake reviews | Not done, ever |
| Privacy | Cookieless analytics, privacy page, no personal data in UTMs |

## 12. Owner decisions and actions

Decisions needed:

1. Approve the plan and the gates; say when the publishing gate opens (the standing rule says after the roadmap).
2. Analytics: Cloudflare Web Analytics plus Plausible or self-hosted Umami (recommended), or something else?
3. Production domains: landing on `ulams.app`, docs on `docs.ulams.app`?
4. Who is the voice: founder profile on LinkedIn and HN, company page, or both? Which language first for LinkedIn (EN and PL)?
5. Newsletter provider: MailerLite (already integrated)?
6. Publish the CLI to npm and the CLI image now (#77), since the MCP story depends on it?
7. Budget for optional paid items (eLearning Industry listing, review-site profiles, a video editor, conference tickets): none, or a cap?
8. Weekly time budget for the campaign.

Owner-only actions (accounts and verification, none done by us): Google Search Console and Bing Webmaster verification
(DNS TXT), Product Hunt maker account, Hacker News and Reddit accounts with some history, LinkedIn company page,
analytics account, MailerLite list, YouTube channel, GitHub repo settings (topics, homepage, social preview).

## 13. Work we can start now without publishing anything

1. Analytics helper and events, privacy page (behind a flag), CSP allowance.
2. `robots.txt`, `sitemap.xml`, `SoftwareApplication`/`Organization` JSON-LD for the landing; keep staging `Disallow`.
3. README rewrite (status, GIF placeholder, quick start), GitHub topics list as a PR-ready checklist.
4. Draft the first four articles and the HN/PH/Reddit texts in `docs/marketing/` (not published).
5. Press kit and a launch-day runbook.
6. Comparison data sources and dates.
7. The H5P content types page and the demo summary pages (indexable, behind the production gate).

Each item is a small PR with tests; none changes what the public sees until the gate opens.
