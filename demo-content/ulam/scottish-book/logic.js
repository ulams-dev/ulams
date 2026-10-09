// @ts-check
// The pure logic of the Scottish Book cards: the filter by poser, grading a guess and the score the lesson
// completes on. No DOM, so the unit tests run it in Node.

/**
 * @typedef {{ id: string, number: number, poser: string, date: string, summary: string, prize: string, outcome: string, answer: string, sources: string[] }} Card
 */

/** The share of correct guesses (in percent) at which the lesson counts as passed. */
export const PASS_PERCENT = 60;

/** The distinct posers, in the order they first appear. */
export const posersOf = (/** @type {Card[]} */ cards) => [...new Set(cards.map((c) => c.poser))];

/** The cards of one poser, or all of them for an empty filter. */
export const filterByPoser = (/** @type {Card[]} */ cards, /** @type {string} */ poser) => (poser ? cards.filter((c) => c.poser === poser) : cards);

/** True when the guess is the card's recorded outcome. */
export const isCorrect = (/** @type {Card} */ card, /** @type {string | undefined} */ guess) => guess !== undefined && guess === card.answer;

/**
 * The score over all cards: how many guesses were right, out of how many cards, and whether that passes.
 * @param {Card[]} cards
 * @param {Record<string, string>} guesses card id to the chosen outcome
 */
export function scoreGuesses(cards, guesses) {
  const raw = cards.filter((c) => isCorrect(c, guesses[c.id])).length;
  const max = cards.length;
  return { raw, max, percent: max ? (100 * raw) / max : 0, passed: max > 0 && (100 * raw) / max >= PASS_PERCENT };
}

/** The sentence after a guess. */
export function feedback(/** @type {Card} */ card, /** @type {string} */ guess, /** @type {{value: string, label: string}[]} */ options) {
  const label = (v) => (options.find((o) => o.value === v) || { label: v }).label;
  return isCorrect(card, guess)
    ? `Correct: ${label(card.answer)}. ${card.outcome}`
    : `Not quite: you chose "${label(guess)}", the recorded outcome is "${label(card.answer)}". ${card.outcome}`;
}
