import { stringify as yamlStringify } from "yaml";
import type { GlobalFlags } from "../registry/types.ts";
import type { Envelope, ErrorEnvelope, SuccessEnvelope } from "./envelope.ts";

export type Mode = "json" | "ndjson" | "yaml" | "table" | "text";

export function resolveMode(flags: Pick<GlobalFlags, "json" | "output">, stdoutIsTTY: boolean, env: NodeJS.ProcessEnv = process.env): Mode {
  if (flags.json) return "json";
  let output: string = flags.output;
  if (output === "auto" && env.ULAMS_OUTPUT && env.ULAMS_OUTPUT !== "auto") output = env.ULAMS_OUTPUT;
  if (output === "auto") return stdoutIsTTY ? "table" : "json";
  return output as Mode;
}

/** Projects `data` (or each item of a list) to dotted paths. */
export function project(data: unknown, fields: string[] | undefined): unknown {
  if (!fields || fields.length === 0) return data;
  const pick = (item: unknown): unknown => {
    if (item === null || typeof item !== "object" || Array.isArray(item)) return item;
    const out: Record<string, unknown> = {};
    for (const path of fields) {
      const value = path.split(".").reduce<unknown>((acc, key) => (acc && typeof acc === "object" ? (acc as Record<string, unknown>)[key] : undefined), item);
      if (value !== undefined) setPath(out, path.split("."), value);
    }
    return out;
  };
  return Array.isArray(data) ? data.map(pick) : pick(data);
}

function setPath(target: Record<string, unknown>, path: string[], value: unknown): void {
  let node = target;
  path.forEach((key, index) => {
    if (index === path.length - 1) node[key] = value;
    else node = (node[key] ??= {}) as Record<string, unknown>;
  });
}

const cell = (value: unknown): string => {
  if (value === null || value === undefined) return "";
  if (typeof value === "object") return JSON.stringify(value);
  return String(value);
};

const DEFAULT_COLUMNS = ["id", "title", "name", "email", "status", "updated_at"];

export function renderTable(rows: Array<Record<string, unknown>>, fields?: string[]): string {
  if (rows.length === 0) return "(no results)";
  const columns =
    fields && fields.length > 0
      ? fields
      : DEFAULT_COLUMNS.filter((c) => rows.some((r) => r[c] !== undefined)).length > 0
        ? DEFAULT_COLUMNS.filter((c) => rows.some((r) => r[c] !== undefined))
        : Object.keys(rows[0] ?? {}).slice(0, 6);
  const get = (row: Record<string, unknown>, col: string) =>
    cell(col.split(".").reduce<unknown>((acc, key) => (acc && typeof acc === "object" ? (acc as Record<string, unknown>)[key] : undefined), row));
  const widths = columns.map((c) => Math.min(60, Math.max(c.length, ...rows.map((r) => get(r, c).length))));
  const line = (values: string[]) => values.map((v, i) => v.slice(0, widths[i]).padEnd(widths[i] ?? 0)).join("  ").trimEnd();
  return [line(columns.map((c) => c.toUpperCase())), ...rows.map((r) => line(columns.map((c) => get(r, c))))].join("\n");
}

function isRows(data: unknown): data is Array<Record<string, unknown>> {
  return Array.isArray(data) && data.every((x) => x && typeof x === "object" && !Array.isArray(x));
}

export function renderSuccess(envelope: SuccessEnvelope, mode: Mode, flags: Pick<GlobalFlags, "fields">): string {
  const data = project(envelope.data, flags.fields);
  const shaped: SuccessEnvelope = { ...envelope, data };
  switch (mode) {
    case "json":
      return JSON.stringify(shaped);
    case "ndjson": {
      if (Array.isArray(data)) {
        const lines = data.map((item) => JSON.stringify({ type: "item", data: item }));
        lines.push(JSON.stringify({ type: "end", ok: true, ...(envelope.meta ? { meta: envelope.meta } : {}) }));
        return lines.join("\n");
      }
      return JSON.stringify(shaped);
    }
    case "yaml":
      return yamlStringify(shaped.data ?? null).trimEnd();
    case "table":
    case "text": {
      let text: string;
      if (isRows(data)) text = renderTable(data, flags.fields);
      else if (data === null || data === undefined) text = "ok";
      else if (typeof data === "object") text = yamlStringify(data).trimEnd();
      else text = String(data);
      const meta = envelope.meta as { page?: number; lastPage?: number; total?: number } | undefined;
      if (meta?.total !== undefined) text += `\n\npage ${meta.page}/${meta.lastPage}, ${meta.total} total`;
      return text;
    }
  }
}

export function renderError(envelope: ErrorEnvelope, mode: Mode): string {
  if (mode === "json" || mode === "ndjson") return JSON.stringify(envelope);
  const e = envelope.error;
  const lines = [`error [${e.code}]: ${e.message}`];
  if (e.details && Object.keys(e.details).length > 0) lines.push(`details: ${JSON.stringify(e.details)}`);
  if (e.hint) lines.push(`hint: ${e.hint}`);
  if (e.requestId) lines.push(`request id: ${e.requestId}`);
  return lines.join("\n");
}

export function render(envelope: Envelope, mode: Mode, flags: Pick<GlobalFlags, "fields">): { stdout?: string; stderr?: string } {
  if (envelope.ok) return { stdout: renderSuccess(envelope, mode, flags) };
  // JSON mode: errors on stdout so a single parse handles both; human mode: stderr.
  if (mode === "json" || mode === "ndjson") return { stdout: renderError(envelope, mode) };
  return { stderr: renderError(envelope, mode) };
}
