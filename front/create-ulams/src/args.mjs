import { parseArgs } from "node:util";

export const THEMES = ["coffee", "oncall", "nightsky", "gravity", "poland", "ulam"];

export const HELP = `create-ulams: set up a local ulams academy in one command

Usage
  npx create-ulams [directory] [options]

Creates the directory, generates private credentials and a Docker Compose stack (database, cache,
API, workers, learner site, admin, H5P, PDF, object storage, gateway), starts it and provisions
your first academy with a few demo students. The directory is also where you manage it later:
  npx ulams stack status --directory <directory>
  npx ulams stack down --directory <directory>

Options
  --mode <development|production>  Default development: local, http, fake AI, no paid provider
  --domain <host>                  Public domain (required for production)
  --academy <slug>                 Slug of the first academy (default: academy)
  --academy-name <name>            Display name (default: Your academy)
  --theme <preset>                 ${THEMES.join(", ")} (default: coffee)
  --users <n>                      Demo students to create, 0-50 (development default: 3)
  --admin-email <email>
  --http-port <port>               Gateway port (default 8480 in development)
  --target <docker|kubernetes>     Default docker
  --image-tag <tag>                Application image release (default latest, pinned on first start)
  --tls <acme|internal>            Production TLS
  --force                          Use a directory that already has files (resumes a stack there)
  --dry-run                        Show what would be done, change nothing
  --cli <path>                     Path to the ulams CLI (dist/ulams.mjs); default the bundled one
  -h, --help
  -v, --version
`;

const OPTIONS = {
  mode: { type: "string" },
  domain: { type: "string" },
  academy: { type: "string" },
  "academy-name": { type: "string" },
  theme: { type: "string" },
  users: { type: "string" },
  "admin-email": { type: "string" },
  "http-port": { type: "string" },
  target: { type: "string" },
  "image-tag": { type: "string" },
  tls: { type: "string" },
  force: { type: "boolean", default: false },
  "dry-run": { type: "boolean", default: false },
  cli: { type: "string" },
  help: { type: "boolean", short: "h", default: false },
  version: { type: "boolean", short: "v", default: false },
};

export class UsageError extends Error {}

/** @returns {{help:boolean,version:boolean,directory:string,name:string,options:Record<string,any>}} */
export function parse(argv) {
  let parsed;
  try {
    parsed = parseArgs({ args: argv, options: OPTIONS, allowPositionals: true, strict: true });
  } catch (error) {
    throw new UsageError(error.message);
  }
  const { values, positionals } = parsed;
  if (values.help || values.version) return { help: values.help, version: values.version, directory: "", name: "", options: {} };
  if (positionals.length > 1) throw new UsageError(`Expected one directory, got ${positionals.length}: ${positionals.join(" ")}`);

  const directory = positionals[0] ?? "my-academy";
  const mode = values.mode ?? "development";
  if (!["development", "production"].includes(mode)) throw new UsageError(`--mode must be development or production, not "${mode}"`);
  const target = values.target ?? "docker";
  if (!["docker", "kubernetes"].includes(target)) throw new UsageError(`--target must be docker or kubernetes, not "${target}"`);
  if (mode === "production" && !values.domain) throw new UsageError("Production needs --domain (and DNS pointing at this server).");
  if (values.theme !== undefined && !THEMES.includes(values.theme)) throw new UsageError(`--theme must be one of ${THEMES.join(", ")}`);
  if (values.tls !== undefined && !["acme", "internal"].includes(values.tls)) throw new UsageError("--tls must be acme or internal");
  if (values.academy !== undefined && !/^[a-z][a-z0-9]{1,29}$/.test(values.academy)) throw new UsageError("--academy must be 2-30 lowercase letters and digits, starting with a letter");
  if (values["admin-email"] !== undefined && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(values["admin-email"])) throw new UsageError("--admin-email is not an e-mail address");
  const integer = (key, min, max) => {
    if (values[key] === undefined) return undefined;
    if (!/^\d+$/.test(values[key]) || Number(values[key]) < min || Number(values[key]) > max) throw new UsageError(`--${key} must be a whole number from ${min} to ${max}`);
    return Number(values[key]);
  };
  const users = integer("users", 0, 50);
  const httpPort = integer("http-port", 1, 65535);

  const name = projectName(directory);
  return {
    help: false,
    version: false,
    directory,
    name,
    options: {
      mode,
      target,
      domain: values.domain,
      academy: values.academy,
      academyName: values["academy-name"],
      theme: values.theme ?? (mode === "development" ? "coffee" : undefined),
      users: users ?? (mode === "development" ? 3 : undefined),
      adminEmail: values["admin-email"],
      httpPort,
      imageTag: values["image-tag"],
      tls: values.tls,
      force: values.force,
      dryRun: values["dry-run"],
      cli: values.cli,
    },
  };
}

/** The Compose project / Kubernetes namespace: the directory name as 2-40 lowercase letters, digits and dashes. */
export function projectName(directory) {
  const base = directory.replace(/[\\/]+$/, "").split(/[\\/]/).pop() ?? "";
  const slug = base.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 40).replace(/-+$/, "");
  if (!/^[a-z][a-z0-9-]{1,39}$/.test(slug)) {
    throw new UsageError(`Cannot derive a project name from "${base}": use a directory name that starts with a letter and has at least two letters or digits.`);
  }
  return slug;
}
