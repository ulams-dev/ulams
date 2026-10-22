import { z } from "zod";
import { CliError } from "../errors.ts";
import type { AnyCommand, Ctx } from "../registry/types.ts";
import { call, callVersions, out, planOf, wantsJson } from "./course-io.ts";
import { defineCommand } from "./define.ts";

/* Course-as-code, the document-level commands (ADR 0160): export a course as a Blueprint v3 document, import an edited
 * document as a proposed `import` version, approve it. They work with AI switched off and with plain JSON files; the
 * folder workflow (init, pull, push, diff, status, apply, publish) is in course-sync.ts. */

const common = { audience: ["author" as const], mcp: { toolset: "builder" } };
const READ = ["builder:read"];
const WRITE = ["builder:write"];

const session = z.string().describe("Builder session id (data.sessionId of `course export`).");
const version = z.string().describe("Version id (data.version.id of `course import`).");

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

interface ReleaseRow {
  id: string;
  number: number;
  trigger: string;
  versionId: string;
  versionNumber: number | null;
  reason: string | null;
  courseId: number;
  approvedBy: { id: number; name: string } | null;
  summary: { first: boolean; counts: { added: number; changed: number; removed: number }; lessons: { added: number; changed: number; removed: number }; citationsChanged: number; answerKeysChanged: number };
  releasedAt: string;
}

interface ReleasesPayload {
  sessionId: string;
  courseId: number;
  releases: ReleaseRow[];
  total: number;
  page: number;
  perPage: number;
}

const plural = (n: number, one: string, many = `${one}s`): string => `${n} ${n === 1 ? one : many}`;

/** One line for the release list: what changed in words, e.g. "2 lessons added, 1 element changed, 1 answer key changed". */
export function releaseChanges(s: ReleaseRow["summary"]): string {
  if (s.first) return `first release, ${plural(s.lessons.added, "lesson")}`;
  const parts: string[] = [];
  if (s.lessons.added) parts.push(`${plural(s.lessons.added, "lesson")} added`);
  if (s.lessons.removed) parts.push(`${plural(s.lessons.removed, "lesson")} removed`);
  if (s.lessons.changed) parts.push(`${plural(s.lessons.changed, "lesson")} changed`);
  const others = s.counts.added + s.counts.changed + s.counts.removed - s.lessons.added - s.lessons.removed - s.lessons.changed;
  if (others > 0) parts.push(`${plural(others, "other element")} changed`);
  if (s.citationsChanged) parts.push(`${plural(s.citationsChanged, "citation")} changed`);
  if (s.answerKeysChanged) parts.push(`${plural(s.answerKeysChanged, "answer key")} changed`);
  return parts.length ? parts.join(", ") : "no content change";
}

function releaseLines(p: ReleasesPayload): string[] {
  if (p.releases.length === 0) return ["No releases yet: the course has not been applied."];
  const rows = p.releases.map((r) => [`#${r.number}`, r.releasedAt.slice(0, 16).replace("T", " "), r.trigger, r.approvedBy?.name ?? "-", releaseChanges(r.summary)]);
  const widths = [0, 1, 2, 3].map((c) => Math.max(...rows.map((r) => (r[c] ?? "").length)));
  const lines = rows.map((r) => r.map((cell, c) => (c < 4 ? cell.padEnd(widths[c] ?? 0) : cell)).join("  "));
  const last = Math.max(1, Math.ceil(p.total / p.perPage));
  return [...lines, "", `page ${p.page}/${last}, ${p.total} total`];
}

export const courseCommands: AnyCommand[] = [
  defineCommand({
    ...common,
    id: "course.releases",
    summary: "List the releases of a course: the versions learners actually got, newest first",
    description:
      "A release is written by every successful apply (the first generation, an author or chat edit, an accepted source update, an insights improvement, a course-as-code push, an undo or restore). Each shows its number, when it went live, what started it, who approved it and what changed since the previous release. Proposals, rejections and undone versions are not releases. Works with AI switched off. A course that was not made with the builder has no releases (NOT_FOUND). Visible to the course author and admins.",
    kind: "read",
    scopes: READ,
    endpoints: ["GET /api/admin/course-versions/courses/{course}/releases"],
    positionals: ["course"],
    input: z.object({
      course: z.string().regex(/^\d+$/, "A numeric course id.").describe("Course id."),
      page: z.number().int().min(1).optional(),
      perPage: z.number().int().min(1).max(100).optional().describe("Releases per page (default 25, at most 100)."),
    }),
    output: z.unknown(),
    examples: [
      { title: "The releases of a course", argv: "course releases 12" },
      { title: "For a script", argv: "course releases 12 --fields data.releases --json" },
    ],
    async run(ctx, i) {
      const payload = await callVersions<ReleasesPayload>(ctx, "/courses/{course}/releases", { params: { course: i.course }, query: { page: i.page, perPage: i.perPage } });
      if (wantsJson(ctx)) return { data: payload };
      out(ctx, releaseLines(payload).join("\n"));
      return { data: payload, handled: true };
    },
  }),
  defineCommand({
    ...common,
    id: "course.release",
    summary: "Show one release of a course with the blueprint document of its version",
    description:
      "One release (see `course releases`) with `data.document`, the Blueprint v3 document that learners got at that moment. Pass --out <file> to write the whole payload to a file. Works with AI switched off. Visible to the course author and admins.",
    kind: "read",
    scopes: READ,
    endpoints: ["GET /api/admin/course-versions/releases/{release}"],
    positionals: ["release"],
    input: z.object({ release: z.string().regex(/^[0-9a-z]{26}$/i, "A release id (data.releases[].id of `course releases`).").describe("Release id.") }),
    output: z.unknown(),
    examples: [{ title: "Save what learners got in release 3", argv: "course release 01jb0000000000000000000003 --out release-3.json --json" }],
    async run(ctx, i) {
      const payload = await callVersions<ReleaseRow & { document?: unknown }>(ctx, "/releases/{release}", { params: { release: i.release } });
      if (ctx.flags.out) {
        await ctx.fs.writeFile(ctx.flags.out, `${JSON.stringify(payload, null, 2)}\n`);
        const { document: _document, ...rest } = payload;
        return { data: { ...rest, file: ctx.flags.out } };
      }
      if (wantsJson(ctx)) return { data: payload };
      out(ctx, [`Release #${payload.number} (${payload.trigger}), ${payload.releasedAt.slice(0, 16).replace("T", " ")}`, `Approved by: ${payload.approvedBy?.name ?? "-"}`, `Changes: ${releaseChanges(payload.summary)}`, "Use --json or --out <file> for the document."].join("\n"));
      return { data: payload, handled: true };
    },
  }),
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
];
