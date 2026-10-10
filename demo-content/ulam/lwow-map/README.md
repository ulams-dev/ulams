# lwow-map: a journey on a map

Seven stops: `lwow`, `princeton`, `harvard`, `madison`, `los-alamos`, `boulder`, `santa-fe`. The map (the shared
engine `demo-content/shared/atlas.js`, Natural Earth countries) flies from stop to stop, the legs are great circles,
the travelled ones solid and the rest dashed; the first stop has a schematic inset of central Lwów (three points, no
basemap). The map shows present-day country borders only and says so. Every stop is also a native button in a
list under the map, and each leg is described in text (the two places and the distance). Complete it with the
topic's `on_range_end` rule; the package never sends `complete`.

**Content.** `data/route.json` holds the stops and their years, `data/places.json` the schematic inset (the university,
the Polytechnic and the café building at 27 Shevchenko Avenue, positioned from their Wikidata coordinates, CC0, and
spread out so the labels fit), and the notes of each stop are the step texts of `ulams-interactive.json`. Every note
states only facts of the fact sheet (`demo-content/ulam/facts.json`, ids 1.x) and names its sources; where reference works
differ the note says so ("1940, some reference works give 1941"). No historical border is ever drawn.

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
