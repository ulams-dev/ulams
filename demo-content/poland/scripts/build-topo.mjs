// Regenerates data/world.topo.json from Natural Earth through world-atlas (dev only, output committed):
//   node scripts/build-topo.mjs
// Source: world-atlas@2.0.2 countries-50m.json (ISC), which is Natural Earth (public domain).
// Keeps the ISO 3166-1 numeric id and the name, drops Antarctica, renames the object to "c" (the map
// code expects it) and quantises, so the file stays small. Nothing in the file comes from a third-party page.
import { readFileSync, writeFileSync } from "node:fs";
import { createRequire } from "node:module";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { feature, quantize } from "topojson-client";
import { topology } from "topojson-server";
import { presimplify, simplify } from "topojson-simplify";

const require = createRequire(import.meta.url);
const here = dirname(fileURLToPath(import.meta.url));
const world = JSON.parse(readFileSync(require.resolve("world-atlas/countries-50m.json"), "utf8"));
const ANTARCTICA = "010";
const EUROPE_BOOST = 8;
const MIN_WEIGHT = 2e-3;
const QUANTISATION = 20000;

// The map is a backdrop for Europe and the world: tiny islands add weight and no meaning. A polygon whose
// bounding box covers less than this (in square degrees) is dropped, unless it is the biggest part of a
// country the story names; polygons wholly south of 56 degrees S (sub-Antarctic islands) go too.
const MIN_AREA = 2;
const KEEP_SMALL = new Set(["470", "442", "196", "438", "020", "492", "674", "336", "703", "705", "191", "428", "233", "440", "352", "372"]);

const bboxOf = (ring) => ring.reduce((b, [x, y]) => [Math.min(b[0], x), Math.min(b[1], y), Math.max(b[2], x), Math.max(b[3], y)], [Infinity, Infinity, -Infinity, -Infinity]);
const areaOf = (polygon) => {
  const [x0, y0, x1, y1] = bboxOf(polygon[0]);
  return (x1 - x0) * (y1 - y0);
};

const countries = feature(world, world.objects.countries);
const kept = [];
for (const f of countries.features) {
  if (f.id === ANTARCTICA || f.properties?.name === "Antarctica") continue;
  const polygons = f.geometry.type === "Polygon" ? [f.geometry.coordinates] : f.geometry.coordinates;
  const largest = Math.max(...polygons.map(areaOf));
  const parts = polygons.filter((p) => bboxOf(p[0])[3] > -56 && (areaOf(p) >= MIN_AREA || (KEEP_SMALL.has(f.id) && areaOf(p) === largest)));
  if (!parts.length) continue;
  kept.push({
    type: "Feature",
    ...(f.id !== undefined ? { id: f.id } : {}),
    properties: { name: f.properties.name },
    geometry: parts.length === 1 ? { type: "Polygon", coordinates: parts[0] } : { type: "MultiPolygon", coordinates: parts },
  });
}

// Simplify in spherical weight (steradians), then quantise. The weight keeps Poland and its neighbours
// detailed enough for the zoomed views (a few kilometres across a screen) and thins the rest of the world.
const full = topology({ c: { type: "FeatureCollection", features: kept } });
const triangleArea = (a, b, c) => Math.abs((a[0] - c[0]) * (b[1] - a[1]) - (a[0] - b[0]) * (c[1] - a[1]));
const inEurope = (x, y) => x > -12 && x < 46 && y > 34 && y < 72;
const weight = ([a, b, c]) => triangleArea(a, b, c) * (inEurope(b[0], b[1]) ? EUROPE_BOOST : 1);
const topo = quantize(simplify(presimplify(full, weight), MIN_WEIGHT), QUANTISATION);
const out = join(here, "..", "data", "world.topo.json");
writeFileSync(out, JSON.stringify(topo));
const poland = topo.objects.c.geometries.find((g) => g.id === "616");
console.log(`wrote ${out}: ${topo.objects.c.geometries.length} countries, ${Math.round(JSON.stringify(topo).length / 1024)} KB, Poland ${poland ? "present" : "MISSING"}`);
