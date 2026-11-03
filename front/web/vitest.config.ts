import { getViteConfig } from "astro/config";

// getViteConfig adds Astro's Vite plugins, so tests can render .astro components with the container API.
// The catalogue stylesheets are processed (not stubbed) so the style guide can read its tokens from them (`?raw`).
export default getViteConfig({ test: { include: ["tests/unit/**/*.test.ts"], environment: "node", css: { include: [/\/ui\/src\/styles\//] } } } as never);
