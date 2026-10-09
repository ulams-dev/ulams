import { chmodSync, existsSync, mkdirSync, readFileSync, statSync, writeFileSync, openSync, closeSync, constants } from "node:fs";
import { dirname } from "node:path";
import { CliError } from "../errors.ts";
import { configFile, credentialsFile } from "./paths.ts";

export const CONFIG_CONTRACT = 1;

export interface Profile {
  url: string;
  kind: "tenant" | "platform";
  user?: string;
  tokenId?: string | null;
  expiresAt?: string | null;
  scopes?: string[];
  login?: "token" | "password" | "demo" | "device";
}

export interface ConfigFile {
  contract: number;
  defaultProfile: string | null;
  profiles: Record<string, Profile>;
  defaultOutput?: string;
  color?: boolean;
}

export type Credentials = Record<string, { token: string }>;

const empty = (): ConfigFile => ({ contract: CONFIG_CONTRACT, defaultProfile: null, profiles: {} });

export function normaliseUrl(input: string): string {
  let value = input.trim();
  if (!/^https?:\/\//i.test(value)) value = `https://${value}`;
  let url: URL;
  try {
    url = new URL(value);
  } catch {
    throw new CliError("INPUT_INVALID", `"${input}" is not a valid URL.`, { hint: "Use an origin such as http://coffee.localhost." });
  }
  return url.origin;
}

/** A readable default profile name from an origin: coffee.localhost -> "coffee". */
export function profileNameFromUrl(url: string): string {
  const host = new URL(url).hostname;
  return host.split(".")[0] || host;
}

export class ConfigStore {
  constructor(readonly dir: string) {}

  read(): ConfigFile {
    const file = configFile(this.dir);
    if (!existsSync(file)) return empty();
    try {
      const parsed = JSON.parse(readFileSync(file, "utf8")) as ConfigFile;
      return { ...empty(), ...parsed, profiles: parsed.profiles ?? {} };
    } catch (error) {
      throw new CliError("INPUT_INVALID", `Cannot read ${file}: ${(error as Error).message}`, {
        hint: `Fix or delete ${file}.`,
      });
    }
  }

  write(config: ConfigFile): void {
    mkdirSync(this.dir, { recursive: true });
    writeFileSync(configFile(this.dir), `${JSON.stringify(config, null, 2)}\n`, { mode: 0o644 });
  }

  readCredentials(): Credentials {
    const file = credentialsFile(this.dir);
    if (!existsSync(file)) return {};
    if (process.platform !== "win32") {
      const mode = statSync(file).mode & 0o777;
      if (mode & 0o077) {
        throw new CliError("INSECURE_CREDENTIALS", `${file} is readable by other users (mode ${mode.toString(8)}).`, {
          hint: `Run: chmod 600 ${file}`,
        });
      }
    }
    try {
      return JSON.parse(readFileSync(file, "utf8")) as Credentials;
    } catch (error) {
      throw new CliError("INPUT_INVALID", `Cannot read ${file}: ${(error as Error).message}`, { hint: `Fix or delete ${file}.` });
    }
  }

  writeCredentials(credentials: Credentials): void {
    const file = credentialsFile(this.dir);
    mkdirSync(dirname(file), { recursive: true });
    if (!existsSync(file)) {
      // O_EXCL: never follow a pre-planted file or symlink.
      closeSync(openSync(file, constants.O_CREAT | constants.O_EXCL | constants.O_WRONLY, 0o600));
    }
    writeFileSync(file, `${JSON.stringify(credentials, null, 2)}\n`, { mode: 0o600 });
    if (process.platform !== "win32") chmodSync(file, 0o600);
  }

  saveLogin(name: string, profile: Profile, token: string, makeDefault = true): void {
    const config = this.read();
    config.profiles[name] = profile;
    if (makeDefault || !config.defaultProfile) config.defaultProfile = name;
    const credentials = this.readCredentials();
    credentials[name] = { token };
    this.writeCredentials(credentials);
    this.write(config);
  }

  removeProfile(name: string): boolean {
    const config = this.read();
    if (!config.profiles[name]) return false;
    delete config.profiles[name];
    if (config.defaultProfile === name) config.defaultProfile = Object.keys(config.profiles)[0] ?? null;
    const credentials = this.readCredentials();
    delete credentials[name];
    this.writeCredentials(credentials);
    this.write(config);
    return true;
  }
}

export interface ResolvedProfile {
  name: string | null;
  url: string | null;
  kind: "tenant" | "platform";
  token: string | null;
  tokenSource: "env" | "stdin" | "profile" | "none";
  user?: string;
  scopes?: string[];
  expiresAt?: string | null;
  tokenId?: string | null;
}

export interface ResolveInput {
  store: ConfigStore;
  env: NodeJS.ProcessEnv;
  profile?: string | undefined;
  url?: string | undefined;
  stdinToken?: string | null;
}

/** Precedence: flag > env > profile > default (plan 4.3). */
export function resolveProfile(input: ResolveInput): ResolvedProfile {
  const { store, env } = input;
  const config = store.read();
  const wanted = input.profile ?? env.ULAMS_PROFILE ?? config.defaultProfile ?? null;
  const stored = wanted ? config.profiles[wanted] : undefined;
  if (wanted && !stored && input.profile) {
    throw new CliError("INPUT_INVALID", `Unknown profile "${wanted}".`, {
      hint: `Known profiles: ${Object.keys(config.profiles).join(", ") || "none"}. Run \`ulams login --url <origin>\`.`,
    });
  }
  const urlOverride = input.url ?? env.ULAMS_URL;
  const url = urlOverride ? normaliseUrl(urlOverride) : (stored?.url ?? null);
  // A profile token is bound to its own origin: never send it to a different --url.
  const sameOrigin = stored && url === stored.url;
  let token: string | null = null;
  let tokenSource: ResolvedProfile["tokenSource"] = "none";
  if (input.stdinToken) {
    token = input.stdinToken;
    tokenSource = "stdin";
  } else if (env.ULAMS_TOKEN) {
    token = env.ULAMS_TOKEN;
    tokenSource = "env";
  } else if (wanted && stored && sameOrigin) {
    token = store.readCredentials()[wanted]?.token ?? null;
    tokenSource = token ? "profile" : "none";
  }
  return {
    name: sameOrigin ? wanted : null,
    url,
    kind: stored?.kind ?? "tenant",
    token,
    tokenSource,
    user: sameOrigin ? stored?.user : undefined,
    scopes: sameOrigin ? stored?.scopes : undefined,
    expiresAt: sameOrigin ? stored?.expiresAt : undefined,
    tokenId: sameOrigin ? stored?.tokenId : undefined,
  };
}
