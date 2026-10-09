# scottish-book: problem cards

Notebook pages, one per problem: a number, who posed it, when, a plain-language summary, a prize. Steps: `intro`
(every card, with a filter by poser) and one step per problem (`problem-19`, `problem-153`, `problem-193`), where
the learner guesses the problem's outcome and checks the guess. Every check sends `score(raw, max, passed)` over
all cards; use the topic's `on_score` completion with a pass percentage of 60. The package never sends `complete`.

**Placeholder content.** `data/problems.json` holds only placeholder text (`"placeholder": true`): the posers,
dates, summaries, prizes and outcomes are not facts, the package says so on screen, and
`demo-content/tests/unit/ulam-scottish-book.test.mjs` pins it. The real cards are filled in M9b, from the cleared
fact sheet (`docs/plans/interactive-demos-ulam-facts.md`, section 3, M9a): each card then needs its source ids.

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
