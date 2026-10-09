import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { test } from "node:test";
import { fileURLToPath } from "node:url";
import { findProblems } from "../../poland/scripts/check.mjs";

const root = join(dirname(fileURLToPath(import.meta.url)), "..", "..", "poland");
const read = (p) => readFileSync(join(root, p), "utf8");
const withEdit = (file, edit) => (p) => (p === file ? edit(read(p)) : read(p));

test("the package passes its own lint", () => {
  assert.deepEqual(findProblems(), []);
});

test("every figure resolves to a primary source: no press, aggregator or encyclopedia link, no essay", () => {
  const sources = JSON.parse(read("data/sources.json"));
  const hosts = new Set(Object.values(sources).flatMap((e) => e.s.map(([, u]) => new URL(u).hostname)));
  for (const bad of ["notesfrompoland.com", "en.wikipedia.org", "news.ycombinator.com", "tomwojcik.com", "www.ft.com", "www.bankier.pl"]) assert.equal(hosts.has(bad), false, bad);
  const statuses = new Set(Object.values(sources).map((e) => e.st));
  assert.equal(statuses.has("reported_in_uploaded_document"), false);
  assert.equal(statuses.has("essay"), false);
});

test("the lint catches third-party framing, a weak source, a missing source id and an oversized map", () => {
  const steps = (fn) => withEdit("data/steps.json", (t) => JSON.stringify(fn(JSON.parse(t))));
  assert.ok(findProblems(root, withEdit("data/steps.json", (t) => t.replace("Poland, measured", "A reply to the essay"))).some((p) => /essay/i.test(p)));
  assert.ok(findProblems(root, withEdit("data/sources.json", (t) => t.replace("https://www.nato.int", "https://en.wikipedia.org/wiki/NATO?x=https://www.nato.int"))).some((p) => /wikipedia/.test(p)));
  assert.ok(findProblems(root, steps((d) => { d.steps[2].src.push("PL-999"); return d; })).some((p) => /PL-999/.test(p)));
  assert.ok(findProblems(root, steps((d) => { d.steps.find((s) => s.src?.length).src = []; return d; })).some((p) => /cites no source/.test(p)));
  assert.ok(findProblems(root, withEdit("data/world.topo.json", (t) => t.slice(0, -1) + ',"pad":"' + "x".repeat(420 * 1024) + '"}')).some((p) => /KB/.test(p)));
  assert.ok(findProblems(root, withEdit("index.html", (t) => t.replace("</head>", '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow"></head>'))).length > 0);
});

test("both languages are complete: every step and chapter has English and Polish text", () => {
  const data = JSON.parse(read("data/steps.json"));
  for (const st of data.steps) {
    const fields = st.type === "chapter" ? ["title", "lede"] : st.type === "hero" ? [] : ["kicker", "title", "body"];
    for (const f of fields) for (const l of ["en", "pl"]) assert.ok(st[f]?.[l]?.length > 3, `${st.id}.${f}.${l}`);
  }
  for (const f of ["eyebrow", "title", "sub", "lede", "byline"]) for (const l of ["en", "pl"]) assert.ok(data.hero[f][l].length > 3, `hero.${f}.${l}`);
  for (const [k, v] of Object.entries(data.ui)) for (const l of ["en", "pl"]) assert.ok(v[l] != null, `ui.${k}.${l}`);
});
