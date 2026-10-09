import { z } from "zod";
import { CliError } from "../errors.ts";
import { buildRequest, type BuiltRequest } from "./request.ts";
import type { AnyCommand, Ctx, FsPort, RequestSpec, Result } from "./types.ts";

type HttpDef = Omit<AnyCommand, "run" | "output" | "stability" | "since" | "request"> & {
  request: RequestSpec;
  output?: z.ZodType;
};

/** Appends a value to FormData the way Laravel expects: nested keys as a[b], lists as a[0]. */
export function appendForm(form: FormData, key: string, value: unknown): void {
  if (value === undefined || value === null) return;
  if (typeof value === "boolean") form.append(key, value ? "1" : "0");
  else if (Array.isArray(value)) value.forEach((item, i) => appendForm(form, `${key}[${i}]`, item));
  else if (typeof value === "object") for (const [k, v] of Object.entries(value as Record<string, unknown>)) appendForm(form, `${key}[${k}]`, v);
  else form.append(key, String(value));
}

export async function buildForm(fs: FsPort, req: BuiltRequest, input: Record<string, unknown>): Promise<FormData> {
  const form = new FormData();
  for (const [key, value] of Object.entries(req.form ?? {})) appendForm(form, key, value);
  for (const [inputKey, field] of Object.entries(req.files ?? {})) {
    const value = input[inputKey];
    if (value === undefined || value === null || value === "") continue;
    const paths = Array.isArray(value) ? (value as string[]) : [String(value)];
    for (const [i, path] of paths.entries()) {
      let bytes: Uint8Array;
      try {
        bytes = await fs.readFile(path);
      } catch {
        throw new CliError("INPUT_INVALID", `Cannot read the file for --${inputKey}: ${path}`, { hint: "Pass the path of an existing local file." });
      }
      const name = path.split(/[\\/]/).pop() ?? "file";
      form.append(Array.isArray(value) || paths.length > 1 ? `${field}[${i}]` : field, new Blob([bytes as BlobPart]), name);
    }
  }
  return form;
}

export async function runHttp(ctx: Ctx, spec: RequestSpec, input: Record<string, unknown>): Promise<Result> {
  const req = buildRequest(spec, input);
  const common = {
    params: req.params,
    query: req.query,
    idempotent: spec.method !== "POST",
    signal: ctx.signal,
    // Only an explicit key: the server cannot deduplicate until scoped tokens (S1) land, so an automatic
    // key would only make retrying a POST look safe.
    ...(ctx.flags.idempotencyKey ? { idempotencyKey: ctx.flags.idempotencyKey } : {}),
  };
  if (spec.download) {
    const file = await ctx.client.download(spec.method, spec.path, common);
    const out = (input.out as string | undefined) ?? ctx.flags.out;
    const isText = /^text\/|json|csv|xml/.test(file.contentType ?? "");
    if (!out) {
      if (isText) return { data: new TextDecoder().decode(file.data) };
      throw new CliError("INPUT_INVALID", `This command downloads ${file.contentType ?? "a file"}; pass --out <path>.`, {
        hint: "Example: --out ./export.xlsx",
      });
    }
    if (out === "-") {
      if (!isText) throw new CliError("INPUT_INVALID", "Binary downloads cannot go to stdout; pass --out <path>.");
      return { data: new TextDecoder().decode(file.data) };
    }
    await ctx.fs.writeFile(out, file.data);
    return { data: { path: out, bytes: file.data.byteLength, contentType: file.contentType, filename: file.filename } };
  }
  const form = req.form ? await buildForm(ctx.fs, req, input) : undefined;
  const res = await ctx.client.call(spec.method, spec.path, { ...common, body: req.body, ...(form ? { form } : {}) });
  return { data: res.data, ...(res.meta ? { meta: res.meta } : {}) };
}

/** Wraps a declarative definition (generated or curated) into a registry command. */
export function httpCommand(def: HttpDef): AnyCommand {
  return {
    stability: "stable",
    since: "0.1.0",
    output: z.unknown(),
    ...def,
    run: (ctx: Ctx, input: Record<string, unknown>) => runHttp(ctx, def.request, input),
  } as unknown as AnyCommand;
}
