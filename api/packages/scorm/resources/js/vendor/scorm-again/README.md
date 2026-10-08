# scorm-again (vendored)

- Upstream: https://github.com/jcputney/scorm-again
- Version: 2.6.4 (`dist/scorm-again.min.js`, source map reference removed), the same version the
  front and admin apps use from npm
- Licence: MIT (see `LICENSE`)

Vendored so that SCORM players work without internet access (principle 8); it was loaded from
the jsDelivr CDN before. Served by the API at `/api/scorm/assets/scorm-again.min.js` for the legacy
player and published with the content-origin player to `scorm/_player/` on the SCORM disk.

To update: copy `dist/scorm-again.min.js` and `LICENSE` from the npm package, drop the
`sourceMappingURL` line, and update the version here.
