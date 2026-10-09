// The course text of the gravity and poland demo academies (api/database/seeds/Demo/content/<key>/modules/*.md, read by the
// PHP seeders) against the packages and the source lists in this folder: every step range exists and runs forward, every
// cited source exists, the seed copy of each source list equals the package's, and every layout uses a component of the
// learner catalogue.
import assert from "node:assert/strict";
import { readFileSync, readdirSync } from "node:fs";
import { dirname, join } from "node:path";
import { test } from "node:test";
import { fileURLToPath } from "node:url";
import { readSteps } from "../../gravity/scripts/steps.mjs";
import { giftQuestions, giftType, parseModule } from "../lib/course-text.mjs";

const demo = join(dirname(fileURLToPath(import.meta.url)), "..", "..");
const content = join(demo, "..", "api", "database", "seeds", "Demo", "content");
const manifest = JSON.parse(readFileSync(join(demo, "..", "api", "packages", "topic-type-layout", "resources", "learner-layout-manifest.json"), "utf8"));

const modulesOf = (dir) =>
  readdirSync(join(content, dir, "modules"))
    .filter((f) => f.endsWith(".md"))
    .sort()
    .map((file) => ({ file, ...parseModule(readFileSync(join(content, dir, "modules", file), "utf8")) }));

const COURSES = {
  gravity: { dir: "gravity", sources: join(demo, "gravity", "sources.json"), seedSources: join(content, "gravity", "sources.json") },
};

const stepIds = {
  gravity: (await readSteps()).map((s) => s.id),
};
const packageOf = (name) => (name === "gravity" ? "gravity" : "poland");

for (const [name, course] of Object.entries(COURSES)) {
  const modules = modulesOf(course.dir);
  const sources = JSON.parse(readFileSync(course.sources, "utf8"));

  test(`${name}: the seed copy of the source list equals the package's`, () => {
    assert.deepEqual(JSON.parse(readFileSync(course.seedSources, "utf8")), sources, `copy ${course.sources} to ${course.seedSources}`);
  });

  test(`${name}: every interactive topic is a forward range of steps of the package`, () => {
    const ids = stepIds[packageOf(name)];
    let interactive = 0;
    for (const { file, blocks } of modules)
      for (const b of blocks.filter((x) => x.kind === "interactive")) {
        interactive++;
        const from = ids.indexOf(b.attrs.start);
        const to = ids.indexOf(b.attrs.end ?? b.attrs.start);
        assert.ok(from >= 0 && to >= from, `${file}: ${b.attrs.title}: ${b.attrs.start}..${b.attrs.end} is not a range of the package`);
      }
    assert.ok(interactive >= 18, `${interactive} interactive topics`);
  });

  test(`${name}: every cited source exists`, () => {
    for (const { file, blocks } of modules)
      for (const b of blocks) {
        const cited = [...b.body.matchAll(/\{\{src:([^}]+)\}\}/g)].flatMap((m) => m[1].split(",").map((s) => s.trim()));
        const attr = (b.attrs.sources ?? "").split(",").map((s) => s.trim()).filter(Boolean);
        for (const id of [...cited, ...attr]) assert.ok(sources[id], `${file}: "${b.attrs.title}" cites unknown source ${id}`);
      }
  });

  test(`${name}: each module has interactive, explanation, layout and a quiz of 3 to 6 questions; the course ends with a final test and sources`, () => {
    const lessons = modules.filter((m) => !/^(00|1[0-9])-/.test(m.file));
    assert.equal(lessons.length, 9);
    for (const { file, blocks } of lessons) {
      const kinds = blocks.map((b) => b.kind);
      for (const k of ["interactive", "richtext", "layout", "quiz"]) assert.ok(kinds.includes(k), `${file} has no ${k}`);
      const quiz = blocks.find((b) => b.kind === "quiz");
      const n = giftQuestions(quiz.body).length;
      assert.ok(n >= 3 && n <= 6, `${file}: ${n} questions`);
      assert.ok(blocks.filter((b) => b.kind === "richtext").every((b) => /\{\{src:/.test(b.body)), `${file}: an explanation without a citation`);
    }
    const final = modules.at(-2).blocks.find((b) => b.kind === "quiz");
    assert.equal(giftQuestions(final.body).length, name === "gravity" ? 15 : 12);
    assert.match(modules.at(-1).blocks[0].body, /CC BY 4\.0/);
    assert.match(modules.at(-1).blocks[0].body, /\{\{sources:all\}\}/);
  });

  test(`${name}: every question parses as a real type and the course uses all the kinds`, () => {
    const types = new Set();
    for (const { file, blocks } of modules)
      for (const b of blocks.filter((x) => x.kind === "quiz"))
        for (const q of giftQuestions(b.body)) {
          const t = giftType(q);
          assert.ok(!["description", "essay", "short"].includes(t), `${file}: ${q.slice(0, 60)} parses as ${t}`);
          types.add(t);
        }
    for (const t of ["multiple_choice", "numerical", "matching", "true_false"]) assert.ok(types.has(t), `no ${t} question`);
  });

  test(`${name}: every layout is JSON of catalogue components with a Markdown fallback`, () => {
    let layouts = 0;
    for (const { file, blocks } of modules)
      for (const b of blocks.filter((x) => x.kind === "layout")) {
        layouts++;
        const doc = JSON.parse(b.body);
        assert.ok(Array.isArray(doc.document) && doc.document.length > 0 && typeof doc.fallback === "string", `${file}: layout shape`);
        for (const node of doc.document) assert.ok(manifest.components[node.component], `${file}: unknown component ${node.component}`);
        assert.ok(b.attrs.sources, `${file}: a layout without sources`);
      }
    assert.equal(layouts, 9);
  });
}
