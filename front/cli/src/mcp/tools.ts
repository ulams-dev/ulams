import { z } from "zod";
import { CliError, fromApiError, toErrorObject } from "../errors.ts";
import { redact } from "../config/redact.ts";
import { effectiveKind, execute, planFor, validateInput } from "../registry/executor.ts";
import { exportCommand, mcpName } from "../registry/schema-export.ts";
import type { AnyCommand, Ctx, GlobalFlags } from "../registry/types.ts";
import { errorEnvelope, successEnvelope } from "../output/envelope.ts";
import { project } from "../output/render.ts";
import { ConfirmTokens } from "./confirm.ts";

export const MAX_TEXT = 25_000;

export interface McpToolOptions {
  toolsets: string[] | "all";
  readOnly: boolean;
  noDestructive: boolean;
  /** Skip confirmation of destructive tools (sandboxes only). */
  yes: boolean;
}

export const CORE_TOOLS = [
  "whoami",
  "courses.list",
  "courses.get",
  "courses.create",
  "courses.update",
  "courses.publish",
  "lessons.list",
  "lessons.get",
  "lessons.create",
  "lessons.update",
  "topics.create-richtext",
  "topics.create-quiz",
  "users.list",
  "users.get",
  "users.create",
  "access.grant",
  "reports.metrics",
];

export const MCP_EXCLUDED_IDS = new Set(["login", "logout", "mcp", "completion", "version", "api", "schema", "describe", "profiles.list", "profiles.use", "profiles.delete", "config.get", "config.set", "apply"]);

export function exposable(cmd: AnyCommand): boolean {
  return cmd.mcp?.expose !== false && !MCP_EXCLUDED_IDS.has(cmd.id) && cmd.kind !== "local" && cmd.kind !== "stream";
}

export function visibleCommands(commands: AnyCommand[], o: McpToolOptions): AnyCommand[] {
  return commands.filter((c) => {
    if (!exposable(c)) return false;
    if (o.readOnly && c.kind !== "read") return false;
    if (o.noDestructive && c.kind === "destructive") return false;
    return true;
  });
}

export function selectTools(commands: AnyCommand[], o: McpToolOptions): AnyCommand[] {
  const visible = visibleCommands(commands, o);
  if (o.toolsets === "all") return visible;
  const sets = new Set(o.toolsets);
  return visible.filter((c) => (sets.has("core") && CORE_TOOLS.includes(c.id)) || sets.has(c.mcp?.toolset ?? c.id.split(".")[0] ?? ""));
}

export const EXTRA_INPUT = {
  dry_run: z.boolean().optional().describe("Show the plan and change nothing."),
  confirm: z.string().optional().describe("Confirm token from a CONFIRMATION_REQUIRED result of the same call (destructive tools)."),
  fields: z.array(z.string()).optional().describe("Return only these fields (dotted paths) of each result item."),
};

export function toolInput(cmd: AnyCommand): z.ZodObject {
  const extra: Record<string, z.ZodType> = { ...EXTRA_INPUT };
  if (cmd.paginated) {
    extra.all = z.boolean().optional().describe("Fetch every page.");
    extra.limit = z.number().int().optional().describe("With all: stop after this many items.");
  }
  if (cmd.kind === "read") delete extra.confirm;
  return cmd.input.extend(extra);
}

export function toolDescription(cmd: AnyCommand): string {
  const parts = [cmd.summary.replace(/\.?$/, ".")];
  if (cmd.description) parts.push(cmd.description);
  const ex = cmd.examples[0];
  if (ex) parts.push(`Example: ulams ${ex.argv}`);
  return parts.join(" ");
}

export function annotations(cmd: AnyCommand) {
  return {
    readOnlyHint: cmd.kind === "read",
    destructiveHint: cmd.kind === "destructive",
    idempotentHint: cmd.idempotent,
    openWorldHint: false,
  };
}

export interface ToolResult {
  [key: string]: unknown;
  content: Array<{ type: "text"; text: string }>;
  structuredContent: Record<string, unknown>;
  isError?: boolean;
}

function pack(envelope: unknown, isError: boolean): ToolResult {
  let text = JSON.stringify(envelope);
  let structured = envelope as Record<string, unknown>;
  if (text.length > MAX_TEXT && !isError) {
    const env = envelope as { data?: unknown; warnings?: unknown[] };
    const items = Array.isArray(env.data) ? env.data : null;
    let data = env.data;
    if (items) {
      let keep = items.length;
      while (keep > 1 && JSON.stringify({ ...env, data: items.slice(0, keep) }).length > MAX_TEXT) keep = Math.floor(keep / 2);
      data = items.slice(0, keep);
    } else data = { truncated: true, preview: JSON.stringify(env.data).slice(0, MAX_TEXT / 2) };
    structured = {
      ...(envelope as object),
      data,
      warnings: [...(env.warnings ?? []), { code: "TRUNCATED", message: "The result was too large.", hint: "Use page/per_page or fields to narrow it." }],
    };
    text = JSON.stringify(structured);
  }
  return { content: [{ type: "text", text }], structuredContent: structured, ...(isError ? { isError: true } : {}) };
}

export interface RunOutcome {
  result: ToolResult;
}

/** Runs one registry command for MCP: validation, dry-run, confirmation, envelope mapping. */
export async function runTool(
  cmd: AnyCommand,
  args: Record<string, unknown>,
  ctxBase: Ctx,
  tokens: ConfirmTokens,
  o: McpToolOptions
): Promise<ToolResult> {
  const secrets = ctxBase.profile.token ? [ctxBase.profile.token] : [];
  try {
    const { dry_run, confirm, fields, all, limit, ...input } = args as Record<string, unknown> & {
      dry_run?: boolean;
      confirm?: string;
      fields?: string[];
      all?: boolean;
      limit?: number;
    };
    const flags: GlobalFlags = {
      ...ctxBase.flags,
      dryRun: Boolean(dry_run),
      yes: false,
      all: Boolean(all),
      ...(limit !== undefined ? { limit } : {}),
      ...(fields ? { fields } : {}),
    };
    const ctx: Ctx = { ...ctxBase, flags };
    if (o.readOnly && cmd.kind !== "read") throw new CliError("FORBIDDEN", `${cmd.id} is not available: this server runs read-only.`, { hint: "Restart ulams mcp without --read-only." });
    const clean = validateInput(cmd, input);
    const kind = effectiveKind(cmd, clean);
    let confirmed = o.yes;
    if (kind === "destructive" && !dry_run && !o.yes) {
      if (confirm && tokens.consume(confirm, cmd.id, clean)) confirmed = true;
      else {
        const plan = await planFor(cmd, ctx, clean).catch(() => ({ note: "plan unavailable" }));
        throw new CliError("CONFIRMATION_REQUIRED", confirm ? "The confirm token is invalid, expired or already used." : `${cmd.id} is destructive.`, {
          hint: "Show the plan to the user, then call again with the same input and confirm set to details.confirm.",
          details: { plan, confirm: tokens.issue(cmd.id, clean) },
        });
      }
    }
    const result = await execute(cmd, clean, ctx, { confirmed });
    const env = successEnvelope(cmd.id, { ...result, data: project(result.data, fields) });
    return pack(redactEnvelope(env, cmd, secrets), false);
  } catch (error) {
    const cli = error instanceof CliError ? error : fromApiError(error);
    const env = errorEnvelope(cmd.id, cli);
    // The confirm token is meant for the model; everything else is redacted.
    const confirm = cli.code === "CONFIRMATION_REQUIRED" ? (cli.details as { confirm?: string }).confirm : undefined;
    const safe = redact(env, secrets) as typeof env;
    if (confirm) (safe.error.details as Record<string, unknown>).confirm = confirm;
    return pack(safe, true);
  }
}

function redactEnvelope<T>(env: T, cmd: AnyCommand, secrets: string[]): T {
  // Data may legitimately contain fields named "token" (e.g. token creation); only strip real secrets.
  void cmd;
  return JSON.parse(JSON.stringify(env, (_k, v) => (typeof v === "string" ? secrets.reduce((s, x) => (x.length >= 6 ? s.split(x).join("«redacted»") : s), v) : v))) as T;
}

export function searchCommands(commands: AnyCommand[], query: string, limit = 15) {
  const words = query.toLowerCase().split(/\s+/).filter(Boolean);
  return commands
    .map((c) => {
      const hay = `${c.id} ${c.summary} ${c.description ?? ""} ${c.endpoints.join(" ")}`.toLowerCase();
      const score = words.reduce((s, w) => s + (c.id.toLowerCase().includes(w) ? 3 : 0) + (hay.includes(w) ? 1 : 0), 0);
      return { c, score };
    })
    .filter((x) => x.score > 0)
    .sort((a, b) => b.score - a.score || a.c.id.localeCompare(b.c.id))
    .slice(0, limit)
    .map(({ c }) => ({ id: c.id, summary: c.summary, kind: c.kind, tool: mcpName(c) }));
}

export function describeCommand(cmd: AnyCommand) {
  const info = exportCommand(cmd);
  return { id: info.id, summary: info.summary, description: info.description, kind: info.kind, input: info.input, examples: info.examples };
}

export { toErrorObject, mcpName };
