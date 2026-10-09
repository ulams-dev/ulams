import { getViteConfig } from "astro/config";

// getViteConfig lets the tests render the catalogue's .astro components with the container API;
// they are compiled for the server even when a test mounts the HTML in jsdom (for axe).
export default getViteConfig({ test: { include: ["tests/**/*.test.ts"], environment: "node", testTransformMode: { ssr: ["**/*.test.ts"] } } });
