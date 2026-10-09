import { readFile, writeFile, access } from "node:fs/promises";
import { createInterface } from "node:readline";
import { CliError, fromApiError } from "../errors.ts";
import { HttpClient } from "../http/client.ts";
import { ConfigStore, resolveProfile, type ResolvedProfile } from "../config/profiles.ts";
import { configDir } from "../config/paths.ts";
import { redactString } from "../config/redact.ts";
import { errorEnvelope, successEnvelope } from "../output/envelope.ts";
import { render, resolveMode, type Mode } from "../output/render.ts";
import { execute, validateInput } from "../registry/executor.ts";
import { getRegistry } from "../registry/index.ts";
import { commandPath } from "../registry/schema-export.ts";
import type { AnyCommand, Ctx, FsPort, GlobalFlags } from "../registry/types.ts";
import { commandHelp, nounHelp, rootHelp, suggest } from "./help.ts";
import { parseInvocation, resolveCommand } from "./parse.ts";

export const VERSION = "0.1.0";

export interface Deps {
  argv: string[];
  env: NodeJS.ProcessEnv;
  stdout: (text: string) => void;
  stderr: (text: string) => void;
  stdinIsTTY: boolean;
  stdoutIsTTY: boolean;
  stderrIsTTY: boolean;
  readStdin: () => Promise<string>;
  fetch?: typeof fetch;
  fs?: FsPort;
  configDir?: string;
  signal?: AbortSignal;
  prompt?: (question: string, opts?: { secret?: boolean }) => Promise<string>;
  commands?: AnyCommand[];
}

export const nodeFs: FsPort = {
  readFile: async (path) => new Uint8Array(await readFile(path)),
  readText: (path) => readFile(path, "utf8"),
  writeFile: (path, data) => writeFile(path, data),
  exists: (path) =>
    access(path).then(
      () => true,
      () => false
    ),
};

export function nodeDeps(): Deps {
  let stdinText: Promise<string> | null = null;
  return {
    argv: process.argv.slice(2),
    env: process.env,
    stdout: (t) => void process.stdout.write(`${t}\n`),
    stderr: (t) => void process.stderr.write(`${t}\n`),
    stdinIsTTY: Boolean(process.stdin.isTTY),
    stdoutIsTTY: Boolean(process.stdout.isTTY),
    stderrIsTTY: Boolean(process.stderr.isTTY),
    readStdin: () =>
      (stdinText ??= (async () => {
        const chunks: Buffer[] = [];
        for await (const chunk of process.stdin) chunks.push(chunk as Buffer);
        return Buffer.concat(chunks).toString("utf8");
      })()),
    prompt: (question, opts) =>
      new Promise((resolve) => {
        const rl = createInterface({ input: process.stdin, output: process.stderr, terminal: true });
        if (opts?.secret) {
          const w = rl as unknown as { _writeToOutput: (s: string) => void };
          w._writeToOutput = (s) => {
            if (s.includes(question)) process.stderr.write(s);
          };
        }
        rl.question(question, (answer) => {
          rl.close();
          if (opts?.secret) process.stderr.write("\n");
          resolve(answer);
        });
      }),
  };
}

function emptyProfile(): ResolvedProfile {
  return { name: null, url: null, kind: "tenant", token: null, tokenSource: "none" };
}

export async function main(deps: Deps): Promise<number> {
  const commands = deps.commands ?? getRegistry();
  const { argv, env } = deps;
  const fs = deps.fs ?? nodeFs;
  const store = new ConfigStore(deps.configDir ?? configDir(env));
  let commandId = "ulams";
  let mode: Mode = resolveMode({ json: argv.includes("--json"), output: "auto" }, deps.stdoutIsTTY, env);
  let flags: GlobalFlags | null = null;
  let secrets: string[] = [];

  const fail = (error: unknown): number => {
    const cli = error instanceof CliError ? error : fromApiError(error);
    const safe = new CliError(cli.code, redactString(cli.message, secrets), {
      hint: cli.hint,
      status: cli.status,
      retryable: cli.retryable,
      requestId: cli.requestId,
      details: JSON.parse(redactString(JSON.stringify(cli.details), secrets)) as Record<string, unknown>,
    });
    const out = render(errorEnvelope(commandId, safe), mode, { fields: undefined });
    if (out.stdout) deps.stdout(out.stdout);
    if (out.stderr) deps.stderr(out.stderr);
    return safe.exitCode;
  };

  try {
    const resolved = resolveCommand(argv, commands);
    if (resolved.version && !resolved.command) {
      deps.stdout(`ulams ${VERSION}`);
      return 0;
    }
    if (!resolved.command) {
      if (resolved.words.length === 0) {
        deps.stdout(rootHelp(commands, VERSION));
        return resolved.help || argv.length === 0 ? 0 : 2;
      }
      const noun = resolved.words[0] as string;
      if (commands.some((c) => commandPath(c)[0] === noun) && resolved.words.length === 1) {
        deps.stdout(nounHelp(noun, commands));
        return 0;
      }
      const near = suggest(resolved.words, commands);
      throw new CliError("USAGE", `Unknown command "${resolved.words.join(" ")}".`, {
        hint: near.length ? `Did you mean: ${near.join(", ")}? Run \`ulams --help\`.` : "Run `ulams --help` to list commands.",
      });
    }

    const cmd = resolved.command;
    commandId = cmd.id;
    if (resolved.help) {
      deps.stdout(commandHelp(cmd));
      return 0;
    }

    const stdin = deps.readStdin;
    const parsed = await parseInvocation(cmd, resolved.rest, env, fs, stdin);
    flags = parsed.flags;
    const config = store.read();
    if (flags.output === "auto" && !env.ULAMS_OUTPUT && config.defaultOutput && config.defaultOutput !== "auto") {
      flags = { ...flags, output: config.defaultOutput as GlobalFlags["output"] };
    }
    mode = resolveMode(flags, deps.stdoutIsTTY, env);
    // --out with a write command streams elsewhere; keep stdout clean for envelopes.

    let stdinToken: string | null = null;
    if (flags.tokenStdin && cmd.id !== "login") {
      stdinToken = (await stdin()).split(/\r?\n/)[0]?.trim() || null;
    }
    const softResolve = (name: string | undefined): ResolvedProfile => {
      try {
        return resolveProfile({ store, env, profile: name, url: flags?.url, stdinToken });
      } catch {
        return emptyProfile();
      }
    };
    // Anonymous commands (login, schema, profiles...) never fail on a missing or unknown profile.
    const profile = cmd.anonymous ? softResolve(cmd.id === "login" ? undefined : flags.profile) : resolveProfile({ store, env, profile: flags.profile, url: flags.url, stdinToken });
    const loginProfile = profile;
    secrets = [profile.token ?? "", env.ULAMS_TOKEN ?? "", stdinToken ?? ""].filter(Boolean);

    const needsAuth = !cmd.anonymous && cmd.kind !== "local";
    if (needsAuth) {
      if (!profile.url) {
        throw new CliError("AUTH_REQUIRED", "No instance configured.", { hint: "Run `ulams login --url <origin>` or set ULAMS_URL and ULAMS_TOKEN." });
      }
      if (!profile.token) {
        throw new CliError("AUTH_REQUIRED", `Not logged in to ${profile.url}.`, { hint: `Run \`ulams login --url ${profile.url}\` or set ULAMS_TOKEN.` });
      }
    }

    const abort = new AbortController();
    const onSignal = () => abort.abort();
    process.once("SIGINT", onSignal);
    const signal = deps.signal ? AbortSignal.any([deps.signal, abort.signal]) : abort.signal;

    const client = new HttpClient({
      baseUrl: (cmd.id === "login" ? loginProfile.url : profile.url) ?? "http://invalid.localhost",
      token: profile.token,
      ...(deps.fetch ? { fetch: deps.fetch } : {}),
      userAgent: `ulams-cli/${VERSION} (${process.platform}; node ${process.versions.node})`,
      client: "cli",
      agent: env.ULAMS_AGENT,
      debug: flags.debug ? (line) => deps.stderr(`[debug] ${redactString(line, secrets)}`) : undefined,
    });

    const interactive = (flags.interactive || cmd.id === "login") && deps.stdinIsTTY && deps.stderrIsTTY;
    const ctx: Ctx = {
      client,
      profile: cmd.id === "login" ? loginProfile : profile,
      fs,
      io: { stderr: (line) => !flags?.quiet && deps.stderr(redactString(line, secrets)), isTTY: deps.stderrIsTTY, interactive },
      signal,
      emit: (event) => deps.stdout(JSON.stringify({ type: "event", data: event })),
      flags,
      env,
      readStdin: stdin,
      prompt: deps.prompt ?? (async () => ""),
      version: VERSION,
      store,
    };

    try {
      const prompt = deps.prompt;
      const result = await execute(cmd, parsed.input, ctx, {
        ...(prompt && interactive ? { prompt: async (q: string) => /^y(es)?$/i.test((await prompt(`${q} [y/N] `)).trim()) } : {}),
      });
      const binaryOut = (result as { handled?: boolean }).handled;
      if (!binaryOut) {
        const out = render(successEnvelope(cmd.id, result), mode, flags);
        if (out.stdout !== undefined) deps.stdout(out.stdout);
      }
      // apply --dry-run --exit-code: exit 13 when the plan contains changes (ADR 0073).
      const planned = (result.data as { dryRun?: boolean; changes?: unknown[] } | null)?.changes;
      if (parsed.input.exitCode && flags.dryRun && Array.isArray(planned) && planned.length > 0) return 13;
      return 0;
    } finally {
      process.off("SIGINT", onSignal);
    }
  } catch (error) {
    return fail(error);
  }
}

export { validateInput };
