import { z } from "zod";
import { getOperation, operationKinds, registerOperationKind, waitOperation } from "../http/lro.ts";
import { defineCommand } from "./define.ts";

registerOperationKind({
  kind: "video",
  describe: "Video processing of a video topic: video:<topic id>",
  async get(ctx, id) {
    const res = await ctx.client.call<Array<{ topic_id?: number; topic?: { id?: number }; state?: string; status?: string; error?: string }>>(
      "GET",
      "/api/admin/video/states",
      { query: { per_page: 100 }, idempotent: true }
    );
    const row = (Array.isArray(res.data) ? res.data : []).find((r) => String(r.topic_id ?? r.topic?.id) === id);
    if (!row) return { status: "succeeded", data: { note: "no processing state recorded; nothing to wait for" } };
    const state = String(row.state ?? row.status ?? "");
    if (state === "finished") return { status: "succeeded", data: row };
    if (state === "error") return { status: "failed", data: row, error: row.error ?? "video processing failed" };
    return { status: "running", data: row };
  },
});

const common = { anonymous: false, audience: ["any" as const], scopes: [] as string[], mcp: { toolset: "core" } };

export const operationCommands = [
  defineCommand({
    ...common,
    id: "operations.get",
    summary: "Show the state of a long-running operation by its handle",
    description: "Handles come from commands run with --no-wait (or from a timeout, exit 10): <kind>:<id>, e.g. video:42.",
    kind: "read",
    positionals: ["handle"],
    input: z.object({ handle: z.string().describe("Operation handle <kind>:<id>.") }),
    output: z.unknown(),
    examples: [{ title: "Check a video", argv: "operations get video:42 --json" }],
    async run(ctx, i) {
      return { data: await getOperation(ctx, i.handle) };
    },
  }),
  defineCommand({
    ...common,
    id: "operations.wait",
    summary: "Wait until an operation finishes (exit 10 on timeout)",
    kind: "read",
    positionals: ["handle"],
    input: z.object({ handle: z.string().describe("Operation handle <kind>:<id>.") }),
    output: z.unknown(),
    examples: [{ title: "Wait up to 5 minutes", argv: "operations wait video:42 --timeout 300 --json" }],
    async run(ctx, i) {
      return { data: await waitOperation(ctx, i.handle) };
    },
  }),
  defineCommand({
    ...common,
    id: "operations.kinds",
    summary: "List the operation kinds this CLI can wait for",
    kind: "read",
    anonymous: true,
    input: z.object({}),
    output: z.unknown(),
    examples: [{ title: "Kinds", argv: "operations kinds --json" }],
    async run() {
      return { data: operationKinds().map((k) => ({ kind: k.kind, description: k.describe })) };
    },
  }),
];
