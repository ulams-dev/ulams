import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { test } from "node:test";
import { distanceKm, greatCircle, legText, legs, totalKm, viewBox } from "../../ulam/lwow-map/logic.js";

const read = (f) => JSON.parse(readFileSync(new URL(`../../ulam/lwow-map/${f}`, import.meta.url), "utf8"));
const route = read("data/route.json");
const near = (a, b, eps) => assert.ok(Math.abs(a - b) <= eps, `${a} is not within ${eps} of ${b}`);

test("the great-circle distance is right: a quarter of the equator, a degree of latitude, the same point", () => {
  near(distanceKm([0, 0], [90, 0]), (Math.PI / 2) * 6371, 1e-6);
  near(distanceKm([10, 50], [10, 51]), 111.19, 0.05);
  assert.equal(distanceKm([24, 49.8], [24, 49.8]), 0);
  near(distanceKm([-180, 0], [180, 0]), 0, 1e-6); // the same meridian
});

test("the legs of the journey have the lengths the coordinates give", () => {
  const km = Object.fromEntries(legs(route.stops).map((l) => [`${l.from.id}>${l.to.id}`, Math.round(l.km)]));
  assert.deepEqual(km, { "lwow>princeton": 7242, "princeton>harvard": 372, "harvard>madison": 1493, "madison>los-alamos": 1652, "los-alamos>boulder": 469, "boulder>santa-fe": 485 });
  near(totalKm(route.stops), 11712, 3);
  assert.equal(legText(route.stops, 1), "Lwów (today Lviv) to Princeton: about 7,240 km in a straight line over the globe.");
  assert.match(legText(route.stops, 0), /where the journey starts/);
});

test("a great-circle line starts and ends at the stops and stays on the shortest path", () => {
  const [a, b] = [route.stops[0].lonlat, route.stops[1].lonlat];
  const line = greatCircle(a, b, 20);
  assert.equal(line.length, 21);
  near(line[0][0], a[0], 1e-9); near(line[0][1], a[1], 1e-9);
  near(line[20][0], b[0], 1e-9); near(line[20][1], b[1], 1e-9);
  // every point is on the circle: the two partial distances add up to the whole
  for (const p of line) near(distanceKm(a, p) + distanceKm(p, b), distanceKm(a, b), 0.01);
  // the shortest way from Lwów to Princeton passes north of both (far above the 40th parallel)
  assert.ok(Math.max(...line.map((p) => p[1])) > 55);
  assert.deepEqual(greatCircle([5, 5], [5, 5]), [[5, 5], [5, 5]]);
});

test("the view box is centred on the stop and stays between the poles", () => {
  assert.deepEqual(viewBox([24, 49.8], 14, 7), [[10, 42.8], [38, 56.8]]);
  assert.deepEqual(viewBox([0, 79], 5, 5), [[-5, 74], [5, 80]]);
});

test("the shipped data is consistent: one step per stop, real coordinates, placeholders flagged, the inset has its three places", () => {
  const manifest = read("ulams-interactive.json");
  assert.deepEqual(manifest.steps.map((s) => s.id), route.stops.map((s) => s.id));
  assert.deepEqual(route.stops.map((s) => s.id), ["lwow", "princeton", "harvard", "madison", "los-alamos", "boulder", "santa-fe"]);
  for (const s of route.stops) { assert.ok(s.lonlat[0] >= -180 && s.lonlat[0] <= 180 && s.lonlat[1] >= -90 && s.lonlat[1] <= 90, s.id); assert.match(s.years, /^Placeholder/); }
  assert.equal(route.placeholder, true);
  assert.match(route.note, /not facts/);
  assert.match(route.caption, /no historical borders are drawn/);
  const places = read("data/places.json");
  assert.equal(places.placeholder, true);
  assert.deepEqual(places.inset.points.map((p) => p.id), ["university", "polytechnic", "cafe"]);
  for (const p of places.inset.points) { assert.ok(p.x > 0 && p.x < 1 && p.y > 0 && p.y < 1); assert.match(p.address, /^Placeholder/); }
  for (const s of manifest.steps) assert.match(s.text.en, /^Placeholder text/);
});
