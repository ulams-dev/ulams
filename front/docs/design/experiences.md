# E-learning experiences: content and Google Stitch prompts

> Three deliberately different learning experiences used to design the Ulams front-end in [Google Stitch](https://stitch.withgoogle.com/).
> The same courses, lessons, quizzes and projects are created by `DemoCoursesSeeder`, so every design is backed by real data in the app.

## 0. What the product actually has (from the code — every design must cover this)

**Topic types** (`api/.../topic-types/src/Models/TopicContent/*`, `topic-type-gift`, `topic-type-project`):

| #   | Type      | What the learner sees                                                                                                                                                             |
| --- | --------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | RichText  | Markdown article (headings, callouts, KaTeX math, tables, images)                                                                                                                 |
| 2   | Video     | Video player with poster, length, transcript                                                                                                                                      |
| 3   | Audio     | Audio player (podcast / pronunciation / soundscape)                                                                                                                               |
| 4   | Image     | Full-bleed image / diagram with caption, zoom/gallery                                                                                                                             |
| 5   | PDF       | Embedded PDF reader with page nav + download                                                                                                                                      |
| 6   | OEmbed    | Embedded external media (YouTube, Vimeo, Figma, CodePen, map)                                                                                                                     |
| 7   | H5P       | Interactive (drag-and-drop, hotspots, flashcards, interactive video, branching)                                                                                                   |
| 8   | SCORM SCO | Packaged module in a framed player with its own nav                                                                                                                               |
| 9   | cmi5 AU   | xAPI-tracked external activity launched in a frame/new window                                                                                                                     |
| 10  | GIFT Quiz | Quiz with 8 question types: multiple choice, multiple-right-answers, true/false, short answer, matching, numerical, essay, description block; attempts, time limit, score, review |
| 11  | Project   | Assignment brief → learner uploads a solution file → tutor feedback                                                                                                               |

**Topic flags:** free preview, can skip, duration, introduction, summary, description, downloadable resources.
**Course:** title, subtitle, summary, description, level, language, duration/hours to complete, target group, tutors, categories, tags, image + poster + teaser video, price/packages/subscriptions, certificate, rating & reviews.
**Course player chrome:** lesson/sub-lesson tree with progress, topic view, prev/next + "mark as complete", notes & bookmarks, downloads, finish page (congrats, certificate, rate course).
**Around the course:** catalogue + category filter, search, tutors, webinars, stationary events, consultations (1:1 booking), packages/subscriptions, cart & vouchers, user area (my courses, certificates, tasks, bookmarks, orders, notifications).

## 1. How to use the prompts in Stitch

1. New project → **Web**, the highest-quality model mode (it follows long design briefs better).
2. Paste the **Design brief + Landing** prompt first. Iterate until the landing feels right — that screen locks the visual language.
3. In the **same project**, paste each **follow-up screen prompt** one at a time ("Course page", "Lesson player", "Quiz", …). Stitch reuses the established style.
4. Ask at the end: _"Export the design tokens (colors, type scale, radii, spacing, shadows) as CSS custom properties."_ — they drop straight into the CSS-vars theme that replaces styled-components.
5. Export to Figma / HTML for implementation.

---

## 2. Experience 1 — "The Coffee Atlas" · editorial, self-paced, slow learning

**Concept:** a magazine you can learn from. Long-form, tactile, unhurried. For people who want depth, not gamification.

**Course content**

- **Title:** The Coffee Atlas — From Seed to Cup
- **Subtitle:** A field guide to specialty coffee for curious home baristas
- **Level:** Beginner → Intermediate · **Language:** English · **Length:** 6 lessons, 22 topics, ~9 h
- **Target group:** Home brewers, café staff in their first year, food writers
- **Tutors:** Inés Duarte (Q-grader, Colombia) · Tomasz Wierzba (roaster, Kraków)
- **Price:** €89 · or in the "Taste Makers" package with the cupping masterclass webinar
- **Summary:** Follow one coffee cherry from a hillside in Huila to your cup. Learn origin, processing, roasting, brewing and tasting — and leave with your own signature recipe.
- **Certificate:** "Certified Home Barista — The Coffee Atlas"

| Lesson                | Topic                 | Type                 | Content                                                                              |
| --------------------- | --------------------- | -------------------- | ------------------------------------------------------------------------------------ |
| 1. Origins            | Welcome to the Atlas  | Video (free preview) | 4-min film: dawn harvest in Huila, tutors introduce the journey                      |
|                       | The coffee belt       | Image                | Illustrated world map of growing regions with altitude bands                         |
|                       | Arabica vs Robusta    | RichText             | Long-read with pull quotes, a comparison table, a callout "Why altitude matters"     |
| 2. The farm           | Voices from the farm  | Audio                | 12-min podcast with a producer, ambient birdsong                                     |
|                       | Anatomy of a cherry   | H5P                  | Hotspot diagram: skin, pulp, mucilage, parchment, silver skin, bean                  |
|                       | Processing methods    | RichText             | Washed / natural / honey / anaerobic, with photos                                    |
|                       | Visit the cooperative | OEmbed               | Embedded map + 360° video of the cooperative                                         |
| 3. Roasting           | The roast curve       | Image                | Annotated roast curve chart (first crack, development time)                          |
|                       | Roaster's logbook     | PDF                  | 8-page printable roast log template (downloadable)                                   |
|                       | Roast simulator       | SCORM                | Packaged simulator: pick charge temp, watch colour change                            |
| 4. Brewing            | Ratios & extraction   | RichText             | Brew ratio maths with KaTeX: `EY% = (TDS × beverage mass) / dose`                    |
|                       | Pour-over masterclass | Video                | 14-min V60 technique with chapter markers                                            |
|                       | Grind size matching   | H5P                  | Drag-and-drop: match grind size to brew method                                       |
| 5. Tasting            | The flavour wheel     | H5P                  | Interactive SCA flavour wheel flashcards                                             |
|                       | Cupping at home       | cmi5                 | Tracked guided cupping session (timer, scoring form)                                 |
|                       | Tasting check         | GIFT Quiz            | see quiz below                                                                       |
| 6. Your signature cup | Design your recipe    | Project              | Brief: brew 3 variations, photograph, submit recipe card (PDF/image); tutor feedback |
|                       | Final reflections     | RichText (can skip)  | Reading list + coffee glossary                                                       |

**Quiz "Tasting check"** (one of each type)

1. _Multiple choice:_ Which process usually gives the fruitiest cup? → **Natural** / Washed / Wet-hulled / Decaf
2. _Multiple right answers:_ Which variables change extraction? → **Grind size**, **Water temperature**, **Brew time**, Cup colour
3. _True/false:_ Darker roasts contain more caffeine by weight. → **False**
4. _Short answer:_ The moment beans audibly pop during roasting is called… → **first crack**
5. _Matching:_ V60 → medium-fine · French press → coarse · Espresso → fine · Cold brew → extra coarse
6. _Numerical:_ Using 1:16, how many grams of water for 18 g of coffee? → **288** (±2)
7. _Essay:_ Describe your favourite cup this week using three descriptors from the flavour wheel.
8. _Description block:_ "Take a sip of water before the next section."

**Landing content:** hero headline "Learn coffee the slow way." · sub "Six chapters. One cherry. Your best cup." · CTA "Start the free chapter" · sections: chapter index as a table of contents, "From the tutors" letter, pull-quote testimonials ("I finally understand why my V60 tastes sour" — Maja, Gdańsk), upcoming webinar "Live cupping with Inés — 14 Nov", FAQ, package pricing, newsletter "Field notes".

#### Prompt 1 · Design brief + landing

```
Design a desktop website for "The Coffee Atlas", an online course platform that feels like a premium printed food magazine, not a SaaS app. Self-paced, slow learning, long-form reading.

Visual language: warm editorial. Off-white paper background (#F6F1E9) with subtle grain, deep roast brown text (#2B1D14), accent terracotta (#C2552D), secondary sage (#7A8B6F), hairline rules in #D9CFC2. Typography: a high-contrast serif for headlines (like "Fraunces" or "Canela"), a humanist sans for UI (like "Inter Tight"), generous line-height, drop caps, pull quotes, numbered chapter marks (I, II, III). Asymmetric 12-column grid, wide margins, editorial image crops, hand-drawn line illustrations of coffee plants and maps. No gradients, no glassmorphism, no generic stock icons, no rounded "card soup".

Landing page sections:
1. Masthead nav: logo wordmark "The Coffee Atlas", Courses, Tutors, Live sessions, Journal, Sign in, "Start free chapter" button.
2. Hero: headline "Learn coffee the slow way." Sub: "Six chapters. One cherry. Your best cup." Large editorial photo of hands holding red coffee cherries, caption in small italic. CTA "Start the free chapter", secondary "See the syllabus".
3. Table of contents: the 6 chapters as a magazine contents page (Origins, The farm, Roasting, Brewing, Tasting, Your signature cup) with page-number style durations and small icons for the media inside each (video, audio, interactive, quiz, project).
4. "A letter from your tutors": Inés Duarte (Q-grader, Colombia) and Tomasz Wierzba (roaster, Kraków), portraits, signature.
5. What's inside: an editorial strip showing the formats you learn with — film, podcast, interactive diagrams, printable PDFs, a roast simulator, a tracked cupping session, a quiz and a final project.
6. Pull-quote testimonials.
7. Live session teaser: "Live cupping with Inés — 14 Nov, 18:00 CET".
8. Pricing: single course €89 vs "Taste Makers" package.
9. FAQ accordion, newsletter "Field notes", footer.
```

#### Prompt 2 · Course page (catalogue detail)

```
Same style. Course detail page for "The Coffee Atlas — From Seed to Cup". Left: editorial header with title, subtitle "A field guide to specialty coffee for curious home baristas", level, 6 lessons / 22 topics / ~9 h, language, rating 4.9 (212 reviews), tutors. Teaser video poster. Long-form description with drop cap. Syllabus as an expandable contents list: lessons → topics, each topic shows a type label (Video, Audio, Image, Reading, PDF, Embed, Interactive, SCORM, Tracked activity, Quiz, Project), duration and a "Free preview" tag on the first video. Right sticky purchase column: price €89, "Add to cart", voucher field, "Included in Taste Makers package", what you get (certificate, downloadable roast log, lifetime access). Below: reviews, related courses, upcoming webinar.
```

#### Prompt 3 · Lesson player (reading + media)

```
Same style. Learning view inside the course. Left narrow column: chapter tree with progress ticks and the current topic highlighted; overall progress "38%". Centre: reading layout for the rich-text topic "Ratios & extraction" — serif article, pull quote, comparison table, a math formula block "EY% = (TDS × beverage mass) / dose", inline photo. Right collapsible panel: "My notes" with a text area and saved notes, "Bookmarks", "Downloads" (Roaster's logbook PDF). Bottom bar: Previous topic, "Mark as complete", Next topic. Also show small variants of the same frame for: video topic (player with chapter markers + transcript), audio topic (podcast player with waveform), image topic (zoomable annotated chart), PDF topic (embedded reader with page nav and download), embed topic (map/360 video), interactive H5P topic (hotspot diagram), SCORM topic (framed simulator with its own nav), tracked cmi5 activity (launch card "Opens guided cupping session", status "In progress").
```

#### Prompt 4 · Quiz, project, finish

```
Same style. Three screens. (1) Quiz "Tasting check": one question per page, editorial typography, question types shown: multiple choice, multiple correct answers, true/false, short text answer, matching pairs (brew method ↔ grind size), numerical answer with unit "g", essay text area, and a description-only interlude. Timer, attempt 1 of 3, results page with score 7/8 and per-question review. (2) Project "Design your signature recipe": brief, requirements checklist, file upload drop zone, submitted solution list with tutor feedback thread. (3) Course finish: congratulations spread, certificate preview "Certified Home Barista — The Coffee Atlas" styled like a letterpress certificate, 5-star rating + review form, "Continue with: Espresso at home".
```

---

## 3. Experience 2 — "On-Call" · professional cohort, dense, dark, keyboard-first

**Concept:** learning that feels like the tools engineers already live in: dense, fast, dark, data-rich, cohort deadlines and live incident drills.

**Course content**

- **Title:** On-Call — Incident Command for Platform Engineers
- **Subtitle:** Run calm incidents, write blameless postmortems, ship reliability
- **Level:** Advanced · **Language:** English · **Length:** 5 modules, 20 topics, 4-week cohort + live drills
- **Target group:** SREs, platform/backend engineers joining an on-call rotation, engineering managers
- **Tutors:** Priya Raman (Staff SRE) · Marek Lis (Incident commander, fintech)
- **Price:** €490 per seat · team subscription · company voucher codes
- **Summary:** A 4-week cohort with a live game-day. You'll command a simulated outage, coordinate responders, communicate to stakeholders and write the postmortem.
- **Certificate:** "Incident Commander — Level 1"

| Module      | Topic                          | Type                | Content                                                                          |
| ----------- | ------------------------------ | ------------------- | -------------------------------------------------------------------------------- |
| 0. Briefing | Course kickoff                 | Video (preview)     | Cohort welcome, schedule, how drills are graded                                  |
|             | Rules of engagement            | RichText            | Severity matrix table (SEV1–SEV4), roles (IC, comms, ops, scribe)                |
| 1. Signals  | Anatomy of an alert            | Image               | Annotated Grafana-style dashboard screenshot                                     |
|             | SLOs and error budgets         | RichText            | Math: `budget = (1 − SLO) × window`; worked example 99.9% / 30 days = 43.2 min   |
|             | Talk: "Alert fatigue is a bug" | OEmbed              | Embedded conference talk                                                         |
| 2. Command  | Taking command                 | Video               | Role-play of the first 5 minutes of a SEV1                                       |
|             | Radio discipline               | Audio               | Recorded incident bridge call — spot the mistakes                                |
|             | Who does what?                 | H5P                 | Branching scenario: choose actions as IC under pressure                          |
|             | Incident runbook template      | PDF                 | Printable runbook + comms templates                                              |
| 3. Game day | Outage simulator               | SCORM               | Simulated cascading failure, timeline + chat pane                                |
|             | Live drill                     | cmi5                | xAPI-tracked live drill in the sandbox cluster                                   |
|             | Comms check                    | GIFT Quiz           | see below                                                                        |
| 4. After    | Blameless postmortems          | RichText            | Template, 5 whys, contributing factors vs root cause                             |
|             | Postmortem assignment          | Project             | Write the postmortem for the game-day outage (Markdown/PDF), peer + tutor review |
|             | Reading list                   | RichText (can skip) | Papers and books                                                                 |

**Quiz "Comms check"**

1. _Multiple choice:_ First thing the IC does when paged into a SEV1 → **Declare roles and open the incident channel** / Start debugging / Email the CTO / Roll back everything
2. _Multiple right answers:_ What belongs in a status update? → **Impact**, **Current action**, **Next update time**, Suspected engineer's name
3. _True/false:_ A postmortem should name the person who caused the outage. → **False**
4. _Short answer:_ The time from detection to mitigation is called… → **time to mitigate** (also accept "TTM")
5. _Matching:_ SEV1 → customer-facing outage · SEV2 → major degradation · SEV3 → minor, workaround exists · SEV4 → cosmetic
6. _Numerical:_ Minutes of error budget for a 99.95% SLO over 30 days? → **21.6** (±0.1)
7. _Essay:_ Write the first stakeholder update for the game-day incident (max 80 words).
8. _Description block:_ "Drill starts in 2 minutes — open the sandbox."

**Landing content:** hero "Stay calm at 3 a.m." · sub "A 4-week cohort for engineers who carry the pager." · CTA "Apply for the November cohort" · live status-page-style "Next cohort: 4 Nov · 18/24 seats" · curriculum timeline · drill replay screenshot · team pricing · testimonials from engineering leads · consultations "Book a 1:1 on-call review" · webinar "Postmortem teardown — live".

#### Prompt 1 · Design brief + landing

```
Design a desktop website for "On-Call", a cohort-based course platform for platform engineers and SREs. It must feel like a pro developer tool (think incident dashboards, terminals, status pages), not a marketing template.

Visual language: dark UI. Background #0B0F14, panels #121821, borders #1E2733, text #E6EDF3, muted #8B98A5. Accent signal colors used semantically: green #3FB950 (healthy), amber #D29922 (degraded), red #F85149 (incident), electric blue #58A6FF (links/actions). Monospace for data and labels ("JetBrains Mono"), a tight grotesk for headlines ("Space Grotesk"). Dense 8px grid, small radii (4px), keyboard shortcut hints (⌘K), status pills, sparklines, timelines, log-style lists. No illustrations of smiling people, no pastel gradients.

Landing page:
1. Top bar: logo "On-Call", Curriculum, Cohorts, Drills, Pricing, Teams, Sign in, "Apply" button, ⌘K search.
2. Hero: "Stay calm at 3 a.m." Sub: "A 4-week cohort for engineers who carry the pager." Right side: a fake live incident timeline panel (SEV1 declared 03:12, IC assigned, mitigation 03:41) with status pills. CTA "Apply for the November cohort". A status-page strip: "Next cohort: 4 Nov · 18/24 seats taken".
3. Curriculum as a horizontal timeline: Briefing → Signals → Command → Game day → After, each showing the formats inside (video, audio, reading, embed, branching scenario, PDF runbook, SCORM simulator, live tracked drill, quiz, postmortem project).
4. "Game day" feature: screenshot of the outage simulator with chat pane and metrics.
5. Instructors: Priya Raman (Staff SRE), Marek Lis (Incident commander) with stats.
6. Teams: per-seat €490 vs team subscription, voucher codes for companies.
7. 1:1 consultations: "Book an on-call review" calendar slots. Upcoming webinar "Postmortem teardown — live".
8. Testimonials as quoted log lines from engineering leads. FAQ. Footer.
```

#### Prompt 2 · Course page / cohort dashboard

```
Same style. Logged-in cohort dashboard for "On-Call — Incident Command for Platform Engineers": week 2 of 4, progress bars per module, deadline list (Comms check quiz due Thu, Postmortem due in 9 days), next live drill countdown, cohort leaderboard by drill score, recent tutor feedback, my tasks. Below: full module/topic table with columns: topic, type (Video, Reading, Image, Embed, Audio, Branching H5P, PDF, SCORM, cmi5 drill, Quiz, Project), duration, status (done / in progress / locked), preview tag.
```

#### Prompt 3 · Lesson player

```
Same style. Three-pane learning view like an IDE: left module tree with status icons and keyboard navigation hints (J/K), centre content, right panel with tabs Notes / Bookmarks / Resources / Transcript. Centre shows the reading topic "SLOs and error budgets" with a code-styled math block "budget = (1 − SLO) × window", a severity table, and a callout. Bottom command bar: ← Prev, "Mark complete (⌘↵)", Next →. Also show compact variants of the centre pane for: video with chapter list, audio bridge-call recording with timestamped comments, annotated dashboard image, PDF runbook reader, embedded conference talk, H5P branching scenario (decision buttons), SCORM outage simulator in a framed window, and a cmi5 live drill launch card with live xAPI status ("attempted", "progressed 60%").
```

#### Prompt 4 · Quiz, project, finish

```
Same style. (1) Quiz "Comms check": dense form layout, all question types: multiple choice, multiple correct, true/false toggle, short text, matching (SEV level ↔ definition), numerical with unit "minutes", essay with word counter (max 80), and an info-only block. Timer in the top bar, attempts 1/2, results with per-question diff view. (2) Project "Write the postmortem": brief, rubric table, upload area (Markdown/PDF), review thread with tutor and peer comments, status "Changes requested". (3) Completion: terminal-style success, certificate "Incident Commander — Level 1" with verification ID and QR, rate the cohort, "Next: Chaos engineering cohort".
```

---

## 4. Experience 3 — "Night Sky Explorers" · playful, gamified, mobile-first, for kids 10–14

**Concept:** an adventure map, not a course list. Short missions, rewards, read-aloud audio, parents in the loop.

**Course content**

- **Title:** Night Sky Explorers
- **Subtitle:** A 7-mission journey through planets, stars and galaxies
- **Level:** Beginner (ages 10–14) · **Language:** English (Polish audio available) · **Length:** 7 missions, 21 topics, 10–15 min each
- **Target group:** curious kids, homeschool families, teachers running a club
- **Tutors:** Dr. Ada Kowalczyk (astrophysicist) · "Orbi" the robot guide (mascot)
- **Price:** Free first mission · family subscription €6/month · classroom package
- **Summary:** Build your own star map mission by mission. Spot constellations, explore planets, and earn your Junior Astronomer badge.
- **Certificate:** "Junior Astronomer" badge + printable diploma

| Mission           | Topic                     | Type                | Content                                                                         |
| ----------------- | ------------------------- | ------------------- | ------------------------------------------------------------------------------- |
| 1. Lift-off       | Meet Orbi                 | Video (preview)     | 2-min animated intro by the mascot                                              |
|                   | Your sky tonight          | Image               | Star chart of tonight's sky over your city                                      |
| 2. Our Moon       | Why does the Moon change? | H5P                 | Interactive: drag the Moon around Earth to see phases                           |
|                   | Moon diary                | PDF                 | Printable 30-day Moon observation sheet                                         |
| 3. Planets        | Planet parade             | RichText            | Short facts with big pictures; "Fun fact" bubbles                               |
|                   | Sounds of space           | Audio               | NASA-style sonification of Saturn's rings, read-aloud narration                 |
|                   | Fly to Mars               | OEmbed              | Embedded Mars rover 360° panorama                                               |
| 4. Stars          | How stars are born        | Video               | Animated nebula explainer                                                       |
|                   | Star life cycle           | H5P                 | Flashcards: nebula → main sequence → red giant → white dwarf                    |
| 5. Constellations | Connect the stars         | SCORM               | Game: draw constellations by connecting stars                                   |
|                   | Sky safari                | cmi5                | Tracked backyard observation challenge (check in what you saw)                  |
| 6. Galaxies       | How big is space?         | RichText            | Scale comparisons, simple math: light travels 300,000 km every second           |
|                   | Mission quiz              | GIFT Quiz           | see below                                                                       |
| 7. Your star map  | Build your star map       | Project             | Draw or photograph your own constellation map, upload it; tutor sends a sticker |
|                   | Bonus: space jokes        | RichText (can skip) | Just for fun                                                                    |

**Quiz "Mission quiz"**

1. _Multiple choice:_ Which planet is the biggest? → **Jupiter** / Mars / Venus / Earth
2. _Multiple right answers:_ Which are planets? → **Mars**, **Neptune**, **Venus**, the Moon
3. _True/false:_ The Sun is a star. → **True**
4. _Short answer:_ The group of stars that looks like a big spoon/plough is called the Big… → **Dipper** (also "Plough")
5. _Matching:_ Mercury → closest to the Sun · Saturn → famous rings · Mars → red planet · Neptune → farthest planet
6. _Numerical:_ How many planets are in our Solar System? → **8**
7. _Essay:_ If you could visit one planet, which one and why?
8. _Description block:_ "Great job, explorer! Stretch your arms before the last questions."

**Landing content:** hero "Your adventure to the stars starts tonight" · CTA "Start mission 1 — free" · mission map preview · "For parents" (progress emails, safe, ad-free) · "For teachers" (classroom package) · badges showcase · live "Star party" stationary event + webinar "Ask an astronomer".

#### Prompt 1 · Design brief + landing (mobile-first)

```
Design a mobile-first (390px) plus desktop website for "Night Sky Explorers", a gamified astronomy course for kids aged 10–14 and their parents. It should feel like a friendly adventure game map, not a school portal.

Visual language: deep night blue background #13153A with a soft star field, playful accent colors: sunshine yellow #FFD23F, coral #FF6B6B, mint #3DDC97, lilac #9B8CFF. Chunky rounded shapes (16–24px radii), thick 3px outlines, bouncy illustrated planets, a friendly robot mascot "Orbi". Rounded geometric font for headlines ("Baloo 2" or "Fredoka"), very readable body font ("Nunito"), large tap targets (48px+), big icons, short sentences. Progress shown as a winding mission path with planets as checkpoints, stars as points, badges as rewards. No dense tables, no corporate photography.

Landing page:
1. Header: logo "Night Sky Explorers", Missions, For parents, For teachers, Log in, big yellow button "Start mission 1 — free".
2. Hero: "Your adventure to the stars starts tonight." Orbi waving from a rocket, star field, CTA.
3. Mission map: 7 missions as a winding path (Lift-off, Our Moon, Planets, Stars, Constellations, Galaxies, Your star map), each node shows what's inside with fun icons (watch, listen, play, read, print, explore, quiz, make).
4. Badges showcase: Moon Watcher, Planet Hopper, Star Finder, Junior Astronomer.
5. For parents: progress emails, safe and ad-free, 10–15 min missions, family subscription €6/month.
6. For teachers: classroom package, printable worksheets.
7. Events: "Star party in Kraków — 22 Nov" (in-person) and webinar "Ask an astronomer — live".
8. Testimonials from kids (first names + age) and parents. FAQ. Footer.
```

#### Prompt 2 · Mission map (course page)

```
Same style, mobile 390px. The course home for a logged-in kid: header with avatar, star points counter (240 ★) and streak (5 nights). The mission path with missions 1–3 completed (glowing), mission 4 "Stars" current (pulsing), 5–7 locked with padlocks. Tapping a mission opens a bottom sheet listing its topics as big tiles with icons and minutes: Watch (video), Look (image), Play (interactive), Print (PDF), Read, Listen (audio), Explore (embed), Game (SCORM), Challenge (tracked outdoor activity), Quiz, Make (project). Parent toggle in the corner showing a simple progress summary.
```

#### Prompt 3 · Topic player

```
Same style, mobile 390px. Topic screen "Why does the Moon change?": top bar with back arrow, mission name, progress dots (2 of 3). The H5P interactive fills the card: drag the Moon around Earth to see phases, big instruction bubble from Orbi. Bottom: big "I did it!" complete button, previous/next arrows, and a "My space notebook" button (notes + saved favourites). Also show smaller frames of the same template for: video with big play button and subtitles, audio with read-aloud karaoke text highlighting, star chart image with pinch-zoom hint, printable PDF with "Print" and "Download", Mars 360° embed, a SCORM constellation game in a framed panel, and a backyard challenge launch card (tracked) with a checklist "I saw the Moon / I found the Big Dipper".
```

#### Prompt 4 · Quiz, project, finish

```
Same style, mobile 390px. (1) Mission quiz with one big question per screen and big answer buttons: multiple choice, pick-all-that-apply, true/false as thumbs up/down, type-in short answer, matching with drag lines, number pad for numerical answers, a drawing/writing area for the open question, and a cheerful "stretch break" info card. Hearts for attempts, instant friendly feedback, final score with stars. (2) Project "Build your star map": steps 1-2-3, camera/upload button, gallery of submitted maps, tutor reply with a sticker. (3) Finish: confetti, "Junior Astronomer" badge and printable diploma, rate with emoji faces, "Next adventure: Ocean Explorers".
```

---

## 5. From designs to the app (feeds the implementation)

- Each experience becomes a **theme = a set of CSS custom properties** (`--color-bg`, `--color-text`, `--color-accent`, `--font-display`, `--radius-md`, …) applied on `:root` / `[data-theme="coffee|oncall|nightsky"]`. Stitch's exported tokens map 1:1 onto that contract — no styled-components ThemeProvider.
- The three courses above (lessons, topics of all 11 types, quizzes with all 8 question types, projects, tutors, categories, packages, webinars, events, consultations, vouchers) are seeded by `DemoCoursesSeeder` so every Stitch screen has real data behind it. Media placeholders: royalty-free images/video/audio + generated PDFs, uploaded to MinIO by the seeder; SCORM/cmi5/H5P packages from public sample packages.
- The prompts and content live in the repo as `front/docs/design/experiences.md` so designers and agents share one source.

---

## 6. Experience 4 — "Gravity Lab" · dark space, a live simulation, free

- **Concept:** one free course, "How gravity shapes the solar system", built around a real-data 3D simulation
  the learner plays inside. Calm and exact, not a game.
- **Tenant:** slug `gravity`, preset `gravity`, accent `#3DD6F5`, certificate in Space Grotesk and JetBrains Mono.
- **Look:** near-black navy `#05070F`, a calm starfield, thin cyan orbit rules, gold `#FFB547` as the second colour.
  Space Grotesk headings, Inter body, JetBrains Mono for numbers.
- **Course content (M8, done):** a welcome lesson, nine modules that follow the walkthrough of the simulation (each
  with Interactive topics pinned to a step range, a short explanation, a Layout element and a quiz), a 15-question
  final test with the certificate and a "Sources and licence" lesson. Every number cites NASA, JPL or another named
  reference; the text is CC BY 4.0.
- **Landing:** header; `cosmos` hero (the live simulation from the showcase endpoint, a drawn orbit scene without
  it); three tiles (explore, read, check yourself); `orbits` syllabus; badges (free, certificate, modules, hours);
  data sources as citation chips; FAQ; call-to-action band; footer with the simulation's licence.
- **Prompt:** `front/docs/design/stitch/interactive-demos/PROMPTS.md`, "gravity-landing".

## 7. Experience 5 — "Poland, Measured" · cartographic paper, maps and charts, free

- **Concept:** a free course of maps and charts of public data, every number cited. English course now; the Polish
  twin ("Polska w liczbach") arrives with M8d.
- **Tenant:** slug `poland`, preset `poland`, accent `#C8102E`, certificate in Playfair Display and Noto Sans.
- **Look:** warm paper `#F4EFE6`, graticule hairlines, legend-style badges, teal `#5E8C8A` as the second colour.
  Source Serif 4 headings, IBM Plex Sans body, tabular numerals.
- **Course content (M8, done):** a welcome lesson, nine chapters (energy, prosperity, security, made in Poland, daily
  life, mobility, health, people, the unfinished work), a 12-question final test with the certificate and a "Sources and
  licence" lesson, in English and in Polish as two courses. Every figure names its publisher and period; the text is CC BY 4.0.
- **Landing:** header with the English and Polish course links; `atlas` hero (the live map from the showcase
  endpoint, a drawn contour scene without it); three tiles (map, charts, quizzes); `atlas` syllabus with chapter
  numbers; badges (free, EN/PL, certificate); sources as footnote chips; FAQ; call-to-action band; footer with
  the map data credits.
- **Prompt:** `PROMPTS.md`, "poland-landing".

## 8. Experience 6 — "The Scottish Book" · archival notebook, mathematics and history, free

- **Concept:** "Stanisław Ulam and the Lwów School of Mathematics": five small interactives (the Ulam spiral, a
  Monte Carlo estimate of pi, a cellular automaton, a page of the Scottish Book, a map of Lwów) around a sourced
  history. The product's name honours Ulam; the demo suggests no endorsement, has no account in his name, and the
  course quotes only short, exact, sourced passages (the landing has no testimonials section).
- **Course content (M9b, done):** a welcome lesson that ends with the spiral (the landing hero plays it), eight modules
  holding sixteen numbered lessons (Lwów; the Lwów School and the Scottish Café; America and the war; Monte Carlo; the
  Teller-Ulam design; the prime spiral and cellular automata; Fermi-Pasta-Ulam-Tsingou; legacy and the name), a
  14-question final test with the certificate and a "Sources and licence" lesson that credits four photographs. Every
  quiz question is traced to the fact sheet (`demo-content/ulam/facts.json`, ADR 0095).
- **Tenant:** slug `ulam`, preset `ulam`, accent `#1D3B8F`, certificate in Playfair Display and JetBrains Mono.
- **Look:** cream paper `#F7F3E8` with a 5 mm squared-paper grid, ruled margin, paper-corner frames, marginal red
  `#B23A2E` as the second colour. EB Garamond headings, IBM Plex Sans body, IBM Plex Mono for formulas.
- **Landing:** header; `notebook` hero (the spiral from the showcase endpoint, a drawn squared-paper scene
  without it); the five interactive formats; `timeline` syllabus; sources; badges; FAQ including "Why is the
  product called ulams?"; call-to-action band; footer.
- **Prompt:** `PROMPTS.md`, "ulam-landing".
