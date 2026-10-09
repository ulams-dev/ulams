import assert from "node:assert/strict";
import { createRequire } from "node:module";
import { test } from "node:test";

const Atlas = createRequire(import.meta.url)("../../shared/atlas.js");
const near = (a, b, eps = 1e-6) => assert.ok(Math.abs(a - b) < eps, `${a} is not near ${b}`);

test("the projection round-trips and puts the equator and the prime meridian at the origin", () => {
  const [x, y] = Atlas.P([0, 0]);
  near(x, 0);
  near(y, 0);
  for (const ll of [[21.01, 52.23], [-122.42, 37.77], [139.69, 35.69], [-70, -33]]) {
    const back = Atlas.Pinv(Atlas.P(ll));
    near(back[0], ll[0], 1e-9);
    near(back[1], ll[1], 1e-9);
  }
});

test("latitude is clamped at 85 degrees so the poles do not blow up", () => {
  assert.ok(Number.isFinite(Atlas.P([0, 90])[1]));
  near(Atlas.P([0, 89])[1], Atlas.P([0, 85])[1]);
});

test("arc and great-circle lines run from the first point to the second", () => {
  const a = [21.01, 52.23], b = [-0.13, 51.5];
  const arc = Atlas.arc(a, b, 0.2), gc = Atlas.gc(a, b);
  for (const line of [arc, gc]) {
    near(line[0][0], Atlas.P(a)[0], 1e-6);
    near(line[0][1], Atlas.P(a)[1], 1e-6);
    near(line.at(-1)[0], Atlas.P(b)[0], 1e-6);
    near(line.at(-1)[1], Atlas.P(b)[1], 1e-6);
  }
  assert.ok(arc.length > 10 && gc.length > 10);
});

test("the smooth zoom starts and ends where it is told to and takes longer for a bigger move", () => {
  const near1 = Atlas.zoomInterp([0, 0, 100], [20, 0, 100]);
  const far = Atlas.zoomInterp([0, 0, 100], [2000, 0, 100]);
  const start = near1(0), end = near1(1);
  near(start[0], 0);
  near(end[0], 20, 1e-6);
  near(end[2], 100, 1e-6);
  assert.ok(far.duration > near1.duration);
});
