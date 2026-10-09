import { z } from "zod";
import { CliError } from "../../errors.ts";
import { defineCommand } from "../define.ts";

const common = { kind: "local" as const, anonymous: true, mcp: { expose: false }, audience: ["any" as const] };

export const profilesList = defineCommand({
  ...common,
  id: "profiles.list",
  summary: "List saved profiles",
  idempotent: true,
  input: z.object({}),
  output: z.array(z.unknown()),
  examples: [{ title: "List profiles", argv: "profiles list" }],
  async run(ctx) {
    const config = ctx.store.read();
    const rows = Object.entries(config.profiles).map(([name, p]) => ({
      name,
      url: p.url,
      user: p.user ?? null,
      kind: p.kind,
      default: config.defaultProfile === name,
      expiresAt: p.expiresAt ?? null,
    }));
    return { data: rows };
  },
});

export const profilesUse = defineCommand({
  ...common,
  id: "profiles.use",
  summary: "Make a profile the default",
  idempotent: true,
  input: z.object({ name: z.string().describe("Profile name from `ulams profiles list`.") }),
  positionals: ["name"],
  output: z.unknown(),
  examples: [{ title: "Switch to coffee", argv: "profiles use coffee" }],
  async run(ctx, i) {
    const config = ctx.store.read();
    if (!config.profiles[i.name]) {
      throw new CliError("NOT_FOUND", `No profile named "${i.name}".`, { hint: "Run `ulams profiles list`." });
    }
    config.defaultProfile = i.name;
    ctx.store.write(config);
    return { data: { default: i.name } };
  },
});

export const profilesDelete = defineCommand({
  ...common,
  id: "profiles.delete",
  summary: "Delete a profile and its stored token",
  idempotent: true,
  input: z.object({ name: z.string().describe("Profile name.") }),
  positionals: ["name"],
  output: z.unknown(),
  examples: [{ title: "Delete a profile", argv: "profiles delete coffee" }],
  async run(ctx, i) {
    if (!ctx.store.removeProfile(i.name)) {
      throw new CliError("NOT_FOUND", `No profile named "${i.name}".`, { hint: "Run `ulams profiles list`." });
    }
    return { data: { deleted: i.name } };
  },
});

const CONFIG_KEYS = ["defaultOutput", "color"] as const;

export const configGet = defineCommand({
  ...common,
  id: "config.get",
  summary: "Read a CLI setting (defaultOutput, color)",
  idempotent: true,
  input: z.object({ key: z.enum(CONFIG_KEYS).optional().describe("Setting name; omit for all.") }),
  positionals: ["key"],
  output: z.unknown(),
  examples: [{ title: "All settings", argv: "config get" }],
  async run(ctx, i) {
    const config = ctx.store.read();
    const all = { defaultOutput: config.defaultOutput ?? "auto", color: config.color ?? true };
    return { data: i.key ? { [i.key]: all[i.key] } : all };
  },
});

export const configSet = defineCommand({
  ...common,
  id: "config.set",
  summary: "Change a CLI setting (defaultOutput, color)",
  idempotent: true,
  input: z.object({ key: z.enum(CONFIG_KEYS), value: z.string() }),
  positionals: ["key", "value"],
  output: z.unknown(),
  examples: [{ title: "Always print JSON", argv: "config set defaultOutput json" }],
  async run(ctx, i) {
    const config = ctx.store.read();
    if (i.key === "color") config.color = i.value !== "false";
    else {
      if (!["auto", "json", "ndjson", "yaml", "table", "text"].includes(i.value)) {
        throw new CliError("INPUT_INVALID", "defaultOutput must be auto, json, ndjson, yaml, table or text.");
      }
      config.defaultOutput = i.value;
    }
    ctx.store.write(config);
    return { data: { [i.key]: i.key === "color" ? config.color : config.defaultOutput } };
  },
});
