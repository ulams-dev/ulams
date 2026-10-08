import type { FormatKey } from "../shared/formats";

/**
 * Copy for "The Coffee Atlas" landing. Facts come from the brief
 * (front/docs/design/experiences.md §2); layout and voice follow the Stitch
 * screen "The Coffee Atlas — Editorial Landing"
 * (front/docs/design/stitch/screens/coffee-landing). Used when the API has no data yet.
 */
export const COFFEE = {
  brand: "The Coffee Atlas",
  tagline: "Field guide & academy",
  courseTitleHint: "coffee atlas",
  courseTitle: "The Coffee Atlas — From Seed to Cup",
  courseSubtitle: "A field guide to specialty coffee for curious home baristas",
  ribbon: {
    left: "Self-paced · Long-form · Slow learning",
    right: "Huila · Kraków",
  },
  hero: {
    volume: "Vol. I",
    curated: "Curated by Duarte & Wierzba",
    kicker: "A field guide from seed to cup",
    title: "Learn coffee the slow way.",
    sub: "Six chapters. One cherry. Your best cup.",
    summary:
      "Follow one coffee cherry from a hillside in Huila to your cup. Learn origin, processing, roasting, brewing and tasting — and leave with your own signature recipe.",
    image: "/landing/coffee/hero-cherries.webp",
    imageAlt:
      "Weathered hands cupping freshly picked red coffee cherries in front of coffee shrubs on a misty hillside",
    caption: "Ripe cherries at first light, Huila, Colombia. Harvested at 1,750 m.",
    plate: "Plate No. 01 — Harvest",
    plateNote: "Chapter I · Origins",
    stamp: {
      title: "First edition",
      text: "Includes the 8-page printable roaster's logbook.",
    },
    facts: [
      ["Level", "Beginner", "→ Intermediate"],
      ["Length", "~9 hours", "6 chapters · 22 topics"],
      ["Certificate", "Certified", "Home Barista"],
    ] as Array<[string, string, string]>,
  },
  syllabus: {
    label: "Course syllabus",
    title: "The folio & index",
    note: "Six chapters, in the order a coffee travels from the farm to your cup. Read at your own pace; access does not expire.",
  },
  chapters: [
    {
      title: "Origins",
      kicker: "Foundational agronomy",
      blurb: "The coffee belt, altitude and why Arabica and Robusta taste worlds apart.",
      formats: ["video", "image", "reading"],
      minutes: 75,
      topics: [
        { title: "Welcome to the Atlas", format: "video", minutes: 4, preview: true },
        { title: "The coffee belt", format: "image", minutes: 10, preview: false },
        { title: "Arabica vs Robusta", format: "reading", minutes: 20, preview: false },
      ],
    },
    {
      title: "The farm",
      kicker: "Agronomy & fermentation",
      blurb: "Voices from a cooperative, the anatomy of a cherry and four ways to process it.",
      formats: ["audio", "interactive", "reading", "embed"],
      minutes: 110,
      topics: [
        { title: "Voices from the farm", format: "audio", minutes: 12, preview: false },
        { title: "Anatomy of a cherry", format: "interactive", minutes: 10, preview: false },
        { title: "Processing methods", format: "reading", minutes: 20, preview: false },
        { title: "Visit the cooperative", format: "embed", minutes: 10, preview: false },
      ],
    },
    {
      title: "Roasting",
      kicker: "Heat & chemistry",
      blurb: "Reading a roast curve, keeping a logbook and a simulator for your first batch.",
      formats: ["image", "pdf", "scorm"],
      minutes: 95,
      topics: [
        { title: "The roast curve", format: "image", minutes: 10, preview: false },
        { title: "Roaster's logbook", format: "pdf", minutes: 10, preview: false },
        { title: "Roast simulator", format: "scorm", minutes: 25, preview: false },
      ],
    },
    {
      title: "Brewing",
      kicker: "Extraction",
      blurb: "Ratios and extraction, a V60 masterclass and matching grind to method.",
      formats: ["reading", "video", "interactive"],
      minutes: 100,
      topics: [
        { title: "Ratios & extraction", format: "reading", minutes: 20, preview: false },
        { title: "Pour-over masterclass", format: "video", minutes: 14, preview: false },
        { title: "Grind size matching", format: "interactive", minutes: 10, preview: false },
      ],
    },
    {
      title: "Tasting",
      kicker: "Sensory calibration",
      blurb: "The flavour wheel, a guided cupping at home and a short tasting check.",
      formats: ["interactive", "tracked", "quiz"],
      minutes: 85,
      topics: [
        { title: "The flavour wheel", format: "interactive", minutes: 10, preview: false },
        { title: "Cupping at home", format: "tracked", minutes: 30, preview: false },
        { title: "Tasting check", format: "quiz", minutes: 15, preview: false },
      ],
    },
    {
      title: "Your signature cup",
      kicker: "Final project",
      blurb: "Brew three variations, photograph them and send your recipe card for feedback.",
      formats: ["project", "reading"],
      minutes: 75,
      topics: [
        { title: "Design your recipe", format: "project", minutes: 60, preview: false },
        { title: "Final reflections", format: "reading", minutes: 15, preview: false },
      ],
    },
  ] as Array<{
    title: string;
    kicker: string;
    blurb: string;
    formats: FormatKey[];
    minutes: number;
    topics: Array<{ title: string; format: FormatKey; minutes: number; preview: boolean }>;
  }>,
  tutors: [
    {
      name: "Inés Duarte",
      role: "Q-grader",
      place: "Huila, Colombia",
      photo: "/landing/coffee/ines-duarte.webp",
      photoAlt: "Inés Duarte holding a ceramic cup on a coffee farm with drying beds behind her",
      signedFrom: "Pitalito, Huila",
    },
    {
      name: "Tomasz Wierzba",
      role: "Roaster",
      place: "Kraków, Poland",
      photo: "/landing/coffee/tomasz-wierzba.webp",
      photoAlt: "Tomasz Wierzba smiling as he scoops green coffee from a jute sack in his roastery",
      signedFrom: "Kraków",
    },
  ],
  letter: {
    label: "A letter from your tutors",
    title: "Why coffee asks for our undivided patience.",
    paragraphs: [
      "We met over a cupping table in Bogotá, arguing about whether a washed Caturra could taste of hibiscus. It could, and we have been comparing notes ever since.",
      "This course is the long version of that afternoon. We follow a single cherry from the hillside to your kitchen, and we stop wherever something interesting happens: at the drying beds, at first crack, at the moment your pour-over turns from sour to sweet.",
    ],
    quote:
      "Take your time. Read with a cup beside you. By the last chapter you will have a recipe that is entirely your own.",
  },
  inside: {
    label: "Between the covers",
    title: "What's inside",
    intro:
      "Every chapter mixes formats, so each idea arrives the way it is best learned: watched, heard, handled or tried.",
  },
  formats: [
    {
      tag: "Film",
      name: "Pour-over masterclass",
      text: "Fourteen minutes of V60 technique with chapter markers, plus a dawn harvest film in Huila.",
      foot: "Video with transcript",
      format: "video",
    },
    {
      tag: "Podcast",
      name: "Voices from the farm",
      text: "A twelve-minute conversation with a producer, recorded among the drying beds.",
      foot: "Audio",
      format: "audio",
    },
    {
      tag: "Interactive",
      name: "Anatomy of a cherry",
      text: "Hotspot diagram: skin, pulp, mucilage, parchment, silver skin and bean.",
      foot: "H5P",
      format: "interactive",
    },
    {
      tag: "Printable PDF",
      name: "The roaster's logbook",
      text: "Eight pages for charge temperature, first crack and development time.",
      foot: "Download and print",
      format: "pdf",
    },
    {
      tag: "Simulator",
      name: "Roast simulator",
      text: "Pick a charge temperature and watch the colour change through first crack.",
      foot: "SCORM package",
      format: "scorm",
    },
    {
      tag: "Tracked",
      name: "Cupping at home",
      text: "A guided session with a timer and a scoring form, saved to your progress.",
      foot: "cmi5 activity",
      format: "tracked",
    },
    {
      tag: "Quiz",
      name: "Tasting check",
      text: "Eight questions, one of each type, from the flavour wheel to brew ratios.",
      foot: "Instant feedback",
      format: "quiz",
    },
    {
      tag: "Project",
      name: "Your signature recipe",
      text: "Brew three variations, photograph them and send a recipe card for feedback.",
      foot: "Reviewed by your tutors",
      format: "project",
    },
  ] as Array<{ tag: string; name: string; text: string; foot: string; format: FormatKey }>,
  quotes: [
    {
      text: "I finally understand why my V60 tastes sour. The course did not give me another recipe to memorise; it gave me the vocabulary of extraction.",
      who: "Maja",
      where: "Home barista · Gdańsk",
    },
    {
      text: "The roast-curve chapter is the clearest explanation I have read, and I roast for a living.",
      who: "Kuba",
      where: "Roaster · Wrocław",
    },
    {
      text: "I printed the logbook. It lives next to my grinder now.",
      who: "Elena",
      where: "Café staff · Lisbon",
    },
  ],
  live: {
    label: "Live session · from Huila",
    title: "Live cupping with Inés",
    date: "14 Nov",
    time: "18:00 CET",
    text: "Six coffees, one table, and a Q-grader thinking aloud. Bring a spoon and two mugs; we send the list of coffees a week before.",
    facts: [
      ["Coffees", "Six", "List sent a week before"],
      ["Format", "Live online", "With questions at the end"],
      ["Recording", "Included", "In the Taste Makers package"],
    ] as Array<[string, string, string]>,
    note: "Included in the Taste Makers package.",
    image: "/landing/coffee/cupping-table.webp",
    imageAlt:
      "A cupping table seen from above: brass tasting bowls of coffee, spoons, linen napkins and a scoring card",
    caption: "Cupping protocol: 200 ml bowls, four-minute crust break.",
  },
  pricing: {
    label: "Tuition",
    title: "Simple pricing",
    intro: "One payment, no subscription. Access includes later revisions of every chapter.",
    single: {
      label: "Digital edition",
      chip: "Standard",
      name: "The course",
      text: "All six chapters for the self-paced reader.",
      price: "€89",
      items: [
        "Six chapters, 22 topics, about nine hours",
        "Printable roaster's logbook",
        "Certificate: Certified Home Barista — The Coffee Atlas",
        "Lifetime access",
      ],
      excluded: ["Live cupping masterclass", "Recording of the live session"],
      cta: "Enrol in the course",
      foot: "Start with the free chapter",
    },
    bundle: {
      ribbon: "Recommended",
      label: "Course + live session",
      chip: "Package",
      name: "Taste Makers",
      text: "The course plus a seat at the live cupping with Inés.",
      items: [
        "Everything in the course",
        "Live cupping masterclass with Inés",
        "Recording of the session afterwards",
      ],
      cta: "See the package",
      foot: "Course and masterclass, one price",
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
  newsletter: {
    label: "Monthly dispatch",
    title: "Field notes",
    text: "One letter a month: a coffee we are drinking, a technique worth trying and a note from the farm. Create a free account to subscribe.",
  },
  colophon: {
    quote: "Printed in spirit on paper, honouring the slow chemistry of harvest and roastery.",
    text: "Set in Playfair Display and Plus Jakarta Sans.",
  },
};
