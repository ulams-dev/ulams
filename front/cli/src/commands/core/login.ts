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

const input = z.object({
  url: z.string().optional().describe("Instance origin, e.g. http://coffee.localhost. Defaults to --url or ULAMS_URL."),
  email: z.string().optional().describe("Email for a password login (use with --password-stdin)."),
  passwordStdin: z.boolean().optional().describe("Read the password from stdin (one line)."),
  demo: z.enum(["admin", "tutor", "student"]).optional().describe("Log in as a demo user (demo tenants only)."),
  device: z.boolean().optional().describe("Browser login with a device code (needs server support)."),
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
      throw new CliError("UNSUPPORTED_SERVER", "Device login is advertised by the server but this CLI version cannot complete it yet.", {
        hint: "Update the CLI, or use --token-stdin.",
      });
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

    const name = ctx.flags.profile ?? profileNameFromUrl(url);
    const profile: Profile = {
      url,
      kind: "tenant",
      ...(me.email ? { user: me.email } : {}),
      tokenId: null,
      expiresAt,
      scopes: ["*"],
      login: method,
    };
    ctx.store.saveLogin(name, profile, token, i.makeDefault !== false);
    return {
      data: { profile: name, url, user: me.email ?? null, roles: me.roles ?? [], expiresAt, scopes: ["*"], method },
      warnings,
    };
  },
});
