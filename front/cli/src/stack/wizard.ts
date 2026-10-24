import { CliError } from "../errors.ts";
import type { Ctx } from "../registry/types.ts";
import { stackInput, type Secrets, type StackConfig, type StackInput } from "./config.ts";

/** Environment variables for the secrets the operator supplies; never flags, so they stay out of argv. */
export const SECRET_ENV = { SMTP_PASSWORD: "ULAMS_SMTP_PASSWORD", ANTHROPIC_API_KEY: "ULAMS_ANTHROPIC_API_KEY" } as const;

export function secretsFromEnv(ctx: Ctx): Secrets {
  const secrets: Secrets = {};
  for (const [key, name] of Object.entries(SECRET_ENV)) {
    const value = ctx.env[name] ?? process.env[name];
    if (value) secrets[key] = value;
  }
  return secrets;
}

/** The wizard runs only for a new install, in a terminal, without --yes or --json. */
export const wizardApplies = (ctx: Ctx, prior: StackConfig | undefined) => prior === undefined && ctx.io.interactive && !ctx.flags.yes && !ctx.flags.json;

/**
 * Install-time setup (P8-7, docs/plans/phase-8-self-hosting.md 4.7): asks for the values a new stack needs and
 * that were not given as flags (flags always win). Answers are validated with the same schema as the flags.
 */
export async function askSetup(ctx: Ctx, input: StackInput, secrets: Secrets): Promise<{ input: StackInput; secrets: Secrets }> {
  const next: StackInput = { ...input };
  const found: Secrets = { ...secrets };
  const shape = stackInput.shape;
  const ask = async <K extends keyof StackInput>(key: K, question: string, fallback?: string, optional = false): Promise<StackInput[K] | undefined> => {
    for (let attempt = 0; attempt < 3; attempt++) {
      const answer = (await ctx.prompt(`${question}${fallback ? ` [${fallback}]` : ""}: `)).trim() || fallback || "";
      if (!answer && optional) return undefined;
      const parsed = (shape[key] as unknown as { safeParse(v: unknown): { success: boolean; data?: unknown } }).safeParse(answer);
      if (parsed.success && parsed.data !== undefined) return parsed.data as StackInput[K];
      ctx.io.stderr(`  "${answer}" is not valid here; try again.`);
    }
    throw new CliError("USAGE", `No valid answer for ${String(key)}; pass it as --${String(key).replaceAll("_", "-")}.`);
  };
  const askSecret = async (key: keyof typeof SECRET_ENV, question: string) => {
    if (found[key]) return;
    const value = (await ctx.prompt(`${question} (hidden; or set ${SECRET_ENV[key]}): `, { secret: true })).trim();
    if (!value) throw new CliError("USAGE", `${question} is needed; set ${SECRET_ENV[key]} or answer the prompt.`);
    found[key] = value;
  };

  ctx.io.stderr("New ULAMS stack: a few questions (Enter keeps the default in brackets; values given as flags are not asked).");
  const mode = next.mode ?? "development";
  if (mode === "production" && !next.domain) next.domain = await ask("domain", "Public domain (its DNS must point at this server)");
  const domain = next.domain ?? (mode === "development" ? "ulams.localhost" : "");
  if (!next.admin_email) next.admin_email = await ask("admin_email", "Platform admin e-mail", `admin@${domain}`);

  if (!next.mail) next.mail = await ask("mail", "E-mail delivery: log (written to the log, nothing is sent) or smtp", "log");
  if (next.mail === "smtp") {
    if (!next.smtp_host) next.smtp_host = await ask("smtp_host", "SMTP host");
    if (!next.smtp_port) next.smtp_port = await ask("smtp_port", "SMTP port", "587");
    if (!next.smtp_encryption) next.smtp_encryption = await ask("smtp_encryption", "SMTP encryption: tls, ssl or none", "tls");
    if (!next.smtp_username) next.smtp_username = await ask("smtp_username", "SMTP user name (Enter for none)", undefined, true);
    if (next.smtp_username) await askSecret("SMTP_PASSWORD", "SMTP password");
    if (!next.mail_from) next.mail_from = await ask("mail_from", "Sender address", `no-reply@${domain}`);
  }

  if (!next.ai) next.ai = await ask("ai", mode === "development" ? "AI provider: fake (deterministic, no cost), none or anthropic" : "AI provider: none or anthropic", mode === "development" ? "fake" : "none");
  if (next.ai === "anthropic") await askSecret("ANTHROPIC_API_KEY", "Anthropic API key");

  ctx.io.stderr("Storage: the bundled SeaweedFS object store. Choosing another S3 store once content exists needs a migration (operators guide, storage).");
  return { input: next, secrets: found };
}
