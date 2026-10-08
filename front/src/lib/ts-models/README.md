# ts-models (vendored)

Global `App.Models.*` TypeScript declarations generated from the Laravel models.

- Upstream: https://github.com/EscolaLMS/ts-models (npm `@escolalms/ts-models`)
- Version: 0.0.35, commit `35e9e415c11e90a5e053e4d2204dd996118f72d0`
- Copied: `models.d.ts` only (the upstream repo is also the Laravel generator app).
- Not imported: it is an ambient declaration file picked up by `include` in `front/tsconfig.json`
  (`src`) and `admin/tsconfig.json` (`../front/src/lib/ts-models/models.d.ts`). Single copy shared by both apps.
- Regenerate upstream with `php artisan typescript:generate`.
- License: see `LICENSE`.
