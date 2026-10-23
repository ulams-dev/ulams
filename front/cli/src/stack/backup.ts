import { createCipheriv, createDecipheriv, createHash, pbkdf2Sync, randomBytes } from "node:crypto";
import { lstat, mkdir, mkdtemp, readFile, rm, writeFile } from "node:fs/promises";
import { homedir } from "node:os";
import { basename, join, resolve } from "node:path";
import { z } from "zod";
import { CliError } from "../errors.ts";
import type { Ctx, Warning } from "../registry/types.ts";
import { INFRA_IMAGES, stackInput, type Secrets, type StackConfig } from "./config.ts";
import { atomicWrite, installDirectory, readConfig, readSecrets, renderInstall, withInstall } from "./files.ts";
import { runProcess, runStream } from "./process.ts";
import { applyKubernetes, composeArgs, kubectlArgs, options, ownedDocker, ownedKubernetes, waitStatefulSet } from "./runtime.ts";

/**
 * `ulams stack backup` and `ulams stack restore` for CLI-owned stacks (Docker and Kubernetes).
 *
 * One backup is a directory:
 *   manifest.json            format, stack identity, release, databases, buckets, sha256 of every file
 *   globals.sql              PostgreSQL roles (pg_dumpall --globals-only)
 *   db-<name>.dump           pg_dump -Fc of the platform database and of every tenant database
 *   buckets.tar.gz           every ulams* bucket, copied with rclone
 *   api-storage.tar.gz       the API storage volume (keys, local disks), without logs and the H5P service config
 *   h5p-libraries.tar.gz     installed H5P libraries
 *   install.json.enc         stack.json, credentials.json (and ca.crt): AES-256-CBC, PBKDF2 200000, the same
 *                            format as `openssl enc -aes-256-cbc -pbkdf2 -iter 200000 -salt` in the VPS scripts
 */
export const BACKUP_FORMAT = 1;
export const RCLONE_IMAGE = "rclone/rclone:1.75.2";
export const HELPER_IMAGE = "alpine:3.24";
const OWNER = "ulams_owner";
const ITERATIONS = 200000;
const PASSPHRASE_ENV = "ULAMS_BACKUP_PASSPHRASE";
const DATA_SERVICES = ["postgres","valkey","object-store"];
const APP_SERVICES = ["api","h5p","web","admin","pdf","mjml","caddy"];
const VOLUMES = [
  { file: "api-storage.tar.gz", volume: "api_storage", container: "api", path: "/var/www/html/storage", exclude: ["./logs","./h5p-service"] },
  { file: "h5p-libraries.tar.gz", volume: "h5p_libraries", container: "h5p", path: "/data/libraries", exclude: [] as string[] },
] as const;

export interface BackupManifest {
  format: number; kind: "ulams-stack-backup"; createdAt: string; cli: string;
  stack: { id: string; name: string; target: StackConfig["target"]; mode: StackConfig["mode"]; domain: string; tenant: string; imageRegistry: string; imageTag: string };
  postgres: { image: string; databases: string[] };
  buckets: string[];
  secrets: boolean;
  files: Record<string, { size: number; sha256: string }>;
}

const remoteSpec = z.string().regex(/^[A-Za-z0-9][A-Za-z0-9_.-]*:[^\0]*$/,"An rclone remote such as offsite:ulams-backups");
export const backupInput = z.object({
  directory: z.string().min(1).optional().describe("Install directory; default ./ulams-stack."),
  to: z.string().min(1).optional().describe("Parent directory for the new backup; default <install directory>/backups."),
  remote: remoteSpec.optional().describe("Also copy the backup to this rclone remote (your rclone config), for example offsite:ulams-backups."),
  passphrase_file: z.string().min(1).optional().describe(`File with the passphrase that encrypts the stack credentials; or set ${PASSPHRASE_ENV}. Without one the credentials are left out.`),
});
export const restoreInput = z.object({
  archive: z.string().min(1).optional().describe("Backup directory made by stack backup."),
  remote: remoteSpec.optional().describe("Download the backup from this rclone remote path instead, for example offsite:ulams-backups/ulams-ulams-20261023T020000Z."),
  directory: z.string().min(1).optional().describe("Install directory; default ./ulams-stack."),
  restore_secrets: z.boolean().default(false).describe("Also restore stack.json and credentials.json from the backup (needed in a new, empty install directory)."),
  passphrase_file: z.string().min(1).optional().describe(`File with the backup passphrase; or set ${PASSPHRASE_ENV}.`),
  ready_timeout: z.coerce.number().int().min(30).max(3600).default(900),
});

// ---- encryption (openssl enc compatible) -----------------------------------------------------------

/** Same bytes as `openssl enc -aes-256-cbc -pbkdf2 -iter 200000 -salt` (sha256 digest): "Salted__", salt, ciphertext. */
export function encrypt(plain: Buffer, passphrase: string, salt = randomBytes(8)): Buffer {
  const material = pbkdf2Sync(passphrase, salt, ITERATIONS, 48, "sha256");
  const cipher = createCipheriv("aes-256-cbc", material.subarray(0,32), material.subarray(32));
  return Buffer.concat([Buffer.from("Salted__"), salt, cipher.update(plain), cipher.final()]);
}
export function decrypt(data: Buffer, passphrase: string): Buffer {
  if (data.length < 32 || data.subarray(0,8).toString("latin1") !== "Salted__") throw new CliError("CONFLICT", "install.json.enc is not an encrypted backup file.");
  const material = pbkdf2Sync(passphrase, data.subarray(8,16), ITERATIONS, 48, "sha256");
  try {
    const decipher = createDecipheriv("aes-256-cbc", material.subarray(0,32), material.subarray(32));
    return Buffer.concat([decipher.update(data.subarray(16)), decipher.final()]);
  } catch {
    throw new CliError("USAGE", "Wrong backup passphrase.");
  }
}
async function passphrase(ctx: Ctx, file: string | undefined, required: boolean): Promise<string | undefined> {
  let value = file ? (await readFile(resolve(file), "utf8")).replace(/\r?\n$/, "") : ctx.env[PASSPHRASE_ENV] ?? process.env[PASSPHRASE_ENV];
  if (value !== undefined && value.length < 12) throw new CliError("USAGE", "The backup passphrase needs at least 12 characters.");
  if (!value && required) throw new CliError("USAGE", `Restoring the credentials needs the backup passphrase: --passphrase-file or ${PASSPHRASE_ENV}.`);
  if (!value) value = undefined;
  return value;
}

// ---- releases ---------------------------------------------------------------------------------------

export function parseRelease(tag: string): [number, number, number] | undefined {
  const match = /^v?(\d+)\.(\d+)\.(\d+)$/.exec(tag);
  return match ? [Number(match[1]), Number(match[2]), Number(match[3])] : undefined;
}
const newer = (a: [number,number,number], b: [number,number,number]) => a[0] !== b[0] ? a[0] > b[0] : a[1] !== b[1] ? a[1] > b[1] : a[2] > b[2];
/** A backup restores into the same or a later release only; `ulams:upgrade` then brings its data forward. */
export function checkRelease(manifest: Pick<BackupManifest, "format" | "cli" | "stack">, cliVersion: string, currentTag?: string): void {
  if (manifest.format > BACKUP_FORMAT) throw new CliError("CONFLICT", `This backup has format ${manifest.format}; this CLI reads format ${BACKUP_FORMAT}. Use a newer ulams CLI.`);
  const cli = parseRelease(manifest.cli), own = parseRelease(cliVersion);
  if (cli && own && newer(cli, own)) throw new CliError("CONFLICT", `This backup was made by ulams CLI ${manifest.cli}, newer than this CLI (${cliVersion}). Use the newer CLI.`);
  if (!currentTag || currentTag === manifest.stack.imageTag) return;
  const backup = parseRelease(manifest.stack.imageTag), current = parseRelease(currentTag);
  if (backup && current) {
    if (newer(backup, current)) throw new CliError("CONFLICT", `This backup comes from release ${manifest.stack.imageTag}, newer than the installed ${currentTag}. Upgrade first: ulams stack up --image-tag ${manifest.stack.imageTag}.`);
    return;
  }
  throw new CliError("CONFLICT", `This backup comes from images ${manifest.stack.imageTag} and the install runs ${currentTag}; their order is unknown. Run the backup's release first: ulams stack up --image-tag ${manifest.stack.imageTag}.`);
}

// ---- manifest ---------------------------------------------------------------------------------------

const sha256 = (data: Buffer) => createHash("sha256").update(data).digest("hex");
async function fileSha256(path: string): Promise<{ size: number; sha256: string }> {
  const { createReadStream } = await import("node:fs");
  const hash = createHash("sha256");
  let size = 0;
  for await (const chunk of createReadStream(path) as AsyncIterable<Buffer>) { hash.update(chunk); size += chunk.length; }
  return { size, sha256: hash.digest("hex") };
}
export async function readManifest(dir: string): Promise<BackupManifest> {
  let manifest: BackupManifest;
  try { manifest = JSON.parse(await readFile(join(dir,"manifest.json"),"utf8")) as BackupManifest; }
  catch { throw new CliError("NOT_FOUND", `${dir} has no readable manifest.json: not a stack backup.`); }
  if (manifest.kind !== "ulams-stack-backup" || typeof manifest.format !== "number" || !manifest.files || !manifest.stack) throw new CliError("CONFLICT", `${dir}/manifest.json is not a stack backup manifest.`);
  return manifest;
}
/** Every listed file exists with its recorded size and checksum, and nothing the restore reads is unlisted. */
export async function verifyBackup(dir: string, manifest: BackupManifest): Promise<void> {
  for (const [name, expected] of Object.entries(manifest.files)) {
    if (name !== basename(name) || name.startsWith(".")) throw new CliError("CONFLICT", `Unexpected file name in the manifest: ${name}.`);
    const path = join(dir, name);
    const info = await lstat(path).catch(() => undefined);
    if (!info?.isFile()) throw new CliError("CONFLICT", `The backup is incomplete: ${name} is missing.`);
    const actual = await fileSha256(path);
    if (actual.size !== expected.size || actual.sha256 !== expected.sha256) throw new CliError("CONFLICT", `The backup is damaged: ${name} does not match its checksum.`);
  }
  for (const db of manifest.postgres.databases) if (!manifest.files[`db-${db}.dump`]) throw new CliError("CONFLICT", `The backup lists database ${db} but has no dump of it.`);
  if (!manifest.files["globals.sql"]) throw new CliError("CONFLICT", "The backup has no globals.sql.");
}

// ---- runtime adapters -------------------------------------------------------------------------------

const DB_NAME = /^[a-z][a-z0-9_]{0,62}$/;
const BUCKET = /^[a-z0-9][a-z0-9.-]{1,62}$/;
type Opts = ReturnType<typeof options>;
interface Runtime {
  /** Command line of a program in the PostgreSQL container. */
  postgres(args: string[], stdin: boolean): [string, string[]];
  /** rclone with the remote `s:` pointing at the bundled object store. */
  rclone(script: string, stdin: boolean): Promise<[string, string[]]>;
  /** tar of one data volume, to stdout (backup) or from stdin (restore). */
  volume(v: typeof VOLUMES[number], write: boolean): [string, string[]];
  cleanup(): Promise<void>;
}
const rcloneEnv = (s: Secrets) => ({ RCLONE_CONFIG: "/dev/null", RCLONE_CONFIG_S_TYPE: "s3", RCLONE_CONFIG_S_PROVIDER: "SeaweedFS", RCLONE_CONFIG_S_ENDPOINT: "http://object-store:8333", RCLONE_CONFIG_S_REGION: "us-east-1", RCLONE_CONFIG_S_ACCESS_KEY_ID: s.S3_KEY!, RCLONE_CONFIG_S_SECRET_ACCESS_KEY: s.S3_SECRET! });
const tarOut = (path: string, exclude: readonly string[]) => ["tar","czf","-","-C",path,...exclude.map(e=>`--exclude=${e}`),"."];
const tarIn = (path: string) => ["tar","xzf","-","-C",path];

function dockerRuntime(directory: string, c: StackConfig, s: Secrets, opts: Opts): Runtime {
  const env = rcloneEnv(s);
  Object.assign(opts.env, env);
  return {
    postgres: (args) => ["docker", [...composeArgs(directory,c),"exec","-T","postgres",...args]],
    rclone: async (script, stdin) => ["docker", ["run","--rm",...(stdin ? ["-i"] : []),"--network",`${c.name}_default`,...Object.keys(env).flatMap(name=>["-e",name]),"--entrypoint","sh",RCLONE_IMAGE,"-c",script]],
    volume: (v, write) => ["docker", ["run","--rm",...(write ? ["-i"] : []),"-v",`${c.name}_${v.volume}:/v${write ? "" : ":ro"}`,HELPER_IMAGE,...(write ? tarIn("/v") : tarOut("/v",v.exclude))]],
    cleanup: async () => undefined,
  };
}
function kubernetesRuntime(c: StackConfig, opts: Opts): Runtime {
  const pod = "ulams-backup-rclone";
  let started = false;
  const literal = rcloneEnv({ S3_KEY: "", S3_SECRET: "" });
  const env = Object.entries(literal).map(([name, value]) => name === "RCLONE_CONFIG_S_ACCESS_KEY_ID" ? { name, valueFrom: { secretKeyRef: { name: "credentials", key: "S3_KEY" } } } : name === "RCLONE_CONFIG_S_SECRET_ACCESS_KEY" ? { name, valueFrom: { secretKeyRef: { name: "credentials", key: "S3_SECRET" } } } : { name, value });
  return {
    postgres: (args, stdin) => ["kubectl", [...kubectlArgs(c),"exec",...(stdin ? ["-i"] : []),"statefulset/postgres","-c","postgres","--",...args]],
    rclone: async (script, stdin) => {
      if (!started) {
        // A one-shot pod in the namespace reaches object-store:8333 (the NetworkPolicy admits pods of the namespace).
        await runProcess("kubectl",[...kubectlArgs(c),"delete","pod",pod,"--ignore-not-found","--wait=true"],opts);
        await runProcess("kubectl",[...kubectlArgs(c),"apply","-f","-"],{...opts,stdin:JSON.stringify({ apiVersion: "v1", kind: "Pod", metadata: { name: pod, namespace: c.name, labels: { "io.ulams.stack": c.id, "io.ulams.role": "backup" } }, spec: { restartPolicy: "Never", automountServiceAccountToken: false, enableServiceLinks: false, containers: [{ name: "rclone", image: RCLONE_IMAGE, command: ["sh","-c","sleep 86400"], env, resources: { requests: { cpu: "100m", memory: "128Mi" }, limits: { memory: "512Mi" } }, securityContext: { allowPrivilegeEscalation: false } }] } })});
        started = true;
        await runProcess("kubectl",[...kubectlArgs(c),"wait",`pod/${pod}`,"--for=condition=Ready","--timeout=300s"],opts);
      }
      return ["kubectl", [...kubectlArgs(c),"exec",...(stdin ? ["-i"] : []),pod,"-c","rclone","--","sh","-c",script]];
    },
    volume: (v, write) => ["kubectl", [...kubectlArgs(c),"exec",...(write ? ["-i"] : []),"statefulset/application","-c",v.container,"--",...(write ? tarIn(v.path) : tarOut(v.path,v.exclude))]],
    cleanup: async () => { if (started) await runProcess("kubectl",[...kubectlArgs(c),"delete","pod",pod,"--ignore-not-found","--wait=false"],opts).catch(()=>undefined); },
  };
}
const runtimeFor = (directory: string, c: StackConfig, s: Secrets, opts: Opts) => c.target === "docker" ? dockerRuntime(directory,c,s,opts) : kubernetesRuntime(c,opts);
const timestamp = (date: Date) => date.toISOString().replace(/[-:]/g,"").replace(/\.\d+Z$/,"Z");

// ---- rclone remotes (the operator's own rclone config) ----------------------------------------------

async function remoteCopy(ctx: Ctx, opts: Opts, from: string, to: string, local: string): Promise<void> {
  try {
    await runProcess("rclone",["version"],opts);
    await runProcess("rclone",["copy",from,to],opts);
    return;
  } catch (error) {
    if (!(error instanceof CliError) || error.code !== "USAGE") throw error;
  }
  // No rclone on PATH: the rclone image with the operator's config file and the local directory mounted.
  const config = ctx.env.RCLONE_CONFIG ?? process.env.RCLONE_CONFIG ?? join(homedir(),".config","rclone","rclone.conf");
  if (!await lstat(config).then(i=>i.isFile(),()=>false)) throw new CliError("USAGE", `No rclone on PATH and no rclone config at ${config}. Install rclone or set RCLONE_CONFIG.`);
  const map = (p: string) => p === local ? "/data" : p;
  await runProcess("docker",["run","--rm","-v",`${config}:/config/rclone/rclone.conf:ro`,"-v",`${local}:/data`,RCLONE_IMAGE,"copy",map(from),map(to)],opts);
}

// ---- backup -----------------------------------------------------------------------------------------

export async function stackBackup(ctx: Ctx, input: z.infer<typeof backupInput>) {
  const directory = installDirectory(input.directory);
  return withInstall(directory, async () => {
    const c = await readConfig(directory);
    if (!c) throw new CliError("NOT_FOUND", "No managed stack in this directory.");
    const s = await readSecrets(directory), opts = options(ctx,directory,s);
    const pass = await passphrase(ctx, input.passphrase_file, false);
    if (c.target === "docker") await ownedDocker(ctx,directory,c); else await ownedKubernetes(ctx,directory,c,false);
    const parent = resolve(input.to ?? join(directory,"backups"));
    await mkdir(parent, { recursive: true, mode: 0o700 });
    const name = `ulams-${c.name}-${timestamp(new Date())}`;
    const out = join(parent, name);
    await mkdir(out, { mode: 0o700 });
    const runtime = runtimeFor(directory,c,s,opts);
    const files: BackupManifest["files"] = {};
    const warnings: Warning[] = [];
    const stream = async (file: string, [binary, args]: [string, string[]]) => {
      const result = await runStream(binary,args,{...opts,stdoutFile:join(out,file)});
      files[file] = { size: result.size!, sha256: result.sha256! };
    };
    try {
      ctx.io.stderr("Backing up PostgreSQL roles and databases…");
      await stream("globals.sql", runtime.postgres(["pg_dumpall","-U",OWNER,"--globals-only"],false));
      const [lb, la] = runtime.postgres(["psql","-U",OWNER,"-d","postgres","-Atc","select datname from pg_database where not datistemplate and datname <> 'postgres' order by 1"],false);
      const databases = (await runProcess(lb,la,opts)).split("\n").map(line=>line.trim()).filter(Boolean);
      if (!databases.length || databases.some(db=>!DB_NAME.test(db))) throw new CliError("SERVER_ERROR", `Unexpected database list: ${databases.join(", ") || "none"}.`);
      for (const db of databases) await stream(`db-${db}.dump`, runtime.postgres(["pg_dump","-U",OWNER,"-Fc",db],false));

      ctx.io.stderr("Backing up object storage buckets…");
      const listing = await runtime.rclone("rclone lsf s: --dirs-only",false);
      const buckets = (await runProcess(listing[0],listing[1],opts)).split("\n").map(line=>line.trim().replace(/\/$/,"")).filter(b=>b.startsWith("ulams"));
      if (buckets.some(b=>!BUCKET.test(b))) throw new CliError("SERVER_ERROR", `Unexpected bucket names: ${buckets.join(", ")}.`);
      const copy = buckets.map(b=>`mkdir -p /w/buckets/${b} && rclone copy s:${b} /w/buckets/${b}`).join(" && ");
      await stream("buckets.tar.gz", await runtime.rclone(`set -e; mkdir -p /w/buckets${copy ? ` && ${copy}` : ""}; tar czf - -C /w buckets`,false));

      ctx.io.stderr("Backing up the storage and H5P library volumes…");
      for (const v of VOLUMES) await stream(v.file, runtime.volume(v,false));

      if (pass) {
        const bundle: Record<string,string> = {};
        for (const file of ["stack.json","credentials.json","ca.crt"]) {
          const content = await readFile(join(directory,file),"utf8").catch(()=>undefined);
          if (content !== undefined) bundle[file] = content;
        }
        const encrypted = encrypt(Buffer.from(JSON.stringify(bundle)), pass);
        await writeFile(join(out,"install.json.enc"), encrypted, { mode: 0o600, flag: "wx" });
        files["install.json.enc"] = { size: encrypted.length, sha256: sha256(encrypted) };
      } else {
        warnings.push({ code: "CREDENTIALS_NOT_INCLUDED", message: "The stack credentials are not in this backup: it restores only into this install directory.", hint: `Pass --passphrase-file or set ${PASSPHRASE_ENV} to include them, encrypted.` });
      }
      const manifest: BackupManifest = { format: BACKUP_FORMAT, kind: "ulams-stack-backup", createdAt: new Date().toISOString(), cli: ctx.version, stack: { id: c.id, name: c.name, target: c.target, mode: c.mode, domain: c.domain, tenant: c.tenant, imageRegistry: c.imageRegistry, imageTag: c.imageTag }, postgres: { image: INFRA_IMAGES.postgres, databases }, buckets, secrets: Boolean(pass), files };
      await writeFile(join(out,"manifest.json"), JSON.stringify(manifest,null,2)+"\n", { mode: 0o600, flag: "wx" });
      let remote: string | undefined;
      if (input.remote) {
        remote = `${input.remote.replace(/\/$/,"")}/${name}`;
        ctx.io.stderr(`Copying the backup to ${remote}…`);
        await remoteCopy(ctx, opts, out, remote, out);
      }
      const size = Object.values(files).reduce((sum,f)=>sum+f.size,0);
      return { data: { backup: out, ...(remote ? { remote } : {}), databases, buckets, credentialsIncluded: Boolean(pass), files: Object.keys(files).length + 1, bytes: size, imageTag: c.imageTag }, ...(warnings.length ? { warnings } : {}) };
    } catch (error) {
      await rm(out, { recursive: true, force: true });
      throw error;
    } finally {
      await runtime.cleanup();
    }
  });
}

// ---- restore ----------------------------------------------------------------------------------------

export async function restorePlan(input: z.infer<typeof restoreInput>) {
  const directory = installDirectory(input.directory);
  if (input.remote) return { directory, remote: input.remote, note: "Download the backup, verify its checksums, then replace every database, the bucket contents and the storage volumes of this stack, and run ulams:upgrade." };
  if (!input.archive) throw new CliError("USAGE", "Give the backup directory, or --remote.");
  const manifest = await readManifest(resolve(input.archive));
  return { directory, backup: resolve(input.archive), createdAt: manifest.createdAt, imageTag: manifest.stack.imageTag, replaces: { databases: manifest.postgres.databases, buckets: manifest.buckets, volumes: VOLUMES.map(v=>v.volume) }, restoresCredentials: input.restore_secrets, note: "Every database, the bucket contents and the storage volumes of this stack are replaced by the backup; ulams:upgrade runs after." };
}

export async function stackRestore(ctx: Ctx, input: z.infer<typeof restoreInput>) {
  if (!input.archive === !input.remote) throw new CliError("USAGE", "Give either the backup directory or --remote, not both.");
  const directory = installDirectory(input.directory);
  return withInstall(directory, async () => {
    let source = input.archive ? resolve(input.archive) : "", download: string | undefined;
    if (input.remote) {
      await mkdir(join(directory,"backups"), { recursive: true, mode: 0o700 });
      download = await mkdtemp(join(directory,"backups","download-"));
      ctx.io.stderr(`Downloading ${input.remote}…`);
      await remoteCopy(ctx, options(ctx,directory), input.remote, download, download);
      source = download;
    }
    try {
      return await restoreFrom(ctx, input, directory, source);
    } finally {
      if (download) await rm(download, { recursive: true, force: true });
    }
  });
}

async function restoreFrom(ctx: Ctx, input: z.infer<typeof restoreInput>, directory: string, source: string) {
  const manifest = await readManifest(source);
  ctx.io.stderr("Verifying the backup checksums…");
  await verifyBackup(source, manifest);
  let c = await readConfig(directory);
  checkRelease(manifest, ctx.version, c?.imageTag);
  if (c && c.id !== manifest.stack.id) throw new CliError("CONFLICT", `This backup belongs to another installation (${manifest.stack.name}, ${manifest.stack.id}). Restore it into an empty directory with --restore-secrets.`);
  if (input.restore_secrets) {
    if (!manifest.secrets || !manifest.files["install.json.enc"]) throw new CliError("CONFLICT", "This backup has no credentials (it was made without a passphrase).");
    const pass = await passphrase(ctx, input.passphrase_file, true);
    const bundle = JSON.parse(decrypt(await readFile(join(source,"install.json.enc")), pass!).toString("utf8")) as Record<string,string>;
    if (!bundle["stack.json"] || !bundle["credentials.json"]) throw new CliError("CONFLICT", "The credentials archive is incomplete.");
    const restored = JSON.parse(bundle["stack.json"]) as StackConfig;
    if (restored.id !== manifest.stack.id) throw new CliError("CONFLICT", "The credentials archive belongs to another installation.");
    for (const [file, content] of Object.entries(bundle)) if (["stack.json","credentials.json","ca.crt"].includes(file)) await atomicWrite(directory, file, content);
    c = await readConfig(directory);
  }
  if (!c) throw new CliError("NOT_FOUND", "No managed stack in this directory. Restore into a new directory with --restore-secrets.");
  ({ config: c } = await renderInstall(directory, stackInput.parse({ directory })));
  const s = await readSecrets(directory), opts = options(ctx,directory,s);
  const runtime = runtimeFor(directory,c,s,opts);
  const exec = (args: string[]) => c.target === "docker" ? runProcess("docker",[...composeArgs(directory,c),"exec","-T","api",...args],opts) : runProcess("kubectl",[...kubectlArgs(c),"exec","statefulset/application","-c","api","--",...args],opts);
  const fromFile = async (file: string, [binary, args]: [string, string[]]) => { await runStream(binary,args,{...opts,stdinFile:join(source,file)}); };
  try {
    ctx.io.stderr("Starting the data services and stopping the application…");
    if (c.target === "docker") {
      await ownedDocker(ctx,directory,c);
      await runProcess("docker",[...composeArgs(directory,c),"up","--no-start","--pull","missing"],opts);
      await runProcess("docker",[...composeArgs(directory,c),"up","--detach","--wait","--wait-timeout",String(input.ready_timeout),...DATA_SERVICES],opts);
      await runProcess("docker",[...composeArgs(directory,c),"stop",...APP_SERVICES],opts);
    } else {
      await ownedKubernetes(ctx,directory,c,true);
      await applyKubernetes(directory,c,opts,DATA_SERVICES,input.ready_timeout);
      await runProcess("kubectl",[...kubectlArgs(c),"scale","statefulset/application","--replicas=0"],opts);
      await runProcess("kubectl",[...kubectlArgs(c),"wait","--for=delete","pods","-l",`io.ulams.stack=${c.id},io.ulams.role=application`,"--timeout=900s"],opts);
    }

    ctx.io.stderr("Restoring PostgreSQL roles and databases…");
    const databases = manifest.postgres.databases;
    if (databases.some(db=>!DB_NAME.test(db))) throw new CliError("CONFLICT", "The backup lists an invalid database name.");
    const [tb, ta] = runtime.postgres(["psql","-U",OWNER,"-d","postgres","-Atqc",`select count(pg_terminate_backend(pid)) from pg_stat_activity where datname in (${databases.map(db=>`'${db}'`).join(",")}) and pid <> pg_backend_pid()`],false);
    await runProcess(tb,ta,opts);
    // Roles that already exist report an error and keep going; their passwords come from the backup.
    await fromFile("globals.sql", runtime.postgres(["psql","-U",OWNER,"-d","postgres","-q","-v","ON_ERROR_STOP=0","-f","-"],true));
    for (const db of databases) await fromFile(`db-${db}.dump`, runtime.postgres(["pg_restore","-U",OWNER,"-d","postgres","--create","--clean","--if-exists","--exit-on-error"],true));

    ctx.io.stderr("Restoring the bucket contents…");
    await fromFile("buckets.tar.gz", await runtime.rclone("set -e; mkdir -p /w; tar xzf - -C /w; for d in /w/buckets/*/; do [ -d \"$d\" ] || continue; b=$(basename \"$d\"); rclone mkdir \"s:$b\"; rclone sync \"$d\" \"s:$b\"; done",true));

    ctx.io.stderr("Starting the application…");
    if (c.target === "docker") {
      for (const v of VOLUMES) await fromFile(v.file, runtime.volume(v,true));
      await runProcess("docker",[...composeArgs(directory,c),"up","--detach","--wait","--wait-timeout",String(input.ready_timeout)],opts);
    } else {
      await runProcess("kubectl",[...kubectlArgs(c),"scale","statefulset/application","--replicas=1"],opts);
      await waitStatefulSet(c,opts,"application",input.ready_timeout);
      for (const v of VOLUMES) await fromFile(v.file, runtime.volume(v,true));
    }

    ctx.io.stderr("Re-issuing storage keys and bringing the data to this release (ulams:upgrade)…");
    await exec(["php","artisan","ulams:tenant:ensure-buckets","--no-interaction"]);
    // A restored object store has no tenant identities: issue new per-tenant keys and rebuild the env files
    // first, because ulams:upgrade already reads the tenant buckets.
    await exec(["php","artisan","ulams:tenant:sync-env","--rotate-storage-keys","--no-interaction"]);
    await exec(["php","artisan","ulams:upgrade","--no-interaction"]);
    await exec(["php","artisan","ulams:h5p:export-config","--no-interaction"]);
    await exec(["php","artisan","health:check"]);
    return { data: { restored: true, directory, backup: source, createdAt: manifest.createdAt, imageTag: c.imageTag, databases, buckets: manifest.buckets, credentialsRestored: input.restore_secrets } };
  } finally {
    await runtime.cleanup();
  }
}
