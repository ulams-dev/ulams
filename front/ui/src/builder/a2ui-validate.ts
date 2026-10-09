/**
 * Validates A2UI v0.9 server-to-client messages against the vendored JSON Schema
 * (`vendor/a2ui/v0.9/server_to_client.json`, Apache-2.0, see its NOTICE). Used in development and
 * in tests only; production builds never import this module.
 *
 * It interprets the draft 2020-12 keywords the A2UI message schemas use: `$ref` (local `#/$defs/…`),
 * `oneOf`, `type`, `const`, `enum`, `properties`, `required`, `additionalProperties`, `items`,
 * `minItems`. References into `catalog.json` (the catalogue is ours) are checked loosely.
 */
import serverToClient from "../../vendor/a2ui/v0.9/server_to_client.json";

type Schema = Record<string, unknown>;

export interface A2uiIssue {
  path: string;
  message: string;
}

const root = serverToClient as unknown as Schema;

const typeOf = (v: unknown): string => (v === null ? "null" : Array.isArray(v) ? "array" : Number.isInteger(v) ? "integer" : typeof v);

/** Our catalogue replaces `catalog.json`: components need an id and a name, the theme is an object. */
const EXTERNAL: Record<string, Schema> = {
  "catalog.json#/$defs/anyComponent": {
    type: "object",
    properties: { id: { type: "string" }, component: { type: "string" } },
    required: ["id", "component"],
  },
  "catalog.json#/$defs/theme": { type: "object" },
};

function resolve(ref: string): Schema {
  if (ref in EXTERNAL) return EXTERNAL[ref]!;
  if (!ref.startsWith("#/")) return {};
  let node: unknown = root;
  for (const part of ref.slice(2).split("/")) node = (node as Schema | undefined)?.[part];
  return (node as Schema) ?? {};
}

function check(schema: Schema, value: unknown, path: string, issues: A2uiIssue[]): void {
  if (typeof schema.$ref === "string") {
    check(resolve(schema.$ref), value, path, issues);
    return;
  }
  if (Array.isArray(schema.oneOf)) {
    const results = (schema.oneOf as Schema[]).map((option) => {
      const own: A2uiIssue[] = [];
      check(option, value, path, own);
      return own;
    });
    const valid = results.filter((own) => own.length === 0).length;
    if (valid === 1) return;
    if (valid > 1) issues.push({ path, message: "matches several message kinds" });
    else {
      // Report the issues of the option the value is closest to (fewest issues), not just "none matched".
      const closest = results.reduce((a, b) => (b.length < a.length ? b : a));
      issues.push(...(closest.length < 4 ? closest : [{ path, message: "matches none of the A2UI message kinds" }]));
    }
    return;
  }
  if ("const" in schema && value !== schema.const) issues.push({ path, message: `must be ${JSON.stringify(schema.const)}` });
  if (Array.isArray(schema.enum) && !schema.enum.includes(value)) issues.push({ path, message: "is not an allowed value" });
  if (typeof schema.type === "string") {
    const actual = typeOf(value);
    if (actual !== schema.type && !(schema.type === "number" && actual === "integer")) {
      issues.push({ path, message: `must be ${schema.type}` });
      return;
    }
  }
  if (Array.isArray(value)) {
    if (typeof schema.minItems === "number" && value.length < schema.minItems) issues.push({ path, message: `needs at least ${schema.minItems} items` });
    if (schema.items) value.forEach((item, i) => check(schema.items as Schema, item, `${path}/${i}`, issues));
  } else if (value && typeof value === "object") {
    const record = value as Record<string, unknown>;
    const props = (schema.properties ?? {}) as Record<string, Schema>;
    for (const key of (schema.required ?? []) as string[]) if (!(key in record)) issues.push({ path: `${path}/${key}`, message: "is required" });
    for (const [key, v] of Object.entries(record)) {
      if (key in props) check(props[key]!, v, `${path}/${key}`, issues);
      else if (schema.additionalProperties === false) issues.push({ path: `${path}/${key}`, message: "is not allowed" });
    }
  }
}

/** Issues of one A2UI server-to-client message (empty when valid). */
export function validateA2uiMessage(message: unknown): A2uiIssue[] {
  const issues: A2uiIssue[] = [];
  check(root, message, "", issues);
  return issues;
}

/** Issues of every message of an `a2ui-surface` activity content (`{ surfaceId, messages: […] }`). */
export function validateSurfaceContent(content: unknown): A2uiIssue[] {
  const messages = (content as { messages?: unknown } | null)?.messages;
  if (!Array.isArray(messages)) return [{ path: "/messages", message: "must be an array of A2UI messages" }];
  return messages.flatMap((message, i) => validateA2uiMessage(message).map((issue) => ({ ...issue, path: `/messages/${i}${issue.path}` })));
}
