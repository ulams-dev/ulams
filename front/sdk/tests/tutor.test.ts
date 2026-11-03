import { describe, expect, it, vi } from "vitest";
import { ApiError } from "../src/client.ts";
import { createTutorClient, TutorRunError, tutorFailure } from "../src/tutor.ts";

function fakeFetch(responses: Array<{ status?: number; body?: unknown; throws?: boolean }>) {
  const calls: Array<{ url: string; init: RequestInit }> = [];
  const fn = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
    calls.push({ url: String(url), init: init ?? {} });
    const next = responses.shift() ?? { status: 500, body: { message: "no more" } };
    if (next.throws) throw new TypeError("offline");
    return new Response(next.body === undefined ? "" : JSON.stringify(next.body), { status: next.status ?? 200 });
  });
  return { fn: fn as unknown as typeof fetch, calls };
}

const status = { enabled: true, aiEnabled: true, remaining: 29, dailyLimit: 30, attemptActive: false };

describe("tutor client", () => {
  it("reads the status through the BFF", async () => {
    const { fn, calls } = fakeFetch([{ body: { success: true, data: status } }]);
    const client = createTutorClient({ baseUrl: "/bff", fetch: fn });
    expect(await client.status(5)).toEqual(status);
    expect(calls[0]!.url).toBe("/bff/api/tutor/courses/5/status");
    expect(calls[0]!.init.method).toBe("GET");
    expect((calls[0]!.init.headers as Record<string, string>).Authorization).toBeUndefined();
  });

  it("asks with a JSON body, sending the conversation and topic only when given", async () => {
    const answer = { conversationId: "c1", messageId: "m1", answer: "Because.", citations: [], refusal: "none", inScope: true, remaining: 28, label: "AI tutor" };
    const { fn, calls } = fakeFetch([{ body: { data: answer } }, { body: { data: answer } }]);
    const client = createTutorClient({ baseUrl: "http://coffee.localhost/", token: "tok", fetch: fn });
    expect((await client.ask(5, { text: "Why?" })).answer).toBe("Because.");
    await client.ask(5, { text: "And?", conversationId: "c1", topicId: 12 });
    expect(calls[0]!.url).toBe("http://coffee.localhost/api/tutor/courses/5/messages");
    expect(JSON.parse(String(calls[0]!.init.body))).toEqual({ text: "Why?" });
    expect(JSON.parse(String(calls[1]!.init.body))).toEqual({ text: "And?", conversationId: "c1", topicId: 12 });
    const headers = calls[0]!.init.headers as Record<string, string>;
    expect(headers.Authorization).toBe("Bearer tok");
    expect(headers["Content-Type"]).toBe("application/json");
  });

  it("lists, opens and deletes conversations", async () => {
    const summary = { id: "01J", lastMessageAt: "2026-10-21T10:00:00+00:00", expiresAt: "2027-01-19T10:00:00+00:00" };
    const full = { id: "01J", courseId: 5, expiresAt: summary.expiresAt, messages: [] };
    const { fn, calls } = fakeFetch([{ body: { data: [summary] } }, { body: { data: full } }, { body: { data: { deleted: true } } }, { body: { data: null } }]);
    const client = createTutorClient({ baseUrl: "/bff", fetch: fn });
    expect(await client.conversations(5)).toEqual([summary]);
    expect(await client.conversation("01J")).toEqual(full);
    await client.deleteConversation("01J");
    expect(calls.map((c) => `${c.init.method} ${c.url}`)).toEqual([
      "GET /bff/api/tutor/courses/5/conversations",
      "GET /bff/api/tutor/conversations/01J",
      "DELETE /bff/api/tutor/conversations/01J",
    ]);
    expect(await client.conversations(5)).toEqual([]);
  });

  it("turns failures into ApiError and classifies them", async () => {
    const { fn } = fakeFetch([
      { status: 429, body: { message: "You have used today's questions.", reason: "limit" } },
      { status: 503, body: { message: "off", reason: "disabled" } },
      { throws: true },
      { status: 403, body: { message: "no" } },
    ]);
    const client = createTutorClient({ baseUrl: "/bff", fetch: fn });
    const errors: unknown[] = [];
    for (let i = 0; i < 4; i++) errors.push(await client.ask(5, { text: "Why?" }).catch((e: unknown) => e));
    expect(errors[0]).toBeInstanceOf(ApiError);
    expect((errors[0] as ApiError).message).toBe("You have used today's questions.");
    expect(errors.map(tutorFailure)).toEqual(["limit", "disabled", "network", "forbidden"]);
    expect(tutorFailure(new Error("x"))).toBe("unknown");
  });
});

describe("streamed answers", () => {
  const run = { conversationId: "c1", runId: "01jrun00000000000000000ok", questionId: "q1" };
  const final = { conversationId: "c1", messageId: "m1", questionId: "q1", answer: "Robusta is Coffea canephora.", citations: [{ chunkId: "c3", label: "Species", topicId: 3, fragmentIds: [] }], refusal: "none", inScope: true, remaining: 28, label: "AI tutor" };
  const sse = (events: Array<Record<string, unknown>>, startId = 1) => events.map((e, i) => `id: ${startId + i}\ndata: ${JSON.stringify(e)}\n\n`).join("");
  const events = [
    { type: "RUN_STARTED", runId: run.runId },
    { type: "TEXT_MESSAGE_START", messageId: "m", role: "assistant" },
    { type: "TEXT_MESSAGE_CONTENT", messageId: "m", delta: "Robusta is " },
    { type: "TEXT_MESSAGE_CONTENT", messageId: "m", delta: "Coffea canephora, I think." },
    { type: "TEXT_MESSAGE_END", messageId: "m" },
    { type: "CUSTOM", name: "tutor.final", value: final },
    { type: "RUN_FINISHED", runId: run.runId },
  ];

  function router(handlers: Record<string, (init: RequestInit) => Response>) {
    const calls: Array<{ key: string; init: RequestInit }> = [];
    const fn = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
      const key = `${init?.method ?? "GET"} ${String(url)}`;
      calls.push({ key, init: init ?? {} });
      const handler = handlers[key];
      return handler ? handler(init ?? {}) : new Response(JSON.stringify({ message: "nope" }), { status: 404 });
    });
    return { fn: fn as unknown as typeof fetch, calls };
  }
  const json = (status: number, data: unknown) => new Response(JSON.stringify({ data }), { status });
  const events200 = (body: string) => new Response(body, { status: 200, headers: { "Content-Type": "text/event-stream" } });
  const EVENTS = `GET /bff/api/tutor/runs/${run.runId}/events`;

  it("posts stream: true, hands out the run and the draft, and resolves with the validated answer", async () => {
    const { fn, calls } = router({
      "POST /bff/api/tutor/courses/5/messages": () => json(202, run),
      [EVENTS]: () => events200(sse(events)),
    });
    const drafts: string[] = [];
    const runs: unknown[] = [];
    const client = createTutorClient({ baseUrl: "/bff", fetch: fn });

    const answer = await client.askStream(5, { text: "Why?", topicId: 3 }, { onDraft: (t) => drafts.push(t), onRun: (r) => runs.push(r) });

    expect(JSON.parse(String(calls[0]!.init.body))).toEqual({ text: "Why?", topicId: 3, stream: true });
    expect(runs).toEqual([run]);
    expect(drafts).toEqual(["Robusta is ", "Robusta is Coffea canephora, I think."]);
    // the final event wins over the draft
    expect(answer).toEqual(final);
    expect((calls[1]!.init.headers as Record<string, string>).Accept).toBe("text/event-stream");
  });

  it("answers at once when the operator switched streaming off (the API answers 200)", async () => {
    const plain = { ...final, answer: "Plain." };
    const { fn, calls } = router({ "POST /bff/api/tutor/courses/5/messages": () => json(200, plain) });
    const answer = await createTutorClient({ baseUrl: "/bff", fetch: fn }).askStream(5, { text: "Why?" });
    expect(answer.answer).toBe("Plain.");
    expect(calls).toHaveLength(1);
  });

  it("keeps the API's refusals: a second question while one runs is a busy ApiError", async () => {
    const { fn } = router({ "POST /bff/api/tutor/courses/5/messages": () => new Response(JSON.stringify({ message: "busy", reason: "busy" }), { status: 409 }) });
    const error = await createTutorClient({ baseUrl: "/bff", fetch: fn }).askStream(5, { text: "Again?" }).catch((e) => e);
    expect(error).toBeInstanceOf(ApiError);
    expect(tutorFailure(error)).toBe("busy");
  });

  it("rejects with a TutorRunError when the run ends with RUN_ERROR", async () => {
    const { fn } = router({
      "POST /bff/api/tutor/courses/5/messages": () => json(202, run),
      [EVENTS]: () => events200(sse([{ type: "RUN_STARTED" }, { type: "RUN_ERROR", code: "timeout", message: "Too slow." }])),
    });
    const error = await createTutorClient({ baseUrl: "/bff", fetch: fn }).askStream(5, { text: "Why?" }).catch((e) => e);
    expect(error).toBeInstanceOf(TutorRunError);
    expect(error.code).toBe("timeout");
  });

  it("resumes after a dropped connection with Last-Event-ID, without repeating the draft", async () => {
    let n = 0;
    const { fn, calls } = router({
      "POST /bff/api/tutor/courses/5/messages": () => json(202, run),
      [EVENTS]: () => (n++ === 0 ? events200(sse(events.slice(0, 3))) : events200(sse(events.slice(3), 4))),
    });
    const drafts: string[] = [];
    const answer = await createTutorClient({ baseUrl: "/bff", fetch: fn }).askStream(5, { text: "Why?" }, { onDraft: (t) => drafts.push(t) });
    expect(answer).toEqual(final);
    expect((calls[2]!.init.headers as Record<string, string>)["Last-Event-ID"]).toBe("3");
    expect(drafts).toEqual(["Robusta is ", "Robusta is Coffea canephora, I think."]);
  });

  it("rejoins a run after a reload (followRun replays the events)", async () => {
    const { fn } = router({ [EVENTS]: () => events200(sse(events)) });
    expect(await createTutorClient({ baseUrl: "/bff", fetch: fn }).followRun(run)).toEqual(final);
  });

  it("falls back to the conversation when the events cannot be opened, without asking twice", async () => {
    const { fn, calls } = router({
      "POST /bff/api/tutor/courses/5/messages": () => json(202, run),
      [EVENTS]: () => new Response("{}", { status: 404 }),
      "GET /bff/api/tutor/conversations/c1": () =>
        json(200, { id: "c1", courseId: 5, expiresAt: "x", messages: [
          { id: "q1", role: "learner", text: "Why?", citations: [], refusal: null, at: "t" },
          { id: "m9", role: "tutor", text: "Stored answer.", citations: [], refusal: "none", at: "t" },
        ] }),
      "GET /bff/api/tutor/courses/5/status": () => json(200, status),
    });
    const answer = await createTutorClient({ baseUrl: "/bff", fetch: fn }).askStream(5, { text: "Why?" });
    expect(answer).toMatchObject({ answer: "Stored answer.", messageId: "m9", questionId: "q1", remaining: 29 });
    expect(calls.filter((c) => c.key.startsWith("POST"))).toHaveLength(1);
  });

  it("stops when the caller aborts", async () => {
    const controller = new AbortController();
    const { fn } = router({
      "POST /bff/api/tutor/courses/5/messages": () => json(202, run),
      [EVENTS]: () => new Response(new ReadableStream({ start() { controller.abort(); } }), { status: 200 }),
    });
    const error = await createTutorClient({ baseUrl: "/bff", fetch: fn }).askStream(5, { text: "Why?" }, { signal: controller.signal }).catch((e) => e);
    expect(error).toBeInstanceOf(TutorRunError);
    expect(error.code).toBe("aborted");
  });

  it("classifies 409 as busy", () => {
    expect(tutorFailure(new ApiError(409, "/x", null, "busy"))).toBe("busy");
  });
});
