#!/usr/bin/env node
// Entrypoint of the `ulams-dev/ulams/actions/course` composite action (plan 8.2). It turns the action's inputs into one
// `ulams course ...` run (in the pinned `ulams-cli` image by default), writes the outputs GitHub reads (SARIF file, step
// summary, sticky pull request comment) and, for `pull-pr`, opens or updates the branch `ulams/sync`.
//
// Everything with side effects goes through the injected `io` (exec, files, env), so the unit tests run it without Docker,
// git, gh or a network. The access token travels only in the environment of the child process, never in an argument or a log.
import { appendFileSync, readFileSync, writeFileSync } from "node:fs";
import { createHash } from "node:crypto";
import { spawnSync } from "node:child_process";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { fileURLToPath } from "node:url";

export const COMMANDS = ["validate", "diff", "preview", "preview-cleanup", "push", "pull-pr"];
export const COMMENT_MARKER = "<!-- ulams-course-action -->";
export const SYNC_BRANCH = "ulams/sync";
const IMAGE = "ghcr.io/ulams-dev/ulams-cli";

const flag = (v) => /^(1|true|yes)$/i.test(String(v ?? "").trim());

/** @param {Record<string,string|undefined>} env */
export function parseInputs(env) {
  const command = (env.INPUT_COMMAND ?? "").trim();
  if (!COMMANDS.includes(command)) throw new Error(`command must be one of ${COMMANDS.join(", ")} (got "${command}")`);
  const path = (env.INPUT_PATH ?? ".").trim() || ".";
  if (path.startsWith("/") || path.split("/").includes("..")) throw new Error("path must be a folder inside the workspace");
  return {
    command,
    path,
    version: (env.INPUT_CLI_VERSION ?? "").trim() || "latest",
    comment: flag(env.INPUT_COMMENT ?? "true"),
    apply: flag(env.INPUT_APPLY),
    strict: flag(env.INPUT_STRICT),
    studioUrl: (env.INPUT_STUDIO_URL ?? "").trim().replace(/\/+$/, ""),
    message: (env.INPUT_MESSAGE ?? "").trim(),
    baseBranch: (env.INPUT_BASE_BRANCH ?? "").trim(),
  };
}

/**
 * The preview academy of a pull request: `pr`, six hex characters of the repository and the number (a slug is letters and digits,
 * at most 30), and the group that caps the previews per repository.
 */
export function previewTarget(repository, prNumber) {
  const hash = createHash("sha1").update(String(repository).toLowerCase()).digest("hex").slice(0, 6);
  const group = String(repository).toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 40) || "course";
  return { slug: `pr${hash}${prNumber}`.slice(0, 30), group };
}

/** The `ulams` arguments of a command. */
export function ulamsArgs(inputs, ctx = {}) {
  const message = inputs.message || ctx.message || "Update from a course repository";
  switch (inputs.command) {
    case "preview-cleanup":
      return ["tenants", "delete", ctx.preview?.slug ?? "", "--yes", "--json"];
    case "validate":
      return ["course", "validate", inputs.path, "--format", "sarif", ...(inputs.strict ? ["--strict"] : [])];
    case "diff":
      return ["course", "diff", inputs.path, "--remote", "--json"];
    case "preview":
      return ctx.preview
        ? ["course", "preview", inputs.path, "--tenant", ctx.preview.slug, "--group", ctx.preview.group, "--message", message, "--json"]
        : ["course", "push", inputs.path, "--message", message, "--json"];
    case "push":
      return ["course", "push", inputs.path, "--message", message, "--json", ...(inputs.apply ? ["--apply"] : [])];
    case "pull-pr":
      return ["course", "pull", inputs.path, "--json"];
    default:
      throw new Error(`unknown command ${inputs.command}`);
  }
}

/** How the CLI is started: the image (default), or a binary named by ULAMS_CLI_BIN (the repository's own tests, local use). */
export function cliCommand(inputs, env, workspace) {
  if (env.ULAMS_CLI_BIN) return { cmd: env.ULAMS_CLI_BIN, prefix: [] };
  if (!/^[A-Za-z0-9_.-]+$/.test(inputs.version)) throw new Error("cli-version is not a valid image tag");
  const pass = ["ULAMS_URL", "ULAMS_TOKEN", "ULAMS_COMMIT", "ULAMS_CHANGE_URL", "ULAMS_OUTPUT", "ULAMS_PREVIEW_URL", "ULAMS_PREVIEW_TOKEN"].flatMap((n) => ["-e", n]);
  return { cmd: "docker", prefix: ["run", "--rm", "-v", `${workspace}:/work`, "-w", "/work", ...pass, `${IMAGE}:${inputs.version}`] };
}

/** The commit, the change URL and a default message the CLI sends along, read from the GitHub event. */
export function changeContext(env, event) {
  const repo = env.GITHUB_REPOSITORY ?? "";
  const server = env.GITHUB_SERVER_URL ?? "https://github.com";
  const sha = env.GITHUB_SHA ?? "";
  const pr = event?.pull_request;
  const head = event?.head_commit;
  return {
    commit: pr?.head?.sha ?? sha,
    changeUrl: pr?.html_url ?? (repo && sha ? `${server}/${repo}/commit/${sha}` : ""),
    message: (pr?.title ?? head?.message ?? "").split("\n")[0].slice(0, 200),
    prNumber: pr?.number ?? null,
    defaultBranch: event?.repository?.default_branch ?? "main",
  };
}

const lines = (...parts) => parts.flat().filter((p) => p !== null && p !== undefined && p !== false).join("\n");

/** Markdown for the sticky comment of a `preview` run. */
export function previewComment(result, studio) {
  const data = result?.data ?? {};
  if (result?.ok === false) {
    const e = result.error ?? {};
    return lines(COMMENT_MARKER, `**ulams: the course could not be proposed** (${e.code ?? "error"})`, "", String(e.message ?? "").slice(0, 600), e.hint ? `\n${String(e.hint).slice(0, 400)}` : null);
  }
  if (data.tenant) {
    return lines(
      COMMENT_MARKER,
      `**ulams: preview academy ${data.created ? "created" : "updated"}**${data.versionNumber ? ` (version ${data.versionNumber})` : ""}`,
      "",
      `- Learner view: ${data.urls?.front ?? "?"}`,
      `- Studio and admin: ${data.urls?.admin ?? "?"}`,
      data.expiresAt ? `- Deleted after ${data.expiresAt}, or when this pull request is closed` : null,
      "",
      "The preview has no sources: citations are dropped and every element reads as written by the author. Your real academy is unchanged."
    );
  }
  if (data.pushed === false) return lines(COMMENT_MARKER, "**ulams: nothing to propose.** The course folder equals the version on the server.");
  const v = data.version ?? {};
  const c = data.counts ?? {};
  const review = studio && data.version?.id ? `${studio}/studio/s/${data.sessionId ?? ""}/versions` : null;
  return lines(
    COMMENT_MARKER,
    `**ulams: proposed version ${v.number ?? "?"}** (not live; a person approves it): ${c.changed ?? 0} changed, ${c.added ?? 0} added, ${c.removed ?? 0} removed.`,
    "",
    ...(data.changes ?? []).slice(0, 15).map((ch) => `- ${ch.kind}: ${String(ch.label ?? ch.id).slice(0, 120)}`),
    (data.changes ?? []).length > 15 ? `- ... and ${(data.changes ?? []).length - 15} more` : null,
    ...(data.warnings ?? []).slice(0, 5).map((w) => `- warning: ${String(w).slice(0, 200)}`),
    "",
    review ? `Review it in the studio: ${review}` : `Review it with \`ulams course diff --version ${v.id ?? "<version>"}\`.`
  );
}

/** Body of the sync pull request. */
export function syncBody(result, studio) {
  const d = result?.data ?? {};
  return lines(
    `This pull request brings changes made in ulams into the course folder (version ${d.version?.number ?? "?"}).`,
    "",
    d.counts ? `Elements: ${d.counts.changed ?? 0} changed, ${d.counts.added ?? 0} added, ${d.counts.removed ?? 0} removed.` : null,
    d.files ? `Files: ${(d.files.written ?? []).length} new, ${(d.files.updated ?? []).length} changed, ${(d.files.removed ?? []).length} removed.` : null,
    studio ? `\nStudio: ${studio}` : null,
    "\nMerging it pushes the same content back, which the server answers with \"unchanged\": no new version, no loop. This pull request never touches `sources/`."
  );
}

const report0 = (env, append, text) => env.GITHUB_STEP_SUMMARY && append(env.GITHUB_STEP_SUMMARY, `${text}\n`);

const parseJson = (text) => {
  try {
    return JSON.parse(text);
  } catch {
    return null;
  }
};

/**
 * @param {{env:Record<string,string|undefined>, event:any, exec:(cmd:string,args:string[],o?:{env?:Record<string,string>,input?:string})=>{status:number,stdout:string,stderr:string}, write:(path:string,text:string)=>void, append:(path:string,text:string)=>void, log:(line:string)=>void}} io
 * @returns {{exitCode:number, outputs:Record<string,string>}}
 */
export function run(io) {
  const { env, event, exec, write, append, log } = io;
  const inputs = parseInputs(env);
  const workspace = env.GITHUB_WORKSPACE ?? ".";
  const ctx = changeContext(env, event);
  const cli = cliCommand(inputs, env, workspace);
  const childEnv = { ULAMS_URL: env.ULAMS_URL ?? "", ULAMS_TOKEN: env.ULAMS_TOKEN ?? "", ULAMS_COMMIT: ctx.commit, ULAMS_CHANGE_URL: ctx.changeUrl };
  if (inputs.command !== "validate") childEnv.ULAMS_OUTPUT = "json";
  // a preview academy needs a preview platform and a pull request to name it after
  const previewPlatform = Boolean(env.ULAMS_PREVIEW_URL && env.ULAMS_PREVIEW_TOKEN);
  if (inputs.command === "preview" && previewPlatform && ctx.prNumber) ctx.preview = previewTarget(env.GITHUB_REPOSITORY ?? "", ctx.prNumber);
  if (inputs.command === "preview-cleanup") {
    if (!previewPlatform || !ctx.prNumber) {
      report0(env, io.append, "### ulams preview cleanup\nNo preview platform or no pull request: nothing to delete.");
      return { exitCode: 0, outputs: { "exit-code": "0" } };
    }
    ctx.preview = previewTarget(env.GITHUB_REPOSITORY ?? "", ctx.prNumber);
    // the platform is the target of this one command: its address and token stand in for the academy's
    childEnv.ULAMS_URL = env.ULAMS_PREVIEW_URL;
    childEnv.ULAMS_TOKEN = env.ULAMS_PREVIEW_TOKEN;
  } else if (previewPlatform) {
    childEnv.ULAMS_PREVIEW_URL = env.ULAMS_PREVIEW_URL;
    childEnv.ULAMS_PREVIEW_TOKEN = env.ULAMS_PREVIEW_TOKEN;
  }
  if (!childEnv.ULAMS_URL || !childEnv.ULAMS_TOKEN) {
    if (inputs.command !== "validate") throw new Error("Set the secrets ULAMS_URL and ULAMS_TOKEN (url and token inputs) for this command.");
    delete childEnv.ULAMS_URL;
    delete childEnv.ULAMS_TOKEN;
  }
  const ulams = exec(cli.cmd, [...cli.prefix, ...ulamsArgs(inputs, ctx)], { env: childEnv });
  const outputs = { "exit-code": String(ulams.status) };
  if (inputs.command === "preview-cleanup") {
    // an academy that is already gone (never created, or reaped) is a clean result
    const gone = ulams.status === 0 || ulams.status === 5;
    report0(env, io.append, `### ulams preview cleanup\n${gone ? `The preview academy ${ctx.preview.slug} is gone.` : `Could not delete ${ctx.preview.slug} (exit ${ulams.status}); the reaper deletes it when it expires.`}`);
    return { exitCode: gone ? 0 : ulams.status, outputs };
  }
  const summary = env.GITHUB_STEP_SUMMARY;
  const report = (text) => summary && append(summary, `${text}\n`);

  if (inputs.command === "validate") {
    const file = join(env.RUNNER_TEMP ?? tmpdir(), "ulams-course.sarif");
    write(file, ulams.stdout);
    outputs.sarif = file;
    const issues = (parseJson(ulams.stdout)?.runs?.[0]?.results ?? []).length;
    outputs.issues = String(issues);
    report(`### ulams course validate\n${issues === 0 ? "No issues." : `${issues} issue${issues === 1 ? "" : "s"}; they are annotated on the pull request when the SARIF file is uploaded.`}`);
    if (ulams.stderr) log(ulams.stderr.trim());
    return { exitCode: ulams.status, outputs };
  }

  const result = parseJson(ulams.stdout);
  if (inputs.command === "diff") {
    const d = result?.data ?? {};
    report(`### ulams course diff --remote\n${result?.ok === false ? `Failed: ${result.error?.message ?? "error"}` : `${d.counts?.changed ?? 0} changed, ${d.counts?.added ?? 0} added, ${d.counts?.removed ?? 0} removed against the server.`}`);
    log(ulams.stdout.trim().slice(0, 4000));
    // exit 13 only means "there are differences": a report, not a failure of the job
    return { exitCode: ulams.status === 13 ? 0 : ulams.status, outputs };
  }

  if (inputs.command === "preview") {
    const body = previewComment(result, inputs.studioUrl);
    outputs["comment-body"] = body;
    report(body.replace(COMMENT_MARKER, "").trim());
    if (inputs.comment && ctx.prNumber) {
      const file = join(env.RUNNER_TEMP ?? tmpdir(), "ulams-comment.md");
      write(file, body);
      const posted = exec("gh", ["pr", "comment", String(ctx.prNumber), "--body-file", file, "--edit-last", "--create-if-none"], { env: { GH_TOKEN: env.GH_TOKEN ?? env.GITHUB_TOKEN ?? "" } });
      if (posted.status !== 0) log(`could not post the comment: ${posted.stderr.trim().slice(0, 300)}`);
    }
    // a conflict or a pending proposal is worth a red check; an agent token that cannot approve is not asked to
    return { exitCode: ulams.status, outputs };
  }

  if (inputs.command === "push") {
    const d = result?.data ?? {};
    outputs["version-id"] = d.version?.id ?? "";
    report(`### ulams course push\n${result?.ok === false ? `Failed: ${result.error?.message ?? "error"}` : d.pushed === false ? "Nothing to push." : `Proposed version ${d.version?.number ?? "?"}${d.applied ? " and applied it" : " (a person approves it in the studio)"}.`}`);
    return { exitCode: ulams.status, outputs };
  }

  // pull-pr
  if (ulams.status !== 0) {
    report(`### ulams course pull\nFailed (exit ${ulams.status}): ${result?.error?.message ?? ulams.stderr.trim().slice(0, 300)}`);
    return { exitCode: ulams.status, outputs };
  }
  const git = (...args) => exec("git", args, {});
  const changed = git("status", "--porcelain", "--", inputs.path).stdout.trim();
  outputs.changed = changed ? "true" : "false";
  if (!changed) {
    report("### ulams course pull\nThe folder is up to date; no pull request needed.");
    return { exitCode: 0, outputs };
  }
  const base = inputs.baseBranch || ctx.defaultBranch;
  const title = `Sync from ulams: version ${result?.data?.version?.number ?? "?"}`;
  const bodyFile = join(env.RUNNER_TEMP ?? tmpdir(), "ulams-sync.md");
  write(bodyFile, syncBody(result, inputs.studioUrl));
  const steps = [
    git("checkout", "-B", SYNC_BRANCH),
    git("add", "--", inputs.path),
    git("reset", "-q", "--", `${inputs.path.replace(/\/$/, "")}/sources`), // a sync pull request never touches sources/
    git("-c", "user.name=ulams", "-c", "user.email=noreply@ulams.invalid", "commit", "-m", `chore(course): ${title.toLowerCase()}`),
    git("push", "--force-with-lease", "origin", SYNC_BRANCH),
  ];
  const failed = steps.find((s, i) => s.status !== 0 && i !== 2);
  if (failed) {
    report(`### ulams course pull\nCould not push the branch: ${failed.stderr.trim().slice(0, 300)}`);
    return { exitCode: 1, outputs };
  }
  const ghEnv = { env: { GH_TOKEN: env.GH_TOKEN ?? env.GITHUB_TOKEN ?? "" } };
  const open = parseJson(exec("gh", ["pr", "list", "--head", SYNC_BRANCH, "--state", "open", "--json", "number"], ghEnv).stdout) ?? [];
  const pr = open.length > 0
    ? exec("gh", ["pr", "edit", String(open[0].number), "--title", title, "--body-file", bodyFile], ghEnv)
    : exec("gh", ["pr", "create", "--base", base, "--head", SYNC_BRANCH, "--title", title, "--body-file", bodyFile], ghEnv);
  outputs["pull-request"] = open.length > 0 ? String(open[0].number) : pr.stdout.trim();
  report(`### ulams course pull\n${pr.status === 0 ? `Sync pull request ${open.length > 0 ? "updated" : "opened"}: ${outputs["pull-request"]}` : `Could not open the pull request: ${pr.stderr.trim().slice(0, 300)}`}`);
  return { exitCode: pr.status === 0 ? 0 : 1, outputs };
}

/* ------------------------------------------------------------------ real io */

function realIo(env) {
  const event = env.GITHUB_EVENT_PATH ? parseJson(readFileSync(env.GITHUB_EVENT_PATH, "utf8")) : null;
  return {
    env,
    event,
    exec: (cmd, args, o = {}) => {
      const r = spawnSync(cmd, args, { encoding: "utf8", env: { ...process.env, ...(o.env ?? {}) }, input: o.input, maxBuffer: 64 * 1024 * 1024 });
      return { status: r.status ?? 1, stdout: r.stdout ?? "", stderr: r.stderr ?? (r.error ? String(r.error.message) : "") };
    },
    write: (path, text) => writeFileSync(path, text),
    append: (path, text) => appendFileSync(path, text),
    log: (line) => console.log(line),
  };
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  try {
    const { exitCode, outputs } = run(realIo(process.env));
    if (process.env.GITHUB_OUTPUT) appendFileSync(process.env.GITHUB_OUTPUT, Object.entries(outputs).map(([k, v]) => (String(v).includes("\n") ? `${k}<<EOF_ULAMS\n${v}\nEOF_ULAMS` : `${k}=${v}`)).join("\n") + "\n");
    process.exit(exitCode);
  } catch (e) {
    console.error(`::error::${e instanceof Error ? e.message : String(e)}`);
    process.exit(2);
  }
}
