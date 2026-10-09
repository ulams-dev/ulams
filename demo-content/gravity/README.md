# gravity: a guided solar system

A Three.js model of the solar system with a 44-step guided tour (gravity, orbits, Kepler, cosmic velocities,
the Moon and tides, Lagrange points, gravity assists, the galaxy, general relativity), packaged as an ulams
interactive. English and Polish.

This is the owner's own simulator ([`qunabu/Gravity`](https://github.com/qunabu/Gravity)), MIT-licensed
for ulams. See [`NOTICE`](NOTICE) for provenance, what was left out (two outside commits, the zh
translation, the music track, the Moon photograph) and the third-party material.

## What was changed for ulams

- Browser storage goes through `src/ui/storage.ts` (guarded: the package runs in an opaque origin).
- Fonts (Inter, Roboto Mono) are self-hosted; nothing is loaded from Google Fonts or a CDN.
- The tour has a public API (`steps()`, `goTo(id)`, `setLanguage()`, `embed(range)`, a `gravity:step`
  event) and `src/ulams/adapter.ts` connects it to the bridge. Without a parent window it runs as before.
- `chrome` from the lesson page: `none` keeps the scene and the controls a step needs (time speed, Lagrange
  shots, stopped-galaxy buttons); `minimal` adds Back and Next inside the step range.
- Reduced motion: the clock is slowed (`prefers-reduced-motion` standalone, `init.reducedMotion` embedded) and
  the camera does not auto-rotate. The manifest declares `reducedMotion: false`, so the lesson page shows
  the step poster instead and offers "play anyway".
- `?ulams-poster#<step>` renders a step without chrome (used to make the posters).

## Develop

```bash
yarn workspace @ulams/demo-gravity dev          # Vite on :5173; the tour runs on its own
yarn workspace @ulams/demo-gravity typecheck
yarn workspace @ulams/demo-gravity test         # manifest, steps, no network, storage, what is left out
yarn workspace @ulams/demo-gravity package      # dist/ + release/gravity-ulams-<version>.zip (Chromium for the posters)
```

The manifest (`ulams-interactive.json`) is generated from `STEPS` and `PL` in `src/ui/tour.ts`, so the
step ids, titles and texts cannot drift from the code. A step id is a stable name; courses refer to ids,
never to numbers.

## Controls inside the frame

The 3D view itself is pointer-only (drag to rotate). The time-speed slider, the Lagrange shot buttons and the
stopped-galaxy buttons are native controls and work with the keyboard; the lesson page provides the stepper and
the text version of every step.
