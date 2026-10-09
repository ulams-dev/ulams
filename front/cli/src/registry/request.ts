import type { RequestSpec } from "./types.ts";

export interface BuiltRequest {
  method: RequestSpec["method"];
  path: string;
  params: Record<string, string | number>;
  query: Record<string, string | number | boolean>;
  body?: unknown;
  /** multipart: plain fields */
  form?: Record<string, unknown>;
  /** multipart: input key -> multipart field name */
  files?: Record<string, string>;
}

/** Builds the HTTP request of a declarative command from its (validated) input. */
export function buildRequest(spec: RequestSpec, input: Record<string, unknown>): BuiltRequest {
  const params: Record<string, string | number> = {};
  for (const name of spec.pathParams) {
    const value = input[name];
    if (value !== undefined && value !== null) params[name] = value as string | number;
  }
  const query: Record<string, string | number | boolean> = {};
  for (const name of spec.queryParams) {
    const value = input[name];
    if (value !== undefined && value !== null) query[name] = value as string | number | boolean;
  }
  const built: BuiltRequest = { method: spec.method, path: spec.path, params, query };
  if (spec.bodyMode === "json") {
    if (spec.wholeBody) built.body = input.body;
    else {
      // Declared fields plus any extra keys: the spec is not complete, so unknown keys are sent too.
      const body: Record<string, unknown> = {};
      const skip = new Set([...spec.pathParams, ...spec.queryParams, "out"]);
      for (const [name, value] of Object.entries(input)) if (value !== undefined && !skip.has(name)) body[name] = value;
      built.body = body;
    }
  } else if (spec.bodyMode === "multipart") {
    const form: Record<string, unknown> = {};
    const skip = new Set([...spec.pathParams, ...spec.queryParams, "out"]);
    for (const [name, value] of Object.entries(input)) {
      if (value !== undefined && !skip.has(name) && !(spec.files && name in spec.files)) form[name] = value;
    }
    built.form = form;
    if (spec.files) built.files = spec.files;
  }
  return built;
}

export function fillPath(path: string, params: Record<string, string | number>): string {
  return path.replace(/\{(\w+)\}/g, (_, key: string) => (params[key] === undefined ? `{${key}}` : encodeURIComponent(String(params[key]))));
}
