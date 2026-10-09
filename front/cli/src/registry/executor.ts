import { CliError } from "../errors.ts";
import { fillPath, buildRequest } from "./request.ts";
import { diffFields } from "./diff.ts";
import type { AnyCommand, Ctx, Kind, PageMeta, Plan, Result } from "./types.ts";
import { commandPath } from "./schema-export.ts";

export interface ExecOptions {
  /** The caller has already confirmed a destructive command (MCP confirm token, --yes). */
  confirmed?: boolean;
  /** Prompt on a TTY: resolves true when the user confirmed. */
  prompt?: (question: string) => Promise<boolean>;
}

export function effectiveKind(cmd: AnyCommand, input: Record<string, unknown>): Kind {
  return cmd.kindFor ? cmd.kindFor(input) : cmd.kind;
}

export function validateInput(cmd: AnyCommand, raw: unknown): Record<string, unknown> {
  const parsed = cmd.input.safeParse(raw ?? {});
  if (!parsed.success) {
    const issues = parsed.error.issues.map((issue) => ({ path: issue.path.join("."), message: issue.message }));
    const first = issues[0];
    const hint = first?.path
      ? `Pass --${first.path.replace(/([A-Z_])/g, (m) => `-${m.replace("_", "").toLowerCase()}`)} or --input @file.json. Run \`ulams describe ${commandPath(cmd).join(" ")}\`.`
      : undefined;
    throw new CliError("INPUT_INVALID", `Invalid input for ${cmd.id}: ${issues.map((i) => `${i.path || "(input)"}: ${i.message}`).join("; ")}`, {
      details: { issues },
      ...(hint ? { hint } : {}),
    });
  }
  return parsed.data as Record<string, unknown>;
}

export async function planFor(cmd: AnyCommand, ctx: Ctx, input: Record<string, unknown>): Promise<Plan> {
  if (cmd.plan) return cmd.plan(ctx, input);
  if (cmd.request) {
    const req = buildRequest(cmd.request, input);
    const path = fillPath(req.path, req.params);
    const plan: Plan = {
      request: {
        method: req.method,
        path,
        ...(Object.keys(req.query).length ? { query: req.query } : {}),
        ...(req.body !== undefined ? { body: req.body } : {}),
        ...(req.form ? { body: req.form, files: Object.keys(req.files ?? {}) } : {}),
      },
    };
    const itemPath = cmd.request.pathParams.length > 0 && req.path.endsWith("}");
    const body = (req.body ?? req.form) as Record<string, unknown> | undefined;
    if (itemPath && req.method !== "GET" && (req.method !== "POST" || cmd.id.endsWith(".update"))) {
      // Update or delete of one item: show what exists now (best effort: the same path with GET).
      try {
        const current = (await ctx.client.call<Record<string, unknown>>("GET", path, { idempotent: true, signal: ctx.signal })).data;
        if (req.method === "DELETE") plan.current = current;
        else if (body && typeof body === "object") plan.changes = diffFields(current, body);
      } catch {
        plan.note = "The current resource could not be fetched, so no diff is shown.";
      }
    } else if (body && typeof body === "object" && req.method === "POST") {
      plan.changes = diffFields(null, body);
    }
    return plan;
  }
  return { note: "This command has no client-side plan." };
}

export async function execute(cmd: AnyCommand, raw: unknown, ctx: Ctx, options: ExecOptions = {}): Promise<Result> {
  const input = validateInput(cmd, raw);
  const kind = effectiveKind(cmd, input);
  const mutating = kind === "write" || kind === "destructive";

  if (ctx.flags.dryRun && mutating && cmd.dryRun !== "none") {
    const plan = await planFor(cmd, ctx, input);
    return { data: { dryRun: true, ...plan } };
  }

  if (kind === "destructive" && !options.confirmed && !ctx.flags.yes) {
    const plan = await planFor(cmd, ctx, input).catch((): Plan => ({ note: "plan unavailable" }));
    if (ctx.io.interactive && options.prompt) {
      const ok = await options.prompt(`This will run ${cmd.id}. ${JSON.stringify(plan.request ?? plan).slice(0, 300)}\nContinue?`);
      if (!ok) throw new CliError("CONFIRMATION_REQUIRED", "Cancelled by the user.", { details: { plan } });
    } else {
      throw new CliError("CONFIRMATION_REQUIRED", `${cmd.id} is destructive and needs confirmation.`, { details: { plan } });
    }
  }

  const flags = ctx.flags;
  if (cmd.paginated) {
    const shape = Object.keys((cmd.input.shape as Record<string, unknown>) ?? {});
    const withPaging: Record<string, unknown> = { ...input };
    if (flags.page !== undefined && shape.includes("page") && withPaging.page === undefined) withPaging.page = flags.page;
    if (flags.perPage !== undefined && shape.includes("per_page") && withPaging.per_page === undefined) withPaging.per_page = flags.perPage;
    if (flags.all) return collectAll(cmd, ctx, withPaging, shape.includes("per_page"));
    return cmd.run(ctx, withPaging);
  }
  return cmd.run(ctx, input);
}

async function collectAll(cmd: AnyCommand, ctx: Ctx, input: Record<string, unknown>, hasPerPage: boolean): Promise<Result> {
  const items: unknown[] = [];
  const limit = ctx.flags.limit;
  let page = Number(input.page ?? 1);
  let meta: PageMeta | undefined;
  for (;;) {
    const result = await cmd.run(ctx, { ...input, page, ...(hasPerPage && input.per_page === undefined ? { per_page: 100 } : {}) });
    const data = Array.isArray(result.data) ? result.data : [];
    items.push(...data);
    meta = result.meta as PageMeta | undefined;
    if (limit !== undefined && items.length >= limit) break;
    if (!meta || !meta.nextPage || data.length === 0) break;
    page = meta.nextPage;
  }
  const out = limit !== undefined ? items.slice(0, limit) : items;
  return { data: out, meta: { total: meta?.total ?? out.length, returned: out.length } };
}
