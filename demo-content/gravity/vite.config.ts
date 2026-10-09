import { defineConfig } from 'vite';

// Relative base: the build is served from any folder (the ulams content origin serves a package under
// interactive/<key>/v<n>/). `npm run dev` serves it at the root.
export default defineConfig({
  base: './',
  server: { port: 5173 },
  build: { target: 'es2022', assetsInlineLimit: 0 },
});
