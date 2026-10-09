// @ts-check
import { defineConfig } from "astro/config";
import starlight from "@astrojs/starlight";
import mermaid from "astro-mermaid";
import starlightLinksValidator from "starlight-links-validator";
import baseLinks from "./src/plugins/base-links.mjs";

/**
 * Where the site is published. GitHub Pages sets both from actions/configure-pages
 * (see .github/workflows/docs.yml); locally the site runs at the root.
 */
const site = process.env.DOCS_SITE || "https://ulams-dev.github.io";
const base = process.env.DOCS_BASE || "/";
/**
 * Links are written without the base path and validated on root builds (local, PR checks);
 * a build under a base path only prefixes them (src/plugins/base-links.mjs).
 */
const validateLinks = process.env.DOCS_VALIDATE_LINKS !== "0" && base === "/";

const repo = "https://github.com/ulams-dev/ulams";

export default defineConfig({
  site,
  base,
  trailingSlash: "always",
  server: { port: 4322 },
  integrations: [
    baseLinks(),
    mermaid({ autoTheme: true }),
    starlight({
      title: "ulams docs",
      description:
        "Documentation for ulams, the open, AI-native, headless LMS: guides for course authors, administrators, developers and operators.",
      logo: { src: "./src/assets/logo.svg", replacesTitle: false, alt: "ulams" },
      favicon: "/favicon.svg",
      social: [{ icon: "github", label: "GitHub", href: repo }],
      editLink: { baseUrl: `${repo}/edit/main/front/docs-site/` },
      lastUpdated: false,
      pagination: true,
      customCss: [
        "@fontsource-variable/inter",
        "@fontsource-variable/inter-tight",
        "@fontsource-variable/jetbrains-mono",
        "./src/styles/custom.css",
      ],
      components: {
        PageTitle: "./src/components/PageTitle.astro",
      },
      plugins: validateLinks
        ? [starlightLinksValidator({ errorOnLocalLinks: false, exclude: ["/api/**"] })]
        : [],
      sidebar: [
        { label: "Getting started", items: [{ autogenerate: { directory: "getting-started" } }] },
        { label: "Content creators", items: [{ autogenerate: { directory: "creators" } }] },
        { label: "Learners", items: [{ autogenerate: { directory: "learners" } }] },
        { label: "Administrators", items: [{ autogenerate: { directory: "admin" } }] },
        { label: "Developers", items: [{ autogenerate: { directory: "developers" } }] },
        { label: "Extending ulams", items: [{ autogenerate: { directory: "extending" } }] },
        { label: "Operators", items: [{ autogenerate: { directory: "operators" } }] },
        { label: "Reference", collapsed: true, items: [{ autogenerate: { directory: "reference" } }] },
        { label: "Contributing", items: [{ autogenerate: { directory: "contributing" } }] },
        { label: "Roadmap", link: "/roadmap/" },
      ],
    }),
  ],
});
