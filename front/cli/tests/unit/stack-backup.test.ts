import { describe, expect, it } from "vitest";
import { execFileSync } from "node:child_process";
import { chmod, mkdir, mkdtemp, readFile, readdir, rm, writeFile } from "node:fs/promises";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { stackInput } from "../../src/stack/config.ts";
import { renderInstall, withInstall } from "../../src/stack/files.ts";
import { checkRelease, decrypt, encrypt, parseRelease, readManifest, verifyBackup, type BackupManifest } from "../../src/stack/backup.ts";
import { runCli } from "../helpers.ts";

const PASS = "correct horse battery staple";

/** A `docker` that answers like the stack's containers and records every call (argv and stdin). */
async function fakeDocker(root: string) {
  const bin = join(root, "bin"), log = join(root, "calls.ndjson");
  await mkdir(bin);
  const script = `#!${process.execPath}
const fs = require("node:fs");
const args = process.argv.slice(2), line = args.join(" ");
const chunks = [];
const done = () => {
  const stdin = Buffer.concat(chunks).toString("utf8");
  fs.appendFileSync(${JSON.stringify(log)}, JSON.stringify({ args, stdin, rcloneSecret: process.env.RCLONE_CONFIG_S_SECRET_ACCESS_KEY ?? null }) + "\\n");
  if (/^(ps|volume) /.test(line)) return;
  if (line.includes("pg_dumpall")) return process.stdout.write("-- roles\\n");
  if (line.includes("select datname")) return process.stdout.write("ulams\\nulams_academy\\n");
  if (line.includes("pg_dump ")) return process.stdout.write("DUMP " + args[args.length - 1]);
  if (line.includes("rclone lsf")) return process.stdout.write("other/\\nulams/\\nulams-academy/\\n");
  if (line.includes("tar czf - -C /w buckets")) return process.stdout.write("BUCKETS");
  if (line.includes("api_storage") && line.includes("czf")) return process.stdout.write("API-STORAGE");
  if (line.includes("h5p_libraries") && line.includes("czf")) return process.stdout.write("H5P-LIBRARIES");
};
if (process.stdin.isTTY) done(); else { process.stdin.on("data", c => chunks.push(c)); process.stdin.on("end", done); process.stdin.on("error", done); }
`;
  await writeFile(join(bin, "docker"), script);
  await chmod(join(bin, "docker"), 0o755);
  const calls = async () => (await readFile(log, "utf8").catch(() => "")).trim().split("\n").filter(Boolean).map(l => JSON.parse(l) as { args: string[]; stdin: string; rcloneSecret: string | null });
  return { env: { PATH: `${bin}:${process.env.PATH ?? ""}` }, calls, reset: () => rm(log, { force: true }) };
}
async function install(root: string) {
  const directory = join(root, "stack");
  await withInstall(directory, () => renderInstall(directory, stackInput.parse({ name: "unit" })));
  return directory;
}
async function only(dir: string) {
  const names = await readdir(join(dir, "backups"));
  expect(names).toHaveLength(1);
  return join(dir, "backups", names[0]!);
}

describe("stack backup and restore", () => {
  it("encrypts like openssl enc -aes-256-cbc -pbkdf2 -iter 200000 and refuses a wrong passphrase", () => {
    const sealed = encrypt(Buffer.from("{\"APP_KEY\":\"x\"}"), PASS);
    expect(sealed.subarray(0, 8).toString()).toBe("Salted__");
    expect(decrypt(sealed, PASS).toString()).toBe("{\"APP_KEY\":\"x\"}");
    expect(() => decrypt(sealed, "another passphrase")).toThrow("Wrong backup passphrase");
    let openssl = true;
    try { execFileSync("openssl", ["version"], { stdio: "ignore" }); } catch { openssl = false; }
    if (openssl) {
      const plain = execFileSync("openssl", ["enc", "-d", "-aes-256-cbc", "-pbkdf2", "-iter", "200000", "-pass", "env:P"], { input: sealed, env: { ...process.env, P: PASS } });
      expect(plain.toString()).toBe("{\"APP_KEY\":\"x\"}");
    }
  });

  it("refuses a backup from a newer release, CLI or format, and an unknown release order", () => {
    const m = (imageTag: string, cli = "0.2.0", format = 1) => ({ format, cli, stack: { imageTag } } as BackupManifest);
    expect(parseRelease("v1.2.3")).toEqual([1, 2, 3]);
    expect(parseRelease("sha-abc1234")).toBeUndefined();
    expect(() => checkRelease(m("1.4.0"), "0.2.0", "1.3.9")).toThrow("newer than the installed 1.3.9");
    expect(() => checkRelease(m("1.3.0"), "0.2.0", "1.10.0")).not.toThrow();
    expect(() => checkRelease(m("sha-abc"), "0.2.0", "sha-abc")).not.toThrow();
    expect(() => checkRelease(m("sha-abc"), "0.2.0", "sha-def")).toThrow("order is unknown");
    expect(() => checkRelease(m("1.0.0"), "0.2.0", undefined)).not.toThrow();
    expect(() => checkRelease(m("1.0.0", "0.9.0"), "0.2.0", "1.0.0")).toThrow("newer than this CLI");
    expect(() => checkRelease(m("1.0.0", "0.2.0", 2), "0.2.0", "1.0.0")).toThrow("format 2");
  });

  it("backs up every database, the ulams buckets and both volumes, with checksums and no secret on a command line", async () => {
    const root = await mkdtemp(join(tmpdir(), "ulams-backup-"));
    try {
      const docker = await fakeDocker(root), directory = await install(root);
      const credentials = JSON.parse(await readFile(join(directory, "credentials.json"), "utf8")) as Record<string, string>;
      const result = await runCli(["stack", "backup", "--directory", directory, "--json"], { env: { ...docker.env, ULAMS_BACKUP_PASSPHRASE: PASS } });
      try {
        expect(result.code).toBe(0);
        const output = result.json() as { data: { backup: string; databases: string[]; buckets: string[]; credentialsIncluded: boolean } };
        expect(output.data).toMatchObject({ databases: ["ulams", "ulams_academy"], buckets: ["ulams", "ulams-academy"], credentialsIncluded: true });
        const dir = await only(directory);
        const manifest = await readManifest(dir);
        expect(Object.keys(manifest.files).sort()).toEqual(["api-storage.tar.gz", "buckets.tar.gz", "db-ulams.dump", "db-ulams_academy.dump", "globals.sql", "h5p-libraries.tar.gz", "install.json.enc"]);
        expect(await readFile(join(dir, "db-ulams_academy.dump"), "utf8")).toBe("DUMP ulams_academy");
        await expect(verifyBackup(dir, manifest)).resolves.toBeUndefined();
        const bundle = JSON.parse(decrypt(await readFile(join(dir, "install.json.enc")), PASS).toString()) as Record<string, string>;
        expect(JSON.parse(bundle["credentials.json"]!)).toEqual(credentials);

        const calls = await docker.calls();
        const bucketCopy = calls.find(call => call.args.join(" ").includes("tar czf - -C /w buckets"))!;
        expect(bucketCopy.args.join(" ")).toContain("rclone copy s:ulams-academy /w/buckets/ulams-academy");
        expect(bucketCopy.args.join(" ")).not.toContain("other");
        expect(bucketCopy.args).toContain("RCLONE_CONFIG_S_SECRET_ACCESS_KEY");
        expect(bucketCopy.rcloneSecret).toBe(credentials.S3_SECRET);
        // Credentials reach the containers through the environment, never through argv, the manifest or the output.
        const everything = JSON.stringify(calls.map(c => c.args)) + JSON.stringify(manifest) + result.stdout + result.stderr;
        for (const [key, value] of Object.entries(credentials)) if (value.length > 8) expect(everything, key).not.toContain(value);
        expect(calls.find(call => call.args.includes("--exclude=./logs"))!.args).toContain("unit_api_storage:/v:ro");
      } finally { result.cleanup(); }
    } finally { await rm(root, { recursive: true, force: true }); }
  });

  it("restores in order: data services, roles, databases, buckets, volumes, application, new storage keys and ulams:upgrade", async () => {
    const root = await mkdtemp(join(tmpdir(), "ulams-restore-"));
    try {
      const docker = await fakeDocker(root), directory = await install(root);
      const backup = await runCli(["stack", "backup", "--directory", directory, "--json"], { env: docker.env });
      try {
        expect(backup.code).toBe(0);
        expect(backup.json()).toMatchObject({ warnings: [{ code: "CREDENTIALS_NOT_INCLUDED" }] });
      } finally { backup.cleanup(); }
      const dir = await only(directory);
      await docker.reset();

      const unconfirmed = await runCli(["stack", "restore", dir, "--directory", directory, "--json"], { env: docker.env });
      try { expect(unconfirmed.json()).toMatchObject({ error: { code: "CONFIRMATION_REQUIRED" } }); } finally { unconfirmed.cleanup(); }

      const restored = await runCli(["stack", "restore", dir, "--directory", directory, "--yes", "--json"], { env: docker.env });
      try {
        expect(restored.code).toBe(0);
        expect(restored.json()).toMatchObject({ data: { restored: true, databases: ["ulams", "ulams_academy"] } });
      } finally { restored.cleanup(); }
      const steps = (await docker.calls()).filter(call => !/^(ps|volume)$/.test(call.args[0]!));
      const index = (needle: string) => steps.findIndex(call => call.args.join(" ").includes(needle));
      const order = ["up --no-start", "up --detach --wait --wait-timeout 900 postgres valkey object-store", "stop api h5p", "pg_terminate_backend", "psql -U ulams_owner -d postgres -q", "pg_restore", "rclone sync", "unit_api_storage:/v alpine", "ulams:tenant:ensure-buckets", "--rotate-storage-keys", "ulams:upgrade", "health:check"];
      const positions = order.map(index);
      expect(positions.every(p => p >= 0), JSON.stringify(steps.map(s => s.args.slice(-6)))).toBe(true);
      expect([...positions].sort((a, b) => a - b)).toEqual(positions);
      expect(steps.find(call => call.args.includes("ON_ERROR_STOP=0"))!.stdin).toBe("-- roles\n");
      expect(steps.filter(call => call.args.includes("pg_restore")).map(call => call.stdin)).toEqual(["DUMP ulams", "DUMP ulams_academy"]);
      expect(steps.find(call => call.args.join(" ").includes("rclone sync"))!.stdin).toBe("BUCKETS");
    } finally { await rm(root, { recursive: true, force: true }); }
  });

  it("checks the backup before touching the stack: damaged files, another installation, missing credentials", async () => {
    const root = await mkdtemp(join(tmpdir(), "ulams-restore-refuse-"));
    try {
      const docker = await fakeDocker(root), directory = await install(root);
      const backup = await runCli(["stack", "backup", "--directory", directory, "--json"], { env: docker.env });
      backup.cleanup();
      const dir = await only(directory);
      await docker.reset();

      const secrets = await runCli(["stack", "restore", dir, "--directory", join(root, "new"), "--restore-secrets", "--yes", "--json"], { env: { ...docker.env, ULAMS_BACKUP_PASSPHRASE: PASS } });
      try { expect(secrets.json()).toMatchObject({ error: { message: expect.stringContaining("no credentials") } }); } finally { secrets.cleanup(); }

      const other = await install(join(root, "other"));
      const foreign = await runCli(["stack", "restore", dir, "--directory", other, "--yes", "--json"], { env: docker.env });
      try { expect(foreign.json()).toMatchObject({ error: { code: "CONFLICT", message: expect.stringContaining("another installation") } }); } finally { foreign.cleanup(); }

      await writeFile(join(dir, "db-ulams.dump"), "TAMPERED");
      const damaged = await runCli(["stack", "restore", dir, "--directory", directory, "--yes", "--json"], { env: docker.env });
      try { expect(damaged.json()).toMatchObject({ error: { code: "CONFLICT", message: expect.stringContaining("db-ulams.dump does not match") } }); } finally { damaged.cleanup(); }
      expect(await docker.calls()).toEqual([]);
    } finally { await rm(root, { recursive: true, force: true }); }
  });

  it("rebuilds the install in an empty directory from the encrypted credentials", async () => {
    const root = await mkdtemp(join(tmpdir(), "ulams-restore-new-"));
    try {
      const docker = await fakeDocker(root), directory = await install(root);
      const env = { ...docker.env, ULAMS_BACKUP_PASSPHRASE: PASS };
      const backup = await runCli(["stack", "backup", "--directory", directory, "--json"], { env });
      backup.cleanup();
      const dir = await only(directory), fresh = join(root, "fresh");

      const wrong = await runCli(["stack", "restore", dir, "--directory", fresh, "--restore-secrets", "--yes", "--json"], { env: { ...docker.env, ULAMS_BACKUP_PASSPHRASE: "not the passphrase" } });
      try { expect(wrong.json()).toMatchObject({ error: { message: "Wrong backup passphrase." } }); } finally { wrong.cleanup(); }

      const restored = await runCli(["stack", "restore", dir, "--directory", fresh, "--restore-secrets", "--yes", "--json"], { env });
      try { expect(restored.code).toBe(0); } finally { restored.cleanup(); }
      expect(await readFile(join(fresh, "credentials.json"), "utf8")).toBe(await readFile(join(directory, "credentials.json"), "utf8"));
      expect(JSON.parse(await readFile(join(fresh, "stack.json"), "utf8")).id).toBe(JSON.parse(await readFile(join(directory, "stack.json"), "utf8")).id);
      expect(await readFile(join(fresh, "compose.yaml"), "utf8")).toContain("name: unit");
    } finally { await rm(root, { recursive: true, force: true }); }
  });
});
