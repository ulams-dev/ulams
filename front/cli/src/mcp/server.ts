import { createServer, type IncomingMessage, type ServerResponse } from "node:http";
import { McpServer, ResourceTemplate, createMcpHandler, type McpServerFactory } from "@modelcontextprotocol/server";
import { serveStdio } from "@modelcontextprotocol/server/stdio";
import { z } from "zod";
import { CliError } from "../errors.ts";
import { HttpClient } from "../http/client.ts";
import type { AnyCommand, Ctx } from "../registry/types.ts";
import { ConfirmTokens } from "./confirm.ts";
import { annotations, describeCommand, exposable, mcpName, runTool, searchCommands, selectTools, toolDescription, toolInput, visibleCommands, type McpToolOptions } from "./tools.ts";
import { registerResources } from "./resources.ts";

export interface McpServeOptions extends McpToolOptions {
  version: string;
}

/** Builds a Ctx for one MCP request: the token is the server's own (stdio) or the caller's bearer (HTTP). */
export type CtxFactory = (token: string | null, agent?: string) => Ctx;

function jsonText(value: unknown): { content: Array<{ type: "text"; text: string }>; structuredContent: Record<string, unknown> } {
  return { content: [{ type: "text", text: JSON.stringify(value) }], structuredContent: value as Record<string, unknown> };
}

export function buildServer(commands: AnyCommand[], makeCtx: CtxFactory, o: McpServeOptions, tokens: ConfirmTokens, bearer: string | null, agent?: string): McpServer {
  const server = new McpServer({ name: "ulams", version: o.version }, { instructions: "Tools for operating a ulams LMS instance. Call whoami first. Prefer dry_run before writes; destructive tools return a plan and a confirm token." });
  const ctx = () => makeCtx(bearer, agent);
  const tools = selectTools(commands, o);

  for (const cmd of tools) {
    server.registerTool(
      mcpName(cmd),
      {
        title: cmd.mcp?.title ?? cmd.summary,
        description: toolDescription(cmd),
        inputSchema: toolInput(cmd),
        annotations: annotations(cmd),
      },
      async (args: Record<string, unknown>) => runTool(cmd, args, ctx(), tokens, o)
    );
  }

  // Meta tools: reach every exposed command without listing hundreds of tools.
  const all = visibleCommands(commands, o);
  server.registerTool(
    "commands_search",
    {
      title: "Search commands",
      description: "Find ulams commands by keyword (course, quiz, user, enrol, ...). Returns ids to use with commands_describe and commands_run.",
      inputSchema: z.object({ query: z.string().describe("Keywords.") }),
      annotations: { readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false },
    },
    async ({ query }: { query: string }) => jsonText({ ok: true, data: searchCommands(all, query) })
  );
  server.registerTool(
    "commands_describe",
    {
      title: "Describe a command",
      description: "The input JSON Schema, kind and examples of one command id (from commands_search).",
      inputSchema: z.object({ id: z.string().describe("Command id, e.g. courses.create.") }),
      annotations: { readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false },
    },
    async ({ id }: { id: string }) => {
      const cmd = all.find((c) => c.id === id);
      return cmd ? jsonText({ ok: true, data: describeCommand(cmd) }) : { ...jsonText({ ok: false, error: { code: "NOT_FOUND", message: `No command ${id}`, hint: "Use commands_search." } }), isError: true };
    }
  );
  server.registerTool(
    "commands_run",
    {
      title: "Run a command",
      description: "Run any exposed command by id with its input (see commands_describe). Same dry_run and confirmation rules as the dedicated tools.",
      inputSchema: z.object({
        id: z.string().describe("Command id."),
        input: z.record(z.string(), z.unknown()).optional().describe("The command's input object."),
        dry_run: z.boolean().optional(),
        confirm: z.string().optional(),
      }),
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false },
    },
    async ({ id, input, dry_run, confirm }: { id: string; input?: Record<string, unknown>; dry_run?: boolean; confirm?: string }) => {
      const cmd = all.find((c) => c.id === id);
      if (!cmd) {
        return { ...jsonText({ ok: false, error: { code: "NOT_FOUND", message: `No command ${id}`, hint: "Use commands_search." } }), isError: true };
      }
      return runTool(cmd, { ...(input ?? {}), ...(dry_run !== undefined ? { dry_run } : {}), ...(confirm ? { confirm } : {}) }, ctx(), tokens, o);
    }
  );

  if (commands.some((c) => c.id === "courses.get")) {
    const read = commands.find((c) => c.id === "courses.get") as AnyCommand;
    const list = commands.find((c) => c.id === "courses.list") as AnyCommand | undefined;
    registerResources(server, { ctx, read, list, template: ResourceTemplate });
  }
  void exposable;
  return server;
}

function bearerOf(request: Request | undefined): string | null {
  const header = request?.headers.get("authorization") ?? "";
  const m = /^Bearer\s+(.+)$/i.exec(header);
  return m ? (m[1] as string) : null;
}

export function serveMcpStdio(commands: AnyCommand[], makeCtx: CtxFactory, o: McpServeOptions, token: string | null): { close(): Promise<void> } {
  const tokens = new ConfirmTokens();
  const factory: McpServerFactory = () => buildServer(commands, makeCtx, o, tokens, token, process.env.ULAMS_AGENT);
  return serveStdio(factory, { onerror: (e) => process.stderr.write(`[ulams mcp] ${e.message}\n`) });
}

export interface HttpServeOptions {
  host: string;
  port: number;
  allowRemote: boolean;
  stderr: (line: string) => void;
}

/** Streamable HTTP at /mcp. Every request must carry its own `Authorization: Bearer <ulams token>`. */
export async function serveMcpHttp(commands: AnyCommand[], makeCtx: CtxFactory, o: McpServeOptions, http: HttpServeOptions) {
  if (http.host !== "127.0.0.1" && http.host !== "localhost" && http.host !== "::1" && !http.allowRemote) {
    throw new CliError("INPUT_INVALID", `Refusing to bind ${http.host}: it exposes your ulams access to the network.`, { hint: "Pass --allow-remote to confirm, and put TLS and a firewall in front." });
  }
  if (http.host !== "127.0.0.1" && http.host !== "localhost" && http.host !== "::1") http.stderr(`warning: listening on ${http.host}; anyone who can reach this port and has a valid ulams token can use it.`);
  const tokens = new ConfirmTokens();
  const handler = createMcpHandler((rc) => buildServer(commands, makeCtx, o, tokens, bearerOf(rc.requestInfo), undefined));
  const server = createServer(async (req: IncomingMessage, res: ServerResponse) => {
    try {
      const url = new URL(req.url ?? "/", `http://${req.headers.host ?? "localhost"}`);
      if (url.pathname !== "/mcp") {
        res.writeHead(404, { "content-type": "application/json" }).end(JSON.stringify({ error: "not_found", hint: "The endpoint is /mcp." }));
        return;
      }
      const auth = req.headers.authorization ?? "";
      if (!/^Bearer\s+\S+/i.test(auth)) {
        res.writeHead(401, { "www-authenticate": 'Bearer realm="ulams"', "content-type": "application/json" }).end(JSON.stringify({ error: "unauthorized", message: "Send Authorization: Bearer <ulams token>." }));
        return;
      }
      const chunks: Buffer[] = [];
      for await (const c of req) chunks.push(c as Buffer);
      const body = chunks.length ? Buffer.concat(chunks) : undefined;
      const headers = new Headers();
      for (const [k, v] of Object.entries(req.headers)) if (v !== undefined) headers.set(k, Array.isArray(v) ? v.join(", ") : v);
      const request = new Request(url, { method: req.method, headers, ...(body && req.method !== "GET" && req.method !== "HEAD" ? { body } : {}) });
      const response = await handler.fetch(request);
      res.writeHead(response.status, Object.fromEntries(response.headers));
      if (response.body) {
        for await (const chunk of response.body as unknown as AsyncIterable<Uint8Array>) res.write(chunk);
      }
      res.end();
    } catch (error) {
      http.stderr(`[ulams mcp] ${(error as Error).message}`);
      if (!res.headersSent) res.writeHead(500, { "content-type": "application/json" });
      res.end(JSON.stringify({ error: "internal", message: (error as Error).message }));
    }
  });
  await new Promise<void>((resolve) => server.listen(http.port, http.host, resolve));
  const address = server.address();
  const port = typeof address === "object" && address ? address.port : http.port;
  return {
    port,
    url: `http://${http.host}:${port}/mcp`,
    close: () => new Promise<void>((resolve) => server.close(() => void handler.close().then(() => resolve()))),
  };
}

export { HttpClient };
