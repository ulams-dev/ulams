import type { FormatKey } from "../shared/formats";

/**
 * Copy for "Night Sky Explorers" (front/docs/design/experiences.md §4 and the
 * Stitch landing in front/docs/design/stitch/screens/nightsky-landing). The
 * missions, prices and events are replaced by API data when the tenant has it.
 */

export interface MissionTopicCopy {
  title: string;
  format: FormatKey;
}

export interface MissionCopy {
  title: string;
  label: string;
  emoji: string;
  blurb: string;
  minutes: string;
  topics: MissionTopicCopy[];
}

/** Kid-friendly label and emoji for each learning format. */
export const FORMAT_KID: Record<FormatKey, { label: string; emoji: string }> = {
  video: { label: "Watch", emoji: "🎬" },
  image: { label: "Look", emoji: "🌌" },
  interactive: { label: "Play", emoji: "🔄" },
  pdf: { label: "Printable PDF", emoji: "📄" },
  reading: { label: "Read", emoji: "🪐" },
  audio: { label: "Listen", emoji: "🎧" },
  embed: { label: "360° Explore", emoji: "🛸" },
  scorm: { label: "Game", emoji: "🎮" },
  tracked: { label: "Outdoor challenge", emoji: "🧭" },
  quiz: { label: "Quiz", emoji: "🏆" },
  project: { label: "Make", emoji: "📸" },
};

export const NIGHTSKY = {
  brand: "Night Sky Explorers",
  courseTitleHint: "night sky",
  hero: {
    badge: "Mission 1 is free for every explorer",
    titleStart: "Your adventure to the stars",
    titleHighlight: "starts tonight",
    sub: "A 7-mission cosmic quest through planets, stars and galaxies. Spot real constellations, collect star points and earn your Junior Astronomer badge!",
    cta: "Start Mission 1 — Free",
    secondary: "Explore Mission Map",
    bubble: "All systems green! Ready for liftoff?",
    chips: [
      { emoji: "👨‍👩‍👧", text: "Ages 10–14 & families" },
      { emoji: "📖", text: "21 hands-on topics" },
      { emoji: "⏱️", text: "10–15 min missions" },
      { emoji: "✅", text: "Free first mission" },
      { emoji: "🛡️", text: "Ad-free & safe" },
    ],
  },
  missions: [
    {
      title: "Lift-off",
      label: "Free preview · Start here",
      emoji: "🚀",
      blurb: "Meet Orbi, your robot guide, and find tonight’s sky over your city.",
      minutes: "10–12 min",
      topics: [
        { title: "Meet Orbi", format: "video" },
        { title: "Your sky tonight", format: "image" },
      ],
    },
    {
      title: "Our Moon",
      label: "Phases & craters",
      emoji: "🌙",
      blurb: "Solve the mystery of why the Moon changes shape every night.",
      minutes: "15 min",
      topics: [
        { title: "Why does the Moon change?", format: "interactive" },
        { title: "Moon diary", format: "pdf" },
      ],
    },
    {
      title: "Planets",
      label: "Solar system safari",
      emoji: "🪐",
      blurb: "Tour the planets, listen to the sounds of space and fly to Mars.",
      minutes: "15 min",
      topics: [
        { title: "Planet parade", format: "reading" },
        { title: "Sounds of space", format: "audio" },
        { title: "Fly to Mars", format: "embed" },
      ],
    },
    {
      title: "Stars",
      label: "Stellar nurseries",
      emoji: "✨",
      blurb: "Dive into glowing nebulae to see how baby stars are born — and how they grow old.",
      minutes: "12 min",
      topics: [
        { title: "How stars are born", format: "video" },
        { title: "Star life cycle", format: "interactive" },
      ],
    },
    {
      title: "Constellations",
      label: "Myths & navigation",
      emoji: "🔭",
      blurb: "Connect the stars of Orion and the Great Bear, then go on a backyard sky safari.",
      minutes: "15 min",
      topics: [
        { title: "Connect the stars", format: "scorm" },
        { title: "Sky safari", format: "tracked" },
      ],
    },
    {
      title: "Galaxies",
      label: "Deep cosmos scale",
      emoji: "🌌",
      blurb: "Zoom out of the Milky Way to Andromeda, then take the mission quiz.",
      minutes: "15 min",
      topics: [
        { title: "How big is space?", format: "reading" },
        { title: "Mission quiz", format: "quiz" },
      ],
    },
    {
      title: "Your star map",
      label: "Final capstone · Mission 07",
      emoji: "🎓",
      blurb: "Draw or photograph your own star map, upload it and get a sticker from Dr. Ada — plus a bonus page of space jokes!",
      minutes: "15 min",
      topics: [
        { title: "Build your star map", format: "project" },
        { title: "Bonus: space jokes", format: "reading" },
      ],
    },
  ] as MissionCopy[],
  badges: [
    { name: "Moon Watcher", tier: "Tier 1 reward", emoji: "🌕", how: "Track the Moon’s phases for 7 nights in your Moon diary.", points: "+150 star points", tone: "yellow" },
    { name: "Planet Hopper", tier: "Tier 2 reward", emoji: "🪐", how: "Visit all 8 planets in the planet parade.", points: "+250 star points", tone: "lilac" },
    { name: "Star Finder", tier: "Tier 3 reward", emoji: "🔭", how: "Spot the Big Dipper and Polaris in your own backyard sky.", points: "+400 star points", tone: "mint" },
    { name: "Junior Astronomer", tier: "Capstone reward", emoji: "🎓", how: "Finish all 7 missions and unlock your printable diploma!", points: "Diploma unlocked", tone: "capstone" },
  ],
  formats: [
    { emoji: "🛸", title: "360° panoramas", text: "Step onto the dusty plains of Mars with the rover panorama." },
    { emoji: "🕹️", title: "Interactive games", text: "Drag the Moon around Earth and flip star life-cycle cards." },
    { emoji: "🧭", title: "Backyard challenges", text: "Tracked outdoor missions: check in what you saw in the real sky." },
    { emoji: "🎮", title: "Constellation game", text: "Connect the stars to draw the constellations yourself." },
    { emoji: "🎧", title: "Sounds of space", text: "Hear Saturn’s rings turned into sound, with read-aloud narration." },
    { emoji: "📸", title: "Make-it projects", text: "Upload your hand-drawn star map and get a sticker back." },
  ],
  parents: {
    label: "Family peace of mind",
    title: "Loved by kids. Trusted by parents.",
    text: "We built Night Sky Explorers to swap passive screen time for curiosity that sends kids outdoors into the night air.",
    quote: {
      text: "My 11-year-old now takes me outside every clear evening with her red flashlight! She pointed out Jupiter without opening her tablet.",
      who: "Anna K.",
      role: "Mum of Zoe (11)",
    },
    items: [
      { emoji: "🛡️", tone: "mint", title: "Safe and ad-free", text: "No ads, no chat with strangers, no tracking for marketing." },
      { emoji: "✉️", tone: "yellow", title: "Progress emails", text: "A short note after every mission: what they learned and what to look for tonight." },
      { emoji: "⏳", tone: "lilac", title: "10–15 minute missions", text: "Short enough for a school night, and every mission ends with looking up." },
      { emoji: "👨‍👩‍👧", tone: "coral", title: "Family plan", text: "One family subscription covers every explorer in the house." },
    ],
  },
  teachers: {
    label: "Classroom package",
    title: "Bring the cosmos to your classroom",
    text: "Run an astronomy club or a whole class: every pupil gets their own mission map, you get a class dashboard, projector-friendly simulations and printable worksheets for each mission.",
    points: ["Printable worksheets", "Whole-class sky projection", "Class progress dashboard", "Badges for the whole club"],
    cta: "Set up a classroom",
    note: "Free trial for teachers",
    quote: {
      text: "Night Sky Explorers made our astronomy unit the most memorable science unit of the year.",
      who: "Mr. David Henderson",
      role: "Science teacher, Dublin",
    },
  },
  events: {
    party: {
      kind: "In-person event",
      title: "Star party in Kraków",
      date: "22 Nov",
      text: "Join Dr. Ada Kowalczyk at the observatory for an evening of big-telescope views of Jupiter’s moons and Saturn’s rings. Warm cocoa provided!",
      place: "Kraków Observatory Hill",
      extra: "Families welcome",
      cta: "RSVP for the star party",
    },
    webinar: {
      kind: "Live webinar",
      title: "Ask an astronomer — live",
      date: "Monthly",
      text: "Got wild questions about black holes or alien life? Send them in and Dr. Ada answers live.",
      place: "Interactive kids’ stream",
      extra: "Moderated, safe chat",
      cta: "Send your space question",
    },
  },
  testimonials: [
    { text: "I found Saturn with its rings with my dad! Orbi makes it super fun because it never feels like homework.", who: "Leo, age 11", note: "Earned: Planet Hopper ★", tone: "yellow" },
    { text: "The Moon phase drag game helped me ace my science test. Even my teacher asked where I found the diagrams!", who: "Maya, age 12", note: "Earned: Moon Watcher ★", tone: "lilac" },
    { text: "Finally something that gets kids away from scrolling and actually looking up at the real night sky.", who: "Marcus V.", note: "Homeschool parent of two", tone: "mint" },
  ],
  faq: [
    { q: "Do we need an expensive telescope to start?", a: "Not at all. Every mission works with just your eyes, and some with ordinary binoculars." },
    { q: "Can brothers and sisters share one account?", a: "Yes. The family subscription covers every explorer in the house, and each one earns their own badges." },
    { q: "What devices work best for night-sky sessions?", a: "Any tablet, phone or laptop browser. Outdoors, turn the screen brightness down so your eyes stay used to the dark." },
    { q: "Is Mission 1 really free?", a: "Yes. Mission 1 is free, no card needed: meet Orbi, explore tonight’s star chart and earn your first star points." },
    { q: "What age is it for?", a: "Explorers aged 10–14. Younger kids enjoy it with a grown-up reading along, and there is Polish audio too." },
  ],
  signup: {
    title: "Ready for lift-off?",
    text: "Start Mission 1 today — it’s free. Grown-ups, leave your email and we’ll set up your explorer’s account.",
    cta: "Launch",
    note: "Mission 1 is free · No card needed",
  },
};
