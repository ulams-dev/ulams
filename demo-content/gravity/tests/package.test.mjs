import assert from "node:assert/strict";
import { readFileSync, readdirSync, statSync, existsSync } from "node:fs";
import { dirname, join } from "node:path";
import { test } from "node:test";
import { fileURLToPath } from "node:url";
import { validateManifest } from "../../scripts/lib/manifest.mjs";
import { buildManifest } from "../scripts/manifest.mjs";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const sources = (dir = join(root, "src"), out = []) => {
  for (const name of readdirSync(dir)) {
    const path = join(dir, name);
    if (statSync(path).isDirectory()) sources(path, out);
    else out.push(path);
  }
  return out;
};

test("the manifest is generated from the tour and is valid (44 steps, English and Polish)", async () => {
  const manifest = await buildManifest();
  assert.deepEqual(validateManifest(manifest), []);
  assert.equal(manifest.steps.length, 44);
  assert.equal(new Set(manifest.steps.map((s) => s.id)).size, 44);
  assert.ok(manifest.steps.some((s) => s.id === "stopped-galaxy"));
  assert.equal(manifest.licence, "MIT");
  assert.deepEqual(manifest.network, []);
  assert.deepEqual(manifest.locales, ["en", "pl"]);
  assert.deepEqual(manifest.requires, ["webgl"]);
  for (const s of manifest.steps) assert.equal(s.poster, `posters/${s.id}.webp`);
});

test("the text alternative of a step is the narration, in both languages, within the manifest limits", async () => {
  const { steps } = await buildManifest();
  for (const s of steps) {
    for (const l of ["en", "pl"]) {
      assert.ok(s.text[l].length > 40 && s.text[l].length <= 4000, `${s.id} ${l}`);
      assert.ok(s.title[l].length > 2 && s.title[l].length <= 255, `${s.id} ${l} title`);
    }
  }
});

test("the page loads nothing from the network: no Google Fonts, no CDN, no remote script or style", () => {
  const html = readFileSync(join(root, "index.html"), "utf8");
  assert.doesNotMatch(html, /googleapis|gstatic/);
  assert.doesNotMatch(html, /<(script|link|img|iframe)[^>]+(src|href)="https?:/);
  for (const file of sources().filter((f) => /\.(ts|css)$/.test(f))) {
    const text = readFileSync(file, "utf8");
    assert.doesNotMatch(text, /@import\s+url\(\s*['"]?https?:/, file);
    assert.doesNotMatch(text, /fetch\(\s*['"`]https?:/, file);
  }
});

test("browser storage goes through the guarded helper only", () => {
  for (const file of sources().filter((f) => f.endsWith(".ts") && !f.endsWith("storage.ts"))) {
    assert.doesNotMatch(readFileSync(file, "utf8"), /\b(localStorage|sessionStorage|indexedDB)\b/, file);
  }
});

test("what the owner did not clear is not in the package: no music, no Moon photograph, no zh translation", () => {
  assert.equal(existsSync(join(root, "src", "ui", "music.ts")), false);
  assert.equal(existsSync(join(root, "public", "Moon-TomBrown.webp")), false);
  assert.deepEqual(readdirSync(join(root, "public")), ["earth_daymap.jpg"]);
  for (const file of sources().filter((f) => f.endsWith(".ts"))) {
    const text = readFileSync(file, "utf8");
    assert.doesNotMatch(text, /\bZH\b|data-lang="zh"/, file);
    assert.doesNotMatch(text, /[一-鿿]/, `${file} contains Chinese text`);
    assert.doesNotMatch(text, /\.mp3|Moon-TomBrown/, file);
  }
});
