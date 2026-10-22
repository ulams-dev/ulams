# Market research: AI-native / headless LMS (October 2026)

Supplied by the owner on 2026-10-22. It is the "why" behind `docs/ROADMAP-PROMPT.md`: every roadmap item should trace back to a finding here. The product is called ULAMS in the repository; the research used the placeholder "Ulam LMS".
Base: Wellms (EscolaLMS, Laravel headless LMS) + Sylius as a separate commerce service.

## TL;DR

1. **AI course generation is table stakes, not a differentiator.** Every major vendor (Docebo, TalentLMS, 360Learning, Sana, Teachfloor, Coursebox, WorkRamp) ships "document → course". Outline and quiz generation work reliably; AI tutors, agents and personalised paths often look better in demos than in production.
2. **MCP for LMS has started, but the field is thin.** Docebo has an MCP server focused on learners searching and checking progress (read-mostly). Teachfloor runs a hosted MCP with read/write access to courses, lessons and enrollments. Mini Course Generator positions itself as an "agentic LMS" with an MCP server and a headless widget. Moodle, Canvas, D2L and Blackboard have only community MCP servers. **No open-source, self-hostable LMS is agent-native with full write access.**
3. **Customer education is consolidating at the top.** Gainsight bought Northpass and then Skilljar. Thought Industries is going "headless + AI + customer intelligence". Small SaaS teams increasingly skip the portal and use lightweight, embed-first tools. **The gap is mid-market and developer-led companies who want to embed learning in their product and docs.**
4. **Satisfaction with LMS is chronically low.** Repeated Brandon Hall research: only a single-digit share of organisations are very satisfied; a third to almost half actively look to replace. Most-cited switch reason over the years: **poor reporting**, then hard to use / outdated look, poor vendor support.
5. **Authoring cost is the core economic pain.** Chapman Alliance development ratios: 49 to 716 hours of work per 1 hour of e-learning depending on interactivity. Typical (mid) values per level: **79 h** passive, **184 h** limited interaction, **490 h** moderate participation. (A frequently repeated "197 h average" appears in secondary articles but is not in Chapman's published table; do not use it.) **Maintenance (updating stale content) is a hidden multiplier on top.**
6. **AI-generated content is locked in the LMS that generated it.** There is no portable, git-friendly format for AI-built courses. LiaScript (Markdown) is a credible base for "course as code".
7. **EU AI Act shapes the design.** AI that evaluates learning outcomes, assigns levels/tracks, or proctors tests is **high-risk** (Annex III, point 3). After the digital omnibus, stand-alone Annex III obligations apply from **2 Dec 2027**; Art. 50 transparency/marking obligations from **2 Dec 2026**. Design rule: AI drafts and recommends, humans decide; AI-generated content is marked.

## Competitive landscape

| Player | Segment | AI authoring | Agent / MCP | Headless / embed | Self-host / OSS | Notes |
|---|---|---|---|---|---|---|
| Docebo | Enterprise | Yes | MCP (learner search, progress, certs) | Partial | No | Mature enterprise, AI-first marketing |
| Sana Labs | Enterprise | Yes, from docs | AI search across org knowledge | No | No | Learning + knowledge management combined |
| Teachfloor | Cohort academies | Yes | Hosted MCP, read+write | Some | No | Most complete official LMS MCP today |
| Mini Course Generator | SMB / creators | Yes, since 2021 | MCP, "agentic LMS" | Widget + API, Intercom app | No | Closest to our positioning, but closed and SMB-focused |
| Skilljar (Gainsight) | Enterprise customer ed | Yes | Unclear | Limited | No | Category leader, CRM-tied, long implementations |
| Thought Industries | Customer ed / monetised | Yes | Roadmap: AI discovery | Headless | No | Expensive (from ~USD 2.5k/month reported) |
| WorkRamp | Employee + customer | AI content engine | Unclear | Limited | No | Two "clouds" in one platform |
| Moodle | Edu / public sector | Plugins | Community MCP only | No | Yes (PHP) | Huge base, interface debt, plugin hell |
| Open edX | MOOC scale | Limited | No | Partial | Yes | Heavy to self-host |
| Frappe LMS, EaseLMS | Lightweight OSS | No / minimal | No | No | Yes | Modern UI, simple feature set |
| **ULAMS (us)** | Dev-led SaaS, customer & developer education, EU / self-host | Grounded, per-element editing | **Full read/write MCP, OSS** | **API-first + web components** | **Yes** | Course-as-code (LiaScript), living courses |

### White space we target

The intersection that nobody covers today:

- open source and easy to self-host,
- agent-native (MCP with write access, scoped permissions),
- headless, embeddable in product and docs,
- content as code (versioned, diffable, portable),
- "living courses" that stay in sync with their sources (docs, repos, releases),
- EU-friendly (data residency, pluggable/local models, AI Act-aware by design).

## Pain points with evidence → what we do about it

The roadmap labels (R0 to R6) are the research document's own; map them to `docs/ROADMAP-PROMPT.md` phases before relying on them.

| Pain | Who | Evidence | Our answer (roadmap) |
|---|---|---|---|
| Authoring takes 50–700 h per hour of content | Authors, L&D | Chapman Alliance benchmarks | R1: source → grounded course in hours |
| Content goes stale, updating means re-export | Authors | Industry consensus, hidden maintenance cost | R3: living courses, source-change detection |
| AI-generated content is trapped in the vendor | Buyers | Analyst notes on AI LMS | R1.2 canonical model + LiaScript export, R6 import/export |
| Poor reporting, cannot prove ROI | L&D, buyers | Top switch reason in Brandon Hall surveys | R5: outcomes analytics tied to product data |
| Learning lives in a separate portal | Learners, SaaS | Headless / customer-ed vendors' own positioning | R2: SDK, web components, embed |
| Agents cannot operate the LMS | Developers, ops | Most LMS have no official MCP | R2.1: full MCP server |
| Lock-in, painful migration | Buyers | Chronic replacement intent | R6: SCORM / H5P / Moodle import |
| Hard to self-host, monolith upgrades | Developers | Moodle alternatives guides | R0: modern stack, one-command install |
| AI tutors / personalisation over-promised | Learners | Analyst notes: weaker in production | R4: narrow, grounded tutor; deterministic adaptivity |

Added by the owner on 2026-10-22 (not in the research): everybody gets the same course regardless of level and role, so the expert wades through the basics and the beginner gets lost in the middle. Answer: course variants by level and role from one source (`docs/plans/course-versions-and-variants.md`, ADR 0192).

## Risks

- **Wellms tech debt.** Packagist metadata for `escolalms/api` shows a Laravel 9 dependency. Laravel 9 is out of security support. An upgrade is a prerequisite, not a nice-to-have. *Verify the current state of the repo before planning.*
- **Buyer mismatch.** Classic LMS buyers are L&D teams who want SCORM, compliance reports and a simple admin. Developers are great early adopters but pay less. We deliberately target **customer / developer education at software companies** first.
- **Generation is a commodity.** The moat is workflow (grounding, review, versioning, sync), integrations and outcome data, not the model call.
- **AI Act high-risk scope.** Automated grading, level placement and proctoring would make us a high-risk provider. Keep these out of scope or strictly human-in-the-loop. (Relevant to variant routing: placement checks stay opt-in and advisory, see the plan's Q8.)

## Sources (accessed October 2026)

- Easygenerator, "Best AI-powered LMS for enterprise learning in 2026": https://www.easygenerator.com/en/blog/e-learning/ai-powered-lms/
- Teachfloor, "10 Best AI-Powered LMS Platforms in 2026": https://teachfloor.com/blog/best-ai-lms
- Teachfloor, "Best MCP servers for LMS platforms in 2026": https://www.teachfloor.com/blog/best-mcp-servers-for-lms
- Docebo MCP Server: https://www.docebo.com/products/mcp/
- Mini Course Generator, about / agentic LMS: https://minicoursegenerator.com/about/
- Mini Course Generator, LMS MCP server evaluation: https://minicoursegenerator.com/blog/lms-mcp-server-evaluation/
- Mini Course Generator, customer education platforms 2026: https://minicoursegenerator.com/blog/best-customer-education-platforms/
- Dupple, "The 8 Best Customer Education Platforms in 2026": https://dupple.com/learn/best-customer-education-platforms
- Thought Industries, headless LMS in the AI era: https://www.thoughtindustries.com/blog/rethinking-learning-delivery-with-a-headless-lms/
- Thought Industries product roadmap Q1 2026: https://community.thoughtindustries.com/whats-new/post/thought-industries-product-roadmap-what-s-next-for-customer-learning-JMozlxm0Jrf4vi0
- eLearning Industry, cost of eLearning authoring: https://elearningindustry.com/cost-of-elearning-authoring
- Chapman Alliance development ratios: https://www.slideshare.net/slideshow/how-long-does-it-take-to-develop-1-hour-of-elearning/59421278
- Training Magazine on Brandon Hall LMS surveys: https://trainingmag.com/?p=13564
- TechClass, LMS migration signs: https://techclass.com/resources/learning-and-development-articles/lms-migration-7-signs-its-time-to-upgrade
- thefrontkit, Moodle alternatives 2026: https://thefrontkit.com/blogs/moodle-alternatives-2026
- CASRAI, AI Act Annex III education: https://casrai.org/news/ai-act-annex-iii
- Utrecht University, high-risk AI in education: https://www.uu.nl/en/organisation/ai-policy/teachers/ai-compliance/high-risk-ai
- Packagist, escolalms/api: https://packagist.org/packages/escolalms/api
- GitHub, EscolaLMS/Create-LMS-App: https://github.com/EscolaLMS/Create-LMS-App
