import { createHmac, randomBytes } from "node:crypto";

const TTL_MS = 5 * 60 * 1000;

function canonical(value: unknown): string {
  if (Array.isArray(value)) return `[${value.map(canonical).join(",")}]`;
  if (value && typeof value === "object") {
    return `{${Object.keys(value as object)
      .sort()
      .map((k) => `${JSON.stringify(k)}:${canonical((value as Record<string, unknown>)[k])}`)
      .join(",")}}`;
  }
  return JSON.stringify(value) ?? "null";
}

/** Single-use confirm tokens for destructive MCP tools (ADR 0076): HMAC over id + input + expiry. */
export class ConfirmTokens {
  private readonly key = randomBytes(32);
  private readonly used = new Set<string>();

  issue(id: string, input: unknown, now = Date.now()): string {
    const expires = now + TTL_MS;
    return `${expires}.${this.sign(id, input, expires)}`;
  }

  /** True once per token; false when expired, tampered with, reused or issued for other input. */
  consume(token: string, id: string, input: unknown, now = Date.now()): boolean {
    const [exp, sig] = token.split(".");
    const expires = Number(exp);
    if (!sig || !Number.isFinite(expires) || expires < now) return false;
    if (this.used.has(token)) return false;
    if (this.sign(id, input, expires) !== sig) return false;
    this.used.add(token);
    return true;
  }

  private sign(id: string, input: unknown, expires: number): string {
    return createHmac("sha256", this.key).update(`${id}\n${canonical(input)}\n${expires}`).digest("hex");
  }
}
