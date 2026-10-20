# Prompt: market analysis and marketing campaign for ULAMS

Use this in a chat model that can browse the web (Claude with search, ChatGPT with browsing, Gemini, Perplexity). Paste everything below the line.
Run it in two passes if the model truncates: first sections 1-4 (analysis), then 5-8 (campaign). Keep the answers: the owner will compare models.

---

You are a senior product-marketing strategist and market analyst for developer-facing and education software, with open-source go-to-market experience.
Your job: (A) analyse the market around the product below, and (B) turn the analysis into an organic (no paid ads) marketing campaign the founder can run alone.

## Rules for your answer

- **Research first, with sources.** Use live web search. Every market claim (size, growth, competitor feature, price, user count, review score, ranking, date) needs a source URL and the date you read it.
  If you cannot find a number, write "not found", never estimate silently. Separate **facts** (sourced), **inferences** (yours, say why) and **hypotheses to test**.
- Today's date is in your system; say which year's data you use. Prefer sources from the last 18 months.
- Do not invent customers, benchmarks, quotes or statistics. Do not praise the product; test its claims against what competitors already do.
- Be concrete: names, links, channels, copy drafts, dates, numbers of hours. Tables where they help. No generic marketing advice ("create engaging content").
- Where the best option depends on a founder decision, list the options with trade-offs and recommend one.
- Length: thorough but scannable. Start each section with a 3-line summary.

## 1. The product (facts; treat as true, challenge the positioning)

**ULAMS** is an open-source (MIT core), self-hostable, **headless LMS** (REST API first) with an AI course builder. Origin: a fork of Wellms (EscolaLMS), now one monorepo
(Laravel 13 / PHP 8.4 / PostgreSQL API; React admin; Astro server-rendered learner app; separate H5P and PDF services). Public repo: `github.com/ulams-dev/ulams`.
Live demos (throwaway data, no signup, one-click login as student, tutor or admin): `https://ulams.app` (landing, EN/PL/ZH) with six demo academies:
Coffee Atlas (editorial self-paced), On-Call (incident-command cohort), Night Sky Explorers (gamified, kids 10-14), Gravity Lab (3D solar-system simulation),
Poland, Measured (35 years of public data on a map, EN/PL), The Scottish Book (Ulam and the Lwów School, five interactives).

**Positioning line (draft):** "Courses that stay true to their sources: an open-source, AI-native LMS with cited generation, a site per customer and standards built in."
Target buyer (draft): teams that teach a product, i.e. **developer education and docs teams, customer education / enablement teams**, plus self-hosters and e-learning developers.

**Differentiators claimed (verify each against the market):**

1. **Living Course** *(on the roadmap, not shipped)*: watches a course's sources (upload, Git repo, web pages), detects changes at fragment level, finds the lessons and quiz questions that cite them, and proposes
   cited updates the author reviews as one diff.
2. **AI Course Builder** *(shipped, beta)*: Markdown/PDF/DOCX to a cited course through a short interview, an approved outline with learning objectives, generated lessons and quizzes; every generated element cites a source fragment;
   "AI proposes, the author approves" (every change is a reviewable diff).
3. **MCP server and CLI for AI agents** *(shipped, built from the repo; npm package planned)*: `ulams mcp` exposes every command as MCP tools (stdio / Streamable HTTP), read-only mode, dry-run plans, confirmation tokens for destructive actions, agent audit log.
4. **Multi-tenant "site per customer"**: own host, database, storage bucket, keys and Redis prefix per academy; white-label themes.
5. **Standards**: H5P (isolated GPL service; all 54 content types from the Hub installable), SCORM 1.2/2004, cmi5 + built-in xAPI LRS, LTI 1.3 as both platform and tool (Moodle launch and grade passback verified), LiaScript, Adapt Learning (off by default), GIFT quizzes (8 question types).
6. **Media pipeline**: video to adaptive HLS with ffmpeg, on-demand image resizing API, safe upload guard (zip-bomb, zip-slip, MIME sniffing).
7. **Commerce**: products, bundles, subscriptions with trials, vouchers, Stripe and Przelewy24 (Polish payments), PDF invoices; a headless Sylius shop is planned.
8. **Analytics today**: course/topic stats, quiz reports with manual grading, dashboard charts (revenue, popularity, time spent), questionnaires, xAPI statements. *Planned*: per-course author analytics, at-risk learner scoring, AI tutor.
9. **Developer experience**: OpenAPI (566 operations) with a searchable API browser, TypeScript SDK, scoped API tokens, inbound webhooks (no outgoing webhooks yet), package-based extensibility.
10. **Operations**: Docker Compose, shared-hosting (MyDevil) and VPS + Cloudflare guides, high-availability reference topology, per-tenant backup and restore, CSP, rate limits.

**Honest limits:** one maintainer; pre-1.0; no paying customers yet; no public references; roadmap items (Living Course, AI tutor, risk scoring, Sylius) are not built; learner app is a reference frontend (the older React app is legacy);
the AI features need an LLM provider key (Anthropic driver today); no analytics on the site yet; docs live at `ulams-dev.github.io/ulams`.
Languages of the landing: EN, PL, ZH. The founder is based in Poland and works in Polish and English.

Already decided and available to you: `docs/plans/organic-marketing.md` (draft plan with channels, calendar, KPIs, gates), but **do not just repeat it**: challenge it and improve it with evidence.

## 2. Market analysis to deliver

1. **Category map.** Where does ULAMS sit? Define 4-6 relevant categories and size/trend each with sources: open-source LMS; headless / API-first LMS; customer-education and developer-education platforms (customer training LMS);
   AI course authoring tools; e-learning standards tooling (H5P, SCORM, xAPI); MCP/agent-ready SaaS. Say which category the product should claim first and why (where it can win, not where the market is biggest).
2. **Competitor table (at least 20).** Include: Moodle (and Moodle Workplace), Open edX, Canvas (Instructure), Chamilo, ILIAS, Sakai, Totara, Docebo, TalentLMS, LearnWorlds, Thinkific, Teachable, Kajabi, Absorb, 360Learning, Skilljar, Thought Industries, Northpass (Gainsight),
   Intellum, WorkRamp, Learn.xyz/Learnupon, LearnDash and Tutor LMS (WordPress), Frappe LMS, Appsmith/other low-code, Wellms/EscolaLMS, Articulate Rise/Storyline, Adapt Learning, LiaScript, plus AI course tools (Coursebox, Teachable AI, Synthesia, Mindsmith, Docebo AI, etc.).
   For each: type, target buyer, open-source or not, hosting, API/headless story, AI features, standards support, pricing model and entry price (sourced), strengths, weaknesses, how they acquire users (SEO, community, marketplaces, sales), and "who they are for that ULAMS is not".
3. **Customer and demand.** Who buys or adopts in this niche (developer education, customer education, internal enablement, open-source communities, SMB course sellers)? Jobs-to-be-done, buying triggers, objections (security, SSO, SCORM
   import, support, "who maintains this"), buying process (self-serve vs procurement), what they search for. Use real sources: analyst notes, surveys, communities, job posts, forum threads, Reddit, Hacker News, review sites (G2, Capterra, GetApp, TrustRadius), conference programs.
4. **Search landscape.** For 30-50 candidate keywords/topics (open-source LMS, headless LMS, LMS API, H5P LMS, SCORM cloud alternative, xAPI LRS open source, LTI 1.3 tool, customer education platform, AI course generator from documentation, MCP server for LMS, self-hosted LMS Docker, Moodle alternative, Open edX alternative...)
   give intent, who ranks today, how strong they are, and whether a new site with a docs-style content engine can realistically rank; label volumes only if sourced.
5. **Distribution landscape.** Where do these buyers and builders gather? Rank channels by fit: Hacker News, Product Hunt, Reddit (list exact subreddits and each one's self-promotion rule), dev.to/Hashnode, LinkedIn (groups, hashtags, creators in e-learning/DevRel/customer education),
   X/Bluesky/Mastodon, YouTube, newsletters and podcasts (name them, with audience size if public), awesome-lists, directories and registries (awesome-selfhosted, selfh.st, AlternativeTo, OpenAlternative, LibHunt, MCP registries and lists, Laravel News, Packagist),
   conferences and meetups (Write the Docs, DevRel events, Learning Technologies, DevLearn, OEB, Laracon, MCP/Claude meetups), foundations and communities (H5P, LiaScript, Adapt, ADL xAPI/cmi5, Moodle/Open edX communities, open-source funding programs).
   Note recent rule changes or bans for self-promotion on each.
6. **Trends and timing.** What changed in the last 12-18 months that helps or hurts (AI authoring, MCP adoption, open-source licence shifts, Moodle/Open edX news, LMS consolidation, EU accessibility act, WCAG 2.2, data-residency, DevRel budget cuts)? What should the founder say now?
7. **SWOT and positioning test.** Test the positioning line and each differentiator against competitors: is it unique, provable, valuable, and understandable in 5 seconds? Propose 3 alternative positioning statements with the audience each one targets, and recommend one with reasons.
8. **Risks and kill-criteria.** Where could this fail (maintainer risk, trust, GPL/H5P perception, AI claims, "yet another LMS" fatigue)? What evidence in the first 4-6 weeks should make the founder change course?

## 3. Campaign to deliver

Constraints: one founder, 5-10 hours per week unless you argue for more, EUR 0 baseline budget (list optional paid items separately with cost and expected return), no fake reviews or vote manipulation, privacy-first analytics (no cookie banner).
Everything public stays unpublished until the founder opens the gate: plan preparation separately from publishing.

4. **Strategy in one page**: goal for 90 days (leading indicators and lagging outcomes, with targets you justify and state as hypotheses), core audience order, the one story that carries the campaign, proof assets needed.
5. **Measurement plan**: tool recommendation (cookieless), events, UTM scheme, dashboard, weekly review, how to attribute demo opens and GitHub stars to channels.
6. **SEO plan**: technical checklist (Search Console, Bing, sitemap, structured data, Core Web Vitals, docs domain), 20 priority pages with target query, intent, outline and internal links, comparison/alternative pages (and how to stay accurate and legally safe), and a link-earning plan.
7. **Channel playbooks** for Hacker News (Show HN), Product Hunt, Reddit, dev.to/Hashnode, LinkedIn (founder and company page), directories/registries/awesome-lists, newsletters and podcasts pitches, communities (H5P, LiaScript, Adapt, xAPI, Laravel, MCP), YouTube:
   for each: prerequisites, exact steps, timing, draft copy (title, first comment, post body, pitch email), what not to do, expected effort and result. Draft all copy in English, and LinkedIn variants in Polish too.
8. **12-week calendar** (week by week, prepare vs publish, owner hours), the content backlog (30 titles with audience, keyword, format, channel), the asset checklist (video, GIF, screenshots, one-pager, press kit, demo runbook),
   a risk register, and the **10 decisions the founder must make** with your recommendation for each.

## 4. Output format

- Section order as above, numbered. Tables for competitors, keywords, channels, calendar.
- End with: (a) a source list (URL, title, date read), (b) a list of the 10 claims you are least sure about and how the founder can verify each in under a day, (c) the three actions to take this week.
- If you could not use live search, say so first and mark every figure "unverified".
