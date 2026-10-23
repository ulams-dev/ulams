import { z } from "zod";
import { CliError } from "../errors.ts";
import type { AnyCommand, Ctx, Plan } from "../registry/types.ts";
import { builderCall } from "./builder-lib.ts";
import { defineCommand } from "./define.ts";

/* Style profiles (ADR 0196): the cited, author-approved reading of an organisation's style guide. Every command is
 * also an MCP tool (toolset `builder`). A model proposes the values, an admin approves them as a diff; nothing here
 * changes a course or a theme. */

const common = { audience: ["author" as const, "admin" as const], mcp: { toolset: "builder" } };
const READ = ["builder:read"];
const WRITE = ["builder:write"];
const BASE = "/api/admin/course-builder/style-profiles";

const profile = z.string().describe("Style profile id (from `ulams style-profiles list`).");
const values = z.record(z.string(), z.unknown()).describe("Profile values as JSON, or @file.json / @file.yaml: voice, readingLevel, terminology, banned, components, imagery, theme, examples (schema: style-profile/v1).");

const planOf = (method: string, path: string, body?: unknown): Plan => ({ request: { method, path, ...(body !== undefined ? { body } : {}) } });

async function readStyleGuide(ctx: Ctx, path: string): Promise<{ name: string; bytes: Uint8Array }> {
  try {
    return { name: path.split(/[\\/]/).pop() ?? "style-guide", bytes: await ctx.fs.readFile(path) };
  } catch {
    throw new CliError("INPUT_INVALID", `Cannot read ${path}.`, { hint: "Pass the path of a Markdown, PDF or DOCX style guide." });
  }
}

export const styleProfileCommands: AnyCommand[] = [
  defineCommand({
    ...common,
    id: "style-profiles.list",
    summary: "List the style profiles of the academy, newest first",
    description: "A profile is suggested until an admin approves it; the approved one of a scope is what later steps read. `data[].profile` holds the values, `data[].warnings` what was dropped from a proposal.",
    kind: "read",
    scopes: READ,
    endpoints: [`GET ${BASE}`],
    input: z.object({ status: z.enum(["suggested", "approved", "superseded"]).optional().describe("Only profiles in this status.") }),
    output: z.unknown(),
    examples: [{ title: "The approved profile", argv: "style-profiles list --status approved --json" }],
    async run(ctx, i) {
      return { data: await builderCall(ctx, "GET", "/style-profiles", { query: { status: i.status } }) };
    },
  }),
  defineCommand({
    ...common,
    id: "style-profiles.get",
    summary: "Show a style profile with the passages it cites and its diff against the approved profile",
    kind: "read",
    scopes: READ,
    endpoints: [`GET ${BASE}/{profile}`],
    positionals: ["profile"],
    input: z.object({ profile }),
    output: z.unknown(),
    examples: [{ title: "Review a proposal", argv: "style-profiles get <profile> --json" }],
    async run(ctx, i) {
      return { data: await builderCall(ctx, "GET", "/style-profiles/{profile}", { params: { profile: i.profile } }) };
    },
  }),
  defineCommand({
    ...common,
    id: "style-profiles.propose",
    summary: "Propose a style profile from a style guide file (read and cited by the model), by hand, or as a revision of an existing profile",
    description:
      "With --file the style guide (Markdown, PDF or DOCX; untrusted input) is read by one model call that returns typed values, each citing the passage it comes from; values that are out of range, unknown or uncited are dropped and listed in `data.warnings`. Without AI, or with --profile, you give the values yourself. With --from the new profile starts from an existing one: that is how an approved rule changes. Nothing applies until an admin runs `style-profiles approve`.",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: [`POST ${BASE}`],
    input: z.object({
      file: z.string().optional().describe("Style guide file to read."),
      profile: values.optional(),
      from: z.string().optional().describe("Existing profile id to revise."),
      name: z.string().max(255).optional().describe("Name of the profile."),
    }),
    output: z.unknown(),
    examples: [
      { title: "Read a style guide", argv: "style-profiles propose --file ./style-guide.md --json" },
      { title: "Change a rule of the approved profile", argv: 'style-profiles propose --from <profile> --profile \'{"terminology":[{"use":"participant","avoid":["learner"]}]}\' --json' },
    ],
    plan: async (_ctx, i) => ({ note: i.file ? "Uploads the style guide." : "Creates the profile from the given values.", request: { method: "POST", path: BASE, body: { ...(i.file ? { file: i.file } : {}), ...(i.profile ? { profile: i.profile } : {}), ...(i.from ? { from: i.from } : {}), ...(i.name ? { name: i.name } : {}) } } }),
    async run(ctx, i) {
      if (!i.file && !i.profile && !i.from) throw new CliError("INPUT_INVALID", "Pass --file <style guide>, --profile <values> or --from <profile>.");
      if (i.file && (i.profile || i.from)) throw new CliError("INPUT_INVALID", "--file reads a style guide; --profile and --from write values yourself. Use one or the other.");
      if (i.file) {
        const file = await readStyleGuide(ctx, i.file);
        const form = new FormData();
        form.append("file", new Blob([file.bytes as BlobPart]), file.name);
        if (i.name) form.append("name", i.name);
        return { data: await builderCall(ctx, "POST", "/style-profiles", { form }) };
      }
      return { data: await builderCall(ctx, "POST", "/style-profiles", { body: { ...(i.profile ? { profile: i.profile } : {}), ...(i.from ? { from: i.from } : {}), ...(i.name ? { name: i.name } : {}) } }) };
    },
  }),
  defineCommand({
    ...common,
    id: "style-profiles.edit",
    summary: "Edit a suggested profile (values and name); invalid values are dropped and reported",
    description: "Only a suggestion can be edited, by its creator or an admin. An approved profile is revised with `style-profiles propose --from <profile>`.",
    kind: "write",
    scopes: WRITE,
    endpoints: [`PATCH ${BASE}/{profile}`],
    positionals: ["profile"],
    input: z.object({ profile, values: values.optional(), name: z.string().max(255).optional().describe("New name.") }),
    output: z.unknown(),
    examples: [{ title: "Rename", argv: 'style-profiles edit <profile> --name "Northwind v2" --json' }],
    plan: async (_ctx, i) => planOf("PATCH", `${BASE}/${i.profile}`, { ...(i.values ? { profile: i.values } : {}), ...(i.name ? { name: i.name } : {}) }),
    async run(ctx, i) {
      if (!i.values && !i.name) throw new CliError("INPUT_INVALID", "Pass --values <json> or --name <name>.");
      return { data: await builderCall(ctx, "PATCH", "/style-profiles/{profile}", { params: { profile: i.profile }, body: { ...(i.values ? { profile: i.values } : {}), ...(i.name ? { name: i.name } : {}) } }) };
    },
  }),
  defineCommand({
    ...common,
    id: "style-profiles.approve",
    summary: "Approve a suggested profile (admins); it replaces the approved profile of its scope",
    description: "Review the diff first with `style-profiles get`. An approved profile is what the builder reads for the courses it drafts afterwards; courses that exist are not changed.",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: [`POST ${BASE}/{profile}/approve`],
    positionals: ["profile"],
    input: z.object({ profile }),
    output: z.unknown(),
    examples: [{ title: "Approve", argv: "style-profiles approve <profile> --json" }],
    plan: async (_ctx, i) => planOf("POST", `${BASE}/${i.profile}/approve`),
    async run(ctx, i) {
      return { data: await builderCall(ctx, "POST", "/style-profiles/{profile}/approve", { params: { profile: i.profile } }) };
    },
  }),
  defineCommand({
    ...common,
    id: "style-profiles.delete",
    summary: "Delete a suggested or superseded profile",
    description: "An approved profile cannot be deleted (409): approve a revision to replace it.",
    kind: "destructive",
    idempotent: true,
    scopes: WRITE,
    endpoints: [`DELETE ${BASE}/{profile}`],
    positionals: ["profile"],
    input: z.object({ profile }),
    output: z.unknown(),
    examples: [{ title: "Delete a proposal", argv: "style-profiles delete <profile> --yes --json" }],
    plan: async (_ctx, i) => planOf("DELETE", `${BASE}/${i.profile}`),
    async run(ctx, i) {
      return { data: await builderCall(ctx, "DELETE", "/style-profiles/{profile}", { params: { profile: i.profile } }) };
    },
  }),
];
