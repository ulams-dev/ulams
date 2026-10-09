import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { test } from "node:test";
import { PASS_PERCENT, feedback, filterByPoser, isCorrect, posersOf, scoreGuesses } from "../../ulam/scottish-book/logic.js";

const read = (f) => JSON.parse(readFileSync(new URL(`../../ulam/scottish-book/${f}`, import.meta.url), "utf8"));
const card = (id, poser, answer) => ({ id, number: Number(id.split("-")[1]), poser, date: "d", summary: "s", prize: "p", outcome: `Outcome of ${id}.`, answer, sources: [] });
const cards = [card("problem-1", "A", "solved"), card("problem-2", "B", "open"), card("problem-3", "A", "disproved"), card("problem-4", "C", "solved"), card("problem-5", "B", "open")];
const options = [{ value: "solved", label: "Solved" }, { value: "open", label: "Still open" }, { value: "disproved", label: "Answered the other way" }];

test("the filter lists the posers in order of appearance and narrows the cards", () => {
  assert.deepEqual(posersOf(cards), ["A", "B", "C"]);
  assert.deepEqual(filterByPoser(cards, "A").map((c) => c.id), ["problem-1", "problem-3"]);
  assert.equal(filterByPoser(cards, "").length, 5);
  assert.deepEqual(filterByPoser(cards, "nobody"), []);
});

test("a guess is right only when it is the recorded outcome", () => {
  assert.equal(isCorrect(cards[0], "solved"), true);
  assert.equal(isCorrect(cards[0], "open"), false);
  assert.equal(isCorrect(cards[0], undefined), false);
});

test("the score counts right guesses over all cards and passes at 60 percent", () => {
  assert.equal(PASS_PERCENT, 60);
  assert.deepEqual(scoreGuesses(cards, {}), { raw: 0, max: 5, percent: 0, passed: false });
  const two = scoreGuesses(cards, { "problem-1": "solved", "problem-2": "open" });
  assert.deepEqual([two.raw, two.max, two.percent, two.passed], [2, 5, 40, false]);
  const three = scoreGuesses(cards, { "problem-1": "solved", "problem-2": "open", "problem-3": "disproved", "problem-4": "open" });
  assert.deepEqual([three.raw, three.percent, three.passed], [3, 60, true]); // the boundary passes
  assert.equal(scoreGuesses([], {}).passed, false);
});

test("the feedback names the guess and the outcome", () => {
  assert.equal(feedback(cards[0], "solved", options), "Correct: Solved. Outcome of problem-1.");
  assert.equal(feedback(cards[1], "solved", options), 'Not quite: you chose "Solved", the recorded outcome is "Still open". Outcome of problem-2.');
});

test("the shipped data is consistent: unique ids, every answer is an option, one step per problem, placeholder flagged", () => {
  const data = read("data/problems.json");
  const manifest = read("ulams-interactive.json");
  const ids = data.problems.map((p) => p.id);
  assert.equal(new Set(ids).size, ids.length);
  assert.ok(data.problems.length >= 3);
  const values = data.options.map((o) => o.value);
  for (const p of data.problems) { assert.ok(values.includes(p.answer), p.id); assert.ok(p.summary.length > 0 && p.poser.length > 0); }
  assert.deepEqual(manifest.steps.map((s) => s.id), ["intro", ...ids]);
  // until the fact sheet is cleared (M9a) the cards say so, and the manifest says so
  assert.equal(data.placeholder, true);
  assert.match(data.note, /not facts/);
  for (const p of data.problems) assert.match(p.summary, /^Placeholder/);
  assert.deepEqual(data.problems.map((p) => p.number), [19, 153, 193]);
});
