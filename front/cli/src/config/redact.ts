const SECRET_KEY = /token|secret|password|passwd|api[_-]?key|private[_-]?key|device_code|client_secret|authorization|cookie/i;
const JWT = /eyJ[\w-]+\.[\w-]+\.[\w-]+/g;
const PAT = /ulams_pat_\w+/g;
const BEARER = /(Bearer\s+)[\w.~+/=-]+/gi;

export const REDACTED = "«redacted»";

export function redactString(value: string, extra: string[] = []): string {
  let out = value.replace(JWT, REDACTED).replace(PAT, REDACTED).replace(BEARER, `$1${REDACTED}`);
  for (const secret of extra) {
    if (secret && secret.length >= 6) out = out.split(secret).join(REDACTED);
  }
  return out;
}

/** Deep copy with secret-looking keys and token-looking strings replaced. */
export function redact<T>(value: T, extra: string[] = []): T {
  const walk = (node: unknown, key?: string): unknown => {
    if (key && SECRET_KEY.test(key) && node !== null && node !== undefined && typeof node !== "boolean") return REDACTED;
    if (typeof node === "string") return redactString(node, extra);
    if (Array.isArray(node)) return node.map((item) => walk(item));
    if (node && typeof node === "object") {
      return Object.fromEntries(Object.entries(node as Record<string, unknown>).map(([k, v]) => [k, walk(v, k)]));
    }
    return node;
  };
  return walk(value) as T;
}
