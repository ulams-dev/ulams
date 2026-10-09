import { describe, it } from "node:test";
import assert from "node:assert/strict";
import {
  MAX_REFRESH_DELAY_MS,
  refreshDelayMs,
  tokenExpiry,
} from "../src/lib/sdk/utils/tokenRefresh.ts";

const b64url = (value: object) =>
  Buffer.from(JSON.stringify(value)).toString("base64").replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
const jwt = (claims: object) => `${b64url({ alg: "RS256", typ: "JWT" })}.${b64url(claims)}.signature`;

describe("tokenRefresh", () => {
  const now = Date.UTC(2026, 9, 9, 12, 0, 0);
  const inMinutes = (m: number) => Math.floor(now / 1000) + m * 60;

  it("reads the exp claim of a Passport token", () => {
    assert.equal(tokenExpiry(jwt({ exp: 1_800_000_000, sub: "1" })), 1_800_000_000);
    assert.equal(tokenExpiry("not-a-jwt"), undefined);
    assert.equal(tokenExpiry("a.%%%.c"), undefined);
    assert.equal(tokenExpiry(jwt({ sub: "1" })), undefined);
  });

  it("refreshes a 5-minute token a minute before it expires", () => {
    assert.equal(refreshDelayMs(jwt({ exp: inMinutes(5) }), now), 4 * 60 * 1000);
  });

  it("refreshes an almost expired token right away", () => {
    assert.equal(refreshDelayMs(jwt({ exp: inMinutes(0.5) }), now), 0);
  });

  it("sets no timer for long-lived, missing or opaque tokens", () => {
    assert.equal(refreshDelayMs(jwt({ exp: inMinutes(60 * 24 * 30) }), now), undefined);
    assert.ok((refreshDelayMs(jwt({ exp: inMinutes(61) }), now) ?? 0) <= MAX_REFRESH_DELAY_MS);
    assert.equal(refreshDelayMs(null, now), undefined);
    assert.equal(refreshDelayMs("opaque", now), undefined);
  });
});
