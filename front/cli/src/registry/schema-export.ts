import { z } from "zod";
import { CONTRACT, type AnyCommand } from "./types.ts";
import { EXIT_CODES, DEFAULT_HINTS } from "../errors.ts";
import { ENVELOPE_SCHEMA } from "../output/envelope.ts";

export type JsonSchema = Record<string, unknown> & {
  type?: string | string[];
  properties?: Record<string, JsonSchema>;
  required?: string[];
  items?: JsonSchema;
  enum?: unknown[];
  anyOf?: JsonSchema[];
  oneOf?: JsonSchema[];
  description?: string;
  default?: unknown;
  fileInput?: boolean;
};

export function toJsonSchema(schema: z.ZodType, io: "input" | "output" = "input"): JsonSchema {
  return z.toJSONSchema(schema, { io, unrepresentable: "any", target: "draft-2020-12" }) as JsonSchema;
}

export const kebab = (name: string): string =>
  name
    .replace(/([a-z0-9])([A-Z])/g, "$1-$2")
    .replace(/[_.\s]+/g, "-")
    .toLowerCase();

export function commandPath(cmd: Pick<AnyCommand, "id">): string[] {
  return cmd.id.split(".");
}

export function cliPath(cmd: Pick<AnyCommand, "id">): string {
  return commandPath(cmd).join(" ");
}

export function mcpName(cmd: Pick<AnyCommand, "id">): string {
  return cmd.id.replace(/[.-]/g, "_");
}

export function exportCommand(cmd: AnyCommand) {
  const input = toJsonSchema(cmd.input, "input");
  const flags = Object.entries(input.properties ?? {}).map(([name, prop]) => ({
    name,
    flag: `--${kebab(name)}`,
    type: primaryType(prop),
    required: (input.required ?? []).includes(name),
    description: prop.description ?? "",
    positional: (cmd.positionals ?? []).includes(name),
  }));
  return {
    id: cmd.id,
    path: cliPath(cmd),
    summary: cmd.summary,
    description: cmd.description ?? null,
    kind: cmd.kind,
    idempotent: cmd.idempotent,
    scopes: cmd.scopes,
    audience: cmd.audience,
    endpoints: cmd.endpoints,
    paginated: Boolean(cmd.paginated),
    longRunning: cmd.longRunning ?? null,
    dryRun: cmd.dryRun ?? (cmd.kind === "write" || cmd.kind === "destructive" ? "client" : "none"),
    stability: cmd.stability,
    since: cmd.since,
    positionals: cmd.positionals ?? [],
    flags,
    input,
    output: toJsonSchema(cmd.output, "output"),
    examples: cmd.examples,
    mcp: {
      tool: mcpName(cmd),
      expose: cmd.mcp?.expose !== false,
      toolset: cmd.mcp?.toolset ?? cmd.id.split(".")[0],
      annotations: {
        readOnlyHint: cmd.kind === "read",
        destructiveHint: cmd.kind === "destructive",
        idempotentHint: cmd.idempotent,
        openWorldHint: false,
      },
    },
  };
}

export const GLOBAL_FLAG_DOCS = [
  { flag: "--profile <name>", env: "ULAMS_PROFILE", description: "Profile to use." },
  { flag: "--url <origin>", env: "ULAMS_URL", description: "Instance origin; overrides the profile." },
  { flag: "--token-stdin", env: "ULAMS_TOKEN", description: "Read the token from stdin (there is no --token flag)." },
  { flag: "--json", env: "", description: "Shorthand for --output json." },
  { flag: "--output json|ndjson|yaml|table|text", env: "ULAMS_OUTPUT", description: "Default auto: table on a TTY, json when piped." },
  { flag: "--fields a,b.c", env: "", description: "Project data (each item of a list) to these paths." },
  { flag: "--input <json|@file|->", env: "", description: "Whole input object; explicit flags override it." },
  { flag: "--set path=value", env: "", description: "Set one nested input value (repeatable)." },
  { flag: "--quiet", env: "", description: "No stderr progress." },
  { flag: "--no-color", env: "NO_COLOR", description: "Disable colors." },
  { flag: "--dry-run", env: "", description: "Show the plan; make no changes." },
  { flag: "--yes", env: "ULAMS_YES=1", description: "Confirm destructive commands non-interactively." },
  { flag: "--wait | --no-wait, --timeout <s>", env: "", description: "Long-running operations; default wait, 600 s." },
  { flag: "--idempotency-key <key>", env: "", description: "Sent as Idempotency-Key." },
  { flag: "--page, --per-page, --all, --limit <n>", env: "", description: "Pagination of list commands." },
  { flag: "--out <path>", env: "", description: "Write a binary download to this path (- for stdout)." },
  { flag: "--debug", env: "ULAMS_DEBUG=1", description: "Redacted request log on stderr." },
  { flag: "--interactive", env: "", description: "Allow prompts." },
];

export function exportRegistry(commands: AnyCommand[], version: string) {
  const sorted = [...commands].sort((a, b) => a.id.localeCompare(b.id));
  return {
    contract: CONTRACT,
    cliVersion: version,
    globals: GLOBAL_FLAG_DOCS,
    envelope: ENVELOPE_SCHEMA,
    exitCodes: Object.entries(EXIT_CODES).map(([code, exit]) => ({ code, exit, hint: DEFAULT_HINTS[code as keyof typeof DEFAULT_HINTS] })),
    commands: sorted.map(exportCommand),
  };
}

export function primaryType(prop: JsonSchema | undefined): string {
  if (!prop) return "unknown";
  if (typeof prop.type === "string") return prop.type;
  if (Array.isArray(prop.type)) return prop.type.find((t) => t !== "null") ?? "unknown";
  if (prop.enum) return "string";
  const variants = (prop.anyOf ?? prop.oneOf ?? []).filter((v) => v.type !== "null");
  if (variants.length === 1) return primaryType(variants[0]);
  if (variants.length > 1) return "unknown";
  return "unknown";
}
