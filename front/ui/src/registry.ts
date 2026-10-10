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
import {
  COURSE_UPDATES_TITLE,
  extendedText,
  pendingNoticeText,
  REATTEMPT_LINK,
  REATTEMPT_TEXT,
  REATTEMPT_TITLE,
  RETIRED_TEXT,
  updateNoticeText,
} from "./lib/notices.ts";

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

export const THEMES = ["coffee", "oncall", "nightsky", "gravity", "poland", "ulam", "platform"] as const;
export type ThemeName = (typeof THEMES)[number];
export { THEME_PRESETS, TENANT_THEMES } from "./theme/presets.ts";

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
  "compass",
  "sigma",
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
  "sparkle",
  "eye",
  "chat",
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
export const WORKFLOW_KINDS = ["prompt", "cmd", "cont", "agent", "out", "spin", "tool", "add", "del", "ctx", "note", "user", "assistant", "card"] as const;
const FORMAT = oneOf(FORMATS, "Learning format of a topic");
const ICON = oneOf(ICONS, "Icon from the catalogue's icon set");
/** Steps of an interactive package with their text alternatives (InteractiveLesson, the Hero showcase). */
const IX_STEPS = list(
  obj(
    {
      id: text("Step id", { maxLength: 64 }),
      title: text("Step title", { maxLength: 255 }),
      text: text("Text alternative of the step", { maxLength: 4000 }),
      poster: href("Poster image of the step"),
    },
    ["id", "title", "text"]
  ),
  "Steps with their text alternatives and posters",
  { minItems: 1, maxItems: 200 }
);

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
const str = (value: unknown): string | null => (typeof value === "string" && value !== "" ? value : null);
const DATE = text("ISO 8601 date", { format: "date-time", maxLength: 40 });
const LESSON_TITLE = text("Lesson title", { maxLength: 200 });
const UPDATED_ROW: JsonSchema = obj({ title: LESSON_TITLE, href: href("Lesson link"), date: DATE }, ["title", "href"]);
const RETIRED_ROW: JsonSchema = obj({ title: LESSON_TITLE, date: DATE }, ["title"]);
const EXTENDED_ROW: JsonSchema = obj({ title: LESSON_TITLE, href: href("Lesson link") }, ["title", "href"]);
const PENDING_ROW: JsonSchema = obj({ title: LESSON_TITLE, href: href("Lesson link"), since: DATE }, ["title", "href"]);
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
        mark: oneOf(["wordmark", "terminal", "rocket", "orbit", "compass", "sigma", "logo"], "Brand mark style; logo = the ulams mark", "wordmark"),
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
      "First screen of a landing page. editorial = magazine cover with a photo; console = dark split with a log panel; adventure = playful with an illustration; cosmos, atlas and notebook = copy on the left and a live interactive package (or a drawing of orbits, a map graticule or an Ulam spiral) on the right.",
    category: "section",
    interactive: false,
    children: false,
    props: obj(
      {
        variant: oneOf(["editorial", "console", "adventure", "product", "cosmos", "atlas", "notebook"], "Layout", "editorial"),
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
            prompt: text("Command shown at the bottom of the panel, without the $", { maxLength: 60 }),
            values: list({ type: "number", minimum: 0, description: "Value" }, "Series drawn as the sparkline (e.g. minutes per module); omit for none", {
              maxItems: 24,
            }),
            valuesLabel: text("What the sparkline shows, e.g. 'minutes per module'", { maxLength: 60 }),
          },
          ["lines"],
          "Log-style panel for the console variant"
        ),
        capabilities: list(
          obj(
            {
              icon: ICON,
              label: text("Short label of the capability (2 to 4 words)", { maxLength: 32 }),
              caption: text("One line shown under the orbit while this capability is highlighted", { maxLength: 90 }),
              href: href("In-page anchor of the section that shows it, e.g. #living"),
              ring: int("Orbit the card travels on, 1 = inner", { minimum: 1, maximum: 3, default: 1 }),
              angle: int("Start angle on the orbit in degrees (0 = right, 90 = below); spreads the cards evenly when omitted", { maximum: 359 }),
              status: oneOf(["available", "preview", "coming"], "Roadmap status; shown as a Coming marker only in the actual landing mode", "available"),
            },
            ["label", "caption"]
          ),
          "Capability orbit for the product variant: cards on up to three orbits around the logo, one highlighted at a time",
          { maxItems: 12 }
        ),
        showcase: obj(
          {
            src: href("Entry file of the interactive package on the content origin"),
            title: text("Title of the frame", { maxLength: 160 }),
            steps: IX_STEPS,
            height: int("Frame height px", { default: 440, minimum: 240, maximum: 1000 }),
            startStep: text("First step of the range", { maxLength: 64 }),
            endStep: text("Last step of the range", { maxLength: 64 }),
            locale: text("Language of the step texts", { maxLength: 16, default: "en" }),
            requires: list(oneOf(["webgl"], "Browser capability the package needs"), "Capabilities the package needs", { maxItems: 5 }),
            reducedMotionSupported: bool("The package handles reduced motion itself", false),
            licence: text("SPDX licence id", { maxLength: 64 }),
            attribution: text("Attribution line", { maxLength: 1000 }),
            sourceUrl: href("Source code or origin of the package"),
          },
          ["src", "title", "steps"],
          "A live interactive package for the cosmos, atlas and notebook variants; without it the variant draws its own picture. Nothing is tracked."
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
    fallback: (p) => join(p.eyebrow, p.title, p.subtitle, p.body, titles(p.capabilities, "label").join(" · ")),
  },
  Syllabus: {
    description:
      "Course program, laid out as a contents page, a timeline, a mission path, orbits or an atlas. folio = magazine table of contents with roman numerals; timeline = horizontal modules with week labels; missions = winding adventure path; orbits = modules on concentric rings (a list on phones); atlas = atlas table of contents with chapter numbers.",
    category: "course",
    interactive: false,
    children: false,
    props: obj(
      {
        variant: oneOf(["folio", "timeline", "missions", "orbits", "atlas"], "Layout", "folio"),
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
  WorkflowShowcase: {
    description:
      "Tabs of realistic windows (a terminal, an agent session, a chat with tool calls, an API call, the studio) whose commands and answers are typed line by line, to show that the product can be run from agents and the command line as well as the UI. Each tab may carry a status badge; unbuilt interfaces must be labelled.",
    category: "section",
    interactive: true,
    children: false,
    props: obj(
      {
        eyebrow: EYEBROW,
        title: TITLE,
        intro: INTRO,
        valueLine: text("One line of value copy under the tabs", { maxLength: 200 }),
        description: text("Text alternative of the whole animation for screen readers", { maxLength: 600 }),
        footnote: text("Small print under the tabs, e.g. that some commands are the planned interface", { maxLength: 300 }),
        legal: text("Trademark line, e.g. that product names belong to their owners and are not endorsements", { maxLength: 300 }),
        tabs: list(
          obj(
            {
              key: text("Stable key (letters, digits, dashes)", { maxLength: 24 }),
              label: text("Tab label", { maxLength: 24 }),
              status: oneOf(["available", "preview", "coming"], "Status badge; omit when everything shown exists"),
              window: oneOf(["terminal", "chat", "studio"], "Look of the window frame", "terminal"),
              title: text("Window title", { maxLength: 60 }),
              caption: text("One line under the window", { maxLength: 200 }),
              note: text("Honest note under the caption, e.g. what the example depends on", { maxLength: 200 }),
              lines: list(
                obj(
                  {
                    kind: oneOf(WORKFLOW_KINDS, "Line type: typed (prompt, cmd, cont, agent, user, assistant) or shown whole"),
                    text: text("The line", { maxLength: 400 }),
                    args: text("Tool arguments, or the buttons of a card separated by |", { maxLength: 200 }),
                    result: text("Short tool result shown after the call", { maxLength: 120 }),
                  },
                  ["kind", "text"]
                ),
                "Lines, in order",
                { minItems: 1, maxItems: 30 }
              ),
            },
            ["key", "label", "title", "lines"]
          ),
          "Tabs",
          { minItems: 1, maxItems: 6 }
        ),
      },
      ["tabs"]
    ),
    fallback: (p) => join(p.title, p.intro, ...titles(p.tabs, "label")),
  },
  LivingCourseStory: {
    description:
      "A looping three-beat animation: a source document changes, ulams finds every lesson and quiz question that cites it, and an approved proposal updates the lesson while learner progress is kept. Example values only.",
    category: "section",
    interactive: true,
    children: false,
    props: obj(
      {
        eyebrow: EYEBROW,
        title: TITLE,
        intro: INTRO,
        status: oneOf(["available", "coming"], "Roadmap label under the heading; omit when the feature exists"),
        valueLine: text("One line of value copy under the animation", { maxLength: 200 }),
        description: text("Text alternative of the whole animation for screen readers", { maxLength: 600 }),
        beats: list(obj({ title: text("Beat title", { maxLength: 60 }), text: text("One-line caption", { maxLength: 140 }) }, ["title", "text"]), "Exactly three beats", {
          minItems: 3,
          maxItems: 3,
        }),
        source: obj(
          {
            file: text("Source file name", { maxLength: 60 }),
            heading: text("Heading line of the source", { maxLength: 80 }),
            context: text("An unchanged line", { maxLength: 120 }),
            lead: text("Text of the edited line before the value", { maxLength: 80 }),
            before: text("Old value", { maxLength: 24 }),
            after: text("New value", { maxLength: 24 }),
            added: text("A new line typed after the edit", { maxLength: 120 }),
          },
          ["file", "lead", "before", "after", "added"]
        ),
        lesson: obj(
          {
            title: text("Lesson title", { maxLength: 80 }),
            lead: text("Lesson sentence before the value", { maxLength: 120 }),
            citation: text("Citation chip of the sentence", { maxLength: 40 }),
            question: text("Quiz question", { maxLength: 160 }),
            questionCite: text("Citation chip of the question", { maxLength: 40 }),
          },
          ["title", "lead", "citation", "question", "questionCite"]
        ),
        proposal: obj(
          { title: text("Proposal card title", { maxLength: 80 }), approve: text("Approve button", { maxLength: 24 }), done: text("Badge after approval", { maxLength: 60 }) },
          ["title", "approve", "done"]
        ),
      },
      ["description", "beats", "source", "lesson", "proposal"]
    ),
    fallback: (p) => join(p.title, p.intro, p.description),
  },
  BuilderStory: {
    description:
      "A looping animation of the studio course builder: a document drops in, interview answers appear, an outline grows with a source chip per lesson, a lesson streams in with citations, a quiz question cites its source, a running cost ticks (example values) and Apply publishes the course.",
    category: "section",
    interactive: true,
    children: false,
    props: obj(
      {
        eyebrow: EYEBROW,
        title: TITLE,
        intro: INTRO,
        valueLine: text("One line of value copy under the animation", { maxLength: 200 }),
        description: text("Text alternative of the whole animation for screen readers", { maxLength: 600 }),
        windowTitle: text("Window title", { maxLength: 60 }),
        file: obj({ name: text("File name", { maxLength: 60 }), label: text("Small label", { maxLength: 30 }) }, ["name"]),
        interview: list(obj({ question: text("Question", { maxLength: 30 }), answer: text("Answer", { maxLength: 30 }) }, ["question", "answer"]), "Interview answers", {
          minItems: 1,
          maxItems: 4,
        }),
        outline: list(
          obj(
            {
              title: text("Module title", { maxLength: 60 }),
              lessons: list(obj({ title: text("Lesson title", { maxLength: 60 }), source: text("Source chip", { maxLength: 30 }) }, ["title", "source"]), "Lessons", {
                minItems: 1,
                maxItems: 4,
              }),
            },
            ["title", "lessons"]
          ),
          "Modules",
          { minItems: 1, maxItems: 4 }
        ),
        lesson: obj(
          {
            title: text("Lesson shown streaming in", { maxLength: 60 }),
            text: text("Its text", { maxLength: 240 }),
            citations: list(text("Citation chip", { maxLength: 30 }), "Citation chips", { maxItems: 3 }),
          },
          ["title", "text"]
        ),
        quiz: obj({ question: text("Quiz question", { maxLength: 120 }), source: text("Source chip", { maxLength: 30 }) }, ["question", "source"]),
        cost: obj(
          { label: text("Counter label", { maxLength: 30 }), amount: { type: "number", description: "Final amount shown (an example)", minimum: 0 }, note: text("Note, e.g. 'example run'", { maxLength: 60 }) },
          ["label", "amount"]
        ),
        apply: obj(
          { label: text("Apply button", { maxLength: 24 }), publishedTitle: text("Published course title", { maxLength: 60 }), publishedMeta: text("Facts after the title", { maxLength: 80 }) },
          ["label", "publishedTitle", "publishedMeta"]
        ),
      },
      ["description", "file", "interview", "outline", "lesson", "quiz", "cost", "apply"]
    ),
    fallback: (p) => join(p.title, p.intro, p.description),
  },
  WhiteLabelStory: {
    description:
      "The white-label story of the product landing in four animated steps (an agent with the ulams CLI and a design tool's MCP reads a brand and applies its tokens, or builds a custom front on the headless API; get a themed site on its own subdomain, bring content and get one course per source, see per-course learner analytics), with a short numbered list under the stage. Every name, colour and bar in the stage is an example. Mark steps and sources that are not shipped with status 'coming'.",
    category: "section",
    interactive: true,
    children: false,
    props: obj(
      {
        id: text("Anchor id for in-page links", { default: "white-label", maxLength: 40 }),
        eyebrow: EYEBROW,
        title: TITLE,
        intro: INTRO,
        valueLine: text("One line of value copy under the list", { maxLength: 200 }),
        description: text("Text alternative of the whole animation for screen readers", { maxLength: 1400 }),
        exampleNote: text("Small label on the dashboard, e.g. 'Example data'", { maxLength: 40 }),
        primaryCta: LINK,
        secondaryCta: LINK,
        steps: list(
          obj(
            {
              title: text("Step title", { maxLength: 40 }),
              text: text("One or two sentences", { maxLength: 240 }),
              points: list(text("Short point", { maxLength: 80 }), "Up to four short points", { maxItems: 4 }),
              status: oneOf(["available", "coming"], "Shipped today or on the roadmap; shown as Coming only in actual mode", "available"),
              today: text("What exists today; shown only in actual mode", { maxLength: 200 }),
            },
            ["title", "text"]
          ),
          "The four steps, in order",
          { minItems: 4, maxItems: 4 }
        ),
        brand: obj(
          {
            sources: list(
              obj(
                {
                  kind: oneOf(["figma", "stitch", "pdf", "logo"], "Kind of brand source"),
                  label: text("What it is, e.g. 'Figma file'", { maxLength: 40 }),
                  file: text("Example file name", { maxLength: 40 }),
                  status: oneOf(["available", "coming"], "Shipped today or on the roadmap", "available"),
                },
                ["kind", "label", "file"]
              ),
              "Sources dropped in (1 to 4)",
              { minItems: 1, maxItems: 4 }
            ),
            agentLabel: text("Window title of the agent session, e.g. 'Claude Code · ulams CLI · Figma MCP'", { maxLength: 60 }),
            sourcesLabel: text("Caption over the sources", { maxLength: 40 }),
            alternative: obj({ label: text("The alternative, e.g. 'Your own front'", { maxLength: 40 }), text: text("One line", { maxLength: 100 }) }, ["label", "text"]),
            commands: list(text("A real ulams CLI command the agent runs, without the prompt", { maxLength: 80 }), "One to three commands", { minItems: 1, maxItems: 3 }),
            tokensLabel: text("Caption over the extracted tokens", { maxLength: 40 }),
            colors: list(
              obj({ name: text("Role, e.g. Primary", { maxLength: 24 }), value: text("Hex colour, #RRGGBB", { minLength: 7, maxLength: 7 }) }, ["name", "value"]),
              "Five colours in the order primary, secondary, accent, surface, text",
              { minItems: 5, maxItems: 5 }
            ),
            fonts: list(obj({ role: text("Role, e.g. Headings", { maxLength: 24 }), name: text("Font name", { maxLength: 40 }) }, ["role", "name"]), "Two fonts: headings, body", {
              minItems: 2,
              maxItems: 2,
            }),
            logo: text("Placeholder wordmark of the example brand", { maxLength: 16 }),
            radius: int("Corner radius in px", { maximum: 32 }),
          },
          ["sources", "agentLabel", "sourcesLabel", "alternative", "commands", "tokensLabel", "colors", "fonts", "logo", "radius"]
        ),
        site: obj(
          {
            host: text("Example subdomain, e.g. acme.ulams.app", { maxLength: 60 }),
            pages: list(text("Page name", { maxLength: 24 }), "Landing, catalogue, admin", { minItems: 3, maxItems: 3 }),
            headline: text("Headline on the example landing", { maxLength: 60 }),
            action: text("Button on the example landing", { maxLength: 24 }),
          },
          ["host", "pages", "headline", "action"]
        ),
        content: obj(
          {
            sources: list(
              obj(
                {
                  kind: oneOf(["doc", "pdf", "git", "url"], "Kind of content source"),
                  label: text("Source, e.g. 'handbook.pdf'", { maxLength: 40 }),
                  course: text("The course built from it", { maxLength: 40 }),
                  status: oneOf(["available", "coming"], "Shipped today or on the roadmap", "available"),
                },
                ["kind", "label", "course"]
              ),
              "Sources, each becomes its own course (1 to 4)",
              { minItems: 1, maxItems: 4 }
            ),
            builderLabel: text("Caption over the courses", { maxLength: 40 }),
            citedLabel: text("Chip on every course, e.g. 'cited'", { maxLength: 24 }),
            syncLabel: text("Line about keeping courses in sync", { maxLength: 80 }),
            syncStatus: oneOf(["available", "coming"], "Shipped today or on the roadmap", "available"),
          },
          ["sources", "builderLabel", "citedLabel", "syncLabel"]
        ),
        dashboard: obj(
          {
            kpis: list(obj({ label: text("Measure, e.g. Progress", { maxLength: 30 }), value: int("Bar fill, 0 to 100 (illustrative)", { maximum: 100 }) }, ["label", "value"]), "Up to three measures", {
              minItems: 1,
              maxItems: 3,
            }),
            quiz: obj(
              {
                label: text("Panel title", { maxLength: 40 }),
                bars: list(obj({ label: text("Quiz or module", { maxLength: 30 }), value: int("Bar fill, 0 to 100", { maximum: 100 }) }, ["label", "value"]), "Up to four bars", {
                  minItems: 1,
                  maxItems: 4,
                }),
              },
              ["label", "bars"]
            ),
            atRisk: obj(
              {
                label: text("Panel title", { maxLength: 40 }),
                learners: list(obj({ name: text("Anonymous label, e.g. 'Learner A'", { maxLength: 24 }), reason: text("Why, in a few words", { maxLength: 60 }) }, ["name", "reason"]), "Up to three learners", {
                  minItems: 1,
                  maxItems: 3,
                }),
              },
              ["label", "learners"]
            ),
            confusing: obj(
              {
                label: text("Panel title", { maxLength: 40 }),
                sections: list(obj({ title: text("Section", { maxLength: 40 }), level: int("How confusing, 1 to 5", { minimum: 1, maximum: 5 }) }, ["title", "level"]), "Up to three sections", {
                  minItems: 1,
                  maxItems: 3,
                }),
              },
              ["label", "sections"]
            ),
          },
          ["kpis", "quiz", "atRisk", "confusing"]
        ),
      },
      ["description", "steps", "brand", "site", "content", "dashboard"]
    ),
    fallback: (p) => join(p.title, p.intro, p.description),
  },
  ComparisonTable: {
    description:
      "Feature comparison of products in columns, features in rows. Every competitor cell must come from a sourced data file (value, note, source URL, checked date); list the sources and the 'as of' date under the table. Neutral values only.",
    category: "section",
    interactive: false,
    children: false,
    props: obj(
      {
        eyebrow: EYEBROW,
        title: TITLE,
        intro: INTRO,
        caption: text("Table caption read by screen readers (what is compared)", { maxLength: 160 }),
        asOf: text("When the facts were checked, e.g. 'October 2026'", { maxLength: 40 }),
        columns: list(
          obj(
            {
              label: text("Product name, plain text (no logos)", { maxLength: 40 }),
              note: text("Small line under the name, e.g. 'hosted SaaS'", { maxLength: 40 }),
              highlight: bool("The column of our own product", false),
            },
            ["label"]
          ),
          "Products, in column order (omit when groups is set)",
          { minItems: 0, maxItems: 8 }
        ),
        rows: list(
          obj(
            {
              label: text("Feature", { maxLength: 60 }),
              help: text("What the row means", { maxLength: 160 }),
              cells: list(
                obj(
                  {
                    value: text("Yes, No, Partial, Via plugin, Paid add-on, Not documented, Coming, or a short phrase", { maxLength: 60 }),
                    note: text("Short neutral note", { maxLength: 120 }),
                  },
                  ["value"]
                ),
                "One cell per column, same order",
                { maxItems: 8 }
              ),
            },
            ["label", "cells"]
          ),
          "Features (omit when groups is set)",
          { minItems: 0, maxItems: 24 }
        ),
        groups: list(
          obj(
            {
              label: text("Name of the group, shown on the segmented control", { maxLength: 40 }),
              caption: text("Table caption for this group", { maxLength: 160 }),
              columns: list(
          obj(
            {
              label: text("Product name, plain text (no logos)", { maxLength: 40 }),
              note: text("Small line under the name, e.g. 'hosted SaaS'", { maxLength: 40 }),
              highlight: bool("The column of our own product", false),
            },
            ["label"]
          ),
          "Products, in column order",
          { minItems: 2, maxItems: 8 }
        ),
              rows: list(
          obj(
            {
              label: text("Feature", { maxLength: 60 }),
              help: text("What the row means", { maxLength: 160 }),
              cells: list(
                obj({ value: text("Yes, No, Partial, Via plugin, Paid add-on, Not documented, Coming, or a short phrase", { maxLength: 60 }), note: text("Short neutral note", { maxLength: 120 }) }, ["value"]),
                "One cell per column, same order",
                { maxItems: 8 }
              ),
            },
            ["label", "cells"]
          ),
          "Features (omit when sections is set)",
          { minItems: 0, maxItems: 40 }
        ),
              sections: list(
          obj(
            {
              label: text("Section heading shown as a header row, e.g. 'Developer & headless'", { maxLength: 60 }),
              rows: list(
                obj(
                  {
                    label: text("Feature", { maxLength: 60 }),
                    help: text("What the row means", { maxLength: 160 }),
                    cells: list(
                      obj({ value: text("Yes, No, Partial, Via plugin, Paid add-on, Not documented, Coming, or a short phrase", { maxLength: 60 }), note: text("Short neutral note", { maxLength: 120 }) }, ["value"]),
                      "One cell per column, same order",
                      { maxItems: 8 }
                    ),
                  },
                  ["label", "cells"]
                ),
                "Features in this section",
                { minItems: 1, maxItems: 24 }
              ),
            },
            ["label", "rows"]
          ),
          "Rows grouped under header rows (each section is its own tbody with a rowgroup header)",
          { minItems: 0, maxItems: 8 }
        ),
            },
            ["label", "columns"]
          ),
          "Several tables behind a CSS-only segmented control (no JavaScript); each group lists its own products and features. Omit for a single table (columns and rows)",
          { minItems: 0, maxItems: 4 }
        ),
        sources: list(
          obj({ label: text("What the source supports, e.g. 'Moodle: SCORM, H5P'", { maxLength: 300 }), href: href("Source URL"), checked: text("Date checked", { maxLength: 20 }) }, [
            "label",
            "href",
          ]),
          "Sources for the cells",
          { maxItems: 400 }
        ),
        note: text("Small print under the table", { maxLength: 300 }),
      },
      ["caption"]
    ),
    fallback: (p) => join(p.title, p.caption),
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
  Timeline: {
    description:
      "Ordered sequence of events or stages (history, a process over time, a project plan). Use for anything where the order or the dates matter; use Steps for a short how-to on a landing page.",
    category: "learning",
    interactive: false,
    children: false,
    props: obj(
      {
        id: text("Anchor id", { default: "timeline", maxLength: 40 }),
        title: TITLE,
        intro: INTRO,
        items: list(
          obj(
            {
              label: text("When: a date, period or stage name", { maxLength: 40 }),
              title: text("What happened or happens", { maxLength: 100 }),
              text: text("One or two sentences of detail", { maxLength: 400 }),
              status: oneOf(["done", "current", "upcoming"], "Where the learner is on the line; omit for a purely historical timeline"),
            },
            ["label", "title"]
          ),
          "Entries in order, earliest first",
          { minItems: 2, maxItems: 20 }
        ),
      },
      ["items"]
    ),
    fallback: (p) => join(p.title, ...(Array.isArray(p.items) ? p.items.map((i) => `${(i as { label?: string }).label ?? ""}: ${(i as { title?: string }).title ?? ""}`) : [])),
  },
  FlipCards: {
    description:
      "Self-test cards: the learner reads the front (a term or question), thinks, then reveals the back (the definition or answer). Use for vocabulary, definitions and quick recall; not for graded questions.",
    category: "learning",
    interactive: true,
    children: false,
    props: obj(
      {
        id: text("Anchor id", { default: "cards", maxLength: 40 }),
        title: TITLE,
        intro: INTRO,
        cards: list(
          obj({ front: text("Term or question", { maxLength: 200 }), back: text("Definition or answer", { maxLength: 600 }) }, ["front", "back"]),
          "Cards",
          { minItems: 1, maxItems: 24 }
        ),
      },
      ["cards"]
    ),
    fallback: (p) => join(p.title, ...(Array.isArray(p.cards) ? p.cards.map((c) => `${(c as { front?: string }).front ?? ""} - ${(c as { back?: string }).back ?? ""}`) : [])),
  },
  CodeBlock: {
    description:
      "A code or command listing with a copy button. Plain text with the language named; no syntax colouring and no execution. Put prose around it, not inside it.",
    category: "learning",
    interactive: true,
    children: false,
    props: obj(
      {
        id: text("Anchor id", { default: "code", maxLength: 40 }),
        title: text("File name or short title shown above the code", { maxLength: 120 }),
        language: text("Language name, e.g. sql, python, bash, json", { default: "text", maxLength: 24 }),
        code: text("The code, exactly as it is to be copied", { maxLength: 8000 }),
        caption: text("One sentence under the listing", { maxLength: 300 }),
        lineNumbers: bool("Show line numbers", false),
      },
      ["code"]
    ),
    fallback: (p) => join(p.title, p.code, p.caption),
  },
  PracticeActivity: {
    description:
      "Scaffolded practice container (required for any practice in a layout): an intro, a toolbox of allowed resources, and 1-6 challenges of rising level (1 guided, 2 supported, 3 independent). Each challenge may offer hints in tiers (nudge, then pointer, then near_solution), answer options that each explain why, and a worked solution that stays hidden until the learner has made an attempt.",
    category: "learning",
    interactive: true,
    children: false,
    props: obj(
      {
        id: text("Anchor id", { default: "practice", maxLength: 40 }),
        title: TITLE,
        intro: text("What the learner will practise and why, in one or two sentences", { maxLength: 800 }),
        toolbox: list(
          obj({ label: text("Resource or tool", { maxLength: 80 }), text: text("When and how to use it", { maxLength: 300 }) }, ["label"]),
          "Resources the learner may use (formulas, glossary, earlier lesson); at least one",
          { minItems: 1, maxItems: 8 }
        ),
        challenges: list(
          obj(
            {
              id: text("Stable challenge id, unique in this activity", { maxLength: 40 }),
              level: int("1 = guided, 2 = supported, 3 = independent", { minimum: 1, maximum: 3 }),
              prompt: text("The task", { maxLength: 800 }),
              hints: list(
                obj(
                  {
                    tier: oneOf(["nudge", "pointer", "near_solution"], "nudge = a question to think about; pointer = where to look; near_solution = almost the answer"),
                    text: text("The hint", { maxLength: 400 }),
                  },
                  ["tier", "text"]
                ),
                "Hints, revealed one at a time in tier order",
                { maxItems: 3 }
              ),
              options: list(
                obj(
                  {
                    label: text("Answer option", { maxLength: 300 }),
                    correct: bool("Whether this option is right", false),
                    feedback: text("Why this option is right or wrong", { maxLength: 500 }),
                  },
                  ["label", "feedback"]
                ),
                "Answer options; leave empty for an open task the learner marks as tried",
                { maxItems: 6 }
              ),
              workedSolution: text("Full worked solution; shown only after the learner has made an attempt", { maxLength: 2000 }),
            },
            ["id", "level", "prompt", "workedSolution"]
          ),
          "Challenges in rising level",
          { minItems: 1, maxItems: 6 }
        ),
      },
      ["intro", "toolbox", "challenges"]
    ),
    // The fallback never includes hints or worked solutions: text channels cannot hold them back.
    fallback: (p) =>
      join(
        p.title,
        p.intro,
        ...(Array.isArray(p.toolbox) ? p.toolbox.map((t) => `Toolbox: ${(t as { label?: string }).label ?? ""}`) : []),
        ...(Array.isArray(p.challenges) ? p.challenges.map((c) => `Level ${(c as { level?: number }).level ?? ""}: ${(c as { prompt?: string }).prompt ?? ""}`) : [])
      ),
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
    props: obj(
      {
        src: href("Player URL"),
        title: text("Title", { maxLength: 160 }),
        height: int("Frame height px", { default: 640 }),
        isolated: bool("Third-party package on the tenant content origin: sandboxed frame, no full-screen link (the URL carries a one-off token)", false),
      },
      ["src", "title"]
    ),
    fallback: (p) => `${String(p.title ?? "")}: ${String(p.src ?? "")}`,
  },
  LiaScriptLesson: {
    description: "A LiaScript course (Markdown with quizzes, code and slides) played in a sandboxed frame on the tenant content origin; it completes itself at the last section.",
    category: "learning",
    interactive: false,
    children: false,
    props: obj(
      {
        src: href("Player URL on the content origin (from POST /api/liascript/launches/{topic})"),
        title: text("Title", { maxLength: 160 }),
        sections: int("Number of sections", { minimum: 1 }),
        height: int("Frame height px", { default: 720 }),
      },
      ["src", "title"]
    ),
    fallback: (p) => String(p.title ?? ""),
  },
  InteractiveLesson: {
    description:
      "An author-uploaded interactive package (3D scene, map, simulation) in a sandboxed frame on the tenant content origin, inline or as the background of the page, with steps, a text version and the licence. Progress goes through the learner session.",
    category: "learning",
    interactive: true,
    children: false,
    props: obj(
      {
        src: href("Entry file on the content origin (from POST /api/interactive/launches/{topic})"),
        title: text("Title", { maxLength: 160 }),
        topicId: int("Topic id for the events call (omitted in a preview: nothing is tracked)"),
        courseId: int("Course id"),
        display: oneOf(["inline", "background"], "Inline in the page, or the background of the page with the text over it", "inline"),
        height: int("Frame height px (inline only)", { default: 640, minimum: 240, maximum: 2000 }),
        startStep: text("First step of the range this topic plays", { maxLength: 64 }),
        endStep: text("Last step of the range this topic plays", { maxLength: 64 }),
        steps: IX_STEPS,
        text: text("The lesson's own explanation (Markdown)", { maxLength: 20000 }),
        requires: list(oneOf(["webgl"], "Browser capability the package needs"), "Capabilities the package needs", { maxItems: 5 }),
        reducedMotionSupported: bool("The package handles reduced motion itself", false),
        locale: text("Language of the texts", { maxLength: 16, default: "en" }),
        licence: text("SPDX licence id", { maxLength: 64 }),
        attribution: text("Attribution line", { maxLength: 1000 }),
        sourceUrl: href("Source code or origin of the package"),
      },
      ["src", "title", "steps"]
    ),
    fallback: (p) => String(p.title ?? ""),
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
  UpdateNotice: {
    description:
      "Notice on a lesson that changed after the learner completed or started it: the date, what changed (the author's note) and a 'Mark as reviewed' button. Filled by the app from the learner's own notices; it never changes progress. Authors do not place it.",
    category: "learning",
    interactive: true,
    children: false,
    props: obj({
      noticeId: int("Id of the learner notice; without it there is no 'Mark as reviewed' button", { minimum: 1 }),
      started: bool("The learner started the lesson but had not completed it", false),
      date: text("ISO 8601 date of the update", { format: "date-time", maxLength: 40 }),
      message: text("What changed (plain text, written by the course author)", { maxLength: 500 }),
    }),
    fallback: (p) => {
      const t = updateNoticeText({ started: p.started === true, date: str(p.date), message: str(p.message) });
      return join(t.title, t.change);
    },
  },
  ReattemptNotice: {
    description:
      "Notice on a quiz where one question was corrected: the previous score stays on record and the learner may retake the quiz once more. It stays until the quiz is retaken. Filled by the app; authors do not place it.",
    category: "learning",
    interactive: false,
    children: false,
    props: obj({
      quizHref: href("Where the quiz is: an in-page anchor on the quiz topic, or the topic link elsewhere"),
      linkLabel: text("Label of the link to the quiz", { maxLength: 80, default: REATTEMPT_LINK }),
    }),
    fallback: () => join(REATTEMPT_TITLE, REATTEMPT_TEXT),
  },
  PendingUpdateNotice: {
    description:
      "Opt-in marker on a lesson whose source changed and whose update is under review ('The source of this lesson changed on {date}; an update is under review'). Shown only when the course turned it on. Filled by the app; authors do not place it.",
    category: "learning",
    interactive: false,
    children: false,
    props: obj({ since: text("ISO 8601 date the source changed", { format: "date-time", maxLength: 40 }) }),
    fallback: (p) => pendingNoticeText(str(p.since)),
  },
  CourseUpdates: {
    description:
      "Summary on the course page of what changed for a returning learner: lessons updated since they completed them, retired lessons, new lessons since they finished, and lessons whose update is under review. Each entry names its status in words. Filled by the app; authors do not place it.",
    category: "learning",
    interactive: false,
    children: false,
    props: obj({
      title: text("Heading", { maxLength: 120, default: COURSE_UPDATES_TITLE }),
      updated: list(UPDATED_ROW, "Lessons updated since the learner completed them"),
      retired: list(RETIRED_ROW, "Lessons removed from the course that the learner had completed"),
      extended: list(EXTENDED_ROW, "Lessons added after the learner finished the course"),
      pending: list(PENDING_ROW, "Lessons whose source changed and whose update is under review"),
    }),
    fallback: (p) => {
      const rows = (key: string): Array<Record<string, unknown>> => (Array.isArray(p[key]) ? (p[key] as Array<Record<string, unknown>>) : []);
      return join(
        rows("updated").map((r) => `${updateNoticeText({ date: str(r.date) }).title.replace(/[.]$/, "")}: ${String(r.title ?? "")}`),
        rows("extended").map((r) => extendedText(String(r.title ?? ""))),
        rows("retired").map((r) => `${RETIRED_TEXT}: ${String(r.title ?? "")}`),
        rows("pending").map((r) => `${String(r.title ?? "")}: ${pendingNoticeText(str(r.since))}`)
      );
    },
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

/**
 * The approved set for generated learner layouts (ADR 0052): a layout topic's document may use
 * only these components. Practice must use PracticeActivity so the scaffolding slots are enforced.
 */
export const LEARNER_LAYOUT_COMPONENTS = [
  "Callout",
  "Steps",
  "ComparisonTable",
  "H5PFrame",
  "LiaScriptLesson",
  "Timeline",
  "FlipCards",
  "CodeBlock",
  "PracticeActivity",
] as const satisfies ReadonlyArray<ComponentName>;

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

export const LEARNER_CATALOGUE_ID = "https://ulams.dev/catalogue/learner/v1";

/** Standard JSON Schema reading of our subset: objects are closed (our validator rejects unknown props). */
function closed(schema: JsonSchema): JsonSchema {
  const out: JsonSchema = { ...schema };
  if (schema.properties) {
    out.properties = Object.fromEntries(Object.entries(schema.properties).map(([k, v]) => [k, closed(v)]));
  }
  if (schema.items) out.items = closed(schema.items);
  if (schema.type === "object") out.additionalProperties = false;
  return out;
}

/**
 * The approved learner-layout components as a manifest for the API (description + closed props schema),
 * written to catalogue/learner-layout-manifest.json by `yarn workspace @ulams/ui learner-manifest`.
 */
export function learnerLayoutManifest(): {
  catalogId: string;
  components: Record<string, { description: string; interactive: boolean; props: JsonSchema }>;
} {
  return {
    catalogId: LEARNER_CATALOGUE_ID,
    components: Object.fromEntries(
      LEARNER_LAYOUT_COMPONENTS.map((name) => [
        name,
        { description: registry[name].description, interactive: registry[name].interactive, props: closed(registry[name].props) },
      ])
    ),
  };
}

/**
 * Every page-catalogue component as a manifest for the API (closed props JSON Schema, whether it takes
 * children), written to catalogue/page-manifest.json by `yarn workspace @ulams/ui page-manifest`. The
 * course builder validates generated landing documents against it before publishing.
 */
export function pageManifest(): {
  catalogId: string;
  components: Record<string, { description: string; children: boolean; props: JsonSchema }>;
} {
  return {
    catalogId: "https://ulams.dev/catalogue/page/v1",
    components: Object.fromEntries(
      Object.entries(registry).map(([name, spec]) => [name, { description: spec.description, children: spec.children, props: closed(spec.props) }])
    ),
  };
}
