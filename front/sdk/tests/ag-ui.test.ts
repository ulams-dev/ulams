import { describe, expect, it, vi } from "vitest";
import { EventSchemas } from "@ag-ui/core/schemas";
import { applyJsonPatch, connectEventStream, SseParser, surfaceFromEvent, type AgUiEvent } from "../src/ag-ui.ts";

const encoder = new TextEncoder();

function streamOf(chunks: string[]): ReadableStream<Uint8Array> {
  return new ReadableStream({
    start(controller) {
      for (const chunk of chunks) controller.enqueue(encoder.encode(chunk));
      controller.close();
    },
  });
}

describe("SseParser", () => {
  it("parses messages split at any byte boundary", () => {
    const text = 'retry: 1000\n\ndata: {"type":"STATE_SNAPSHOT","snapshot":{}}\n\nid: 7\ndata: {"type":"RUN_STARTED",\ndata: "runId":"r"}\n\n: ping\n\nid: 8\r\ndata: {"type":"RUN_FINISHED"}\r\n\r\n';
    for (let split = 1; split < text.length; split += 7) {
      const parser = new SseParser();
      const out = [...parser.push(text.slice(0, split)), ...parser.push(text.slice(split))];
      expect(out.map((m) => m.data)).toEqual([
        '{"type":"STATE_SNAPSHOT","snapshot":{}}',
        '{"type":"RUN_STARTED",\n"runId":"r"}',
        '{"type":"RUN_FINISHED"}',
      ]);
      expect(out.map((m) => m.id)).toEqual([null, "7", "8"]);
      expect(parser.lastEventId).toBe("8");
    }
  });

  it("reads retry and ignores comments", () => {
    const parser = new SseParser();
    const [message] = parser.push("retry: 2500\ndata: x\n\n: comment\n\n");
    expect(message?.retry).toBe(2500);
  });
});

describe("connectEventStream", () => {
  it("resumes with Last-Event-ID after the server closes the connection", async () => {
    const calls: Array<Record<string, string>> = [];
    const controller = new AbortController();
    const seen: string[] = [];
    const bodies = [
      ['id: 1\ndata: {"type":"RUN_STARTED","threadId":"s","runId":"r"}\n\n', 'id: 2\ndata: {"type":"STEP_STARTED","stepName":"lessons"}\n\n'],
      ['id: 3\ndata: {"type":"RUN_FINISHED","threadId":"s","runId":"r"}\n\n'],
    ];
    const fakeFetch = vi.fn(async (_url: RequestInfo | URL, init?: RequestInit) => {
      calls.push({ ...(init?.headers as Record<string, string>) });
      const body = bodies.shift();
      if (!body) {
        controller.abort();
        return new Response(streamOf([]), { status: 200 });
      }
      return new Response(streamOf(body), { status: 200, headers: { "Content-Type": "text/event-stream" } });
    });

    await connectEventStream({
      url: "/events",
      fetch: fakeFetch as unknown as typeof fetch,
      retryMs: 1,
      signal: controller.signal,
      onEvent: (event, id) => seen.push(`${id}:${event.type}`),
    });

    expect(seen).toEqual(["1:RUN_STARTED", "2:STEP_STARTED", "3:RUN_FINISHED"]);
    expect(calls[0]!["Last-Event-ID"]).toBeUndefined();
    expect(calls[1]!["Last-Event-ID"]).toBe("2");
    expect(calls[2]!["Last-Event-ID"]).toBe("3");
  });

  it("stops on 403 and reports the status", async () => {
    const statuses: string[] = [];
    await connectEventStream({
      url: "/events",
      fetch: (async () => new Response("", { status: 403 })) as unknown as typeof fetch,
      onEvent: () => undefined,
      onStatus: (s) => statuses.push(s),
    });
    expect(statuses).toEqual(["connecting", "error"]);
  });

  it("backs off and reconnects after a network error", async () => {
    const controller = new AbortController();
    let n = 0;
    const statuses: string[] = [];
    await connectEventStream({
      url: "/events",
      retryMs: 1,
      signal: controller.signal,
      fetch: (async () => {
        n += 1;
        if (n === 1) throw new Error("offline");
        controller.abort();
        return new Response(streamOf([]), { status: 200 });
      }) as unknown as typeof fetch,
      onEvent: () => undefined,
      onStatus: (s) => statuses.push(s),
    });
    expect(n).toBe(2);
    expect(statuses).toContain("reconnecting");
  });
});

describe("events from the API follow the AG-UI schemas", () => {
  // shapes produced by api/packages/course-builder/src/Events/EventLog.php
  const events: AgUiEvent[] = [
    { type: "RUN_STARTED", threadId: "01s", runId: "01r", timestamp: 1 },
    { type: "STEP_STARTED", stepName: "lessons", timestamp: 1 },
    { type: "STEP_FINISHED", stepName: "lessons", timestamp: 1 },
    { type: "TEXT_MESSAGE_START", messageId: "msg_1", role: "assistant", timestamp: 1 },
    { type: "TEXT_MESSAGE_CONTENT", messageId: "msg_1", delta: "Hello", timestamp: 1 },
    { type: "TEXT_MESSAGE_END", messageId: "msg_1", timestamp: 1 },
    { type: "STATE_SNAPSHOT", snapshot: { session: { id: "01s" } } },
    { type: "STATE_DELTA", delta: [{ op: "replace", path: "/cost", value: { usedMicroUsd: 5 } }], timestamp: 1 },
    {
      type: "ACTIVITY_SNAPSHOT",
      messageId: "interview",
      activityType: "a2ui-surface",
      replace: true,
      content: { surfaceId: "interview", messages: [] },
      timestamp: 1,
    },
    { type: "CUSTOM", name: "applied", value: { courseId: 1 }, timestamp: 1 },
    { type: "RUN_ERROR", message: "Budget reached", code: "budget", timestamp: 1 },
    { type: "RUN_FINISHED", threadId: "01s", runId: "01r", timestamp: 1 },
  ];
  it.each(events.map((e) => [e.type, e]))("%s", (_type, event) => {
    expect(EventSchemas.safeParse(event).success).toBe(true);
  });
});

describe("A2UI helpers", () => {
  it("builds a surface from an activity snapshot", () => {
    const surface = surfaceFromEvent({
      type: "ACTIVITY_SNAPSHOT",
      messageId: "outline-1",
      activityType: "a2ui-surface",
      content: {
        surfaceId: "outline-1",
        kind: "outline",
        messages: [
          { version: "v0.9", createSurface: { surfaceId: "outline-1", catalogId: "https://ulams.dev/catalogue/builder/v1" } },
          { version: "v0.9", updateComponents: { surfaceId: "outline-1", components: [{ id: "root", component: "OutlineDiff", versionId: "v" }] } },
        ],
      },
    });
    expect(surface?.kind).toBe("outline");
    expect(surface?.catalogId).toBe("https://ulams.dev/catalogue/builder/v1");
    expect(surface?.components[0]?.component).toBe("OutlineDiff");
    expect(surfaceFromEvent({ type: "CUSTOM", name: "x" })).toBeNull();
  });

  it("applies STATE_DELTA patches", () => {
    const state = { cost: { usedMicroUsd: 1 }, brief: { tone: "friendly" } };
    const next = applyJsonPatch(state, [
      { op: "replace", path: "/cost", value: { usedMicroUsd: 9 } },
      { op: "add", path: "/budgetReached", value: true },
      { op: "remove", path: "/brief/tone" },
    ]);
    expect(next).toEqual({ cost: { usedMicroUsd: 9 }, brief: {}, budgetReached: true });
    expect(state.cost.usedMicroUsd).toBe(1);
  });
});
