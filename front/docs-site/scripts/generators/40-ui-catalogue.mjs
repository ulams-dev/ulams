// The @ulams/ui component reference, generated from front/ui/src/registry.ts (the same
// registry the renderer validates against and that `yarn workspace @ulams/ui catalogue`
// prints for agents), so the page always matches the catalogue.
import { pathToFileURL } from "node:url";
import { join } from "node:path";
import { cell, writePage, ROOT, EDIT, BLOB } from "../lib.mjs";

const type = (s) => {
  if (!s) return "";
  if (s.enum) return s.enum.map((v) => `\`${JSON.stringify(v)}\``).join(" \\| ");
  if (s.const !== undefined) return `\`${JSON.stringify(s.const)}\``;
  const t = Array.isArray(s.type) ? s.type.join(" \\| ") : (s.type ?? "any");
  if (t === "array") return `array of ${s.items?.type === "object" ? "objects" : (s.items?.type ?? "any")}`;
  return s.format ? `${t} (${s.format})` : t;
};

const limits = (s) =>
  [
    s.minLength !== undefined && `min length ${s.minLength}`,
    s.maxLength !== undefined && `max length ${s.maxLength}`,
    s.minItems !== undefined && `min ${s.minItems} items`,
    s.maxItems !== undefined && `max ${s.maxItems} items`,
    s.minimum !== undefined && `≥ ${s.minimum}`,
    s.maximum !== undefined && `≤ ${s.maximum}`,
    s.default !== undefined && `default \`${JSON.stringify(s.default)}\``,
  ]
    .filter(Boolean)
    .join(", ");

function rows(schema, prefix = "") {
  const out = [];
  const req = new Set(schema?.required ?? []);
  for (const [name, s] of Object.entries(schema?.properties ?? {})) {
    const path = `${prefix}${name}`;
    out.push(`| \`${path}\` | ${type(s)} | ${req.has(name) ? "yes" : ""} | ${cell([s.description, limits(s)].filter(Boolean).join(". "))} |`);
    const nested = s.type === "array" ? s.items : s.type === "object" ? s : null;
    if (nested?.properties) out.push(...rows(nested, `${path}${s.type === "array" ? "[]" : ""}.`));
  }
  return out;
}

export default async function generate() {
  const mod = await import(pathToFileURL(join(ROOT, "front/ui/src/registry.ts")).href);
  const catalogue = mod.catalogueJson();
  const names = Object.keys(catalogue);
  const categories = ["structure", "section", "course", "learning"];
  const sections = categories
    .map((cat) => {
      const items = names.filter((n) => catalogue[n].category === cat);
      if (!items.length) return "";
      return `## ${cat[0].toUpperCase()}${cat.slice(1)} components\n\n${items
        .map((n) => {
          const c = catalogue[n];
          const flags = [c.interactive ? "interactive (ships a web component)" : "static (no JS)", c.children ? "renders children" : "no children"].join(" · ");
          const props = rows(c.props);
          return `### ${n}\n\n${c.description}\n\n_${flags}_\n\n${
            props.length ? `| Prop | Type | Required | Notes |\n|---|---|---|---|\n${props.join("\n")}` : "No props."
          }`;
        })
        .join("\n\n")}`;
    })
    .filter(Boolean)
    .join("\n\n");

  return [
    writePage(
      "reference/ui-catalogue.md",
      {
        title: "UI catalogue",
        description: `The ${names.length} components of the @ulams/ui catalogue with their props, generated from the registry that validates page documents.`,
        generatedFrom: "front/ui/src/registry.ts",
        editUrl: `${EDIT}/front/ui/src/registry.ts`,
        sidebar: { order: 8 },
      },
      `Every component an agent or a page document may use, from [\`front/ui/src/registry.ts\`](${BLOB}/front/ui/src/registry.ts). A document is a tree of \`{ "component", "props", "children" }\` nodes rendered by \`<Render>\`; invalid props render the component's plain-text fallback. See [Reference frontend](/developers/reference-frontend/) and [Generative UI](/developers/generative-ui/); to add a component, [UI components](/extending/ui-components/).\n\nThemes: ${mod.THEMES.map((t) => `\`${t}\``).join(", ")}. Formats: ${mod.FORMATS.map((t) => `\`${t}\``).join(", ")}.\n\n${sections}`
    ),
  ];
}
