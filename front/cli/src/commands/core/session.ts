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

export const logout = defineCommand({
  id: "logout",
  summary: "Forget the saved credentials of a profile",
  description: "Deletes the stored token of the profile (--profile, else the default). The token itself stays valid on the server until it expires; revoking needs scoped tokens.",
  kind: "local",
  idempotent: true,
  anonymous: true,
  mcp: { expose: false },
  input: z.object({ revoke: z.boolean().optional().describe("Also revoke the token on the server (needs scoped tokens; not available yet).") }),
  output: z.object({ profile: z.string(), removed: z.boolean() }),
  examples: [{ title: "Log out of the default profile", argv: "logout" }],
  async run(ctx, i) {
    const name = ctx.flags.profile ?? ctx.profile.name ?? ctx.store.read().defaultProfile;
    if (!name) throw new CliError("AUTH_REQUIRED", "No profile to log out of.");
    if (i.revoke) {
      throw new CliError("FEATURE_DISABLED", "Token revocation is not available on this server yet.", {
        hint: "Run `ulams logout` without --revoke; the token expires on its own.",
      });
    }
    const removed = ctx.store.removeProfile(name);
    return { data: { profile: name, removed } };
  },
});

export const whoami = defineCommand({
  id: "whoami",
  summary: "Show the current user, instance, roles and token source",
  description: "Calls GET /api/profile/me with the active credentials. Never prints the token. Use it first to check that the CLI points at the right instance.",
  kind: "read",
  scopes: ["learner:read"],
  endpoints: ["GET /api/profile/me"],
  mcp: { toolset: "core" },
  input: z.object({ permissions: z.boolean().optional().describe("Include the full permission list.") }),
  output: z.unknown(),
  examples: [{ title: "Who am I?", argv: "whoami --json" }],
  async run(ctx, i) {
    const me = (await ctx.client.call<MeData>("GET", "/api/profile/me")).data;
    const perms = me.permissions ?? [];
    return {
      data: {
        user: { id: me.id ?? null, email: me.email ?? null, name: me.name ?? null, roles: me.roles ?? [] },
        permissionCount: perms.length,
        ...(i.permissions ? { permissions: perms } : {}),
        instance: ctx.profile.url,
        profile: ctx.profile.name,
        auth: {
          source: ctx.profile.tokenSource,
          scopes: ctx.profile.scopes ?? null,
          expiresAt: ctx.profile.expiresAt ?? null,
        },
      },
    };
  },
});
