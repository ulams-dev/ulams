# 0089. Six demo academies: three free interactive courses, one theme preset each, sourced content, EN/PL as two courses

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/interactive-demos.md` (M7–M9)

## Context and problem statement

The product owner asked for three free courses: one built on the gravity app, one built on the poland
app, and one about Stanisław Ulam. That makes six demos in total, next to coffee, oncall and nightsky.
The existing demos are paid courses, one per tenant, each with a theme preset named after the demo
(`DemoExperience` subclasses, `front/web/src/docs/<slug>.json`).

The poland app is bilingual. Courses have a single `language` field and no translation model.

The Ulam course is history and biography, where invented dates or quotes would damage trust.

## Decision

- **Tenants.** `gravity` ("Gravity Lab"), `poland` ("Poland, Measured / Polska w liczbach") and `ulam`
  ("The Scottish Book"). Each has its own theme preset named after the slug, as the existing three
  do: `gravity` (dark space), `poland` (cartographic paper) and `ulam` (squared-paper notebook). Each
  preset also gets a themed certificate that uses only the fonts already bundled in `api/pdf/fonts`.
- **Free.** The courses are `public = true`, with no products, prices or coupons. `commerce()` is a
  no-op, and the landings show "Free" instead of pricing. Demo mode, the users and the hourly reset
  work as for the other demos.
- **EN/PL.** The poland tenant gets two courses, "Poland, measured" (`en`) and "Polska w liczbach"
  (`pl`). They are built from the same lesson table and backed by the same interactive package; the
  topic passes the locale to the package. The landing has a language switch that links the two
  courses. A multilingual course model is out of scope (default, pending #149).
- **Sourcing rules for every demo course.**
  1. Every factual sentence in a lesson, card or quiz item cites a fragment: the app's own text plus
     an authoritative public source (NASA/JPL for gravity; Eurostat, GUS, ministries and operators
     from `sources.json` for poland; MacTutor, Los Alamos National Laboratory publications,
     Encyclopaedia Britannica and published histories for Ulam). Each demo keeps
     `demo-content/<demo>/sources.json` (`id`, `title`, `publisher`, `url`, `accessed`, `licence` where
     relevant). Topic texts cite with bracketed numbers (`[3]`) and end with a "Sources" list
     generated from that file, so the citation survives in every client and in exports. Quiz items
     carry the source id in their GIFT feedback.
  2. Content is not invented. Quotes are verbatim from a fetched page with its URL. Dates and numbers
     come from a cited source. Where sources disagree, the lesson says so or leaves the claim out.
  3. Weak sources from the poland app are dropped: the essay it replies to, Hacker News, Wikipedia
     used as a primary source, and entries marked `reported_in_uploaded_document`. A claim stays only
     if a primary source supports it.
  4. Images are used only with a licence confirmed per file on its Commons (or archive) page: public
     domain or CC BY / CC BY-SA. The licence and attribution are recorded in `demo-content/<demo>/CREDITS.md`
     and shown in the lesson. A photo whose licence cannot be confirmed is replaced by a drawn
     illustration.
  5. The Teller–Ulam design is covered factually and briefly (who, when, the test that followed), with
     no technical detail beyond encyclopaedic sources.
  6. Each course is reviewed by a person against its fact sheet before it is merged (definition of
     done for M8 and M9).
- **Product name.** The Ulam course states plainly that the product's name honours Stanisław Ulam. It
  does not suggest any endorsement by his estate or by any institution (the name check is #45).

## Consequences

- Good: six demos with one shape (tenant, preset, landing, course, certificate, reset) and a visible
  citation trail, which is the product's differentiator.
- Bad: the poland lesson text exists twice (EN and PL), and fixes must be made in both. Both are
  generated from one PHP lesson table with `{en, pl}` strings to limit drift.
- Bad: three more presets in every theme list (see the checklist in the plan, section 9).
