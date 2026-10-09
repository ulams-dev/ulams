import { parseAllDocuments, stringify as yamlStringify } from "yaml";
import { z } from "zod";
import { CliError } from "../errors.ts";
import { diffFields, type Change } from "../registry/diff.ts";
import { markdownToHtml } from "../util-markdown.ts";
import { defineCommand } from "./define.ts";
import type { AnyCommand, Ctx } from "../registry/types.ts";

/** Declarative manifests (plan 7.3): idempotent apply of Course (with lessons and topics), User, Group, Setting, Page and Access. */
export const API_VERSION = "ulams.dev/v1";

export interface Manifest {
  apiVersion?: string;
  kind: string;
  metadata?: { key?: string; id?: number };
  spec: Record<string, unknown>;
}

export interface ApplyResult {
  kind: string;
  key: string;
  id: number | null;
  action: "created" | "updated" | "unchanged" | "failed";
  changes?: Change[];
  error?: string;
  children?: ApplyResult[];
}

type Json = Record<string, unknown>;
const call = <T = unknown>(ctx: Ctx, method: "GET" | "POST" | "PUT" | "PATCH" | "DELETE", path: string, o: { params?: Record<string, string | number>; query?: Record<string, string | number | boolean>; body?: unknown } = {}) =>
  ctx.client.call<T>(method, path, { ...o, idempotent: method !== "POST", signal: ctx.signal });

async function sha256(text: string): Promise<string> {
  const buf = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(text));
  return [...new Uint8Array(buf)].map((b) => b.toString(16).padStart(2, "0")).join("");
}

/** Idempotency-Key = sha256(instance + kind + key + spec hash) so a retried apply replays instead of duplicating. */
async function idemKey(ctx: Ctx, kind: string, key: string, spec: unknown): Promise<string> {
  return (await sha256(`${ctx.profile.url}|${kind}|${key}|${JSON.stringify(spec)}`)).slice(0, 48);
}

async function write<T = unknown>(ctx: Ctx, kind: string, key: string, spec: unknown, method: "POST" | "PUT" | "PATCH", path: string, o: { params?: Record<string, string | number>; body: unknown }): Promise<T> {
  const idempotencyKey = await idemKey(ctx, kind, `${key}:${method}:${path}`, o.body);
  return (await ctx.client.call<T>(method, path, { ...o, idempotencyKey, signal: ctx.signal })).data;
}

export function parseManifests(text: string, label = "manifest"): Manifest[] {
  const docs = parseAllDocuments(text);
  const out: Manifest[] = [];
  docs.forEach((doc, i) => {
    if (doc.errors.length) throw new CliError("INPUT_INVALID", `${label} document ${i + 1}: ${doc.errors[0]?.message.split("\n")[0]}`);
    const value = doc.toJS() as unknown;
    if (value === null || value === undefined) return;
    if (typeof value !== "object" || Array.isArray(value)) throw new CliError("INPUT_INVALID", `${label} document ${i + 1} must be an object.`);
    const m = value as Manifest;
    if (!m.kind || typeof m.kind !== "string") throw new CliError("INPUT_INVALID", `${label} document ${i + 1} has no kind.`, { hint: `Supported kinds: ${Object.keys(HANDLERS).join(", ")}.` });
    if (!HANDLERS[m.kind]) throw new CliError("INPUT_INVALID", `Unsupported kind "${m.kind}" in ${label} document ${i + 1}.`, { hint: `Supported kinds: ${Object.keys(HANDLERS).join(", ")}.` });
    if (m.apiVersion && m.apiVersion !== API_VERSION) throw new CliError("INPUT_INVALID", `Unsupported apiVersion "${m.apiVersion}" (expected ${API_VERSION}).`);
    if (!m.spec || typeof m.spec !== "object") throw new CliError("INPUT_INVALID", `${label} document ${i + 1} (${m.kind}) has no spec.`);
    out.push(m);
  });
  if (out.length === 0) throw new CliError("INPUT_INVALID", `${label} contains no documents.`);
  return out;
}

async function asText(ctx: Ctx, value: unknown): Promise<string> {
  const text = String(value ?? "");
  if (text.startsWith("@")) {
    try {
      return await ctx.fs.readText(text.slice(1));
    } catch {
      throw new CliError("INPUT_INVALID", `Cannot read ${text.slice(1)} (referenced as ${text}).`);
    }
  }
  return text;
}

// ------------------------------------------------------------------------------------------------ handlers

interface Handler {
  apply(ctx: Ctx, m: Manifest, dry: boolean, prune: boolean): Promise<ApplyResult>;
  /** Live resource -> manifest, for `ulams get <kind> <key>`. */
  export?(ctx: Ctx, key: string): Promise<Manifest>;
}

const keyOf = (m: Manifest, fallback: string): string => String(m.metadata?.key ?? fallback);

function result(kind: string, key: string, id: number | null, action: ApplyResult["action"], changes?: Change[]): ApplyResult {
  return { kind, key, id, action, ...(changes && changes.length ? { changes } : {}) };
}

async function findCourse(ctx: Ctx, m: Manifest): Promise<Json | null> {
  if (m.metadata?.id) {
    return (await call<Json>(ctx, "GET", "/api/admin/courses/{course}", { params: { course: m.metadata.id } }).catch(() => null))?.data ?? null;
  }
  const title = String(m.spec.title ?? "");
  const list = (await call<Json[]>(ctx, "GET", "/api/admin/courses", { query: { title, per_page: 100 } })).data;
  const exact = (Array.isArray(list) ? list : []).filter((c) => c.title === title);
  if (exact.length > 1) {
    throw new CliError("CONFLICT", `${exact.length} courses are titled "${title}".`, { hint: "Add metadata.id to say which one.", details: { ids: exact.map((c) => c.id) } });
  }
  const found = exact[0];
  return found ? (await call<Json>(ctx, "GET", "/api/admin/courses/{course}", { params: { course: found.id as number } })).data : null;
}

const COURSE_FIELDS = ["title", "subtitle", "summary", "description", "status", "language", "level", "duration", "target_group", "hours_to_complete", "public", "findable", "teaser_url", "scorm_sco_id", "active_from", "active_to", "image_path", "video_path"];
const pick = (spec: Json, keys: string[]): Json => Object.fromEntries(keys.filter((k) => spec[k] !== undefined).map((k) => [k, spec[k]]));

async function applyCourse(ctx: Ctx, m: Manifest, dry: boolean, prune: boolean): Promise<ApplyResult> {
  const key = keyOf(m, String(m.spec.title ?? ""));
  const desired = pick(m.spec, COURSE_FIELDS);
  if (!desired.title) throw new CliError("INPUT_INVALID", `Course ${key}: spec.title is required.`);
  let course = await findCourse(ctx, m);
  let res: ApplyResult;
  if (!course) {
    res = result("Course", key, null, "created", diffFields(null, desired));
    if (!dry) {
      const created = await write<Json>(ctx, "Course", key, m.spec, "POST", "/api/admin/courses", { body: desired });
      course = created;
      res.id = Number(created.id);
    }
  } else {
    const changes = diffFields(course, desired);
    res = result("Course", key, Number(course.id), changes.length ? "updated" : "unchanged", changes);
    if (changes.length && !dry) {
      const body = { title: desired.title, ...Object.fromEntries(changes.map((c) => [c.path.slice(1), c.to])) };
      await write(ctx, "Course", key, m.spec, "POST", "/api/admin/courses/{id}", { params: { id: Number(course.id) }, body });
    }
  }
  const lessons = (m.spec.lessons as Json[] | undefined) ?? [];
  const existingLessons = ((course?.lessons as Json[] | undefined) ?? []).slice();
  res.children = [];
  for (const [li, l] of lessons.entries()) {
    const lr = await applyLesson(ctx, res.id, existingLessons, l, li + 1, dry, prune);
    res.children.push(lr);
    if (lr.action !== "unchanged") res.action = res.action === "created" ? "created" : "updated";
    for (const c of lr.children ?? []) if (c.action !== "unchanged" && res.action === "unchanged") res.action = "updated";
  }
  if (prune && course) {
    const wanted = new Set(lessons.map((l) => String(l.title)));
    for (const old of existingLessons.filter((l) => !wanted.has(String(l.title)))) {
      res.children.push(result("Lesson", String(old.title), Number(old.id), "updated", [{ op: "remove", path: `/lessons/${old.id}`, from: old.title }]));
      res.action = "updated";
      if (!dry) await call(ctx, "DELETE", "/api/admin/lessons/{id}", { params: { id: Number(old.id) } });
    }
  }
  return res;
}

async function applyLesson(ctx: Ctx, courseId: number | null, existing: Json[], l: Json, order: number, dry: boolean, prune: boolean): Promise<ApplyResult> {
  const title = String(l.title ?? "");
  const key = String(l.key ?? title);
  if (!title) throw new CliError("INPUT_INVALID", "Every lesson needs a title.");
  const desired = { ...pick(l, ["title", "summary", "duration"]), order };
  const found = existing.find((e) => e.title === title);
  let res: ApplyResult;
  if (!found) {
    res = result("Lesson", key, null, "created", diffFields(null, desired));
    if (!dry && courseId) {
      const created = await write<Json>(ctx, "Lesson", key, l, "POST", "/api/admin/lessons", { body: { ...desired, course_id: courseId } });
      res.id = Number(created.id);
    }
  } else {
    const changes = diffFields(found, desired);
    res = result("Lesson", key, Number(found.id), changes.length ? "updated" : "unchanged", changes);
    if (changes.length && !dry) await write(ctx, "Lesson", key, l, "PUT", "/api/admin/lessons/{id}", { params: { id: Number(found.id) }, body: { title, ...Object.fromEntries(changes.map((c) => [c.path.slice(1), c.to])) } });
  }
  const topics = (l.topics as Json[] | undefined) ?? [];
  const existingTopics = ((found?.topics as Json[] | undefined) ?? []).slice();
  res.children = [];
  for (const [ti, t] of topics.entries()) {
    const tr = await applyTopic(ctx, res.id, existingTopics, t, ti + 1, dry);
    res.children.push(tr);
    if (tr.action !== "unchanged" && res.action === "unchanged") res.action = "updated";
  }
  if (prune && found) {
    const wanted = new Set(topics.map((t) => String(t.title)));
    for (const old of existingTopics.filter((t) => !wanted.has(String(t.title)))) {
      res.children.push(result("Topic", String(old.title), Number(old.id), "updated", [{ op: "remove", path: `/topics/${old.id}`, from: old.title }]));
      res.action = "updated";
      if (!dry) await call(ctx, "DELETE", "/api/admin/topics/{id}", { params: { id: Number(old.id) } });
    }
  }
  return res;
}

const TOPIC_TYPES: Record<string, string> = {
  richtext: "Ulams\\TopicTypes\\Models\\TopicContent\\RichText",
  oembed: "Ulams\\TopicTypes\\Models\\TopicContent\\OEmbed",
};

async function applyTopic(ctx: Ctx, lessonId: number | null, existing: Json[], t: Json, order: number, dry: boolean): Promise<ApplyResult> {
  const title = String(t.title ?? "");
  const key = String(t.key ?? title);
  const type = String(t.type ?? "richtext");
  const cls = TOPIC_TYPES[type];
  if (!cls) throw new CliError("INPUT_INVALID", `Topic "${title}": type "${type}" is not supported by apply (use ${Object.keys(TOPIC_TYPES).join(" or ")}); create other types with \`ulams topics create-${type}\`.`);
  if (!title) throw new CliError("INPUT_INVALID", "Every topic needs a title.");
  let value: string;
  if (type === "richtext") {
    if (t.markdown !== undefined) value = markdownToHtml(await asText(ctx, t.markdown));
    else value = await asText(ctx, t.html ?? "");
  } else value = String(t.url ?? "");
  const found = existing.find((e) => e.title === title);
  const base = { ...pick(t, ["summary", "duration", "preview", "active"]), order };
  let res: ApplyResult;
  if (!found) {
    res = result("Topic", key, null, "created", diffFields(null, { title, type, value, ...base }));
    if (!dry && lessonId) {
      const created = await write<Json>(ctx, "Topic", key, t, "POST", "/api/admin/topics", { body: { ...base, title, lesson_id: lessonId, topicable_type: cls, value } });
      res.id = Number(created.id);
    }
    return res;
  }
  const full = (await call<Json>(ctx, "GET", "/api/admin/topics/{id}", { params: { id: Number(found.id) } })).data;
  const current = ((full.topicable as Json | undefined)?.value as string | undefined) ?? "";
  const changes = diffFields(full, base);
  if (current.trim() !== value.trim()) changes.push({ op: "replace", path: "/value", from: current.length > 60 ? `${current.slice(0, 60)}...` : current, to: value.length > 60 ? `${value.slice(0, 60)}...` : value });
  res = result("Topic", key, Number(found.id), changes.length ? "updated" : "unchanged", changes);
  if (changes.length && !dry) {
    await write(ctx, "Topic", key, t, "PUT", "/api/admin/topics/{id}", { params: { id: Number(found.id) }, body: { title, ...base, topicable_type: cls, value } });
  }
  return res;
}

async function findByList(ctx: Ctx, path: string, query: Record<string, string | number>, match: (row: Json) => boolean): Promise<Json | null> {
  const rows = (await call<Json[]>(ctx, "GET", path, { query: { ...query, per_page: 100 } })).data;
  return (Array.isArray(rows) ? rows : []).find(match) ?? null;
}

async function genericUpsert(ctx: Ctx, kind: string, m: Manifest, dry: boolean, o: { key: string; find: () => Promise<Json | null>; fields: Json; createPath: string; updatePath: string; updateMethod: "PUT" | "PATCH"; createBody?: Json }): Promise<ApplyResult> {
  const existing = await o.find();
  if (!existing) {
    const res = result(kind, o.key, null, "created", diffFields(null, o.fields));
    if (!dry) res.id = Number((await write<Json>(ctx, kind, o.key, m.spec, "POST", o.createPath, { body: o.createBody ?? o.fields })).id);
    return res;
  }
  const changes = diffFields(existing, o.fields);
  const res = result(kind, o.key, Number(existing.id), changes.length ? "updated" : "unchanged", changes);
  if (changes.length && !dry) await write(ctx, kind, o.key, m.spec, o.updateMethod, o.updatePath, { params: { id: Number(existing.id) }, body: { ...o.fields } });
  return res;
}

const HANDLERS: Record<string, Handler> = {
  Course: {
    apply: applyCourse,
    async export(ctx, key) {
      const m: Manifest = { kind: "Course", spec: { title: key } };
      const course = await findCourse(ctx, m);
      if (!course) throw new CliError("NOT_FOUND", `No course titled "${key}".`, { hint: "List courses with `ulams courses list`." });
      const lessons = ((course.lessons as Json[] | undefined) ?? []).map((l) => ({
        title: l.title,
        ...(l.summary ? { summary: l.summary } : {}),
        topics: ((l.topics as Json[] | undefined) ?? []).map((t) => ({ title: t.title, type: String(t.topicable_type ?? "").split("\\").pop()?.toLowerCase() ?? "richtext" })),
      }));
      return { apiVersion: API_VERSION, kind: "Course", metadata: { key, id: Number(course.id) }, spec: { ...Object.fromEntries(COURSE_FIELDS.filter((f) => course[f] !== null && course[f] !== undefined).map((f) => [f, course[f]])), lessons } };
    },
  },
  User: {
    async apply(ctx, m, dry) {
      const email = String(m.spec.email ?? "");
      if (!email) throw new CliError("INPUT_INVALID", "User: spec.email is required.");
      const { password, ...rest } = m.spec as Json;
      const fields = { ...pick(rest, ["first_name", "last_name", "name", "is_active"]), email };
      return genericUpsert(ctx, "User", m, dry, {
        key: keyOf(m, email),
        find: () => findByList(ctx, "/api/admin/users", { search: email }, (u) => u.email === email),
        fields,
        createBody: { ...fields, ...(password ? { password } : {}), ...(m.spec.roles ? { roles: m.spec.roles } : {}) },
        createPath: "/api/admin/users",
        updatePath: "/api/admin/users/{id}",
        updateMethod: "PATCH",
      });
    },
  },
  Group: {
    async apply(ctx, m, dry) {
      const name = String(m.spec.name ?? "");
      if (!name) throw new CliError("INPUT_INVALID", "Group: spec.name is required.");
      return genericUpsert(ctx, "Group", m, dry, {
        key: keyOf(m, name),
        find: () => findByList(ctx, "/api/admin/user-groups/", { search: name }, (g) => g.name === name),
        fields: pick(m.spec, ["name", "registerable", "parent_id"]),
        createPath: "/api/admin/user-groups/",
        updatePath: "/api/admin/user-groups/{id}",
        updateMethod: "PUT",
      });
    },
  },
  Page: {
    async apply(ctx, m, dry) {
      const title = String(m.spec.title ?? "");
      if (!title) throw new CliError("INPUT_INVALID", "Page: spec.title is required.");
      const content = m.spec.markdown !== undefined ? markdownToHtml(await asText(ctx, m.spec.markdown)) : await asText(ctx, m.spec.content ?? "");
      const me = (await call<Json>(ctx, "GET", "/api/profile/me")).data;
      return genericUpsert(ctx, "Page", m, dry, {
        key: keyOf(m, title),
        find: () => findByList(ctx, "/api/admin/pages", {}, (p) => p.title === title),
        fields: { title, content, ...pick(m.spec, ["active"]) },
        createBody: { title, content, author_id: me.id, ...pick(m.spec, ["active"]) },
        createPath: "/api/admin/pages",
        updatePath: "/api/admin/pages/{id}",
        updateMethod: "PATCH",
      });
    },
  },
  Setting: {
    async apply(ctx, m, dry) {
      const group = String(m.spec.group ?? "");
      const key = String(m.spec.key ?? "");
      if (!group || !key) throw new CliError("INPUT_INVALID", "Setting: spec.group and spec.key are required.");
      const value = String(m.spec.value ?? "");
      const found = await findByList(ctx, "/api/admin/settings", { group }, (s) => s.key === key && s.group === group);
      if (!found) {
        const res = result("Setting", `${group}.${key}`, null, "created", [{ op: "add", path: `/${group}/${key}`, to: value }]);
        if (!dry) res.id = Number((await write<Json>(ctx, "Setting", `${group}.${key}`, m.spec, "POST", "/api/admin/settings", { body: { group, key, value, type: "text", public: group === "theme" } })).id);
        return res;
      }
      if (String(found.value) === value) return result("Setting", `${group}.${key}`, Number(found.id), "unchanged");
      const res = result("Setting", `${group}.${key}`, Number(found.id), "updated", [{ op: "replace", path: `/${group}/${key}`, from: found.value, to: value }]);
      if (!dry) await write(ctx, "Setting", `${group}.${key}`, m.spec, "PUT", "/api/admin/settings/{id}", { params: { id: Number(found.id) }, body: { group, key, value, type: "text" } });
      return res;
    },
  },
  Access: {
    async apply(ctx, m, dry, prune) {
      const title = String(m.spec.course ?? "");
      const course = await findCourse(ctx, { kind: "Course", spec: { title }, ...(typeof m.spec.course === "number" ? { metadata: { id: m.spec.course } } : {}) });
      const key = keyOf(m, `access:${title}`);
      if (!course) throw new CliError("NOT_FOUND", `Access ${key}: no course "${title}".`);
      const emails = ((m.spec.users as string[] | undefined) ?? []).map(String);
      const groupNames = ((m.spec.groups as string[] | undefined) ?? []).map(String);
      const userIds: number[] = [];
      for (const email of emails) {
        const u = await findByList(ctx, "/api/admin/users", { search: email }, (x) => x.email === email);
        if (!u) throw new CliError("NOT_FOUND", `Access ${key}: no user ${email}.`);
        userIds.push(Number(u.id));
      }
      const groupIds: number[] = [];
      for (const name of groupNames) {
        const g = await findByList(ctx, "/api/admin/user-groups/", { search: name }, (x) => x.name === name);
        if (!g) throw new CliError("NOT_FOUND", `Access ${key}: no group ${name}.`);
        groupIds.push(Number(g.id));
      }
      const now = (await call<{ users?: Json[]; groups?: Json[] }>(ctx, "GET", "/api/admin/courses/{id}/access", { params: { id: Number(course.id) } })).data;
      const haveU = new Set((now.users ?? []).map((u) => Number(u.id)));
      const haveG = new Set((now.groups ?? []).map((g) => Number(g.id)));
      const addU = userIds.filter((id) => !haveU.has(id));
      const addG = groupIds.filter((id) => !haveG.has(id));
      const remU = prune ? [...haveU].filter((id) => !userIds.includes(id)) : [];
      const remG = prune ? [...haveG].filter((id) => !groupIds.includes(id)) : [];
      const changes: Change[] = [
        ...addU.map((id): Change => ({ op: "add", path: "/users", to: id })),
        ...addG.map((id): Change => ({ op: "add", path: "/groups", to: id })),
        ...remU.map((id): Change => ({ op: "remove", path: "/users", from: id })),
        ...remG.map((id): Change => ({ op: "remove", path: "/groups", from: id })),
      ];
      if (!dry) {
        if (addU.length || addG.length) await write(ctx, "Access", key, m.spec, "POST", "/api/admin/courses/{id}/access/add", { params: { id: Number(course.id) }, body: { users: addU, groups: addG } });
        if (remU.length || remG.length) await write(ctx, "Access", key, m.spec, "POST", "/api/admin/courses/{id}/access/remove", { params: { id: Number(course.id) }, body: { users: remU, groups: remG } });
      }
      return result("Access", key, Number(course.id), changes.length ? "updated" : "unchanged", changes);
    },
  },
};

/** Applies manifests in order; a failure stops the run and is reported on its item. */
export async function applyManifests(ctx: Ctx, manifests: Manifest[], dry: boolean, prune: boolean): Promise<ApplyResult[]> {
  const out: ApplyResult[] = [];
  for (const m of manifests) {
    const handler = HANDLERS[m.kind] as Handler;
    try {
      out.push(await handler.apply(ctx, m, dry, prune));
    } catch (error) {
      if (error instanceof CliError && (error.code === "INPUT_INVALID" || error.code === "AUTH_EXPIRED" || error.code === "NETWORK")) throw error;
      out.push({ kind: m.kind, key: keyOf(m, String(m.spec.title ?? m.spec.name ?? m.spec.email ?? m.spec.key ?? m.kind)), id: null, action: "failed", error: (error as Error).message });
      break;
    }
  }
  return out;
}

const flat = (r: ApplyResult): ApplyResult[] => [r, ...(r.children ?? []).flatMap(flat)];

const common = { audience: ["admin" as const], scopes: ["courses:write"], mcp: { expose: false } };

export const applyCommands: AnyCommand[] = [
  defineCommand({
    ...common,
    id: "apply",
    summary: "Apply YAML or JSON manifests (Course, User, Group, Page, Setting, Access) idempotently",
    description:
      "Each document has apiVersion ulams.dev/v1, kind, metadata.key and spec. Courses may nest lessons and topics (richtext or oembed). Existing objects are found by title, email, name or group+key and only changed fields are written, so a second run performs no writes. --dry-run prints the plan; --exit-code exits 13 when it contains changes; --prune also removes lessons, topics and access entries missing from the manifest (needs --yes).",
    kind: "write",
    idempotent: true,
    endpoints: [],
    input: z.object({
      file: z.string().describe("Manifest file, or - for stdin."),
      exitCode: z.boolean().optional().describe("With --dry-run: exit 13 when there are changes."),
      prune: z.boolean().optional().describe("Delete children missing from the manifest (destructive)."),
    }),
    positionals: [],
    output: z.unknown(),
    examples: [
      { title: "Preview a course manifest", argv: "apply --file course.yaml --dry-run --json" },
      { title: "Apply it", argv: "apply --file course.yaml --json" },
    ],
    kindFor: (i) => (i.prune ? "destructive" : "write"),
    async plan(ctx, i) {
      const res = await applyManifests(ctx, parseManifests(await readManifest(ctx, i.file as string), i.file as string), true, Boolean(i.prune));
      return { results: res as unknown as never, changes: res.flatMap(flat).flatMap((r) => r.changes ?? []) };
    },
    async run(ctx, i) {
      const manifests = parseManifests(await readManifest(ctx, i.file as string), i.file as string);
      const res = await applyManifests(ctx, manifests, false, Boolean(i.prune));
      if (res.some((r) => r.action === "failed")) {
        const failed = res.find((r) => r.action === "failed") as ApplyResult;
        throw new CliError("SERVER_ERROR", `Apply failed on ${failed.kind} ${failed.key}: ${failed.error}`, { retryable: false, details: { results: res as unknown as never } });
      }
      return { data: res };
    },
  }),
  defineCommand({
    ...common,
    id: "get",
    summary: "Export a live resource as a manifest (kind and key): the inverse of apply",
    kind: "read",
    idempotent: true,
    scopes: ["courses:read"],
    endpoints: [],
    positionals: ["kind", "key"],
    input: z.object({ kind: z.string().describe("Course (more kinds follow)."), key: z.string().describe("The course title.") }),
    output: z.unknown(),
    examples: [{ title: "Course as YAML", argv: 'get Course "Kubernetes 101" --output yaml' }],
    async run(ctx, i) {
      const h = HANDLERS[i.kind];
      if (!h?.export) throw new CliError("INPUT_INVALID", `get supports: ${Object.entries(HANDLERS).filter(([, v]) => v.export).map(([k]) => k).join(", ")}.`);
      return { data: await h.export(ctx, i.key) };
    },
  }),
];

async function readManifest(ctx: Ctx, file: string): Promise<string> {
  if (file === "-") return ctx.readStdin();
  try {
    return await ctx.fs.readText(file);
  } catch {
    throw new CliError("INPUT_INVALID", `Cannot read ${file}.`, { hint: "Pass --file <path> or --file - for stdin." });
  }
}

export { yamlStringify };
