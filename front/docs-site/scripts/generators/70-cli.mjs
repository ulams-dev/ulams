// CLI reference generated from the command registry: runs `ulams schema --json` from the built CLI
// (front/cli, built here when dist/ is missing) and writes one page per noun plus the index.
import { spawnSync } from "node:child_process";
import { existsSync } from "node:fs";
import { join } from "node:path";
import { ROOT, cell, writePage } from "../lib.mjs";

const dist = join(ROOT, "front/cli/dist/ulams.mjs");

function registry() {
  if (!existsSync(dist)) {
    const build = spawnSync("corepack", ["yarn", "workspace", "ulams", "build"], { cwd: ROOT, stdio: "inherit" });
    if (build.status !== 0) throw new Error("Could not build front/cli for the CLI reference");
  }
  const run = spawnSync("node", [dist, "schema", "--json"], { cwd: ROOT, encoding: "utf8", env: { ...process.env, ULAMS_CONFIG_DIR: join(ROOT, "front/cli/.docs-config") } });
  if (run.status !== 0) throw new Error(`ulams schema failed: ${run.stderr || run.stdout}`);
  return JSON.parse(run.stdout).data;
}

const code = (s) => `\`${String(s).replace(/`/g, "'")}\``;

function flagTable(cmd) {
  if (!cmd.flags.length) return "";
  const rows = cmd.flags.map(
    (f) => `| ${code(f.flag)}${f.positional ? " (positional)" : ""} | ${f.type} | ${f.required ? "yes" : ""} | ${cell(f.description)} |`
  );
  return `| Flag | Type | Required | Description |\n|---|---|---|---|\n${rows.join("\n")}`;
}

export default function generate() {
  const reg = registry();
  const nouns = new Map();
  for (const cmd of reg.commands) {
    const noun = cmd.id.split(".")[0];
    nouns.set(noun, [...(nouns.get(noun) ?? []), cmd]);
  }
  const written = [];
  let order = 10;
  for (const [noun, cmds] of [...nouns].sort(([a], [b]) => a.localeCompare(b))) {
    const body = cmds
      .map((cmd) => {
        const lines = [`## ${code(`ulams ${cmd.path}`)}`, "", cmd.summary + (cmd.summary.endsWith(".") ? "" : ".")];
        if (cmd.description) lines.push("", cmd.description);
        lines.push(
          "",
          `Kind: **${cmd.kind}**${cmd.idempotent ? " (idempotent)" : ""}. Stability: ${cmd.stability}. MCP tool: ${code(cmd.mcp.tool)}${cmd.mcp.expose ? "" : " (not exposed)"}. Scopes: ${cmd.scopes.length ? cmd.scopes.map(code).join(", ") : "none"}.`
        );
        if (cmd.endpoints.length) lines.push("", `Endpoints: ${cmd.endpoints.map(code).join(", ")}.`);
        const flags = flagTable(cmd);
        if (flags) lines.push("", flags);
        if (cmd.examples.length) {
          lines.push("", "```bash", ...cmd.examples.flatMap((e) => [`# ${e.title}`, `ulams ${e.argv}`]), "```");
        }
        lines.push("", "<details><summary>Input JSON Schema</summary>", "", "```json", JSON.stringify(cmd.input, null, 2), "```", "", "</details>");
        return lines.join("\n");
      })
      .join("\n\n");
    written.push(
      writePage(
        `reference/cli/${noun}.md`,
        {
          title: `ulams ${noun}`,
          description: `Reference for the ulams ${noun} command: ${cmds.length} command(s), generated from the CLI command registry.`,
          generatedFrom: "front/cli (ulams schema)",
          editUrl: false,
          sidebar: { order: order++ },
        },
        body
      )
    );
  }

  const globals = reg.globals.map((g) => `| ${code(g.flag)} | ${g.env ? code(g.env) : ""} | ${cell(g.description)} |`).join("\n");
  const exits = reg.exitCodes.map((e) => `| ${e.exit} | ${code(e.code)} | ${cell(e.hint)} |`).join("\n");
  written.push(
    writePage(
      "reference/cli/index.md",
      {
        title: "CLI reference",
        description: `Reference for the ulams CLI ${reg.cliVersion}: ${reg.commands.length} commands, global flags, the JSON envelope and exit codes, generated from the command registry.`,
        generatedFrom: "front/cli (ulams schema)",
        editUrl: false,
        sidebar: { label: "CLI", order: 8 },
      },
      `Generated from the CLI's command registry (\`ulams schema\`), so it always matches the binary. Guides: [Using the CLI](/developers/cli/), [Use ulams from Claude Code](/developers/agents/).

## Commands

${[...nouns].sort(([a], [b]) => a.localeCompare(b)).map(([noun, cmds]) => `- [${code(`ulams ${noun}`)}](/reference/cli/${noun}/): ${cmds.length} command(s)`).join("\n")}

## Global flags

| Flag | Environment | Description |
|---|---|---|
${globals}

## Output envelope (contract ${reg.contract})

In JSON mode stdout holds exactly one document:

\`\`\`json
{ "ok": true, "contract": 1, "command": "courses.list", "data": [], "meta": { "page": 1, "perPage": 25, "total": 0, "lastPage": 1, "nextPage": null } }
{ "ok": false, "contract": 1, "command": "courses.get", "error": { "code": "NOT_FOUND", "message": "…", "hint": "…", "status": 404, "retryable": false, "requestId": "…", "details": {} } }
\`\`\`

## Exit codes

| Exit | Error code | Default hint |
|---|---|---|
${exits}
`
    )
  );
  return written;
}
