// Topic type reference (from the classes registered with Topic::registerContentClass) and the
// Reference section's index page.
import { cell, read, walk, writePage, BLOB } from "../lib.mjs";
import { packageNames } from "../lib-code.mjs";

function topicTypes() {
  const out = [];
  for (const p of packageNames()) {
    const files = walk(`api/packages/${p}/src`, (f) => f.endsWith(".php"));
    for (const f of files.filter((x) => x.endsWith("ServiceProvider.php"))) {
      for (const m of read(f).matchAll(/registerContentClass(?:es)?\(\s*(\[[\s\S]*?\]|[^)]*)\)/g)) {
        for (const c of m[1].matchAll(/(\w+)::class/g)) {
          const name = c[1];
          const model = files.find((x) => x.endsWith(`/${name}.php`) && /class\s+\w+\s+extends/.test(read(x)));
          const src = model ? read(model) : "";
          const rules = src.match(/function\s+rules\(\)[^{]*\{\s*return\s*\[([\s\S]*?)\];/)?.[1] ?? "";
          const fields = [...rules.matchAll(/['"]([\w.*]+)['"]\s*=>\s*(\[[^\]]*\]|['"][^'"]*['"])/g)].map(
            (r) => `\`${r[1]}\`: ${r[2].replace(/['"]\s*\.\s*(\w+)::class\s*\.\s*['"]/g, "$1").replace(/[\[\]'"]/g, "").replace(/\s+/g, " ").trim()}`
          );
          const table = src.match(/protected\s+\$table\s*=\s*['"]([^'"]+)['"]/)?.[1] ?? "";
          if (!out.some((o) => o.name === name)) out.push({ name, pkg: p, model, fields, table });
        }
      }
    }
  }
  return out;
}

export default function generate() {
  const types = topicTypes();
  return [
    writePage(
      "reference/topic-types.md",
      {
        title: "Topic types",
        description: `The ${types.length} topic content types registered by the API packages, with their package, table and validation rules.`,
        generatedFrom: "Topic::registerContentClass calls in api/packages",
        editUrl: false,
        sidebar: { order: 9 },
      },
      `A topic's content is one of these classes, registered by its package with \`Topic::registerContentClass\` (package [courses](/reference/packages/courses/)). The API identifies a type by its full class name (\`topicable_type\`). How to use each one: [Content creators](/creators/); how to add one: [New topic type](/extending/new-topic-type/).\n\n| Type | Package | Validation rules | Model |\n|---|---|---|---|\n${types
        .map(
          (t) =>
            `| \`${t.name}\` | [${t.pkg}](/reference/packages/${t.pkg}/) | ${cell(t.fields.join("; "))} | ${
              t.model ? `[source](${BLOB}/${t.model})` : ""
            } |`
        )
        .join("\n")}`
    ),
    writePage(
      "reference/index.md",
      {
        title: "Reference",
        description: "Reference pages generated from the ulams code on every docs build: packages, permissions, settings, environment, events, commands, jobs, endpoints.",
        generatedFrom: "the repository (scripts/sync-content.mjs)",
        editUrl: false,
        sidebar: { label: "Overview", order: 0 },
      },
      `Everything in this section is generated from the repository by [\`front/docs-site/scripts\`](${BLOB}/front/docs-site/scripts/sync-content.mjs) before every build, so it lists what the code declares today. To change a page, change the code or the README it comes from.

| Page | Built from |
|---|---|
| [API packages](/reference/packages/) | \`api/packages/*\`: README plus endpoints, permissions, settings, events, commands and jobs per package |
| [API endpoints](/reference/api-endpoints/) | route definitions in \`api/routes\` and the packages |
| [Permissions](/reference/permissions/) | permission enums and seeders |
| [Settings keys](/reference/settings/) | \`AdministrableConfig::registerConfig\` calls |
| [Environment variables](/reference/environment-variables/) | \`api/docs/enviromental-variables.md\` and \`env()\` calls |
| [Events and notifications](/reference/events-and-notifications/) | \`src/Events\` classes and \`Template::register\` calls |
| [Artisan commands](/reference/artisan-commands/) | command classes |
| [Scheduled jobs](/reference/scheduled-jobs/) | \`$schedule\` calls |
| [UI catalogue](/reference/ui-catalogue/) | \`front/ui/src/registry.ts\` |
| [Topic types](/reference/topic-types/) | \`Topic::registerContentClass\` calls |
| Apps: [H5P service](/reference/apps/api-h5p/), [PDF service](/reference/apps/api-pdf/), [front/web](/reference/apps/web/), [admin](/reference/apps/admin/), [legacy front](/reference/apps/front/), [api](/reference/apps/api/) | each app's README |`
    ),
  ];
}
