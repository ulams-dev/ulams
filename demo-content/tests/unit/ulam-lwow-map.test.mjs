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

test("the shipped data is the sourced content: one step per stop, real coordinates and years, the inset has its three places, no placeholder", () => {
  const manifest = read("ulams-interactive.json");
  const facts = read("../facts.json");
  assert.deepEqual(manifest.steps.map((s) => s.id), route.stops.map((s) => s.id));
  assert.deepEqual(route.stops.map((s) => s.id), ["lwow", "princeton", "harvard", "madison", "los-alamos", "boulder", "santa-fe"]);
  for (const s of route.stops) assert.ok(s.lonlat[0] >= -180 && s.lonlat[0] <= 180 && s.lonlat[1] >= -90 && s.lonlat[1] <= 90, s.id);
  assert.deepEqual(route.stops.map((s) => s.years), ["1909–1935", "1936", "1936–1940", "1940–1943", "1944–1965", "from 1965", "died 1984"]);
  assert.equal(route.placeholder, undefined);
  assert.match(route.caption, /no historical borders are drawn/);
  const places = read("data/places.json");
  assert.equal(places.placeholder, undefined);
  assert.deepEqual(places.inset.points.map((p) => p.id), ["university", "polytechnic", "cafe"]);
  for (const p of places.inset.points) { assert.ok(p.x > 0 && p.x < 1 && p.y > 0 && p.y < 1); }
  assert.equal(places.inset.points.find((p) => p.id === "cafe").address, "27 Shevchenko Avenue");
  assert.doesNotMatch(JSON.stringify([manifest, route, places]), /placeholder/i);
  // the inset keeps the true order of the three places: the Polytechnic is west of the university, the café east, the university north
  const at = Object.fromEntries(places.inset.points.map((p) => [p.id, p]));
  assert.ok(at.polytechnic.x < at.university.x && at.university.x < at.cafe.x);
  assert.ok(at.university.y < at.polytechnic.y && at.university.y < at.cafe.y);
  // every stop's text states the facts of the fact sheet and names its sources
  const text = Object.fromEntries(manifest.steps.map((s) => [s.id, s.text.en]));
  for (const t of Object.values(text)) assert.match(t, /Sources?: /);
  assert.match(text.lwow, /13 April 1909/); assert.match(text.lwow, /1927/); assert.match(text.lwow, /1933.*Kuratowski/); assert.match(text.lwow, /27 Shevchenko Avenue/);
  assert.match(text.princeton, /von Neumann/); assert.match(text.princeton, /January 1936/);
  assert.match(text.harvard, /Society of Fellows/); assert.match(text.harvard, /August 1939/);
  assert.match(text.madison, /1940 \(some reference works give 1941\)/); assert.match(text.madison, /19 August 1941/); assert.match(text.madison, /1943/);
  assert.match(text["los-alamos"], /4 February 1944/); assert.match(text["los-alamos"], /1965/); assert.match(text["los-alamos"], /1945 and 1946/);
  assert.match(text.boulder, /1965/); assert.match(text.boulder, /1974 to 1984/);
  assert.match(text["santa-fe"], /13 May 1984/);
  // nothing the sheet rejects: no ship, no port, no day of the week, no 3 April
  assert.doesNotMatch(Object.values(text).join(" "), /Piłsudski|Batory|Danzig|(^|[^0-9])3 April|studied under Banach/);
  for (const id of ["1.1", "1.3", "1.4", "1.5", "1.6", "1.6a", "1.7", "1.8", "1.8b", "1.9", "1.10", "1.12"]) assert.ok(facts[id] && facts[id].status !== "not-used", id);
});
