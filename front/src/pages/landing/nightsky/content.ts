/** Brief copy for "Night Sky Explorers" (front/docs/design/experiences.md §4), used when the API has no data yet. */
export type MissionActivity =
  | "watch"
  | "look"
  | "play"
  | "print"
  | "read"
  | "listen"
  | "explore"
  | "game"
  | "challenge"
  | "quiz"
  | "make";

export const NIGHTSKY = {
  brand: "Night Sky Explorers",
  courseTitleHint: "night sky",
  hero: {
    title: "Your adventure to the stars starts tonight.",
    sub: "Seven short missions through planets, stars and galaxies. For explorers aged 10–14 — and the grown-ups who stay up with them.",
    cta: "Start mission 1 — free",
  },
  missions: [
    { title: "Lift-off", blurb: "Meet Orbi and find tonight’s sky over your city.", activities: ["watch", "look"], color: "yellow" },
    { title: "Our Moon", blurb: "Spin the Moon around Earth and keep a Moon diary.", activities: ["play", "print"], color: "lilac" },
    { title: "Planets", blurb: "Planet parade, the sound of Saturn’s rings and a trip to Mars.", activities: ["read", "listen", "explore"], color: "coral" },
    { title: "Stars", blurb: "How stars are born — and what happens when they grow old.", activities: ["watch", "play"], color: "yellow" },
    { title: "Constellations", blurb: "Connect the stars, then go on a backyard sky safari.", activities: ["game", "challenge"], color: "mint" },
    { title: "Galaxies", blurb: "How big is space? Then the mission quiz.", activities: ["read", "quiz"], color: "lilac" },
    { title: "Your star map", blurb: "Draw your own star map and get a sticker from Dr. Ada.", activities: ["make"], color: "coral" },
  ] as Array<{ title: string; blurb: string; activities: MissionActivity[]; color: "yellow" | "lilac" | "coral" | "mint" }>,
  activityLabels: {
    watch: "Watch",
    look: "Look",
    play: "Play",
    print: "Print",
    read: "Read",
    listen: "Listen",
    explore: "Explore",
    game: "Game",
    challenge: "Challenge",
    quiz: "Quiz",
    make: "Make",
  } as Record<MissionActivity, string>,
  badges: [
    { name: "Moon Watcher", how: "Fill in 7 nights of your Moon diary", color: "lilac", shape: "moon" },
    { name: "Planet Hopper", how: "Visit every planet in the parade", color: "coral", shape: "planet" },
    { name: "Star Finder", how: "Spot the Big Dipper on your sky safari", color: "yellow", shape: "star" },
    { name: "Junior Astronomer", how: "Finish all 7 missions", color: "mint", shape: "rocket" },
  ] as Array<{ name: string; how: string; color: string; shape: "moon" | "planet" | "star" | "rocket" }>,
  parents: [
    { title: "Progress emails", text: "A short note after every mission: what they learned and what to look for tonight." },
    { title: "Safe and ad-free", text: "No ads, no chat with strangers, no tracking for marketing." },
    { title: "10–15 minute missions", text: "Short enough for a school night, long enough to spark questions." },
    { title: "€6 a month", text: "One family subscription covers every explorer in the house." },
  ],
  teachers: {
    title: "Run an astronomy club",
    text: "The classroom package gives every pupil their own mission map, and you get a class dashboard plus printable worksheets for each mission.",
    points: ["Class dashboard", "Printable worksheets", "Moon diary sheets for the whole class"],
  },
  events: {
    party: { title: "Star party in Kraków", date: "22 Nov", kind: "In person", text: "Telescopes, hot chocolate and Dr. Ada pointing out Jupiter’s moons." },
    webinar: { title: "Ask an astronomer — live", date: "Monthly", kind: "Online", text: "Explorers send questions, Dr. Ada answers them live." },
  },
  testimonials: [
    { text: "I found the Big Dipper from my balcony!", who: "Zosia, 11", kind: "kid" },
    { text: "Mission 4 is my favourite. The nebula video is so cool.", who: "Leo, 12", kind: "kid" },
    { text: "Ten minutes before bed, and she always asks for one more.", who: "Anna, mum of Maja (10)", kind: "parent" },
  ],
  faq: [
    { q: "What age is it for?", a: "Explorers aged 10–14. Younger kids enjoy it with a grown-up reading along." },
    { q: "Is the first mission really free?", a: "Yes. Mission 1 is free, no card needed." },
    { q: "Do we need a telescope?", a: "No. Every mission works with just your eyes, and some with binoculars." },
    { q: "Is there Polish audio?", a: "Yes, every video and audio topic has a Polish narration as well." },
  ],
};
