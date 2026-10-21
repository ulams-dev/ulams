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
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";

const fixture = (name) => JSON.parse(readFileSync(fileURLToPath(new URL(`../fixtures/${name}.json`, import.meta.url)), "utf8"));
const coffee = fixture("coffee");
// Two tenants share the content: "coffee" has the light theme, "nightsky" the dark one. The web server reaches
// the API through ULAMS_API_INTERNAL_ORIGIN and names the tenant in X-Ulams-Api-Host.
const settingsByTenant = { coffee: coffee.settings, nightsky: fixture("nightsky").settings };
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

const routes = [
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
  const data = path === "/api/settings" ? (settingsByTenant[tenant] ?? coffee.settings) : route[2]({ tenant, match, body, status: (code) => (res.statusCode = code) });
  res.end(JSON.stringify({ success: true, data, meta: { current_page: 1, last_page: 1, per_page: 24, total: 1 } }));
}

const port = Number(process.env.MOCK_API_PORT ?? 4390);
server.listen(port, "127.0.0.1", () => console.log(`mock tenant API on :${port}`));
