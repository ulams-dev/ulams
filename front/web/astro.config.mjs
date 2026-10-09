// @ts-check
import { defineConfig, envField, fontProviders } from "astro/config";
import node from "@astrojs/node";

const google = fontProviders.google();
const latin = /** @type {[string]} */ (["latin", "latin-ext"]);

/**
 * Reference frontend: server-rendered on every request (tenant comes from the Host header),
 * zero client JS unless a page uses an interactive catalogue component.
 */
export default defineConfig({
  output: "server",
  devToolbar: { enabled: false },
  adapter: node({ mode: "standalone" }),
  server: { port: 4321, host: true, allowedHosts: [".app.localhost", "localhost"] },
  env: {
    // All runtime (read from process.env when the server starts), never inlined into the build.
    schema: {
      ULAMS_TENANT_HOSTS: envField.string({ context: "server", access: "secret", optional: true }),
      ULAMS_ADMIN_URL: envField.string({ context: "server", access: "secret", optional: true }),
      ULAMS_DEFAULT_TENANT: envField.string({ context: "server", access: "secret", optional: true }),
      ULAMS_PLATFORM_HOSTS: envField.string({ context: "server", access: "secret", optional: true }),
      ULAMS_DEMO_TENANTS: envField.string({ context: "server", access: "secret", optional: true }),
      ULAMS_CACHE_TTL: envField.number({ context: "server", access: "secret", optional: true }),
      ULAMS_WARM_TENANTS: envField.string({ context: "server", access: "secret", optional: true }),
      ULAMS_COOKIE_SECURE: envField.string({ context: "server", access: "secret", optional: true }),
      ULAMS_COOKIE_FALLBACK_PREFIX: envField.string({ context: "server", access: "secret", optional: true }),
      ULAMS_LANDING_STATUS: envField.string({ context: "server", access: "secret", optional: true }),
      DEMO_STUDENT_EMAIL: envField.string({ context: "server", access: "secret", optional: true }),
      DEMO_STUDENT_PASSWORD: envField.string({ context: "server", access: "secret", optional: true }),
    },
  },
  // Bundle the catalogue's server-side libraries: the hoisted copies at the repo root are
  // other (older) versions used by other workspaces.
  vite: { ssr: { noExternal: ["marked", "katex"] } },
  // Speculative loading: links are prefetched on hover / focus and prerendered in Chromium.
  prefetch: { prefetchAll: true, defaultStrategy: "hover" },
  build: { inlineStylesheets: "always" },
  // Origin checks for form posts and the BFF are done in src/middleware.ts against the Host
  // header (Astro's check compares with the listen address behind the Node server).
  security: { checkOrigin: false },
  image: {
    // Course images and avatars come from the tenant storage (MinIO behind Caddy).
    remotePatterns: [{ protocol: "http", hostname: "**.localhost" }, { protocol: "https", hostname: "**.ulams.app" }],
  },
  experimental: {
    clientPrerender: true,
    // Self-hosted fonts (no Google request at runtime). Fallback faces with measured metrics live
    // in @ulams/ui/styles/fallbacks.css (Astro's generated ones were off for these fonts).
    fonts: [
      { provider: google, name: "Playfair Display", cssVariable: "--font-playfair", weights: [400, 500, 600], styles: ["normal", "italic"], subsets: latin, fallbacks: ["Playfair Display fallback", "serif"], optimizedFallbacks: false },
      { provider: google, name: "Plus Jakarta Sans", cssVariable: "--font-jakarta", weights: [400, 500, 600, 700], styles: ["normal"], subsets: latin, fallbacks: ["Plus Jakarta Sans fallback", "sans-serif"], optimizedFallbacks: false },
      { provider: google, name: "Inter Tight", cssVariable: "--font-inter-tight", weights: [500, 600, 700], styles: ["normal"], subsets: latin, fallbacks: ["Inter Tight fallback", "sans-serif"], optimizedFallbacks: false },
      { provider: google, name: "Space Grotesk", cssVariable: "--font-space-grotesk", weights: [500, 600, 700], styles: ["normal"], subsets: latin, fallbacks: ["Space Grotesk fallback", "sans-serif"], optimizedFallbacks: false },
      { provider: google, name: "Inter", cssVariable: "--font-inter", weights: [400, 500, 600], styles: ["normal"], subsets: latin, fallbacks: ["Inter fallback", "sans-serif"], optimizedFallbacks: false },
      { provider: google, name: "JetBrains Mono", cssVariable: "--font-jetbrains", weights: [400, 500, 700], styles: ["normal"], subsets: latin, fallbacks: ["JetBrains Mono fallback", "monospace"], optimizedFallbacks: false },
      // Course Builder studio headings and course content (Editorial Intelligence design)
      { provider: google, name: "Newsreader", cssVariable: "--font-newsreader", weights: [400, 500], styles: ["normal", "italic"], subsets: latin, fallbacks: ["Georgia", "serif"], optimizedFallbacks: false },
      { provider: google, name: "Comfortaa", cssVariable: "--font-comfortaa", weights: [500, 700], styles: ["normal"], subsets: latin, fallbacks: ["Comfortaa fallback", "sans-serif"], optimizedFallbacks: false },
      { provider: google, name: "Quicksand", cssVariable: "--font-quicksand", weights: [400, 500, 600, 700], styles: ["normal"], subsets: latin, fallbacks: ["Quicksand fallback", "sans-serif"], optimizedFallbacks: false },
    ],
  },
});
