import { readdir, readFile, writeFile } from "node:fs/promises";
import { join } from "node:path";
import { fileURLToPath } from "node:url";

/**
 * Pages link to each other with root-relative paths ("/admin/users/"), in Markdown and in
 * components such as LinkCard. When the site is published under a base path (GitHub Pages:
 * "/ulams/"), this integration prefixes those links in the built HTML, so the sources stay
 * the same for every deployment. Links that already carry the base are left alone.
 */
export default function baseLinks() {
  let base = "/";
  return {
    name: "ulams-base-links",
    hooks: {
      "astro:config:done": ({ config }) => {
        base = config.base;
      },
      "astro:build:done": async ({ dir, logger }) => {
        const prefix = base.replace(/\/$/, "");
        if (!prefix) return;
        const root = fileURLToPath(dir);
        const pattern = /(\s(?:href|src)=)(["'])\/(?!\/)([^"']*)\2/g;
        let files = 0;
        const walk = async (d) => {
          for (const entry of await readdir(d, { withFileTypes: true })) {
            const p = join(d, entry.name);
            if (entry.isDirectory()) await walk(p);
            else if (entry.name.endsWith(".html")) {
              const html = await readFile(p, "utf8");
              const out = html.replace(pattern, (m, attr, q, rest) =>
                `/${rest}`.startsWith(`${prefix}/`) || `/${rest}` === prefix ? m : `${attr}${q}${prefix}/${rest}${q}`
              );
              if (out !== html) {
                await writeFile(p, out);
                files++;
              }
            }
          }
        };
        await walk(root);
        logger.info(`prefixed root-relative links with ${prefix} in ${files} page(s)`);
      },
    },
  };
}
