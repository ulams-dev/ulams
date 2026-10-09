// Static extraction of facts from the PHP code of the API (no container needed, so the docs
// build runs in CI): routes, permissions, settings, events, templates, artisan commands,
// scheduled jobs and env() calls, grouped by package. Best effort by design: whatever a
// pattern cannot resolve is shown as written in the code rather than dropped.
import { isDir, list, read, walk } from "./lib.mjs";

/** Package directory names under api/packages. */
export const packageNames = () => list("api/packages").filter((p) => isDir(`api/packages/${p}`));

const phpFiles = (rel) => walk(rel, (p) => p.endsWith(".php") && !/\/tests?\//.test(p));

/** Owner of a file: the package name, or "app" for the Laravel application. */
export const ownerOf = (file) => file.match(/^api\/packages\/([^/]+)\//)?.[1] ?? "app";

/** All PHP source files of the API (packages and app), without tests. */
let _sources;
export function sources() {
  if (_sources) return _sources;
  const files = [
    ...phpFiles("api/app"),
    ...phpFiles("api/routes"),
    ...phpFiles("api/config"),
    ...packageNames().flatMap((p) => [
      ...phpFiles(`api/packages/${p}/src`),
      ...phpFiles(`api/packages/${p}/config`),
      ...phpFiles(`api/packages/${p}/database/seeders`),
    ]),
  ];
  _sources = files.map((file) => ({ file, owner: ownerOf(file), src: read(file) }));
  return _sources;
}

/** `const NAME = 'value';` declarations of a PHP class body. */
export function constants(src) {
  const out = {};
  for (const m of src.matchAll(/const\s+(\w+)\s*=\s*(['"])(.*?)\2\s*;/g)) out[m[1]] = m[3];
  return out;
}

const className = (src) => src.match(/\n\s*(?:final\s+|abstract\s+)?(?:class|enum|interface|trait)\s+(\w+)/)?.[1];
const namespaceOf = (src) => src.match(/namespace\s+([\w\\]+)\s*;/)?.[1] ?? "";

/** Map short class name → constants, for every enum-like class (used to resolve Enum::CONST). */
let _consts;
export function classConstants() {
  if (_consts) return _consts;
  _consts = {};
  for (const { src } of sources()) {
    const name = className(src);
    if (name && /const\s+\w+\s*=/.test(src)) _consts[name] = { ...(_consts[name] ?? {}), ...constants(src) };
  }
  return _consts;
}

const resolveConst = (expr, selfConsts = {}) => {
  const m = expr.trim().match(/^(?:\\?[\w\\]*\\)?(\w+)::(\w+)$/);
  if (!m) {
    const s = expr.trim().match(/^(['"])(.*)\1$/);
    return s ? s[2] : null;
  }
  const [, cls, c] = m;
  if (cls === "self" || cls === "static") return selfConsts[c] ?? null;
  return classConstants()[cls]?.[c] ?? null;
};

/** Resolves a PHP string expression made of constants, literals and `.` concatenation. */
export function resolveString(expr, selfConsts = {}) {
  const parts = expr.split(/\s*\.\s*(?=(?:[^'"]|'[^']*'|"[^"]*")*$)/);
  const out = parts.map((p) => resolveConst(p, selfConsts) ?? (/^\$\w+$/.test(p.trim()) ? `<${p.trim().slice(1)}>` : null));
  return out.every((x) => x !== null) ? out.join("") : null;
}

// ---------------------------------------------------------------------------------------
// Permissions

export function permissions() {
  const perms = []; // { name, owner, enumClass, constName, roles: Set }
  const byName = new Map();
  for (const { file, owner, src } of sources()) {
    if (!/\/src\/Enums?\/.*Permission.*\.php$/.test(file)) continue;
    const cls = className(src);
    for (const [c, v] of Object.entries(constants(src))) {
      if (!byName.has(v)) {
        const p = { name: v, owner, enumClass: cls, constName: c, roles: new Set(), file };
        byName.set(v, p);
        perms.push(p);
      }
    }
  }
  const ROLE_CONSTS = classConstants().UserRole ?? {};
  const enumSrc = new Map(sources().map(({ src }) => [className(src), src]));
  const roleName = (raw) => {
    const r = raw.trim();
    const c = r.match(/(\w+)::(\w+)/);
    if (c) return (c[1] === "UserRole" ? ROLE_CONSTS[c[2]] : classConstants()[c[1]]?.[c[2]]) ?? null;
    return r.match(/^(['"])([\w-]+)\1$/)?.[2] ?? null;
  };
  // Values of an expression: an array literal, Enum::CONST, 'literal', Enum::getValues(),
  // Enum::asArray(), Enum::someList() (a static method returning an array) or $variable.
  const values = (expr, src, depth = 0) => {
    if (depth > 5) return [];
    const e = expr.trim();
    const all = e.match(/^(\w+)::(?:getValues|asArray|values|toArray)\(\)$/);
    if (all) return Object.values(classConstants()[all[1]] ?? {});
    const method = e.match(/^(\w+)::(\w+)\(\)$/);
    if (method) {
      const owner = enumSrc.get(method[1]) ?? "";
      const start = owner.search(new RegExp(`function\\s+${method[2]}\\s*\\(`));
      if (start < 0) return [];
      const ret = owner.slice(start).match(/return\s+([\s\S]*?);/)?.[1] ?? "";
      return values(ret.replace(/\b(self|static)::/g, `${method[1]}::`), owner, depth + 1);
    }
    const variable = e.match(/^\$(\w+)$/);
    if (variable) {
      const assigns = [...src.matchAll(new RegExp(`\\$${variable[1]}\\s*=\\s*([\\s\\S]*?);`, "g"))];
      return assigns.length ? values(assigns[assigns.length - 1][1], src, depth + 1) : [];
    }
    if (e.startsWith("[")) {
      return e
        .slice(1, e.lastIndexOf("]"))
        .split(",")
        .map((part) => part.trim().replace(/^\.\.\./, ""))
        .filter(Boolean)
        .flatMap((t) => values(t, src, depth + 1));
    }
    const c = e.match(/^(\w+)::(\w+)$/);
    if (c) return [classConstants()[c[1]]?.[c[2]]].filter(Boolean);
    const lit = e.match(/^(['"])(.*)\1$/);
    return lit ? [lit[2]] : [];
  };
  for (const { file, src } of sources()) {
    if (!/database\/seeders\//.test(file) || !/givePermissionTo|syncPermissions/.test(src)) continue;
    const roleVars = {};
    for (const m of src.matchAll(/\$(\w+)\s*=\s*Role::(?:findOrCreate|findByName|firstOrCreate|create)\(\s*([^,)]+)/g)) {
      const r = roleName(m[2]);
      if (r) roleVars[m[1]] = [r];
    }
    for (const m of src.matchAll(/foreach\s*\(\s*(\[[^\]]*\])\s+as\s+\$(\w+)\s*\)/g)) {
      roleVars[m[2]] = m[1].slice(1, -1).split(",").map(roleName).filter(Boolean);
    }
    for (const m of src.matchAll(/(?:\$(\w+)|Role::\w+\(\s*\$(\w+)[^)]*\))->(?:givePermissionTo|syncPermissions)\(([\s\S]*?)\);/g)) {
      const roles = roleVars[m[1] ?? m[2]];
      if (!roles) continue;
      for (const v of values(m[3], src)) {
        const p = byName.get(v);
        if (p) for (const r of roles) p.roles.add(r);
      }
    }
  }
  return perms;
}

// ---------------------------------------------------------------------------------------
// Administrable settings (AdministrableConfig::registerConfig)

export function settings() {
  const out = [];
  for (const { file, owner, src } of sources()) {
    if (!src.includes("registerConfig(") || /Contracts|Facades\/AdministrableConfig|Services\/AdministrableConfigService/.test(file)) continue;
    const selfConsts = constants(src);
    for (const m of src.matchAll(/registerConfig\(\s*([^,]+?)\s*,\s*(\[[^\]]*\])?\s*(?:,\s*(true|false))?\s*(?:,\s*(true|false))?\s*\)/g)) {
      const key = resolveString(m[1], selfConsts) ?? m[1].trim();
      out.push({
        key,
        owner,
        file,
        rules: (m[2] ?? "").replace(/[\[\]'"]/g, "").replace(/\s+/g, " ").trim(),
        public: m[3] !== "false",
        readonly: m[4] === "true",
      });
    }
  }
  return out;
}

// ---------------------------------------------------------------------------------------
// Events and notification templates

const docSummary = (src) => {
  const m = src.match(/\/\*\*([\s\S]*?)\*\/\s*(?:#\[[^\]]*\]\s*)*(?:final\s+|abstract\s+)?class\s/);
  if (!m) return "";
  return m[1]
    .split("\n")
    .map((l) => l.replace(/^\s*\*\s?/, "").trim())
    .filter((l) => l && !l.startsWith("@"))
    .join(" ");
};

export function events() {
  const out = [];
  for (const { file, owner, src } of sources()) {
    if (!/\/src\/Events\/[^/]+\.php$/.test(file)) continue;
    const name = className(src);
    if (!name || /abstract\s+class|interface\s|trait\s/.test(src)) continue;
    out.push({ name, owner, file, fqcn: `${namespaceOf(src)}\\${name}`, summary: docSummary(src) });
  }
  return out;
}

export function templateRegistrations() {
  const out = [];
  for (const { file, owner, src } of sources()) {
    if (!src.includes("Template::register(")) continue;
    for (const m of src.matchAll(/Template::register\(\s*([\w\\]+)::class\s*,\s*([\w\\]+)::class\s*(?:,\s*([\w\\]+)::class)?/g)) {
      const short = (s) => s.split("\\").pop();
      out.push({ event: short(m[1]), channel: short(m[2]).replace(/Channel$/, ""), variables: m[3] ? short(m[3]) : "", owner, file });
    }
  }
  return out;
}

// ---------------------------------------------------------------------------------------
// Artisan commands and scheduled jobs

export function commands() {
  const out = [];
  for (const { file, owner, src } of sources()) {
    const sig = src.match(/protected\s+\$signature\s*=\s*(['"])([\s\S]*?)\1\s*;/);
    const nameProp = src.match(/protected\s+\$name\s*=\s*(['"])(.*?)\1\s*;/);
    const attr = src.match(/#\[AsCommand\(\s*name:\s*(['"])(.*?)\1(?:\s*,\s*description:\s*(['"])(.*?)\3)?/);
    if (!sig && !nameProp && !attr) continue;
    if (!/extends\s+\w*Command\b/.test(src)) continue;
    const signature = (sig?.[2] ?? nameProp?.[2] ?? attr?.[2] ?? "").replace(/\s+/g, " ").trim();
    const name = signature.split(" ")[0];
    const desc =
      src.match(/protected\s+\$description\s*=\s*(['"])([\s\S]*?)\1\s*;/)?.[2] ?? attr?.[4] ?? "";
    out.push({ name, signature, description: desc.replace(/\s+/g, " ").trim(), owner, file });
  }
  return out.sort((a, b) => a.name.localeCompare(b.name));
}

export function schedules() {
  const out = [];
  for (const { file, owner, src } of sources()) {
    if (!/\$schedule->|Schedule::/.test(src)) continue;
    for (const m of src.matchAll(/(?:\$schedule->|Schedule::)(job|command|call|exec)\(([\s\S]*?)\)((?:\s*->\s*\w+\([^;]*?\))*)\s*;/g)) {
      const target = m[2].replace(/\s+/g, " ").trim();
      const chain = m[3]
        .replace(/\s*->\s*/g, "->")
        .replace(/\s+/g, " ")
        .replace(/^->/, "")
        .split("->")
        .filter(Boolean);
      out.push({ kind: m[1], target, chain, owner, file });
    }
  }
  return out;
}

// ---------------------------------------------------------------------------------------
// Routes

const RESOURCE_ACTIONS = [
  ["GET", "", "index"],
  ["GET", "/create", "create"],
  ["POST", "", "store"],
  ["GET", "/{id}", "show"],
  ["GET", "/{id}/edit", "edit"],
  ["PUT|PATCH", "/{id}", "update"],
  ["DELETE", "/{id}", "destroy"],
];

const joinPath = (...parts) =>
  `/${parts
    .map((p) => String(p ?? "").replace(/^\/+|\/+$/g, ""))
    .filter(Boolean)
    .join("/")}`;

/** Strings in a PHP expression: 'a' or "a". */
const firstString = (s) => s.match(/(['"])(.*?)\1/)?.[2];

/**
 * Routes of one PHP route file. Tracks nested `Route::group([... 'prefix' => ...], function)`
 * and `Route::prefix(...)->group(function)` by brace depth.
 */
export function parseRoutes(src, basePrefix = "") {
  const routes = [];
  const stack = [{ depth: -1, prefix: basePrefix, auth: false }];
  let depth = 0;
  let stmtStart = 0;
  const pending = []; // prefixes waiting for the next "{" of a group closure
  const top = () => stack[stack.length - 1];
  for (let i = 0; i < src.length; i++) {
    const ch = src[i];
    if (ch === "'" || ch === '"') {
      const end = src.indexOf(ch, i + 1);
      i = end < 0 ? src.length : end;
      continue;
    }
    if (ch === "/" && src[i + 1] === "/") {
      i = src.indexOf("\n", i);
      if (i < 0) break;
      continue;
    }
    if (ch === "/" && src[i + 1] === "*") {
      i = src.indexOf("*/", i) + 1;
      continue;
    }
    if (ch === "{") {
      const head = src.slice(stmtStart, i);
      const closure = !pending.length && head.match(/Route::(get|post|put|patch|delete|any)\(\s*(['"])(.*?)\2\s*,\s*(?:static\s+)?function\b/);
      if (closure) {
        routes.push({ method: closure[1].toUpperCase(), path: joinPath(top().prefix, closure[3]), action: "closure", auth: top().auth });
      }
      depth++;
      if (pending.length) {
        const p = pending.shift();
        stack.push({ depth, prefix: joinPath(top().prefix, p.prefix), auth: top().auth || p.auth });
      }
      stmtStart = i + 1;
      continue;
    }
    if (ch === "}") {
      if (top().depth === depth) stack.pop();
      depth--;
      stmtStart = i + 1;
      continue;
    }
    if (ch === ";") {
      const stmt = src.slice(stmtStart, i + 1);
      stmtStart = i + 1;
      if (/->group\(|Route::group\(/.test(stmt)) continue;
      const verb = stmt.match(/Route::(get|post|put|patch|delete|options|any|match|resource|apiResource)\(([\s\S]*)\)\s*(?:->[\s\S]*)?;$/);
      if (!verb) continue;
      const args = verb[2];
      const chainPrefix = [...stmt.matchAll(/->prefix\(\s*(['"])(.*?)\1/g)].map((m) => m[2]);
      const base = joinPath(top().prefix, ...chainPrefix);
      const auth = top().auth || /auth:api|'auth'/.test(stmt);
      const action = (() => {
        const arr = args.match(/\[\s*([\w\\]+)::class\s*,\s*(['"])(\w+)\2\s*\]/);
        if (arr) return `${arr[1].split("\\").pop()}@${arr[3]}`;
        const inv = args.match(/([\w\\]+)::class/);
        if (inv) return inv[1].split("\\").pop();
        return /function\s*\(|fn\s*\(/.test(args) ? "closure" : "";
      })();
      if (verb[1] === "resource" || verb[1] === "apiResource") {
        const name = firstString(args) ?? "";
        const ctrl = args.match(/([\w\\]+)::class/)?.[1]?.split("\\").pop() ?? "";
        const only = args.match(/->only\(\s*\[([^\]]*)\]/)?.[1] ?? stmt.match(/->only\(\s*\[([^\]]*)\]/)?.[1];
        const except = stmt.match(/->except\(\s*\[([^\]]*)\]/)?.[1];
        for (const [method, suffix, act] of RESOURCE_ACTIONS) {
          if (verb[1] === "apiResource" && (act === "create" || act === "edit")) continue;
          if (only && !only.includes(act)) continue;
          if (except && except.includes(act)) continue;
          routes.push({ method, path: joinPath(base, name) + suffix, action: `${ctrl}@${act}`, auth });
        }
        continue;
      }
      let method = verb[1].toUpperCase();
      let rest = args;
      if (verb[1] === "match") {
        const methods = args.match(/^\s*\[([^\]]*)\]/)?.[1] ?? "";
        method = methods.replace(/['"\s]/g, "").toUpperCase().replace(/,/g, "|");
        rest = args.slice(args.indexOf("]") + 1);
      }
      const pathArg = rest.trim().startsWith("null") ? "" : (firstString(rest.split(/,\s*[\[\w\\]/)[0]) ?? "");
      routes.push({ method, path: joinPath(base, pathArg), action, auth });
      continue;
    }
    // A group opening: remember its prefix until the closure's "{".
    if (src.startsWith("group(", i) && (src.slice(i - 2, i) === "->" || src.slice(i - 7, i) === "Route::")) {
      const head = src.slice(stmtStart, i);
      const argsHead = src.slice(i, src.indexOf("function", i) > 0 ? src.indexOf("function", i) : i + 200);
      const prefixes = [
        ...[...head.matchAll(/->prefix\(\s*(['"])(.*?)\1/g)].map((m) => m[2]),
        ...[...head.matchAll(/Route::prefix\(\s*(['"])(.*?)\1/g)].map((m) => m[2]),
        ...[...argsHead.matchAll(/['"]prefix['"]\s*=>\s*(['"])(.*?)\1/g)].map((m) => m[2]),
      ];
      const auth = /auth:api|'auth'/.test(head + argsHead);
      pending.push({ prefix: joinPath(...prefixes), auth });
      i += 5;
    }
  }
  return routes;
}

export function routes() {
  const out = [];
  for (const p of packageNames()) {
    for (const file of walk(`api/packages/${p}/src`, (f) => f.endsWith(".php"))) {
      const src = read(file);
      if (!/Route::(get|post|put|patch|delete|any|match|group|prefix|resource|apiResource|middleware)\(/.test(src)) continue;
      for (const r of parseRoutes(src)) out.push({ ...r, owner: p, file });
    }
  }
  for (const [file, prefix] of [
    ["api/routes/api.php", "api"],
    ["api/routes/web.php", ""],
  ]) {
    for (const r of parseRoutes(read(file), prefix)) out.push({ ...r, owner: "app", file });
  }
  return out;
}

// ---------------------------------------------------------------------------------------
// Environment variables read with env()

export function envCalls() {
  const out = new Map();
  for (const { file, owner, src } of sources()) {
    for (const m of src.matchAll(/\benv\(\s*(['"])([A-Z0-9_]+)\1\s*(?:,\s*([^)]*?))?\)/g)) {
      const name = m[2];
      const entry = out.get(name) ?? { name, defaults: new Set(), files: new Set(), owners: new Set() };
      if (m[3] !== undefined && m[3].trim() !== "") entry.defaults.add(m[3].trim().slice(0, 60));
      entry.files.add(file);
      entry.owners.add(owner);
      out.set(name, entry);
    }
  }
  return [...out.values()].sort((a, b) => a.name.localeCompare(b.name));
}
