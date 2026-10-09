import { z } from "zod";
import { CliError } from "../errors.ts";
import { registerOperationKind, waitOperation } from "../http/lro.ts";
import type { AnyCommand, Ctx, Plan, Result } from "../registry/types.ts";
import { defineCommand } from "./define.ts";

/* Tenant management on a platform host (plan 6.6, ADR 0078): `ulams tenants ...`. The server API is off
 * unless TENANCY_PLATFORM_API=true and exists on platform hosts only; everything else answers 404. */

const P = "/api/platform";
const common = { audience: ["platform" as const], mcp: { toolset: "tenants" } };
const READ = ["platform:read"];
const WRITE = ["platform:write"];

interface Operation {
  id: string;
  kind: string;
  status: "queued" | "running" | "succeeded" | "failed";
  tenant: string;
  steps: Array<{ name: string; status: string; startedAt: string | null; finishedAt: string | null; error: string | null }>;
  error: string | null;
}

registerOperationKind({
  kind: "tenant-op",
  describe: "A tenant creation or deletion on the platform: tenant-op:<operation id>",
  async get(ctx, id) {
    const op = (await ctx.client.call<Operation>("GET", `${P}/operations/{id}`, { params: { id }, idempotent: true, signal: ctx.signal })).data;
    if (op.status === "failed") return { status: "failed", data: op, error: op.error ?? "the operation failed" };
    if (op.status === "succeeded") return { status: "succeeded", data: op };
    return { status: "running", data: op };
  },
});

const handleOf = (id: string) => `tenant-op:${id}`;

const planOf = (method: string, path: string, body?: unknown): Plan => ({ request: { method, path, ...(body !== undefined ? { body } : {}) } });

/** The API answers 404 when the platform API is off or this is a tenant host: say so instead of "not found". */
async function platform<T>(ctx: Ctx, method: "GET" | "POST" | "PATCH" | "DELETE", path: string, o: { params?: Record<string, string>; body?: unknown } = {}): Promise<{ data: T; body: unknown }> {
  try {
    const res = await ctx.client.call<T>(method, path, { ...(o.params ? { params: o.params } : {}), ...(o.body !== undefined ? { body: o.body } : {}), idempotent: method === "GET", signal: ctx.signal });
    return { data: res.data, body: res.body };
  } catch (error) {
    if (error instanceof CliError && error.code === "NOT_FOUND" && /^(Not found\.?|The route .* could not be found\.?)$/i.test(error.message)) {
      throw new CliError("FEATURE_DISABLED", "The platform API is not available on this host.", {
        status: 404,
        hint: "Use a platform host (for example http://api.localhost) of an installation that runs with TENANCY_PLATFORM_API=true; tenant hosts never have it.",
      });
    }
    throw error;
  }
}

async function started(ctx: Ctx, data: { operation: Operation; tenant?: unknown }): Promise<Result> {
  const handle = handleOf(data.operation.id);
  if (!ctx.flags.wait) return { data, meta: { operation: handle } };
  const done = await waitOperation(ctx, handle);
  const operation = done.data as Operation;
  let tenant: unknown = data.tenant ?? null;
  if (operation.kind === "create") {
    tenant = (await platform(ctx, "GET", `${P}/tenants/{slug}`, { params: { slug: operation.tenant } })).data;
  }
  return { data: { operation, tenant: operation.kind === "delete" ? null : tenant } };
}

const slugInput = z.string().describe("Tenant slug (from `ulams tenants list`).");

export const tenantCommands: AnyCommand[] = [
  defineCommand({
    ...common,
    id: "tenants.list",
    summary: "List the tenants of this platform",
    kind: "read",
    scopes: READ,
    endpoints: [`GET ${P}/tenants`],
    input: z.object({}),
    output: z.unknown(),
    examples: [{ title: "Tenants", argv: "tenants list --fields slug,status,urls.front --json" }],
    async run(ctx) {
      return { data: (await platform(ctx, "GET", `${P}/tenants`)).data };
    },
  }),
  defineCommand({
    ...common,
    id: "tenants.get",
    summary: "Show one tenant: status, hosts, finished provisioning steps, the names of its setting overrides",
    kind: "read",
    scopes: READ,
    endpoints: [`GET ${P}/tenants/{slug}`],
    positionals: ["slug"],
    input: z.object({ slug: slugInput }),
    output: z.unknown(),
    examples: [{ title: "A tenant", argv: "tenants get coffee --json" }],
    async run(ctx, i) {
      return { data: (await platform(ctx, "GET", `${P}/tenants/{slug}`, { params: { slug: i.slug } })).data };
    },
  }),
  defineCommand({
    ...common,
    id: "tenants.create",
    summary: "Create a tenant (database, bucket, env, keys, demo users) and wait for it",
    description:
      "Provisioning is queued and takes about a minute; this waits and prints progress (use --no-wait to return the handle tenant-op:<id> for `ulams operations wait`). Asking for a tenant that failed resumes it at the first unfinished step; an existing active tenant is a conflict (exit 6). The slug is 2 to 30 lowercase letters and digits starting with a letter.",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: [`POST ${P}/tenants`],
    longRunning: { kind: "tenant-op" },
    positionals: ["slug"],
    input: z.object({
      slug: z.string().describe("Slug of the new tenant."),
      name: z.string().optional().describe("Display name."),
      theme: z.string().optional().describe("Front theme preset, coffee, oncall, nightsky, gravity, poland or ulam."),
      accent: z.string().optional().describe("Accent colour, e.g. #C2552D."),
      users: z.number().int().min(0).max(50).optional().describe("Number of demo students (default 5)."),
      demo: z.boolean().optional().describe("Demo mode: login without a password, hourly reset."),
    }),
    output: z.unknown(),
    examples: [{ title: "A demo tenant", argv: 'tenants create acme --name "Acme Academy" --theme coffee --accent "#C2552D" --users 3 --demo --json' }],
    plan: async (_ctx, i) => planOf("POST", `${P}/tenants`, i),
    async run(ctx, i) {
      const res = await platform<{ operation: Operation; tenant: unknown }>(ctx, "POST", `${P}/tenants`, { body: i });
      return started(ctx, res.data);
    },
  }),
  defineCommand({
    ...common,
    id: "tenants.set-env",
    summary: "Override or reset inheritable settings of a tenant (AI driver, key and models)",
    description:
      "Only the keys the platform lets a tenant override are accepted (ANTHROPIC_API_KEY, AI_DRIVER, AI_MODEL_*); anything else is refused (exit 7). Values are never shown back: the result lists the names of the overridden keys. Example: --override AI_DRIVER=fake.",
    kind: "write",
    idempotent: true,
    scopes: WRITE,
    endpoints: [`PATCH ${P}/tenants/{slug}/env`],
    positionals: ["slug"],
    input: z.object({
      slug: slugInput,
      override: z.array(z.string()).optional().describe("KEY=value; repeat for several."),
      reset: z.array(z.string()).optional().describe("KEY to drop, so the tenant inherits the platform value again; repeat."),
    }),
    output: z.unknown(),
    examples: [{ title: "A fake AI driver for tests", argv: "tenants set-env acme --override AI_DRIVER=fake --json" }],
    plan: async (_ctx, i) => planOf("PATCH", `${P}/tenants/${i.slug}/env`, { set: Object.keys(parseOverrides(i.override)), unset: i.reset ?? [] }),
    async run(ctx, i) {
      const set = parseOverrides(i.override);
      if (Object.keys(set).length === 0 && !(i.reset?.length)) throw new CliError("INPUT_INVALID", "Pass --override KEY=value or --reset KEY.");
      return { data: (await platform(ctx, "PATCH", `${P}/tenants/{slug}/env`, { params: { slug: i.slug }, body: { set, unset: i.reset ?? [] } })).data };
    },
  }),
  defineCommand({
    ...common,
    id: "tenants.delete",
    summary: "Delete a tenant and ALL its data (database, files, settings) and wait for it",
    description:
      "Permanent. The command asks for --yes (exit 11 with the plan otherwise); the slug is sent to the API as the confirmation. Waits for the queued deletion (--no-wait returns the handle tenant-op:<id>).",
    kind: "destructive",
    idempotent: false,
    scopes: WRITE,
    endpoints: [`DELETE ${P}/tenants/{slug}`],
    longRunning: { kind: "tenant-op" },
    positionals: ["slug"],
    input: z.object({ slug: slugInput }),
    output: z.unknown(),
    examples: [{ title: "Delete", argv: "tenants delete acme --yes --json" }],
    plan: async (ctx, i) => {
      const tenant = await platform(ctx, "GET", `${P}/tenants/{slug}`, { params: { slug: i.slug } }).then((r) => r.data).catch(() => null);
      return { ...planOf("DELETE", `${P}/tenants/${i.slug}`, { confirm: i.slug }), ...(tenant ? { current: tenant } : {}), note: "Drops the database, bucket objects, env file, storage and Redis keys of this tenant." };
    },
    async run(ctx, i) {
      const res = await platform<{ operation: Operation }>(ctx, "DELETE", `${P}/tenants/{slug}`, { params: { slug: i.slug }, body: { confirm: i.slug } });
      return started(ctx, res.data);
    },
  }),
  defineCommand({
    ...common,
    id: "tenants.operations.list",
    summary: "The latest tenant operations (creations and deletions), newest first",
    kind: "read",
    scopes: READ,
    endpoints: [`GET ${P}/operations`],
    input: z.object({}),
    output: z.unknown(),
    examples: [{ title: "Operations", argv: "tenants operations list --json" }],
    async run(ctx) {
      return { data: (await platform(ctx, "GET", `${P}/operations`)).data };
    },
  }),
  defineCommand({
    ...common,
    id: "tenants.operations.get",
    summary: "Status and steps of one tenant operation",
    kind: "read",
    scopes: READ,
    endpoints: [`GET ${P}/operations/{id}`],
    positionals: ["id"],
    input: z.object({ id: z.string().describe("Operation id (from `tenants create --no-wait` or `tenants operations list`).") }),
    output: z.unknown(),
    examples: [{ title: "An operation", argv: "tenants operations get 01j9z3k8m2x4q7r5t6v8w0y1ab --json" }],
    async run(ctx, i) {
      return { data: (await platform(ctx, "GET", `${P}/operations/{id}`, { params: { id: i.id } })).data };
    },
  }),
];

function parseOverrides(pairs: string[] | undefined): Record<string, string> {
  const out: Record<string, string> = {};
  for (const pair of pairs ?? []) {
    const eq = pair.indexOf("=");
    if (eq < 1 || eq === pair.length - 1) throw new CliError("INPUT_INVALID", `--override expects KEY=value, got "${pair}".`);
    out[pair.slice(0, eq).trim().toUpperCase()] = pair.slice(eq + 1);
  }
  return out;
}
