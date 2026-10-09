import { visit } from "unist-util-visit";

/**
 * Pages link to each other with root-relative paths ("/admin/users/"). When the site is
 * published under a base path (GitHub Pages: "/ulams/"), prefix those links with it so the
 * Markdown is the same everywhere.
 */
export function rehypeBaseLinks({ base = "/" } = {}) {
  const prefix = base.replace(/\/$/, "");
  return (tree) => {
    if (!prefix) return;
    visit(tree, "element", (node) => {
      for (const attr of ["href", "src"]) {
        const value = node.properties?.[attr];
        if (
          typeof value === "string" &&
          value.startsWith("/") &&
          !value.startsWith("//") &&
          value !== prefix &&
          !value.startsWith(`${prefix}/`)
        ) {
          node.properties[attr] = `${prefix}${value}`;
        }
      }
    });
  };
}
