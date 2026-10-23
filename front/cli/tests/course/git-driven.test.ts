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
    expect(Object.keys(wf.jobs)).toEqual(["validate", "cleanup", "push", "sync"]);
    expect(wf.on.pull_request.types).toEqual(["opened", "synchronize", "reopened", "closed"]);
    expect(wf.jobs.cleanup.steps[0]).toMatchObject({ with: { command: "preview-cleanup" } });
    expect(wf.jobs.cleanup.if).toContain("closed");
    expect(wf.jobs.validate.if).toContain("!= 'closed'");
    expect(wf.env.ULAMS_PREVIEW_TOKEN).toBe("${{ secrets.ULAMS_PREVIEW_TOKEN }}");
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
    expect(wf.on.pull_request.paths).toBeUndefined();
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

describe("course preview", () => {
  const PLATFORM_URL = "http://platform.localhost";
  const previewEnv = { ...env, ULAMS_PREVIEW_URL: PLATFORM_URL, ULAMS_PREVIEW_TOKEN: "platform-secret-1" };
  const emptyCourse = (): Out => ({ schemaVersion: 3, sources: [], course: { id: "01j9z8x7w6v5t4s3r2q1p0n9aa", title: "Preview", language: "en", objectives: [] }, modules: [], finalTest: null, pages: { landing: null, header: null } });

  /** A preview platform and the academy it creates, behind one fetch (requests are routed by host). */
  function world(over: { tenant?: Out | null; tokenStatus?: number } = {}) {
    const academy = fakeCourseAsCode({ fresh: true, document: emptyCourse(), applyTicks: 1 });
    const tenants = new Map<string, Out>();
    if (over.tenant) tenants.set("pr7acme", over.tenant);
    let polls = 0;
    const log: string[] = [];
    const tenantRecord = (status = "active"): Out => ({ slug: "pr7acme", status, expires_at: "2026-10-30T10:00:00+00:00", urls: { api: "http://pr7acme.localhost", front: "http://pr7acme.app.localhost", admin: "http://pr7acme.admin.localhost" } });
    const handler: Handler = (req) => {
      const host = new URL(req.url).host;
      if (host === "pr7acme.localhost") {
        log.push(`academy ${req.method} ${req.path}`);
        if (req.headers.authorization !== "Bearer ulams_pat_preview") return { status: 401, body: { success: false, message: "Unauthenticated." } };
        return academy.handler(req);
      }
      log.push(`platform ${req.method} ${req.path}`);
      if (req.headers.authorization !== "Bearer platform-secret-1") return { status: 401, body: { success: false, message: "Unauthenticated." } };
      if (req.method === "GET" && req.path === "/api/platform/tenants/pr7acme") return tenants.has("pr7acme") ? { body: { success: true, data: tenants.get("pr7acme") } } : { status: 404, body: { success: false, message: "Tenant not found." } };
      if (req.method === "POST" && req.path === "/api/platform/tenants") {
        tenants.set("pr7acme", tenantRecord());
        return { status: 202, body: { success: true, data: { operation: { id: "01op", status: "queued" } } } };
      }
      if (req.method === "GET" && req.path === "/api/platform/operations/01op") return { body: { success: true, data: { id: "01op", status: ++polls < 2 ? "running" : "succeeded", error: null } } };
      if (req.method === "POST" && req.path === "/api/platform/tenants/pr7acme/token") {
        if (over.tokenStatus) return { status: over.tokenStatus, body: { success: false, message: "Only preview academies hand out a token." } };
        return { body: { success: true, data: { token: "ulams_pat_preview", expires_at: "2026-10-23T10:00:00+00:00", scopes: ["builder:read", "builder:write"], urls: tenants.get("pr7acme")?.urls } } };
      }
      return { status: 404, body: { success: false, message: "no mock" } };
    };
    return { academy, tenants, log, routes: { "*": handler } as Record<string, Handler>, tenantRecord };
  }

  async function folder() {
    const fake = fakeCourseAsCode();
    const dir = join(tmp(), "course");
    const r = await cli(["course", "init", dir, "--from-course", "12", "--json"], fake.routes);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    return dir;
  }

  const run = async (args: string[], routes: Record<string, Handler>, e: Record<string, string> = previewEnv) => {
    const r = await runCli(args, { env: e, routes });
    cleanups.push(r.cleanup);
    return Object.assign(r, { body: (): Out => r.json() as Out, data: (): Out => ((r.json() as Out).data ?? {}) as Out, error: (): Out => ((r.json() as Out).error ?? {}) as Out });
  };

  it("creates the preview academy, builds the folder into it and prints where to look, without citations", async () => {
    const dir = await folder();
    const w = world();

    const r = await run(["course", "preview", dir, "--tenant", "pr7acme", "--group", "acme-course", "--json"], w.routes);

    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(r.data()).toMatchObject({ tenant: "pr7acme", created: true, applied: true, courseId: 12, urls: { front: "http://pr7acme.app.localhost" } });
    const create = r.requests.find((q) => q.method === "POST" && q.path === "/api/platform/tenants");
    expect(create?.body).toMatchObject({ slug: "pr7acme", demo: true, ttl_days: 7, group: "acme-course" });
    // the course in the preview is the folder's, written by the author, with nothing citing a source
    const live = JSON.stringify(w.academy.db.live);
    expect(live).toContain("kubectl is the command-line tool");
    expect(live).not.toMatch(/frg_[a-z2-7]{12}/);
    expect(w.academy.db.createCalls[0]).toMatchObject({ title: expect.any(String), key: "acme-course:pr7acme" });
    const tail = w.log.filter((l) => l.startsWith("academy")).map((l) => l.replace("academy ", ""));
    expect(tail.slice(0, 5)).toEqual(["POST /api/admin/course-as-code/courses", "POST /api/admin/course-as-code/sessions/" + w.academy.sessionId + "/imports", expect.stringMatching(/approve$/), expect.stringMatching(/apply$/), expect.stringMatching(/\/sessions\/[^/]+$/)]);
    // the academy's token is never printed, and the real academy was not called at all
    expect(r.stdout + r.stderr).not.toContain("ulams_pat_preview");
    expect(r.stdout + r.stderr).not.toContain("platform-secret-1");
    expect(r.requests.every((q) => !q.url.startsWith("http://coffee.localhost"))).toBe(true);
  });

  it("a second run updates the same preview: the academy is reused and an unchanged folder pushes nothing new", async () => {
    const dir = await folder();
    const w = world();
    await run(["course", "preview", dir, "--tenant", "pr7acme", "--group", "g", "--json"], w.routes);
    const again = await run(["course", "preview", dir, "--tenant", "pr7acme", "--group", "g", "--json"], w.routes);

    expect(again.code, again.stdout + again.stderr).toBe(0);
    expect(again.data()).toMatchObject({ created: false, unchanged: true, applied: false });
    expect(again.requests.some((q) => q.method === "POST" && q.path === "/api/platform/tenants")).toBe(false);
    expect(w.academy.db.createCalls.map((c) => c.key)).toEqual(["g:pr7acme", "g:pr7acme"]);
  });

  it("refuses a real academy, a missing platform and a platform without the API, before touching anything", async () => {
    const dir = await folder();
    const real = world({ tenant: { slug: "pr7acme", status: "active", expires_at: null, urls: { api: "http://pr7acme.localhost", front: "x", admin: "y" } } });
    const refused = await run(["course", "preview", dir, "--tenant", "pr7acme", "--json"], real.routes);
    expect(refused.code).toBe(4);
    expect(refused.error().message).toContain("real academy");
    expect(real.log.some((l) => l.includes("token"))).toBe(false);

    const none = await run(["course", "preview", dir, "--tenant", "pr7acme", "--json"], real.routes, env);
    expect(none.code).toBe(3);
    expect(none.error().hint).toContain("ULAMS_PREVIEW_URL");
    expect(none.requests).toHaveLength(0);

    const off = await run(["course", "preview", dir, "--tenant", "pr7acme", "--json"], { "*": { status: 404, body: { success: false, message: "Not found." } } });
    expect(off.code).toBe(12);
  });

  it("a folder with errors is refused before the platform is called, and --dry-run sends nothing", async () => {
    const dir = await folder();
    const fs2 = await import("node:fs");
    const bad = join(dir, "modules/01-basics/01-install.md");
    const good = fs2.readFileSync(bad, "utf8");
    fs2.writeFileSync(bad, good.replace("---\nid:", "---\nidd:"));
    const w = world();
    const refused = await run(["course", "preview", dir, "--tenant", "pr7acme", "--json"], w.routes);
    expect(refused.code).toBe(7);
    expect(refused.requests).toHaveLength(0);

    fs2.writeFileSync(bad, good);
    const dry = await run(["course", "preview", dir, "--tenant", "pr7acme", "--dry-run", "--json"], w.routes);
    expect(dry.code, dry.stdout + dry.stderr).toBe(0);
    expect(dry.requests).toHaveLength(0);
    expect(JSON.stringify(dry.body())).toContain("ttl_days");
  });

  it("without --tenant it only proposes the folder as a version and never approves", async () => {
    const dir = await folder();
    const { writeFileSync, readFileSync } = await import("node:fs");
    const file = join(dir, "modules/01-basics/01-install.md");
    writeFileSync(file, readFileSync(file, "utf8").replace("kubectl is the command-line tool for Kubernetes clusters.", "kubectl is the CLI."));
    const fake = fakeCourseAsCode();
    const r = await cli(["course", "init", join(tmp(), "c2"), "--from-course", "12", "--json"], fake.routes);
    expect(r.code).toBe(0);

    const pushed = await cli(["course", "preview", dir, "--json"], fake.routes);
    expect(pushed.code, pushed.stdout + pushed.stderr).toBe(0);
    expect(pushed.data()).toMatchObject({ pushed: true, approved: false, applied: false, version: { status: "proposed" } });
  });
});

describe("course adopt", () => {
  const skipped = [{ topicId: 77, title: "Screencast", type: "Video", reason: "This kind of topic needs its file to travel with the course (asset download), which is not built yet; the topic stays as it is and is never changed by an apply." }];

  it("refuses init for a hand-made course and points at adopt, then adopt makes init work", async () => {
    const fake = fakeCourseAsCode({ handMade: { skipped } });
    const dir = join(tmp(), "course");

    const refused = await cli(["course", "init", dir, "--from-course", "12", "--json"], fake.routes);
    expect(refused.code).toBe(6);
    expect(refused.error()).toMatchObject({ code: "CONFLICT", details: { code: "NOT_ADOPTED" }, hint: expect.stringContaining("ulams course adopt") });
    expect(fs.existsSync(dir)).toBe(false);

    const adopted = await cli(["course", "adopt", "12", "--json"], fake.routes);
    expect(adopted.code, adopted.stdout + adopted.stderr).toBe(0);
    expect(adopted.data()).toMatchObject({ created: true, courseId: 12, skipped: [{ topicId: 77, type: "Video" }] });

    const init = await cli(["course", "init", dir, "--from-course", "12", "--json"], fake.routes);
    expect(init.code, init.stdout + init.stderr).toBe(0);
    expect(fs.existsSync(join(dir, "ulams.yaml"))).toBe(true);
  });

  it("says what was left out and why, in text, and is idempotent", async () => {
    const fake = fakeCourseAsCode({ handMade: { skipped } });
    const first = await cli(["course", "adopt", "12"], fake.routes);
    expect(first.code).toBe(0);
    expect(first.stdout).toContain("Adopted course 12");
    expect(first.stdout).toContain("1 topic was left out and stay as they are");
    expect(first.stdout).toContain("Screencast (Video)");
    expect(first.stdout).toContain("ulams course init ./my-course --from-course 12");

    const again = await cli(["course", "adopt", "12"], fake.routes);
    expect(again.stdout).toContain("was adopted before");
    expect(fake.db.adoptCalls).toBe(2);
  });

  it("--dry-run sends nothing, a bad id is refused before any request and an unknown course is exit 5", async () => {
    const fake = fakeCourseAsCode({ handMade: {} });
    const dry = await cli(["course", "adopt", "12", "--dry-run", "--json"], fake.routes);
    expect(dry.code, dry.stdout + dry.stderr).toBe(0);
    expect(dry.requests).toHaveLength(0);
    expect(JSON.stringify(dry.body())).toContain("/adopt");
    const bad = await cli(["course", "adopt", "abc", "--json"], fake.routes);
    expect(bad.code).toBe(2);
    expect(bad.requests).toHaveLength(0);
    const unknown = await cli(["course", "adopt", "99", "--json"], fake.routes);
    expect(unknown.code).toBe(5);
  });
});
