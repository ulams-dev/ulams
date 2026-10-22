import Ajv2020, { type ErrorObject, type ValidateFunction } from "ajv/dist/2020.js";
import blueprintSchema from "./blueprint-v3.json" with { type: "json" };
import { loadCourse } from "./load.ts";
import type { AssetLookup, Files, Issue, Obj } from "./types.ts";

let compiled: ValidateFunction | null = null;

function blueprintValidator(): ValidateFunction {
  if (!compiled) compiled = new Ajv2020({ allErrors: true, strict: false }).compile(blueprintSchema);
  return compiled;
}

/** The ajv errors a person can act on: the `if`/`oneOf` wrappers and "must be null" alternatives are noise. */
function actionable(errors: ErrorObject[]): ErrorObject[] {
  const real = errors.filter((e) => !["if", "oneOf", "anyOf"].includes(e.keyword));
  const underAlternative = (e: ErrorObject) => e.keyword === "type" && (e.params as { type?: string }).type === "null";
  const out = real.filter((e) => !underAlternative(e) && !(e.keyword === "minItems" && /\/citations$/.test(e.instancePath)));
  const seen = new Set<string>();
  return out.filter((e) => {
    const key = `${e.instancePath}|${e.keyword}|${JSON.stringify(e.params)}`;
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

function describe(e: ErrorObject): string {
  const where = e.instancePath === "" ? "/" : e.instancePath;
  const p = e.params as Record<string, unknown>;
  switch (e.keyword) {
    case "required":
      return `${where}: missing "${String(p.missingProperty)}"`;
    case "additionalProperties":
      return `${where}: unexpected property "${String(p.additionalProperty)}"`;
    case "enum":
      return `${where}: must be one of ${(p.allowedValues as unknown[]).map(String).join(", ")}`;
    default:
      return `${where}: ${e.message ?? "is invalid"}`;
  }
}

export interface ValidateResult {
  issues: Issue[];
  /** The Blueprint v3 document the folder maps to (complete enough to inspect even when issues exist). */
  doc: Obj;
}

/** Runs every offline check: files, grammar, mapping to Blueprint v3, the bundled v3 schema and the deterministic checks. */
export function validateFiles(files: Files, assets: AssetLookup): ValidateResult {
  const loaded = loadCourse(files, assets);
  if (loaded.structurallySound) {
    const validate = blueprintValidator();
    if (!validate(loaded.doc)) {
      for (const e of actionable(validate.errors ?? [])) {
        const at = loaded.locate(e.instancePath);
        loaded.sink.add("BLUEPRINT_SCHEMA", { file: at.file, pos: at.pos }, describe(e));
      }
    }
  }
  return { issues: loaded.sink.sorted(), doc: loaded.doc };
}
