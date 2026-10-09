// Technical notes that phases keep next to the plans (docs/phase-N/*.md, e.g. LiaScript, LTI,
// Adapt, H5P operations, conformance) are rendered as reference pages, so they appear on the
// site as soon as they are merged, without a copy.
import { list, isDir, read, rewriteLinks, splitTitle, firstParagraph, plain, writePage, EDIT } from "../lib.mjs";

export default function generate() {
  const written = [];
  const phases = list("docs").filter((d) => /^phase-\d+/.test(d) && isDir(`docs/${d}`));
  const index = [];
  for (const phase of phases) {
    for (const file of list(`docs/${phase}`).filter((f) => f.endsWith(".md"))) {
      const source = `docs/${phase}/${file}`;
      const { title, body } = splitTitle(read(source));
      const name = title ?? file.replace(/\.md$/, "");
      const out = `reference/notes/${phase}/${file}`;
      written.push(
        writePage(
          out,
          {
            title: name,
            description: plain(`${phase.replace("-", " ")} notes: ${plain(firstParagraph(body), 200)}`, 160).padEnd(20, "."),
            generatedFrom: source,
            editUrl: `${EDIT}/${source}`,
          },
          rewriteLinks(body, source, (p) =>
            /^docs\/decisions\/\d{4}-.+\.md$/.test(p) ? `/decisions/${p.slice(15, -3)}/` : p === "docs/ROADMAP-TODO.md" ? "/roadmap/" : null
          )
        )
      );
      index.push(`- [${name}](/reference/notes/${phase}/${file.replace(/\.md$/, "")}/) (${phase})`);
    }
  }
  if (index.length) {
    written.push(
      writePage(
        "reference/notes/index.md",
        {
          title: "Phase notes",
          description: "Technical notes written during the roadmap phases (docs/phase-N), rendered from the repository.",
          generatedFrom: "docs/phase-*/*.md",
          editUrl: false,
          sidebar: { label: "Overview", order: 0 },
        },
        `Notes kept next to the phase plans in \`docs/phase-N/\`.\n\n${index.join("\n")}`
      )
    );
  }
  return written;
}
