# 0088. Separately licensed content packages under `demo-content/`; GPL apps stay GPL and are never linked into MIT code

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/interactive-demos.md` (M3, M4, M5)

## Context and problem statement

Two of the new demos are built from the owner's own apps:

- **Gravity** (`github.com/qunabu/Gravity`) is GPL-3.0 and has outside contributors (David Frankel,
  jin). It cannot be relicensed.
- **poland** (`github.com/qunabu/poland-october-2026`) is the owner's code. It has no `LICENSE` file,
  and its repository holds a copy of a third-party article. Its map file (`src/world.topo.json`) was
  copied from that article page.

`LICENSING.md` rule 1 forbids linking GPL code into the API or bundling it into the admin or the
front. H5P content is the precedent for GPL code that only runs as content in a frame.

## Considered options

1. **A top-level `demo-content/` tree of content packages.** Each package has its own `LICENSE` and
   `NOTICE`, is played only as an interactive package in a sandboxed frame, and is never imported by
   any workspace. GPL builds come from the upstream repository at a pinned commit and are verified by
   checksum.
2. Vendor the gravity sources into `front/` or `api/`. This breaks rule 1.
3. Keep the demo content outside the repository entirely. Then demo seeding is not reproducible from
   the repository.

## Decision

Option 1.

- **Layout.**
  - `demo-content/gravity/` holds `LICENSE` (the GPL-3.0 text), `NOTICE` (copyright holders, source
    repository and commit, asset credits), `manifest.json` (the `ulams-interactive.json` we add),
    `source.json` (release URL, commit SHA, sha256) and `README.md`. It holds no GPL source and no
    build output in git.
  - `demo-content/poland/` and `demo-content/ulam/*` hold the package sources (plain HTML, JS and
    JSON, no build step) under the licence the owner chooses for them (#148; default: MIT for code,
    CC BY 4.0 for text).
  - `demo-content/README.md` states the rule: nothing outside `demo-content/` may import from it.
    A lint check (`scripts/check-demo-content-boundary.mjs`) fails CI if any workspace or PHP file
    imports or requires a path under `demo-content/`. The seeder reads its files as data only.
- **Gravity build.** A bridge adapter is committed to the owner's repository, `qunabu/Gravity`.
  The adapter is GPL there, and the contributors' code stays untouched and GPL. That repository's
  release workflow builds `gravity-ulams-<version>.zip` with `--base=./` and attaches it to a GitHub
  release together with `LICENSE` and a `SOURCE.txt` that points to the tag. `source.json` pins the
  URL and sha256. The demo seeder downloads the zip, checks the hash, caches it in
  `api/database/seeds/Demo/assets/cache/` (git-ignored, as the H5P demo assets are) and uploads it
  through the normal interactive upload path. An overlay applied at build time was rejected: it would
  hide a GPL derivative inside our repository and make the corresponding source harder to name.
- **Distribution.** A tenant that plays the gravity package conveys GPL object code to learners'
  browsers. The corresponding source is the public upstream tag named in the package's `NOTICE` and
  in the manifest's `source` field. The player shows the licence and source link in the lesson's
  "About this interactive" disclosure. The upstream tag must stay public as long as a release is
  offered (as in `LICENSING.md` rule 3).
- **Third-party material.** The article copy, its `_files` folder, the screen recording and the
  research dumps in the poland repository are never copied. `world.topo.json` is regenerated from
  `world-atlas@2.0.2/countries-50m.json`. That file is ISC and built from Natural Earth, which is
  public domain. The `NOTICE` names both. Gravity's music track and Moon texture have no stated
  licence and are left out of the content build. Earth keeps its Solar System Scope CC BY 4.0
  texture, with attribution.
- **`LICENSING.md`.** A new row: `demo-content/*`, licence per package, played only as sandboxed
  content.

## Consequences

- Good: the MIT/Apache code base stays free of GPL code, and the GPL obligations attach to a clearly
  named upstream source.
- Good: a self-hoster can delete `demo-content/` with no effect on the product.
- Bad: gravity demo seeding needs network access once, or a manually placed zip. The seeder prints
  the URL and the expected hash when the download fails.
- Bad: two repositories must move together. `source.json` is the only coupling, and it is bumped by
  a PR.
