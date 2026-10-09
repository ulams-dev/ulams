#!/usr/bin/env node
// Agent eval (plan 12.5): a real model completes scripted tasks through `ulams mcp`; results are checked via the API.
import { spawnSync } from "node:child_process";
import { mkdirSync, mkdtempSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { Client } from "@modelcontextprotocol/client";
import { StdioClientTransport } from "@modelcontextprotocol/client/stdio";
import { COURSE, tasks } from "./tasks.mjs";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const dist = resolve(root, "dist/ulams.mjs");
const URL_ = process.env.ULAMS_EVAL_URL ?? "http://coffee.localhost";
const BUDGET = Number(process.env.ULAMS_EVAL_BUDGET_USD ?? 1);
const PRICE_IN = Number(process.env.ULAMS_EVAL_PRICE_IN ?? 3);
const PRICE_OUT = Number(process.env.ULAMS_EVAL_PRICE_OUT ?? 15);
const MAX_CALLS = 30;

function tinker(expr) {
  const compose = process.env.ULAMS_API_COMPOSE ?? "/Users/mateuszwojczal/Desktop/localhost/ulams/api/docker-compose.yml";
  const r = spawnSync("docker", ["compose", "-f", compose, "exec", "-T", "api", "php", "artisan", "tinker", "--execute", `echo ${expr};`], { encoding: "utf8" });
  return r.status === 0 ? r.stdout.trim().split("\n").pop().trim() : "";
}

const apiKey = process.env.ANTHROPIC_API_KEY || tinker('config("ai.api_key")');
if (!apiKey) {
  console.error("eval: no API key reachable (set ANTHROPIC_API_KEY or run the API stack). Skipping.");
  process.exit(3);
}
const model = process.env.ULAMS_EVAL_MODEL || tinker('config("ai.profiles.light.model")');
if (!model) {
  console.error("eval: no model configured (set ULAMS_EVAL_MODEL).");
  process.exit(3);
}

const configDir = mkdtempSync(join(tmpdir(), "ulams-eval-"));
const env = { ...process.env, ULAMS_CONFIG_DIR: configDir, ULAMS_URL: "", ULAMS_TOKEN: "" };
function ulams(args) {
  const r = spawnSync("node", [dist, ...args, "--json"], { encoding: "utf8", env });
  try {
    return JSON.parse(r.stdout.trim().split("\n")[0]);
  } catch {
    return { ok: false, error: { message: r.stderr } };
  }
}
if (ulams(["login", "--url", URL_, "--demo", "admin"]).ok !== true) {
  console.error("eval: demo login failed");
  process.exit(1);
}

const client = new Client({ name: "ulams-eval", version: "1" });
await client.connect(new StdioClientTransport({ command: "node", args: [dist, "mcp", "--toolsets", "core,access,courses"], env, stderr: "pipe" }));
const mcpTools = (await client.listTools()).tools;
const tools = mcpTools.map((t) => ({ name: t.name, description: (t.description ?? "").slice(0, 900), input_schema: t.inputSchema }));

let spent = 0;
let inTok = 0;
let outTok = 0;

async function messages(body) {
  const res = await fetch("https://api.anthropic.com/v1/messages", {
    method: "POST",
    headers: { "x-api-key": apiKey, "anthropic-version": "2023-06-01", "content-type": "application/json" },
    body: JSON.stringify(body),
  });
  const json = await res.json();
  if (!res.ok) throw new Error(`Anthropic API ${res.status}: ${json?.error?.type ?? "error"}`);
  inTok += json.usage?.input_tokens ?? 0;
  outTok += json.usage?.output_tokens ?? 0;
  spent = (inTok * PRICE_IN + outTok * PRICE_OUT) / 1_000_000;
  return json;
}

const system = "You operate a ulams learning platform through tools. Call whoami first if unsure. Use dry_run before writes when helpful. Destructive tools return a plan and a confirm token: show the plan in your reply text, then call again with the confirm token. Finish with a short answer.";

async function runTask(task) {
  const trace = [];
  const started = Date.now();
  const convo = [{ role: "user", content: task.prompt }];
  let calls = 0;
  let answer = "";
  let note = "";
  while (true) {
    if (spent >= BUDGET) {
      note = "budget reached";
      break;
    }
    const res = await messages({ model, max_tokens: 2048, system, tools, messages: convo });
    convo.push({ role: "assistant", content: res.content });
    const uses = res.content.filter((b) => b.type === "tool_use");
    answer = res.content.filter((b) => b.type === "text").map((b) => b.text).join("\n") || answer;
    if (uses.length === 0 || res.stop_reason !== "tool_use") break;
    const results = [];
    for (const use of uses) {
      if (++calls > MAX_CALLS) {
        note = `more than ${MAX_CALLS} tool calls`;
        results.push({ type: "tool_result", tool_use_id: use.id, content: "Tool call limit reached.", is_error: true });
        continue;
      }
      const r = await client.callTool({ name: use.name, arguments: use.input });
      const text = r.content?.[0]?.text ?? "";
      let code;
      try {
        code = JSON.parse(text)?.error?.code;
      } catch {
        /* not JSON */
      }
      trace.push({ tool: use.name, error: code ?? (r.isError ? "ERROR" : null) });
      results.push({ type: "tool_result", tool_use_id: use.id, content: text.slice(0, 20000), is_error: Boolean(r.isError) });
    }
    convo.push({ role: "user", content: results });
    if (note) break;
  }
  let failure = note || null;
  if (!failure) failure = await task.check({ ulams: async (a) => ulams(a), answer, trace });
  return { id: task.id, pass: !failure, failure, calls, errors: trace.filter((t) => t.error).length, seconds: Math.round((Date.now() - started) / 1000), answer: answer.slice(0, 300) };
}

const results = [];
try {
  for (const task of tasks) results.push(await runTask(task));
} finally {
  await client.close();
  // Leave nothing behind, whatever the agent did.
  const left = (ulams(["courses", "list", "--title", COURSE]).data ?? []).filter((c) => c.title === COURSE);
  for (const c of left) ulams(["courses", "delete", String(c.id), "--yes"]);
}

const date = new Date().toISOString().slice(0, 10);
mkdirSync(join(root, "evals/reports"), { recursive: true });
const passed = results.filter((r) => r.pass).length;
const md = [
  `# Agent eval ${date}`,
  "",
  `Mode: MCP (stdio, toolsets core,access,courses). Model: \`${model}\` (from the API's AI config). Instance: ${URL_}.`,
  "",
  `**${passed}/${results.length} tasks passed.** Tokens: ${inTok} in, ${outTok} out. Estimated cost: USD ${spent.toFixed(3)} (budget ${BUDGET}, prices ${PRICE_IN}/${PRICE_OUT} per million tokens).`,
  "",
  "| Task | Result | Tool calls | Errors | Seconds | Note |",
  "|---|---|---|---|---|---|",
  ...results.map((r) => `| ${r.id} | ${r.pass ? "pass" : "FAIL"} | ${r.calls} | ${r.errors} | ${r.seconds} | ${r.failure ?? ""} |`),
  "",
].join("\n");
writeFileSync(join(root, `evals/reports/${date}.md`), md);
console.log(md);
process.exit(passed === results.length ? 0 : 1);
