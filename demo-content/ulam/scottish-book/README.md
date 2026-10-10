# scottish-book: problem cards

Notebook pages, one per problem: a number, who posed it, when (where the sources give a date), a plain-language summary,
the prize, and the sources. Steps: `intro` (every card, with a filter by poser) and one step per problem (`problem-1`,
`-19`, `-38`, `-43`, `-59`, `-152`, `-153`, `-184`, `-193`). On four of them (19, 59, 153, 184) the learner guesses the
outcome (Yes, No, Not yet settled) and checks the guess; the other cards show what became of the problem, because the
sources do not record it as a yes or no. Every check sends `score(raw, max, passed)` over the guessable cards; use the
topic's `on_score` completion with a pass percentage of 60. The package never sends `complete`.

**Content.** `data/problems.json` holds the cards; the statements are paraphrased, never copied from Mauldin's edition.
Each card lists source ids that resolve in `data/problems.json`'s `sources` table and in `demo-content/ulam/sources.json`;
the unit test `ulam-scottish-book.test.mjs` pins the dates, prizes and outcomes against the fact sheet. Problem 77(a) is
left out because its statement could not be sourced.

- `logic.js`: the filter, grading a guess, the score and the pass rule (the boundary 60% passes), the feedback
  sentence. Unit tests cover them and check the data file (unique ids, every answer is an option, one step per
  problem).
- Card text is set with `textContent`, never as HTML (a test asserts that the cards contain no script, image or
  link).
- A guess uses native radio buttons and a button; after checking, the focus returns to the card's heading and the
  result is announced. Answered cards stay answered while the learner moves between steps.

```bash
node demo-content/scripts/pack.mjs demo-content/ulam/scottish-book
node demo-content/scripts/posters.mjs demo-content/ulam/scottish-book
yarn workspace @ulams/demo-content test:e2e ulam-scottish-book
```
