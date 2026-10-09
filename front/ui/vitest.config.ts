import { getViteConfig } from "astro/config";

// getViteConfig adds Astro's Vite plugins, so tests can render .astro components with the container API.
export default getViteConfig({ test: { include: ["tests/**/*.test.ts"], environment: "node" } } as never);
