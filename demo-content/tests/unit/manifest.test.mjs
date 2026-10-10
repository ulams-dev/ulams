import assert from "node:assert/strict";
import { test } from "node:test";
import { validateManifest } from "../../scripts/lib/manifest.mjs";

const good = () => ({
  id: "sample",
  title: { en: "Sample" },
  version: "1.0.0",
  licence: "MIT",
  locales: ["en"],
  defaultLocale: "en",
  bridge: 1,
  steps: [{ id: "a", title: { en: "A" }, text: { en: "Text of A." }, poster: "p/a.webp" }],
});

test("a valid manifest has no problems", () => {
  assert.deepEqual(validateManifest(good(), new Set(["index.html", "p/a.webp"])), []);
});

test("schema violations are reported", () => {
  const m = good();
  m.id = "Bad Id";
  m.bridge = 2;
  assert.ok(validateManifest(m).length >= 2);
});

test("every locale needs a title and a text for every step", () => {
  const m = good();
  m.locales = ["en", "pl"];
  m.title.pl = "Przykład";
  const problems = validateManifest(m);
  assert.ok(problems.some((p) => p.includes('"pl"')));
});

test("the licence must be on the allow-list and the files must exist and be allowed", () => {
  const m = good();
  m.licence = "WTFPL";
  assert.ok(validateManifest(m).some((p) => p.includes("licence")));
  const files = new Set(["index.html", "p/a.webp", "run.php", ".env"]);
  const problems = validateManifest(good(), files);
  assert.ok(problems.some((p) => p.includes("run.php")));
  assert.ok(problems.some((p) => p.includes(".env")));
  assert.ok(validateManifest(good(), new Set(["index.html"])).some((p) => p.includes("poster")));
});

test("a showcase must name real steps and, when it has one, a poster in the package", () => {
  const m = { ...good(), showcase: { steps: ["a"], poster: "p/showcase.webp" } };
  assert.deepEqual(validateManifest(m, new Set(["index.html", "p/a.webp", "p/showcase.webp"])), []);
  assert.ok(validateManifest(m, new Set(["index.html", "p/a.webp"])).some((p) => p.includes("showcase.poster")));
  assert.ok(validateManifest({ ...m, showcase: { steps: ["nope"] } }).some((p) => p.includes('showcase.steps[0]: "nope"')));
  assert.ok(validateManifest({ ...m, showcase: { steps: [] } }).length > 0);
  assert.ok(validateManifest({ ...m, showcase: { steps: ["a"], loop: true } }).length > 0);
});
