import { z } from "zod";
import { CliError } from "../../errors.ts";
import { defineCommand } from "../define.ts";

interface MeData {
  id?: number;
  email?: string;
  name?: string;
  roles?: string[];
  permissions?: string[];
}

interface CurrentToken {
  id?: string | null;
  scoped?: boolean;
  scopes?: string[];
  kind?: string | null;
  agent_name?: string | null;
  expires_at?: string | null;
}

export const logout = defineCommand({
  id: "logout",
  summary: "Forget the saved credentials of a profile",
  description: "Deletes the stored token of the profile (--profile, else the default). The token stays valid on the server until it expires unless you pass --revoke, which revokes it first (scoped tokens only; needs the tokens:write scope).",
  kind: "local",
  idempotent: true,
  anonymous: true,
  mcp: { expose: false },
  endpoints: ["DELETE /api/auth/tokens/{id}"],
  input: z.object({ revoke: z.boolean().optional().describe("Also revoke the token on the server (scoped tokens only; needs tokens:write).") }),
  output: z.object({ profile: z.string(), removed: z.boolean(), revoked: z.boolean().optional() }),
  examples: [{ title: "Log out of the default profile", argv: "logout" }],
  async run(ctx, i) {
    const name = ctx.flags.profile ?? ctx.profile.name ?? ctx.store.read().defaultProfile;
    if (!name) throw new CliError("AUTH_REQUIRED", "No profile to log out of.");
    let revoked: boolean | undefined;
    if (i.revoke) {
      const id = ctx.profile.tokenId ?? (await ctx.client.call<{ id?: string | null; scoped?: boolean }>("GET", "/api/auth/tokens/current")).data.id;
      if (!id) {
        throw new CliError("FEATURE_DISABLED", "This token is not a scoped token, so it cannot be revoked from here.", {
          hint: "Run `ulams logout` without --revoke, or create a scoped token with `ulams tokens create`.",
        });
      }
      await ctx.client.call("DELETE", "/api/auth/tokens/{id}", { params: { id }, idempotent: true });
      revoked = true;
    }
    const removed = ctx.store.removeProfile(name);
    return { data: { profile: name, removed, ...(revoked === undefined ? {} : { revoked }) } };
  },
});

export const whoami = defineCommand({
  id: "whoami",
  summary: "Show the current user, instance, roles and token source",
  description: "Calls GET /api/profile/me with the active credentials. Never prints the token. Use it first to check that the CLI points at the right instance.",
  kind: "read",
  scopes: ["learner:read"],
  endpoints: ["GET /api/profile/me"],
  undocumented: ["GET /api/auth/tokens/current"],
  mcp: { toolset: "core" },
  input: z.object({ permissions: z.boolean().optional().describe("Include the full permission list.") }),
  output: z.unknown(),
  examples: [{ title: "Who am I?", argv: "whoami --json" }],
  async run(ctx, i) {
    const me = (await ctx.client.call<MeData>("GET", "/api/profile/me")).data;
    const perms = me.permissions ?? [];
    // Token facts from the server (id, scopes, expiry); older servers have no such endpoint.
    const current = await ctx.client
      .call<CurrentToken>("GET", "/api/auth/tokens/current", { idempotent: true })
      .then((r) => r.data)
      .catch(() => null);
    return {
      data: {
        user: { id: me.id ?? null, email: me.email ?? null, name: me.name ?? null, roles: me.roles ?? [] },
        permissionCount: perms.length,
        ...(i.permissions ? { permissions: perms } : {}),
        instance: ctx.profile.url,
        profile: ctx.profile.name,
        auth: {
          source: ctx.profile.tokenSource,
          scopes: current?.scopes ?? ctx.profile.scopes ?? null,
          expiresAt: current?.expires_at ?? ctx.profile.expiresAt ?? null,
          ...(current ? { tokenId: current.id ?? null, scoped: Boolean(current.scoped), kind: current.kind ?? null, agent: current.agent_name ?? null } : {}),
        },
      },
    };
  },
});
