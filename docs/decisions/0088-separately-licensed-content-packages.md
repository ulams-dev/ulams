# 0088. Content packages under `demo-content/`, played only as sandboxed content

- Status: Proposed (amended 2026-10-09)
- Date: 2026-10-09
- Plan: `docs/plans/interactive-demos.md` (M3, M4, M5)

## Amendment, 2026-10-09

The product owner decided (#147, #148, #149, chat): `qunabu/Gravity` and the poland repository are **his own
code**, and he may use them under any licence, including MIT inside ulams. The first version of this record
treated gravity as GPL-3.0 code with outside contributors that had to be kept apart and fetched by
checksum. That part is withdrawn:

- Gravity is imported into this repository (`demo-content/gravity/`), under MIT, with the owner's copyright.
  There is no GPL separation, no release zip in the upstream repository, no `source.json` and no checksum
  download. The adapter PR in `qunabu/Gravity` is optional; if it is ever opened it is a pull request on that
  repository, never a push to its main.
- Three outside commits of the upstream history are left out: bc9d770 and 9db0edc (David Frankel: Docker
  files and deletions) and 4adaa1b (jin: the Chinese tour translation). No third-party copyright is
  involved, and the package is English and Polish.
- Poland: the saved copy of the third-party article, its `_files` folder, the copied `world.topo.json` and the
  mp4 are not used. The map is regenerated from Natural Earth (via `world-atlas`, ISC), the reply framing is
  dropped, only figures with primary sources stay, and the package is EN and PL.
- Licences: MIT for code, CC BY 4.0 for course text and data (#148).
- Poland was built in M4: the story is neutral public statistics, every figure links to the institution that
  published it, and a lint (`demo-content/poland/scripts/check.mjs`) fails on third-party framing, a weak
  source, an unresolved source id or an oversized map. Figures whose only source was the article, a press
  report or an encyclopedia were removed or replaced by the publisher's number.

The rest of the decision stands: packages live in a separate tree, are played only in a sandboxed frame, and
nothing outside the tree may import them.

## Context and problem statement

Two of the new demos are built from the owner's own apps:

- **Gravity** (`github.com/qunabu/Gravity`) is a Three.js simulator. It was published under GPL-3.0 and has
  three outside commits (see the amendment).
- **poland** (`github.com/qunabu/poland-october-2026`) is plain inline JavaScript. It has no `LICENSE` file,
  and its repository holds a copy of a third-party article. Its map file (`src/world.topo.json`) was
  copied from that article page.

`LICENSING.md` rule 1 forbids linking GPL code into the API or bundling it into the admin or the front.
H5P content is the precedent for code that only runs as content in a frame. Even with the code now MIT, the
content packages should stay separate: they are large, third-party-data-heavy, and a self-hoster can delete
them.

## Considered options

1. **A top-level `demo-content/` tree of content packages.** Each package has its own `LICENSE` and
   `NOTICE`, is played only as an interactive package in a sandboxed frame, and is never imported by
   any other workspace.
2. Vendor the gravity sources into `front/` or `api/`. This mixes a large 3D app and its assets into the
   product code.
3. Keep the demo content outside the repository entirely. Then demo seeding is not reproducible from
   the repository.

## Decision

Option 1.

- **Layout.**
  - `demo-content/gravity/` is a yarn workspace (`@ulams/demo-gravity`, private): the simulator source, its
    Vite build, `LICENSE` (MIT), `NOTICE` (origin, what was left out, asset credits) and a README. The
    package zip is built by `yarn workspace @ulams/demo-gravity package` into `release/` (git-ignored).
  - `demo-content/poland/` and `demo-content/ulam/*` hold the package sources (plain HTML, JS and JSON,
    no build step) under MIT, with the text and data under CC BY 4.0 (`LICENSE-content`).
  - `demo-content/README.md` states the rule: nothing outside `demo-content/` may import from it.
    A lint check (`demo-content/scripts/check-demo-content-boundary.mjs`, part of `yarn lint`) fails CI if
    any file outside the tree imports or requires a path under it, or depends on one of its workspaces.
    PHP seeders read its files as data only.
  - `demo-content/scripts/check-manifests.mjs` validates every `ulams-interactive.json` and checks that
    each vendored copy of the bridge equals a fresh build.
- **Third-party material.** The article copy, its `_files` folder, the screen recording and the research
  dumps in the poland repository are never copied. `world.topo.json` is regenerated from
  `world-atlas@2.0.2/countries-50m.json` (ISC, built from Natural Earth, public domain). Gravity's music
  track and Moon photograph have no stated licence and are left out; the Moon uses a procedural texture.
  Earth keeps its Solar System Scope CC BY 4.0 texture, with attribution in the manifest and `NOTICE`.
  Fonts are SIL OFL 1.1 and ship with their licence text.
- **`LICENSING.md`.** A row: `demo-content/*`, MIT for code and CC BY 4.0 for course text and data, with
  the exceptions named in each package's `NOTICE`; played only as sandboxed content.

## Consequences

- Good: the product code base stays free of the demo apps and their assets, and the boundary is enforced.
- Good: one repository holds everything; seeding the gravity demo needs no network access.
- Good: a self-hoster can delete `demo-content/` with no effect on the product.
- Bad: building the gravity package needs Chromium (for the step posters) and takes about a minute; the
  seeder uses a cached zip when it exists.
- Bad: the repository grows by the simulator source (about 0.5 MB, plus a 450 KB Earth texture).
