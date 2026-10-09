import { spawn } from "node:child_process";
import { hostname } from "node:os";
import { z } from "zod";
import { CliError } from "../../errors.ts";
import { HttpClient } from "../../http/client.ts";
import { normaliseUrl, profileNameFromUrl, type Profile } from "../../config/profiles.ts";
import { defineCommand } from "../define.ts";
import type { Ctx, Warning } from "../../registry/types.ts";

interface MeData {
  id?: number;
  email?: string;
  name?: string;
  roles?: string[];
}

/** Least privilege that still lets `ulams logout --revoke` and `ulams tokens` manage the token itself. */
export const DEFAULT_DEVICE_SCOPES = ["@author", "tokens:write"];

const input = z.object({
  url: z.string().optional().describe("Instance origin, e.g. http://coffee.localhost. Defaults to --url or ULAMS_URL."),
  email: z.string().optional().describe("Email for a password login (use with --password-stdin)."),
  passwordStdin: z.boolean().optional().describe("Read the password from stdin (one line)."),
  demo: z.enum(["admin", "tutor", "student"]).optional().describe("Log in as a demo user (demo tenants only)."),
  device: z.boolean().optional().describe("Browser login with a device code (needs server support)."),
  scopes: z.array(z.string()).optional().describe(`Scopes to request with --device: comma-separated or repeated, scopes (courses:write) or presets (@author, @admin, @read-only, @ci). Default: ${DEFAULT_DEVICE_SCOPES.join(",")}.`),
  makeDefault: z.boolean().optional().describe("Make this the default profile (default true)."),
});

async function firstLine(ctx: Ctx, what: string): Promise<string> {
  const text = (await ctx.readStdin()).split(/\r?\n/)[0]?.trim() ?? "";
  if (!text) throw new CliError("INPUT_INVALID", `No ${what} on stdin.`, { hint: `Pipe it: printf '%s' "$VALUE" | ulams login ...` });
  return text;
}

export const login = defineCommand({
  id: "login",
  summary: "Log in to an instance and save a profile",
  description:
    "Methods: --token-stdin (paste a token), --email with --password-stdin, --demo admin|tutor|student (demo tenants), --device (browser approval, when the server supports it). The token is stored in a 0600 credentials file, never printed.",
  kind: "local",
  idempotent: true,
  anonymous: true,
  endpoints: ["POST /api/auth/login", "POST /api/demo/login", "POST /api/auth/device/code", "POST /api/auth/device/token"],
  mcp: { expose: false },
  input,
  output: z.object({ profile: z.string(), url: z.string(), user: z.string().nullable(), expiresAt: z.string().nullable() }),
  examples: [
    { title: "Demo admin on the local coffee tenant", argv: "login --url http://coffee.localhost --demo admin" },
    { title: "Pasted token", argv: "login --url https://school.example.com --token-stdin < token.txt" },
    { title: "Password", argv: "login --url https://school.example.com --email me@example.com --password-stdin" },
  ],
  async run(ctx, i) {
    const urlInput = i.url ?? ctx.flags.url ?? ctx.env.ULAMS_URL ?? ctx.profile.url;
    if (!urlInput) throw new CliError("INPUT_INVALID", "login needs the instance URL.", { hint: "Pass --url http://coffee.localhost." });
    const url = normaliseUrl(urlInput);
    const anon = new HttpClient({ baseUrl: url, token: null, fetch: ctx.client.fetchImpl, userAgent: ctx.client.userAgent, client: "cli" });
    const warnings: Warning[] = [];
    let token: string;
    let expiresAt: string | null = null;
    let method: Profile["login"];
    let scopes: string[] = ["*"];
    let tokenId: string | null = null;

    if (i.demo) {
      method = "demo";
      const res = await anon.call<{ token: string; expires_at?: string | null }>("POST", "/api/demo/login", { body: { role: i.demo } });
      token = res.data.token;
      expiresAt = res.data.expires_at ?? null;
    } else if (i.email) {
      method = "password";
      let password: string;
      if (i.passwordStdin) password = await firstLine(ctx, "password");
      else if (ctx.io.interactive) password = await ctx.prompt("Password: ", { secret: true });
      else throw new CliError("INPUT_INVALID", "A password login needs --password-stdin.", { hint: 'printf %s "$PASSWORD" | ulams login --email … --password-stdin' });
      const res = await anon.call<{ token: string; expires_at?: string | null }>("POST", "/api/auth/login", {
        body: { email: i.email, password, remember_me: 1 },
      });
      token = res.data.token;
      expiresAt = res.data.expires_at ?? null;
      warnings.push({
        code: "UNSCOPED_TOKEN",
        message: "This token has the full permissions of the user and is not scoped.",
        hint: "Create a scoped token with `ulams tokens create` once the server supports scoped tokens.",
      });
    } else if (ctx.flags.tokenStdin) {
      method = "token";
      token = await firstLine(ctx, "token");
    } else if (i.device || (!i.demo && !i.email)) {
      method = "device";
      const meta = await anon.call<{ features?: { deviceLogin?: boolean } }>("GET", "/api/meta").catch(() => null);
      if (!meta?.data?.features?.deviceLogin) {
        if (i.device) {
          throw new CliError("FEATURE_DISABLED", "Device login is not available on this server.", {
            hint: "Use --token-stdin, --email with --password-stdin, or --demo on a demo tenant.",
          });
        }
        throw new CliError("INPUT_INVALID", "Choose a login method.", {
          hint: "Pass --demo admin, --email + --password-stdin, or --token-stdin. Device login is not available on this server.",
        });
      }
      const requested = (i.scopes ?? []).flatMap((s) => s.split(",")).map((s) => s.trim()).filter(Boolean);
      const granted = await deviceLogin(ctx, anon, requested.length ? requested : DEFAULT_DEVICE_SCOPES);
      token = granted.token;
      expiresAt = granted.expiresAt;
      scopes = granted.scopes;
      tokenId = granted.tokenId;
    } else {
      throw new CliError("INPUT_INVALID", "Choose a login method.");
    }

    // Verify the token and learn who it belongs to.
    const verified = new HttpClient({ baseUrl: url, token, fetch: ctx.client.fetchImpl, userAgent: ctx.client.userAgent, client: "cli" });
    let me: MeData;
    try {
      me = (await verified.call<MeData>("GET", "/api/profile/me")).data;
    } catch (error) {
      if (error instanceof CliError && error.code === "AUTH_EXPIRED") {
        throw new CliError("AUTH_EXPIRED", "The server rejected this token.", { status: 401, hint: "Check that the token belongs to this instance (tokens are per tenant)." });
      }
      throw error;
    }

    // A platform host (tenant management) is a different kind of profile; older servers have no /api/meta.
    const hostKind = await verified
      .call<{ kind?: string }>("GET", "/api/meta", { idempotent: true })
      .then((r) => (r.data?.kind === "platform" ? "platform" : "tenant"))
      .catch(() => "tenant" as const);
    const name = ctx.flags.profile ?? profileNameFromUrl(url);
    const profile: Profile = {
      url,
      kind: hostKind,
      ...(me.email ? { user: me.email } : {}),
      tokenId,
      expiresAt,
      scopes,
      login: method,
    };
    ctx.store.saveLogin(name, profile, token, i.makeDefault !== false);
    return {
      data: { profile: name, url, user: me.email ?? null, roles: me.roles ?? [], expiresAt, scopes, method },
      warnings,
    };
  },
});

interface DeviceCode {
  device_code: string;
  user_code: string;
  verification_uri: string;
  verification_uri_complete?: string;
  expires_in: number;
  interval: number;
}

function openBrowser(url: string): void {
  const cmd = process.platform === "darwin" ? "open" : process.platform === "win32" ? "cmd" : "xdg-open";
  const args = process.platform === "win32" ? ["/c", "start", "", url] : [url];
  try {
    spawn(cmd, args, { stdio: "ignore", detached: true }).on("error", () => undefined).unref();
  } catch {
    /* the URL is printed anyway */
  }
}

/** RFC 8628 shaped device flow (ADR 0075): print the code, poll until approved, denied or expired. */
async function deviceLogin(
  ctx: Ctx,
  anon: HttpClient,
  scopes: string[]
): Promise<{ token: string; expiresAt: string | null; scopes: string[]; tokenId: string | null }> {
  const code = (
    await anon.call<DeviceCode>("POST", "/api/auth/device/code", {
      body: { client_name: `ulams-cli on ${hostname()}`, scopes, agent: ctx.env.ULAMS_AGENT ?? null },
    })
  ).data;
  const link = code.verification_uri_complete ?? code.verification_uri;
  ctx.io.stderr(`Open ${link} and enter the code ${code.user_code} to approve this login.`);
  if (ctx.io.isTTY) openBrowser(link);
  const deadline = Date.now() + code.expires_in * 1000;
  let interval = Math.max(0, code.interval) * 1000;
  for (;;) {
    if (Date.now() > deadline) throw new CliError("AUTH_EXPIRED", "The device code expired before it was approved.", { hint: "Run `ulams login --device` again." });
    await new Promise((r) => setTimeout(r, interval));
    try {
      const res = (await anon.call<{ access_token: string; expires_at?: string | null; scopes?: string[]; token_id?: string }>("POST", "/api/auth/device/token", { body: { device_code: code.device_code } })).data;
      return { token: res.access_token, expiresAt: res.expires_at ?? null, scopes: res.scopes ?? scopes, tokenId: res.token_id ?? null };
    } catch (error) {
      const e = (error as CliError).details as { body?: { error?: string } } | undefined;
      const reason = e?.body?.error ?? (error as CliError).message;
      if (/authorization_pending/.test(reason)) continue;
      if (/slow_down/.test(reason)) {
        interval += 5000;
        continue;
      }
      if (/access_denied/.test(reason)) throw new CliError("FORBIDDEN", "The login was denied in the browser.", { hint: "Run `ulams login --device` again and approve it." });
      if (/expired_token/.test(reason)) throw new CliError("AUTH_EXPIRED", "The device code expired.", { hint: "Run `ulams login --device` again." });
      throw error;
    }
  }
}
