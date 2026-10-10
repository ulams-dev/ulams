// Validates a ulams-interactive.json (ADR 0086) the way the API does: the JSON Schema first, then the
// rules a schema cannot say (a text and a title in every locale, unique step ids, the licence allow-list,
// files that must exist). The schema is the bridge workspace's copy; the API keeps an identical one.
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const here = dirname(fileURLToPath(import.meta.url));
export const SCHEMA_PATH = join(here, "..", "..", "..", "front", "interactive-bridge", "schema", "ulams-interactive.v1.json");

export const LICENCES = ["MIT", "Apache-2.0", "BSD-2-Clause", "BSD-3-Clause", "ISC", "GPL-3.0-only", "GPL-3.0-or-later", "CC-BY-4.0", "CC-BY-SA-4.0", "CC0-1.0", "LicenseRef-Proprietary"];

/** The file types the API accepts in a package (api/packages/interactive/src/config.php). */
export const ALLOWED_EXTENSIONS = ["html", "htm", "js", "mjs", "css", "json", "map", "txt", "md", "svg", "png", "jpg", "jpeg", "webp", "avif", "gif", "ico", "woff", "woff2", "ttf", "otf", "mp3", "ogg", "wav", "mp4", "webm", "glb", "gltf", "bin", "wasm", "csv", "tsv", "geojson", "topojson", "xml"];

/** A small JSON Schema subset validator (the keywords the manifest schema uses). */
export function validateSchema(schema, value) {
  const errors = [];
  const walk = (s, v, at) => {
    if ("const" in s && v !== s.const) errors.push(`${at}: must be ${JSON.stringify(s.const)}`);
    if (Array.isArray(s.enum) && !s.enum.includes(v)) errors.push(`${at}: must be one of ${s.enum.join(", ")}`);
    switch (s.type) {
      case "string":
        if (typeof v !== "string") return void errors.push(`${at}: not a string`);
        if (s.minLength !== undefined && v.length < s.minLength) errors.push(`${at}: shorter than ${s.minLength}`);
        if (s.maxLength !== undefined && v.length > s.maxLength) errors.push(`${at}: longer than ${s.maxLength}`);
        if (s.pattern && !new RegExp(s.pattern).test(v)) errors.push(`${at}: does not match ${s.pattern}`);
        break;
      case "boolean":
        if (typeof v !== "boolean") errors.push(`${at}: not a boolean`);
        break;
      case "array":
        if (!Array.isArray(v)) return void errors.push(`${at}: not an array`);
        if (s.minItems !== undefined && v.length < s.minItems) errors.push(`${at}: fewer than ${s.minItems} items`);
        if (s.maxItems !== undefined && v.length > s.maxItems) errors.push(`${at}: more than ${s.maxItems} items`);
        if (s.uniqueItems && new Set(v.map((x) => JSON.stringify(x))).size !== v.length) errors.push(`${at}: items must be unique`);
        if (s.items) v.forEach((x, i) => walk(s.items, x, `${at}[${i}]`));
        break;
      case "object": {
        if (typeof v !== "object" || v === null || Array.isArray(v)) return void errors.push(`${at}: not an object`);
        for (const k of s.required ?? []) if (!(k in v)) errors.push(`${at}.${k}: required`);
        const props = s.properties ?? {};
        for (const [k, x] of Object.entries(v)) {
          if (props[k]) walk(props[k], x, `${at}.${k}`);
          else if (s.additionalProperties === false) errors.push(`${at}.${k}: not allowed`);
          else if (s.additionalProperties && typeof s.additionalProperties === "object") walk(s.additionalProperties, x, `${at}.${k}`);
          if (s.propertyNames?.pattern && !new RegExp(s.propertyNames.pattern).test(k)) errors.push(`${at}.${k}: invalid key`);
        }
        const n = Object.keys(v).length;
        if (s.minProperties !== undefined && n < s.minProperties) errors.push(`${at}: fewer than ${s.minProperties} properties`);
        if (s.maxProperties !== undefined && n > s.maxProperties) errors.push(`${at}: more than ${s.maxProperties} properties`);
        break;
      }
      default:
    }
  };
  walk(schema, value, "$");
  return errors;
}

/**
 * @param {unknown} manifest
 * @param {Set<string> | null} files paths in the package (relative to its root); null skips the file checks
 * @returns {string[]} problems (empty when valid)
 */
export function validateManifest(manifest, files = null) {
  const schema = JSON.parse(readFileSync(SCHEMA_PATH, "utf8"));
  const errors = validateSchema(schema, manifest);
  if (errors.length) return errors;
  const m = /** @type {any} */ (manifest);
  if (!LICENCES.includes(m.licence)) errors.push(`licence: "${m.licence}" is not an accepted SPDX id`);
  if (!m.locales.includes(m.defaultLocale)) errors.push("defaultLocale: must be one of locales");
  if (!m.title[m.defaultLocale]) errors.push("title: needs the defaultLocale");
  for (const locale of Object.keys(m.title)) if (!m.locales.includes(locale)) errors.push(`title: the locale "${locale}" is not listed in locales`);
  const seen = new Set();
  m.steps.forEach((step, i) => {
    if (seen.has(step.id)) errors.push(`steps[${i}].id: "${step.id}" is used twice`);
    seen.add(step.id);
    for (const locale of m.locales) {
      for (const field of ["title", "text"]) {
        if (!step[field]?.[locale]?.trim()) errors.push(`steps[${i}] (${step.id}).${field}: missing for "${locale}" (every step needs a text alternative in every locale)`);
      }
    }
  });
  if (m.showcase) {
    m.showcase.steps.forEach((id, i) => { if (!seen.has(id)) errors.push(`showcase.steps[${i}]: "${id}" is not a step of this package`); });
    if (files && m.showcase.poster && !files.has(m.showcase.poster)) errors.push(`showcase.poster: "${m.showcase.poster}" is not in the package`);
  }
  if (files) {
    const entry = m.entry ?? "index.html";
    if (!files.has(entry)) errors.push(`entry: "${entry}" is not in the package`);
    for (const step of m.steps) if (step.poster && !files.has(step.poster)) errors.push(`steps[${step.id}].poster: "${step.poster}" is not in the package`);
    for (const path of files) {
      if (path.endsWith("/")) continue; // a directory entry
      const name = path.split("/").pop() ?? "";
      const ext = name.includes(".") ? name.split(".").pop().toLowerCase() : "";
      if (name.startsWith(".")) errors.push(`"${path}": dotfiles are not allowed`);
      else if (!ALLOWED_EXTENSIONS.includes(ext)) errors.push(`"${path}": .${ext} files are not allowed in a package`);
    }
  }
  return errors;
}
