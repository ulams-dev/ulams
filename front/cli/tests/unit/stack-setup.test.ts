import { describe, expect, it } from "vitest";
import { mkdtemp, readFile, rm } from "node:fs/promises";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { configure, createSecrets, stackInput } from "../../src/stack/config.ts";
import { compose } from "../../src/stack/render.ts";
import { kubernetes } from "../../src/stack/kubernetes.ts";
import { readConfig, renderInstall, withInstall } from "../../src/stack/files.ts";
import { askSetup, wizardApplies } from "../../src/stack/wizard.ts";
import type { Ctx } from "../../src/registry/types.ts";

const input = (value: Record<string, unknown> = {}) => stackInput.parse(value);
const apiEnv = (value: Record<string, unknown>) => (compose(configure(input(value))).services.api as { environment: Record<string, string> }).environment;

/** A Ctx with scripted answers; records the questions and whether each was asked hidden. */
function scripted(answers: string[], flags: Partial<Ctx["flags"]> = {}) {
  const asked: Array<{ question: string; secret: boolean }> = [], lines: string[] = [];
  const ctx = {
    env: {}, flags: { yes: false, json: false, ...flags }, io: { interactive: true, isTTY: true, stderr: (line: string) => void lines.push(line) },
    prompt: async (question: string, opts?: { secret?: boolean }) => { asked.push({ question, secret: Boolean(opts?.secret) }); return answers.shift() ?? ""; },
  } as unknown as Ctx;
  return { ctx, asked, lines };
}

describe("stack setup: mail and AI (P8-7)", () => {
  it("keeps log mail and the mode's AI default when nothing is chosen", () => {
    expect(apiEnv({})).toMatchObject({ LARAVEL_MAIL_DRIVER: "log", LARAVEL_AI_DRIVER: "fake" });
    expect(apiEnv({ mode: "production", domain: "learn.example.org" })).toMatchObject({ LARAVEL_MAIL_DRIVER: "log", LARAVEL_AI_DRIVER: "disabled" });
    expect(apiEnv({})).not.toHaveProperty("LARAVEL_MAIL_HOST");
  });

  it("renders SMTP with defaults and the password as a secret reference only", () => {
    expect(() => configure(input({ mail: "smtp" }))).toThrow("--smtp-host");
    const env = apiEnv({ mail: "smtp", smtp_host: "smtp.example.org", smtp_username: "mailer" });
    expect(env).toMatchObject({ LARAVEL_MAIL_DRIVER: "smtp", LARAVEL_MAIL_HOST: "smtp.example.org", LARAVEL_MAIL_PORT: "587", LARAVEL_MAIL_ENCRYPTION: "tls", LARAVEL_MAIL_FROM_ADDRESS: "no-reply@ulams.localhost", LARAVEL_MAIL_USERNAME: "mailer" });
    expect(env.LARAVEL_MAIL_PASSWORD).toBe("${SMTP_PASSWORD:?generated credentials required}");
    expect(apiEnv({ mail: "smtp", smtp_host: "smtp.example.org", smtp_encryption: "none" })).toMatchObject({ LARAVEL_MAIL_ENCRYPTION: "null" });
    expect(apiEnv({ mail: "smtp", smtp_host: "smtp.example.org" })).not.toHaveProperty("LARAVEL_MAIL_PASSWORD");
  });

  it("uses the Anthropic key from the credentials and keeps it out of public Kubernetes manifests", () => {
    expect(apiEnv({ ai: "anthropic" })).toMatchObject({ LARAVEL_AI_DRIVER: "anthropic", LARAVEL_ANTHROPIC_API_KEY: "${ANTHROPIC_API_KEY:?generated credentials required}" });
    const secrets = { ...createSecrets(), ANTHROPIC_API_KEY: "sk-test-key-123" };
    const k = kubernetes(configure(input({ target: "kubernetes", ai: "anthropic" })), secrets);
    expect(JSON.stringify(k.public)).not.toContain("sk-test-key-123");
    expect(JSON.stringify(k.public)).toContain("\"key\":\"ANTHROPIC_API_KEY\"");
    expect(k.private).toMatchObject({ stringData: { ANTHROPIC_API_KEY: "sk-test-key-123" } });
  });

  it("stores supplied secrets, requires them when configured, and keeps mail and AI on resume", async () => {
    const directory = await mkdtemp(join(tmpdir(), "ulams-stack-setup-"));
    try {
      await expect(withInstall(directory, () => renderInstall(directory, input({ ai: "anthropic" })))).rejects.toThrow("ULAMS_ANTHROPIC_API_KEY");
      await expect(withInstall(directory, () => renderInstall(directory, input({ mail: "smtp", smtp_host: "smtp.example.org", smtp_username: "u" })))).rejects.toThrow("ULAMS_SMTP_PASSWORD");
      await withInstall(directory, () => renderInstall(directory, input({ ai: "anthropic", mail: "smtp", smtp_host: "smtp.example.org", smtp_username: "u" }), { ANTHROPIC_API_KEY: "sk-1", SMTP_PASSWORD: "pw-1", APP_KEY: "must-not-replace" }));
      const stored = JSON.parse(await readFile(join(directory, "credentials.json"), "utf8")) as Record<string, string>;
      expect(stored).toMatchObject({ ANTHROPIC_API_KEY: "sk-1", SMTP_PASSWORD: "pw-1" });
      expect(stored.APP_KEY).toMatch(/^base64:/);
      // resume without flags or environment: the choices and the stored key stay
      const again = await withInstall(directory, () => renderInstall(directory, input()));
      expect(again.config).toMatchObject({ ai: "anthropic", mail: { mode: "smtp", host: "smtp.example.org", username: "u" } });
      expect((await readConfig(directory))?.ai).toBe("anthropic");
      // a new key from the environment replaces the stored one
      await withInstall(directory, () => renderInstall(directory, input(), { ANTHROPIC_API_KEY: "sk-2" }));
      expect(JSON.parse(await readFile(join(directory, "credentials.json"), "utf8")).ANTHROPIC_API_KEY).toBe("sk-2");
      expect(await readFile(join(directory, "compose.yaml"), "utf8")).not.toContain("sk-2");
    } finally { await rm(directory, { recursive: true, force: true }); }
  });

  it("asks only for what is missing, re-asks invalid answers and hides secrets", async () => {
    const { ctx, asked } = scripted(["", "smtp", "smtp.example.org", "", "", "mailer", "s3cret", "", "maybe", "anthropic", "sk-from-prompt"]);
    const result = await askSetup(ctx, input(), {});
    expect(result.input).toMatchObject({ admin_email: "admin@ulams.localhost", mail: "smtp", smtp_host: "smtp.example.org", smtp_port: 587, smtp_encryption: "tls", smtp_username: "mailer", mail_from: "no-reply@ulams.localhost", ai: "anthropic" });
    expect(result.secrets).toEqual({ SMTP_PASSWORD: "s3cret", ANTHROPIC_API_KEY: "sk-from-prompt" });
    expect(asked.filter(a => a.secret).map(a => a.question)).toEqual([expect.stringContaining("SMTP password"), expect.stringContaining("Anthropic API key")]);
    expect(asked.filter(a => a.question.startsWith("AI provider"))).toHaveLength(2);
  });

  it("does not ask what flags or the environment already give", async () => {
    const { ctx, asked } = scripted([]);
    const result = await askSetup(ctx, input({ admin_email: "ops@example.org", mail: "log", ai: "anthropic" }), { ANTHROPIC_API_KEY: "sk-env" });
    expect(asked).toEqual([]);
    expect(result.secrets).toEqual({ ANTHROPIC_API_KEY: "sk-env" });
  });

  it("asks for the domain in production and runs only for a new install in a terminal", async () => {
    const { ctx } = scripted(["learn.example.org", "", "", ""]);
    expect((await askSetup(ctx, input({ mode: "production" }), {})).input).toMatchObject({ domain: "learn.example.org", admin_email: "admin@learn.example.org", mail: "log", ai: "none" });
    expect(wizardApplies(scripted([]).ctx, undefined)).toBe(true);
    expect(wizardApplies(scripted([], { yes: true }).ctx, undefined)).toBe(false);
    expect(wizardApplies(scripted([], { json: true }).ctx, undefined)).toBe(false);
    expect(wizardApplies(scripted([]).ctx, configure(input()))).toBe(false);
  });
});
