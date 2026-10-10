// The text of the Ulam course (api/database/seeds/Demo/content/ulam/modules/*.md, read by the PHP seeder) against the five packages
// (demo-content/ulam/*), the fact sheet (facts.json) and the source list (sources.json): the structure of eight modules, a final test and
// a sources lesson, every step range, every citation, every quiz question traced to its fact ids, the question types, the layouts, the
// images and their credits, and the things the fact sheet rules out.
import assert from "node:assert/strict";
import { existsSync, readFileSync, readdirSync } from "node:fs";
import { dirname, join } from "node:path";
import { test } from "node:test";
import { fileURLToPath } from "node:url";
import { giftQuestions, giftType, parseModule } from "../lib/course-text.mjs";

const demo = join(dirname(fileURLToPath(import.meta.url)), "..", "..");
const seeds = join(demo, "..", "api", "database", "seeds", "Demo");
const content = join(seeds, "content", "ulam");
const read = (p) => JSON.parse(readFileSync(p, "utf8"));
const facts = read(join(demo, "ulam", "facts.json"));
const sources = read(join(demo, "ulam", "sources.json"));
const catalogue = read(join(demo, "..", "api", "packages", "topic-type-layout", "resources", "learner-layout-manifest.json"));
const POINTER_SOURCES = [8, 13, 14, 20, 21, 27, 37];

const PACKAGES = Object.fromEntries(["spiral", "monte-carlo", "automaton", "scottish-book", "lwow-map"].map((d) => [`ulam-${d}`, read(join(demo, "ulam", d, "ulams-interactive.json")).steps.map((s) => s.id)]));
const files = readdirSync(join(content, "modules")).filter((f) => f.endsWith(".md")).sort();
const modules = files.map((file) => ({ file, raw: readFileSync(join(content, "modules", file), "utf8"), ...parseModule(readFileSync(join(content, "modules", file), "utf8")) }));
const lessons = modules.slice(1, 9);
const quizBlocks = (m) => m.blocks.filter((b) => b.kind === "quiz");
const everyText = modules.map((m) => m.blocks.filter((b) => b.kind !== "quiz").map((b) => b.body).join("\n")).join("\n");

/** [{ comment, question }] in order: a question and the `// facts:` line above it. */
const withFacts = (body) => {
  const out = [];
  let comment = null;
  let current = [];
  const flush = () => { if (current.length) out.push({ comment, question: current.join("\n").trim() }); current = []; comment = null; };
  for (const line of body.split("\n")) {
    if (line.trim() === "") flush();
    else if (line.trimStart().startsWith("//")) comment = line.trim();
    else current.push(line);
  }
  flush();
  return out;
};

test("the course is a welcome lesson, eight modules, the final test and the sources lesson", () => {
  assert.equal(modules.length, 11);
  assert.deepEqual(files.map((f) => f.slice(0, 2)), ["00", "01", "02", "03", "04", "05", "06", "07", "08", "09", "10"]);
  assert.match(modules[0].meta.title, /Welcome/);
  assert.match(modules[9].meta.title, /final test/i);
  assert.match(modules[10].meta.title, /Sources and licence/);
  for (const m of modules) assert.ok(m.meta.title && m.meta.summary && m.meta.duration, m.file);
});

test("each module has text, a Layout learning element and a quiz, and the lesson titles number the sixteen lessons", () => {
  const layouts = ["Timeline", "FlipCards", "Callout", "PracticeActivity", "Callout", "Steps", "Timeline", "FlipCards"];
  const titles = lessons.flatMap((m) => m.blocks.filter((b) => b.kind === "richtext").map((b) => b.attrs.title));
  assert.deepEqual(titles.map((t) => t.split(" ")[0]), ["1.1", "1.2", "2.1", "2.2", "2.3", "3.1", "3.2", "3.3", "4.1", "4.2", "5.1", "6.1", "6.2", "7.1", "8.1", "8.2"]);
  lessons.forEach((m, i) => {
    const kinds = m.blocks.map((b) => b.kind);
    for (const k of ["richtext", "layout", "quiz"]) assert.ok(kinds.includes(k), `${m.file} has no ${k}`);
    assert.equal(m.blocks.filter((b) => b.kind === "layout").length, 1, m.file);
    const layout = JSON.parse(m.blocks.find((b) => b.kind === "layout").body);
    assert.deepEqual(layout.document.map((n) => n.component), [layouts[i]], m.file);
    for (const node of layout.document) assert.ok(catalogue.components[node.component], node.component);
    assert.ok(m.blocks.find((b) => b.kind === "layout").attrs.sources, `${m.file}: a layout without sources`);
    assert.ok(layout.fallback.length > 40, m.file);
  });
  assert.equal(modules[5].blocks.some((b) => b.kind === "interactive"), false, "module 5 has no interactive");
  for (const m of lessons.filter((m) => m !== modules[5])) assert.ok(m.blocks.some((b) => b.kind === "interactive"), `${m.file} has no interactive`);
});

test("every interactive topic names a package of the course and a forward range of its steps", () => {
  let n = 0;
  for (const m of modules)
    for (const b of m.blocks.filter((x) => x.kind === "interactive")) {
      n++;
      const steps = PACKAGES[b.attrs.package];
      assert.ok(steps, `${m.file}: ${b.attrs.title}: unknown package ${b.attrs.package}`);
      const from = steps.indexOf(b.attrs.start);
      const to = steps.indexOf(b.attrs.end ?? b.attrs.start);
      assert.ok(from >= 0 && to >= from, `${m.file}: ${b.attrs.title}: ${b.attrs.start}..${b.attrs.end}`);
      assert.equal(b.attrs.display, "inline");
      if (b.attrs.completion === "on_score") assert.equal(b.attrs.score, "60");
    }
  assert.equal(n, 12);
  // the landing hero plays the first interactive of the first lesson: the spiral
  const first = modules.flatMap((m) => m.blocks).find((b) => b.kind === "interactive");
  assert.equal(first.attrs.package, "ulam-spiral");
  assert.equal(modules[0].blocks.at(-1), first, "the welcome lesson ends with the spiral");
  // the packages that complete themselves: spiral, monte-carlo and automaton by `complete`, the cards by score, the map by its range
  const rule = (pkg) => new Set(modules.flatMap((m) => m.blocks).filter((b) => b.attrs.package === pkg && b.attrs.completion).map((b) => b.attrs.completion));
  assert.deepEqual([...rule("ulam-scottish-book")], ["on_score"]);
  assert.deepEqual([...rule("ulam-spiral")], ["on_complete"]);
  assert.deepEqual([...rule("ulam-lwow-map")], []);
});

test("every citation names a source of the list, never a pointer source, and the seed copy of the list is the package's", () => {
  assert.deepEqual(read(join(content, "sources.json")), sources);
  for (const n of POINTER_SOURCES) assert.equal(sources[`U-${String(n).padStart(2, "0")}`], undefined, `pointer source ${n}`);
  for (const [id, s] of Object.entries(sources)) assert.ok(s.title && /^U-\d\d$/.test(id), id);
  const cited = new Set();
  for (const m of modules)
    for (const b of m.blocks) {
      for (const g of b.body.matchAll(/\{\{src:([^}]+)\}\}/g)) for (const id of g[1].split(",").map((x) => x.trim())) { assert.ok(sources[id], `${m.file}: unknown source ${id}`); cited.add(id); }
      for (const id of (b.attrs.sources ?? "").split(",").map((x) => x.trim()).filter(Boolean)) { assert.ok(sources[id], `${m.file}: unknown source ${id}`); cited.add(id); }
    }
  assert.ok(!cited.has("U-47"), "the 1958 Bulletin memorial could not be read and is never cited");
  assert.ok(cited.size >= 30, `${cited.size} sources cited`);
  for (const m of lessons) for (const b of m.blocks.filter((x) => x.kind === "richtext")) assert.match(b.body, /\{\{src:/, `${m.file}: ${b.attrs.title} has no citation`);
  assert.match(modules[10].blocks[0].body, /\{\{sources:all\}\}/);
  assert.match(modules[10].blocks[0].body, /CC BY 4\.0/);
  assert.match(modules[0].blocks[0].body, /CC BY 4\.0/);
});

test("the fact sheet is consistent: every fact's sources exist, nothing marked 'not used' is a quiz fact", () => {
  for (const [id, f] of Object.entries(facts)) {
    assert.ok(f.text.length > 10 && ["ok", "differs", "second", "resolution", "not-used"].includes(f.status), id);
    for (const s of f.sources) assert.ok(sources[s], `${id}: unknown source ${s}`);
  }
  for (const id of ["1.1", "1.4", "3b/153", "C3", "C4", "C5", "3.13"]) assert.ok(facts[id], id);
  assert.equal(facts["3b/77a"].status, "not-used");
  assert.equal(facts["9.4"].status, "not-used");
});

test("module quizzes have 38 questions (5, 7, 5, 5, 3, 5, 4, 4), the final test 14, each traced to the fact ids and sources it rests on", () => {
  const counts = lessons.map((m) => giftQuestions(quizBlocks(m)[0].body).length);
  assert.deepEqual(counts, [5, 7, 5, 5, 3, 5, 4, 4]);
  assert.equal(counts.reduce((a, b) => a + b, 0), 38);
  const final = modules[9].blocks[0];
  assert.equal(giftQuestions(final.body).length, 14);
  assert.equal(final.attrs.pass, "70"); assert.equal(final.attrs.attempts, "2"); assert.equal(final.attrs.minutes, "20"); assert.equal(final.attrs.weight, "10");
  for (const m of lessons) { const q = quizBlocks(m)[0]; assert.equal(q.attrs.pass, "60"); assert.equal(q.attrs.attempts, undefined); }
  const types = new Set();
  for (const m of [...lessons, modules[9]])
    for (const b of quizBlocks(m))
      for (const { comment, question } of withFacts(b.body)) {
        assert.ok(comment && /^\/\/ facts: /.test(comment), `${m.file}: ${question.slice(0, 50)} has no facts comment`);
        const ids = [...comment.matchAll(/(?<![\w.])(\d+\.\d+[a-z]?|3b\/\d+[a-z]?)(?![\w.])/g)].map((x) => x[1]);
        const derived = /derivation|product name sentence/.test(comment);
        assert.ok(ids.length > 0 || derived, `${m.file}: ${comment}`);
        for (const id of ids) {
          assert.ok(facts[id], `${m.file}: ${comment}: unknown fact ${id}`);
          assert.ok(["ok", "differs"].includes(facts[id].status), `${m.file}: fact ${id} is ${facts[id].status} and cannot be quizzed`);
          if (facts[id].status === "differs") assert.match(comment, /see C\d|place only/, `${m.file}: a disputed fact needs its note`);
        }
        for (const s of comment.matchAll(/\[(U-\d\d)\]/g)) assert.ok(sources[s[1]], `${m.file}: ${comment}`);
        const t = giftType(question);
        assert.ok(!["description", "essay", "short"].includes(t), `${m.file}: ${question.slice(0, 60)} parses as ${t}`);
        types.add(t);
      }
  for (const t of ["multiple_choice", "numerical", "matching", "true_false"]) assert.ok(types.has(t), `no ${t} question`);
  // the numerical answers follow from the lesson (4.2) or are exact facts
  const nums = Object.fromEntries([...lessons, modules[9]].flatMap((m) => quizBlocks(m).flatMap((b) => giftQuestions(b.body))).filter((q) => giftType(q) === "numerical").map((q) => [q.match(/::([\w-]+)::/)[1], q.match(/\{#([\d.]+):([\d.]+)\}/).slice(1).map(Number)]));
  assert.deepEqual(nums, { "M1-Q2": [1909, 0], "M2-Q4": [193, 0], "M4-Q2": [3.14, 0.01], "M6-Q4": [29, 0], "M7-Q4": [1955, 0], "F-Q5": [193, 0], "F-Q8": [3.13, 0.01] });
  assert.ok(Math.abs((4 * 7850) / 10000 - 3.14) < 1e-9 && Math.abs((4 * 3130) / 4000 - 3.13) < 1e-9);
});

test("the quizzes never ask about what the fact sheet says is disputed or unconfirmed", () => {
  const quizText = modules.flatMap((m) => quizBlocks(m).map((b) => b.body.split("\n").filter((l) => !l.trimStart().startsWith("//")).join("\n"))).join("\n");
  assert.doesNotMatch(quizText, /Piłsudski|Batory|Danzig|Gdańsk.*1939|Stożek|brandy|singularity|13 April|3 April/i, "disputed points are not quizzed");
  assert.doesNotMatch(quizText, /who (bought|supplied)|Łucja|buried|survive/i);
  assert.doesNotMatch(quizText, /uncle|MANIAC II|\bcover\b/i);
  assert.doesNotMatch(quizText, /Wisconsin.*(1940|1941)|(1940|1941).*Wisconsin/); // the place, never the year
  // the numerical answers of the quizzes are years and counts, not days of the month of a disputed date
});

test("what the course text says follows the sheet: the resolved disputes, no ruled-out claim, no unnamed quotation", () => {
  const text = everyText;
  assert.match(text, /13 April 1909/);
  assert.doesNotMatch(text, /(^|[^0-9])3 April 1909/);
  assert.match(text, /thesis written under Kuratowski/);
  assert.match(text, /Another account says that Banach's wife/);
  assert.match(text, /two accounts/); // how the book came through the war
  assert.doesNotMatch(text, /brandy/i);
  assert.doesNotMatch(text, /singularity/i);
  assert.doesNotMatch(text, /ENIAC.*Ulam's uncle|uncle named|uncle,? (Michał|Szymon)/);
  assert.match(text, /1940 \(some reference works give 1941\)/);
  assert.match(text, /not the Batory as Ulam states/); // the one place the ship is named, as a difference between sources
  assert.equal([...text.matchAll(/Piłsudski/g)].length, 2); // the callout and its fallback
  assert.match(text, /ULAMS is named in tribute to the mathematician Stanisław Ulam \(1909–1984\)\. The project is not affiliated with or endorsed by his estate, Los Alamos National Laboratory or any institution associated with him\./);
  // every quotation of four words or more is a quotation of the fact sheet
  const norm = (s) => s.replace(/\*/g, "").replace(/[“”]/g, '"').replace(/[‘’]/g, "'").replace(/\s+/g, " ").toLowerCase();
  const haystack = norm(Object.values(facts).map((f) => `${f.text} ${(f.quotes ?? []).join(" ")} ${f.wording ?? ""}`).join(" ") + " " + readFileSync(join(demo, "..", "docs", "plans", "interactive-demos-ulam-facts.md"), "utf8"));
  const layoutStrings = (v) => (typeof v === "string" ? [v] : Array.isArray(v) ? v.flatMap(layoutStrings) : v && typeof v === "object" ? Object.values(v).flatMap(layoutStrings) : []);
  const prose = modules.slice(0, 10).flatMap((m) => m.blocks.filter((b) => b.kind === "richtext" || b.kind === "interactive").map((b) => b.body)).concat(modules.slice(0, 10).flatMap((m) => m.blocks.filter((b) => b.kind === "layout").flatMap((b) => layoutStrings(JSON.parse(b.body))))).join("\n");
  const quotes = [...prose.matchAll(/(?<![\w])"([^"\n]{20,}?)"(?![\w])/g)].map((m) => m[1]).filter((q) => q.split(" ").length >= 4);
  assert.ok(quotes.length >= 10, `${quotes.length} quotations`);
  for (const q of quotes) {
    const bare = norm(q.replace(/[.,]$/, "")).replace(/^prizes/, "prizes");
    assert.ok(haystack.includes(bare), `a quotation that is not in the fact sheet: "${q}"`);
  }
});

test("the photographs: four files, each with its credit line in the lesson and in the sources lesson and CREDITS.md", () => {
  const images = ["ulam-badge.webp", "ulam-portrait.webp", "fermiac.webp", "scottish-cafe-building.webp"];
  for (const i of images) { assert.ok(existsSync(join(seeds, "assets", "ulam", "images", i)), i); assert.ok(readFileSync(join(seeds, "assets", "ulam", "images", i)).length < 300 * 1024, `${i} is under 300 KB`); }
  const used = [...everyText.matchAll(/\{\{asset:([\w.-]+)\}\}/g)].map((m) => m[1]).sort();
  assert.deepEqual(used, [...images].sort());
  const credits = readFileSync(join(demo, "ulam", "CREDITS.md"), "utf8");
  const licensing = readFileSync(join(demo, "..", "LICENSING.md"), "utf8");
  for (const [img, credit] of [["ulam-badge.webp", "Los Alamos National Laboratory"], ["ulam-portrait.webp", "Los Alamos National Laboratory"], ["fermiac.webp", "Mark Pellegrini"], ["scottish-cafe-building.webp", "Rbrechko"]]) {
    const lesson = modules.find((m) => m.raw.includes(`{{asset:${img}}}`));
    assert.ok(lesson.raw.includes(credit), `${img}: the credit line names ${credit}`);
    assert.match(lesson.raw, new RegExp(`\\*[^*\\n]*${credit}[^*\\n]*\\*`), `${img}: an italic credit line`);
    assert.ok(credits.includes(img.replace(".webp", "")) || credits.includes(credit), img);
  }
  assert.match(modules[10].blocks[0].body, /CC BY-SA 1\.0/);
  assert.match(modules[10].blocks[0].body, /CC BY-SA 4\.0/);
  assert.match(modules[10].blocks[0].body, /Unless otherwise indicated, this information has been authored by an employee or employees of the Los Alamos National Security/);
  for (const needle of ["Pellegrini", "Rbrechko", "Los Alamos National Laboratory"]) assert.ok(credits.includes(needle), `CREDITS.md: ${needle}`);
  assert.match(licensing, /Ulam course/);
  assert.match(licensing, /CC BY-SA/);
  // dropped by the owner decision of the fact sheet: no FERMIAC-with-Ulam photo, no Scottish Book page photo
  assert.doesNotMatch(everyText, /KsiegaSzkocka|STAN_ULAM_HOLDING/);
});

test("no placeholder text is left anywhere in the course or the packages", () => {
  assert.doesNotMatch(everyText, /placeholder|to be added|coming soon/i);
  for (const f of ["welcome.md"]) assert.equal(existsSync(join(content, f)), false, `${f} is replaced by the module files`);
  assert.doesNotMatch(readFileSync(join(content, "course-description.md"), "utf8"), /as it will be|for now the welcome lesson/);
});
