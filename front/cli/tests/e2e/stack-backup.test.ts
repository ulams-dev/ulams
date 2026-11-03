import { describe, expect, it } from "vitest";
import { spawn } from "node:child_process";
import { createHash } from "node:crypto";
import { mkdtemp, readdir, readFile, rm } from "node:fs/promises";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";

/**
 * Backup, wipe and restore of a real Docker stack (nightly-ops.yml): demo courses with media and H5P
 * content and a learner with progress go through `stack backup`, `stack down --purge` (every volume is
 * deleted) and `stack restore`, and must come back unchanged.
 *   ULAMS_STACK_BACKUP_E2E=1 yarn workspace ulams vitest run tests/e2e/stack-backup.test.ts
 */
const enabled = process.env.ULAMS_STACK_BACKUP_E2E === "1";
const cli = process.env.ULAMS_STACK_E2E_CLI ?? resolve(import.meta.dirname, "../../dist/ulams.mjs");
type Json = Record<string, unknown>;

function run(program: string, args: string[], env: NodeJS.ProcessEnv, stdin?: string): Promise<{ code: number | null; out: string; err: string }> {
  return new Promise((done, reject) => {
    const child = spawn(program, args, { env, stdio: ["pipe", "pipe", "pipe"] });
    let out = "", err = "";
    child.stdout.on("data", c => out += String(c)); child.stderr.on("data", c => err += String(c));
    child.on("error", reject); child.on("close", code => done({ code, out, err }));
    child.stdin.end(stdin);
  });
}
async function invoke(args: string[], env: NodeJS.ProcessEnv, stdin?: string): Promise<unknown> {
  console.info(`E2E: ${args.slice(0, 2).join(" ")}`);
  const { code, out, err } = await run(process.execPath, [cli, ...args, "--json"], env, stdin);
  let response: Json;
  try { response = JSON.parse(out.trim()) as Json; } catch { throw new Error(`No JSON from ${args.slice(0, 2).join(" ")}: ${err}`); }
  if (code !== 0) throw new Error(`${args.slice(0, 2).join(" ")}: ${JSON.stringify(response.error)} ${err.slice(-3000)}`);
  return response.data;
}
async function fetchBytes(url: string, env: NodeJS.ProcessEnv): Promise<string> {
  // *.localhost resolves to 127.0.0.1 without /etc/hosts entries
  const script = `const u=new URL(process.argv[1]);const lib=await import('node:http');lib.get(u,{agent:false,lookup:(h,o,cb)=>o&&o.all?cb(null,[{address:'127.0.0.1',family:4}]):cb(null,'127.0.0.1',4)},r=>{if(r.statusCode!==200){console.error('status '+r.statusCode);process.exit(1)}const c=[];r.on('data',d=>c.push(d));r.on('end',()=>process.stdout.write(Buffer.concat(c).toString('base64')))}).on('error',e=>{console.error(e.message);process.exit(1)});`;
  const { code, out, err } = await run(process.execPath, ["--input-type=module", "-e", script, url], env);
  if (code !== 0) throw new Error(`GET ${url}: ${err}`);
  return createHash("sha256").update(Buffer.from(out, "base64")).digest("hex");
}
const list = (value: unknown): Json[] => (Array.isArray(value) ? value : []) as Json[];

describe.skipIf(!enabled)("backup, wipe and restore of a real Docker stack", () => {
  it("brings back courses, media, H5P content and learner progress after every volume was deleted", async () => {
    const root = await mkdtemp(join(tmpdir(), "ulams-backup-live-"));
    const directory = join(root, "stack");
    const name = `ulams-bk-${Date.now().toString(36)}`;
    const httpPort = process.env.ULAMS_STACK_E2E_HTTP_PORT ?? "19280";
    const base: NodeJS.ProcessEnv = { ...process.env, ULAMS_BACKUP_PASSPHRASE: "nightly backup passphrase" };
    const admin = { ...base, ULAMS_CONFIG_DIR: join(root, "admin") }, student = { ...base, ULAMS_CONFIG_DIR: join(root, "student") };
    const flags = ["--directory", directory, "--name", name, "--http-port", httpPort, "--image-registry", process.env.ULAMS_STACK_E2E_IMAGE_REGISTRY ?? "ghcr.io/ulams-dev", "--image-tag", process.env.ULAMS_STACK_E2E_IMAGE_TAG ?? "latest"];
    const artisan = async (...args: string[]) => {
      const compose = ["compose", "--project-name", name, "--env-file", join(directory, ".env"), "-f", join(directory, "compose.yaml"), "exec", "-T", "api", "php", "artisan", ...args];
      const result = await run("docker", compose, base);
      if (result.code !== 0) throw new Error(`artisan ${args[0]}: ${result.err.slice(-3000)} ${result.out.slice(-3000)}`);
      return result.out;
    };
    let installed = false, passed = false;
    try {
      // set before the launch, so a failed launch still gets its logs printed and its resources removed
      installed = true;
      const up = await invoke(["stack", "up", ...flags], base) as Json;
      expect(up.ready).toBe(true);
      const urls = up.urls as Record<string, string>;
      const secrets = JSON.parse(await readFile(join(directory, "credentials.json"), "utf8")) as Record<string, string>;

      // Content: a demo course with images and H5P activities, granted to the demo student.
      await artisan("ulams:demo:seed", "--experience=coffee", "--domain=academy-api.ulams.localhost", "--no-interaction");
      await invoke(["login", "--url", urls.academyApi!, "--email", "admin@academy.ulams.localhost", "--password-stdin"], admin, `${secrets.TENANT_INITIAL_PASSWORD}\n`);
      await invoke(["login", "--url", urls.academyApi!, "--email", "student1@academy.ulams.localhost", "--password-stdin"], student, `${secrets.TENANT_INITIAL_PASSWORD}\n`);
      // The learner's own views: the demo seed granted the student its course.
      const granted = list(await invoke(["my", "courses-progress"], student)).map(p => p.course as Json);
      expect(granted.length, "a course granted to the demo student").toBeGreaterThan(0);
      const program = await invoke(["my", "courses-program", String(granted[0]!.id)], student) as Json;
      const course = { id: program.id, image_url: program.image_url };
      expect(course.image_url, "a seeded course with an image").toBeTruthy();
      const topic = list((program.lessons as Json[] | undefined)?.flatMap(l => list(l.topics)) ?? []).find(t => t.id)!;
      expect(topic, "a topic in the seeded course").toBeTruthy();
      await invoke(["api", "PATCH", `/api/courses/progress/${String(course.id)}`, "--body", JSON.stringify({ progress: [{ topic_id: topic.id, status: 1 }] })], student);

      const snapshot = async () => ({
        // signed-in course lists (#495: they answered 500 after an artisan command had created the day's log as root)
        courses: list(await invoke(["courses", "list", "--all"], admin)).map(c => [c.id, c.title]).sort(),
        catalogue: list(await invoke(["public", "courses-list", "--all"], student)).map(c => c.id).sort(),
        h5p: list(await invoke(["h5p", "contents", "--all"], admin)).map(h => [h.id, h.title]).sort(),
        // started or finished topics only: a not-started row can appear later when a queued job (video processing)
        // activates a seeded topic, which is unrelated to the backup
        progress: list(await invoke(["my", "courses-progress"], student)).map(p => [(p.course as Json).id, (p.course as Json).title, JSON.stringify(list(p.progress).filter(t => t.status).map(t => [t.topic_id, t.status]).sort())]).sort(),
        lessons: list((await invoke(["my", "courses-program", String(course.id)], student) as Json).lessons).map(l => [l.id, l.title, list(l.topics).length]),
        image: await fetchBytes(String(course.image_url), base),
      });
      const before = await snapshot();
      expect(before.h5p.length).toBeGreaterThan(0);
      expect(JSON.stringify(before.progress)).toContain(`[${topic.id},1]`);

      const backup = await invoke(["stack", "backup", "--directory", directory], base) as Json;
      expect(backup).toMatchObject({ credentialsIncluded: true });
      expect((backup.databases as string[])).toEqual(expect.arrayContaining(["ulams", "ulams_academy"]));
      await invoke(["stack", "down", "--directory", directory, "--purge", "--yes"], base);
      const volumes = await run("docker", ["volume", "ls", "--quiet", "--filter", `label=com.docker.compose.project=${name}`], base);
      expect(volumes.out.trim()).toBe("");

      const restored = await invoke(["stack", "restore", String(backup.backup), "--directory", directory, "--yes"], base) as Json;
      expect(restored.restored).toBe(true);
      expect(await snapshot()).toEqual(before);
      // stack restore ran ulams:upgrade, which lists the tenant bucket: it only succeeds with the per-tenant
      // storage key issued after the restore (the restored object store has no identities).
      expect(await readdir(join(directory, "backups"))).toHaveLength(1);
      passed = true;
    } finally {
      if (installed && !passed) {
        // the purge below deletes the containers: print their logs first
        const logs = await run("docker", ["compose", "--project-name", name, "--env-file", join(directory, ".env"), "-f", join(directory, "compose.yaml"), "logs", "--no-color", "--tail", "200"], base);
        console.error(logs.out.slice(-60000));
      }
      if (installed) await invoke(["stack", "down", "--directory", directory, "--purge", "--yes"], base).catch(() => undefined);
      await rm(root, { recursive: true, force: true });
    }
  }, 2400000);
});
