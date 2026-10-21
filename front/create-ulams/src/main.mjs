import { spawn } from "node:child_process";
import { createRequire } from "node:module";
import { resolve } from "node:path";
import { HELP, UsageError, parse } from "./args.mjs";
import { resolveCli, stackArgs } from "./cli.mjs";
import { MIN_NODE, dockerComposeAvailable, inspectDirectory, nodeSupported } from "./preflight.mjs";

const EXIT = { ok: 0, failed: 1, usage: 2 };

/** Runs a command with the terminal attached, resolving with its exit code. */
function inherit(command, args) {
  return new Promise((done) => {
    const child = spawn(command, args, { stdio: "inherit" });
    child.on("error", () => done(EXIT.failed));
    child.on("close", (code) => done(code ?? EXIT.failed));
  });
}

/**
 * `create-ulams`: checks the machine and the target directory, then lets the ulams CLI launch the stack
 * (`ulams stack up`, ADR 0102). Everything with side effects is injectable, for the tests.
 *
 * @returns {Promise<number>} the exit code
 */
export async function main(argv, deps = {}) {
  const io = deps.io ?? { out: (text) => process.stdout.write(`${text}\n`), err: (text) => process.stderr.write(`${text}\n`) };
  const nodeVersion = deps.nodeVersion ?? process.versions.node;
  const inspect = deps.inspectDirectory ?? inspectDirectory;
  const composeAvailable = deps.dockerComposeAvailable ?? dockerComposeAvailable;
  const findCli = deps.resolveCli ?? resolveCli;
  const launch = deps.launch ?? inherit;
  const cwd = deps.cwd ?? process.cwd();

  let parsed;
  try {
    parsed = parse(argv);
  } catch (error) {
    if (!(error instanceof UsageError)) throw error;
    io.err(`create-ulams: ${error.message}\n\nRun create-ulams --help for the options.`);
    return EXIT.usage;
  }
  if (parsed.help) {
    io.out(HELP);
    return EXIT.ok;
  }
  if (parsed.version) {
    io.out(deps.version ?? createRequire(import.meta.url)("../package.json").version);
    return EXIT.ok;
  }

  if (!nodeSupported(nodeVersion)) {
    io.err(`create-ulams needs Node ${MIN_NODE.join(".")} or newer; this is Node ${nodeVersion}.`);
    return EXIT.failed;
  }

  const { directory, name, options } = parsed;
  const target = resolve(cwd, directory);
  const found = inspect(target);
  if (found.state === "file") {
    io.err(`${target} is a file. Choose another directory name.`);
    return EXIT.usage;
  }
  if (found.state === "not-empty" && !options.force) {
    io.err(
      `${target} is not empty (${found.entries.slice(0, 3).join(", ")}${found.entries.length > 3 ? ", ..." : ""}).\n` +
        "Choose a new directory. To resume a stack that create-ulams or `ulams stack up` started there, run it again with --force.",
    );
    return EXIT.usage;
  }

  const cli = findCli({ flag: options.cli });
  if (cli === null) {
    io.err(
      "Could not find the ulams CLI that launches the stack." +
        (options.cli ? ` --cli ${options.cli} does not exist.` : "") +
        "\nInstall it (npm install -g ulams), or pass --cli /path/to/dist/ulams.mjs, or set ULAMS_CLI.",
    );
    return EXIT.failed;
  }

  if (!options.dryRun && options.target === "docker" && !composeAvailable()) {
    io.err("Docker Compose v2 is needed to start the stack: install Docker (https://docs.docker.com/get-docker/) and make sure `docker compose version` works.\nOr run with --dry-run to see the plan without Docker.");
    return EXIT.failed;
  }

  const args = [...cli.args, ...stackArgs({ directory: target, name, options })];
  io.err(`${options.dryRun ? "Planning" : "Starting"} your academy in ${target} (project ${name}, ${options.mode}, ${options.target}).`);
  const code = await launch(cli.command, args);
  if (code !== EXIT.ok) {
    io.err(`\nThe stack did not start (exit code ${code}). Nothing was removed: fix the cause above and run the same command again with --force to resume.`);
    return code;
  }
  if (!options.dryRun) {
    io.out(`\nYour academy is running. Manage it with:\n  npx ulams stack status --directory ${directory}\n  npx ulams stack down --directory ${directory}   # keeps your data; --purge deletes it`);
  }
  return EXIT.ok;
}
