// Contributor files kept at the repository root (where GitHub shows them) rendered as pages.
import { exists, read, rewriteLinks, splitTitle, writePage, EDIT } from "../lib.mjs";

const FILES = [
  {
    source: "CONTRIBUTING.md",
    out: "contributing/contributing-guide.md",
    title: "Contributing guide",
    description: "How to contribute to ulams: issues, pull requests, branches, commits, tests, docs, decisions and the licence of contributions.",
    order: 1,
  },
  {
    source: "CODE_OF_CONDUCT.md",
    out: "contributing/code-of-conduct.md",
    title: "Code of conduct",
    description: "The Contributor Covenant 2.1 code of conduct that applies to everyone taking part in the ulams project.",
    order: 3,
  },
  {
    source: "SECURITY.md",
    out: "contributing/security-policy.md",
    title: "Security policy",
    description: "How to report a security vulnerability in ulams privately, what to include, and how disclosure works.",
    order: 4,
  },
];

const internal = (p) =>
  ({
    "CONTRIBUTING.md": "/contributing/contributing-guide/",
    "CODE_OF_CONDUCT.md": "/contributing/code-of-conduct/",
    "SECURITY.md": "/contributing/security-policy/",
    "docs/ROADMAP-TODO.md": "/roadmap/",
    "docs/decisions": "/decisions/",
    "docs/decisions/README.md": "/decisions/",
  })[p.replace(/\/$/, "")] ?? (/^docs\/decisions\/\d{4}-.+\.md$/.test(p) ? `/decisions/${p.slice(15, -3)}/` : null);

export default function generate() {
  const written = [];
  for (const f of FILES) {
    if (!exists(f.source)) continue;
    const { body } = splitTitle(read(f.source));
    written.push(
      writePage(
        f.out,
        {
          title: f.title,
          description: f.description,
          generatedFrom: f.source,
          editUrl: `${EDIT}/${f.source}`,
          sidebar: { order: f.order },
        },
        rewriteLinks(body, f.source, internal)
      )
    );
  }
  return written;
}
