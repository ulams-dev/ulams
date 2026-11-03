import type { Ctx, Plan } from "../registry/types.ts";

/** `--json` (or an explicit machine-readable output) asks for the envelope; otherwise the folder commands print text. */
export function wantsJson(ctx: Ctx): boolean {
  return ctx.flags.json || ctx.flags.output === "json" || ctx.flags.output === "ndjson" || ctx.flags.output === "yaml" || ctx.env.ULAMS_OUTPUT === "json";
}

export const out = (ctx: Ctx, text: string): void => (ctx.io.stdout ? ctx.io.stdout(text) : void process.stdout.write(`${text}\n`));

export const COURSE_AS_CODE = "/api/admin/course-as-code";

export async function call<T>(ctx: Ctx, method: "GET" | "POST", path: string, o: { params?: Record<string, string | number>; query?: Record<string, string | undefined>; body?: unknown } = {}): Promise<T> {
  const res = await ctx.client.call<T>(method, `${COURSE_AS_CODE}${path}`, {
    ...(o.params ? { params: o.params } : {}),
    ...(o.query ? { query: o.query } : {}),
    ...(o.body !== undefined ? { body: o.body } : {}),
    idempotent: method === "GET",
    signal: ctx.signal,
  });
  return res.data;
}

export const planOf = (method: string, path: string, body?: unknown): Plan => ({ request: { method, path: `${COURSE_AS_CODE}${path}`, ...(body !== undefined ? { body } : {}) } });

/** Course versions (ADR 0192): releases per course, no AI gate. */
export const COURSE_VERSIONS = "/api/admin/course-versions";

export async function callVersions<T>(ctx: Ctx, path: string, o: { params?: Record<string, string | number>; query?: Record<string, string | number | undefined> } = {}): Promise<T> {
  const res = await ctx.client.call<T>("GET", `${COURSE_VERSIONS}${path}`, {
    ...(o.params ? { params: o.params } : {}),
    ...(o.query ? { query: o.query as Record<string, string | undefined> } : {}),
    idempotent: true,
    signal: ctx.signal,
  });
  return res.data;
}
