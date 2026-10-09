// Decisions: every ADR in docs/decisions (project-wide) and in api/, admin/, front/docs/adr
// (per-application history) becomes a page; the index tables are built from the files, so a
// new ADR shows up with its status without touching the site.
import { plain, read, list, rewriteLinks, splitTitle, writePage, cell, EDIT } from "../lib.mjs";

const SETS = [
  { dir: "docs/decisions", out: "decisions", label: "Project", order: 0 },
  { dir: "api/docs/adr", out: "decisions/api", label: "API history", order: 100 },
  { dir: "admin/docs/adr", out: "decisions/admin", label: "Admin history", order: 200 },
  { dir: "front/docs/adr", out: "decisions/front", label: "Old front history", order: 300 },
];

const adrFiles = (dir) => list(dir).filter((f) => /^\d{4}-.+\.md$/.test(f));
const pagePath = (set, file) => `/${set.out}/${file.replace(/\.md$/, "")}/`;

function parse(set, file) {
  const source = `${set.dir}/${file}`;
  const raw = read(source);
  const { title, body } = splitTitle(raw);
  const status = (raw.match(/^\s*[-*]\s*Status:\s*(.+)$/im)?.[1] ?? "Unknown").trim();
  const date = raw.match(/^\s*[-*]\s*Date:\s*(.+)$/im)?.[1]?.trim();
  const context = body.match(/##\s+Context[^\n]*\n([\s\S]*?)(\n##\s|$)/i)?.[1] ?? body;
  const number = file.slice(0, 4);
  const name = (title ?? file).replace(/^\d{4}\.\s*/, "");
  return { set, file, source, body, status, date, number, name, context };
}

const statusWord = (s) => plain(s).split(/[\s(;,]/)[0] || "Unknown";
const variant = (s) =>
  ({ Accepted: "success", Proposed: "caution", Superseded: "default", Deprecated: "danger", Rejected: "danger" })[
    statusWord(s)
  ] ?? "note";

export default function generate() {
  const written = [];
  const all = SETS.map((set) => ({ set, adrs: adrFiles(set.dir).map((f) => parse(set, f)) }));

  for (const { set, adrs } of all) {
    const known = new Set(adrs.map((a) => a.source));
    const mapInternal = (repoPath) => {
      for (const s of SETS) {
        if (repoPath.startsWith(`${s.dir}/`) && /\d{4}-.+\.md$/.test(repoPath)) {
          return pagePath(s, repoPath.slice(s.dir.length + 1));
        }
      }
      if (repoPath === "docs/ROADMAP-TODO.md") return "/roadmap/";
      return known.has(repoPath) ? null : null;
    };

    adrs.forEach((a, i) => {
      const body = rewriteLinks(a.body, a.source, mapInternal);
      written.push(
        writePage(
          `${set.out}/${a.file}`,
          {
            title: `${a.number}. ${a.name}`,
            description: plain(`${statusWord(a.status)} decision: ${plain(a.context, 400)}`, 160),
            generatedFrom: a.source,
            editUrl: `${EDIT}/${a.source}`,
            sidebar: {
              label: `${a.number} ${a.name}`,
              order: set.order + i + 1,
              badge: { text: statusWord(a.status), variant: variant(a.status) },
            },
          },
          body
        )
      );
    });

    const rows = adrs
      .map((a) => `| [${a.number}](${pagePath(set, a.file)}) | ${cell(a.name)} | ${cell(plain(a.status))} | ${cell(a.date ?? "")} |`)
      .join("\n");
    const table = `| # | Decision | Status | Date |\n|---|---|---|---|\n${rows}`;

    if (set.out === "decisions") {
      const readme = rewriteLinks(splitTitle(read("docs/decisions/README.md")).body, "docs/decisions/README.md", mapInternal);
      const intro = readme.split(/\n\|/)[0].trim();
      const history = SETS.filter((s) => s.out !== "decisions")
        .map((s) => `- [${s.label}](/${s.out}/): ${adrFiles(s.dir).length} records from \`${s.dir}\``)
        .join("\n");
      written.push(
        writePage(
          "decisions/index.md",
          {
            title: "Architecture decisions",
            description: `All ${adrs.length} project-wide architecture decision records (ADRs) of ulams with their status, plus the per-application history.`,
            generatedFrom: "docs/decisions/*.md",
            editUrl: `${EDIT}/docs/decisions/README.md`,
            sidebar: { label: "Overview", order: 0 },
          },
          `${intro}\n\nThis list is built from the files in \`docs/decisions\` on every build, so a new ADR appears here with its status as soon as it is merged. How to write one: [Decisions and documentation](/contributing/decisions-and-documentation/).\n\n${table}\n\n## Per-application history\n\nRetroactive records mined from the history of each application before the monorepo:\n\n${history}`
        )
      );
    } else {
      written.push(
        writePage(
          `${set.out}/index.md`,
          {
            title: `${set.label} decisions`,
            description: `Retroactive architecture decision records from ${set.dir}: ${adrs.length} records.`,
            generatedFrom: `${set.dir}/*.md`,
            editUrl: `${EDIT}/${set.dir}/README.md`,
            sidebar: { label: "Overview", order: set.order },
          },
          `Records from \`${set.dir}\`, kept as history of the application before the monorepo. Project-wide decisions are in [Architecture decisions](/decisions/).\n\n${table}`
        )
      );
    }
  }

  // Roadmap: docs/ROADMAP-TODO.md as one page.
  const roadmap = splitTitle(read("docs/ROADMAP-TODO.md"));
  written.push(
    writePage(
      "roadmap.md",
      {
        title: "Roadmap",
        description: "Progress of the ulams roadmap: phases, done and open items, decisions made and open decisions (docs/ROADMAP-TODO.md).",
        generatedFrom: "docs/ROADMAP-TODO.md",
        editUrl: `${EDIT}/docs/ROADMAP-TODO.md`,
        tableOfContents: { minHeadingLevel: 2, maxHeadingLevel: 2 },
      },
      `The full specification is [\`docs/ROADMAP-PROMPT.md\`](https://github.com/ulams-dev/ulams/blob/main/docs/ROADMAP-PROMPT.md). Items marked "Coming" across this site link back to the phases below. ✓ marks a done item, ☐ an open one.\n\n${rewriteLinks(
        // Task-list checkboxes would render as unlabelled disabled inputs; use plain marks.
        roadmap.body.replace(/^(\s*[-*] )\[[xX]\] /gm, "$1✓ ").replace(/^(\s*[-*] )\[ \] /gm, "$1☐ "),
        "docs/ROADMAP-TODO.md",
        (p) => {
          for (const s of SETS) if (p.startsWith(`${s.dir}/`) && /\d{4}-.+\.md$/.test(p)) return pagePath(s, p.slice(s.dir.length + 1));
          return null;
        }
      )}`
    )
  );
  return written;
}
