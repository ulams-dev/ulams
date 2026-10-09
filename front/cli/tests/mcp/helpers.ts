import { mkdtempSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { Client } from "@modelcontextprotocol/client";
import { InMemoryTransport } from "@modelcontextprotocol/server";
import { ConfigStore } from "../../src/config/profiles.ts";
import { HttpClient } from "../../src/http/client.ts";
import { nodeFs } from "../../src/cli/run.ts";
import { ConfirmTokens } from "../../src/mcp/confirm.ts";
import { buildServer, type CtxFactory, type McpServeOptions } from "../../src/mcp/server.ts";
import { getRegistry } from "../../src/registry/index.ts";
import type { Ctx, GlobalFlags } from "../../src/registry/types.ts";
import { mockApi, type Handler } from "../helpers.ts";

export const flags: GlobalFlags = { json: true, output: "json", quiet: true, noColor: true, dryRun: false, yes: false, wait: true, timeout: 600, all: false, debug: false, interactive: false, tokenStdin: false };

export function ctxFactory(fetch: typeof globalThis.fetch): CtxFactory {
  const store = new ConfigStore(mkdtempSync(join(tmpdir(), "ulams-mcp-")));
  return (token, agent): Ctx => ({
    client: new HttpClient({ baseUrl: "http://coffee.localhost", token, fetch, userAgent: "test", client: "mcp", agent }),
    profile: { name: null, url: "http://coffee.localhost", kind: "tenant", token, tokenSource: "env" },
    fs: nodeFs,
    io: { stderr: () => undefined, isTTY: false, interactive: false },
    signal: new AbortController().signal,
    emit: () => undefined,
    flags,
    env: {},
    readStdin: async () => "",
    prompt: async () => "",
    version: "test",
    store,
  });
}

export const defaults: McpServeOptions = { toolsets: ["core"], readOnly: false, noDestructive: false, yes: false, version: "test" };

export async function connect(routes: Record<string, Handler>, options: Partial<McpServeOptions> = {}, token: string | null = "tok-123456") {
  const api = mockApi(routes);
  const server = buildServer(getRegistry(), ctxFactory(api.fetch), { ...defaults, ...options }, new ConfirmTokens(), token, "test-agent");
  const [a, b] = InMemoryTransport.createLinkedPair();
  await server.connect(a);
  const client = new Client({ name: "test-client", version: "1" });
  await client.connect(b);
  return { client, api, close: () => client.close() };
}

export const text = (result: unknown): Record<string, any> => // eslint-disable-line @typescript-eslint/no-explicit-any
  JSON.parse(((result as { content: Array<{ text: string }> }).content[0] as { text: string }).text);
