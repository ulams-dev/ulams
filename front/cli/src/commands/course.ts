import { z } from "zod";
import { CliError } from "../errors.ts";
import type { AnyCommand, Ctx, Plan } from "../registry/types.ts";
import { runHandle } from "./builder-lib.ts";
import { defineCommand } from "./define.ts";

/* Course-as-code, server side of it (ADR 0160): export a course as a Blueprint v3 document, push an edited
 * document as a proposed `import` version, review it, approve it, apply it. These work with AI switched off.
 * Thin on purpose: `course pull` and `course push` (folder format, base snapshot) replace the file handling later. */

const COURSE_AS_CODE = "/api/admin/course-as-code";
const common = { audience: ["author" as const], mcp: { toolset: "builder" } };
const READ = ["builder:read"];
const WRITE = ["builder:write"];

const session = z.string().describe("Builder session id (data.sessionId of `course export`).");
const version = z.string().describe("Version id (data.version.id of `course import`).");

async function call<T>(ctx: Ctx, method: "GET" | "POST", path: string, o: { params?: Record<string, string | number>; query?: Record<string, string | undefined>; body?: unknown } = {}): Promise<T> {
  const res = await ctx.client.call<T>(method, `${COURSE_AS_CODE}${path}`, {
    ...(o.params ? { params: o.params } : {}),
    ...(o.query ? { query: o.query } : {}),
    ...(o.body !== undefined ? { body: o.body } : {}),
    idempotent: method === "GET",
    signal: ctx.signal,
  });
  return res.data;
}

const planOf = (method: string, path: string, body?: unknown): Plan => ({ request: { method, path: `${COURSE_AS_CODE}${path}`, ...(body !== undefined ? { body } : {}) } });

interface ExportPayload {
  courseId: number | null;
  sessionId: string;
  versionId: string;
  document: Record<string, unknown>;
  [key: string]: unknown;
}

/** The file holds either what `course export --out` wrote (the whole payload) or a bare blueprint document. */
async function readDocument(ctx: Ctx, file: string): Promise<{ document: Record<string, unknown>; sessionId?: string; versionId?: string }> {
  const text = file === "-" ? await ctx.readStdin() : await ctx.fs.readText(file);
  let parsed: unknown;
  try {
    parsed = JSON.parse(text);
  } catch (e) {
    throw new CliError("INPUT_INVALID", `${file} is not valid JSON: ${(e as Error).message}`);
  }
  if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) throw new CliError("INPUT_INVALID", `${file} must hold a JSON object.`);
  const obj = parsed as Record<string, unknown>;
  const envelope = obj.data && typeof obj.data === "object" && "document" in (obj.data as object) ? (obj.data as Record<string, unknown>) : obj;
  if (envelope.document && typeof envelope.document === "object") {
    return {
      document: envelope.document as Record<string, unknown>,
      ...(typeof envelope.sessionId === "string" ? { sessionId: envelope.sessionId } : {}),
      ...(typeof envelope.versionId === "string" ? { versionId: envelope.versionId } : {}),
    };
  }
  if (!("schemaVersion" in obj) || !("modules" in obj)) throw new CliError("INPUT_INVALID", `${file} is neither a blueprint document nor the output of \`ulams course export --out\`.`);

  return { document: obj };
}

async function resolveImport(ctx: Ctx, i: { session?: string | undefined; file: string; base?: string | undefined; message?: string | undefined }) {
  const doc = await readDocument(ctx, i.file);
  const sessionId = i.session ?? doc.sessionId;
  const baseVersionId = i.base ?? doc.versionId;
  if (!sessionId) throw new CliError("INPUT_INVALID", "Pass the builder session: `course import <session> --file ...`, or use a file written by `course export --out`.");
  if (!baseVersionId) throw new CliError("INPUT_INVALID", "Pass the version the file was exported from with --base <version id>, or use a file written by `course export --out`.");

  return { sessionId, body: { document: doc.document, baseVersionId, ...(i.message ? { message: i.message } : {}) } };
}

export const courseCommands: AnyCommand[] = [
  defineCommand({
    ...common,
    id: "course.export",
    summary: "Export a course as a Blueprint v3 document",
    description:
      "The current version of the course as one JSON document (data.document), with its version id, the applied version id and any drift. Pass --out <file> to write the whole payload to a file that `course import --file` accepts. Works with AI switched off. A course that was not made with the builder has no blueprint yet (NOT_ADOPTED).",
    kind: "read",
    scopes: READ,
    endpoints: ["GET /api/admin/course-as-code/courses/{course}/blueprint"],
    positionals: ["course"],
    input: z.object({ course: z.string().regex(/^\d+$/, "A numeric course id.").describe("Course id.") }),
    output: z.unknown(),
    examples: [
      { title: "Export to a file", argv: "course export 12 --out blueprint.json --json" },
      { title: "Print the version ids", argv: "course export 12 --fields data.versionId,data.sessionId --json" },
    ],
    async run(ctx, i) {
      const payload = await call<ExportPayload>(ctx, "GET", "/courses/{course}/blueprint", { params: { course: i.course } });
      if (ctx.flags.out) {
        await ctx.fs.writeFile(ctx.flags.out, `${JSON.stringify(payload, null, 2)}\n`);
        const { document: _document, ...rest } = payload;
        return { data: { ...rest, file: ctx.flags.out } };
      }
      return { data: payload };
    },
  }),
  defineCommand({
    ...common,
    id: "course.import",
    summary: "Import an edited blueprint as a proposed version (nothing changes for learners until it is approved and applied)",
    description:
      "Validates the document against Blueprint v3 and the deterministic checks, then stores it as a proposed version of kind `import` and returns the element diff. A push that equals the current version creates nothing (data.unchanged). A push based on an older version than the server's is refused with BASE_OUTDATED and the remote diff; export again first. Elements written by hand set `origin: author` and may have no citations; AI-written elements must cite.",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: ["POST /api/admin/course-as-code/sessions/{session}/imports"],
    positionals: ["session"],
    input: z.object({
      session: session.optional(),
      file: z.string().describe("JSON file: a blueprint document, or the file written by `course export --out`. `-` reads stdin."),
      base: z.string().optional().describe("Version the document was exported from (default: data.versionId of the exported file)."),
      message: z.string().max(500).optional().describe("Why, shown in the version history."),
    }),
    output: z.unknown(),
    examples: [{ title: "Push an edit", argv: "course import --file blueprint.json --message 'Fix the install step' --json" }],
    plan: async (ctx, i) => {
      const r = await resolveImport(ctx, i);
      return planOf("POST", `/sessions/${r.sessionId}/imports`, { ...r.body, document: "<document>" });
    },
    async run(ctx, i) {
      const r = await resolveImport(ctx, i);
      return { data: await call(ctx, "POST", "/sessions/{session}/imports", { params: { session: r.sessionId }, body: r.body }) };
    },
  }),
  defineCommand({
    ...common,
    id: "course.diff",
    summary: "Show what a version changes, element by element",
    description: "Against its parent by default, or against another version of the same course with --against.",
    kind: "read",
    scopes: READ,
    endpoints: ["GET /api/admin/course-as-code/versions/{version}/diff"],
    positionals: ["version"],
    input: z.object({ version, against: z.string().optional().describe("Another version id to compare with.") }),
    output: z.unknown(),
    examples: [{ title: "Review an import", argv: "course diff <version> --json" }],
    async run(ctx, i) {
      return { data: await call(ctx, "GET", "/versions/{version}/diff", { params: { version: i.version }, query: { against: i.against } }) };
    },
  }),
  defineCommand({
    ...common,
    id: "course.approve",
    summary: "Approve a proposed import so it becomes the course's current version",
    description:
      "Only a person can approve: a login, or a CLI token you created. Agent tokens and MCP clients are refused (APPROVAL_REQUIRES_HUMAN); CI tokens only when the tenant allows it. Approving does not change what learners see; run `course apply` next. Refused with STALE_PROPOSAL when the course changed after the import was proposed.",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: ["POST /api/admin/course-as-code/versions/{version}/approve"],
    positionals: ["version"],
    input: z.object({ version }),
    output: z.unknown(),
    examples: [{ title: "Approve", argv: "course approve <version> --json" }],
    plan: async (_ctx, i) => planOf("POST", `/versions/${i.version}/approve`),
    async run(ctx, i) {
      return { data: await call(ctx, "POST", "/versions/{version}/approve", { params: { version: i.version } }) };
    },
  }),
  defineCommand({
    ...common,
    id: "course.apply",
    summary: "Apply the current version to the course (an unpublished draft)",
    description: "Writes the course through the course services. Returns the run id; follow it with `ulams operations wait <meta.operation>`.",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    longRunning: { kind: "builder-run" },
    endpoints: ["POST /api/admin/course-as-code/sessions/{session}/apply"],
    positionals: ["session"],
    input: z.object({ session, overwrite: z.boolean().optional().describe("Overwrite edits an admin made in the course since the last apply.") }),
    output: z.unknown(),
    examples: [{ title: "Apply", argv: "course apply <session> --json" }],
    plan: async (_ctx, i) => planOf("POST", `/sessions/${i.session}/apply`, i.overwrite ? { overwrite: true } : {}),
    async run(ctx, i) {
      const res = await call<{ runId: string | null }>(ctx, "POST", "/sessions/{session}/apply", { params: { session: i.session }, body: i.overwrite ? { overwrite: true } : {} });
      return { data: res, ...(res.runId ? { meta: { operation: runHandle(res.runId) } } : {}) };
    },
  }),
  defineCommand({
    ...common,
    id: "course.publish",
    summary: "Publish the applied course so learners can enrol",
    description: "Refused while publish checks block it or warnings are not acknowledged (--acknowledge-warnings).",
    kind: "write",
    idempotent: true,
    scopes: WRITE,
    endpoints: ["POST /api/admin/course-as-code/sessions/{session}/publish"],
    positionals: ["session"],
    input: z.object({ session, acknowledgeWarnings: z.boolean().optional().describe("Acknowledge the publish warnings after reviewing them.") }),
    output: z.unknown(),
    examples: [{ title: "Publish", argv: "course publish <session> --json" }],
    plan: async (_ctx, i) => planOf("POST", `/sessions/${i.session}/publish`, { acknowledgedWarnings: Boolean(i.acknowledgeWarnings) }),
    async run(ctx, i) {
      return { data: await call(ctx, "POST", "/sessions/{session}/publish", { params: { session: i.session }, body: { acknowledgedWarnings: Boolean(i.acknowledgeWarnings) } }) };
    },
  }),
];
