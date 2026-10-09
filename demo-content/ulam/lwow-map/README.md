# lwow-map: a journey on a map

Seven stops: `lwow`, `princeton`, `harvard`, `madison`, `los-alamos`, `boulder`, `santa-fe`. The map (the shared
engine `demo-content/shared/atlas.js`, Natural Earth countries) flies from stop to stop, the legs are great circles,
the travelled ones solid and the rest dashed; the first stop has a schematic inset of central Lwów (three points, no
basemap). The map shows present-day country borders only and says so. Every stop is also a native button in a
list under the map, and each leg is described in text (the two places and the distance). Complete it with the
topic's `on_range_end` rule; the package never sends `complete`.

**Placeholder content.** `data/route.json` and `data/places.json` hold real place names and coordinates (Wikidata,
CC0) but only placeholder years, notes, addresses and inset positions (`"placeholder": true`); the package says so
on screen and `demo-content/tests/unit/ulam-lwow-map.test.mjs` pins it. They are filled in M9b from the cleared fact
sheet; no historical border is ever drawn.

- `logic.js`: great-circle distances and lines, the legs and their lengths, the view box around a stop, the leg
  sentence. Unit tests check known distances (a quarter of the equator, a degree of latitude), the leg lengths of
  the route, that every point of a great-circle line is on the shortest path, and the data.
- `data/world.topo.json` is a copy of `poland/data/world.topo.json` (`sync-bridge` copies it, the lint checks it).
- Reduced motion: the map jumps to the stop instead of flying. No storage, no network.

```bash
node demo-content/scripts/pack.mjs demo-content/ulam/lwow-map
node demo-content/scripts/posters.mjs demo-content/ulam/lwow-map
yarn workspace @ulams/demo-content test:e2e ulam-lwow-map
```
