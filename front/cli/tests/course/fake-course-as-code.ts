import { readFolder } from "../../src/course/disk.ts";
import { compareDocuments, countChanges, documentHash } from "../../src/course/sync.ts";
import type { Obj } from "../../src/course/types.ts";
import { validateFiles } from "../../src/course/validate.ts";
import type { Handler, RecordedRequest, Reply } from "../helpers.ts";
import { example } from "./helpers.ts";

/**
 * An in-memory course-as-code server with the rules of api/packages/course-builder (CourseAsCodeController,
 * CourseImporter): a push is based on the current version or refused with BASE_OUTDATED, an import is always a
 * proposed version, approving needs a person and a current parent (STALE_PROPOSAL), apply finishes after a few polls
 * of the export, publish needs an applied course. It keeps what learners would see (`live`) so tests can assert it.
 */

export interface FakeOptions {
  courseId?: number;
  /** The course the server holds (default: the kubernetes-101 example). */
  document?: Obj;
  /** Polls of the export until an apply has finished. */
  applyTicks?: number;
  /** Approving is refused the way it is for agent tokens and MCP clients. */
  agentToken?: boolean;
  /** Pushes are refused as invalid. */
  rejectImport?: string[];
  /** The publish check has warnings that must be acknowledged. */
  publishWarnings?: string[];
  /** The publish check blocks. */
  publishBlocking?: string[];
  /** Fail every call with this status (network and server error tests). */
  failWith?: number;
}

interface Version {
  id: string;
  number: number;
  kind: string;
  status: "proposed" | "approved";
  parentId: string | null;
  document: Obj;
}

const ok = (data: unknown, status = 200): Reply => ({ status, body: { success: true, data, message: "OK" } });
const fail = (status: number, message: string, code?: string, data?: unknown): Reply => ({
  status,
  body: { success: false, message, ...(code ? { code } : {}), ...(data ? { data } : {}) },
});

/** The kubernetes-101 example as the server holds it: every id a ULID, no local images. */
export function remoteDocument(): Obj {
  const folder = readFolder(example);
  const doc = validateFiles(folder.files, folder.assets).doc;
  const strip = (v: unknown): unknown => {
    if (typeof v === "string") return v.replace(/!\[[^\]]*\]\([^)]*\)\n?/g, "").trim();
    if (Array.isArray(v)) return v.map(strip);
    if (v && typeof v === "object") return Object.fromEntries(Object.entries(v).map(([k, x]) => [k, k === "id" || k === "citations" ? x : strip(x)]));
    return v;
  };
  return strip(JSON.parse(JSON.stringify(doc))) as Obj;
}

const vid = (n: number) => `0ver${String(n).padStart(4, "0")}`.padEnd(26, "0");

export function fakeCourseAsCode(options: FakeOptions = {}) {
  const courseId = options.courseId ?? 12;
  const sessionId = "0ses0000".padEnd(26, "0");
  const start = options.document ?? remoteDocument();
  const versions: Version[] = [{ id: vid(1), number: 1, kind: "content", status: "approved", parentId: null, document: JSON.parse(JSON.stringify(start)) as Obj }];
  const db = {
    versions,
    currentVersionId: vid(1),
    appliedVersionId: vid(1) as string | null,
    /** What learners see: the document of the last applied version. */
    live: JSON.parse(JSON.stringify(start)) as Obj,
    published: false,
    applying: null as null | { ticks: number; versionId: string },
    calls: [] as string[],
    drift: [] as Array<{ element: string; title: string }>,
  };
  const current = () => versions.find((v) => v.id === db.currentVersionId) as Version;

  /** Someone edits the course in the studio: a new approved current version (and, optionally, applies it). */
  function studioEdit(edit: (doc: Obj) => void, applied = true): Version {
    const doc = JSON.parse(JSON.stringify(current().document)) as Obj;
    edit(doc);
    const v: Version = { id: vid(versions.length + 1), number: versions.length + 1, kind: "update", status: "approved", parentId: current().id, document: doc };
    versions.push(v);
    db.currentVersionId = v.id;
    if (applied) {
      db.appliedVersionId = v.id;
      db.live = doc;
    }
    return v;
  }

  const P = "/api/admin/course-as-code";
  const handler: Handler = (req: RecordedRequest): Reply => {
    if (options.failWith) return fail(options.failWith, "The server is having a bad day.");
    if (!req.path.startsWith(P)) return fail(404, `no mock for ${req.method} ${req.path}`);
    const path = req.path.slice(P.length);
    db.calls.push(`${req.method} ${path}`);
    const body = (req.body ?? {}) as Record<string, unknown>;
    let m: RegExpMatchArray | null;

    if (req.method === "GET" && (m = path.match(/^\/courses\/([^/]+)\/blueprint$/))) {
      if (m[1] !== String(courseId)) return fail(404, "Course not found.");
      if (db.applying && --db.applying.ticks <= 0) {
        db.appliedVersionId = db.applying.versionId;
        db.live = (versions.find((v) => v.id === db.applying?.versionId) as Version).document;
        db.applying = null;
      }
      const v = current();
      return ok({ courseId, sessionId, versionId: v.id, versionNumber: v.number, kind: v.kind, status: v.status, appliedVersionId: db.appliedVersionId, schemaVersion: 3, document: v.document, drift: db.drift });
    }
    if (req.method === "POST" && (m = path.match(/^\/sessions\/([^/]+)\/imports$/))) {
      if (m[1] !== sessionId) return fail(404, "Session not found.");
      if (options.rejectImport) return fail(422, "The blueprint is not valid.", "INVALID_BLUEPRINT", { errors: options.rejectImport });
      const base = versions.find((v) => v.id === body.baseVersionId);
      if (!base) return fail(422, "baseVersionId is not a version of this course.", "BASE_UNKNOWN");
      const cur = current();
      if (base.id !== cur.id) {
        return fail(409, "The course changed on the server since your base version; pull first.", "BASE_OUTDATED", {
          currentVersionId: cur.id,
          currentVersionNumber: cur.number,
          remote: compareDocuments(base.document, cur.document),
        });
      }
      const document = { ...(body.document as Obj), sources: cur.document.sources ?? [] };
      if (documentHash(document) === documentHash(cur.document)) {
        return ok({ unchanged: true, version: null, counts: { added: 0, changed: 0, removed: 0 }, changes: [], warnings: [], handEdited: [] });
      }
      const changes = compareDocuments(cur.document, document);
      const v: Version = { id: vid(versions.length + 1), number: versions.length + 1, kind: "import", status: "proposed", parentId: cur.id, document };
      versions.push(v);
      return ok({ unchanged: false, version: { id: v.id, number: v.number, kind: v.kind, status: v.status, parentId: v.parentId }, counts: countChanges(changes), changes, warnings: [], handEdited: [] }, 201);
    }
    if (req.method === "GET" && (m = path.match(/^\/versions\/([^/]+)\/diff$/))) {
      const v = versions.find((x) => x.id === m?.[1]);
      if (!v) return fail(404, "Version not found.");
      const against = versions.find((x) => x.id === (req.query.against ?? v.parentId));
      return ok({ against: against?.id ?? null, changes: compareDocuments(against?.document ?? { course: {}, modules: [] }, v.document) });
    }
    if (req.method === "POST" && (m = path.match(/^\/versions\/([^/]+)\/approve$/))) {
      const v = versions.find((x) => x.id === m?.[1]);
      if (!v) return fail(404, "Version not found.");
      if (options.agentToken) return fail(403, "An agent token can propose changes but only a person can approve them.", "APPROVAL_REQUIRES_HUMAN");
      if (v.status !== "proposed") return fail(409, "This version is not waiting for a decision.", "NOT_PROPOSED");
      if (v.parentId !== db.currentVersionId) return fail(409, "The course changed after this import was proposed. Import again from the current version.", "STALE_PROPOSAL", { currentVersionId: db.currentVersionId });
      v.status = "approved";
      db.currentVersionId = v.id;
      return ok({ versionId: v.id, number: v.number, status: "approved", currentVersionId: v.id, applied: false });
    }
    if (req.method === "POST" && (m = path.match(/^\/sessions\/([^/]+)\/apply$/))) {
      if (m[1] !== sessionId) return fail(404, "Session not found.");
      db.applying = { ticks: options.applyTicks ?? 2, versionId: db.currentVersionId };
      return ok({ runId: "0run0000".padEnd(26, "0") }, 202);
    }
    if (req.method === "POST" && (m = path.match(/^\/sessions\/([^/]+)\/publish$/))) {
      if (m[1] !== sessionId) return fail(404, "Session not found.");
      if (db.appliedVersionId === null) return fail(409, "Apply the course before publishing it.");
      if (options.publishBlocking) return fail(409, options.publishBlocking[0] as string, undefined, { blocking: options.publishBlocking.map((message) => ({ message })), warnings: [] });
      if (options.publishWarnings && !body.acknowledgedWarnings) return fail(409, "Review the warnings and acknowledge them to publish.", undefined, { blocking: [], warnings: options.publishWarnings.map((message) => ({ message })) });
      db.published = true;
      return ok({ courseId, published: true });
    }
    return fail(404, `no mock for ${req.method} ${req.path}`);
  };

  return { handler, db, courseId, sessionId, studioEdit, current, routes: { "*": handler } as Record<string, Handler> };
}
