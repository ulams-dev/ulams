/**
 * Mock tenant API for the CI accessibility suite (`yarn workspace @ulams/web test:a11y`).
 *
 * Serves the captured "coffee" demo fixtures plus a small quiz, so the reference frontend can be
 * built and scanned with axe without PHP, Postgres or Docker. Anything it does not know answers
 * 404 JSON; the pages treat optional data as missing. Not a model of the real API: only the
 * shapes the learner pages read.
 */
import { createServer } from "node:http";
import { Buffer } from "node:buffer";
import { clearTimeout, setTimeout } from "node:timers";
import { createHash, randomBytes } from "node:crypto";
import { existsSync, readFileSync } from "node:fs";
import { createRequire } from "node:module";
import { fileURLToPath, pathToFileURL } from "node:url";
import { codeExerciseTopics, launchOf } from "./code-exercises.mjs";

const fixture = (name) => JSON.parse(readFileSync(fileURLToPath(new URL(`../fixtures/${name}.json`, import.meta.url)), "utf8"));
const coffee = fixture("coffee");
// Two tenants share the content: "coffee" has the light theme, "nightsky" the dark one. The web server reaches
// the API through ULAMS_API_INTERNAL_ORIGIN and names the tenant in X-Ulams-Api-Host.
// "polski" is the coffee academy with Polish as the tenant default language (account pages follow it, ADR 0106)
const nightskySettings = fixture("nightsky").settings;
const settingsByTenant = {
  coffee: coffee.settings,
  nightsky: fixture("nightsky").settings,
  // "zhongwen" is the same academy with Chinese as the tenant default (P5R-1)
  zhongwen: { ...coffee.settings, global: { ...coffee.settings.global, language: "zh" } },
  // and the dark academy (nightsky theme) in Polish and Chinese
  nocpl: { ...nightskySettings, global: { ...nightskySettings.global, language: "pl" } },
  noczh: { ...nightskySettings, global: { ...nightskySettings.global, language: "zh" } },
  polski: { ...coffee.settings, global: { ...coffee.settings.global, language: "pl", accessibilityEmail: "dostepnosc@coffee.test", accessibilityUpdated: "2026-10-01", accessibilityNotes: "Nagrania z kursu o kawie nie mają jeszcze napisów.\n\nPracujemy nad tym." } },
};
const program = fixture("coffee-program");
// On these tenants the course has no language of its own, so the learner pages follow the tenant default
// language (ADR 0106); on the others the course says "en" and the pages stay English.
const LANGUAGELESS = new Set(["polski", "zhongwen", "nocpl", "noczh"]);
const courseFor = (tenant, course) => (LANGUAGELESS.has(tenant) ? { ...course, language: null } : course);

const QUIZ_TOPIC_ID = 900;
const quizTopic = {
  id: QUIZ_TOPIC_ID,
  title: "Check your understanding",
  lesson_id: program.lessons[0].id,
  active: true,
  preview: false,
  order: 99,
  topicable_id: 1,
  topicable_type: "Ulams\\TopicTypes\\Models\\TopicContent\\GiftQuiz",
  topicable: { id: 1, value: "", max_attempts: 3, max_execution_time: 10, min_pass_score: 1, score: 2 },
};
// A Layout topic with a practice activity: the lesson view's "hints shown at the start" applies to it
const PRACTICE_TOPIC_ID = 901;
const practiceTopic = {
  id: PRACTICE_TOPIC_ID,
  title: "Dial in a pour-over",
  lesson_id: program.lessons[0].id,
  active: true,
  preview: false,
  order: 100,
  topicable_id: 1,
  topicable_type: "Ulams\\TopicTypeLayout\\Models\\LayoutTopic",
  topicable: {
    document: [
      {
        component: "PracticeActivity",
        id: "practice-1",
        props: {
          title: "Pour-over practice",
          intro: "Dial in a pour-over by changing one thing at a time.",
          toolbox: [{ label: "Brew ratio chart", text: "1:16 is a good start." }],
          challenges: [
            {
              id: "c1",
              level: 1,
              prompt: "The cup tastes sour. What do you change first?",
              hints: [
                { tier: "nudge", text: "Sour usually means under-extracted." },
                { tier: "pointer", text: "Look at the grind size." },
                { tier: "near_solution", text: "Grind a little finer." },
              ],
              options: [
                { label: "Grind finer", correct: true, feedback: "A finer grind extracts more." },
                { label: "Use cooler water", correct: false, feedback: "Cooler water extracts less." },
              ],
              workedSolution: "Grind finer and brew again.",
            },
          ],
        },
      },
    ],
    markdown_fallback: "Practice: dial in a pour-over.",
  },
};
program.lessons[0].topics = [...program.lessons[0].topics, quizTopic, practiceTopic, ...codeExerciseTopics];

const questions = [
  { id: 1, type: "multiple_choice", score: 1, order: 1, title: null, question: "Which species is called Robusta?", options: { answers: ["Coffea arabica", "Coffea canephora", "Coffea liberica"] } },
  { id: 2, type: "true_false", score: 1, order: 2, title: null, question: "Arabica grows best at high altitude.", options: [] },
];
const attempt = (ended = false) => ({
  id: 1,
  topic_gift_quiz_id: 1,
  started_at: new Date().toISOString(),
  end_at: null,
  max_score: 2,
  min_pass_score: 1,
  result_score: ended ? 2 : null,
  is_ended: ended,
  questions,
  answers: [],
});

const topics = program.lessons.flatMap((l) => l.topics);
// AI content labelling: the roast-curve lesson was applied from a builder session
for (const t of topics) if (t.id === 8) t.ai_assisted = true;
const progressRows = topics.map((t, i) => ({ id: i + 1, topic_id: t.id, status: i < 2 ? 1 : 0, seconds: i < 2 ? 120 : 0 }));
const me = { id: 7, first_name: "Demo", last_name: "Student", email: "student@coffee.example", roles: ["student"] };

// Learner Insights: the "Extra help for you" items of a lesson. State is kept per tenant (the web server names the
// tenant in X-Ulams-Api-Host), so the desktop and phone projects can change their own copy at the same time.
//   topic 3  ready: an explanation with a worked example, and a practice item with a question and an open question
//   topic 6  ready, for the "This helped" journey      topic 11  ready, for the "Hide" journey
//   topic 18 still generating                          every other topic: the module is on and nothing is offered
const ulid = (n) => `01JBCDEFGHJKMNPQRSTVWXYZ${String(n).padStart(2, "0")}`;
const FRAGMENTS = [
  { fragmentId: "frg_aaaaaaaaaaaa", label: "Arabica vs Robusta: the two species" },
  { fragmentId: "frg_bbbbbbbbbbbb", label: "Arabica vs Robusta: growing conditions" },
];
const AI_LABEL = "AI-generated, based on this course's sources";
const PRACTICE = {
  stem: "Which species is usually grown at higher altitude?",
  options: [
    { text: "Coffea arabica", correct: true },
    { text: "Coffea canephora", correct: false },
    { text: "Neither: altitude does not matter", correct: false },
  ],
  explanation: "Arabica prefers cool, high ground; Robusta tolerates heat and lower altitude.",
};
const block = (component, props, citations = ["frg_aaaaaaaaaaaa"]) => ({ component, props, citations });
const item = (n, kind, summary, blocks, extra = {}) => ({
  assignmentId: ulid(n),
  kind,
  status: "ready",
  reasons: ["You answered this question wrongly 2 times", "This part of the course has been hard for other learners too"],
  why: "You answered this question wrongly 2 times",
  feedback: null,
  document: { summary, blocks },
  citations: FRAGMENTS,
  aiLabel: AI_LABEL,
  ...extra,
});
const supportSeed = () => ({
  3: [
    item(1, "explanation", "Two species, two sets of needs: a short explanation and a worked example.", [
      block("Prose", { title: "Another way to see it", markdown: "**Arabica** is grown high, in cool air, and tastes sweeter. **Robusta** tolerates heat and carries more caffeine.\n\n- Arabica: altitude, mild climate\n- Robusta: lowlands, warm climate" }, ["frg_aaaaaaaaaaaa", "frg_bbbbbbbbbbbb"]),
      block("WorkedExample", { title: "Choosing a species for a farm", markdown: "Step 1. The farm sits at 1 600 m with cool nights.\n\nStep 2. Cool, high ground suits Arabica.\n\nStep 3. Plant Arabica." }, ["frg_bbbbbbbbbbbb"]),
    ]),
    item(2, "practice", "One question to try, and one to answer in your own words.", [
      block("PracticeQuestion", { title: "Try it yourself", markdown: "Use what the two species need.", practice: PRACTICE }),
      block("ElaborativeQuestion", { title: "Say it in your own words", markdown: "Why does altitude change how a coffee tastes?" }, ["frg_bbbbbbbbbbbb"]),
    ]),
  ],
  6: [item(3, "explanation", "A short explanation of how the cherry is processed.", [block("Prose", { title: "Another way to see it", markdown: "Processing removes the fruit around the seed before it is dried." })])],
  11: [item(4, "explanation", "A short explanation of the brew ratio.", [block("Prose", { title: "Another way to see it", markdown: "A 1:16 ratio means 1 g of coffee for every 16 g of water." })])],
  18: [{ ...item(5, "explanation", "", []), status: "generating", document: null, citations: [], aiLabel: undefined }],
});
const supportState = new Map();
const supportOf = (tenant) => {
  if (!supportState.has(tenant)) supportState.set(tenant, supportSeed());
  return supportState.get(tenant);
};
// Feynman reflection: five answers a day per tenant, the sixth is refused (429); text containing "AI-OFF" gets 503
const REFLECTIONS_PER_DAY = 5;
const reflectionCount = new Map();
const findAssignment = (tenant, id) => Object.values(supportOf(tenant)).flat().find((i) => i.assignmentId === id);

// Learner Insights presentation profile (the lesson view). "nightsky" has adaptive presentation on and derives parts
// with two hints shown at the start because lessons were hard; "coffee" has it off, so only the learner's own choices
// apply. Choices are kept per tenant like the support items (reset with /__mock/reset).
const PRESENTATION_DEFAULT = { density: "comfortable", chunking: "whole", navigation: "overview", hints: 0 };
const PRESENTATION_OPTIONS = { density: ["comfortable", "compact"], chunking: ["whole", "steps"], navigation: ["overview", "linear"], hints: [0, 1, 2, 3] };
const presentationDerived = (tenant) => (tenant === "nightsky" ? { ...PRESENTATION_DEFAULT, chunking: "steps", hints: 2 } : { ...PRESENTATION_DEFAULT });
const presentationOverrides = new Map();
const presentationOf = (tenant) => {
  const overrides = presentationOverrides.get(tenant) ?? {};
  const adaptive = tenant === "nightsky";
  const derived = presentationDerived(tenant);
  return {
    adaptive,
    profile: { ...PRESENTATION_DEFAULT, ...(adaptive ? derived : {}), ...overrides },
    derived,
    overrides,
    why: adaptive ? ["Two lessons in this module were hard"] : [],
  };
};
const changePresentation = (tenant, body) => {
  const overrides = { ...(presentationOverrides.get(tenant) ?? {}) };
  for (const [key, value] of Object.entries(body ?? {})) {
    if (!(key in PRESENTATION_OPTIONS)) continue;
    if (value === null) delete overrides[key];
    else if (PRESENTATION_OPTIONS[key].includes(value)) overrides[key] = value;
  }
  presentationOverrides.set(tenant, overrides);
  return presentationOf(tenant);
};

// Course tutor: on, 29 of 30 questions left, one earlier conversation; every question gets the same cited answer.
// The `nightsky` tenant has streaming switched off (the operator switch), so its widget uses the plain request.
const tutorStatus = { enabled: true, aiEnabled: true, remaining: 29, dailyLimit: 30, attemptActive: false };
const tutorAnswer = {
  conversationId: "01JMOCKCONVERSATION00000A",
  messageId: "01JMOCKMESSAGE000000000A",
  answer: "Robusta is *Coffea canephora*. It grows at lower altitudes than Arabica and has more caffeine.\n\n- Hardier plant\n- Stronger, more bitter cup",
  citations: [{ chunkId: "c1", label: "Species and altitude", topicId: 3, fragmentIds: [] }],
  refusal: "none",
  inScope: true,
  remaining: 28,
  label: "AI tutor · answers only from this course",
};
const RUN_OK = "01jmockrun00000000000000ok";
const RUN_ERROR = "01jmockrun0000000000000err";
const RUN_GONE = "01jmockrun000000000000gone";
const TUTOR_EVENTS = (runId) =>
  runId === RUN_ERROR
    ? [{ type: "RUN_STARTED", runId }, { type: "RUN_ERROR", code: "error", message: "The tutor could not answer. Please ask again.", runId }]
    : [
        { type: "RUN_STARTED", runId },
        { type: "TEXT_MESSAGE_START", messageId: "m", role: "assistant" },
        { type: "TEXT_MESSAGE_CONTENT", messageId: "m", delta: "Robusta is Coffea canephora. " },
        { type: "TEXT_MESSAGE_CONTENT", messageId: "m", delta: "It grows at lower altitudes." },
        { type: "TEXT_MESSAGE_END", messageId: "m" },
        { type: "CUSTOM", name: "tutor.final", value: { ...tutorAnswer, questionId: "01JMOCKQUESTION00000000A" } },
        { type: "RUN_FINISHED", runId },
      ];
const tutorEarlier = { id: "01JMOCKEARLIER0000000000A", lastMessageAt: "2026-10-20T09:30:00+00:00", expiresAt: "2027-01-18T09:30:00+00:00" };

// A second course the learner has finished, with its issued certificate (finish page, certificates list).
const doneProgram = { ...program, id: 2, title: "Espresso basics", summary: "Pull a balanced shot." };
const doneRows = topics.map((t, i) => ({ id: 100 + i, topic_id: t.id, status: 1, seconds: 60 }));
const CERT_ID = "b3f1c2d4-1111-4222-8333-444455556666";
const isoDay = (offset) => new Date(Date.now() + offset * 86_400_000).toISOString();

// My training: one overdue (course 1), one due soon, one completed with a certificate; a path with a locked step; skills.
const assignments = [
  { id: 1, user: null, course: { id: 1, title: program.title }, source: "manual", status: "overdue", assigned_at: isoDay(-40), due_at: isoDay(-3), completed_at: null, escalation_level: 1, certificate: null },
  { id: 2, user: null, course: { id: 3, title: "Food safety" }, source: "rule", status: "assigned", assigned_at: isoDay(-5), due_at: isoDay(6), completed_at: null, escalation_level: 0, certificate: null },
  { id: 3, user: null, course: { id: 2, title: "Espresso basics" }, source: "manual", status: "completed", assigned_at: isoDay(-90), due_at: isoDay(-60), completed_at: isoDay(-70), escalation_level: 0, certificate: { id: 9, certificate_id: CERT_ID, status: "valid", expires_at: isoDay(300) } },
];
const myPaths = [
  { path_id: 1, title: "Barista onboarding", source: "rule", enrolled_at: isoDay(-30), due_at: null, completed_at: null, steps: [
    { course_id: 2, title: "Espresso basics", status: "done", waiting_for: [] },
    { course_id: 1, title: program.title, status: "available", waiting_for: [] },
    { course_id: 3, title: "Food safety", status: "locked", waiting_for: [1] },
  ] },
];
const mySkills = [
  { competency_id: 1, name: "Espresso extraction", code: "BAR-1", area: "Barista", courses: [{ course_id: 2, title: "Espresso basics" }], earned_at: isoDay(-70), expires_at: isoDay(300) },
  { competency_id: 2, name: "Cupping", code: "BAR-2", area: "Barista", courses: [{ course_id: 1, title: program.title }], earned_at: isoDay(-20), expires_at: null },
  { competency_id: 3, name: "Allergen handling", code: null, area: null, courses: [{ course_id: 2, title: "Espresso basics" }], earned_at: null, expires_at: null },
];
const teamRows = [
  { ...assignments[1], id: 11, user: { id: 21, name: "Ada Lovelace", email: "ada@coffee.example" }, status: "assigned" },
  { ...assignments[0], id: 12, user: { id: 22, name: "Grace Hopper", email: "grace@coffee.example" } },
];
const certificates = [
  { id: 9, title: "Espresso basics", certificate_id: CERT_ID, assignable_type: "Ulams\\Courses\\Models\\Course", assignable_id: 2, status: "valid", expires_at: isoDay(300), revoked_at: null, course_version: 2, created_at: isoDay(-70) },
  { id: 8, title: "Latte art", certificate_id: "c3f1c2d4-1111-4222-8333-444455556666", assignable_type: "Ulams\\Courses\\Models\\Course", assignable_id: 4, status: "revoked", expires_at: null, revoked_at: isoDay(-10), course_version: 1, created_at: isoDay(-120) },
];
const verifyRecord = { certificate_id: CERT_ID, status: "valid", title: "Espresso basics", course_title: "Espresso basics", holder_name: "Demo Student", issued_at: isoDay(-70), expires_at: isoDay(300), revoked_at: null, issuer: "Coffee Atlas" };
// the smallest valid one-page PDF
const PDF = Buffer.from("%PDF-1.1\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 100]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");

/**
 * Learner Insights analytics for the studio Insights page (/studio/courses/:id/insights). The course id picks the
 * state: 1 full data (a withheld section, a rate without enough cases), 2 a group below the minimum, 3 no activity
 * in the period, 4 individual data not allowed, 5 fails once and then works, 6 not allowed at all, 999 unknown.
 * The `polski` tenant has the module switched off.
 */
const INSIGHTS_MIN = 10;
const insightsTitles = { 1: "Coffee brewing basics", 2: "Cupping for beginners", 3: "Roasting 101", 4: "Espresso machines", 5: "Latte art", 6: "Barista secrets" };
const insightsFails = new Map();
const insightsSummary = (id, days) =>
  id === 2
    ? { courseId: 2, periodDays: days, minGroupSize: INSIGHTS_MIN, enrolled: 6, smallGroup: true }
    : {
        courseId: id,
        periodDays: days,
        minGroupSize: INSIGHTS_MIN,
        enrolled: 48,
        smallGroup: false,
        learners: { enrolled: 48, active: id === 3 ? 0 : 31, completed: id === 3 ? 0 : 12, completionRate: id === 3 ? 0 : 0.25 },
        quiz: id === 3 ? { attempts: 0, averageScore: null, passRate: null } : { attempts: 57, averageScore: 0.68, passRate: 0.61 },
        statuses: { on_track: 36, struggling: 8, at_risk: 4 },
        nudges: { sent: 9, suppressed: 3, opened: 5, recoveryRate: null },
        remediation: { shown: 22, helpful: 14, notHelpful: 3, recoveryRate: 0.64 },
      };
const insightsElements = (id, days) => ({
  courseId: id,
  periodDays: days,
  minGroupSize: INSIGHTS_MIN,
  elements:
    id === 3
      ? []
      : [
          { elementKey: "question:3", label: "2.3 Question 3: Which ratio suits a V60?", hidden: false, activeLearners: 29, struggleRate: 0.41, remediationRate: 0.34, recoveryRate: 0.58, topReasons: [{ rule: "failed_attempts", count: 12 }, { rule: "slow_element", count: 4 }] },
          { elementKey: "topic:12", label: "3.1 Grinding for espresso", hidden: false, activeLearners: 18, struggleRate: 0.17, remediationRate: 0.06, recoveryRate: null, topReasons: [{ rule: "repetition", count: 3 }] },
          { elementKey: "topic:14", label: "4.2 Milk texture", hidden: false, activeLearners: 12, struggleRate: 0, remediationRate: 0, recoveryRate: null, topReasons: [] },
          { elementKey: "topic:15", label: "4.3 Pouring a rosetta", hidden: true, reason: "not_enough_learners" },
        ],
});
const insightsLearners = [
  { userId: 21, name: "Ada Lovelace", status: "struggling", since: isoDay(-3), reasons: ["3 failed attempts on 2.3 Question 3"] },
  { userId: 22, name: "Grace Hopper", status: "at_risk", since: isoDay(-9), reasons: ["9 days without activity in this course"] },
];
const insightsTimeline = [
  { at: isoDay(-1), type: "quiz.answer", element: "2.3 Question 3", value: 0, data: { question_id: 3, correct: false }, source: "gift" },
  { at: isoDay(-2), type: "topic.opened", element: "2.3 Water and ratio", value: null, data: { revisit: true }, source: "progress" },
];
const insightsId = (match) => Number(match[1]);
const insightsGate = (id, tenant, what) => {
  if (id === 6) return { __status: 403, message: "This action is unauthorized." };
  if (id === 5 && what === "summary") {
    const key = `${tenant}:5`;
    if (!insightsFails.get(key)) {
      insightsFails.set(key, true);
      return { __status: 500, message: "Insights are temporarily unavailable." };
    }
  }
  return null;
};
const insightsRoutes = [
  // test control: course 5 fails its next summary request again (every tenant has its own state)
  ["POST", /^\/__mock\/insights\/reset$/, ({ tenant }) => (insightsFails.delete(`${tenant}:5`), { reset: true })],
  ["GET", /^\/api\/learner-insights\/me$/, ({ tenant }) => ({ enabled: tenant !== "polski", settings: { enabled: true, help: true, nudges: true, consent: null }, requiresConsent: false, courses: [], retention: { signalsDays: 180, historyDays: 365 } })],
  ["GET", /^\/api\/admin\/courses\/(\d+)$/, ({ match }) => (insightsTitles[match[1]] ? { id: Number(match[1]), title: insightsTitles[match[1]] } : { __status: 404, message: "Not found" })],
  ["GET", /^\/api\/admin\/learner-insights\/courses\/(\d+)\/summary$/, ({ match, query, tenant }) => insightsGate(insightsId(match), tenant, "summary") ?? insightsSummary(insightsId(match), Number(query.get("period") ?? 30))],
  ["GET", /^\/api\/admin\/learner-insights\/courses\/(\d+)\/elements$/, ({ match, query, tenant }) => insightsGate(insightsId(match), tenant, "elements") ?? insightsElements(insightsId(match), Number(query.get("period") ?? 30))],
  ["GET", /^\/api\/admin\/learner-insights\/courses\/(\d+)\/learners$/, ({ match }) => (insightsId(match) === 4 ? { __status: 403, message: "This action is unauthorized." } : insightsLearners)],
  ["GET", /^\/api\/admin\/learner-insights\/learners\/(\d+)\/courses\/(\d+)\/timeline$/, ({ match }) => (Number(match[2]) === 4 ? { __status: 403, message: "This action is unauthorized." } : insightsTimeline)],
];

// Code exercises (ADR 0170): launches, and the attempts the lesson page reports, kept per tenant and topic
const codeAttempts = new Map();
const attemptsOf = (tenant, topic) => codeAttempts.get(`${tenant}:${topic}`) ?? [];
const CODE_TOPICS = new Map(codeExerciseTopics.map((t) => [t.id, t]));
const RUNNER_URL = `http://127.0.0.1:${process.env.MOCK_API_PORT ?? 4390}/runtime/code-runner/1/runner.html`;

const routes = [
  ...insightsRoutes,
  ["POST", /^\/api\/code-exercises\/launches\/(\d+)$/, ({ tenant, match }) => {
    const topic = CODE_TOPICS.get(Number(match[1]));
    return topic ? launchOf(topic, RUNNER_URL, attemptsOf(tenant, topic.id)) : { __status: 404, message: "Not a code exercise" };
  }],
  ["POST", /^\/api\/code-exercises\/topics\/(\d+)\/attempts$/, ({ tenant, match, body }) => {
    const topic = CODE_TOPICS.get(Number(match[1]));
    if (!topic) return { __status: 404, message: "Not a code exercise" };
    const results = body?.results;
    const valid = Array.isArray(results) && results.length >= 1 && results.length <= 200 && results.every((r) => typeof r?.name === "string" && ["pass", "fail", "error"].includes(r?.status));
    if (!valid) return { __status: 422, message: "Invalid results" };
    const rows = attemptsOf(tenant, topic.id);
    const passed = results.filter((r) => r.status === "pass").length;
    const score = Math.round((passed / results.length) * 100);
    const row = { id: rows.length + 1, attempt_no: rows.length + 1, mode: "practice", verified: false, passed, total: results.length, score, results, runner: body.runner ?? null, files_hash: body.files_hash ?? null };
    codeAttempts.set(`${tenant}:${topic.id}`, [...rows, row]);
    return { attempt: row, status: score >= topic.topicable.pass_score ? 1 : 2 };
  }],
  ["GET", /^\/api\/compliance\/my-assignments$/, () => assignments],
  ["GET", /^\/api\/compliance\/my-paths$/, () => myPaths],
  ["GET", /^\/api\/compliance\/my-skills$/, () => mySkills],
  ["GET", /^\/api\/compliance\/team\/assignments$/, () => teamRows],
  ["GET", /^\/api\/pdfs$/, () => certificates],
  ["GET", /^\/api\/certificates\/verify\/b3f1c2d4-1111-4222-8333-444455556666$/, () => verifyRecord],
  ["GET", /^\/api\/courses\/2\/program$/, () => doneProgram],
  ["GET", /^\/api\/courses\/progress\/2$/, () => doneRows],
  ["GET", /^\/api\/learner-insights\/courses\/1\/topics\/(\d+)\/support$/, ({ tenant, match }) => ({ enabled: true, items: (supportOf(tenant)[match[1]] ?? []).filter((i) => !i.hidden) })],
  [
    "POST",
    /^\/api\/learner-insights\/assignments\/([0-9A-Za-z]{26})\/feedback$/,
    ({ tenant, match, body }) => {
      const found = findAssignment(tenant, match[1]);
      if (found) found.feedback = body?.helpful ? "helpful" : "not_helpful";
      return { feedback: found?.feedback ?? null };
    },
  ],
  [
    "POST",
    /^\/api\/learner-insights\/assignments\/([0-9A-Za-z]{26})\/reflection$/,
    ({ tenant, body }) => {
      if (String(body?.text ?? "").length < 10) return { __status: 422, message: "The text must be at least 10 characters." };
      if (String(body?.text).includes("AI-OFF")) return { __status: 503, message: "AI is disabled" };
      const used = reflectionCount.get(tenant) ?? 0;
      if (used >= REFLECTIONS_PER_DAY) return { __status: 429, message: "Daily limit reached" };
      reflectionCount.set(tenant, used + 1);
      return {
        correct: "You linked altitude to a slower, sweeter ripening of the cherry.",
        missing: "You did not say why cooler air matters for the sugars.",
        followUp: "How would a lower farm change the cup?",
        citations: [{ fragmentId: "frg_bbbbbbbbbbbb", label: FRAGMENTS[1]?.label ?? "Species and altitude" }],
        remaining: REFLECTIONS_PER_DAY - used - 1,
      };
    },
  ],
  [
    "POST",
    /^\/api\/learner-insights\/assignments\/([0-9A-Za-z]{26})\/hide$/,
    ({ tenant, match }) => {
      const found = findAssignment(tenant, match[1]);
      if (found) found.hidden = true;
      return { hidden: true };
    },
  ],
  ["GET", /^\/api\/learner-insights\/courses\/1\/presentation$/, ({ tenant }) => presentationOf(tenant)],
  ["PUT", /^\/api\/learner-insights\/courses\/1\/presentation$/, ({ tenant, body }) => changePresentation(tenant, body)],
  ["GET", /^\/api\/tutor\/courses\/1\/status$/, ({ tenant }) => ({ ...tutorStatus, streaming: tenant !== "nightsky" })],
  ["GET", /^\/api\/tutor\/courses\/1\/conversations$/, () => [tutorEarlier]],
  [
    "POST",
    /^\/api\/tutor\/courses\/1\/messages$/,
    ({ body, status }) => {
      if (!body?.stream) return tutorAnswer;
      status(202);
      // a question containing "boom" gets a run that ends with RUN_ERROR, "gone" one whose events are missing
      const runId = /boom/i.test(body.text) ? RUN_ERROR : /gone/i.test(body.text) ? RUN_GONE : RUN_OK;
      return { conversationId: tutorAnswer.conversationId, runId, questionId: "01JMOCKQUESTION00000000A" };
    },
  ],
  ["GET", /^\/api\/tutor\/conversations\/01JMOCKEARLIER0000000000A$/, () => ({ id: tutorEarlier.id, courseId: 1, expiresAt: tutorEarlier.expiresAt, messages: [
    { id: "01JMOCKMSG1", role: "learner", text: "Which species is Robusta?", citations: [], refusal: null, at: tutorEarlier.lastMessageAt },
    { id: "01JMOCKMSG2", role: "tutor", text: tutorAnswer.answer, citations: tutorAnswer.citations, refusal: "none", at: tutorEarlier.lastMessageAt },
  ] })],
  ["DELETE", /^\/api\/tutor\/conversations\/01JMOCKEARLIER0000000000A$/, () => ({ deleted: true })],
  ["GET", /^\/api\/config$/, () => ({ ai: { label_generated_courses: true } })],
  ["GET", /^\/api\/config$/, () => ({ ai: { label_generated_courses: true } })],
  ["GET", /^\/api\/settings$/, () => coffee.settings],
  ["GET", /^\/api\/courses$/, ({ tenant }) => [courseFor(tenant, coffee.course)]],
  ["GET", /^\/api\/courses\/1$/, ({ tenant }) => courseFor(tenant, program)],
  ["GET", /^\/api\/courses\/1\/program$/, ({ tenant }) => courseFor(tenant, program)],
  ["GET", /^\/api\/courses\/my$/, () => ({ ids: [1] })],
  ["GET", /^\/api\/courses\/progress$/, () => [{ course: { id: 1, title: program.title, summary: program.summary, image_url: null }, progress: progressRows }]],
  ["GET", /^\/api\/courses\/progress\/1$/, () => progressRows],
  ["PUT", /^\/api\/courses\/progress\/\d+\/ping$/, () => ({ status: true })],
  ["PATCH", /^\/api\/courses\/progress\/1$/, () => progressRows],
  ["GET", /^\/api\/tutors$/, () => coffee.tutors],
  ["GET", /^\/api\/webinars$/, () => coffee.webinars],
  ["GET", /^\/api\/stationary-events$/, () => coffee.events],
  ["GET", /^\/api\/products$/, () => coffee.products],
  ["GET", /^\/api\/profile\/me$/, () => me],
  ["POST", /^\/api\/demo\/login$/, () => ({ token: "mock-token", expires_at: new Date(Date.now() + 86_400_000).toISOString() })],
  ["POST", /^\/api\/auth\/login$/, () => ({ token: "mock-token", expires_at: new Date(Date.now() + 86_400_000).toISOString() })],
  ["POST", /^\/api\/quiz-attempts$/, () => attempt()],
  ["GET", /^\/api\/quiz-attempts\/1$/, () => attempt()],
  ["GET", /^\/api\/quiz-attempts$/, () => []],
  ["POST", /^\/api\/quiz-answers$/, () => attempt()],
  ["POST", /^\/api\/quiz-attempts\/1\/end$/, () => attempt(true)],
];

/**
 * Social login of the real API (ADR 0121), minus the provider: the start endpoint answers with the
 * redirect back to `return_url` carrying a one-time code bound to the S256 challenge; the exchange
 * checks verifier, single use and the 60 second lifetime. The tests never get a token in a URL.
 */
const socialCodes = new Map();
const challengeFor = (verifier) => createHash("sha256").update(verifier).digest("base64url");

function socialStart(url, res, returnParam = "return_url") {
  const returnUrl = url.searchParams.get(returnParam) ?? "";
  const challenge = url.searchParams.get("challenge") ?? "";
  const allowed = /^http:\/\/[a-z]+\.app\.localhost:\d+\//.test(returnUrl);
  if (!allowed || !/^[A-Za-z0-9_-]{43}$/.test(challenge)) {
    res.statusCode = 422;
    res.end(JSON.stringify({ message: "The return URL is not allowed for this site or the challenge is missing." }));
    return;
  }
  const code = randomBytes(32).toString("base64url");
  socialCodes.set(code, { challenge, expires: Date.now() + 60_000, used: false });
  res.statusCode = 302;
  res.setHeader("Location", `${returnUrl}?code=${code}&complete=true`);
  res.end();
}

function socialExchange(body, res, tokenPrefix = "social-token") {
  let input = {};
  try {
    input = JSON.parse(body || "{}");
  } catch {
    // answered below as an invalid code
  }
  const row = socialCodes.get(input.code);
  const verifier = typeof input.verifier === "string" ? input.verifier : "";
  const ok = row && !row.used && row.expires > Date.now() && challengeFor(verifier) === row.challenge;
  if (row) row.used = true;
  if (!ok) {
    res.statusCode = 400;
    res.end(JSON.stringify({ success: false, message: "The login code is invalid or has expired. Start again." }));
    return;
  }
  res.end(JSON.stringify({ success: true, message: "Login successful", data: { complete: true, token: `${tokenPrefix}-${input.code.slice(0, 8)}`, expires_at: new Date(Date.now() + 86_400_000).toISOString() } }));
}

const server = createServer((req, res) => {
  const url = new URL(req.url ?? "/", "http://mock");
  const path = url.pathname;
  res.setHeader("Content-Type", "application/json");
  // test control: forget what a tenant changed (the suite calls this before a journey that changes state)
  if (req.method === "POST" && path === "/__mock/reset") {
    req.resume();
    supportState.delete(url.searchParams.get("tenant") ?? "");
    reflectionCount.delete(url.searchParams.get("tenant") ?? "");
    presentationOverrides.delete(url.searchParams.get("tenant") ?? "");
    res.end(JSON.stringify({ reset: true }));
    return;
  }
  // forget the code exercise attempts of a tenant (its own reset: the other specs share the tenants, and the general reset is theirs)
  if (req.method === "POST" && path === "/__mock/reset-code") {
    req.resume();
    for (const key of [...codeAttempts.keys()]) if (key.startsWith(`${url.searchParams.get("tenant") ?? ""}:`)) codeAttempts.delete(key);
    res.end(JSON.stringify({ reset: true }));
    return;
  }
  // the attempts a tenant has recorded for a code exercise (for the tests)
  const recorded = /^\/__mock\/code-attempts\/(\d+)$/.exec(path);
  if (req.method === "GET" && recorded) {
    req.resume();
    res.end(JSON.stringify(attemptsOf(url.searchParams.get("tenant") ?? "", Number(recorded[1]))));
    return;
  }
  // the content origin of the runner (ADR 0170): the static files, with the headers the API sets
  if ((req.method === "GET" || req.method === "HEAD") && path.startsWith("/runtime/code-runner/1/")) {
    req.resume();
    serveRunner(path.slice("/runtime/code-runner/1/".length), res);
    return;
  }
  if (req.method === "GET" && /^\/api\/pdfs\/generate\/9$/.test(path)) {
    req.resume();
    res.setHeader("Content-Type", "application/pdf");
    res.setHeader("Content-Disposition", 'attachment; filename="espresso-basics-9.pdf"');
    res.end(PDF);
    return;
  }
  if (req.method === "GET" && path === "/api/auth/social/google") {
    req.resume();
    socialStart(url, res);
    return;
  }
  if (req.method === "POST" && path === "/api/auth/social/exchange") {
    const body = [];
    req.on("data", (chunk) => body.push(chunk));
    req.on("end", () => socialExchange(Buffer.concat(body).toString("utf8"), res));
    return;
  }
  // single sign-on (identity package): the tenant lists its enabled providers, `start` redirects back with a code
  if (req.method === "GET" && path === "/api/identity/providers") {
    req.resume();
    res.end(JSON.stringify({ success: true, message: "", data: [{ slug: "acme-sso", name: "Acme staff", protocol: "oidc" }] }));
    return;
  }
  if (req.method === "GET" && path === "/api/identity/sso/acme-sso/start") {
    req.resume();
    socialStart(url, res, "return_to");
    return;
  }
  if (req.method === "POST" && path === "/api/identity/sso/exchange") {
    const body = [];
    req.on("data", (chunk) => body.push(chunk));
    req.on("end", () => socialExchange(Buffer.concat(body).toString("utf8"), res, "sso-token"));
    return;
  }
  const run = /^\/api\/tutor\/runs\/([0-9a-z]{26})\/events$/.exec(path);
  if (req.method === "GET" && run) {
    streamRun(req, res, run[1]);
    return;
  }
  const route = routes.find(([method, pattern]) => method === req.method && pattern.test(path));
  const chunks = [];
  req.on("data", (chunk) => chunks.push(chunk));
  req.on("end", () => respond(req, res, path, route, Buffer.concat(chunks).toString("utf8")));
});

/**
 * The runner's files on a content origin, with what the API and the proxy set (ADR 0170): the policies come from
 * api/packages/code-exercises/resources/runner-csp.json, the same file RunnerHeaders reads. The pages that may
 * frame them are the apps of every tenant on this machine (*.app.localhost).
 */
const requireHere = createRequire(import.meta.url);
const runnerDir = fileURLToPath(new URL("../../../../api/packages/code-exercises/resources/runtime/code-runner/1/", import.meta.url));
const policies = JSON.parse(readFileSync(fileURLToPath(new URL("../../../../api/packages/code-exercises/resources/runner-csp.json", import.meta.url)), "utf8"));
const policy = (name) => policies[name].map((d) => d.replace("{ancestors}", "http://*.app.localhost:*")).join("; ");
const RUNNER_FILES = { "runner.html": "text/html; charset=UTF-8", "exec.html": "text/html; charset=UTF-8", "runner.js": "text/javascript; charset=UTF-8", "exec.js": "text/javascript; charset=UTF-8", "esbuild.js": "text/javascript; charset=UTF-8", "esbuild.wasm": "application/wasm" };

function serveRunner(file, res) {
  const type = RUNNER_FILES[file];
  // the wasm file is not committed (14 MB): the one of the esbuild-wasm package is the same file
  const path = file === "esbuild.wasm" && !existsSync(`${runnerDir}${file}`) ? `${fileURLToPath(new URL(".", pathToFileURL(requireHere.resolve("esbuild-wasm/package.json"))))}esbuild.wasm` : `${runnerDir}${file}`;
  if (!type || !existsSync(path)) {
    res.statusCode = 404;
    res.setHeader("Content-Type", "text/plain");
    res.end("Not found");
    return;
  }
  res.setHeader("Content-Type", type);
  res.setHeader("Content-Security-Policy", file === "runner.html" ? policy("runner") : file === "exec.html" ? policy("exec") : policies.other);
  res.setHeader("X-Content-Type-Options", "nosniff");
  res.setHeader("Referrer-Policy", "no-referrer");
  res.setHeader("Cross-Origin-Opener-Policy", "same-origin");
  res.setHeader("Cross-Origin-Resource-Policy", "cross-origin");
  res.setHeader("Access-Control-Allow-Origin", "*");
  res.end(readFileSync(path));
}

/** Server-sent events of a streamed tutor answer, a few hundred ms apart; honours Last-Event-ID. */
function streamRun(req, res, runId) {
  if (runId === RUN_GONE) {
    res.statusCode = 404;
    res.end(JSON.stringify({ message: "Not found (mock)" }));
    return;
  }
  res.setHeader("Content-Type", "text/event-stream");
  res.setHeader("Cache-Control", "no-cache");
  const after = Number(req.headers["last-event-id"] ?? 0);
  const events = TUTOR_EVENTS(runId).map((event, i) => ({ id: i + 1, event }));
  res.write("retry: 1000\n\n");
  let i = events.findIndex((e) => e.id > after);
  if (i < 0) return res.end();
  // the draft stays on screen for about a second and a half before the final answer replaces it
  const delays = runId === RUN_ERROR ? [50, 150] : [50, 100, 200, 300, 400, 1800, 1850];
  const timers = events.slice(i).map((e) => setTimeout(() => {
    res.write(`id: ${e.id}\ndata: ${JSON.stringify(e.event)}\n\n`);
    if (e.id === events.length) res.end();
  }, delays[e.id - 1]));
  req.on("close", () => timers.forEach(clearTimeout));
}

function respond(req, res, path, route, rawBody) {
  if (!route) {
    res.statusCode = 404;
    res.end(JSON.stringify({ message: "Not found (mock)" }));
    return;
  }
  const tenant = String(req.headers["x-ulams-api-host"] ?? "").split(".")[0];
  const body = (() => {
    try {
      return rawBody ? JSON.parse(rawBody) : null;
    } catch {
      return null;
    }
  })();
  const match = route[1].exec(path);
  const data = path === "/api/settings" ? (settingsByTenant[tenant] ?? coffee.settings) : route[2]({ tenant, match, body, query: new URL(req.url ?? "/", "http://mock").searchParams, status: (code) => (res.statusCode = code) });
  if (data && typeof data === "object" && "__status" in data) {
    res.statusCode = data.__status;
    res.end(JSON.stringify({ success: false, message: data.message }));
    return;
  }
  res.end(JSON.stringify({ success: true, data, meta: { current_page: 1, last_page: 1, per_page: 24, total: 1 } }));
}

const port = Number(process.env.MOCK_API_PORT ?? 4390);
server.listen(port, "127.0.0.1", () => console.log(`mock tenant API on :${port}`));
