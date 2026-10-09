import { parseArgs, type ParseArgsOptionsConfig } from "node:util";
import { parse as parseYaml } from "yaml";
import { CliError } from "../errors.ts";
import type { AnyCommand, FsPort, GlobalFlags } from "../registry/types.ts";
import { commandPath, kebab, primaryType, toJsonSchema, type JsonSchema } from "../registry/schema-export.ts";

export const GLOBAL_OPTIONS = {
  profile: { type: "string" },
  url: { type: "string" },
  "token-stdin": { type: "boolean" },
  json: { type: "boolean" },
  output: { type: "string" },
  fields: { type: "string" },
  input: { type: "string" },
  set: { type: "string", multiple: true },
  quiet: { type: "boolean" },
  "no-color": { type: "boolean" },
  "dry-run": { type: "boolean" },
  yes: { type: "boolean" },
  wait: { type: "boolean" },
  timeout: { type: "string" },
  "idempotency-key": { type: "string" },
  page: { type: "string" },
  "per-page": { type: "string" },
  all: { type: "boolean" },
  limit: { type: "string" },
  out: { type: "string" },
  debug: { type: "boolean" },
  interactive: { type: "boolean" },
  help: { type: "boolean", short: "h" },
  version: { type: "boolean", short: "v" },
} satisfies ParseArgsOptionsConfig;

export interface Resolved {
  command: AnyCommand | null;
  /** Words that matched no command: used for help and suggestions. */
  words: string[];
  /** argv without the command words. */
  rest: string[];
  help: boolean;
  version: boolean;
}

type Values = Record<string, string | boolean | string[] | boolean[] | undefined>;

/** Pass 1: find the command in the leading positional tokens (globals may come first). */
export function resolveCommand(argv: string[], commands: AnyCommand[]): Resolved {
  const { tokens } = parseArgs({ args: argv, options: GLOBAL_OPTIONS, strict: false, allowPositionals: true, tokens: true, allowNegative: true });
  const leading: Array<{ value: string; index: number }> = [];
  for (const t of tokens) {
    if (t.kind === "positional") leading.push({ value: t.value, index: t.index });
    else if (t.kind === "option") {
      const known = t.name in GLOBAL_OPTIONS;
      if (!known) break;
      // known global with value: its argument is not a positional (parseArgs already knows).
    } else break; // option-terminator
  }
  const byPath = new Map(commands.map((c) => [commandPath(c).join(" "), c]));
  let matched: AnyCommand | null = null;
  let used = 0;
  for (let n = Math.min(leading.length, 3); n >= 1; n--) {
    const candidate = byPath.get(leading.slice(0, n).map((l) => l.value).join(" "));
    if (candidate) {
      matched = candidate;
      used = n;
      break;
    }
  }
  const dropped = new Set(leading.slice(0, matched ? used : 0).map((l) => l.index));
  const rest = argv.filter((_, i) => !dropped.has(i));
  const flagsOnly = parseArgs({ args: argv, options: GLOBAL_OPTIONS, strict: false, allowPositionals: true, allowNegative: true }).values;
  return {
    command: matched,
    words: matched ? [] : leading.map((l) => l.value),
    rest: matched ? rest : argv,
    help: Boolean(flagsOnly.help),
    version: Boolean(flagsOnly.version),
  };
}

export function commandOptions(cmd: AnyCommand): { options: ParseArgsOptionsConfig; schema: JsonSchema; byFlag: Map<string, string> } {
  const schema = toJsonSchema(cmd.input, "input");
  const options: Record<string, { type: "string" | "boolean"; multiple?: boolean }> = { ...GLOBAL_OPTIONS };
  const byFlag = new Map<string, string>();
  for (const [name, prop] of Object.entries(schema.properties ?? {})) {
    const flag = kebab(name);
    byFlag.set(flag, name);
    const type = primaryType(prop);
    options[flag] = type === "boolean" ? { type: "boolean" } : type === "array" ? { type: "string", multiple: true } : { type: "string" };
    if (flag === "file") (options[flag] as { short?: string }).short = "f";
  }
  return { options: options as ParseArgsOptionsConfig, schema, byFlag };
}

export interface ParsedInvocation {
  flags: GlobalFlags;
  tokenStdin: boolean;
  input: Record<string, unknown>;
  rawValues: Values;
}

const OUTPUTS = new Set(["auto", "json", "ndjson", "yaml", "table", "text"]);

function toNumber(flag: string, value: string): number {
  const n = Number(value);
  if (!Number.isFinite(n)) throw new CliError("INPUT_INVALID", `--${flag} expects a number, got "${value}".`);
  return n;
}

export function buildFlags(values: Values, env: NodeJS.ProcessEnv): GlobalFlags {
  const str = (k: string) => (typeof values[k] === "string" ? (values[k] as string) : undefined);
  const output = str("output") ?? env.ULAMS_OUTPUT ?? "auto";
  if (!OUTPUTS.has(output)) throw new CliError("INPUT_INVALID", `--output must be one of ${[...OUTPUTS].join(", ")}.`);
  const fields = str("fields")?.split(",").map((f) => f.trim()).filter(Boolean);
  const num = (k: string) => (str(k) !== undefined ? toNumber(k, str(k) as string) : undefined);
  const flags: GlobalFlags = {
    json: Boolean(values.json),
    output: output as GlobalFlags["output"],
    quiet: Boolean(values.quiet),
    noColor: Boolean(values["no-color"]) || env.NO_COLOR !== undefined,
    dryRun: Boolean(values["dry-run"]),
    yes: Boolean(values.yes) || env.ULAMS_YES === "1",
    wait: values.wait === undefined ? true : Boolean(values.wait),
    timeout: num("timeout") ?? 600,
    all: Boolean(values.all),
    debug: Boolean(values.debug) || env.ULAMS_DEBUG === "1",
    interactive: Boolean(values.interactive),
    tokenStdin: Boolean(values["token-stdin"]),
  };
  const profile = str("profile");
  if (profile) flags.profile = profile;
  const url = str("url");
  if (url) flags.url = url;
  if (fields && fields.length) flags.fields = fields;
  const idem = str("idempotency-key");
  if (idem) flags.idempotencyKey = idem;
  const page = num("page");
  if (page !== undefined) flags.page = page;
  const perPage = num("per-page");
  if (perPage !== undefined) flags.perPage = perPage;
  const limit = num("limit");
  if (limit !== undefined) flags.limit = limit;
  const out = str("out");
  if (out) flags.out = out;
  return flags;
}

export function parseStructured(text: string, label: string): unknown {
  try {
    return parseYaml(text);
  } catch (error) {
    throw new CliError("INPUT_INVALID", `${label} is not valid JSON or YAML: ${(error as Error).message.split("\n")[0]}`);
  }
}

async function readStructured(source: string, fs: FsPort, readStdin: () => Promise<string>): Promise<unknown> {
  if (source === "-") return parseStructured(await readStdin(), "stdin");
  if (source.startsWith("@")) {
    const path = source.slice(1);
    let text: string;
    try {
      text = await fs.readText(path);
    } catch {
      throw new CliError("INPUT_INVALID", `Cannot read ${path}.`, { hint: "Check the path after @." });
    }
    return parseStructured(text, path);
  }
  try {
    return JSON.parse(source);
  } catch {
    throw new CliError("INPUT_INVALID", "--input must be JSON, @file.json, @file.yaml or - (stdin).");
  }
}

function setDeep(target: Record<string, unknown>, path: string, value: unknown): void {
  const keys = path.split(".");
  let node = target;
  keys.forEach((key, i) => {
    if (i === keys.length - 1) node[key] = value;
    else {
      const next = node[key];
      node = (next && typeof next === "object" ? next : (node[key] = {})) as Record<string, unknown>;
    }
  });
}

function guessScalar(value: string): unknown {
  if (value === "true") return true;
  if (value === "false") return false;
  if (value === "null") return null;
  if (/^-?\d+(\.\d+)?$/.test(value)) return Number(value);
  if (/^[[{]/.test(value)) {
    try {
      return JSON.parse(value);
    } catch {
      return value;
    }
  }
  return value;
}

/** Pass 2: parse the command's own flags and assemble the input object. */
export async function parseInvocation(
  cmd: AnyCommand,
  rest: string[],
  env: NodeJS.ProcessEnv,
  fs: FsPort,
  readStdin: () => Promise<string>
): Promise<ParsedInvocation & { positionals: string[] }> {
  const { options, schema, byFlag } = commandOptions(cmd);
  let parsed;
  try {
    parsed = parseArgs({ args: rest, options, strict: true, allowPositionals: true, allowNegative: true });
  } catch (error) {
    const message = (error as Error).message.split(". To specify")[0] ?? String(error);
    throw new CliError("USAGE", message, {
      hint: `Run \`ulams describe ${commandPath(cmd).join(" ")}\` for the accepted flags.`,
    });
  }
  const values = parsed.values as Values;
  // A flag the command defines itself (e.g. topics create-oembed --url) is never also a global flag.
  const globalValues: Values = { ...values };
  for (const owned of ["url", "profile", "output", "timeout", "idempotency-key", "yes", "wait", "json"]) {
    if (byFlag.has(owned)) delete globalValues[owned];
  }
  const flags = buildFlags(globalValues, env);

  const input: Record<string, unknown> = {};
  if (typeof values.input === "string") {
    const whole = await readStructured(values.input, fs, readStdin);
    if (!whole || typeof whole !== "object" || Array.isArray(whole)) {
      throw new CliError("INPUT_INVALID", "--input must contain an object.");
    }
    Object.assign(input, whole);
  }
  for (const entry of (values.set as string[] | undefined) ?? []) {
    const eq = entry.indexOf("=");
    if (eq < 1) throw new CliError("INPUT_INVALID", `--set expects path=value, got "${entry}".`);
    setDeep(input, entry.slice(0, eq), guessScalar(entry.slice(eq + 1)));
  }

  // Positionals fill the declared names in order; extras are an error.
  const names = cmd.positionals ?? [];
  const positionals = parsed.positionals;
  if (positionals.length > names.length) {
    throw new CliError("USAGE", `Unexpected argument "${positionals[names.length]}".`, {
      hint: `Usage: ulams ${commandPath(cmd).join(" ")}${names.map((n) => ` <${kebab(n)}>`).join("")} [flags]`,
    });
  }
  positionals.forEach((value, i) => {
    const name = names[i] as string;
    values[kebab(name)] ??= value;
  });

  for (const [flag, value] of Object.entries(values)) {
    const name = byFlag.get(flag);
    // --fields is always the output projection; an input field of that name goes through --input or --set.
    if (!name || value === undefined || flag === "fields") continue;
    input[name] = await coerce(flag, value, schema.properties?.[name], fs, readStdin);
  }
  return { flags, tokenStdin: Boolean(values["token-stdin"]), input, rawValues: values, positionals };
}

async function coerce(
  flag: string,
  value: string | boolean | string[] | boolean[],
  prop: JsonSchema | undefined,
  fs: FsPort,
  readStdin: () => Promise<string>
): Promise<unknown> {
  const type = primaryType(prop);
  if (typeof value === "boolean") return value;
  if (type === "array") {
    const items = value as string[];
    const itemType = primaryType(prop?.items);
    // Scopes are a comma list of names and @presets (@author is not a file): --scopes @author,tokens:write.
    if (flag === "scopes" && !(items.length === 1 && (items[0] as string).startsWith("["))) return items.flatMap((v) => v.split(",")).map((v) => v.trim()).filter(Boolean);
    // A single JSON array or @file replaces repeated flags.
    if (items.length === 1 && (items[0] as string).startsWith("[")) return readStructured(items[0] as string, fs, readStdin);
    if (items.length === 1 && (items[0] as string).startsWith("@")) return readStructured(items[0] as string, fs, readStdin);
    return items.map((v) => (itemType === "integer" || itemType === "number" ? toNumber(flag, v) : itemType === "object" ? guessScalar(v) : v));
  }
  const text = value as string;
  switch (type) {
    case "integer":
    case "number":
      return toNumber(flag, text);
    case "boolean":
      return text === "true" || text === "1";
    case "object":
      return readStructured(text, fs, readStdin);
    case "unknown":
      return text.startsWith("@") || text.startsWith("{") || text.startsWith("[") || text === "-" ? readStructured(text, fs, readStdin) : guessScalar(text);
    default:
      if (prop?.fileInput && text.startsWith("@") && !text.startsWith("@@")) {
        try {
          return await fs.readText(text.slice(1));
        } catch {
          throw new CliError("INPUT_INVALID", `Cannot read ${text.slice(1)}.`, { hint: `--${flag} @path reads a file; use @@ for a literal @.` });
        }
      }
      return text.startsWith("@@") && prop?.fileInput ? text.slice(1) : text;
  }
}
