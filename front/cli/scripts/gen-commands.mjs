#!/usr/bin/env node
// Generates src/generated/commands.ts from spec/openapi.json + spec/overrides.yaml (plan 4.4).
// Pure Node; the only dependency is `yaml`.
import { mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { parse as parseYaml } from "yaml";

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, "..");

const METHODS = ["get", "post", "put", "patch", "delete"];

/** Scope areas by first matching path pattern (plan 6.2). */
const AREAS = [
  [/^\/api\/admin\/(courses|lessons|topics|categories|tags|file|scorm|cmi5|liascript|interactive|h5p|adapt|gift-|topic-project|video|youtube|images|dictionar|csv\/groups)/, "courses"],
  [/^\/api\/admin\/(users|user-groups|user|roles|permissions|csv\/users|user-submissions)/, "users"],
  [/^\/api\/admin\/(course-access-enquiries|consultation-access)/, "enrolments"],
  [/^\/api\/admin\/(settings|config|pages|templates|translations|notifications|bulk-notifications|model-fields|mattermost|mailerlite|events)/, "settings"],
  [/^\/api\/admin\/(webinars|stationary-events|consultations|jitsi|pencil)/, "events"],
  [/^\/api\/admin\/(pdfs)/, "certificates"],
  [/^\/api\/admin\/(orders|products|productables|vouchers|payments|invoices)/, "commerce"],
  [/^\/api\/admin\/(reports|stats|questionnaire|question|quiz-|tasks)/, "reports"],
  [/^\/api\/admin\/lti/, "lti"],
  [/^\/api\/admin\/course-builder/, "builder"],
];

/** Areas with hand-written commands only (builder.ts, living.ts, tenants.ts): runs and operations, not CRUD. */
const HAND_WRITTEN = ["/api/admin/course-builder/", "/api/admin/living-course/", "/api/platform/"];

const kebab = (s) =>
  s
    .replace(/([a-z0-9])([A-Z])/g, "$1-$2")
    .replace(/[_\s]+/g, "-")
    .toLowerCase();

const camelFree = (s) => s.replace(/[^A-Za-z0-9_]/g, "_");

export function loadInputs(rootDir = root) {
  const spec = JSON.parse(readFileSync(resolve(rootDir, "spec/openapi.json"), "utf8"));
  const overrides = parseYaml(readFileSync(resolve(rootDir, "spec/overrides.yaml"), "utf8")) ?? {};
  const exclusions = parseYaml(readFileSync(resolve(rootDir, "spec/exclusions.yaml"), "utf8")) ?? {};
  return { spec, overrides, exclusions };
}

/** True when "METHOD /path" is listed in spec/exclusions.yaml. */
export function isExcluded(exclusions, method, path) {
  for (const entries of Object.values(exclusions ?? {})) {
    for (const entry of entries ?? []) {
      const [m, p] = String(entry).split(" ");
      if (m !== "*" && m !== method) continue;
      if (p.endsWith("*") ? path.startsWith(p.slice(0, -1)) : path === p) return true;
    }
  }
  return false;
}

// ---------------------------------------------------------------- schema -> zod source

function deref(spec, node, seen = []) {
  if (node && node.$ref) {
    const name = node.$ref.replace("#/components/schemas/", "");
    if (seen.includes(name)) return null;
    const target = spec.components?.schemas?.[name];
    return target ? { ...deref(spec, target, [...seen, name]) } : null;
  }
  return node;
}

const q = (s) => JSON.stringify(String(s ?? "").replace(/\s+/g, " ").trim());

/** OpenAPI schema -> zod source text. `depth` limits $ref expansion. */
function zodFor(spec, schema, depth = 0, seen = []) {
  if (!schema) return "z.unknown()";
  if (schema.$ref) {
    const name = schema.$ref.replace("#/components/schemas/", "");
    if (depth >= 6 || seen.includes(name)) return "z.unknown()";
    const target = spec.components?.schemas?.[name];
    return target ? zodFor(spec, target, depth + 1, [...seen, name]) : "z.unknown()";
  }
  if (schema.allOf || schema.oneOf || schema.anyOf) return "z.unknown()";
  let out;
  switch (schema.type) {
    case "string":
      out = schema.enum && schema.enum.every((v) => typeof v === "string") && schema.enum.length > 0 ? `z.enum([${schema.enum.map(q).join(", ")}])` : "z.string()";
      break;
    case "file":
      out = "z.string()";
      break;
    case "integer":
      out = "z.number().int()";
      break;
    case "number":
      out = "z.number()";
      break;
    case "boolean":
      out = "z.boolean()";
      break;
    case "array":
      out = `z.array(${zodFor(spec, schema.items, depth + 1, seen)})`;
      break;
    case "object":
    case undefined: {
      const props = schema.properties;
      if (!props || Object.keys(props).length === 0) {
        out = schema.type === "object" ? "z.record(z.string(), z.unknown())" : "z.unknown()";
        break;
      }
      const required = new Set(schema.required ?? []);
      const fields = Object.entries(props).map(([k, v]) => {
        let f = zodFor(spec, v, depth + 1, seen);
        if (!required.has(k)) f += ".optional()";
        return `${JSON.stringify(k)}: ${f}`;
      });
      out = `z.looseObject({ ${fields.join(", ")} })`;
      break;
    }
    default:
      out = "z.unknown()";
  }
  if (schema.nullable) out += ".nullable()";
  const d = schema.description?.trim();
  if (d && d !== schema.title && depth <= 1) out += `.describe(${q(d)})`;
  return out;
}

// ---------------------------------------------------------------- naming

const VERB_FOR = {
  get: (item) => (item ? "get" : "list"),
  post: () => "create",
  put: () => "update",
  patch: () => "update",
  delete: () => "delete",
};
const SUB_VERB = { get: "list", post: "add", put: "set", patch: "update", delete: "delete" };

function splitPath(path) {
  const segs = path.split("/").filter(Boolean);
  if (segs[0] === "api") segs.shift();
  return segs;
}

function derive(path, method, pathSiblings, overrides) {
  const segs = splitPath(path);
  const admin = segs[0] === "admin";
  if (admin) segs.shift();
  const nounMap = overrides.nouns ?? {};
  const first = segs.find((s) => !s.startsWith("{")) ?? "root";
  const firstIdx = segs.indexOf(first);
  const rest = segs.slice(firstIdx + 1);
  const noun = nounMap[kebab(first)] ?? kebab(first);
  const tail = rest.filter((s) => !s.startsWith("{")).map(kebab);
  const endsWithParam = rest.length > 0 && rest[rest.length - 1].startsWith("{");
  const leadingParamAfterNoun = rest.length > 0 && rest[0].startsWith("{");
  let verb;
  if (tail.length === 0) {
    verb = VERB_FOR[method](endsWithParam);
    if (method === "post" && endsWithParam) {
      // POST on an item is an update sent as multipart (Laravel method spoofing).
      verb = "update";
    }
  } else {
    const sub = tail.join("-");
    // Several methods on the same path: suffix the sub-resource with an action verb.
    const multi = pathSiblings.length > 1;
    verb = multi ? `${sub}-${SUB_VERB[method]}` : sub;
    // Item-level sub-resource that ends on a parameter (e.g. members/{user_id}) with DELETE.
    if (!multi && endsWithParam && !leadingParamAfterNoun && method === "get") verb = sub;
    if (!multi && endsWithParam && method === "delete") verb = `${sub}-delete`;
  }
  return { admin, noun, verb };
}

function areaFor(path) {
  for (const [re, area] of AREAS) if (re.test(path)) return area;
  return null;
}

// ---------------------------------------------------------------- operations

function collectParams(spec, op) {
  const out = [];
  for (const p of op.parameters ?? []) {
    const param = p.$ref ? spec.components.parameters?.[p.$ref.replace("#/components/parameters/", "")] : p;
    if (param) out.push(param);
  }
  return out;
}

function bodyInfo(spec, op) {
  const rb = op.requestBody;
  if (!rb) return { mode: "none" };
  const content = rb.content ?? {};
  const types = Object.keys(content);
  const json = types.find((t) => t === "application/json");
  const multipart = types.find((t) => t.startsWith("multipart/"));
  const form = types.find((t) => t === "application/x-www-form-urlencoded");
  const chosen = json ?? multipart ?? form ?? types[0];
  if (!chosen) return { mode: "none" };
  const schema = content[chosen]?.schema;
  const resolved = deref(spec, schema ?? {});
  const mode = json ? "json" : "multipart";
  const props = resolved?.properties;
  const files = props ? Object.entries(props).filter(([, v]) => deref(spec, v)?.type === "file" || deref(spec, v)?.format === "binary").map(([k]) => k) : [];
  return {
    mode,
    schema,
    resolved,
    required: rb.required === true,
    flat: Boolean(props && Object.keys(props).length > 0 && resolved.type !== "array"),
    files,
    alsoMultipart: Boolean(json && multipart),
  };
}

function isDownload(op) {
  for (const [code, r] of Object.entries(op.responses ?? {})) {
    if (!code.startsWith("2")) continue;
    const types = Object.keys(r.content ?? {});
    if (types.length === 0) return false;
    if (types.includes("application/json")) return false;
    return true;
  }
  return false;
}

function sampleFor(name, schema) {
  if (schema?.enum?.length) return String(schema.enum[0]);
  switch (schema?.type) {
    case "integer":
    case "number":
      return "1";
    case "boolean":
      return "true";
    default:
      return name.includes("email") ? "user@example.com" : name === "title" || name === "name" ? "Example" : "text";
  }
}

export function buildCommands({ spec, overrides, exclusions }) {
  const byKey = overrides.operations ?? {};
  const commands = [];
  const warnings = [];
  const used = new Map();

  const entries = [];
  for (const [path, item] of Object.entries(spec.paths)) {
    const present = METHODS.filter((m) => item[m]);
    for (const m of present) entries.push({ path, method: m, op: item[m], siblings: present });
  }
  entries.sort((a, b) => (a.path === b.path ? METHODS.indexOf(a.method) - METHODS.indexOf(b.method) : a.path.localeCompare(b.path)));

  for (const { path, method, op, siblings } of entries) {
    const key = `${method.toUpperCase()} ${path}`;
    const ov = byKey[key] ?? {};
    if (ov.hide || isExcluded(exclusions, method.toUpperCase(), path) || HAND_WRITTEN.some((prefix) => path.startsWith(prefix))) continue;
    const params = collectParams(spec, op);
    const pathParams = splitPath(path).filter((s) => s.startsWith("{")).map((s) => s.slice(1, -1));
    const queryParams = params.filter((p) => p.in === "query");
    const body = bodyInfo(spec, op);
    const secured = Boolean(op.security?.length);
    const { admin, noun, verb } = derive(path, method, siblings, overrides);

    let id = ov.id;
    if (!id) {
      if (admin) id = `${noun}.${verb}`;
      else id = `${secured ? "my" : "public"}.${noun}-${verb}`;
    }
    // Disambiguate unplanned collisions deterministically, and report them.
    if (used.has(id)) {
      const alt = `${id}-by-${pathParams.map(kebab).join("-") || method}`;
      warnings.push(`duplicate id ${id} (${key} vs ${used.get(id)}), using ${alt}; add an override`);
      id = alt;
    }
    used.set(id, key);

    const kind = ov.kind ?? (method === "get" ? "read" : method === "delete" ? "destructive" : "write");
    const idempotent = ov.idempotent ?? (method === "get" || method === "put" || method === "delete");
    const area = areaFor(path);
    const scopes = ov.scopes ?? (area ? [`${area}:${kind === "read" ? "read" : "write"}`] : secured ? ["learner:" + (kind === "read" ? "read" : "write")] : []);

    // ---- input fields
    const fields = new Map(); // name -> {zod, required, where}
    for (const name of pathParams) {
      const p = params.find((x) => x.in === "path" && x.name === name);
      fields.set(name, { zod: zodFor(spec, p?.schema ?? { type: "string" }) + (p?.description ? `.describe(${q(p.description)})` : ""), required: true, where: "path" });
      if (!p) fields.get(name).zod = 'z.union([z.string(), z.number()])';
    }
    for (const p of queryParams) {
      if (fields.has(p.name)) continue;
      const base = zodFor(spec, p.schema ?? { type: "string" });
      const withDesc = p.description && !base.includes(".describe(") ? `${base}.describe(${q(p.description)})` : base;
      fields.set(p.name, { zod: withDesc, required: Boolean(p.required), where: "query" });
    }
    let bodyMode = body.mode;
    // The spec documents no body for many writes; extra input keys are still sent as JSON.
    if (bodyMode === "none" && method !== "get" && method !== "delete") bodyMode = "json";
    let wholeBody = false;
    const bodyFields = [];
    const files = {};
    if (bodyMode !== "none") {
      if (body.flat) {
        const required = new Set(body.resolved.required ?? []);
        for (const [name, schema] of Object.entries(body.resolved.properties)) {
          if (fields.has(name)) continue;
          const r = deref(spec, schema) ?? {};
          const isFile = r.type === "file" || r.format === "binary";
          let z = isFile ? `z.string().describe(${q((r.description ?? name) + " (path of a local file)")})` : zodFor(spec, schema);
          if (r.description && !z.includes(".describe(")) z += `.describe(${q(r.description)})`;
          // Updates reuse the create schema; the server validates, so a partial update must not be blocked here.
          const isUpdate = method === "put" || method === "patch" || verb === "update";
          fields.set(name, { zod: z, required: required.has(name) && !isUpdate, where: "body" });
          bodyFields.push(name);
          if (isFile) files[name] = name;
        }
      } else {
        wholeBody = true;
        fields.set("body", { zod: `z.unknown().describe(${q("Request body (JSON)")})`, required: body.required, where: "body" });
        bodyFields.push("body");
      }
    }
    if (bodyMode === "multipart" && Object.keys(files).length === 0 && body.flat === false) bodyMode = "json";
    if (ov.upload) {
      // upload: { inputKey: { field, accept } } marks extra file fields (not documented in the spec)
      for (const [inputKey, u] of Object.entries(ov.upload)) {
        fields.set(inputKey, { zod: `z.string().describe(${q(u.description ?? `Path of a local file (${(u.accept ?? []).join(", ") || "any"})`)})`, required: u.required !== false, where: "body" });
        if (!bodyFields.includes(inputKey)) bodyFields.push(inputKey);
        files[inputKey] = u.field;
      }
      bodyMode = "multipart";
    }

    const summary = ov.summary ?? ((op.summary ?? op.description ?? `${method.toUpperCase()} ${path}`).trim().replace(/\s+/g, " "));
    const download = ov.output === "file" || (ov.output !== "json" && isDownload(op));
    if (download) {
      fields.set("out", { zod: `z.string().optional().describe(${q("Write the download to this path (- for stdout)")})`, required: false, where: "local" });
    }
    const paginated = ov.paginated ?? (queryParams.some((p) => p.name === "page") && queryParams.some((p) => p.name === "per_page"));

    const positionals = ov.positionals ?? (pathParams.length > 0 && pathParams.length <= 4 ? pathParams : []);
    const example = ov.examples ?? [
      {
        title: summary.replace(/\.$/, ""),
        argv: [
          ...id.split("."),
          ...positionals.map((p) => sampleFor(p, params.find((x) => x.name === p)?.schema)),
          ...[...fields.entries()]
            .filter(([n, field]) => field.required && !positionals.includes(n) && field.where !== "path")
            .map(([n]) => `--${kebab(n)} ${JSON.stringify(sampleFor(n, null)).replace(/^"|"$/g, "")}`),
          "--json",
        ].join(" "),
      },
    ];

    commands.push({
      id,
      key,
      method: method.toUpperCase(),
      path,
      summary,
      description: ov.description ?? null,
      kind,
      idempotent,
      scopes,
      audience: admin ? ["admin"] : [secured ? "learner" : "any"],
      positionals,
      fields,
      pathParams,
      queryParams: queryParams.map((p) => p.name).filter((n) => !pathParams.includes(n)),
      bodyMode,
      bodyFields,
      wholeBody,
      files,
      download,
      paginated,
      longRunning: ov.longRunning ?? null,
      dryRun: ov.dryRun ?? null,
      examples: example,
      mcpExpose: ov.mcp?.expose,
      toolset: ov.mcp?.toolset ?? (admin ? noun : "my"),
    });
  }
  return { commands, warnings };
}

// ---------------------------------------------------------------- emit

export function emit(commands) {
  const lines = [
    "// GENERATED by scripts/gen-commands.mjs from spec/openapi.json and spec/overrides.yaml. Do not edit.",
    "/* eslint-disable */",
    'import { z } from "zod";',
    'import type { AnyCommand } from "../registry/types.ts";',
    'import { httpCommand } from "../registry/http-run.ts";',
    "",
    "export const generatedCommands: AnyCommand[] = [",
  ];
  for (const c of commands.sort((a, b) => a.id.localeCompare(b.id))) {
    const inputFields = [...c.fields.entries()].map(([name, f]) => `${JSON.stringify(name)}: ${f.zod}${f.required ? "" : ".optional()"}`);
    lines.push(
      `  httpCommand({`,
      `    id: ${JSON.stringify(c.id)},`,
      `    summary: ${q(c.summary)},`,
      ...(c.description ? [`    description: ${q(c.description)},`] : []),
      `    kind: ${JSON.stringify(c.kind)},`,
      `    idempotent: ${c.idempotent},`,
      `    scopes: ${JSON.stringify(c.scopes)},`,
      `    audience: ${JSON.stringify(c.audience)},`,
      `    endpoints: [${JSON.stringify(c.key)}],`,
      ...(c.positionals.length ? [`    positionals: ${JSON.stringify(c.positionals)},`] : []),
      ...(c.paginated ? ["    paginated: true,"] : []),
      ...(c.longRunning ? [`    longRunning: ${JSON.stringify(c.longRunning)},`] : []),
      ...(c.dryRun ? [`    dryRun: ${JSON.stringify(c.dryRun)},`] : []),
      `    mcp: { ${c.mcpExpose === false ? "expose: false, " : ""}toolset: ${JSON.stringify(c.toolset)} },`,
      `    examples: ${JSON.stringify(c.examples)},`,
      `    input: z.looseObject({ ${inputFields.join(", ")} }),`,
      `    request: ${JSON.stringify({
        method: c.method,
        path: c.path,
        pathParams: c.pathParams,
        queryParams: c.queryParams,
        bodyMode: c.bodyMode,
        bodyFields: c.bodyFields,
        ...(c.wholeBody ? { wholeBody: true } : {}),
        ...(Object.keys(c.files).length ? { files: c.files } : {}),
        ...(c.download ? { download: true } : {}),
      })},`,
      `  }),`
    );
  }
  lines.push("];", "");
  return lines.join("\n");
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const { commands, warnings } = buildCommands(loadInputs());
  mkdirSync(resolve(root, "src/generated"), { recursive: true });
  writeFileSync(resolve(root, "src/generated/commands.ts"), emit(commands));
  for (const w of warnings) console.warn(`warning: ${w}`);
  console.log(`generated ${commands.length} commands (${warnings.length} warnings)`);
}
void camelFree;
