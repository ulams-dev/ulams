import { execFileSync } from "node:child_process";
import * as fs from "node:fs";
import { mkdtempSync, realpathSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { afterEach, describe, expect, it } from "vitest";
import { parse as parseYaml } from "yaml";
import { gitOrigin, repoFromRemote, splitRemote } from "../../src/course/repo.ts";
import { runCli, type Handler } from "../helpers.ts";
import { fakeCourseAsCode, remoteDocument, type FakeOptions } from "./fake-course-as-code.ts";

/* Git-driven updates (plan 9): a push waits for an open Living Course proposal on the same elements, CI names the commit it
   checks for, and the course folder can ask Living Course to watch its repository's sources/ folder. */

const env = { ULAMS_URL: "http://coffee.localhost", ULAMS_TOKEN: "tok-secret-123456", ULAMS_POLL_MS: "1" };
const cleanups: Array<() => void> = [];
afterEach(() => {
  while (cleanups.length) cleanups.pop()?.();
});
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type Out = Record<string, any>;

function tmp(): string {
  const dir = mkdtempSync(join(tmpdir(), "ulams-git-"));
  cleanups.push(() => rmSync(dir, { recursive: true, force: true }));
  return dir;
}

async function cli(args: string[], routes: Record<string, Handler>) {
  const r = await runCli(args, { env, routes });
  cleanups.push(r.cleanup);
  return Object.assign(r, { body: (): Out => r.json() as Out, data: (): Out => ((r.json() as Out).data ?? {}) as Out, error: (): Out => ((r.json() as Out).error ?? {}) as Out });
}

const firstBlock = (): string => String(((((remoteDocument().modules as Out[])[0] as Out).lessons as Out[])[0] as Out).blocks[0].id);

async function initialised(options: FakeOptions = {}) {
  const fake = fakeCourseAsCode(options);
  const dir = join(tmp(), "course");
  const r = await cli(["course", "init", dir, "--from-course", String(fake.courseId), "--json"], fake.routes);
  expect(r.code, r.stdout + r.stderr).toBe(0);
  return { fake, dir };
}

describe("course push and an open update proposal", () => {
  const pending = () => [{ id: "01prop00000000000000000001", number: 3, elements: [firstBlock()] }];
  const EDIT_FROM = "kubectl is the command-line tool for Kubernetes clusters.";
  const edit = async (dir: string) => {
    const { readFileSync, writeFileSync } = await import("node:fs");
    const file = join(dir, "modules/01-basics/01-install.md");
    writeFileSync(file, readFileSync(file, "utf8").replace(EDIT_FROM, "kubectl is the CLI for Kubernetes clusters."));
  };

  it("waits as SYNC_CONFLICT (exit 6) and names the proposal and the elements, storing nothing", async () => {
    const { fake, dir } = await initialised({ pending: pending() });
    await edit(dir);
    const versions = fake.db.versions.length;

    const r = await cli(["course", "push", dir, "--json"], fake.routes);

    expect(r.code).toBe(6);
    expect(r.error()).toMatchObject({ code: "SYNC_CONFLICT", details: { code: "PROPOSAL_PENDING", proposals: [{ proposalId: "01prop00000000000000000001", number: 3 }] } });
    expect(r.error().message).toContain("#3");
    expect(r.error().message).toContain("block 1");
    expect(r.error().hint).toContain("--allow-pending");
    expect(fake.db.versions).toHaveLength(versions);
  });

  it("--allow-pending sends the flag and stores the proposed version", async () => {
    const { fake, dir } = await initialised({ pending: pending() });
    await edit(dir);

    const r = await cli(["course", "push", dir, "--allow-pending", "--json"], fake.routes);

    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(r.data()).toMatchObject({ pushed: true, version: { kind: "import", status: "proposed" } });
    expect(r.requests.find((q) => q.path.endsWith("/imports"))?.body).toMatchObject({ allowPending: true });
  });

  it("does not hold back a push that edits other elements, and sends no flag by default", async () => {
    const { fake, dir } = await initialised({ pending: [{ id: "01prop00000000000000000001", number: 3, elements: ["01nothere000000000000000000"] }] });
    await edit(dir);

    const r = await cli(["course", "push", dir, "--json"], fake.routes);

    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(r.requests.find((q) => q.path.endsWith("/imports"))?.body).not.toHaveProperty("allowPending");
  });

  it("--dry-run shows allowPending in the planned request", async () => {
    const { fake, dir } = await initialised();
    await edit(dir);

    const r = await cli(["course", "push", dir, "--allow-pending", "--dry-run", "--json"], fake.routes);

    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(JSON.stringify(r.body())).toContain("allowPending");
  });
});

describe("living connections check --commit", () => {
  const route = "POST /api/admin/living-course/connections/01conn/check";

  it("sends the commit for the server to record on the revision", async () => {
    const r = await cli(["living", "connections", "check", "01conn", "--commit", "ABCDEF1234", "--json"], { [route]: { status: 202, body: { success: true, data: { queued: true, connectionId: "01conn" } } } });

    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(r.requests[0]?.body).toEqual({ commit: "ABCDEF1234" });
  });

  it("sends no body without --commit, and refuses a value that is not a commit id before any request", async () => {
    const plain = await cli(["living", "connections", "check", "01conn", "--json"], { [route]: { status: 202, body: { success: true, data: { queued: true } } } });
    expect(plain.requests[0]?.body).toBeUndefined();

    const bad = await cli(["living", "connections", "check", "01conn", "--commit", "main", "--json"], {});
    expect(bad.code).toBe(2);
    expect(bad.requests).toHaveLength(0);
  });
});

describe("Git remotes", () => {
  it("reads https, scp-like and ssh remotes", () => {
    expect(splitRemote("https://github.com/acme/course.git")).toEqual({ hostname: "github.com", path: "acme/course" });
    expect(splitRemote("git@github.com:acme/course.git")).toEqual({ hostname: "github.com", path: "acme/course" });
    expect(splitRemote("ssh://git@git.example.org:2222/team/sub/course")).toEqual({ hostname: "git.example.org", path: "team/sub/course" });
    expect(splitRemote("https://x-access-token:secret@github.com/acme/course")).toEqual({ hostname: "github.com", path: "acme/course" });
    expect(splitRemote("not a remote")).toBeNull();
    expect(splitRemote("/local/path")).toBeNull();
  });

  it("knows github.com and gitlab.com, and needs --host for anything else", () => {
    expect(repoFromRemote("git@github.com:acme/course.git", "main")).toEqual({ host: "github", repository: "acme/course", branch: "main" });
    expect(repoFromRemote("https://gitlab.com/g/s/course", "dev")).toEqual({ host: "gitlab", repository: "g/s/course", branch: "dev" });
    expect(repoFromRemote("https://git.example.org/acme/course", "main")).toBeNull();
    expect(repoFromRemote("https://git.example.org/acme/course", "main", "gitea")).toEqual({ host: "gitea", baseUrl: "https://git.example.org", repository: "acme/course", branch: "main" });
    expect(repoFromRemote("https://git.example.org/acme/course", "main", "gitlab")).toEqual({ host: "gitlab", baseUrl: "https://git.example.org/api/v4", repository: "acme/course", branch: "main" });
  });

  it("finds the origin and the default branch with a fake and with a real repository", () => {
    expect(gitOrigin("/x", (args) => (args[0] === "remote" ? "git@github.com:a/b.git" : args[0] === "symbolic-ref" ? "origin/trunk" : "feature"))).toEqual({ remote: "git@github.com:a/b.git", branch: "trunk" });
    expect(gitOrigin("/x", () => null)).toBeNull();

    const dir = tmp();
    execFileSync("git", ["init", "-q", "-b", "main", dir]);
    expect(gitOrigin(dir)).toBeNull();
    execFileSync("git", ["-C", dir, "remote", "add", "origin", "https://github.com/acme/course.git"]);
    expect(gitOrigin(dir)).toEqual({ remote: "https://github.com/acme/course.git", branch: "main" });
  });
});

describe("course sources-connect", () => {
  async function inRepository(remote: string) {
    const fake = fakeCourseAsCode();
    const repo = tmp();
    execFileSync("git", ["init", "-q", "-b", "main", repo]);
    execFileSync("git", ["-C", repo, "remote", "add", "origin", remote]);
    const dir = join(repo, "course");
    const init = await cli(["course", "init", dir, "--from-course", "12", "--json"], fake.routes);
    expect(init.code, init.stdout + init.stderr).toBe(0);
    const connect: Handler = { status: 201, body: { success: true, data: { connection: { id: "01conn", webhookUrl: "http://coffee.localhost/api/living-course/webhooks/abc" }, source: {}, webhookSecret: "whsec_once" } } };
    return { fake, dir, routes: { ...fake.routes, [`POST /api/admin/living-course/sessions/${fake.sessionId}/sources/connect`]: connect } };
  }

  it("connects the repository's sources/ folder to the folder's session and shows the webhook secret once", async () => {
    const { dir, routes } = await inRepository("git@github.com:acme/course.git");

    const r = await cli(["course", "sources-connect", dir, "--token", "ghp_readonly", "--json"], routes);

    expect(r.code, r.stdout + r.stderr).toBe(0);
    const sent = r.requests.find((q) => q.path.endsWith("/sources/connect"))?.body as Out;
    expect(sent).toEqual({ connector: "git", config: { host: "github", repository: "acme/course", branch: "main", paths: ["sources/**"] }, secrets: { token: "ghp_readonly" } });
    expect(r.data()).toMatchObject({ webhookSecret: "whsec_once", repository: { repository: "acme/course" } });
  });

  it("prints the webhook address and secret for a person, never the access token", async () => {
    const { dir, routes } = await inRepository("https://github.com/acme/course");

    const r = await cli(["course", "sources-connect", dir, "--token", "ghp_readonly"], routes);

    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(r.stdout).toContain("acme/course (main), sources/**");
    expect(r.stdout).toContain("Webhook secret (shown once): whsec_once");
    expect(r.stdout + r.stderr).not.toContain("ghp_readonly");
  });

  it("--dry-run masks the token and sends nothing; the token may come from ULAMS_SOURCES_TOKEN", async () => {
    const { dir, routes } = await inRepository("https://github.com/acme/course");

    const r = await cli(["course", "sources-connect", dir, "--token", "ghp_readonly", "--dry-run", "--json"], routes);

    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(JSON.stringify(r.body())).not.toContain("ghp_readonly");
    expect(JSON.stringify(r.body())).toContain("write-only");
    expect(r.requests.some((q) => q.path.endsWith("/sources/connect"))).toBe(false);

    const fromEnv = await runCli(["course", "sources-connect", dir, "--json"], { env: { ...env, ULAMS_SOURCES_TOKEN: "env-token" }, routes });
    cleanups.push(fromEnv.cleanup);
    expect((fromEnv.requests.find((q) => q.path.endsWith("/sources/connect"))?.body as Out).secrets).toEqual({ token: "env-token" });
  });

  it("explains an unknown host, a folder outside a repository and an unbound folder, before any request", async () => {
    const unknown = await inRepository("https://git.example.org/acme/course");
    const host = await cli(["course", "sources-connect", unknown.dir, "--json"], unknown.routes);
    expect(host.code).toBe(2);
    expect(host.error().hint).toContain("--host");
    expect(host.requests.filter((q) => q.path.endsWith("/sources/connect"))).toHaveLength(0);
    const gitea = await cli(["course", "sources-connect", unknown.dir, "--host", "gitea", "--json"], unknown.routes);
    expect((gitea.requests.find((q) => q.path.endsWith("/sources/connect"))?.body as Out).config).toMatchObject({ host: "gitea", base_url: "https://git.example.org" });

    const lone = tmp();
    const noRepo = await cli(["course", "init", join(lone, "c"), "--from-course", "12", "--json"], fakeCourseAsCode().routes);
    expect(noRepo.code).toBe(0);
    const outside = await cli(["course", "sources-connect", join(lone, "c"), "--json"], {});
    expect(outside.code).toBe(2);
    expect(outside.error().message).toContain("Git repository");

    const unbound = await cli(["course", "sources-connect", tmp(), "--json"], {});
    expect(unbound.code).not.toBe(0);
    expect(unbound.requests).toHaveLength(0);
  });
});

describe("course init --ci github", () => {
  const { existsSync, readFileSync, mkdirSync, writeFileSync } = fs;

  it("writes the workflow at the repository root, pointing at the course folder", async () => {
    const fake = fakeCourseAsCode();
    const repo = tmp();
    execFileSync("git", ["init", "-q", "-b", "main", repo]);
    const dir = join(repo, "courses", "k8s");
    const r = await cli(["course", "init", dir, "--from-course", "12", "--ci", "github", "--json"], fake.routes);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    const file = join(realpathSync(repo), ".github/workflows/ulams.yml");
    expect(r.data().workflow).toBe(file);
    const text = readFileSync(file, "utf8");
    const wf = parseYaml(text) as Out;
    expect(Object.keys(wf.jobs)).toEqual(["validate", "push", "sync"]);
    expect(wf.on.pull_request.paths).toEqual(["courses/k8s/**", ".github/workflows/ulams.yml"]);
    expect(wf.on.push.branches).toEqual(["main"]);
    expect(wf.on.repository_dispatch.types).toEqual(["ulams-course-changed"]);
    expect(wf.jobs.sync.steps[1]).toMatchObject({ uses: "ulams-dev/ulams/actions/course@cli-v1", with: { command: "pull-pr", path: "courses/k8s" } });
    expect(text).toContain("secrets.ULAMS_TOKEN");
    expect(text).not.toContain("tok-secret");
    // the push job never applies by itself
    expect(JSON.stringify(wf.jobs.push)).not.toContain("apply");
  });

  it("uses the repository itself as the course folder when it is the root", async () => {
    const fake = fakeCourseAsCode();
    const repo = tmp();
    execFileSync("git", ["init", "-q", "-b", "trunk", repo]);
    writeFileSync(join(repo, "README.md"), "mine");
    const r = await cli(["course", "init", repo, "--from-course", "12", "--ci", "github", "--json"], fake.routes);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    const wf = parseYaml(readFileSync(join(repo, ".github/workflows/ulams.yml"), "utf8")) as Out;
    expect(wf.jobs.sync.steps[1].with.path).toBe(".");
    expect(wf.on.pull_request).toBeNull();
  });

  it("does not overwrite a workflow, and --dry-run writes nothing", async () => {
    const fake = fakeCourseAsCode();
    const repo = tmp();
    execFileSync("git", ["init", "-q", "-b", "main", repo]);
    const dry = await cli(["course", "init", join(repo, "c"), "--from-course", "12", "--ci", "github", "--dry-run", "--json"], fake.routes);
    expect(dry.code, dry.stdout + dry.stderr).toBe(0);
    expect(existsSync(join(repo, ".github"))).toBe(false);
    expect(existsSync(join(repo, "c"))).toBe(false);

    mkdirSync(join(repo, ".github/workflows"), { recursive: true });
    writeFileSync(join(repo, ".github/workflows/ulams.yml"), "mine");
    const r = await cli(["course", "init", join(repo, "c"), "--from-course", "12", "--ci", "github", "--json"], fake.routes);
    expect(r.code).toBe(2);
    expect(r.error().message).toContain("already exists");
    expect(r.requests).toHaveLength(0);
    expect(readFileSync(join(repo, ".github/workflows/ulams.yml"), "utf8")).toBe("mine");
  });
});

describe("CI environment becomes audit headers", () => {
  it("sends the commit and the change URL of a CI job with every request, trimmed to one printable line", async () => {
    const fake = fakeCourseAsCode();
    const r = await runCli(["course", "init", join(tmp(), "c"), "--from-course", "12", "--json"], { env: { ...env, ULAMS_COMMIT: "abc1234\n", ULAMS_CHANGE_URL: "https://github.com/acme/course/pull/7" + "x".repeat(600) }, routes: fake.routes });
    cleanups.push(r.cleanup);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(r.requests[0]?.headers["x-ulams-commit"]).toBe("abc1234");
    expect(r.requests[0]?.headers["x-ulams-change-url"]).toHaveLength(500);

    const none = await cli(["course", "init", join(tmp(), "d"), "--from-course", "12", "--json"], fake.routes);
    expect(none.requests[0]?.headers).not.toHaveProperty("x-ulams-commit");
  });
});
