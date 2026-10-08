/**
 * The ulams UI catalogue: every component an agent may place in a page document.
 *
 * A document is a tree of nodes `{ "component": "<Name>", "props": {…}, "children": [ … ] }`
 * (the tree form of an A2UI v0.9 surface; see docs/decisions/0008-reference-frontend.md).
 * `<Render doc={…} data={…} />` validates each node's props against the schema below,
 * applies defaults and renders the matching Astro component. Unknown components and
 * invalid props render the plain-text fallback instead of breaking the page.
 *
 * Rules for agents
 * - Props are flat and explicit; there is no hidden context. Pick enum values, do not invent them.
 * - Links and image sources must be relative ("/courses/1"), "#anchor", http(s), mailto or tel.
 * - Any prop value may be a data binding `{ "$data": "/json/pointer", "$default": … }` resolved
 *   against the page's data model (for pages built from API data, e.g. `/course/lessons`).
 * - Text is plain text, never HTML. Only `Prose.markdown` takes Markdown (raw HTML is escaped).
 * - Do not write numbers or claims that are not in the data model or the source material.
 *
 * Run `yarn workspace @ulams/ui catalogue` to print this registry as JSON.
 */
import type { JsonSchema } from "./schema.ts";

export const FORMATS = [
  "video",
  "audio",
  "reading",
  "image",
  "pdf",
  "embed",
  "interactive",
  "scorm",
  "tracked",
  "quiz",
  "project",
] as const;
export type Format = (typeof FORMATS)[number];

export const THEMES = ["coffee", "oncall", "nightsky", "platform"] as const;
export type ThemeName = (typeof THEMES)[number];

export const ICONS = [
  "check",
  "arrow",
  "play",
  "clock",
  "calendar",
  "pin",
  "users",
  "mail",
  "shield",
  "star",
  "moon",
  "planet",
  "telescope",
  "rocket",
  "medal",
  "book",
  "terminal",
  "pager",
  "chart",
  "print",
  "lock",
  "sync",
  "quote",
  "layers",
  "server",
  "puzzle",
  "cart",
  "code",
] as const;
export type IconName = (typeof ICONS)[number];

const text = (description: string, extra: Partial<JsonSchema> = {}): JsonSchema => ({
  type: "string",
  description,
  maxLength: 2000,
  ...extra,
});
const href = (description: string): JsonSchema => ({ type: "string", format: "href", description, maxLength: 2048 });
const int = (description: string, extra: Partial<JsonSchema> = {}): JsonSchema => ({
  type: "integer",
  description,
  minimum: 0,
  ...extra,
});
const bool = (description: string, dflt: boolean): JsonSchema => ({ type: "boolean", description, default: dflt });
const oneOf = (values: ReadonlyArray<string>, description: string, dflt?: string): JsonSchema => ({
  type: "string",
  enum: values,
  description,
  ...(dflt !== undefined ? { default: dflt } : {}),
});
const list = (items: JsonSchema, description: string, extra: Partial<JsonSchema> = {}): JsonSchema => ({
  type: "array",
  items,
  description,
  default: [],
  maxItems: 60,
  ...extra,
});
const obj = (properties: Record<string, JsonSchema>, required: string[] = [], description?: string): JsonSchema => ({
  type: "object",
  properties,
  required,
  ...(description ? { description } : {}),
});

export const LINK: JsonSchema = obj(
  { label: text("Visible text", { maxLength: 80 }), href: href("Target URL") },
  ["label", "href"],
  "A link or call to action"
);
export const IMAGE: JsonSchema = obj(
  {
    src: href("Image URL (local /images/… or the tenant storage)"),
    alt: text("Text alternative; empty string only for decorative images", { maxLength: 300 }),
    width: int("Intrinsic width in px", { minimum: 1 }),
    height: int("Intrinsic height in px", { minimum: 1 }),
  },
  ["src", "alt", "width", "height"],
  "An image with explicit size (prevents layout shift)"
);
const FORMAT = oneOf(FORMATS, "Learning format of a topic");
const ICON = oneOf(ICONS, "Icon from the catalogue's icon set");
const TONE = oneOf(["neutral", "ok", "warn", "alert", "info"], "Semantic colour", "neutral");
const EYEBROW = text("Small label above the title", { maxLength: 80 });
const TITLE = text("Section title", { maxLength: 160 });
const INTRO = text("One or two sentences under the title", { maxLength: 600 });
const FACT = obj(
  { label: text("Label", { maxLength: 40 }), value: text("Value", { maxLength: 60 }), note: text("Small note", { maxLength: 80 }) },
  ["label", "value"]
);
const PERSON = obj(
  {
    name: text("Full name", { maxLength: 80 }),
    role: text("Role or title", { maxLength: 80 }),
    place: text("Place", { maxLength: 80 }),
    bio: text("Short bio", { maxLength: 600 }),
    image: IMAGE,
  },
  ["name"]
);
const TOPIC = obj(
  {
    id: int("Topic id from the API"),
    title: text("Topic title", { maxLength: 160 }),
    format: FORMAT,
    minutes: int("Duration in minutes"),
    preview: bool("Free preview without an account", false),
    href: href("Topic link (lesson player)"),
    status: oneOf(["open", "done", "current", "locked"], "Learner status", "open"),
  },
  ["title"]
);
const LESSON = obj(
  {
    id: int("Lesson id from the API"),
    title: text("Lesson / chapter / mission title", { maxLength: 160 }),
    kicker: text("Short label above the title", { maxLength: 80 }),
    summary: text("One sentence", { maxLength: 400 }),
    minutes: int("Total duration in minutes"),
    formats: list(FORMAT, "Formats inside, in order of first appearance"),
    topics: list(TOPIC, "Topics"),
    href: href("Link to the lesson"),
  },
  ["title"]
);

export interface ComponentSpec {
  /** What the component is for, written for the model. */
  description: string;
  category: "structure" | "section" | "course" | "learning";
  /** Whether it ships client-side JS (a web component). */
  interactive: boolean;
  /** Whether it renders `children`. */
  children: boolean;
  props: JsonSchema;
  /** Plain-text rendering, used for invalid props, unknown clients and text-only channels. */
  fallback: (props: Record<string, unknown>) => string;
}

const join = (...parts: unknown[]): string =>
  parts
    .flat()
    .filter((p) => typeof p === "string" && p.trim() !== "")
    .join("\n");
const titles = (items: unknown, key = "title"): string[] =>
  Array.isArray(items) ? items.map((i) => (i && typeof i === "object" ? String((i as Record<string, unknown>)[key] ?? "") : "")) : [];

export const registry = {
  Page: {
    description:
      "Root of a page document. Sets the theme and metadata. Children: SiteHeader, Main, SiteFooter in that order.",
    category: "structure",
    interactive: false,
    children: true,
    props: obj(
      {
        theme: oneOf(THEMES, "Tenant theme preset"),
        title: text("Document title for the browser tab and search results", { maxLength: 70 }),
        description: text("Meta description, under 160 characters", { maxLength: 160 }),
        lang: text("BCP 47 language of the page", { default: "en", maxLength: 10 }),
      },
      ["theme", "title"]
    ),
    fallback: (p) => join(p.title, p.description),
  },
  Main: {
    description: "The page's main landmark. Put every section between the header and the footer inside it.",
    category: "structure",
    interactive: false,
    children: true,
    props: obj({}),
    fallback: () => "",
  },
  Stack: {
    description: "Vertical group of components with consistent spacing (e.g. the body of a lesson).",
    category: "structure",
    interactive: false,
    children: true,
    props: obj({ gap: oneOf(["sm", "md", "lg"], "Space between children", "md") }),
    fallback: () => "",
  },
  SiteHeader: {
    description: "Top navigation bar with the brand, up to six links and one call to action. Collapses to a menu on phones.",
    category: "structure",
    interactive: false,
    children: false,
    props: obj(
      {
        brand: text("Brand name", { maxLength: 60 }),
        tagline: text("Small line under the brand", { maxLength: 60 }),
        mark: oneOf(["wordmark", "terminal", "rocket", "logo"], "Brand mark style; logo = the ulams mark", "wordmark"),
        links: list(LINK, "Navigation links", { maxItems: 6 }),
        cta: LINK,
        signIn: LINK,
      },
      ["brand"]
    ),
    fallback: (p) => String(p.brand ?? ""),
  },
  StatusBar: {
    description:
      "Thin strip of short facts above or below the header: an editorial ribbon or a status-page line (coloured dots by tone).",
    category: "structure",
    interactive: false,
    children: false,
    props: obj(
      {
        variant: oneOf(["ribbon", "status"], "ribbon = editorial small caps, status = monospace with status dots", "ribbon"),
        items: list(obj({ label: text("Text", { maxLength: 80 }), tone: TONE }, ["label"]), "Items", { maxItems: 6 }),
      },
      ["items"]
    ),
    fallback: (p) => titles(p.items, "label").join(" · "),
  },
  Hero: {
    description:
      "First screen of a landing page. editorial = magazine cover with a photo; console = dark split with a log panel; adventure = playful with an illustration.",
    category: "section",
    interactive: false,
    children: false,
    props: obj(
      {
        variant: oneOf(["editorial", "console", "adventure", "product"], "Layout", "editorial"),
        eyebrow: EYEBROW,
        title: text("Headline (the LCP element: keep it short)", { maxLength: 90 }),
        titleAccent: text("Part of the headline to emphasise; must appear in the title", { maxLength: 60 }),
        subtitle: text("Second line under the headline", { maxLength: 160 }),
        body: text("Paragraph", { maxLength: 500 }),
        primaryCta: LINK,
        secondaryCta: LINK,
        image: IMAGE,
        caption: text("Image caption", { maxLength: 160 }),
        illustration: oneOf(["none", "orbi", "planets"], "Inline illustration (adventure variant)", "none"),
        facts: list(FACT, "Up to four facts shown under the call to action", { maxItems: 4 }),
        panel: obj(
          {
            title: text("Panel title", { maxLength: 80 }),
            note: text("Small note under the panel, e.g. 'Example drill replay'", { maxLength: 120 }),
            lines: list(
              obj(
                {
                  time: text("Timestamp", { maxLength: 12 }),
                  tag: text("Tag", { maxLength: 20 }),
                  text: text("Line", { maxLength: 200 }),
                  tone: TONE,
                },
                ["text"]
              ),
              "Log lines",
              { maxItems: 8 }
            ),
          },
          ["lines"],
          "Log-style panel for the console variant"
        ),
        diff: obj(
          {
            title: text("Card title, e.g. the lesson being updated", { maxLength: 80 }),
            source: text("Source the change comes from, e.g. docs/slo.md", { maxLength: 80 }),
            note: text("Small note under the card", { maxLength: 160 }),
            lines: list(
              obj({ op: oneOf(["add", "del", "ctx"], "Diff line kind", "ctx"), text: text("Line", { maxLength: 160 }) }, ["text"]),
              "Diff lines",
              { maxItems: 8 }
            ),
            citations: list(text("Citation chip, e.g. docs/slo.md §2", { maxLength: 40 }), "Citations", { maxItems: 4 }),
            action: text("Label of the approve button", { maxLength: 30 }),
          },
          ["lines"],
          "Update-proposal card for the product variant"
        ),
      },
      ["title"]
    ),
    fallback: (p) => join(p.eyebrow, p.title, p.subtitle, p.body),
  },
  Syllabus: {
    description:
      "Course program. folio = magazine table of contents with roman numerals; timeline = horizontal modules with week labels; missions = winding adventure path.",
    category: "course",
    interactive: false,
    children: false,
    props: obj(
      {
        variant: oneOf(["folio", "timeline", "missions"], "Layout", "folio"),
        id: text("Anchor id for in-page links", { default: "syllabus", maxLength: 40 }),
        eyebrow: EYEBROW,
        title: TITLE,
        intro: INTRO,
        lessons: list(LESSON, "Lessons in order", { minItems: 1 }),
        showTopics: bool("List topics under each lesson", true),
        totalLabel: text("Summary label, e.g. '6 chapters · 18 topics'", { maxLength: 80 }),
        cta: LINK,
      },
      ["lessons"]
    ),
    fallback: (p) => join(p.title, ...titles(p.lessons)),
  },
  Letter: {
    description: "Personal letter from the tutors with portraits, paragraphs and a pull quote (editorial).",
    category: "section",
    interactive: false,
    children: false,
    props: obj(
      {
        eyebrow: EYEBROW,
        title: TITLE,
        paragraphs: list(text("Paragraph", { maxLength: 1200 }), "Paragraphs", { maxItems: 6 }),
        quote: text("Pull quote", { maxLength: 400 }),
        people: list(PERSON, "Signatories", { maxItems: 3 }),
      },
      ["title", "paragraphs"]
    ),
    fallback: (p) => join(p.title, ...(Array.isArray(p.paragraphs) ? (p.paragraphs as string[]) : []), p.quote),
  },
  People: {
    description: "Instructor cards with portrait, role and a short bio.",
    category: "section",
    interactive: false,
    children: false,
    props: obj(
      {
        eyebrow: EYEBROW,
        title: TITLE,
        intro: INTRO,
        people: list(PERSON, "People", { minItems: 1, maxItems: 8 }),
      },
      ["people"]
    ),
    fallback: (p) => join(p.title, ...titles(p.people, "name")),
  },
  FormatGrid: {
    description: "Grid of the learning formats inside the course, one card per format with a short example.",
    category: "section",
    interactive: false,
    children: false,
    props: obj(
      {
        eyebrow: EYEBROW,
        title: TITLE,
        intro: INTRO,
        items: list(
          obj(
            {
              format: FORMAT,
              tag: text("Card label", { maxLength: 30 }),
              name: text("Example title", { maxLength: 80 }),
              text: text("One sentence", { maxLength: 240 }),
              foot: text("Small footer", { maxLength: 60 }),
            },
            ["format", "name"]
          ),
          "Cards",
          { maxItems: 12 }
        ),
      },
      ["items"]
    ),
    fallback: (p) => join(p.title, ...titles(p.items, "name")),
  },
  Quotes: {
    description:
      "Testimonials. Only quote people from the data model or source material, never invent them. pull = large serif quote; log = timestamped log lines; bubbles = playful cards.",
    category: "section",
    interactive: false,
    children: false,
    props: obj(
      {
        variant: oneOf(["pull", "log", "bubbles"], "Layout", "pull"),
        eyebrow: EYEBROW,
        title: TITLE,
        items: list(
          obj({ quote: text("Quote", { maxLength: 400 }), author: text("Who said it", { maxLength: 120 }) }, ["quote", "author"]),
          "Quotes",
          { maxItems: 8 }
        ),
      },
      ["items"]
    ),
    fallback: (p) => join(p.title, ...titles(p.items, "quote")),
  },
  Events: {
    description: "Upcoming live sessions (webinars) and in-person events. Dates come from the data model.",
    category: "section",
    interactive: false,
    children: false,
    props: obj(
      {
        variant: oneOf(["feature", "cards"], "feature = one large event with an image; cards = a row of cards", "cards"),
        eyebrow: EYEBROW,
        title: TITLE,
        intro: INTRO,
        image: IMAGE,
        caption: text("Image caption", { maxLength: 160 }),
        items: list(
          obj(
            {
              kind: oneOf(["webinar", "in-person", "consultation"], "Kind of event", "webinar"),
              title: text("Event title", { maxLength: 120 }),
              date: text("ISO 8601 start", { format: "date-time", maxLength: 40 }),
              place: text("Place (in-person)", { maxLength: 120 }),
              text: text("One or two sentences", { maxLength: 400 }),
              cta: LINK,
            },
            ["title"]
          ),
          "Events",
          { maxItems: 4 }
        ),
      },
      ["items"]
    ),
    fallback: (p) => join(p.title, ...titles(p.items)),
  },
  Pricing: {
    description: "Plans side by side. Prices come preformatted from the data model (products); never invent a price.",
    category: "section",
    interactive: false,
    children: false,
    props: obj(
      {
        id: text("Anchor id", { default: "pricing", maxLength: 40 }),
        eyebrow: EYEBROW,
        title: TITLE,
        intro: INTRO,
        plans: list(
          obj(
            {
              name: text("Plan name", { maxLength: 80 }),
              label: text("Small label above the name", { maxLength: 40 }),
              price: text("Formatted price, e.g. €89", { maxLength: 20 }),
              period: text("e.g. one-off, per month", { maxLength: 30 }),
              description: text("One sentence", { maxLength: 300 }),
              items: list(text("Included item", { maxLength: 120 }), "What is included", { maxItems: 10 }),
              cta: LINK,
              highlighted: bool("Visually recommended plan", false),
            },
            ["name"]
          ),
          "Plans",
          { minItems: 1, maxItems: 4 }
        ),
        note: text("Small print under the plans", { maxLength: 200 }),
      },
      ["plans"]
    ),
    fallback: (p) => join(p.title, ...titles(p.plans, "name")),
  },
  FeatureList: {
    description: "A short list of benefits or facts with icons (e.g. 'For parents', 'For teams').",
    category: "section",
    interactive: false,
    children: false,
    props: obj(
      {
        variant: oneOf(["tiles", "checks", "grid"], "tiles = icon cards; checks = compact checklist; grid = 3-column feature grid with glow", "tiles"),
        eyebrow: EYEBROW,
        title: TITLE,
        intro: INTRO,
        items: list(
          obj(
            {
              icon: ICON,
              title: text("Title", { maxLength: 80 }),
              text: text("Sentence", { maxLength: 240 }),
              status: oneOf(["available", "coming"], "Shipped today or on the roadmap (say so honestly)", "available"),
            },
            ["title"]
          ),
          "Items",
          { maxItems: 8 }
        ),
        cta: LINK,
      },
      ["items"]
    ),
    fallback: (p) => join(p.title, ...titles(p.items)),
  },
  Badges: {
    description: "Rewards the learner earns (gamified courses).",
    category: "section",
    interactive: false,
    children: false,
    props: obj(
      {
        eyebrow: EYEBROW,
        title: TITLE,
        intro: INTRO,
        items: list(obj({ name: text("Badge", { maxLength: 60 }), text: text("How to earn it", { maxLength: 160 }), icon: ICON }, ["name"]), "Badges", {
          maxItems: 8,
        }),
      },
      ["items"]
    ),
    fallback: (p) => join(p.title, ...titles(p.items, "name")),
  },
  Faq: {
    description: "Questions and answers as an accessible disclosure list (no JavaScript).",
    category: "section",
    interactive: false,
    children: false,
    props: obj(
      {
        id: text("Anchor id", { default: "faq", maxLength: 40 }),
        eyebrow: EYEBROW,
        title: TITLE,
        items: list(obj({ q: text("Question", { maxLength: 200 }), a: text("Answer", { maxLength: 1000 }) }, ["q", "a"]), "Questions", {
          minItems: 1,
          maxItems: 12,
        }),
      },
      ["items"]
    ),
    fallback: (p) => join(p.title, ...titles(p.items, "q")),
  },
  CtaBand: {
    description: "Closing call to action: a banner or a newsletter-style band.",
    category: "section",
    interactive: false,
    children: false,
    props: obj(
      {
        variant: oneOf(["banner", "newsletter", "terminal"], "Style; terminal shows `code` lines in a terminal window", "banner"),
        eyebrow: EYEBROW,
        title: TITLE,
        text: INTRO,
        code: list(text("Command or output line", { maxLength: 120 }), "Terminal lines (terminal variant); lines starting with $ are commands", { maxItems: 10 }),
        cta: LINK,
        secondary: LINK,
      },
      ["title", "cta"]
    ),
    fallback: (p) => join(p.title, p.text),
  },
  Steps: {
    description: "A numbered process in 3–5 steps with a connector line (how something works).",
    category: "section",
    interactive: false,
    children: false,
    props: obj(
      {
        eyebrow: EYEBROW,
        title: TITLE,
        intro: INTRO,
        status: oneOf(["available", "coming"], "Whether the whole process ships today", "available"),
        items: list(obj({ icon: ICON, title: text("Step", { maxLength: 80 }), text: text("Sentence", { maxLength: 260 }) }, ["title"]), "Steps", {
          minItems: 2,
          maxItems: 5,
        }),
      },
      ["items"]
    ),
    fallback: (p) => join(p.title, ...titles(p.items)),
  },
  Showcase: {
    description: "Cards that each open something live (demo sites), with a colour swatch, facts and two buttons.",
    category: "section",
    interactive: false,
    children: false,
    props: obj(
      {
        eyebrow: EYEBROW,
        title: TITLE,
        intro: INTRO,
        note: text("Small note under the cards", { maxLength: 200 }),
        items: list(
          obj(
            {
              title: text("Card title", { maxLength: 80 }),
              text: text("Sentence", { maxLength: 300 }),
              theme: oneOf(THEMES, "Colour swatch of the card"),
              facts: list(obj({ label: text("Label", { maxLength: 30 }), value: text("Value", { maxLength: 40 }) }, ["label", "value"]), "Facts", {
                maxItems: 4,
              }),
              image: IMAGE,
              primary: LINK,
              secondary: LINK,
            },
            ["title", "primary"]
          ),
          "Cards",
          { minItems: 1, maxItems: 6 }
        ),
      },
      ["items"]
    ),
    fallback: (p) => join(p.title, ...titles(p.items)),
  },
  Chips: {
    description: "A strip of short labels (standards, integrations); each marked available or coming.",
    category: "section",
    interactive: false,
    children: false,
    props: obj(
      {
        eyebrow: EYEBROW,
        title: TITLE,
        intro: INTRO,
        items: list(
          obj(
            {
              label: text("Label", { maxLength: 40 }),
              note: text("Small note", { maxLength: 60 }),
              status: oneOf(["available", "coming"], "Shipped today or on the roadmap", "available"),
            },
            ["label"]
          ),
          "Chips",
          { minItems: 1, maxItems: 16 }
        ),
      },
      ["items"]
    ),
    fallback: (p) => join(p.title, ...titles(p.items, "label")),
  },
  SiteFooter: {
    description: "Page footer with brand, a sentence, links and small print.",
    category: "structure",
    interactive: false,
    children: false,
    props: obj(
      {
        brand: text("Brand name", { maxLength: 60 }),
        text: text("Sentence under the brand", { maxLength: 300 }),
        links: list(LINK, "Links", { maxItems: 10 }),
        note: text("Small print", { maxLength: 200 }),
      },
      ["brand"]
    ),
    fallback: (p) => String(p.brand ?? ""),
  },
  CourseHeader: {
    description: "Top of a course page: title, subtitle, facts, tutors, poster and the start button.",
    category: "course",
    interactive: false,
    children: false,
    props: obj(
      {
        variant: oneOf(["editorial", "console", "adventure"], "Layout", "editorial"),
        eyebrow: EYEBROW,
        title: text("Course title", { maxLength: 160 }),
        subtitle: text("Subtitle", { maxLength: 200 }),
        summary: text("Summary", { maxLength: 600 }),
        facts: list(FACT, "Facts (level, length, language…)", { maxItems: 6 }),
        image: IMAGE,
        tutors: list(PERSON, "Tutors", { maxItems: 4 }),
        primaryCta: LINK,
        secondaryCta: LINK,
        progress: int("Learner completion 0–100; omit when unknown", { maximum: 100 }),
      },
      ["title"]
    ),
    fallback: (p) => join(p.title, p.subtitle, p.summary),
  },
  PurchaseCard: {
    description: "Sticky side card with the price, what is included and the start button.",
    category: "course",
    interactive: false,
    children: false,
    props: obj(
      {
        price: text("Formatted price", { maxLength: 20 }),
        priceNote: text("e.g. one-off payment", { maxLength: 60 }),
        title: text("Card title", { maxLength: 80 }),
        items: list(text("Included item", { maxLength: 120 }), "What is included", { maxItems: 8 }),
        cta: LINK,
        note: text("Small print", { maxLength: 200 }),
      },
      ["cta"]
    ),
    fallback: (p) => join(p.title, p.price),
  },
  Prose: {
    description:
      "Long-form Markdown (GFM tables, block quotes, $$ math $$). Raw HTML is escaped. Use for articles, briefs and descriptions.",
    category: "learning",
    interactive: false,
    children: false,
    props: obj(
      {
        markdown: text("Markdown source", { maxLength: 60000 }),
        dropCap: bool("Editorial drop cap on the first paragraph", false),
        size: oneOf(["sm", "md", "lg"], "Text size", "md"),
      },
      ["markdown"]
    ),
    fallback: (p) => String(p.markdown ?? ""),
  },
  Callout: {
    description: "Highlighted note inside a lesson: a tip, a warning or a key idea.",
    category: "learning",
    interactive: false,
    children: false,
    props: obj(
      {
        tone: oneOf(["tip", "warning", "key"], "Kind of note", "tip"),
        title: text("Title", { maxLength: 120 }),
        text: text("Text", { maxLength: 1000 }),
      },
      ["text"]
    ),
    fallback: (p) => join(p.title, p.text),
  },
  Figure: {
    description: "Image with caption, click to zoom.",
    category: "learning",
    interactive: false,
    children: false,
    props: obj({ image: IMAGE, caption: text("Caption", { maxLength: 300 }) }, ["image"]),
    fallback: (p) => String(p.caption ?? (p.image as { alt?: string } | undefined)?.alt ?? ""),
  },
  VideoPlayer: {
    description: "Video with poster, chapters and transcript. HLS streams load a player only when played.",
    category: "learning",
    interactive: true,
    children: false,
    props: obj(
      {
        src: href("Video URL (.mp4 or .m3u8)"),
        poster: href("Poster image URL"),
        width: int("Width in px", { default: 1280, minimum: 1 }),
        height: int("Height in px", { default: 720, minimum: 1 }),
        title: text("Accessible title", { maxLength: 160 }),
        chapters: list(obj({ time: int("Start in seconds"), title: text("Chapter", { maxLength: 120 }) }, ["time", "title"]), "Chapters", {
          maxItems: 40,
        }),
      },
      ["src", "title"]
    ),
    fallback: (p) => `Video: ${String(p.title ?? "")}`,
  },
  AudioPlayer: {
    description: "Audio (podcast, recording) with an accessible native player.",
    category: "learning",
    interactive: false,
    children: false,
    props: obj({ src: href("Audio URL"), title: text("Title", { maxLength: 160 }), seconds: int("Length in seconds") }, ["src", "title"]),
    fallback: (p) => `Audio: ${String(p.title ?? "")}`,
  },
  PdfViewer: {
    description: "Embedded PDF with open and download links.",
    category: "learning",
    interactive: false,
    children: false,
    props: obj({ src: href("PDF URL"), title: text("Title", { maxLength: 160 }) }, ["src", "title"]),
    fallback: (p) => `PDF: ${String(p.title ?? "")} ${String(p.src ?? "")}`,
  },
  Embed: {
    description: "External media by URL (YouTube, Vimeo); other URLs become a link card.",
    category: "learning",
    interactive: false,
    children: false,
    props: obj({ url: href("Media URL"), title: text("Title", { maxLength: 160 }) }, ["url", "title"]),
    fallback: (p) => `${String(p.title ?? "")}: ${String(p.url ?? "")}`,
  },
  H5PFrame: {
    description: "Interactive H5P content from the H5P service, framed. Reports completion to progress.",
    category: "learning",
    interactive: true,
    children: false,
    props: obj(
      {
        apiUrl: href("Tenant API origin (the H5P service is under /h5p)"),
        contentId: int("H5P content id"),
        title: text("Title", { maxLength: 160 }),
        topicId: int("Topic id for progress"),
        courseId: int("Course id for progress"),
      },
      ["apiUrl", "contentId", "title"]
    ),
    fallback: (p) => `Interactive: ${String(p.title ?? "")}`,
  },
  PackageFrame: {
    description: "A packaged activity (SCORM) in a frame, with a link to open it full screen.",
    category: "learning",
    interactive: false,
    children: false,
    props: obj({ src: href("Player URL"), title: text("Title", { maxLength: 160 }), height: int("Frame height px", { default: 640 }) }, ["src", "title"]),
    fallback: (p) => `${String(p.title ?? "")}: ${String(p.src ?? "")}`,
  },
  ActivityCard: {
    description: "Launch card for an activity that runs outside the page (cmi5 tracked activity, project hand-in).",
    category: "learning",
    interactive: false,
    children: false,
    props: obj(
      {
        kind: oneOf(["tracked", "project", "locked"], "Kind of activity", "tracked"),
        title: text("Title", { maxLength: 160 }),
        text: text("What happens", { maxLength: 600 }),
        steps: list(text("Step", { maxLength: 200 }), "Checklist", { maxItems: 8 }),
        cta: LINK,
      },
      ["title"]
    ),
    fallback: (p) => join(p.title, p.text),
  },
  QuizRunner: {
    description:
      "GIFT quiz through the quiz-attempts API: one question at a time (8 question types), timer, score and review.",
    category: "learning",
    interactive: true,
    children: false,
    props: obj(
      {
        quizId: int("topic_gift_quiz id", { minimum: 1 }),
        title: text("Quiz title", { maxLength: 160 }),
        intro: text("Intro text", { maxLength: 1000 }),
        maxAttempts: int("Attempts allowed (0 = unlimited)"),
        minutes: int("Time limit in minutes (0 = none)"),
        passScore: int("Points needed to pass"),
        courseId: int("Course id for progress"),
        topicId: int("Topic id for progress"),
        finishHref: href("Where 'Continue' goes after the result"),
      },
      ["quizId", "title"]
    ),
    fallback: (p) => `Quiz: ${String(p.title ?? "")}`,
  },
} as const satisfies Record<string, ComponentSpec>;

// Every section accepts an optional anchor id (for in-page links such as "#pricing").
for (const spec of Object.values(registry) as ComponentSpec[]) {
  const props = spec.props.properties;
  if (props && spec.category !== "structure" && !props.id) {
    props.id = text("Anchor id for in-page links (letters, digits, dashes)", { maxLength: 40 });
  }
}

export type ComponentName = keyof typeof registry;

export const componentNames = Object.keys(registry) as ComponentName[];

export const isComponentName = (name: unknown): name is ComponentName =>
  typeof name === "string" && Object.prototype.hasOwnProperty.call(registry, name);

/** The catalogue as plain JSON (for prompts and tools): name → { description, props schema, … }. */
export function catalogueJson(): Record<string, Omit<ComponentSpec, "fallback">> {
  return Object.fromEntries(
    Object.entries(registry).map(([name, spec]) => [
      name,
      { description: spec.description, category: spec.category, interactive: spec.interactive, children: spec.children, props: spec.props },
    ])
  );
}
