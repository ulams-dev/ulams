import { existsSync, readdirSync, statSync } from "node:fs";
import { resolve } from "node:path";
import { z } from "zod";
import { readFolder, type DiskFolder } from "../course/disk.ts";
import { folderFromBlueprint } from "../course/from-blueprint.ts";
import { summarize } from "../course/issues.ts";
import { ULAMS_FILE } from "../course/layout.ts";
import { loadCourse } from "../course/load.ts";
import { toText } from "../course/sarif.ts";
import {
  changesSinceBase,
  compareDocuments,
  countChanges,
  documentHash,
  folderForm,
  makeBase,
  type BaseFile,
  type Change,
  type Counts,
} from "../course/sync.ts";
import { clearProposal, hasBase, keepAuthorNames, listConflicts, materialise, planMaterialise, readBase, readProposal, writeBase, writeProposal, BASE_FILE, type Proposal } from "../course/sync-disk.ts";
import { isObj, type Files, type Issue, type Obj } from "../course/types.ts";
import { validateFiles } from "../course/validate.ts";
import { CliError } from "../errors.ts";
import type { AnyCommand, Ctx, Plan, Result, Warning } from "../registry/types.ts";
import { runHandle } from "./builder-lib.ts";
import { call, COURSE_AS_CODE, out, planOf, wantsJson } from "./course-io.ts";
import { defineCommand } from "./define.ts";

/* Course-as-code, the sync half (ADR 0160, plan 7.5, milestone CAC-3): a course folder bound to a course on the server.
 * `init` and `pull` write the folder from the server's current version and remember it in `.ulams/base.json`;
 * `push` sends the folder as a PROPOSED version (nothing reaches learners); `apply` approves (a person only) and applies;
 * `publish` publishes. Everything works with AI switched off. There is no merge yet: a push or a pull that would have to
 * combine two sets of changes stops with SYNC_CONFLICT (exit 6) instead of guessing. */

const READ = ["builder:read"];
const WRITE = ["builder:write"];
const SINCE = "0.2.0";
const folderCommand = { audience: ["author" as const], mcp: { expose: false }, since: SINCE };
const builderCommand = { audience: ["author" as const], mcp: { toolset: "builder" }, since: SINCE };

const dirInput = z.string().optional().describe("Course folder (the one holding ulams.yaml). Default: the current directory.");
const sessionInput = z.string().optional().describe("Builder session id. Default: the one in .ulams/base.json of the course folder.");

interface ExportPayload {
  courseId: number | null;
  sessionId: string;
  versionId: string;
  versionNumber: number;
  appliedVersionId: string | null;
  document: Obj;
  drift?: Array<{ element: string; title: string }>;
}

interface ImportResponse {
  unchanged: boolean;
  version: { id: string; number: number; kind: string; status: string; parentId: string | null } | null;
  counts: Counts;
  changes: Array<Pick<Change, "id" | "type" | "label" | "kind" | "fields"> & { summary?: string }>;
  warnings: string[];
  handEdited: string[];
}

/* ---------------------------------------------------------------- errors from the server */

function serverBody(e: CliError): { code: string | null; data: Obj } {
  const body = isObj(e.details.body) ? e.details.body : e.details;
  return { code: typeof body.code === "string" ? body.code : null, data: isObj(body.data) ? body.data : {} };
}

/** Gives the server's course-as-code refusals the CLI's own codes, messages and next steps. */
function translate(e: unknown): unknown {
  if (!(e instanceof CliError)) return e;
  const { code, data } = serverBody(e);
  const keep = { status: e.status, requestId: e.requestId };
  switch (code) {
    case "BASE_OUTDATED":
      return new CliError("SYNC_CONFLICT", `The course changed on the server after the version this folder is based on (the current version is ${String(data.currentVersionNumber ?? "newer")}).`, {
        ...keep,
        hint: "Run `ulams course pull` to bring the folder up to date (it stops if you have local edits too: push them first or ask for the merge milestone), then push again.",
        details: { code, currentVersionId: data.currentVersionId ?? null, currentVersionNumber: data.currentVersionNumber ?? null, remote: Array.isArray(data.remote) ? data.remote : [] },
      });
    case "STALE_PROPOSAL":
      return new CliError("SYNC_CONFLICT", "The course changed after this version was proposed, so it cannot be approved any more.", {
        ...keep,
        hint: "Run `ulams course pull`, then push again to propose a version built on the current one.",
        details: { code, currentVersionId: data.currentVersionId ?? null },
      });
    case "INVALID_BLUEPRINT": {
      const errors = Array.isArray(data.errors) ? data.errors.map(String) : [];
      return new CliError("VALIDATION_FAILED", `The server rejected the course: ${errors[0] ?? e.message}`, {
        ...keep,
        hint: "`ulams course validate` checks most of this offline; the server also checks that cited fragments exist. error.details.errors lists every problem.",
        details: { code, errors },
      });
    }
    case "APPROVAL_REQUIRES_HUMAN":
      return new CliError("FORBIDDEN", e.message, {
        ...keep,
        hint: "The proposal is saved. A person approves it in the studio, or runs `ulams course apply --approve` logged in (`ulams login`) or with a CLI token they created. Agent tokens and MCP clients can only propose.",
        details: { code },
      });
    case "NOT_ADOPTED":
      return new CliError("CONFLICT", e.message, {
        ...keep,
        hint: "Only courses made with the course builder can be synced for now; adopting a hand-made course arrives in a later milestone.",
        details: { code },
      });
    case "NO_BLUEPRINT":
      return new CliError("CONFLICT", e.message, { ...keep, hint: "Finish the course in the builder (outline, generate) first.", details: { code } });
    default:
      return e;
  }
}

async function server<T>(run: () => Promise<T>): Promise<T> {
  try {
    return await run();
  } catch (e) {
    throw translate(e);
  }
}

const fetchExport = (ctx: Ctx, courseId: number | string): Promise<ExportPayload> =>
  server(() => call<ExportPayload>(ctx, "GET", "/courses/{course}/blueprint", { params: { course: courseId } }));

/* ---------------------------------------------------------------- the folder */

function openFolder(dir: string | undefined): DiskFolder {
  const folder = readFolder(dir ?? ".");
  if (!folder.files.has(ULAMS_FILE)) {
    throw new CliError("INPUT_INVALID", `${dir ?? "."} is not a course folder: there is no ulams.yaml.`, { hint: "Run the command inside the course folder, pass its path, or create one with `ulams course init --from-course <id>`." });
  }
  return folder;
}

const textFiles = (files: Files): number => [...files.keys()].filter((p) => /\.(md|yaml)$/.test(p)).length;

function failWith(ctx: Ctx, issues: Issue[], files: number): never {
  const summary = summarize(issues);
  if (!wantsJson(ctx)) out(ctx, toText(issues, files));
  throw new CliError("VALIDATION_FAILED", `${summary.errors} error${summary.errors === 1 ? "" : "s"}, ${summary.warnings} warning${summary.warnings === 1 ? "" : "s"} in the course folder.`, {
    hint: "Fix the issues (`ulams course validate` lists them with file, line and column; `ulams course validate --fix` does the mechanical ones), then run the command again.",
    details: { valid: false, summary, files, issues },
  });
}

/** The folder as a Blueprint v3 document, refusing a folder with errors. */
function localDocument(ctx: Ctx, folder: DiskFolder): { doc: Obj; hash: string; issues: Issue[] } {
  const { issues, doc } = validateFiles(folder.files, folder.assets);
  if (summarize(issues).errors > 0) failWith(ctx, issues, textFiles(folder.files));
  return { doc, hash: documentHash(doc), issues };
}

function requireAuth(ctx: Ctx): void {
  if (!ctx.profile.url) throw new CliError("AUTH_REQUIRED", "No instance configured.", { hint: "Run `ulams login --url <origin>` or set ULAMS_URL and ULAMS_TOKEN." });
  if (!ctx.profile.token) throw new CliError("AUTH_REQUIRED", `Not logged in to ${ctx.profile.url}.`, { hint: `Run \`ulams login --url ${ctx.profile.url}\` or set ULAMS_TOKEN.` });
}

function needCourseId(base: BaseFile): number {
  if (base.courseId === null) throw new CliError("INPUT_INVALID", "This folder is bound to a builder session that has not created its course yet.", { hint: "Apply the course once in the studio, then run `ulams course init --from-course <id>` again." });
  return base.courseId;
}

/* ---------------------------------------------------------------- output */

const MARK: Record<Change["kind"], string> = { added: "+", changed: "~", removed: "-" };
const clip = (v: unknown, n = 70): string => {
  const text = typeof v === "string" ? v : JSON.stringify(v);
  const one = (text ?? "null").replace(/\s+/g, " ");
  return one.length > n ? `${one.slice(0, n - 3)}...` : one;
};

type ChangeLike = Pick<Change, "kind" | "label" | "type" | "id" | "fields"> & { summary?: string | undefined };

function changeLines(changes: ChangeLike[], limit = 40): string[] {
  const lines: string[] = [];
  for (const c of changes.slice(0, limit)) {
    lines.push(`  ${MARK[c.kind]} ${c.label}${c.summary ? ` "${c.summary}"` : ""}  (${c.type} ${c.id})`);
    if (c.kind === "changed") for (const f of c.fields.slice(0, 4)) lines.push(`      ${f.field}: ${clip(f.before, 48)} -> ${clip(f.after, 48)}`);
  }
  if (changes.length > limit) lines.push(`  ... and ${changes.length - limit} more (use --json for the full list)`);
  return lines;
}

const countsText = (c: Counts): string => `${c.changed} changed, ${c.added} added, ${c.removed} removed`;

/** JSON gets the envelope; a terminal gets the text lines (warnings on stderr). */
function present(ctx: Ctx, data: unknown, lines: string[], extra: { exitCode?: number; meta?: Result["meta"]; warnings?: Warning[] } = {}): Result {
  if (wantsJson(ctx)) return { data, ...(extra.exitCode ? { exitCode: extra.exitCode } : {}), ...(extra.meta ? { meta: extra.meta } : {}), ...(extra.warnings?.length ? { warnings: extra.warnings } : {}) };
  for (const w of extra.warnings ?? []) ctx.io.stderr(`warning ${w.code}: ${w.message}`);
  out(ctx, lines.join("\n"));
  return { data, handled: true, ...(extra.exitCode ? { exitCode: extra.exitCode } : {}) };
}

function driftWarnings(payload: ExportPayload): Warning[] {
  const drift = payload.drift ?? [];
  if (drift.length === 0) return [];
  return [
    {
      code: "DRIFT",
      message: `${drift.length} element${drift.length === 1 ? " was" : "s were"} edited in the LMS since the last apply (${drift
        .slice(0, 3)
        .map((d) => d.title)
        .join(", ")}${drift.length > 3 ? ", ..." : ""}). The next apply overwrites those edits unless they are copied into the folder first.`,
      hint: "Review them in the admin, copy what you want to keep into the Markdown, then push.",
    },
  ];
}

function assertRepresentable(doc: Obj): Obj {
  const form = folderForm(doc);
  if (form.errors.length > 0) {
    throw new CliError("INTERNAL", `The course holds content the folder format cannot represent yet (${form.errors[0]?.message ?? "unknown"}), so writing it to files would lose data.`, {
      hint: "Nothing was written. Report it with `error.details.issues`; use `ulams course export` and `import` (JSON) for this course meanwhile.",
      details: { issues: form.errors.slice(0, 10) },
    });
  }
  return form.doc;
}

/* ---------------------------------------------------------------- init */

const INIT_ALLOWED = /^(\.|README|LICENSE|LICENCE|CHANGELOG)/i;

function checkTargetIsEmpty(root: string): void {
  if (!existsSync(root)) return;
  if (!statSync(root).isDirectory()) throw new CliError("INPUT_INVALID", `${root} is not a folder.`);
  const entries = readdirSync(root).filter((n) => !INIT_ALLOWED.test(n));
  if (entries.length > 0) {
    throw new CliError("INPUT_INVALID", `${root} is not empty (${entries.slice(0, 3).join(", ")}${entries.length > 3 ? ", ..." : ""}), so init would mix a course into other files.`, {
      hint: "Pass an empty folder (`ulams course init ./my-course --from-course 12`). Use `ulams course pull` inside a folder that is already bound to a course.",
    });
  }
}

const init = defineCommand({
  ...folderCommand,
  id: "course.init",
  summary: "Create a course folder from a course made with the builder (Markdown and YAML, bound to the course)",
  description:
    "Reads the course's current version from the server and writes it as a course folder (ulams.yaml, course.yaml, modules/...), then records where it stands in .ulams/base.json (commit it). The folder must be empty (a README, a LICENSE and dot files may be there). Only courses made with the course builder have a blueprint (a hand-made one is refused with NOT_ADOPTED). Works with AI switched off. With --dry-run it lists the files and writes nothing.",
  kind: "read",
  scopes: READ,
  endpoints: ["GET /api/admin/course-as-code/courses/{course}/blueprint"],
  positionals: ["dir"],
  input: z.object({
    dir: dirInput,
    fromCourse: z.string().regex(/^\d+$/, "A numeric course id.").optional().describe("Course id on the server (required: scaffolding an empty course folder comes with course creation)."),
  }),
  output: z.unknown(),
  examples: [
    { title: "Pull a course into a new folder", argv: "course init ./kubernetes-101 --from-course 12" },
    { title: "See what would be written", argv: "course init ./kubernetes-101 --from-course 12 --dry-run --json" },
  ],
  async run(ctx, i) {
    if (!i.fromCourse) {
      throw new CliError("INPUT_INVALID", "Pass --from-course <id>: creating a course from an empty folder is not available yet.", { hint: "Create the course in the builder, then `ulams course init --from-course <id>`." });
    }
    const root = resolve(i.dir ?? ".");
    checkTargetIsEmpty(root);
    const payload = await fetchExport(ctx, i.fromCourse);
    const doc = assertRepresentable(payload.document);
    const files = folderFromBlueprint(payload.document, {
      course: { ...(payload.courseId !== null ? { id: payload.courseId } : {}), session: payload.sessionId },
      ...(ctx.flags.profile ? { profile: ctx.flags.profile } : {}),
    });
    const names = [...files.keys()].sort();
    const data = { dir: root, courseId: payload.courseId, sessionId: payload.sessionId, versionId: payload.versionId, versionNumber: payload.versionNumber, files: names, ...(ctx.flags.dryRun ? { dryRun: true } : {}) };
    if (!ctx.flags.dryRun) {
      materialise(root, new Map(), files);
      writeBase(root, makeBase(doc, { sessionId: payload.sessionId, courseId: payload.courseId, versionId: payload.versionId, versionNumber: payload.versionNumber }));
    }
    return present(
      ctx,
      data,
      [
        `${ctx.flags.dryRun ? "Would write" : "Wrote"} ${names.length} files to ${root} (course ${payload.courseId ?? "?"}, version ${payload.versionNumber}).`,
        ...(ctx.flags.dryRun ? names.map((n) => `  ${n}`) : [`Commit ${BASE_FILE}: it records the version this folder is based on.`, "Next: edit the Markdown, `ulams course diff`, then `ulams course push`."]),
      ],
      { warnings: driftWarnings(payload) }
    );
  },
});

/* ---------------------------------------------------------------- pull */

const pull = defineCommand({
  ...folderCommand,
  id: "course.pull",
  summary: "Bring the folder up to date with the server's current version",
  description:
    "Fetches the course's current version. A folder without local edits is rewritten to match it (files you renamed keep their names, files for removed lessons are deleted, assets and your own files are never touched) and the base moves forward. A folder that already equals the server's version only moves the base. A folder with local edits is left alone when the server did not change; when both changed, pull stops with SYNC_CONFLICT (exit 6) and writes nothing: there is no merge yet, push your edits (or discard them) first. --dry-run lists the files that would change.",
  kind: "read",
  scopes: READ,
  endpoints: ["GET /api/admin/course-as-code/courses/{course}/blueprint"],
  positionals: ["dir"],
  input: z.object({ dir: dirInput }),
  output: z.unknown(),
  examples: [
    { title: "Update the folder", argv: "course pull" },
    { title: "Preview", argv: "course pull --dry-run --json" },
  ],
  async run(ctx, i) {
    const folder = openFolder(i.dir);
    const base = readBase(folder.root);
    const local = localDocument(ctx, folder);
    const modified = local.hash !== base.documentHash;
    const payload = await fetchExport(ctx, needCourseId(base));
    const warnings = driftWarnings(payload);
    const version = { id: payload.versionId, number: payload.versionNumber };
    const done = (state: string, extra: Record<string, unknown>, line: string) => present(ctx, { state, version, localChanges: modified, ...extra }, [line], { warnings });

    if (payload.versionId === base.baseVersionId) {
      return done("up-to-date", {}, `Already up to date (version ${version.number}).${modified ? " You have local edits: `ulams course diff` shows them." : ""}`);
    }
    const remote = assertRepresentable(payload.document);
    const rebase = (): BaseFile => makeBase(remote, { sessionId: payload.sessionId, courseId: payload.courseId, versionId: payload.versionId, versionNumber: payload.versionNumber });
    const proposal = readProposal(folder.root);
    const settle = () => {
      if (!ctx.flags.dryRun) {
        writeBase(folder.root, rebase());
        if (proposal && (proposal.versionId === payload.versionId || proposal.baseVersionId !== payload.versionId)) clearProposal(folder.root);
      }
    };

    if (documentHash(remote) === local.hash) {
      settle();
      return done("base-moved", { dryRun: Boolean(ctx.flags.dryRun) }, `The folder already equals version ${version.number} (for example the version you pushed was approved); the base now points at it.`);
    }
    if (modified) {
      const theirs = changesSinceBase(base, remote);
      const ours = changesSinceBase(base, local.doc);
      throw new CliError("SYNC_CONFLICT", `Both sides changed: the server is at version ${version.number}, the folder is based on version ${base.baseVersionNumber} and has local edits.`, {
        hint: "There is no merge yet. Save your edits aside (a Git branch or a copy), reset the folder to its base (`git checkout -- .`), run `ulams course pull`, then make your edits again.",
        details: { code: "DIVERGED", serverVersionId: payload.versionId, serverVersionNumber: version.number, baseVersionId: base.baseVersionId, localChanges: ours.counts, serverChanges: theirs.counts, localElements: ours.changes.map((c) => c.id), serverElements: theirs.changes.map((c) => c.id) },
      });
    }
    const generated = folderFromBlueprint(payload.document);
    generated.delete(ULAMS_FILE);
    const files = keepAuthorNames(generated, folder.files);
    const plan = ctx.flags.dryRun ? planMaterialise(folder.files, files) : materialise(folder.root, folder.files, files);
    settle();
    const changes = changesSinceBase(base, remote);
    return present(
      ctx,
      { state: ctx.flags.dryRun ? "would-update" : "updated", version, files: { written: plan.written, updated: plan.updated, removed: plan.removed }, counts: changes.counts, dryRun: Boolean(ctx.flags.dryRun) },
      [
        `${ctx.flags.dryRun ? "Would update" : "Updated"} to version ${version.number}: ${countsText(changes.counts)}. Files: ${plan.written.length} new, ${plan.updated.length} changed, ${plan.removed.length} removed.`,
        ...changeLines(changes.changes),
      ],
      { warnings }
    );
  },
});

/* ---------------------------------------------------------------- diff */

const diff = defineCommand({
  ...folderCommand,
  id: "course.diff",
  summary: "Show what changed: the folder against its base (offline), against the server (--remote), or a version on the server (--version)",
  description:
    "By default compares the folder with the version it is based on (.ulams/base.json): which elements were added, changed or removed, with no network. --remote fetches the server's current version and shows, field by field, what a push would change. --version <id> shows what a version on the server changes against its parent (or --against another version). --exit-code exits 13 (DIFF_FOUND) when there are differences, for CI.",
  kind: "read",
  anonymous: true,
  scopes: READ,
  endpoints: ["GET /api/admin/course-as-code/versions/{version}/diff"],
  positionals: ["dir"],
  input: z.object({
    dir: dirInput,
    remote: z.boolean().optional().describe("Compare with the server's current version instead of the base."),
    version: z.string().optional().describe("Show what this version on the server changes against its parent."),
    against: z.string().optional().describe("With --version: another version id to compare with."),
    exitCode: z.boolean().optional().describe("Exit 13 when there are differences."),
  }),
  output: z.unknown(),
  examples: [
    { title: "What did I change since the last pull", argv: "course diff" },
    { title: "What would a push change on the server", argv: "course diff --remote" },
    { title: "Fail CI when the folder drifted", argv: "course diff --exit-code --json" },
    { title: "Review a proposed version", argv: "course diff --version <version id>" },
  ],
  async run(ctx, i) {
    const exit = (n: number): { exitCode?: number } => (i.exitCode && n > 0 ? { exitCode: 13 } : {});
    if (i.version) {
      requireAuth(ctx);
      const res = await server(() => call<{ against: string | null; changes: ChangeLike[] }>(ctx, "GET", "/versions/{version}/diff", { params: { version: i.version as string }, query: { against: i.against } }));
      const counts = countChanges(res.changes);
      return present(ctx, { against: res.against, counts, changes: res.changes }, [`Version ${i.version} against ${res.against ?? "its parent"}: ${countsText(counts)}.`, ...changeLines(res.changes)], exit(res.changes.length));
    }
    const folder = openFolder(i.dir);
    const base = readBase(folder.root);
    const local = localDocument(ctx, folder);
    if (i.remote) {
      requireAuth(ctx);
      const payload = await fetchExport(ctx, needCourseId(base));
      const remote = assertRepresentable(payload.document);
      const changes = compareDocuments(remote, local.doc);
      const counts = countChanges(changes);
      const behind = payload.versionId !== base.baseVersionId;
      return present(
        ctx,
        { against: "remote", remote: { versionId: payload.versionId, versionNumber: payload.versionNumber }, behind, counts, changes, unchanged: changes.length === 0 },
        [
          `Folder against the server's version ${payload.versionNumber}${behind ? ` (the folder is based on version ${base.baseVersionNumber}: pull first)` : ""}: ${countsText(counts)}.`,
          ...changeLines(changes),
        ],
        exit(changes.length)
      );
    }
    const since = changesSinceBase(base, local.doc);
    return present(
      ctx,
      { against: "base", base: { versionId: base.baseVersionId, versionNumber: base.baseVersionNumber }, counts: since.counts, changes: since.changes, orderChanged: since.orderChanged, unchanged: !since.modified },
      since.modified
        ? [`Folder against its base (version ${base.baseVersionNumber}): ${countsText(since.counts)}.${since.orderChanged ? " Only the order of elements changed." : ""}`, ...changeLines(since.changes)]
        : [`No changes since version ${base.baseVersionNumber}.`],
      exit(since.modified ? 1 : 0)
    );
  },
});

/* ---------------------------------------------------------------- status */

const status = defineCommand({
  ...folderCommand,
  id: "course.status",
  summary: "Where the folder stands: local edits, the server's version, what is applied, a proposal waiting for approval",
  description:
    "One request to the server plus the folder. Reports local edits since the base, whether the server moved on (behind), a proposal you pushed and nobody has approved, whether the current version is applied, drift (elements edited in the LMS since the last apply) and open conflicts, and suggests the next command. Always exits 0.",
  kind: "read",
  scopes: READ,
  endpoints: ["GET /api/admin/course-as-code/courses/{course}/blueprint"],
  positionals: ["dir"],
  input: z.object({ dir: dirInput }),
  output: z.unknown(),
  examples: [{ title: "Check before pushing", argv: "course status --json" }],
  async run(ctx, i) {
    const folder = openFolder(i.dir);
    const base = readBase(folder.root);
    const { issues, doc } = validateFiles(folder.files, folder.assets);
    const errors = summarize(issues).errors;
    const since = errors === 0 ? changesSinceBase(base, doc) : null;
    const payload = await fetchExport(ctx, needCourseId(base));
    const remoteDoc = assertRepresentable(payload.document);
    const behind = payload.versionId !== base.baseVersionId;
    const theirs = behind ? changesSinceBase(base, remoteDoc) : null;
    const proposal = readProposal(folder.root);
    const pending = proposal && payload.versionId !== proposal.versionId ? (proposal.baseVersionId === payload.versionId ? "waiting" : "stale") : proposal ? "approved" : null;
    const applied = payload.appliedVersionId === payload.versionId;
    const conflicts = listConflicts(folder.root);
    const equal = since && documentHash(remoteDoc) === documentHash(doc);

    const next: string[] = [];
    if (errors > 0) next.push("`ulams course validate` and fix the errors");
    else if (behind && since?.modified && !equal) next.push("both sides changed: there is no merge yet (see `ulams course pull`)");
    else if (behind) next.push("`ulams course pull`");
    else if (since?.modified && pending !== "waiting") next.push("`ulams course push`");
    if (pending === "waiting") next.push("`ulams course apply --approve` (a person), or approve the proposal in the studio");
    else if (!behind && !applied && !pending) next.push("`ulams course apply`");

    const data = {
      course: { id: base.courseId, session: base.sessionId },
      base: { versionId: base.baseVersionId, versionNumber: base.baseVersionNumber },
      local: errors > 0 ? { state: "invalid", errors } : { state: since?.modified ? "modified" : "clean", counts: since?.counts ?? null, orderChanged: since?.orderChanged ?? false },
      remote: {
        versionId: payload.versionId,
        versionNumber: payload.versionNumber,
        state: behind ? "behind" : "in-sync",
        counts: theirs?.counts ?? null,
        sameAsFolder: Boolean(behind && equal),
        applied,
        appliedVersionId: payload.appliedVersionId,
        drift: payload.drift ?? [],
      },
      proposal: proposal ? { versionId: proposal.versionId, versionNumber: proposal.versionNumber, state: pending, pushedAt: proposal.pushedAt } : null,
      conflicts,
      next,
    };
    const lines = [
      `Course ${base.courseId} (session ${base.sessionId}), folder based on version ${base.baseVersionNumber}.`,
      `Local:    ${errors > 0 ? `${errors} error${errors === 1 ? "" : "s"} in the files (run \`ulams course validate\`)` : since?.modified ? `modified, ${countsText(since.counts)}${since.orderChanged ? " (order only)" : ""}` : "clean"}`,
      `Server:   ${behind ? `version ${payload.versionNumber} is current, ${theirs ? countsText(theirs.counts) : ""} since the base${equal ? " (equal to this folder: pull moves the base)" : ""}` : `in sync (version ${payload.versionNumber})`}`,
      `Applied:  ${applied ? "yes, learners see the current version" : `no (applied version: ${payload.appliedVersionId ?? "none"})`}`,
      ...(proposal ? [`Proposal: version ${proposal.versionNumber} pushed ${proposal.pushedAt}, ${pending === "waiting" ? "waiting for approval" : pending === "approved" ? "approved" : "out of date (the course changed after it)"}`] : []),
      ...((payload.drift ?? []).length > 0 ? [`Drift:    ${(payload.drift ?? []).length} element(s) edited in the LMS since the last apply`] : []),
      ...(conflicts.length > 0 ? [`Conflicts: ${conflicts.join(", ")}`] : []),
      ...(next.length > 0 ? [`Next:     ${next.join("; ")}`] : []),
    ];
    return present(ctx, data, lines);
  },
});

/* ---------------------------------------------------------------- push */

const unapproved = (versionId: string, number?: number): CliError =>
  new CliError("CONFIRMATION_REQUIRED", `Version ${number ?? versionId} is a proposal: nobody has approved it, so applying changes nothing for learners.`, {
    hint: "Approve it in the studio, or run `ulams course apply --approve` as a person (a login, or a CLI token you created). Agent tokens and MCP clients cannot approve.",
    details: { versionId, status: "proposed" },
  });

/** After a human approved a version equal to the folder, the base moves to it; a version that is not this folder's leaves the base alone. */
function advanceBase(root: string, base: BaseFile, proposal: Proposal): boolean {
  const folder = readFolder(root);
  const loaded = loadCourse(folder.files, folder.assets);
  if (documentHash(loaded.doc) !== proposal.documentHash) return false;
  writeBase(root, makeBase(loaded.doc, { sessionId: base.sessionId, courseId: base.courseId, versionId: proposal.versionId, versionNumber: proposal.versionNumber }));
  clearProposal(root);
  return true;
}

async function approveVersion(ctx: Ctx, versionId: string): Promise<{ versionId: string; number: number; status: string; currentVersionId: string }> {
  return server(() => call(ctx, "POST", "/versions/{version}/approve", { params: { version: versionId } }));
}

async function waitApplied(ctx: Ctx, courseId: number, expected: string | null): Promise<ExportPayload> {
  const deadline = Date.now() + ctx.flags.timeout * 1000;
  let delay = Number(ctx.env.ULAMS_POLL_MS) || 500;
  let version = expected;
  for (;;) {
    const p = await fetchExport(ctx, courseId);
    version ??= p.versionId;
    if (p.versionId !== version) throw new CliError("CONFLICT", "The course changed while the apply was running.", { hint: "Run `ulams course status`." });
    if (p.appliedVersionId === version) return p;
    if (Date.now() + delay > deadline) {
      throw new CliError("TIMEOUT", `The apply of version ${p.versionNumber} is still running after ${ctx.flags.timeout}s.`, { hint: "Check `ulams course status`; the apply continues on the server." });
    }
    ctx.io.stderr("waiting for the apply ...");
    await new Promise<void>((r) => setTimeout(r, delay));
    delay = Math.min(delay * 2, 5000);
  }
}

async function startApply(ctx: Ctx, sessionId: string, courseId: number | null, overwrite: boolean | undefined, expected: string | null) {
  const res = await server(() => call<{ runId: string | null }>(ctx, "POST", "/sessions/{session}/apply", { params: { session: sessionId }, body: overwrite ? { overwrite: true } : {} }));
  if (ctx.flags.wait && courseId !== null) {
    const done = await waitApplied(ctx, courseId, expected);
    return { runId: res.runId, applied: true, versionId: done.versionId, versionNumber: done.versionNumber, meta: undefined };
  }
  return { runId: res.runId, applied: false, versionId: expected, versionNumber: null, meta: res.runId ? { operation: runHandle(res.runId) } : undefined };
}

interface Prepared {
  folder: DiskFolder;
  base: BaseFile;
  doc: Obj;
  hash: string;
  since: ReturnType<typeof changesSinceBase>;
}

function prepare(ctx: Ctx, dir: string | undefined): Prepared {
  const folder = openFolder(dir);
  const base = readBase(folder.root);
  const local = localDocument(ctx, folder);
  return { folder, base, doc: local.doc, hash: local.hash, since: changesSinceBase(base, local.doc) };
}

const push = defineCommand({
  ...folderCommand,
  id: "course.push",
  summary: "Propose the folder as a new version of the course (nothing changes for learners until it is approved and applied)",
  description:
    "Validates the folder, sends it as a proposed version of kind `import` based on the version in .ulams/base.json, and prints what it changes. Nothing reaches learners: a person approves the version (in the studio, or --approve / --apply here) and it is applied. --approve approves it; --apply approves and applies it (waiting for the apply unless --no-wait). Agent tokens and MCP clients can only propose: approving is refused with APPROVAL_REQUIRES_HUMAN and the proposal stays (see `ulams course apply`). If the server moved on since the base, push stops with SYNC_CONFLICT (exit 6): pull first. A folder equal to its base sends nothing. --dry-run validates and shows what would be sent.",
  kind: "write",
  idempotent: false,
  scopes: WRITE,
  endpoints: ["POST /api/admin/course-as-code/sessions/{session}/imports"],
  positionals: ["dir"],
  input: z.object({
    dir: dirInput,
    message: z.string().max(500).optional().describe("Why, shown in the version history."),
    approve: z.boolean().optional().describe("Approve the proposal after pushing it (people and CLI tokens only)."),
    apply: z.boolean().optional().describe("Approve and apply it; waits for the apply unless --no-wait."),
    overwrite: z.boolean().optional().describe("With --apply: overwrite edits an admin made in the LMS since the last apply."),
  }),
  output: z.unknown(),
  examples: [
    { title: "Propose a change for review", argv: "course push --message 'Fix the install step'" },
    { title: "Propose, approve and apply as yourself", argv: "course push --apply" },
    { title: "Validate and preview without sending", argv: "course push --dry-run --json" },
  ],
  plan: async (ctx, i) => {
    const p = prepare(ctx, i.dir);
    const request = planOf("POST", `/sessions/${p.base.sessionId}/imports`, { document: "<document>", baseVersionId: p.base.baseVersionId, ...(i.message ? { message: i.message } : {}) }).request;
    const then = [...(i.approve || i.apply ? ["POST /versions/<proposed version>/approve"] : []), ...(i.apply ? [`POST /sessions/${p.base.sessionId}/apply`] : [])];
    const plan: Plan = {
      request,
      ...(then.length > 0 ? { then } : {}),
      diff: { against: "base", unchanged: !p.since.modified, counts: p.since.counts, changes: p.since.changes },
      note: p.since.modified ? "Nothing is sent. The server computes the final diff and checks the citations." : "The folder equals its base: a push would send nothing.",
    };
    return plan;
  },
  async run(ctx, i) {
    const p = prepare(ctx, i.dir);
    const { base, folder } = p;
    if (!p.since.modified) {
      return present(ctx, { unchanged: true, base: { versionId: base.baseVersionId, versionNumber: base.baseVersionNumber }, pushed: false }, [`Nothing to push: the folder equals version ${base.baseVersionNumber}.`]);
    }
    const res = await server(() =>
      call<ImportResponse>(ctx, "POST", "/sessions/{session}/imports", { params: { session: base.sessionId }, body: { document: p.doc, baseVersionId: base.baseVersionId, ...(i.message ? { message: i.message } : {}) } })
    );
    if (res.unchanged || !res.version) {
      writeBase(folder.root, { ...base, documentHash: p.hash });
      return present(ctx, { ...res, pushed: false, approved: false, applied: false }, [`The server already has this content (version ${base.baseVersionNumber}); nothing was proposed.`], {});
    }
    const proposal: Proposal = {
      versionId: res.version.id,
      versionNumber: res.version.number,
      baseVersionId: base.baseVersionId,
      documentHash: p.hash,
      message: i.message ?? null,
      counts: res.counts,
      pushedAt: new Date().toISOString(),
    };
    writeProposal(folder.root, proposal);
    const lines = [
      `Proposed version ${res.version.number} (from version ${base.baseVersionNumber}): ${countsText(res.counts)}.`,
      ...changeLines(res.changes),
      ...(res.handEdited.length > 0 ? [`Hand-edited text that the AI wrote: ${res.handEdited.slice(0, 5).join(", ")}${res.handEdited.length > 5 ? ", ..." : ""}`] : []),
      ...res.warnings.slice(0, 5).map((w) => `warning: ${w}`),
    ];
    const data: Record<string, unknown> = { ...res, pushed: true, approved: false, applied: false };
    let meta: Result["meta"] | undefined;

    if (i.approve || i.apply) {
      let approved;
      try {
        approved = await approveVersion(ctx, res.version.id);
      } catch (e) {
        if (e instanceof CliError) throw new CliError(e.code, e.message, { hint: e.hint, status: e.status, requestId: e.requestId, details: { ...e.details, proposed: { versionId: res.version.id, versionNumber: res.version.number } } });
        throw e;
      }
      const moved = advanceBase(folder.root, base, proposal);
      data.approved = true;
      data.approval = approved;
      data.baseMoved = moved;
      lines.push(`Approved version ${approved.number}.`);
      if (i.apply) {
        const applied = await startApply(ctx, base.sessionId, base.courseId, i.overwrite, approved.versionId);
        data.applied = applied.applied;
        data.apply = { runId: applied.runId, versionId: applied.versionId, versionNumber: applied.versionNumber };
        meta = applied.meta;
        lines.push(applied.applied ? `Applied: learners see version ${approved.number}. Publish with \`ulams course publish\`.` : `Apply started${applied.runId ? ` (run ${applied.runId})` : ""}.`);
      } else lines.push("Next: `ulams course apply` to apply it.");
    } else {
      lines.push("Nothing changes for learners until the version is approved and applied.", "Review: `ulams course diff --version " + res.version.id + "`. Approve and apply as a person: `ulams course apply --approve`, or in the studio.");
    }
    return present(ctx, data, lines, { ...(meta ? { meta } : {}) });
  },
});

/* ---------------------------------------------------------------- apply, publish */

function target(i: { session?: string | undefined; dir?: string | undefined }): { root: string | null; base: BaseFile | null; sessionId: string } {
  const root = resolve(i.dir ?? ".");
  const bound = existsSync(root) && hasBase(root);
  if (!bound && i.dir) readBase(root); // an explicit folder must be a bound one: say why it is not
  const base = bound ? readBase(root) : null;
  const usable = base && (!i.session || i.session === base.sessionId) ? base : null;
  const sessionId = i.session ?? usable?.sessionId;
  if (!sessionId) {
    throw new CliError("INPUT_INVALID", "Pass the builder session (`course apply <session>`) or run inside a course folder created with `ulams course init`.", { hint: "`ulams course status` shows the session of the folder." });
  }
  return { root: usable ? root : null, base: usable, sessionId };
}

const apply = defineCommand({
  ...builderCommand,
  id: "course.apply",
  summary: "Apply the course's current version to the live course; with --approve, approve the proposed version first",
  description:
    "Writes the course through the course services as an unpublished draft (publish with `course publish`). Run it in the course folder, or pass the session. When the folder has a proposal from `course push` that nobody approved, apply refuses with CONFIRMATION_REQUIRED (exit 11) unless you pass --approve, which approves it first: only a person can (a login, or a CLI token you created). Agent tokens and MCP clients are refused with APPROVAL_REQUIRES_HUMAN. --version <id> approves a specific proposed version. Waits for the apply to finish unless --no-wait (the folder's course id is needed to wait).",
  kind: "write",
  idempotent: false,
  scopes: WRITE,
  endpoints: ["POST /api/admin/course-as-code/sessions/{session}/apply"],
  positionals: ["session"],
  input: z.object({
    session: sessionInput,
    dir: dirInput,
    version: z.string().optional().describe("The proposed version to approve (default: the one `course push` proposed)."),
    approve: z.boolean().optional().describe("Approve the proposed version before applying (people and CLI tokens only)."),
    overwrite: z.boolean().optional().describe("Overwrite edits an admin made in the course since the last apply."),
  }),
  output: z.unknown(),
  examples: [
    { title: "Approve what you pushed and apply it", argv: "course apply --approve" },
    { title: "Apply a version someone approved in the studio", argv: "course apply" },
    { title: "Apply a session without a folder", argv: "course apply <session> --json" },
  ],
  plan: async (ctx, i) => {
    const t = target(i);
    const proposal = t.root ? readProposal(t.root) : null;
    const versionId = i.version ?? proposal?.versionId;
    const then = [...(versionId && i.approve ? [`POST /versions/${versionId}/approve`] : []), `POST /sessions/${t.sessionId}/apply`];
    return { request: { method: "POST", path: `${COURSE_AS_CODE}/sessions/${t.sessionId}/apply`, ...(i.overwrite ? { body: { overwrite: true } } : {}) }, steps: then, ...(versionId && !i.approve ? { note: `Version ${versionId} is an unapproved proposal: a real run needs --approve.` } : {}) };
  },
  async run(ctx, i) {
    const t = target(i);
    const proposal = t.root ? readProposal(t.root) : null;
    let versionId = i.version ?? proposal?.versionId ?? null;
    let approved: Awaited<ReturnType<typeof approveVersion>> | null = null;
    const lines: string[] = [];

    if (versionId && !i.approve) {
      // approved elsewhere (the studio)? then it is already the current version and applying is right
      const courseId = t.base?.courseId ?? null;
      const current = courseId !== null ? (await fetchExport(ctx, courseId)).versionId : null;
      if (current !== versionId) throw unapproved(versionId, proposal?.versionId === versionId ? proposal.versionNumber : undefined);
      if (t.root && proposal?.versionId === versionId) clearProposal(t.root);
      versionId = null;
    }
    if (versionId && i.approve) {
      approved = await approveVersion(ctx, versionId);
      lines.push(`Approved version ${approved.number}.`);
      if (t.root && t.base && proposal && proposal.versionId === versionId) advanceBase(t.root, t.base, proposal);
    }
    const res = await startApply(ctx, t.sessionId, t.base?.courseId ?? null, i.overwrite, approved?.currentVersionId ?? null);
    lines.push(res.applied ? `Applied version ${res.versionNumber}: learners see it once the course is published (\`ulams course publish\`).` : `Apply started${res.runId ? ` (run ${res.runId})` : ""}.`);
    return present(ctx, { sessionId: t.sessionId, runId: res.runId, approved: approved !== null, ...(approved ? { approval: approved } : {}), applied: res.applied, versionId: res.versionId }, lines, { ...(res.meta ? { meta: res.meta } : {}) });
  },
});

const publish = defineCommand({
  ...builderCommand,
  id: "course.publish",
  summary: "Publish the applied course so learners can enrol",
  description:
    "Runs the publish check and publishes. Blocking items stop it (CONFLICT, error.details.blocking); warnings stop it until you have read them and pass --acknowledge-warnings (CONFIRMATION_REQUIRED, error.details.warnings). The course must be applied first. Run it in the course folder, or pass the session.",
  kind: "write",
  idempotent: true,
  scopes: WRITE,
  endpoints: ["POST /api/admin/course-as-code/sessions/{session}/publish"],
  positionals: ["session"],
  input: z.object({ session: sessionInput, dir: dirInput, acknowledgeWarnings: z.boolean().optional().describe("Acknowledge the publish warnings after reviewing them.") }),
  output: z.unknown(),
  examples: [
    { title: "Publish from the course folder", argv: "course publish" },
    { title: "Publish after reading the warnings", argv: "course publish --acknowledge-warnings --json" },
  ],
  plan: async (ctx, i) => planOf("POST", `/sessions/${target(i).sessionId}/publish`, { acknowledgedWarnings: Boolean(i.acknowledgeWarnings) }),
  async run(ctx, i) {
    const t = target(i);
    try {
      const res = await call<{ courseId: number; published: boolean }>(ctx, "POST", "/sessions/{session}/publish", { params: { session: t.sessionId }, body: { acknowledgedWarnings: Boolean(i.acknowledgeWarnings) } });
      return present(ctx, res, [`Published course ${res.courseId}. Learners can enrol.`]);
    } catch (e) {
      if (!(e instanceof CliError) || e.status !== 409) throw translate(e);
      const data = serverBody(e).data;
      const blocking = Array.isArray(data.blocking) ? data.blocking : [];
      const warnings = Array.isArray(data.warnings) ? data.warnings : [];
      if (blocking.length > 0) throw new CliError("CONFLICT", e.message, { status: 409, requestId: e.requestId, hint: "Fix the blocking items in the course, push and apply again, then publish.", details: { blocking, warnings } });
      if (warnings.length > 0) throw new CliError("CONFIRMATION_REQUIRED", e.message, { status: 409, requestId: e.requestId, hint: "Read error.details.warnings, then re-run with --acknowledge-warnings.", details: { warnings } });
      throw new CliError("CONFLICT", e.message, { status: 409, requestId: e.requestId, hint: /apply the course/i.test(e.message) ? "Run `ulams course apply` first." : e.hint });
    }
  },
});

export const courseSyncCommands: AnyCommand[] = [init, pull, diff, status, push, apply, publish];
