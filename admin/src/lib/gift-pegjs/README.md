# gift-pegjs (vendored)

Parser for the Moodle GIFT quiz format (PEG.js).

- Upstream: https://github.com/EscolaLMS/GIFT-grammar-PEG.js (npm `@escolalms/gift-pegjs`), fork of fuhrmanator/GIFT-grammar-PEG.js
- Version: 0.2.8, commit `b6808df6a2c13f28ebd5c561a8548237649f2e5a`
- Files: `GIFT.pegjs` (grammar source), `index.js` (= upstream `pegjs-gift.js`, the generated
  CommonJS parser, byte-identical), `index.d.ts` (types).
- Import as `@lms/gift-pegjs`; alias in `admin/config/config.ts` + `admin/tsconfig.json`.
- No runtime deps. Regenerate after editing the grammar:
  `npx pegjs@0.10.0 -o index.js GIFT.pegjs` (upstream additionally minified it).
- License: MIT (`LICENSE`).
