// The component playground (ADR 0054): one page per @ulams/ui catalogue component, generated from
// the registry and front/ui/catalogue/examples/<Name>.json. Static parts (model description, props
// table, text fallback, invalid-props example) are written here; the live render is an iframe of
// /catalogue/preview/<Name>/ and the axe result is computed at build time by <AxeResult>.
import { readFileSync } from "node:fs";
import { pathToFileURL } from "node:url";
import { join } from "node:path";
import { cell, writePage, ROOT, EDIT, BLOB } from "../lib.mjs";

const slug = (name) => name.replace(/([a-z0-9])([A-Z])/g, "$1-$2").toLowerCase();

const type = (s) => {
  if (s.enum) return s.enum.map((v) => `\`${JSON.stringify(v)}\``).join(" \\| ");
  const t = Array.isArray(s.type) ? s.type.join(" \\| ") : (s.type ?? "any");
  if (t === "array") return `array of ${s.items?.type === "object" ? "objects" : (s.items?.type ?? "any")}`;
  return s.format ? `${t} (${s.format})` : t;
};

const limits = (s) =>
  [
    s.minLength !== undefined && `min length ${s.minLength}`,
    s.maxLength !== undefined && `max length ${s.maxLength}`,
    s.minItems !== undefined && s.minItems > 0 && `min ${s.minItems} items`,
    s.maxItems !== undefined && `max ${s.maxItems} items`,
    s.minimum !== undefined && `≥ ${s.minimum}`,
    s.maximum !== undefined && `≤ ${s.maximum}`,
    s.default !== undefined && !(Array.isArray(s.default) && s.default.length === 0) && `default \`${JSON.stringify(s.default)}\``,
  ]
    .filter(Boolean)
    .join(", ");

function rows(schema, prefix = "") {
  const out = [];
  const req = new Set(schema?.required ?? []);
  for (const [name, s] of Object.entries(schema?.properties ?? {})) {
    const path = `${prefix}${name}`;
    out.push(`| \`${path}\` | ${type(s)} | ${req.has(name) ? "yes" : ""} | ${mdx(cell([s.description, limits(s)].filter(Boolean).join(". ")))} |`);
    const nested = s.type === "array" ? s.items : s.type === "object" ? s : null;
    if (nested?.properties) out.push(...rows(nested, `${path}${s.type === "array" ? "[]" : ""}.`));
  }
  return out;
}

/** MDX reads braces and angle brackets in prose as code: escape them. */
const mdx = (text) => String(text).replace(/[{}]/g, (c) => `\\${c}`).replace(/</g, "&lt;");
const fence = (text, lang = "") => `\`\`\`${lang}\n${text}\n\`\`\``;
const firstSentence = (text) => text.split(/(?<=\.)\s/)[0];
/** Whole sentences up to 200 characters (at least the first one, at least 40 characters when there is more). */
const summary = (text) => {
  let out = "";
  for (const sentence of text.split(/(?<=\.)\s/)) {
    if (out && (out + " " + sentence).length > 200) break;
    out = out ? `${out} ${sentence}` : sentence;
    if (out.length >= 40) break;
  }
  return out.slice(0, 200);
};

export default async function generate() {
  const registryMod = await import(pathToFileURL(join(ROOT, "front/ui/src/registry.ts")).href);
  const core = await import(pathToFileURL(join(ROOT, "front/ui/src/render-core.ts")).href);
  const { registry, THEMES } = registryMod;
  const names = Object.keys(registry);
  const pages = [];

  for (const name of names) {
    const spec = registry[name];
    const examplePath = `front/ui/catalogue/examples/${name}.json`;
    const example = JSON.parse(readFileSync(join(ROOT, examplePath), "utf8"));
    const doc = { component: name, props: example.props, ...(example.children ? { children: example.children } : {}) };
    const fallback = spec.fallback(example.props);
    const outcome = core.prepare({ component: name, props: example.invalid });
    const issues = outcome.kind === "fallback" ? outcome.issues.slice(0, 5) : [];
    const invalidText = outcome.kind === "fallback" ? outcome.text : "";
    const props = rows(spec.props);
    const flags = [spec.category, spec.interactive ? "interactive (ships a small web component)" : "static (no JavaScript)", spec.children ? "renders children" : "no children"].join(" · ");
    const preview = (theme) => `/catalogue/preview/${name}/?theme=${theme}`;

    const body = `import AxeResult from "../../../components/AxeResult.astro";

_${flags}_. All components: [component playground](/catalogue/).

## Description for the model

${mdx(spec.description)}

## Live example

Rendered from the example props below with the real component. Theme: ${THEMES.map((t) => `<a href="${preview(t)}" target="preview">${t}</a>`).join(" · ")} · <a href="${preview("platform")}">open on its own</a>

<iframe name="preview" title="Live render of ${name}" src="${preview("platform")}" loading="lazy" style="width:100%;height:520px;border:1px solid var(--sl-color-gray-5);border-radius:8px;background:#fff;resize:vertical"></iframe>

## Example

${fence(JSON.stringify(doc, null, 2), "json")}

## Props

${props.length ? `| Prop | Type | Required | Notes |\n|---|---|---|---|\n${props.join("\n")}` : "No props."}

## Text fallback

What a text-only client, an unknown renderer or a plain-text channel shows for the example above.

${fallback.trim() === "" ? "_Empty: this component only groups other components._" : fence(fallback)}

## Invalid props

A node whose props fail the schema never breaks the page: the renderer shows the plain-text fallback instead (and logs the issues in development).

${fence(JSON.stringify(example.invalid, null, 2), "json")}

${issues.length ? `Issues:\n\n${issues.map((i) => `- \`${i.path}\` ${mdx(i.message)}`).join("\n")}\n` : ""}
Rendered as:

${invalidText.trim() === "" ? "_Nothing: this component has no text of its own._" : fence(invalidText)}

## Accessibility

<AxeResult name="${name}" />
`;
    pages.push(
      writePage(
        `catalogue/${slug(name)}.mdx`,
        {
          title: name,
          description: summary(spec.description),
          generatedFrom: examplePath,
          editUrl: `${EDIT}/${examplePath}`,
        },
        body
      )
    );
  }

  const categories = [
    ["structure", "Structure"],
    ["section", "Landing page sections"],
    ["course", "Course"],
    ["learning", "Learning content"],
  ];
  const index = categories
    .map(([key, label]) => {
      const items = names.filter((n) => registry[n].category === key);
      if (!items.length) return "";
      return `## ${label}\n\n${items.map((n) => `- [${n}](/catalogue/${slug(n)}/)${registry[n].interactive ? " (interactive)" : ""}: ${mdx(firstSentence(registry[n].description))}`).join("\n")}`;
    })
    .filter(Boolean)
    .join("\n\n");
  pages.push(
    writePage(
      "catalogue/index.mdx",
      {
        title: "Component playground",
        description: `All ${names.length} components of the @ulams/ui catalogue rendered live from example props, with props table, model description, text fallback and accessibility result.`,
        generatedFrom: "front/ui/catalogue/examples",
        sidebar: { order: 0 },
      },
      `The catalogue is what an agent may place in a page document. Each page renders one component live from its example, shows the props table generated from its JSON Schema, the description the model reads, the plain-text fallback, what happens with invalid props, and the accessibility check run at build time ([ADR 0054](/decisions/0054-component-playground-in-docs-site/)). The same data as one table: [UI catalogue reference](/reference/ui-catalogue/). Examples live in [\`front/ui/catalogue/examples\`](${BLOB}/front/ui/catalogue/examples); every component needs one (checked by \`yarn workspace @ulams/docs coverage\`).\n\n${index}`
    )
  );
  return pages;
}
