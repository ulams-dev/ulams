import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { test } from "node:test";
import { PASS_PERCENT, feedback, filterByPoser, isCorrect, isGuessable, metaLine, posersOf, scoreGuesses, sourceLine } from "../../ulam/scottish-book/logic.js";

const read = (f) => JSON.parse(readFileSync(new URL(`../../ulam/scottish-book/${f}`, import.meta.url), "utf8"));
const card = (id, poser, answer, date = "d") => ({ id, number: Number(id.split("-")[1]), poser, date, summary: "s", prize: "p", outcome: `Outcome of ${id}.`, answer, sources: ["U-33"] });
const cards = [card("problem-1", "A", "yes"), card("problem-2", "B", "open"), card("problem-3", "A", "no"), card("problem-4", "C", "yes"), card("problem-5", "B", "open"), card("problem-6", "A", null)];
const options = [{ value: "yes", label: "Yes" }, { value: "no", label: "No" }, { value: "open", label: "Not yet settled" }];

test("the filter lists the posers in order of appearance and narrows the cards", () => {
  assert.deepEqual(posersOf(cards), ["A", "B", "C"]);
  assert.deepEqual(filterByPoser(cards, "A").map((c) => c.id), ["problem-1", "problem-3", "problem-6"]);
  assert.equal(filterByPoser(cards, "").length, 6);
  assert.deepEqual(filterByPoser(cards, "nobody"), []);
});

test("a guess is right only when it is the recorded outcome, and a card without one cannot be guessed", () => {
  assert.equal(isCorrect(cards[0], "yes"), true);
  assert.equal(isCorrect(cards[0], "no"), false);
  assert.equal(isCorrect(cards[0], undefined), false);
  assert.equal(isGuessable(cards[5]), false);
  assert.equal(isCorrect(cards[5], "yes"), false);
});

test("the score counts right guesses over the guessable cards and passes at 60 percent", () => {
  assert.equal(PASS_PERCENT, 60);
  assert.deepEqual(scoreGuesses(cards, {}), { raw: 0, max: 5, percent: 0, passed: false });
  const two = scoreGuesses(cards, { "problem-1": "yes", "problem-2": "open" });
  assert.deepEqual([two.raw, two.max, two.percent, two.passed], [2, 5, 40, false]);
  const three = scoreGuesses(cards, { "problem-1": "yes", "problem-2": "open", "problem-3": "no", "problem-4": "open" });
  assert.deepEqual([three.raw, three.percent, three.passed], [3, 60, true]); // the boundary passes
  assert.equal(scoreGuesses([], {}).passed, false);
  assert.equal(scoreGuesses([cards[5]], {}).max, 0); // an unguessable card never counts
});

test("the feedback names the guess and the outcome", () => {
  assert.equal(feedback(cards[0], "yes", options), "Correct: Yes. Outcome of problem-1.");
  assert.equal(feedback(cards[1], "yes", options), 'Not quite: you chose "Yes", the recorded outcome is "Not yet settled". Outcome of problem-2.');
});

test("the meta line and the source line read from the card and the label table", () => {
  assert.equal(metaLine(cards[0]), "A · d");
  assert.equal(metaLine(card("problem-9", "Z", null, "")), "Z");
  assert.equal(sourceLine(cards[0], { "U-33": "Paper" }), "Sources: Paper");
  assert.equal(sourceLine(cards[0], {}), "Sources: U-33");
});

test("the shipped data is the sourced content: no placeholder, nine problems in number order, every card sourced", () => {
  const data = read("data/problems.json");
  const manifest = read("ulams-interactive.json");
  const facts = read("../facts.json");
  const sources = read("../sources.json");
  const ids = data.problems.map((p) => p.id);
  assert.equal(new Set(ids).size, ids.length);
  assert.deepEqual(data.problems.map((p) => p.number), [1, 19, 38, 43, 59, 152, 153, 184, 193]);
  assert.equal(data.placeholder, undefined);
  const values = data.options.map((o) => o.value);
  const text = JSON.stringify(data) + JSON.stringify(manifest);
  assert.doesNotMatch(text, /placeholder/i);
  const guessable = data.problems.filter((p) => p.answer !== null);
  assert.deepEqual(guessable.map((p) => p.number), [19, 59, 153, 184]);
  for (const p of data.problems) {
    assert.ok(p.answer === null || values.includes(p.answer), p.id);
    assert.ok(p.summary.length > 20 && p.poser.length > 0 && p.outcome.length > 10, p.id);
    assert.ok(p.sources.length > 0, `${p.id} has no sources`);
    for (const s of p.sources) { assert.ok(sources[s], `${p.id}: unknown source ${s}`); assert.ok(data.sources[s], `${p.id}: no label for ${s}`); }
    assert.ok(facts[`3b/${p.number}`], `${p.id}: no fact 3b/${p.number}`);
    assert.notEqual(facts[`3b/${p.number}`].status, "not-used");
  }
  // 77(a) is not summarised: its statement could not be sourced
  assert.equal(data.problems.some((p) => p.number === 77), false);
  assert.equal(facts["3b/77a"].status, "not-used");
  assert.deepEqual(manifest.steps.map((s) => s.id), ["intro", ...ids]);
  for (const s of manifest.steps) assert.ok(s.text.en.length > 40, s.id);
  // known facts of the sources, so a card cannot drift from them
  const by = (n) => data.problems.find((p) => p.number === n);
  assert.equal(by(1).date, "17 July 1935");
  assert.equal(by(153).date, "6 November 1936");
  assert.equal(by(153).answer, "no");
  assert.match(by(153).prize, /goose/);
  assert.match(by(153).outcome, /Enflo.*1972/);
  assert.match(by(152).prize, /caviar/);
  assert.equal(by(193).date, "31 May 1941");
  assert.match(by(43).prize, /wine/);
  assert.match(by(59).outcome, /Sprague.*1939/);
  assert.match(by(19).outcome, /2022/);
  assert.match(by(184).date, /1940/);
  assert.doesNotMatch(JSON.stringify(data), /brandy/i);
});
