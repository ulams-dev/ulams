import type { AnyCommand } from "../registry/types.ts";
import { commandPath, exportCommand, GLOBAL_FLAG_DOCS } from "../registry/schema-export.ts";

export function rootHelp(commands: AnyCommand[], version: string): string {
  const nouns = new Map<string, AnyCommand[]>();
  for (const cmd of commands) {
    const noun = commandPath(cmd)[0] as string;
    nouns.set(noun, [...(nouns.get(noun) ?? []), cmd]);
  }
  const lines = [
    `ulams ${version}: command line and MCP server for ulams instances`,
    "",
    "Usage: ulams <command> [subcommand] [flags]",
    "",
    "Commands:",
  ];
  for (const [noun, cmds] of [...nouns].sort(([a], [b]) => a.localeCompare(b))) {
    const single = cmds.length === 1 && commandPath(cmds[0] as AnyCommand).length === 1;
    if (single) lines.push(`  ${noun.padEnd(16)}${(cmds[0] as AnyCommand).summary}`);
    else {
      const verbs = cmds.map((c) => commandPath(c).slice(1).join(" ")).sort();
      const shown = verbs.length > 8 ? `${verbs.slice(0, 8).join(", ")}, ...` : verbs.join(", ");
      lines.push(`  ${noun.padEnd(16)}${shown}`);
    }
  }
  lines.push(
    "",
    "Agents: use --json (or pipe stdout) and --input @file.json. `ulams schema` lists every command as JSON;",
    "`ulams describe <command>` shows one. Exit codes and the envelope are documented in `ulams schema`.",
    "",
    "Global flags:",
    ...GLOBAL_FLAG_DOCS.map((g) => `  ${g.flag.padEnd(38)}${g.description}`)
  );
  return lines.join("\n");
}

export function nounHelp(noun: string, commands: AnyCommand[]): string {
  const cmds = commands.filter((c) => commandPath(c)[0] === noun);
  const lines = [`ulams ${noun}`, ""];
  for (const cmd of cmds.sort((a, b) => a.id.localeCompare(b.id))) {
    lines.push(`  ${commandPath(cmd).join(" ").padEnd(28)}${cmd.summary}`);
  }
  lines.push("", `Run \`ulams ${noun} <command> --help\` for flags and examples.`);
  return lines.join("\n");
}

export function commandHelp(cmd: AnyCommand): string {
  const info = exportCommand(cmd);
  const positionals = info.positionals.map((p) => ` <${p.replace(/([A-Z])/g, "-$1").toLowerCase()}>`).join("");
  const lines = [`ulams ${info.path}${positionals} [flags]`, "", cmd.summary];
  if (cmd.description) lines.push("", cmd.description);
  lines.push("", `Kind: ${cmd.kind}${cmd.idempotent ? ", idempotent" : ""}. Scopes: ${cmd.scopes.join(", ") || "none"}. MCP tool: ${info.mcp.tool}.`);
  if (info.flags.length) {
    lines.push("", "Flags:");
    for (const f of info.flags) {
      const mark = f.required ? " (required)" : "";
      lines.push(`  ${f.flag.padEnd(26)}${f.type.padEnd(9)}${f.description}${mark}`);
    }
  }
  lines.push("", "Also: --json, --input <json|@file|->, --set path=value, --dry-run, --fields, --yes (see `ulams --help`).");
  if (cmd.examples.length) {
    lines.push("", "Examples:");
    for (const ex of cmd.examples) lines.push(`  # ${ex.title}`, `  ulams ${ex.argv}`);
  }
  return lines.join("\n");
}

/** Closest command paths for "did you mean". */
export function suggest(words: string[], commands: AnyCommand[]): string[] {
  const first = words[0];
  if (!first) return [];
  const paths = commands.map((c) => commandPath(c).join(" "));
  const scored = paths
    .map((p) => ({ p, d: distance(first, p.split(" ")[0] as string) + (words[1] && p.startsWith(`${first} `) ? distance(words[1], p.split(" ")[1] ?? "") : 0) }))
    .sort((a, b) => a.d - b.d);
  return scored.filter((s) => s.d <= 3).slice(0, 3).map((s) => s.p);
}

function distance(a: string, b: string): number {
  const dp = Array.from({ length: a.length + 1 }, (_, i) => [i, ...Array<number>(b.length).fill(0)]);
  for (let j = 0; j <= b.length; j++) (dp[0] as number[])[j] = j;
  for (let i = 1; i <= a.length; i++)
    for (let j = 1; j <= b.length; j++)
      (dp[i] as number[])[j] = Math.min(
        (dp[i - 1] as number[])[j]! + 1,
        (dp[i] as number[])[j - 1]! + 1,
        (dp[i - 1] as number[])[j - 1]! + (a[i - 1] === b[j - 1] ? 0 : 1)
      );
  return (dp[a.length] as number[])[b.length] as number;
}
