/**
 * A small JSON Schema subset used by the UI catalogue: enough to validate component props
 * produced by an agent, apply defaults and reject unsafe links. Supported keywords:
 * type, enum, const, default, description, properties, required, additionalProperties
 * (false by default for objects), items, minItems, maxItems, minLength, maxLength,
 * minimum, maximum, format ("href", "date-time").
 */

export type JsonType = "string" | "number" | "integer" | "boolean" | "array" | "object";

export interface JsonSchema {
  type?: JsonType | JsonType[];
  description?: string;
  enum?: ReadonlyArray<string | number | boolean | null>;
  const?: string | number | boolean | null;
  default?: unknown;
  properties?: Record<string, JsonSchema>;
  required?: ReadonlyArray<string>;
  additionalProperties?: boolean;
  items?: JsonSchema;
  minItems?: number;
  maxItems?: number;
  minLength?: number;
  maxLength?: number;
  minimum?: number;
  maximum?: number;
  format?: "href" | "date-time";
  examples?: ReadonlyArray<unknown>;
}

export interface ValidationIssue {
  path: string;
  message: string;
}

export interface ValidationResult<T = unknown> {
  valid: boolean;
  value: T;
  issues: ValidationIssue[];
}

/**
 * Links and image sources must be relative, fragment, http(s) or mailto/tel. This rejects
 * `javascript:`, `data:` and other schemes an agent (or injected content) could emit.
 */
export function isSafeHref(value: string): boolean {
  const v = value.trim();
  if (v === "") return false;
  if (v.startsWith("/") && !v.startsWith("//")) return true;
  if (v.startsWith("#") || v.startsWith("?") || v.startsWith("./")) return true;
  return /^(https?:\/\/|mailto:|tel:)/i.test(v);
}

const typeOf = (value: unknown): JsonType | "null" | "undefined" => {
  if (value === null) return "null";
  if (value === undefined) return "undefined";
  if (Array.isArray(value)) return "array";
  if (typeof value === "number") return Number.isInteger(value) ? "integer" : "number";
  return typeof value as JsonType;
};

const matchesType = (value: unknown, type: JsonType): boolean => {
  const actual = typeOf(value);
  if (type === "number") return actual === "number" || actual === "integer";
  return actual === type;
};

const clone = <T>(value: T): T => (value === undefined ? value : (JSON.parse(JSON.stringify(value)) as T));

/** Validates `input` against `schema`; returns a copy with defaults applied. */
export function validate<T = unknown>(schema: JsonSchema, input: unknown, path = ""): ValidationResult<T> {
  const issues: ValidationIssue[] = [];
  const value = walk(schema, input === undefined ? clone(schema.default) : input, path || "/", issues);
  return { valid: issues.length === 0, value: value as T, issues };
}

function walk(schema: JsonSchema, value: unknown, path: string, issues: ValidationIssue[]): unknown {
  const fail = (message: string) => {
    issues.push({ path, message });
    return value;
  };

  if (value === undefined) return value;

  if (schema.type) {
    const types = Array.isArray(schema.type) ? schema.type : [schema.type];
    if (!types.some((t) => matchesType(value, t))) {
      return fail(`expected ${types.join(" | ")}, got ${typeOf(value)}`);
    }
  }
  if (schema.const !== undefined && value !== schema.const) return fail(`must be ${JSON.stringify(schema.const)}`);
  if (schema.enum && !schema.enum.includes(value as string)) {
    return fail(`must be one of ${schema.enum.map((e) => JSON.stringify(e)).join(", ")}`);
  }

  if (typeof value === "string") {
    if (schema.minLength !== undefined && value.length < schema.minLength) fail(`shorter than ${schema.minLength}`);
    if (schema.maxLength !== undefined && value.length > schema.maxLength) fail(`longer than ${schema.maxLength}`);
    if (schema.format === "href" && !isSafeHref(value)) fail("unsafe or empty link");
    if (schema.format === "date-time" && Number.isNaN(Date.parse(value))) fail("not a date");
  }
  if (typeof value === "number") {
    if (schema.minimum !== undefined && value < schema.minimum) fail(`less than ${schema.minimum}`);
    if (schema.maximum !== undefined && value > schema.maximum) fail(`more than ${schema.maximum}`);
  }

  if (Array.isArray(value)) {
    if (schema.minItems !== undefined && value.length < schema.minItems) fail(`fewer than ${schema.minItems} items`);
    if (schema.maxItems !== undefined && value.length > schema.maxItems) fail(`more than ${schema.maxItems} items`);
    if (schema.items) {
      const itemSchema = schema.items;
      return value.map((item, i) => walk(itemSchema, item, `${path === "/" ? "" : path}/${i}`, issues));
    }
    return value;
  }

  if (typeOf(value) === "object" && (schema.properties || schema.type === "object")) {
    const input = value as Record<string, unknown>;
    const out: Record<string, unknown> = {};
    const props = schema.properties ?? {};
    for (const key of schema.required ?? []) {
      if (input[key] === undefined || input[key] === null) {
        issues.push({ path: `${path === "/" ? "" : path}/${key}`, message: "is required" });
      }
    }
    for (const [key, child] of Object.entries(props)) {
      const childPath = `${path === "/" ? "" : path}/${key}`;
      const raw = input[key] === undefined ? clone(child.default) : input[key];
      if (raw === undefined) continue;
      if (raw === null && !(schema.required ?? []).includes(key)) continue;
      out[key] = walk(child, raw, childPath, issues);
    }
    for (const key of Object.keys(input)) {
      if (props[key]) continue;
      if (schema.additionalProperties === true) out[key] = input[key];
      else issues.push({ path: `${path === "/" ? "" : path}/${key}`, message: "is not a known prop" });
    }
    return out;
  }
  return value;
}
