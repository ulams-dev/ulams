import { z } from "zod";
import { CliError } from "../errors.ts";
import { HttpClient } from "../http/client.ts";
import { getRegistry } from "../registry/index.ts";
import type { Ctx, FsPort } from "../registry/types.ts";
import { serveMcpHttp, serveMcpStdio, type CtxFactory } from "../mcp/server.ts";
import { defineCommand } from "./define.ts";

const denyFs: FsPort = {
  readFile: async () => {
    throw new CliError("FORBIDDEN", "File access is disabled in HTTP MCP mode.");
  },
  readText: async () => {
    throw new CliError("FORBIDDEN", "File access is disabled in HTTP MCP mode.");
  },
  writeFile: async () => {
    throw new CliError("FORBIDDEN", "File access is disabled in HTTP MCP mode.");
  },
  exists: async () => false,
};

export const mcp = defineCommand({
  id: "mcp",
  summary: "Run an MCP server that exposes the ulams commands as tools (stdio, or Streamable HTTP with --http)",
  description:
    "stdio (default) uses the credentials of the current profile or ULAMS_URL/ULAMS_TOKEN; stdout carries only the protocol. --http serves /mcp on loopback; each request must send Authorization: Bearer <ulams token>. Destructive tools return a plan and a confirm token first. Default toolset: core (plus commands_search, commands_describe, commands_run).",
  kind: "local",
  idempotent: false,
  anonymous: true,
  mcp: { expose: false },
  input: z.object({
    http: z.boolean().optional().describe("Serve Streamable HTTP instead of stdio."),
    host: z.string().optional().describe("HTTP bind address (default 127.0.0.1)."),
    port: z.number().int().optional().describe("HTTP port (default 8787)."),
    allowRemote: z.boolean().optional().describe("Allow binding a non-loopback address."),
    toolsets: z.string().optional().describe("Comma-separated toolsets (core, courses, users, ...) or all. Default core."),
    readOnly: z.boolean().optional().describe("Expose read commands only."),
    noDestructive: z.boolean().optional().describe("Hide destructive commands."),
    unsafeYes: z.boolean().optional().describe("Skip confirmation of destructive tools (sandboxes only)."),
  }),
  output: z.unknown(),
  examples: [
    { title: "Claude Code (stdio)", argv: "mcp --profile coffee" },
    { title: "Read-only, courses and users toolsets", argv: "mcp --read-only --toolsets core,courses,users" },
    { title: "Streamable HTTP on loopback", argv: "mcp --http --port 8787" },
  ],
  async run(ctx, i) {
    const commands = getRegistry();
    const sets = i.toolsets ? (i.toolsets === "all" ? "all" : i.toolsets.split(",").map((s) => s.trim()).filter(Boolean)) : ["core"];
    const options = { toolsets: sets as string[] | "all", readOnly: Boolean(i.readOnly), noDestructive: Boolean(i.noDestructive), yes: Boolean(i.unsafeYes), version: ctx.version };
    if (!ctx.profile.url) {
      throw new CliError("AUTH_REQUIRED", "ulams mcp needs an instance URL.", { hint: "Run `ulams login --url <origin>` first, or pass --url and set ULAMS_TOKEN." });
    }
    const url = ctx.profile.url;
    const makeCtx: CtxFactory = (token, agent): Ctx => ({
      ...ctx,
      client: new HttpClient({
        baseUrl: url,
        token,
        ...(ctx.client.fetchImpl ? { fetch: ctx.client.fetchImpl } : {}),
        userAgent: ctx.client.userAgent,
        client: "mcp",
        agent: agent ?? ctx.env.ULAMS_AGENT,
      }),
      profile: { ...ctx.profile, token },
      fs: i.http ? denyFs : ctx.fs,
      readStdin: async () => "",
      io: { stderr: (l) => ctx.io.stderr(l), isTTY: false, interactive: false },
      flags: { ...ctx.flags, json: true, wait: true },
    });

    if (i.http) {
      const handle = await serveMcpHttp(commands, makeCtx, options, {
        host: i.host ?? "127.0.0.1",
        port: i.port ?? 8787,
        allowRemote: Boolean(i.allowRemote),
        stderr: (l) => ctx.io.stderr(l),
      });
      ctx.io.stderr(`ulams mcp listening on ${handle.url} (instance ${url})`);
      await new Promise<void>((resolve) => {
        ctx.signal.addEventListener("abort", () => resolve());
      });
      await handle.close();
      return { data: null, handled: true } as never;
    }

    if (!ctx.profile.token) {
      throw new CliError("AUTH_REQUIRED", `Not logged in to ${url}.`, { hint: `Run \`ulams login --url ${url}\` or set ULAMS_TOKEN.` });
    }
    const handle = serveMcpStdio(commands, makeCtx, options, ctx.profile.token);
    await new Promise<void>((resolve) => {
      process.stdin.once("end", () => resolve());
      process.stdin.once("close", () => resolve());
      ctx.signal.addEventListener("abort", () => resolve());
    });
    await handle.close();
    return { data: null, handled: true } as never;
  },
});
