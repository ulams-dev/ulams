import type { Handler, RecordedRequest, Reply } from "./helpers.ts";

/**
 * A small in-memory course builder (the routes of api/packages/course-builder) with the real state
 * machine: draft -> ingesting -> interviewing -> outlining -> outline_review -> generating ->
 * apply_review -> applying -> applied. Runs finish after `ticks` polls, so `--wait` loops are
 * exercised without timers.
 */
export interface FakeBuilderOptions {
  /** Polls until a run finishes. */
  ticks?: number;
  /** Fail the interview run. */
  failInterview?: boolean;
  /** AI switched off. */
  aiDisabled?: boolean;
  /** Keep the SSE answer open-ended: events are only those appended so far. */
}

interface Run {
  id: string;
  sessionId: string;
  kind: string;
  status: "queued" | "running" | "succeeded" | "failed";
  ticks: number;
  error: string | null;
  onFinish: () => void;
}

const ok = (data: unknown, status = 200): Reply => ({ status, body: { success: true, data, message: "OK" } });
const fail = (message: string, status: number): Reply => ({ status, body: { success: false, message } });

const QUESTIONS = [
  { key: "audience", label: "Who is it for?", component: "ChoiceChips", options: [{ value: "new baristas", label: "New baristas" }], defaultValue: "new baristas" },
  { key: "level", label: "How advanced?", component: "ChoiceChips", options: [{ value: "beginner" }, { value: "intermediate" }], defaultValue: "beginner" },
  { key: "duration", label: "How long?", component: "DurationSlider", options: [{ value: "60" }], defaultValue: { totalMinutes: 60, lessonMinutes: 10 } },
];

export function fakeBuilder(options: FakeBuilderOptions = {}) {
  const ticks = options.ticks ?? 2;
  let seq = 0;
  const id = (p: string) => `${p}${String(++seq).padStart(4, "0")}`.padEnd(26, "0");
  const db = {
    session: null as null | { id: string; title: string | null; status: string; courseId: number | null; currentVersionId: string | null; appliedVersionId: string | null },
    runs: new Map<string, Run>(),
    events: [] as Array<{ id: number; payload: Record<string, unknown> }>,
    answered: new Set<string>(),
    versions: [] as Array<{ id: string; number: number; kind: string; status: string; reason: string | null; document: Record<string, unknown> }>,
    published: false,
    uploads: [] as string[],
    brief: { audience: "", level: "beginner", totalMinutes: 60, lessonMinutes: 10, tone: "friendly", language: "en" } as Record<string, unknown>,
    posts: [] as Array<{ path: string; body: unknown }>,
  };
  const append = (payload: Record<string, unknown>) => db.events.push({ id: db.events.length + 1, payload });

  const blueprint = (suffix: string) => ({
    course: { id: "crs_1", title: `Coffee ${suffix}`, objectives: [{ id: "obj_1", text: "Brew a good cup", citations: ["frg_aaaaaaaaaaaa"] }] },
    modules: [{ id: "mod_1", title: "Basics", lessons: [{ id: "les_1", title: "Grind", minutes: 10, objectives: [{ id: "obj_2", text: "Grind right" }], blocks: [{ id: "blk_1", kind: "paragraph", markdown: "Grind just before brewing.", citations: ["frg_aaaaaaaaaaaa"] }], quiz: null }] }],
    finalTest: null,
  });

  function interviewSurface() {
    const components = [
      { id: "root", component: "Column", children: [...QUESTIONS.map((q) => `q-${q.key}`), "decide"] },
      ...QUESTIONS.map((q, i) => {
        const first = QUESTIONS.findIndex((x) => !db.answered.has(x.key));
        return { id: `q-${q.key}`, component: q.component, questionKey: q.key, label: q.label, why: "because", status: db.answered.has(q.key) ? "answered" : i === first ? "open" : "upcoming", options: q.options, defaultValue: q.defaultValue, step: i + 1, total: QUESTIONS.length };
      }),
      { id: "decide", component: "DecideForMe", open: QUESTIONS.filter((q) => !db.answered.has(q.key)).length },
    ];
    append({
      type: "ACTIVITY_SNAPSHOT",
      messageId: "interview",
      activityType: "a2ui-surface",
      replace: true,
      content: { surfaceId: "interview", kind: "interview", messages: [{ createSurface: { surfaceId: "interview", catalogId: "c" } }, { updateComponents: { surfaceId: "interview", components } }] },
    });
  }

  function startRun(kind: string, onFinish: () => void, failWith: string | null = null): Run {
    const run: Run = { id: id("run"), sessionId: db.session!.id, kind, status: "running", ticks, error: failWith, onFinish };
    db.runs.set(run.id, run);
    append({ type: "RUN_STARTED", runId: run.id, threadId: run.sessionId });
    return run;
  }
  const active = () => [...db.runs.values()].find((r) => r.status === "running" || r.status === "queued");

  /** Every poll moves the active run one tick closer to done. */
  function tick() {
    const run = active();
    if (!run) return;
    if (--run.ticks > 0) return;
    if (run.error) {
      run.status = "failed";
      append({ type: "RUN_ERROR", runId: run.id, message: run.error, code: "error" });
      return;
    }
    run.status = "succeeded";
    run.onFinish();
    append({ type: "RUN_FINISHED", runId: run.id, threadId: run.sessionId });
  }

  const startInterview = () =>
    startRun(
      "interview",
      () => {
        db.session!.status = "interviewing";
        interviewSurface();
      },
      options.failInterview ? "The model refused to answer." : null
    );
  const startOutline = () => {
    db.session!.status = "outlining";
    return startRun("outline", () => {
      const v = { id: id("ver"), number: db.versions.length + 1, kind: "outline", status: "proposed", reason: null, document: blueprint("outline") };
      db.versions.push(v);
      db.session!.status = "outline_review";
      db.session!.currentVersionId = v.id;
    });
  };
  const startGenerate = () => {
    db.session!.status = "generating";
    return startRun("generate", () => {
      const v = { id: id("ver"), number: db.versions.length + 1, kind: "content", status: "proposed", reason: null, document: blueprint("content") };
      db.versions.push(v);
      db.session!.status = "apply_review";
      db.session!.currentVersionId = v.id;
    });
  };
  const startApply = () => {
    db.session!.status = "applying";
    return startRun("apply", () => {
      db.session!.status = "applied";
      db.session!.courseId = 77;
      db.session!.appliedVersionId = db.session!.currentVersionId;
    });
  };

  const snapshot = () => {
    const s = db.session!;
    const run = active();
    return {
      session: { ...s },
      brief: db.brief,
      briefRows: [],
      cost: { usedMicroUsd: 1234, budgetMicroUsd: 1_000_000 },
      sources: db.uploads.map((name, i) => ({ id: `src${i}`, name, status: "ready", kind: "markdown", tokens: 10, fragments: 3, error: null })),
      aiEnabled: !options.aiDisabled,
      budgetReached: false,
      activeRunId: run?.id ?? null,
      canUndo: false,
      canRedo: false,
      links: s.courseId ? { adminPath: `/courses/list/${s.courseId}` } : {},
    };
  };

  const runView = (r: Run) => ({
    id: r.id,
    sessionId: r.sessionId,
    kind: r.kind,
    status: r.status === "running" ? "running" : r.status,
    stage: null,
    needsAttention: false,
    steps: [{ id: "st1", name: r.kind, status: r.status === "succeeded" ? "succeeded" : r.status === "failed" ? "failed" : "running", error: r.error }],
    startedAt: "2026-10-09T10:00:00+00:00",
    finishedAt: r.status === "running" ? null : "2026-10-09T10:01:00+00:00",
    error: r.error,
  });

  const P = "/api/admin/course-builder";
  const handler: Handler = (req: RecordedRequest): Reply => {
    if (!req.path.startsWith(P)) return fail(`no mock for ${req.method} ${req.path}`, 404);
    const path = req.path.slice(P.length);
    const m = req.method;
    const body = (req.body ?? {}) as Record<string, unknown>;
    let r: RegExpMatchArray | null;
    if (m !== "GET") db.posts.push({ path: `${m} ${path}`, body: req.body });

    if (m === "POST" && path === "/sessions") {
      db.session = { id: id("ses"), title: (body.title as string) ?? null, status: "draft", courseId: null, currentVersionId: null, appliedVersionId: null };
      return ok(snapshot(), 201);
    }
    if (m === "GET" && path === "/sessions") return ok(db.session ? [{ ...db.session }] : []);
    if (!db.session) return fail("Session not found.", 404);
    if ((r = path.match(/^\/sessions\/([^/]+)(\/.*)?$/)) && r[1] !== db.session.id) return fail("Session not found.", 404);
    const sub = r?.[2] ?? "";
    if (r) {
      if (m === "GET" && sub === "") {
        tick();
        return ok(snapshot());
      }
      if (m === "DELETE" && sub === "") return ok({ id: db.session.id });
      if (m === "POST" && sub === "/sources") {
        const file = req.form?.get("file") as File | null;
        if (!file) return fail("The file field is required.", 422);
        db.uploads.push(file.name);
        db.session.status = "ingesting";
        const run = startRun("ingest", () => {
          if (options.aiDisabled) db.session!.status = "draft";
          else startInterview();
        });
        return ok({ source: { id: `src${db.uploads.length - 1}`, name: file.name, status: "uploaded" }, runId: run.id }, 202);
      }
      if (m === "GET" && sub.startsWith("/events")) {
        const after = Number(req.headers["last-event-id"] ?? req.query.after ?? 0);
        const lines = [`retry: 1000\n\ndata: ${JSON.stringify({ type: "STATE_SNAPSHOT", snapshot: snapshot() })}\n\n`];
        for (const e of db.events) if (e.id > after) lines.push(`id: ${e.id}\ndata: ${JSON.stringify(e.payload)}\n\n`);
        return { raw: lines.join(""), headers: { "content-type": "text/event-stream" } };
      }
      if (m === "GET" && sub === "/brief") return ok({ brief: db.brief, rows: [] });
      if (m === "PUT" && sub === "/brief") {
        db.brief = { ...db.brief, ...(body.brief as object) };
        return ok({ brief: db.brief, stale: false });
      }
      if (m === "GET" && sub === "/versions") {
        return ok({ currentVersionId: db.session.currentVersionId, appliedVersionId: db.session.appliedVersionId, versions: db.versions.map(({ document: _d, ...v }) => ({ ...v, origin: "ai", createdAt: "2026-10-09T10:00:00+00:00" })) });
      }
      if (m === "GET" && sub === "/usage") return ok({ total: { usedMicroUsd: 1234 }, byTask: [] });
      if (m === "POST" && sub === "/apply") return ok({ runId: startApply().id }, 202);
      if (m === "POST" && sub === "/publish") {
        if (db.session.courseId === null) return fail("Apply the course before publishing it.", 409);
        db.published = true;
        return ok({ courseId: db.session.courseId, published: true });
      }
      if (m === "POST" && sub === "/runs") {
        const props = (body.forwardedProps ?? {}) as { action?: { name: string; context?: { key?: string; value?: unknown } }; selection?: { elementId?: string } };
        const a = props.action;
        if (a?.name === "answer") {
          const key = a.context?.key ?? "";
          if (!QUESTIONS.some((q) => q.key === key)) return ok({ runId: null, accepted: false, message: "That answer does not fit this question." }, 202);
          db.answered.add(key);
          interviewSurface();
          if (db.answered.size === QUESTIONS.length) return ok({ runId: startOutline().id, accepted: true, message: null }, 202);
          return ok({ runId: null, accepted: true, message: null }, 202);
        }
        if (a?.name === "decide_for_me") {
          for (const q of QUESTIONS) db.answered.add(q.key);
          interviewSurface();
          return ok({ runId: startOutline().id, accepted: true, message: null }, 202);
        }
        if (props.selection?.elementId) {
          const text = (body.messages as Array<{ content: string }>)[0]?.content ?? "";
          const run = startRun("patch", () => {
            db.versions.push({ id: id("ver"), number: db.versions.length + 1, kind: "patch", status: "proposed", reason: text, document: blueprint("patched") });
          });
          return ok({ runId: run.id, accepted: true, message: null }, 202);
        }
        return fail("Send a message or an action.", 422);
      }
    }
    if ((r = path.match(/^\/runs\/([^/]+)$/)) && m === "GET") {
      tick();
      const run = db.runs.get(r[1] as string);
      return run ? ok(runView(run)) : fail("Run not found.", 404);
    }
    if ((r = path.match(/^\/runs\/([^/]+)\/cancel$/)) && m === "POST") {
      const run = db.runs.get(r[1] as string);
      if (run) run.status = "failed";
      return ok({ id: r[1], status: "cancelled" });
    }
    if ((r = path.match(/^\/versions\/([^/]+)(\/(.*))?$/))) {
      const v = db.versions.find((x) => x.id === r![1]);
      if (!v) return fail("Version not found.", 404);
      const verb = r[3] ?? "";
      if (m === "GET" && verb === "") return ok({ id: v.id, number: v.number, kind: v.kind, origin: "ai", status: v.status, reason: v.reason, parentId: null, elementId: null, document: v.document, fragments: {}, createdAt: "2026-10-09T10:00:00+00:00" });
      if (m === "GET" && verb === "diff") return ok({ against: null, changes: [{ id: "blk_1", kind: "changed", type: "paragraph", label: "Grind" }] });
      if (m === "POST" && verb === "approve") {
        if (v.status !== "proposed") return fail("This version is not waiting for a decision.", 409);
        v.status = "approved";
        if (v.kind === "outline") return ok({ runId: startGenerate().id, state: snapshot() });
        db.session.currentVersionId = v.id;
        return ok({ runId: db.session.courseId ? startApply().id : null, state: snapshot() });
      }
      if (m === "POST" && verb === "reject") {
        if (v.status !== "proposed") return fail("This version is not waiting for a decision.", 409);
        v.status = "rejected";
        return ok({ runId: v.kind === "outline" ? startOutline().id : null, state: snapshot() });
      }
    }
    return fail(`no mock for ${m} ${req.path}`, 404);
  };

  return { handler, db, answer: (key: string) => db.answered.add(key) };
}
