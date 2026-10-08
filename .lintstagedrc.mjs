// Root lint-staged config (husky pre-commit). Each app keeps its own prettier/eslint version and
// config, so the app's own binaries are used (front pins prettier 2.4.1, admin 2.8.8). No yarn
// is required. Vendored libraries in */src/lib are skipped (they stay diffable against their
// upstream commit, see the README.md in each lib folder). api/ has no JS tasks.
import { existsSync } from 'node:fs';

const bin = (app, tool) =>
  existsSync(`${app}/node_modules/.bin/${tool}`)
    ? `${app}/node_modules/.bin/${tool}`
    : `node_modules/.bin/${tool}`;
const quote = (files) => files.map((f) => JSON.stringify(f)).join(' ');
const run = (app, tool, args) => (files) => {
  const own = files.filter((f) => !/\/src\/lib\//.test(f));
  return own.length ? [`${bin(app, tool)} ${args} ${quote(own)}`] : [];
};

export default {
  'front/**/*.{js,ts,tsx,json,md,html}': run('front', 'prettier', '--write'),
  'admin/**/*.{js,jsx,ts,tsx}': run('admin', 'eslint', '--ext .js,.jsx,.ts,.tsx'),
  'admin/**/*.{js,jsx,tsx,ts,less,md,json}': run('admin', 'prettier', '--write'),
};
