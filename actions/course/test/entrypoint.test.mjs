import assert from "node:assert/strict";
import { test } from "node:test";
import { changeContext, cliCommand, COMMENT_MARKER, parseInputs, previewComment, previewTarget, run, SYNC_BRANCH, syncBody, ulamsArgs } from "../entrypoint.mjs";

const base = { INPUT_COMMAND: "validate", INPUT_PATH: "course", INPUT_CLI_VERSION: "cli-v1", GITHUB_WORKSPACE: "/ws", GITHUB_REPOSITORY: "acme/course", GITHUB_SHA: "abc1234def", RUNNER_TEMP: "/tmp/r", GITHUB_STEP_SUMMARY: "/tmp/r/summary.md", ULAMS_URL: "https://coffee.example", ULAMS_TOKEN: "tok-secret" };

/** Records every call; `script` answers by `cmd args` prefix. */
function harness(env, event, script = {}) {
  const calls = [];
  const files = new Map();
  const logs = [];
  const exec = (cmd, args, o = {}) => {
    calls.push({ cmd, args, env: o.env });
    const key = Object.keys(script).find((k) => `${cmd} ${args.join(" ")}`.startsWith(k));
    const r = key ? script[key] : {};
    return { status: r.status ?? 0, stdout: r.stdout ?? "", stderr: r.stderr ?? "" };
  };
  const io = { env: { ...base, ...env }, event, exec, write: (p, t) => files.set(p, t), append: (p, t) => files.set(p, (files.get(p) ?? "") + t), log: (l) => logs.push(l) };
  return { io, calls, files, logs };
}

test("inputs are checked: a known command and a folder inside the workspace", () => {
  assert.equal(parseInputs({ INPUT_COMMAND: "push" }).path, ".");
  assert.throws(() => parseInputs({ INPUT_COMMAND: "deploy" }), /command must be one of/);
  assert.throws(() => parseInputs({ INPUT_COMMAND: "validate", INPUT_PATH: "../x" }), /inside the workspace/);
  assert.throws(() => parseInputs({ INPUT_COMMAND: "validate", INPUT_PATH: "/etc" }), /inside the workspace/);
  assert.equal(parseInputs({ INPUT_COMMAND: "preview" }).comment, true);
  assert.equal(parseInputs({ INPUT_COMMAND: "preview", INPUT_COMMENT: "false" }).comment, false);
  assert.equal(parseInputs({ INPUT_COMMAND: "push", INPUT_APPLY: "true" }).apply, true);
});

test("every command maps to one ulams invocation", () => {
  const i = (command, extra = {}) => ({ ...parseInputs({ INPUT_COMMAND: command, INPUT_PATH: "course" }), ...extra });
  assert.deepEqual(ulamsArgs(i("validate")), ["course", "validate", "course", "--format", "sarif"]);
  assert.deepEqual(ulamsArgs(i("validate", { strict: true })).slice(-1), ["--strict"]);
  assert.deepEqual(ulamsArgs(i("diff")), ["course", "diff", "course", "--remote", "--json"]);
  assert.deepEqual(ulamsArgs(i("preview"), { message: "Rename flag" }), ["course", "push", "course", "--message", "Rename flag", "--json"]);
  assert.ok(!ulamsArgs(i("preview")).includes("--apply"), "a preview never approves");
  assert.ok(ulamsArgs(i("push", { apply: true })).includes("--apply"));
  assert.ok(!ulamsArgs(i("push")).includes("--apply"));
  assert.deepEqual(ulamsArgs(i("pull-pr")), ["course", "pull", "course", "--json"]);
});

test("the CLI runs in the pinned image with the secrets passed by name, never by value", () => {
  const inputs = parseInputs({ INPUT_COMMAND: "validate", INPUT_CLI_VERSION: "cli-v1" });
  const { cmd, prefix } = cliCommand(inputs, {}, "/ws");
  assert.equal(cmd, "docker");
  assert.ok(prefix.includes("ghcr.io/ulams-dev/ulams-cli:cli-v1"));
  assert.ok(prefix.includes("ULAMS_TOKEN"));
  assert.ok(!prefix.some((a) => a.includes("tok-secret") || a.startsWith("ULAMS_TOKEN=")));
  assert.deepEqual(cliCommand(inputs, { ULAMS_CLI_BIN: "/opt/ulams" }, "/ws"), { cmd: "/opt/ulams", prefix: [] });
  assert.throws(() => cliCommand({ ...inputs, version: "x; rm -rf /" }, {}, "/ws"), /image tag/);
});

test("the commit, change URL and message come from the pull request or the push event", () => {
  const pr = changeContext(base, { pull_request: { number: 7, title: "Fix the install step\nbody", html_url: "https://github.com/acme/course/pull/7", head: { sha: "feedbeef" } }, repository: { default_branch: "trunk" } });
  assert.deepEqual([pr.commit, pr.changeUrl, pr.message, pr.prNumber, pr.defaultBranch], ["feedbeef", "https://github.com/acme/course/pull/7", "Fix the install step", 7, "trunk"]);
  const push = changeContext(base, { head_commit: { message: "Merge pull request #7\n\nFix" } });
  assert.deepEqual([push.commit, push.changeUrl, push.message, push.prNumber], ["abc1234def", "https://github.com/acme/course/commit/abc1234def", "Merge pull request #7", null]);
});

test("validate writes the SARIF report, needs no secrets and fails the step on errors", () => {
  const sarif = JSON.stringify({ runs: [{ results: [{}, {}] }] });
  const h = harness({ ULAMS_URL: "", ULAMS_TOKEN: "" }, null, { docker: { status: 7, stdout: sarif } });
  const { exitCode, outputs } = run(h.io);
  assert.equal(exitCode, 7);
  assert.equal(outputs.issues, "2");
  assert.equal(h.files.get(outputs.sarif), sarif);
  assert.ok(!("ULAMS_TOKEN" in h.calls[0].env), "no empty credentials are passed");
  assert.match(h.files.get("/tmp/r/summary.md"), /2 issues/);
});

test("the token reaches the child process only through its environment", () => {
  const h = harness({ INPUT_COMMAND: "diff" }, null, { docker: { stdout: JSON.stringify({ ok: true, data: { counts: { changed: 1, added: 0, removed: 0 } } }) } });
  run(h.io);
  assert.equal(h.calls[0].env.ULAMS_TOKEN, "tok-secret");
  assert.ok(!JSON.stringify(h.calls.map((c) => c.args)).includes("tok-secret"));
  assert.ok(!h.logs.join("\n").includes("tok-secret"));
  assert.throws(() => run(harness({ INPUT_COMMAND: "diff", ULAMS_TOKEN: "" }, null).io), /ULAMS_TOKEN/);
});

test("diff exit 13 is a report, not a failed job", () => {
  const h = harness({ INPUT_COMMAND: "diff" }, null, { docker: { status: 13, stdout: JSON.stringify({ ok: true, data: { counts: { changed: 2 } } }) } });
  assert.equal(run(h.io).exitCode, 0);
});

test("preview proposes with the commit and PR link, never approves, and keeps one sticky comment", () => {
  const event = { pull_request: { number: 7, title: "Fix the install step", html_url: "https://github.com/acme/course/pull/7", head: { sha: "feedbeef" } } };
  const out = { ok: true, data: { pushed: true, version: { id: "01v", number: 4 }, counts: { changed: 1, added: 0, removed: 0 }, changes: [{ kind: "changed", label: "Lesson 1.1 > block 1" }], warnings: [] } };
  const h = harness({ INPUT_COMMAND: "preview", INPUT_STUDIO_URL: "https://studio.example/" }, event, { docker: { stdout: JSON.stringify(out) } });

  const { exitCode, outputs } = run(h.io);

  assert.equal(exitCode, 0);
  assert.ok(h.calls[0].args.includes("Fix the install step"));
  assert.ok(h.calls[0].args.includes("--json") && !h.calls[0].args.includes("--apply") && !h.calls[0].args.includes("--approve"));
  assert.equal(h.calls[0].env.ULAMS_COMMIT, "feedbeef");
  assert.equal(h.calls[0].env.ULAMS_CHANGE_URL, "https://github.com/acme/course/pull/7");
  const gh = h.calls.find((c) => c.cmd === "gh");
  assert.deepEqual(gh.args.slice(0, 3), ["pr", "comment", "7"]);
  assert.ok(gh.args.includes("--edit-last") && gh.args.includes("--create-if-none"));
  assert.ok(outputs["comment-body"].startsWith(COMMENT_MARKER));
  assert.match(outputs["comment-body"], /proposed version 4/);
  assert.match(outputs["comment-body"], /Lesson 1\.1 > block 1/);
});

test("preview without a pull request, or with comment off, posts nothing", () => {
  const out = JSON.stringify({ ok: true, data: { pushed: false } });
  const noPr = harness({ INPUT_COMMAND: "preview" }, { head_commit: { message: "x" } }, { docker: { stdout: out } });
  run(noPr.io);
  assert.ok(!noPr.calls.some((c) => c.cmd === "gh"));
  const off = harness({ INPUT_COMMAND: "preview", INPUT_COMMENT: "false" }, { pull_request: { number: 1, head: { sha: "a" } } }, { docker: { stdout: out } });
  run(off.io);
  assert.ok(!off.calls.some((c) => c.cmd === "gh"));
});

test("the comment says what went wrong when the push failed or nothing changed", () => {
  assert.match(previewComment({ ok: false, error: { code: "SYNC_CONFLICT", message: "Both sides changed Lesson 1.1", hint: "Run ulams course pull" } }), /SYNC_CONFLICT/);
  assert.match(previewComment({ ok: true, data: { pushed: false } }), /nothing to propose/);
  assert.match(previewComment({ ok: true, data: { pushed: true, version: { id: "01v", number: 2 }, counts: {}, changes: [] } }), /ulams course diff --version 01v/);
});

test("push sends the commit with it, and applies only when asked", () => {
  const event = { head_commit: { message: "Merge pull request #7 from acme/x\n\nFix" } };
  const plain = harness({ INPUT_COMMAND: "push" }, event, { docker: { stdout: JSON.stringify({ ok: true, data: { pushed: true, version: { id: "01v", number: 5 } } }) } });
  const r = run(plain.io);
  assert.equal(r.outputs["version-id"], "01v");
  assert.ok(plain.calls[0].args.includes("Merge pull request #7 from acme/x"));
  assert.ok(!plain.calls[0].args.includes("--apply"));
  assert.equal(plain.calls[0].env.ULAMS_COMMIT, "abc1234def");
  const apply = harness({ INPUT_COMMAND: "push", INPUT_APPLY: "true" }, event, { docker: { stdout: "{}" } });
  run(apply.io);
  assert.ok(apply.calls[0].args.includes("--apply"));
});

test("pull-pr does nothing when the folder did not change", () => {
  const h = harness({ INPUT_COMMAND: "pull-pr" }, null, { docker: { stdout: JSON.stringify({ ok: true, data: { state: "up-to-date" } }) }, "git status": { stdout: "" } });
  const r = run(h.io);
  assert.equal(r.outputs.changed, "false");
  assert.deepEqual(h.calls.map((c) => c.cmd), ["docker", "git"]);
});

test("pull-pr opens the sync pull request, never stages sources/, and updates it the next time", () => {
  const pulled = JSON.stringify({ ok: true, data: { version: { number: 6 }, counts: { changed: 2, added: 1, removed: 0 }, files: { written: ["a"], updated: ["b", "c"], removed: [] } } });
  const script = { docker: { stdout: pulled }, "git status": { stdout: " M course/modules/01/a.md" }, "gh pr list": { stdout: "[]" }, "gh pr create": { stdout: "https://github.com/acme/course/pull/9\n" } };
  const first = harness({ INPUT_COMMAND: "pull-pr" }, { repository: { default_branch: "main" } }, script);
  const r = run(first.io);
  assert.equal(r.exitCode, 0);
  const git = first.calls.filter((c) => c.cmd === "git").map((c) => c.args.join(" "));
  assert.ok(git.includes(`checkout -B ${SYNC_BRANCH}`));
  assert.ok(git.includes("add -- course"));
  assert.ok(git.includes("reset -q -- course/sources"), "sources/ is never part of a sync pull request");
  assert.ok(git.some((g) => g.startsWith("push --force-with-lease origin ulams/sync")));
  const create = first.calls.find((c) => c.args.slice(0, 2).join(" ") === "pr create");
  assert.ok(create.args.includes("Sync from ulams: version 6") && create.args.includes("--base") && create.args.includes("main"));
  assert.equal(r.outputs["pull-request"], "https://github.com/acme/course/pull/9");

  const second = harness({ INPUT_COMMAND: "pull-pr" }, null, { ...script, "gh pr list": { stdout: '[{"number":9}]' } });
  const again = run(second.io);
  assert.ok(second.calls.some((c) => c.args.slice(0, 3).join(" ") === "pr edit 9"));
  assert.ok(!second.calls.some((c) => c.args.slice(0, 2).join(" ") === "pr create"));
  assert.equal(again.outputs["pull-request"], "9");
});

test("pull-pr reports a conflict instead of opening a pull request", () => {
  const h = harness({ INPUT_COMMAND: "pull-pr" }, null, { docker: { status: 6, stdout: JSON.stringify({ ok: false, error: { message: "1 conflict" } }) } });
  const r = run(h.io);
  assert.equal(r.exitCode, 6);
  assert.ok(!h.calls.some((c) => c.cmd === "git" || c.cmd === "gh"));
});

test("the sync pull request body says it is safe to merge", () => {
  const body = syncBody({ data: { version: { number: 6 }, counts: { changed: 1, added: 0, removed: 0 } } });
  assert.match(body, /version 6/);
  assert.match(body, /unchanged/);
  assert.match(body, /never touches `sources\/`/);
});

test("a preview academy is named after the repository and the pull request, within the slug rules", () => {
  const a = previewTarget("Acme/Course", 7);
  assert.match(a.slug, /^pr[0-9a-f]{6}7$/);
  assert.equal(a.group, "acme-course");
  assert.deepEqual(previewTarget("acme/course", 7), a, "the same repository and number always give the same academy");
  assert.notEqual(previewTarget("acme/other", 7).slug, a.slug);
  assert.notEqual(previewTarget("acme/course", 8).slug, a.slug);
  assert.ok(previewTarget("a/b", 123456789).slug.length <= 30);
  assert.match(previewTarget("a/b", 12).slug, /^[a-z][a-z0-9]{1,29}$/);
});

const preview = { ULAMS_PREVIEW_URL: "https://platform.example", ULAMS_PREVIEW_TOKEN: "platform-secret" };
const prEvent = { pull_request: { number: 7, title: "Fix the install step", html_url: "https://github.com/acme/course/pull/7", head: { sha: "feedbeef" } } };

test("with a preview platform the pull request gets its own academy and a comment that links it", () => {
  const out = { ok: true, data: { tenant: "x", created: true, versionNumber: 2, expiresAt: "2026-10-30T10:00:00+00:00", urls: { front: "https://x.app.example", admin: "https://x.admin.example" } } };
  const h = harness({ INPUT_COMMAND: "preview", ...preview }, prEvent, { docker: { stdout: JSON.stringify(out) } });

  const r = run(h.io);

  const { slug, group } = previewTarget("acme/course", 7);
  const args = h.calls[0].args;
  assert.deepEqual(args.slice(args.indexOf("course")).slice(0, 2), ["course", "preview"]);
  assert.deepEqual([args[args.indexOf("--tenant") + 1], args[args.indexOf("--group") + 1]], [slug, group]);
  assert.equal(h.calls[0].env.ULAMS_PREVIEW_TOKEN, "platform-secret");
  assert.ok(!JSON.stringify(h.calls.map((c) => c.args)).includes("platform-secret"), "the platform token is passed by name only");
  assert.ok(args.includes("ULAMS_PREVIEW_TOKEN") && !args.includes("ULAMS_PREVIEW_TOKEN=platform-secret"));
  assert.match(r.outputs["comment-body"], /preview academy created/);
  assert.match(r.outputs["comment-body"], /https:\/\/x\.app\.example/);
  assert.match(r.outputs["comment-body"], /citations are dropped/);
});

test("without a preview platform, or outside a pull request, preview falls back to a proposed version", () => {
  const out = JSON.stringify({ ok: true, data: { pushed: true, version: { id: "01v", number: 3 }, counts: {}, changes: [] } });
  const noPlatform = harness({ INPUT_COMMAND: "preview" }, prEvent, { docker: { stdout: out } });
  run(noPlatform.io);
  assert.ok(noPlatform.calls[0].args.includes("push") && !noPlatform.calls[0].args.includes("--tenant"));
  const noPr = harness({ INPUT_COMMAND: "preview", ...preview }, { head_commit: { message: "x" } }, { docker: { stdout: out } });
  run(noPr.io);
  assert.ok(noPr.calls[0].args.includes("push") && !noPr.calls[0].args.includes("--tenant"));
});

test("closing the pull request deletes its preview academy through the platform, and a missing one is fine", () => {
  const { slug } = previewTarget("acme/course", 7);
  const gone = harness({ INPUT_COMMAND: "preview-cleanup", ...preview }, prEvent, { docker: { status: 0, stdout: "{}" } });
  const r = run(gone.io);
  const args = gone.calls[0].args;
  assert.deepEqual(args.slice(args.indexOf("tenants")), ["tenants", "delete", slug, "--yes", "--json"]);
  assert.deepEqual([gone.calls[0].env.ULAMS_URL, gone.calls[0].env.ULAMS_TOKEN], ["https://platform.example", "platform-secret"]);
  assert.equal(r.exitCode, 0);

  const missing = harness({ INPUT_COMMAND: "preview-cleanup", ...preview }, prEvent, { docker: { status: 5, stdout: "{}" } });
  assert.equal(run(missing.io).exitCode, 0);
  const failed = harness({ INPUT_COMMAND: "preview-cleanup", ...preview }, prEvent, { docker: { status: 9, stdout: "{}" } });
  assert.equal(run(failed.io).exitCode, 9);
  const none = harness({ INPUT_COMMAND: "preview-cleanup" }, prEvent, {});
  assert.equal(run(none.io).exitCode, 0);
  assert.equal(none.calls.length, 0);
});
