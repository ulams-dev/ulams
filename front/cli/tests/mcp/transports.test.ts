import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { createServer, type Server } from "node:http";
import { mkdtempSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";
import { Client, StreamableHTTPClientTransport } from "@modelcontextprotocol/client";
import { StdioClientTransport } from "@modelcontextprotocol/client/stdio";
import { serveMcpHttp } from "../../src/mcp/server.ts";
import { getRegistry } from "../../src/registry/index.ts";
import { ctxFactory, defaults } from "./helpers.ts";
import { mockApi } from "../helpers.ts";

const dist = resolve(__dirname, "../../dist/ulams.mjs");

describe("Streamable HTTP", () => {
  const api = mockApi({
    "GET /api/profile/me": { body: { success: true, data: { id: 1, email: "a@x.test", roles: ["admin"], permissions: [] } } },
  });
  let handle: Awaited<ReturnType<typeof serveMcpHttp>>;

  beforeAll(async () => {
    handle = await serveMcpHttp(getRegistry(), ctxFactory(api.fetch), defaults, { host: "127.0.0.1", port: 0, allowRemote: false, stderr: () => undefined });
  });
  afterAll(async () => {
    await handle.close();
  });

  it("rejects a request without a bearer token with 401 and WWW-Authenticate", async () => {
    const res = await fetch(handle.url, { method: "POST", headers: { "content-type": "application/json", accept: "application/json, text/event-stream" }, body: "{}" });
    expect(res.status).toBe(401);
    expect(res.headers.get("www-authenticate")).toContain("Bearer");
    expect((await fetch(`http://127.0.0.1:${handle.port}/other`)).status).toBe(404);
  });

  it("serves tools to a client and forwards the caller's bearer token to the API", async () => {
    const client = new Client({ name: "http-client", version: "1" });
    await client.connect(new StreamableHTTPClientTransport(new URL(handle.url), { requestInit: { headers: { authorization: "Bearer caller-token-1" } } }));
    const tools = await client.listTools();
    expect(tools.tools.map((t) => t.name)).toContain("whoami");
    const r = await client.callTool({ name: "whoami", arguments: {} });
    expect(r.isError).toBeFalsy();
    expect(api.requests.at(-1)?.headers.authorization).toBe("Bearer caller-token-1");
    await client.close();
  });

  it("refuses to bind a non-loopback address without --allow-remote", async () => {
    await expect(serveMcpHttp(getRegistry(), ctxFactory(api.fetch), defaults, { host: "0.0.0.0", port: 0, allowRemote: false, stderr: () => undefined })).rejects.toMatchObject({ code: "INPUT_INVALID" });
  });
});

describe("stdio (built CLI)", () => {
  let fake: Server;
  let url = "";
  const seen: string[] = [];

  beforeAll(async () => {
    fake = createServer((req, res) => {
      seen.push(`${req.method} ${req.url} ${req.headers.authorization ?? ""} ${req.headers["x-ulams-client"] ?? ""}`);
      res.setHeader("content-type", "application/json");
      if (req.url?.startsWith("/api/profile/me")) res.end(JSON.stringify({ success: true, data: { id: 1, email: "a@x.test", roles: ["admin"], permissions: [] } }));
      else if (req.url?.startsWith("/api/admin/courses")) res.end(JSON.stringify({ success: true, data: [{ id: 1, title: "A" }], meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 } }));
      else {
        res.statusCode = 404;
        res.end("{}");
      }
    });
    await new Promise<void>((r) => fake.listen(0, "127.0.0.1", r));
    url = `http://127.0.0.1:${(fake.address() as { port: number }).port}`;
  });
  afterAll(() => void fake.close());

  /** Spawns `ulams mcp` and connects; a child that dies during start-up (a loaded CI runner) is retried on a fresh process. */
  async function connectStdio(env: Record<string, string>): Promise<Client> {
    let last: unknown;
    for (let attempt = 1; attempt <= 3; attempt++) {
      const client = new Client({ name: "stdio-client", version: "1" });
      const transport = new StdioClientTransport({ command: "node", args: [dist, "mcp"], env: { ...(process.env as Record<string, string>), ...env }, stderr: "pipe" });
      let stderr = "";
      transport.stderr?.on("data", (d: Buffer) => (stderr += d.toString()));
      try {
        await client.connect(transport);
        return client;
      } catch (e) {
        last = new Error(`${(e as Error).message} (attempt ${attempt}; stderr: ${stderr.trim() || "empty"})`);
        await client.close().catch(() => undefined);
        await new Promise((r) => setTimeout(r, 250 * attempt));
      }
    }
    throw last;
  }

  it("runs `ulams mcp`, lists tools and calls one against the API with the env credentials", async () => {
    const client = await connectStdio({ ULAMS_URL: url, ULAMS_TOKEN: "stdio-token", ULAMS_CONFIG_DIR: mkdtempSync(join(tmpdir(), "ulams-stdio-")) });
    expect((await client.listTools()).tools.length).toBeGreaterThan(10);
    const r = await client.callTool({ name: "courses_list", arguments: {} });
    expect(r.structuredContent).toMatchObject({ ok: true, data: [{ id: 1 }] });
    expect(seen.some((s) => s.includes("Bearer stdio-token") && s.endsWith(" mcp"))).toBe(true);
    await client.close();
  });

  it("exits with an auth error when there are no credentials", async () => {
    const client = new Client({ name: "stdio-client", version: "1" });
    const dir = mkdtempSync(join(tmpdir(), "ulams-stdio-"));
    writeFileSync(join(dir, "config.json"), JSON.stringify({ contract: 1, defaultProfile: null, profiles: {} }));
    const transport = new StdioClientTransport({ command: "node", args: [dist, "mcp"], env: { ...process.env, ULAMS_URL: url, ULAMS_TOKEN: "", ULAMS_CONFIG_DIR: dir }, stderr: "pipe" });
    await expect(client.connect(transport)).rejects.toBeTruthy();
  });
});
