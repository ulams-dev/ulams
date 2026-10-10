// @ts-check
// The pure logic of the Scottish Book cards: the filter by poser, grading a guess and the score the lesson
// completes on. No DOM, so the unit tests run it in Node.

/**
 * @typedef {{ id: string, number: number, poser: string, date: string, summary: string, prize: string, outcome: string, answer: string | null, sources: string[] }} Card
 * A card whose `answer` is null has no guess: the sources record what became of the problem but not as one of the options.
 */

/** The share of correct guesses (in percent) at which the lesson counts as passed. */
export const PASS_PERCENT = 60;

/** The distinct posers, in the order they first appear. */
export const posersOf = (/** @type {Card[]} */ cards) => [...new Set(cards.map((c) => c.poser))];

/** The cards of one poser, or all of them for an empty filter. */
export const filterByPoser = (/** @type {Card[]} */ cards, /** @type {string} */ poser) => (poser ? cards.filter((c) => c.poser === poser) : cards);

/** True for a card the learner can guess. */
export const isGuessable = (/** @type {Card} */ card) => card.answer !== null && card.answer !== undefined;

/** True when the guess is the card's recorded outcome. */
export const isCorrect = (/** @type {Card} */ card, /** @type {string | undefined} */ guess) => isGuessable(card) && guess !== undefined && guess === card.answer;

/**
 * The score over the guessable cards: how many guesses were right, out of how many such cards, and whether that passes.
 * @param {Card[]} cards
 * @param {Record<string, string>} guesses card id to the chosen outcome
 */
export function scoreGuesses(cards, guesses) {
  const guessable = cards.filter(isGuessable);
  const raw = guessable.filter((c) => isCorrect(c, guesses[c.id])).length;
  const max = guessable.length;
  return { raw, max, percent: max ? (100 * raw) / max : 0, passed: max > 0 && (100 * raw) / max >= PASS_PERCENT };
}

/** The sentence after a guess. */
export function feedback(/** @type {Card} */ card, /** @type {string} */ guess, /** @type {{value: string, label: string}[]} */ options) {
  const label = (v) => (options.find((o) => o.value === v) || { label: v }).label;
  return isCorrect(card, guess)
    ? `Correct: ${label(card.answer ?? "")}. ${card.outcome}`
    : `Not quite: you chose "${label(guess)}", the recorded outcome is "${label(card.answer ?? "")}". ${card.outcome}`;
}

/** "Poser · date", or the poser alone when the sources give no date. */
export const metaLine = (/** @type {Card} */ card) => (card.date ? `${card.poser} · ${card.date}` : card.poser);

/** The "Sources: a; b" line of a card, from the source labels of the data file. */
export const sourceLine = (/** @type {Card} */ card, /** @type {Record<string, string>} */ labels) => `Sources: ${card.sources.map((id) => labels[id] ?? id).join("; ")}`;
