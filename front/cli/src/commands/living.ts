import { z } from "zod";
import { CliError } from "../errors.ts";
import { waitOperation } from "../http/lro.ts";
import type { AnyCommand, Ctx, Plan, Result } from "../registry/types.ts";
import { runHandle } from "./builder-lib.ts";
import { addSources } from "./builder.ts";
import { defineCommand } from "./define.ts";

/* Living Course from the command line (plan 7.5, Phase 3): sources and revisions, update proposals,
 * item decisions, apply and the audit trail. Runs reuse the builder's run model, so `--wait` polls
 * `GET /api/admin/course-builder/runs/{run}`. */

const LC = "/api/admin/living-course";
const common = { audience: ["author" as const], mcp: { toolset: "living" } };
const READ = ["living-course:read"];
const WRITE = ["living-course:write"];

const session = z
  .string()
  .describe(
    "Builder session id (the course's session; from `ulams builder sessions list`)."
  );
const proposal = z
  .string()
  .describe(
    "Update proposal id (from `ulams living proposals list <session>`)."
  );
const item = z
  .string()
  .describe("Proposal item id (from `ulams living proposals get <proposal>`).");

type Method = "GET" | "POST" | "PUT" | "DELETE";

async function lc<T>(
  ctx: Ctx,
  method: Method,
  path: string,
  o: {
    params?: Record<string, string>;
    query?: Record<string, string | number | undefined>;
    body?: unknown;
    form?: FormData;
  } = {}
): Promise<T> {
  const res = await ctx.client.call<T>(method, `${LC}${path}`, {
    ...(o.params ? { params: o.params } : {}),
    ...(o.query ? { query: o.query as Record<string, string | number> } : {}),
    ...(o.body !== undefined ? { body: o.body } : {}),
    ...(o.form ? { form: o.form } : {}),
    idempotent: method === "GET",
    signal: ctx.signal,
  });
  return res.data;
}

const planOf = (method: string, path: string, body?: unknown): Plan => ({
  request: {
    method,
    path: `${LC}${path}`,
    ...(body !== undefined ? { body } : {}),
  },
});

/** Waits for a run started by the API (analysis, apply) unless --no-wait. */
async function withRun(
  ctx: Ctx,
  data: Record<string, unknown>,
  runId: string | null | undefined
): Promise<Result> {
  if (!runId) return { data };
  const handle = runHandle(runId);
  if (!ctx.flags.wait) return { data, meta: { operation: handle } };
  const done = await waitOperation(ctx, handle);
  const run = done.data as {
    kind?: string;
    status?: string;
    steps?: Array<{ status: string }>;
  };
  return {
    data: {
      ...data,
      run: {
        id: runId,
        kind: run.kind ?? null,
        status: run.status ?? done.status,
        steps: run.steps?.length ?? 0,
      },
    },
  };
}

const wait = { longRunning: { kind: "builder-run" } };

export const livingCommands: AnyCommand[] = [
  /* ---- sources and connectors */
  defineCommand({
    ...common,
    id: "living.sources.list",
    summary:
      "List the sources of a course with their connection and sync state",
    kind: "read",
    scopes: READ,
    endpoints: [`GET ${LC}/sessions/{session}/sources`],
    positionals: ["session"],
    input: z.object({ session }),
    output: z.unknown(),
    examples: [
      { title: "Sources", argv: "living sources list <session> --json" },
    ],
    async run(ctx, i) {
      return {
        data: await lc(ctx, "GET", "/sessions/{session}/sources", {
          params: { session: i.session },
        }),
      };
    },
  }),
  defineCommand({
    ...common,
    ...wait,
    id: "living.sources.add",
    summary:
      "Add a source document (Markdown, PDF or DOCX) to a course's session",
    description:
      "Uploads a file or a URL's document as a new source; later versions of it go through `living revisions upload <source> <file>`. For a Git repository or web pages use `living sources connect`.",
    kind: "write",
    idempotent: false,
    scopes: ["builder:write"],
    endpoints: ["POST /api/admin/course-builder/sessions/{session}/sources"],
    positionals: ["session"],
    input: z.object({
      session,
      file: z
        .array(z.string())
        .optional()
        .describe("Local file; repeat for several."),
      url: z
        .array(z.string())
        .optional()
        .describe("URL of the document itself; repeat for several."),
    }),
    output: z.unknown(),
    examples: [
      {
        title: "Add a file",
        argv: "living sources add <session> --file ./handbook-v2.md --json",
      },
    ],
    plan: async (_ctx, i) => ({
      note: "Uploads the files.",
      request: {
        method: "POST",
        path: `/api/admin/course-builder/sessions/${i.session}/sources`,
        body: { files: i.file ?? [], urls: i.url ?? [] },
      },
    }),
    async run(ctx, i) {
      if (!(i.file?.length || i.url?.length))
        throw new CliError(
          "INPUT_INVALID",
          "Pass --file <path> or --url <url>."
        );
      const uploaded = await addSources(
        ctx,
        i.session,
        i.file ?? [],
        i.url ?? []
      );
      if (ctx.flags.wait)
        for (const u of uploaded)
          if (u.runId) await waitOperation(ctx, runHandle(u.runId));
      return { data: { uploaded } };
    },
  }),
  defineCommand({
    ...common,
    id: "living.sources.connect",
    summary:
      "Connect a Git repository or web pages as a source that is checked for changes",
    description:
      "The API fetches revision 1 and starts the interview. --config is the connector's settings (see `living connectors list` for the schema), --secrets holds write-only values such as a Git token (prefer @file over typing it). The webhook secret, if the connector has webhooks, is returned once.",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: [`POST ${LC}/sessions/{session}/sources/connect`],
    positionals: ["session"],
    input: z.object({
      session,
      connector: z
        .string()
        .describe("Connector key: git, url, or a plugin's key."),
      config: z
        .record(z.string(), z.unknown())
        .describe(
          'Connector settings as JSON or @file, e.g. {"host":"github","repository":"owner/name","branch":"main","paths":["docs"]}.'
        ),
      secrets: z
        .record(z.string(), z.string())
        .optional()
        .describe('Write-only values, e.g. {"token":"..."}; JSON or @file.'),
      schedule: z
        .enum(["hourly", "daily", "weekly", "manual"])
        .optional()
        .describe("How often to check (default: the connector's)."),
    }),
    output: z.unknown(),
    examples: [
      {
        title: "A Git docs folder",
        argv: 'living sources connect <session> --connector git --config \'{"host":"github","repository":"owner/name","branch":"main","paths":["docs"]}\' --schedule daily --json',
      },
    ],
    plan: async (_ctx, i) =>
      planOf("POST", `/sessions/${i.session}/sources/connect`, {
        connector: i.connector,
        config: i.config,
        secrets: i.secrets ? "«write-only»" : undefined,
        schedule: i.schedule,
      }),
    async run(ctx, i) {
      return {
        data: await lc(ctx, "POST", "/sessions/{session}/sources/connect", {
          params: { session: i.session },
          body: {
            connector: i.connector,
            config: i.config,
            ...(i.secrets ? { secrets: i.secrets } : {}),
            ...(i.schedule ? { schedule: i.schedule } : {}),
          },
        }),
      };
    },
  }),
  defineCommand({
    ...common,
    id: "living.connectors.list",
    summary: "List the source connectors this instance offers",
    kind: "read",
    scopes: READ,
    endpoints: [`GET ${LC}/connectors`],
    input: z.object({}),
    output: z.unknown(),
    examples: [{ title: "Connectors", argv: "living connectors list --json" }],
    async run(ctx) {
      return { data: await lc(ctx, "GET", "/connectors") };
    },
  }),
  defineCommand({
    ...common,
    id: "living.connections.check",
    summary: "Check a connected source for a new revision now",
    kind: "write",
    idempotent: true,
    scopes: WRITE,
    endpoints: [`POST ${LC}/connections/{connection}/check`],
    positionals: ["connection"],
    input: z.object({
      connection: z
        .string()
        .describe("Connection id (from `living sources list`)."),
    }),
    output: z.unknown(),
    examples: [
      {
        title: "Check now",
        argv: "living connections check <connection> --json",
      },
    ],
    plan: async (_ctx, i) =>
      planOf("POST", `/connections/${i.connection}/check`),
    async run(ctx, i) {
      return {
        data: await lc(ctx, "POST", "/connections/{connection}/check", {
          params: { connection: i.connection },
        }),
      };
    },
  }),
  defineCommand({
    ...common,
    id: "living.connections.update",
    summary:
      "Change a connection: schedule, automatic analysis, pause, learner notices, settings",
    kind: "write",
    idempotent: true,
    scopes: WRITE,
    endpoints: [`PUT ${LC}/connections/{connection}`],
    positionals: ["connection"],
    input: z.object({
      connection: z.string().describe("Connection id."),
      schedule: z.enum(["hourly", "daily", "weekly", "manual"]).optional(),
      autoAnalyse: z
        .boolean()
        .optional()
        .describe(
          "Analyse new revisions automatically (within the cost limit)."
        ),
      status: z.enum(["active", "paused"]).optional(),
      showPendingToLearners: z
        .boolean()
        .optional()
        .describe("Tell learners an update is under review."),
      notifyLearnersOfUpdates: z
        .boolean()
        .optional()
        .describe("Notify learners when an update is applied."),
      config: z
        .record(z.string(), z.unknown())
        .optional()
        .describe("Replace the connector settings (JSON or @file)."),
      secrets: z
        .record(z.string(), z.string())
        .optional()
        .describe(
          "Write-only values; a field left out keeps its stored value."
        ),
    }),
    output: z.unknown(),
    examples: [
      {
        title: "Pause",
        argv: "living connections update <connection> --status paused --json",
      },
    ],
    plan: async (_ctx, i) =>
      planOf("PUT", `/connections/${i.connection}`, connectionPatch(i)),
    async run(ctx, i) {
      const body = connectionPatch(i);
      if (Object.keys(body).length === 0)
        throw new CliError(
          "INPUT_INVALID",
          "Pass at least one setting to change, e.g. --status paused."
        );
      return {
        data: await lc(ctx, "PUT", "/connections/{connection}", {
          params: { connection: i.connection },
          body,
        }),
      };
    },
  }),
  defineCommand({
    ...common,
    id: "living.connections.webhook-secret",
    summary:
      "Rotate the webhook secret of a connection (shown once; the old one stops working)",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: [`POST ${LC}/connections/{connection}/webhook-secret`],
    positionals: ["connection"],
    input: z.object({ connection: z.string().describe("Connection id.") }),
    output: z.unknown(),
    examples: [
      {
        title: "Rotate",
        argv: "living connections webhook-secret <connection> --json",
      },
    ],
    plan: async (_ctx, i) =>
      planOf("POST", `/connections/${i.connection}/webhook-secret`),
    async run(ctx, i) {
      return {
        data: await lc(
          ctx,
          "POST",
          "/connections/{connection}/webhook-secret",
          { params: { connection: i.connection } }
        ),
      };
    },
  }),
  defineCommand({
    ...common,
    id: "living.connections.delete",
    summary:
      "Stop checking a source and forget its secrets (revisions and the audit trail stay)",
    kind: "destructive",
    idempotent: true,
    scopes: WRITE,
    endpoints: [`DELETE ${LC}/connections/{connection}`],
    positionals: ["connection"],
    input: z.object({ connection: z.string().describe("Connection id.") }),
    output: z.unknown(),
    examples: [
      {
        title: "Disconnect",
        argv: "living connections delete <connection> --yes --json",
      },
    ],
    plan: async (_ctx, i) => planOf("DELETE", `/connections/${i.connection}`),
    async run(ctx, i) {
      return {
        data: await lc(ctx, "DELETE", "/connections/{connection}", {
          params: { connection: i.connection },
        }),
      };
    },
  }),

  /* ---- revisions */
  defineCommand({
    ...common,
    id: "living.revisions.list",
    summary: "List the revisions of a source, newest first",
    kind: "read",
    scopes: READ,
    endpoints: [`GET ${LC}/sources/{source}/revisions`],
    positionals: ["source"],
    input: z.object({
      source: z.string().describe("Source id (from `living sources list`)."),
    }),
    output: z.unknown(),
    examples: [
      { title: "Revisions", argv: "living revisions list <source> --json" },
    ],
    async run(ctx, i) {
      return {
        data: await lc(ctx, "GET", "/sources/{source}/revisions", {
          params: { source: i.source },
        }),
      };
    },
  }),
  defineCommand({
    ...common,
    id: "living.revisions.get",
    summary: "Show one source revision",
    kind: "read",
    scopes: READ,
    endpoints: [`GET ${LC}/revisions/{revision}`],
    positionals: ["revision"],
    input: z.object({ revision: z.string().describe("Revision id.") }),
    output: z.unknown(),
    examples: [
      { title: "A revision", argv: "living revisions get <revision> --json" },
    ],
    async run(ctx, i) {
      return {
        data: await lc(ctx, "GET", "/revisions/{revision}", {
          params: { revision: i.revision },
        }),
      };
    },
  }),
  defineCommand({
    ...common,
    id: "living.revisions.changes",
    summary:
      "What changed in a revision (fragments added, removed, moved, changed) and which course elements cite them",
    kind: "read",
    scopes: READ,
    endpoints: [`GET ${LC}/revisions/{revision}/changes`],
    positionals: ["revision"],
    input: z.object({
      revision: z.string().describe("Revision id."),
      against: z
        .string()
        .optional()
        .describe(
          "Compare with this revision (default: the one it was compared with)."
        ),
    }),
    output: z.unknown(),
    examples: [
      { title: "Changes", argv: "living revisions changes <revision> --json" },
    ],
    async run(ctx, i) {
      return {
        data: await lc(ctx, "GET", "/revisions/{revision}/changes", {
          params: { revision: i.revision },
          query: { against: i.against },
        }),
      };
    },
  }),
  defineCommand({
    ...common,
    id: "living.revisions.upload",
    summary: "Upload a new version of a source file as a new revision",
    description:
      "created is false when the file equals the latest revision. A changed file starts change detection and, when AI is on, an update proposal.",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: [`POST ${LC}/sources/{source}/revisions`],
    positionals: ["source", "file"],
    input: z.object({
      source: z.string().describe("Source id."),
      file: z.string().describe("Local file (Markdown, PDF or DOCX)."),
    }),
    output: z.unknown(),
    examples: [
      {
        title: "A new version",
        argv: "living revisions upload <source> ./handbook-v2.md --json",
      },
    ],
    plan: async (_ctx, i) =>
      planOf("POST", `/sources/${i.source}/revisions`, { file: i.file }),
    async run(ctx, i) {
      let bytes: Uint8Array;
      try {
        bytes = await ctx.fs.readFile(i.file);
      } catch {
        throw new CliError("INPUT_INVALID", `Cannot read ${i.file}.`);
      }
      const form = new FormData();
      form.append(
        "file",
        new Blob([bytes as BlobPart]),
        i.file.split(/[\\/]/).pop() ?? "source"
      );
      return {
        data: await lc(ctx, "POST", "/sources/{source}/revisions", {
          params: { source: i.source },
          form,
        }),
      };
    },
  }),
  defineCommand({
    ...common,
    id: "living.staleness",
    summary: "Which course elements are out of date against their sources",
    description:
      "Works with AI disabled: it is deterministic change detection.",
    kind: "read",
    scopes: READ,
    endpoints: [`GET ${LC}/sessions/{session}/staleness`],
    positionals: ["session"],
    input: z.object({ session }),
    output: z.unknown(),
    examples: [
      { title: "Staleness", argv: "living staleness <session> --json" },
    ],
    async run(ctx, i) {
      return {
        data: await lc(ctx, "GET", "/sessions/{session}/staleness", {
          params: { session: i.session },
        }),
      };
    },
  }),

  /* ---- proposals */
  defineCommand({
    ...common,
    id: "living.proposals.list",
    summary: "List the update proposals of a course, newest first",
    kind: "read",
    scopes: READ,
    endpoints: [`GET ${LC}/sessions/{session}/proposals`],
    positionals: ["session"],
    input: z.object({ session }),
    output: z.unknown(),
    examples: [
      { title: "Proposals", argv: "living proposals list <session> --json" },
    ],
    async run(ctx, i) {
      return {
        data: await lc(ctx, "GET", "/sessions/{session}/proposals", {
          params: { session: i.session },
        }),
      };
    },
  }),
  defineCommand({
    ...common,
    id: "living.proposals.get",
    summary:
      "Show a proposal: its items grouped by lesson, the decisions and the analysis steps",
    kind: "read",
    scopes: READ,
    endpoints: [`GET ${LC}/proposals/{proposal}`],
    positionals: ["proposal"],
    input: z.object({ proposal }),
    output: z.unknown(),
    examples: [
      { title: "A proposal", argv: "living proposals get <proposal> --json" },
    ],
    async run(ctx, i) {
      return {
        data: await lc(ctx, "GET", "/proposals/{proposal}", {
          params: { proposal: i.proposal },
        }),
      };
    },
  }),
  defineCommand({
    ...common,
    ...wait,
    id: "living.proposals.analyse",
    summary: "Start (or resume) the AI analysis of a proposal",
    description:
      "Costs model tokens. When the estimate is above the automatic limit the API answers CONFLICT with the estimate: repeat with --confirm-estimate. 503 FEATURE_DISABLED when AI is off.",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: [`POST ${LC}/proposals/{proposal}/analyse`],
    positionals: ["proposal"],
    input: z.object({
      proposal,
      confirmEstimate: z
        .boolean()
        .optional()
        .describe("Accept the cost estimate."),
    }),
    output: z.unknown(),
    examples: [
      {
        title: "Analyse",
        argv: "living proposals analyse <proposal> --confirm-estimate --json",
      },
    ],
    plan: async (_ctx, i) =>
      planOf("POST", `/proposals/${i.proposal}/analyse`, {
        confirmEstimate: Boolean(i.confirmEstimate),
      }),
    async run(ctx, i) {
      try {
        const res = await lc<{
          state: string;
          estimateMicroUsd: number | null;
          runId: string | null;
          message: string | null;
        }>(ctx, "POST", "/proposals/{proposal}/analyse", {
          params: { proposal: i.proposal },
          body: { confirmEstimate: Boolean(i.confirmEstimate) },
        });
        return withRun(
          ctx,
          res as unknown as Record<string, unknown>,
          res.runId
        );
      } catch (error) {
        const body = (error as CliError).details?.body as
          | { code?: string; data?: { estimateMicroUsd?: number } }
          | undefined;
        if (body?.code === "confirm_estimate") {
          throw new CliError("CONFLICT", (error as CliError).message, {
            details: (error as CliError).details,
            hint: `The estimate is ${(
              (body.data?.estimateMicroUsd ?? 0) / 1_000_000
            ).toFixed(2)} USD. Repeat with --confirm-estimate to accept it.`,
          });
        }
        throw error;
      }
    },
  }),
  defineCommand({
    ...common,
    id: "living.proposals.reanalyse",
    summary:
      "Start over from the newest revision (the open proposal is superseded; decisions are not carried over)",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: [`POST ${LC}/proposals/{proposal}/reanalyse`],
    positionals: ["proposal"],
    input: z.object({ proposal }),
    output: z.unknown(),
    examples: [
      {
        title: "Reanalyse",
        argv: "living proposals reanalyse <proposal> --json",
      },
    ],
    plan: async (_ctx, i) =>
      planOf("POST", `/proposals/${i.proposal}/reanalyse`),
    async run(ctx, i) {
      return {
        data: await lc(ctx, "POST", "/proposals/{proposal}/reanalyse", {
          params: { proposal: i.proposal },
        }),
      };
    },
  }),
  ...(["accept", "reject", "reset"] as const).map((verb) =>
    defineCommand({
      ...common,
      id: `living.proposals.${verb}`,
      summary:
        verb === "accept"
          ? "Accept one item of a proposal"
          : verb === "reject"
          ? "Reject one item of a proposal"
          : "Reset a decision on one item",
      description:
        "Nothing reaches the course until `living proposals apply`. Item ids come from `living proposals get`.",
      kind: "write",
      idempotent: true,
      scopes: WRITE,
      endpoints: [`POST ${LC}/proposals/{proposal}/items/{item}/${verb}`],
      positionals: ["proposal"],
      input: z.object({ proposal, item }),
      output: z.unknown(),
      examples: [
        {
          title: verb,
          argv: `living proposals ${verb} <proposal> --item <item> --json`,
        },
      ],
      plan: async (_ctx, i: { proposal: string; item: string }) =>
        planOf("POST", `/proposals/${i.proposal}/items/${i.item}/${verb}`),
      async run(ctx, i: { proposal: string; item: string }) {
        return {
          data: await lc(
            ctx,
            "POST",
            `/proposals/{proposal}/items/{item}/${verb}`,
            { params: { proposal: i.proposal, item: i.item } }
          ),
        };
      },
    })
  ),
  defineCommand({
    ...common,
    id: "living.proposals.regenerate",
    summary:
      "Ask for a new suggestion for one item, with a comment (at most three per item)",
    description: "One new model call for one element.",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: [`POST ${LC}/proposals/{proposal}/items/{item}/regenerate`],
    positionals: ["proposal"],
    input: z.object({
      proposal,
      item,
      comment: z
        .string()
        .optional()
        .describe("What to do differently (up to 1000 characters)."),
    }),
    output: z.unknown(),
    examples: [
      {
        title: "Regenerate",
        argv: 'living proposals regenerate <proposal> --item <item> --comment "Keep the example" --json',
      },
    ],
    plan: async (_ctx, i) =>
      planOf("POST", `/proposals/${i.proposal}/items/${i.item}/regenerate`, {
        comment: i.comment ?? "",
      }),
    async run(ctx, i) {
      return {
        data: await lc(
          ctx,
          "POST",
          "/proposals/{proposal}/items/{item}/regenerate",
          {
            params: { proposal: i.proposal, item: i.item },
            body: { comment: i.comment ?? "" },
          }
        ),
      };
    },
  }),
  defineCommand({
    ...common,
    id: "living.proposals.accept-all",
    summary: "Accept every pending item of a proposal",
    kind: "write",
    idempotent: true,
    scopes: WRITE,
    endpoints: [`POST ${LC}/proposals/{proposal}/accept-all`],
    positionals: ["proposal"],
    input: z.object({ proposal }),
    output: z.unknown(),
    examples: [
      {
        title: "Accept all",
        argv: "living proposals accept-all <proposal> --json",
      },
    ],
    plan: async (_ctx, i) =>
      planOf("POST", `/proposals/${i.proposal}/accept-all`),
    async run(ctx, i) {
      return {
        data: await lc(ctx, "POST", "/proposals/{proposal}/accept-all", {
          params: { proposal: i.proposal },
        }),
      };
    },
  }),
  defineCommand({
    ...common,
    id: "living.proposals.reject-all",
    summary: "Reject the whole proposal and acknowledge the source revision",
    kind: "destructive",
    idempotent: true,
    scopes: WRITE,
    endpoints: [`POST ${LC}/proposals/{proposal}/reject`],
    positionals: ["proposal"],
    input: z.object({ proposal }),
    output: z.unknown(),
    examples: [
      {
        title: "Reject",
        argv: "living proposals reject-all <proposal> --yes --json",
      },
    ],
    plan: async (_ctx, i) => planOf("POST", `/proposals/${i.proposal}/reject`),
    async run(ctx, i) {
      return {
        data: await lc(ctx, "POST", "/proposals/{proposal}/reject", {
          params: { proposal: i.proposal },
        }),
      };
    },
  }),
  defineCommand({
    ...common,
    id: "living.proposals.learner-note",
    summary:
      "Set the note learners see about this update (up to 500 characters; empty restores the suggested one)",
    kind: "write",
    idempotent: true,
    scopes: WRITE,
    endpoints: [`PUT ${LC}/proposals/{proposal}/learner-note`],
    positionals: ["proposal"],
    input: z.object({
      proposal,
      note: z.string().describe("The note, plain text."),
    }),
    output: z.unknown(),
    examples: [
      {
        title: "Set the note",
        argv: 'living proposals learner-note <proposal> --note "Lesson 3 now covers the new API." --json',
      },
    ],
    plan: async (_ctx, i) =>
      planOf("PUT", `/proposals/${i.proposal}/learner-note`, { note: i.note }),
    async run(ctx, i) {
      return {
        data: await lc(ctx, "PUT", "/proposals/{proposal}/learner-note", {
          params: { proposal: i.proposal },
          body: { note: i.note },
        }),
      };
    },
  }),
  defineCommand({
    ...common,
    ...wait,
    id: "living.proposals.apply",
    summary: "Apply the accepted items as a new course version",
    description:
      "Creates a blueprint version of kind update and applies it through the course services. A 409 lists conflicts (items that cannot apply) or admin edits made since the last apply; repeat with --overwrite to replace those edits. Waits for the run (--no-wait returns the handle).",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: [`POST ${LC}/proposals/{proposal}/apply`],
    positionals: ["proposal"],
    input: z.object({
      proposal,
      overwrite: z
        .boolean()
        .optional()
        .describe("Overwrite admin edits made since the last apply."),
    }),
    output: z.unknown(),
    examples: [
      { title: "Apply", argv: "living proposals apply <proposal> --json" },
    ],
    plan: async (_ctx, i) =>
      planOf("POST", `/proposals/${i.proposal}/apply`, {
        overwrite: Boolean(i.overwrite),
      }),
    async run(ctx, i) {
      try {
        const res = await lc<{ runId: string; proposal: unknown }>(
          ctx,
          "POST",
          "/proposals/{proposal}/apply",
          {
            params: { proposal: i.proposal },
            body: { overwrite: Boolean(i.overwrite) },
          }
        );
        return withRun(
          ctx,
          res as unknown as Record<string, unknown>,
          res.runId
        );
      } catch (error) {
        const body = (error as CliError).details?.body as
          | { code?: string }
          | undefined;
        if (body?.code === "admin_edits") {
          throw new CliError("CONFLICT", (error as CliError).message, {
            details: (error as CliError).details,
            hint: "An admin edited the course after the last apply. Repeat with --overwrite to replace those edits.",
          });
        }
        if (body?.code === "conflicts") {
          throw new CliError("CONFLICT", (error as CliError).message, {
            details: (error as CliError).details,
            hint: "Some accepted items conflict. Reject or regenerate them (see details.body.data.conflicts), then apply again.",
          });
        }
        throw error;
      }
    },
  }),

  /* ---- audit */
  defineCommand({
    ...common,
    id: "living.audit.list",
    summary: "The audit trail of a course's updates, newest first",
    kind: "read",
    scopes: READ,
    endpoints: [`GET ${LC}/sessions/{session}/audit`],
    positionals: ["session"],
    input: z.object({
      session,
      action: z
        .string()
        .optional()
        .describe("Action prefix, e.g. proposal. or item.accepted."),
      actorType: z.string().optional(),
      from: z.string().optional().describe("Date-time."),
      to: z.string().optional().describe("Date-time."),
      source: z.string().optional(),
      page: z.number().int().optional(),
      perPage: z.number().int().optional(),
    }),
    output: z.unknown(),
    examples: [
      {
        title: "Recent decisions",
        argv: "living audit list <session> --action item. --json",
      },
    ],
    async run(ctx, i) {
      const { session: s, ...filters } = i;
      return {
        data: await lc(ctx, "GET", "/sessions/{session}/audit", {
          params: { session: s },
          query: filters as Record<string, string | number | undefined>,
        }),
      };
    },
  }),
  defineCommand({
    ...common,
    id: "living.audit.export",
    summary:
      "Export the audit trail of a course as CSV or JSON (stdout, or --out <file>)",
    kind: "read",
    scopes: READ,
    endpoints: [`GET ${LC}/sessions/{session}/audit/export`],
    positionals: ["session"],
    input: z.object({
      session,
      format: z.enum(["csv", "json"]).optional().describe("Default csv."),
      action: z.string().optional(),
      from: z.string().optional(),
      to: z.string().optional(),
      out: z.string().optional().describe("Write to this file."),
    }),
    output: z.unknown(),
    examples: [
      {
        title: "To a file",
        argv: "living audit export <session> --format json --out audit.json",
      },
    ],
    async run(ctx, i) {
      const { session: s, out, ...filters } = i;
      const file = await ctx.client.download(
        "GET",
        `${LC}/sessions/{session}/audit/export`,
        {
          params: { session: s },
          query: {
            format: filters.format ?? "csv",
            ...(filters.action ? { action: filters.action } : {}),
            ...(filters.from ? { from: filters.from } : {}),
            ...(filters.to ? { to: filters.to } : {}),
          },
          idempotent: true,
          signal: ctx.signal,
        }
      );
      const target = out ?? ctx.flags.out;
      if (!target || target === "-")
        return { data: new TextDecoder().decode(file.data) };
      await ctx.fs.writeFile(target, file.data);
      return {
        data: {
          path: target,
          bytes: file.data.byteLength,
          contentType: file.contentType,
        },
      };
    },
  }),
  defineCommand({
    ...common,
    id: "living.audit.verify",
    summary: "Recompute the audit hash chain and report whether it is intact",
    description: "The chain covers the whole academy, not one course.",
    kind: "read",
    scopes: READ,
    endpoints: [`GET ${LC}/sessions/{session}/audit/verify`],
    positionals: ["session"],
    input: z.object({ session }),
    output: z.unknown(),
    examples: [
      { title: "Verify", argv: "living audit verify <session> --json" },
    ],
    async run(ctx, i) {
      return {
        data: await lc(ctx, "GET", "/sessions/{session}/audit/verify", {
          params: { session: i.session },
        }),
      };
    },
  }),
  defineCommand({
    ...common,
    id: "living.audit.export-all",
    summary:
      "Export the audit trail of the whole academy as CSV or JSON (admins only)",
    kind: "read",
    scopes: READ,
    endpoints: [`GET ${LC}/audit/export`],
    input: z.object({
      format: z.enum(["csv", "json"]).optional().describe("Default csv."),
      action: z.string().optional(),
      from: z.string().optional(),
      to: z.string().optional(),
      out: z.string().optional().describe("Write to this file."),
    }),
    output: z.unknown(),
    examples: [
      {
        title: "Everything to a file",
        argv: "living audit export-all --format json --out audit-all.json",
      },
    ],
    async run(ctx, i) {
      const { out, ...filters } = i;
      const file = await ctx.client.download("GET", `${LC}/audit/export`, {
        query: {
          format: filters.format ?? "csv",
          ...(filters.action ? { action: filters.action } : {}),
          ...(filters.from ? { from: filters.from } : {}),
          ...(filters.to ? { to: filters.to } : {}),
        },
        idempotent: true,
        signal: ctx.signal,
      });
      const target = out ?? ctx.flags.out;
      if (!target || target === "-")
        return { data: new TextDecoder().decode(file.data) };
      await ctx.fs.writeFile(target, file.data);
      return {
        data: {
          path: target,
          bytes: file.data.byteLength,
          contentType: file.contentType,
        },
      };
    },
  }),
  defineCommand({
    ...common,
    id: "living.audit.verify-all",
    summary:
      "Recompute the audit hash chain of the whole academy (admins only)",
    kind: "read",
    scopes: READ,
    endpoints: [`GET ${LC}/audit/verify`],
    input: z.object({}),
    output: z.unknown(),
    examples: [{ title: "Verify", argv: "living audit verify-all --json" }],
    async run(ctx) {
      return { data: await lc(ctx, "GET", "/audit/verify") };
    },
  }),
];

function connectionPatch(i: {
  schedule?: string | undefined;
  autoAnalyse?: boolean | undefined;
  status?: string | undefined;
  showPendingToLearners?: boolean | undefined;
  notifyLearnersOfUpdates?: boolean | undefined;
  config?: Record<string, unknown> | undefined;
  secrets?: Record<string, string> | undefined;
}): Record<string, unknown> {
  const body: Record<string, unknown> = {};
  if (i.schedule !== undefined) body.schedule = i.schedule;
  if (i.autoAnalyse !== undefined) body.autoAnalyse = i.autoAnalyse;
  if (i.status !== undefined) body.status = i.status;
  const settings: Record<string, boolean> = {};
  if (i.showPendingToLearners !== undefined)
    settings.show_pending_to_learners = i.showPendingToLearners;
  if (i.notifyLearnersOfUpdates !== undefined)
    settings.notify_learners_of_updates = i.notifyLearnersOfUpdates;
  if (Object.keys(settings).length) body.settings = settings;
  if (i.config !== undefined) body.config = i.config;
  if (i.secrets !== undefined) body.secrets = i.secrets;
  return body;
}
