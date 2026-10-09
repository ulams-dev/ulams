# demo-content

Interactive content packages for the demo academies (plan `docs/plans/interactive-demos.md`, ADR
[0088](../docs/decisions/0088-separately-licensed-content-packages.md)). Each folder is one package that the
Interactive topic type plays in an opaque sandbox on the tenant content origin, talking to the lesson page
through the [`ulams-ix` bridge](../front/interactive-bridge).

| Folder | What | Licence |
|---|---|---|
| [`gravity/`](gravity) | A guided 3D solar system (Three.js, 44 steps, EN and PL) | MIT, see its `NOTICE` |

**The rule.** Nothing outside `demo-content/` may import, require, include or depend on anything in it.
`yarn workspace @ulams/demo-content lint` fails when that happens. PHP seeders may read the files as data.
A self-hoster can delete this folder with no effect on the product.

## Licences

MIT for code, CC BY 4.0 for course text and data (`LICENSE-content` in packages that carry text). Third-party
material is named in each package's `NOTICE` and in `LICENSING.md`.

## Tools

| Command | What it does |
|---|---|
| `yarn workspace @ulams/demo-content lint` | the boundary check, every `ulams-interactive.json` against the schema, vendored bridge copies equal a fresh build |
| `yarn workspace @ulams/demo-content test` | unit tests of the tools |
| `yarn workspace @ulams/demo-content test:e2e` | Playwright on throwaway servers: each package loads in the sandbox, steps advance, no external request, keyboard, axe (needs Chromium: `npx playwright install chromium`) |
| `node demo-content/tests/try-lesson.mjs <tenant> <course> <topic> out.png` | opens a lesson on the running local stack, prints console problems and failed requests, saves a screenshot |
| `yarn workspace @ulams/demo-content sync-bridge` | copies the bridge bundle into every unbuilt package's `vendor/` |
| `yarn workspace @ulams/demo-gravity package` | builds `gravity/release/gravity-ulams-<version>.zip` |

## Try a package on the local stack

```bash
yarn workspace @ulams/demo-gravity package
ulams topics create-interactive --lesson <lesson-id> --title "Gravity" \
  --file demo-content/gravity/release/gravity-ulams-1.0.0.zip \
  --start-step what-is-gravity --end-step what-is-gravity --display background --json
```

## Test harness

`tests/harness/` starts two throwaway servers on free ports (never :4321): a content origin that sets the
same Content-Security-Policy as the API, and a lesson host page that plays the package in a
`SANDBOX_INTERACTIVE` frame with the real bridge host. Package specs live in `tests/e2e/`.
