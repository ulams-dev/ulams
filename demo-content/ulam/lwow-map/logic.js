// @ts-check
// The pure logic of the journey map: distances, great-circle legs and the map view around a stop.
// No DOM and no map engine, so the unit tests run it in Node.

const RAD = Math.PI / 180;
const EARTH_KM = 6371;

/** The great-circle distance in km between two [lon, lat] points (haversine). */
export function distanceKm(/** @type {number[]} */ a, /** @type {number[]} */ b) {
  const dLat = (b[1] - a[1]) * RAD, dLon = (b[0] - a[0]) * RAD;
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(a[1] * RAD) * Math.cos(b[1] * RAD) * Math.sin(dLon / 2) ** 2;
  return 2 * EARTH_KM * Math.asin(Math.min(1, Math.sqrt(h)));
}

/** Points along the great circle from a to b, as [lon, lat], endpoints included. */
export function greatCircle(/** @type {number[]} */ a, /** @type {number[]} */ b, n = 48) {
  const f1 = a[1] * RAD, l1 = a[0] * RAD, f2 = b[1] * RAD, l2 = b[0] * RAD;
  const d = distanceKm(a, b) / EARTH_KM;
  if (d < 1e-9) return [a, b];
  const out = [];
  for (let i = 0; i <= n; i++) {
    const t = i / n, A = Math.sin((1 - t) * d) / Math.sin(d), B = Math.sin(t * d) / Math.sin(d);
    const x = A * Math.cos(f1) * Math.cos(l1) + B * Math.cos(f2) * Math.cos(l2);
    const y = A * Math.cos(f1) * Math.sin(l1) + B * Math.cos(f2) * Math.sin(l2);
    const z = A * Math.sin(f1) + B * Math.sin(f2);
    out.push([Math.atan2(y, x) / RAD, Math.atan2(z, Math.sqrt(x * x + y * y)) / RAD]);
  }
  return out;
}

/**
 * @typedef {{ id: string, name: string, lonlat: number[], years?: string }} Stop
 * The legs between consecutive stops with their length in km.
 */
export function legs(/** @type {Stop[]} */ stops) {
  return stops.slice(1).map((to, i) => ({ from: stops[i], to, km: distanceKm(stops[i].lonlat, to.lonlat) }));
}

export const totalKm = (/** @type {Stop[]} */ stops) => legs(stops).reduce((n, l) => n + l.km, 0);

/** The box [[west, south], [east, north]] to show around a stop. */
export function viewBox(/** @type {number[]} */ lonlat, /** @type {number} */ halfLon, /** @type {number} */ halfLat) {
  return [[lonlat[0] - halfLon, Math.max(-80, lonlat[1] - halfLat)], [lonlat[0] + halfLon, Math.min(80, lonlat[1] + halfLat)]];
}

/** The sentence about the leg that ends at stop i (none for the first stop). */
export function legText(/** @type {Stop[]} */ stops, /** @type {number} */ i) {
  if (i <= 0 || i >= stops.length) return `${stops[0].name}: where the journey starts.`;
  const km = Math.round(distanceKm(stops[i - 1].lonlat, stops[i].lonlat) / 10) * 10;
  return `${stops[i - 1].name} to ${stops[i].name}: about ${km.toLocaleString("en")} km in a straight line over the globe.`;
}
