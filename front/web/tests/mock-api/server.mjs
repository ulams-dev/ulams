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
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";

const fixture = (name) => JSON.parse(readFileSync(fileURLToPath(new URL(`../fixtures/${name}.json`, import.meta.url)), "utf8"));
const coffee = fixture("coffee");
// Two tenants share the content: "coffee" has the light theme, "nightsky" the dark one. The web server reaches
// the API through ULAMS_API_INTERNAL_ORIGIN and names the tenant in X-Ulams-Api-Host.
// "polski" is the coffee academy with Polish as the tenant default language (account pages follow it, ADR 0106)
const settingsByTenant = {
  coffee: coffee.settings,
  nightsky: fixture("nightsky").settings,
  polski: { ...coffee.settings, global: { ...coffee.settings.global, language: "pl" } },
};
const program = fixture("coffee-program");

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
program.lessons[0].topics = [...program.lessons[0].topics, quizTopic];

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
const findAssignment = (tenant, id) => Object.values(supportOf(tenant)).flat().find((i) => i.assignmentId === id);

// Course tutor: on, 29 of 30 questions left, one earlier conversation; every question gets the same cited answer.
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

const routes = [
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
    /^\/api\/learner-insights\/assignments\/([0-9A-Za-z]{26})\/hide$/,
    ({ tenant, match }) => {
      const found = findAssignment(tenant, match[1]);
      if (found) found.hidden = true;
      return { hidden: true };
    },
  ],
  ["GET", /^\/api\/tutor\/courses\/1\/status$/, () => tutorStatus],
  ["GET", /^\/api\/tutor\/courses\/1\/conversations$/, () => [tutorEarlier]],
  ["POST", /^\/api\/tutor\/courses\/1\/messages$/, () => tutorAnswer],
  ["GET", /^\/api\/tutor\/conversations\/01JMOCKEARLIER0000000000A$/, () => ({ id: tutorEarlier.id, courseId: 1, expiresAt: tutorEarlier.expiresAt, messages: [
    { id: "01JMOCKMSG1", role: "learner", text: "Which species is Robusta?", citations: [], refusal: null, at: tutorEarlier.lastMessageAt },
    { id: "01JMOCKMSG2", role: "tutor", text: tutorAnswer.answer, citations: tutorAnswer.citations, refusal: "none", at: tutorEarlier.lastMessageAt },
  ] })],
  ["DELETE", /^\/api\/tutor\/conversations\/01JMOCKEARLIER0000000000A$/, () => ({ deleted: true })],
  ["GET", /^\/api\/settings$/, () => coffee.settings],
  ["GET", /^\/api\/courses$/, () => [coffee.course]],
  ["GET", /^\/api\/courses\/1$/, () => program],
  ["GET", /^\/api\/courses\/1\/program$/, () => program],
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

const server = createServer((req, res) => {
  const url = new URL(req.url ?? "/", "http://mock");
  const path = url.pathname;
  res.setHeader("Content-Type", "application/json");
  // test control: forget what a tenant changed (the suite calls this before a journey that changes state)
  if (req.method === "POST" && path === "/__mock/reset") {
    req.resume();
    supportState.delete(url.searchParams.get("tenant") ?? "");
    res.end(JSON.stringify({ reset: true }));
    return;
  }
  if (req.method === "GET" && /^\/api\/pdfs\/generate\/9$/.test(path)) {
    req.resume();
    res.setHeader("Content-Type", "application/pdf");
    res.setHeader("Content-Disposition", 'attachment; filename="espresso-basics-9.pdf"');
    res.end(PDF);
    return;
  }
  const route = routes.find(([method, pattern]) => method === req.method && pattern.test(path));
  const chunks = [];
  req.on("data", (chunk) => chunks.push(chunk));
  req.on("end", () => respond(req, res, path, route, Buffer.concat(chunks).toString("utf8")));
});

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
  const data = path === "/api/settings" ? (settingsByTenant[tenant] ?? coffee.settings) : route[2]({ tenant, match, body });
  res.end(JSON.stringify({ success: true, data, meta: { current_page: 1, last_page: 1, per_page: 24, total: 1 } }));
}

const port = Number(process.env.MOCK_API_PORT ?? 4390);
server.listen(port, "127.0.0.1", () => console.log(`mock tenant API on :${port}`));
