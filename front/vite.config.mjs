import { defineConfig } from "vite";

import react from "@vitejs/plugin-react";
import viteTsconfigPaths from "vite-tsconfig-paths";
import eslint from "vite-plugin-eslint";
import { sentryVitePlugin } from "@sentry/vite-plugin";
import { visualizer } from "rollup-plugin-visualizer";

// see all documentation here https://vitejs.dev/config/
export default defineConfig(({ mode }) => {
  const config = {
    // This changes the out put dir from dist to build change as your need
    // comment this out if that isn't relevant for your project
    optimizeDeps: {
      include: ["lodash-es", "swiper", "katex/dist/fonts/*"],
    },
    build: {
      sourcemap: true, // Source map generation must be turned on,
      rollupOptions: {
        external: ["katex/dist/fonts/*", "react-icons"],
        output: {
          manualChunks(id) {
            if (id.includes("node_modules")) {
              if (id.includes("react-icons")) return "react-icons";
              if (id.includes("swiper")) return "swiper";
              if (id.includes("katex")) return "katex";
              if (id.includes("@sentry")) return "sentry";
              if (id.includes("lodash-es")) return "lodash";
            }
          },
        },
      },
    },
    plugins: [
      react(),
      viteTsconfigPaths(),
      eslint(),
      process.env.SENTRY_AUTH_TOKEN
        ? sentryVitePlugin({
            authToken: process.env.SENTRY_AUTH_TOKEN,
            org: "ulams",
            project: "ulams-front",
            url: "https://ulams.sentry.io",
          })
        : undefined,
      // set ANALYZE=1 to open the bundle report after build
      visualizer({ open: !!process.env.ANALYZE }),
    ],
    // web workers (src/workers) are bundled separately and need the same @/… and @ulams/… aliases
    worker: {
      plugins: () => [viteTsconfigPaths()],
    },
    server: {
      open: !process.env.CI && process.env.BROWSER !== "none",
      port: 3000,
      // Tenant demos are served as <slug>.app.localhost through Caddy (Host header passed on).
      // Extra production-like hosts can be added with VITE_DEV_ALLOWED_HOSTS=".example.test,foo.local".
      allowedHosts: [
        ".app.localhost",
        "localhost",
        ...(process.env.VITE_DEV_ALLOWED_HOSTS || "")
          .split(",")
          .map((h) => h.trim())
          .filter(Boolean),
      ],
      // Caddy (Docker) reaches the dev server via host.docker.internal, which Docker Desktop forwards
      // to the host's IPv4 loopback; "localhost" may bind to ::1 only and give 502 through Caddy.
      // Set VITE_DEV_HOST=0.0.0.0 if your Docker setup cannot reach the host loopback interface.
      host: process.env.VITE_DEV_HOST || "127.0.0.1",
    },
    define: {},
  };
  if (mode === "development") {
    config.define = {
      global: "globalThis",
    };
  }
  return config;
});
