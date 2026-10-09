// Bundles the browser code of the embed pages (embed-client/*.ts, GPL like the
// rest of this service) into dist/embed/{player,editor}.js.
import { build } from 'esbuild';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

await build({
    entryPoints: {
        player: path.join(root, 'embed-client/player.ts'),
        editor: path.join(root, 'embed-client/editor.ts')
    },
    outdir: path.join(root, 'dist/embed'),
    bundle: true,
    format: 'iife',
    platform: 'browser',
    target: ['es2020'],
    minify: true,
    sourcemap: true,
    legalComments: 'linked',
    logLevel: 'warning'
});
