import { mkdtempSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { main, nodeFs, type Deps } from "../src/cli/run.ts";
import type { FsPort } from "../src/registry/types.ts";

export interface RecordedRequest {
  method: string;
  url: string;
  path: string;
  query: Record<string, string>;
  headers: Record<string, string>;
  body: unknown;
  form?: FormData;
}

export type Reply = { status?: number; body?: unknown; headers?: Record<string, string>; raw?: string };
export type Handler = Reply | ((req: RecordedRequest) => Reply | Promise<Reply>);

/** A fetch stub keyed by "METHOD /path". */
export function mockApi(routes: Record<string, Handler>) {
  const requests: RecordedRequest[] = [];
  const fetchStub: typeof fetch = async (input, init) => {
    const url = new URL(String(input));
    const headers = Object.fromEntries(Object.entries((init?.headers ?? {}) as Record<string, string>).map(([k, v]) => [k.toLowerCase(), v]));
    let body: unknown = undefined;
    let form: FormData | undefined;
    if (typeof init?.body === "string") body = JSON.parse(init.body);
    else if (init?.body instanceof FormData) form = init.body;
    const req: RecordedRequest = {
      method: String(init?.method ?? "GET"),
      url: url.toString(),
      path: url.pathname,
      query: Object.fromEntries(url.searchParams),
      headers,
      body,
      ...(form ? { form } : {}),
    };
    requests.push(req);
    const handler = routes[`${req.method} ${req.path}`] ?? routes["*"];
    if (!handler) return new Response(JSON.stringify({ success: false, message: `no mock for ${req.method} ${req.path}` }), { status: 404 });
    const reply = typeof handler === "function" ? await handler(req) : handler;
    const text = reply.raw ?? JSON.stringify(reply.body ?? { success: true, data: null });
    return new Response(text, { status: reply.status ?? 200, headers: { "content-type": "application/json", ...reply.headers } });
  };
  return { fetch: fetchStub, requests };
}

export interface RunOptions {
  routes?: Record<string, Handler>;
  env?: Record<string, string>;
  stdin?: string;
  tty?: boolean;
  files?: Record<string, string>;
  configDir?: string;
  fs?: FsPort;
}

export async function runCli(argv: string[], options: RunOptions = {}) {
  const api = mockApi(options.routes ?? {});
  const configDir = options.configDir ?? mkdtempSync(join(tmpdir(), "ulams-cli-test-"));
  let stdout = "";
  let stderr = "";
  const files = options.files ?? {};
  const fs: FsPort = options.fs ?? {
    ...nodeFs,
    readText: async (p) => {
      if (p in files) return files[p] as string;
      return nodeFs.readText(p);
    },
    readFile: async (p) => {
      if (p in files) return new TextEncoder().encode(files[p]);
      return nodeFs.readFile(p);
    },
  };
  const deps: Deps = {
    argv,
    env: { ...options.env },
    stdout: (t) => void (stdout += `${t}\n`),
    stderr: (t) => void (stderr += `${t}\n`),
    stdinIsTTY: Boolean(options.tty),
    stdoutIsTTY: Boolean(options.tty),
    stderrIsTTY: Boolean(options.tty),
    readStdin: async () => options.stdin ?? "",
    fetch: api.fetch,
    fs,
    configDir,
  };
  const code = await main(deps);
  const json = (): Record<string, unknown> => JSON.parse(stdout.trim().split("\n")[0] ?? "null") as Record<string, unknown>;
  return { code, stdout, stderr, requests: api.requests, configDir, json, cleanup: () => rmSync(configDir, { recursive: true, force: true }) };
}

export const ME = {
  success: true,
  data: { id: 1, name: "Root Admin", email: "admin@coffee.ulams.app", roles: ["admin"], permissions: ["a", "b"] },
};
export const LOGIN = { success: true, data: { token: "eyJhbGciOiJSUzI1NiJ9.eyJzdWIiOiIxIn0.c2lnbmF0dXJl-secret", expires_at: "2026-11-09T00:00:00Z" } };
