import type { FormatKey } from "../shared/formats";

/** Brief copy for "The Coffee Atlas" (front/docs/design/experiences.md §2), used when the API has no data yet. */
export const COFFEE = {
  brand: "The Coffee Atlas",
  courseTitleHint: "coffee atlas",
  courseTitle: "The Coffee Atlas — From Seed to Cup",
  courseSubtitle: "A field guide to specialty coffee for curious home baristas",
  hero: {
    kicker: "Volume I — A field guide",
    title: "Learn coffee the slow way.",
    sub: "Six chapters. One cherry. Your best cup.",
    summary:
      "Follow one coffee cherry from a hillside in Huila to your cup. Learn origin, processing, roasting, brewing and tasting — and leave with your own signature recipe.",
    caption:
      "Fig. 1 — Ripe cherries at first light, Huila, Colombia. Harvested at 1,750 m.",
    facts: [
      ["Level", "Beginner → Intermediate"],
      ["Length", "6 chapters · 22 topics · ~9 h"],
      ["Language", "English"],
    ] as Array<[string, string]>,
  },
  chapters: [
    {
      title: "Origins",
      blurb: "The coffee belt, altitude and why Arabica and Robusta taste worlds apart.",
      formats: ["video", "image", "reading"],
      minutes: 75,
    },
    {
      title: "The farm",
      blurb: "Voices from a cooperative, the anatomy of a cherry and four ways to process it.",
      formats: ["audio", "interactive", "reading", "embed"],
      minutes: 110,
    },
    {
      title: "Roasting",
      blurb: "Reading a roast curve, keeping a logbook and a simulator for your first batch.",
      formats: ["image", "pdf", "scorm"],
      minutes: 95,
    },
    {
      title: "Brewing",
      blurb: "Ratios and extraction, a V60 masterclass and matching grind to method.",
      formats: ["reading", "video", "interactive"],
      minutes: 100,
    },
    {
      title: "Tasting",
      blurb: "The flavour wheel, a guided cupping at home and a short tasting check.",
      formats: ["interactive", "tracked", "quiz"],
      minutes: 85,
    },
    {
      title: "Your signature cup",
      blurb: "Brew three variations, photograph them and send your recipe card for feedback.",
      formats: ["project", "reading"],
      minutes: 75,
    },
  ] as Array<{ title: string; blurb: string; formats: FormatKey[]; minutes: number }>,
  tutors: [
    { name: "Inés Duarte", role: "Q-grader, Huila, Colombia" },
    { name: "Tomasz Wierzba", role: "Roaster, Kraków" },
  ],
  letter: [
    "We met over a cupping table in Bogotá, arguing about whether a washed Caturra could taste of hibiscus. It could, and we have been comparing notes ever since.",
    "This course is the long version of that afternoon. We follow a single cherry from the hillside to your kitchen, and we stop wherever something interesting happens: at the drying beds, at first crack, at the moment your pour-over turns from sour to sweet.",
    "Take your time. Read with a cup beside you. By the last chapter you will have a recipe that is entirely your own.",
  ],
  formats: [
    { name: "Film", example: "Pour-over masterclass, 14 min", format: "video" },
    { name: "Podcast", example: "Voices from the farm, 12 min", format: "audio" },
    { name: "Interactive diagrams", example: "Anatomy of a cherry", format: "interactive" },
    { name: "Printable PDFs", example: "The roaster's logbook, 8 pages", format: "pdf" },
    { name: "Roast simulator", example: "Charge temperature to first crack", format: "scorm" },
    { name: "Tracked cupping", example: "A guided session with a scoring form", format: "tracked" },
    { name: "Quiz", example: "Tasting check, eight questions", format: "quiz" },
    { name: "Final project", example: "Your signature recipe card", format: "project" },
  ] as Array<{ name: string; example: string; format: FormatKey }>,
  quotes: [
    { text: "I finally understand why my V60 tastes sour.", who: "Maja, Gdańsk" },
    {
      text: "The roast-curve chapter is the clearest explanation I have read, and I roast for a living.",
      who: "Kuba, Wrocław",
    },
    { text: "I printed the logbook. It lives next to my grinder now.", who: "Elena, Lisbon" },
  ],
  live: {
    label: "Live session",
    title: "Live cupping with Inés",
    date: "14 Nov",
    time: "18:00 CET",
    text: "Six coffees, one table, and a Q-grader thinking aloud. Bring a spoon and two mugs; we send the list of coffees a week before.",
  },
  pricing: {
    single: {
      name: "The course",
      price: "€89",
      items: [
        "Six chapters, 22 topics, about nine hours",
        "Printable roaster's logbook",
        "Certificate: Certified Home Barista — The Coffee Atlas",
        "Lifetime access",
      ],
    },
    bundle: {
      name: "Taste Makers",
      items: [
        "Everything in the course",
        "Live cupping masterclass with Inés",
        "Recording of the session afterwards",
      ],
    },
  },
  faq: [
    {
      q: "Do I need special equipment?",
      a: "A kettle, a scale and any pour-over dripper are enough. We suggest upgrades only where they change the cup.",
    },
    {
      q: "Can I try it before paying?",
      a: "Yes. The first chapter, Origins, opens with a free film. Start it without a card.",
    },
    {
      q: "How long do I keep access?",
      a: "For as long as the course exists, including later revisions of the chapters.",
    },
    {
      q: "Is there a certificate?",
      a: "Finish the tasting check and the final project to receive “Certified Home Barista — The Coffee Atlas”.",
    },
    {
      q: "Who reviews my final project?",
      a: "Inés and Tomasz read every recipe card and reply with written feedback.",
    },
  ],
};
