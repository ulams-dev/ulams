// Offline tests for the Cloudflare side of IMG-6b (ADR 0193):
//   node --experimental-strip-types --no-warnings --test deploy/mydevil/test/image-ops.test.mjs
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { test } from "node:test";
import { allowedCloudflareOptions, imageUrl, IMAGE_PRESETS } from "../../../front/sdk/src/images.ts";
import worker from "../cloudflare/storage.worker.js";
import { MAX_EXPRESSION_LENGTH, allowedPrefixes, rule, ruleExpression } from "../cloudflare/image-waf-rule.mjs";

// ---- storage Worker: image objects are cached for an hour, everything else for five minutes, always with an ETag
function bucketWith(objects) {
  return {
    async get(key) {
      const o = objects[key];
      if (!o) return null;
      return { httpEtag: `"${key}-etag"`, body: o.body ?? "x", size: 1, writeHttpMetadata: (h) => o.type && h.set("content-type", o.type) };
    },
    async head(key) {
      return this.get(key);
    },
  };
}
const env = { B_ULAMS_STAGING_COFFEE: bucketWith({ "a.png": { type: "image/png" }, "b.webp": { type: "image/webp" }, "c.pdf": { type: "application/pdf" }, "d.bin": {} }) };
const get = (path, method = "GET") => worker.fetch(new Request(`https://storage.example/coffee/${path}`, { method }), env);

test("storage Worker: images are cached for one hour with an ETag", async () => {
  for (const key of ["a.png", "b.webp"]) {
    const r = await get(key);
    assert.equal(r.status, 200);
    assert.equal(r.headers.get("cache-control"), "public, max-age=3600");
    assert.equal(r.headers.get("etag"), `"${key}-etag"`);
  }
});

test("storage Worker: other files keep five minutes", async () => {
  for (const key of ["c.pdf", "d.bin"]) {
    const r = await get(key);
    assert.equal(r.headers.get("cache-control"), "public, max-age=300");
    assert.ok(r.headers.get("etag"));
  }
});

test("storage Worker: HEAD of an image has the same cache header; a missing key is 404", async () => {
  assert.equal((await get("a.png", "HEAD")).headers.get("cache-control"), "public, max-age=3600");
  assert.equal((await get("nope.png")).status, 404);
});

// ---- WAF rule: allows exactly what the builder emits
const SOURCE = "https://staging-storage.ulams.app/coffee/cover.png";
const config = { service: "cloudflare", transformOrigin: "", allowedSources: ["https://staging-storage.ulams.app"] };

test("WAF rule lists exactly allowedCloudflareOptions()", () => {
  const options = allowedCloudflareOptions();
  assert.ok(options.length > 0);
  assert.deepEqual(allowedPrefixes(), options.map((o) => `/cdn-cgi/image/${o}/`));
  const quoted = [...rule().expression.matchAll(/starts_with\(http\.request\.uri\.path, "([^"]+)"\)/g)].map((m) => m[1]);
  assert.deepEqual(quoted, ["/cdn-cgi/image/", ...allowedPrefixes()]);
});

test("WAF rule: block action, within the expression limit, no regex (not available on the Free plan)", () => {
  const r = rule();
  assert.equal(r.action, "block");
  assert.equal(r.enabled, true);
  assert.ok(r.expression.length <= MAX_EXPRESSION_LENGTH, `${r.expression.length} characters`);
  assert.doesNotMatch(r.expression, /matches|~/);
});

test("WAF rule allows every URL the builder emits for every preset width", () => {
  const prefixes = allowedPrefixes();
  let checked = 0;
  for (const [name, preset] of Object.entries(IMAGE_PRESETS)) {
    for (const width of preset.widths) {
      const url = imageUrl({ url: SOURCE }, width, name, config);
      const path = new URL(url, "https://page.example").pathname;
      if (!path.startsWith("/cdn-cgi/image/")) continue;
      assert.ok(prefixes.some((p) => path.startsWith(p)), `${url} would be blocked`);
      checked++;
    }
  }
  assert.ok(checked >= allowedCloudflareOptions().length, `only ${checked} URLs were Cloudflare URLs`);
});

test("WAF rule blocks an option string the builder never emits", () => {
  const prefixes = allowedPrefixes();
  for (const path of ["/cdn-cgi/image/width=777,quality=78,format=auto,fit=scale-down,onerror=redirect/x.png", "/cdn-cgi/image/width=240/x.png", "/cdn-cgi/image/width=240,quality=78,format=auto,fit=scale-down/x.png"]) {
    assert.ok(!prefixes.some((p) => path.startsWith(p)), `${path} would be allowed`);
  }
  assert.match(ruleExpression(), /^\(starts_with\(http\.request\.uri\.path, "\/cdn-cgi\/image\/"\) and not \(/);
});

test("the smoke script uses an option string the WAF rule allows", () => {
  const script = readFileSync(new URL("../bin/check-images.sh", import.meta.url), "utf8");
  const options = script.match(/^OPTIONS="([^"]+)"/m)?.[1];
  assert.ok(options, "OPTIONS line");
  assert.ok(allowedCloudflareOptions().includes(options), `${options} is not an option string the builder emits`);
});
