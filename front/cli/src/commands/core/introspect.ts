import { z } from "zod";
import { CliError } from "../../errors.ts";
import { defineCommand } from "../define.ts";
import { getRegistry } from "../../registry/index.ts";
import { exportCommand, exportRegistry } from "../../registry/schema-export.ts";
import { suggest } from "../../cli/help.ts";

export const schemaCommand = defineCommand({
  id: "schema",
  summary:
    "Print every command as JSON: inputs, outputs, kinds, scopes, examples, exit codes",
  description:
    "Works without credentials. Agents should read this once to discover the CLI; `ulams describe <command>` shows a single command.",
  kind: "local",
  idempotent: true,
  anonymous: true,
  mcp: { expose: false },
  input: z.object({
    command: z
      .string()
      .optional()
      .describe("Only this command id, e.g. courses.list."),
  }),
  output: z.unknown(),
  examples: [
    { title: "Whole registry", argv: "schema --json" },
    { title: "One command", argv: "schema --command courses.list" },
  ],
  async run(ctx, i) {
    const commands = getRegistry();
    if (i.command) {
      const cmd = commands.find((c) => c.id === i.command);
      if (!cmd)
        throw new CliError("NOT_FOUND", `No command "${i.command}".`, {
          hint: "Run `ulams schema --fields commands.id`.",
        });
      return { data: exportCommand(cmd) };
    }
    return { data: exportRegistry(commands, ctx.version) };
  },
});

export const describeCommand = defineCommand({
  id: "describe",
  summary: "Describe one command: flags, JSON Schema, examples, scopes, kind",
  kind: "local",
  idempotent: true,
  anonymous: true,
  mcp: { expose: false },
  positionals: ["command"],
  input: z.object({
    command: z
      .string()
      .describe("Command id (courses.list) or path (courses list)."),
  }),
  output: z.unknown(),
  examples: [
    { title: "Describe courses create", argv: "describe courses.create" },
  ],
  async run(ctx, i) {
    const commands = getRegistry();
    const wanted = i.command.trim().replace(/\s+/g, ".");
    const cmd = commands.find((c) => c.id === wanted);
    if (!cmd) {
      const near = suggest(i.command.split(/[\s.]+/), commands);
      throw new CliError("NOT_FOUND", `No command "${i.command}".`, {
        hint: near.length
          ? `Did you mean: ${near.join(", ")}?`
          : "Run `ulams schema` to list commands.",
      });
    }
    void ctx;
    return { data: exportCommand(cmd) };
  },
});

export const version = defineCommand({
  id: "version",
  summary: "Print the CLI version and the output contract version",
  kind: "local",
  idempotent: true,
  anonymous: true,
  mcp: { expose: false },
  input: z.object({}),
  output: z.object({ version: z.string(), contract: z.number() }),
  examples: [{ title: "Version", argv: "version" }],
  async run(ctx) {
    return {
      data: { version: ctx.version, contract: 1, node: process.version },
    };
  },
});
