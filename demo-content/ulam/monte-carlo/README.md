# monte-carlo: estimate π by throwing points

Throw random points into a unit square with a quarter circle in it; four times the share that lands inside is an
estimate of π. Four steps: `idea` (a picture), `throw` (+1, +100, +10,000, Reset), `converge` (a log-log chart of
the error against the number of throws) and `error` (the error to expect is about 1.64 over the square root of
n). It completes (`complete`, once) when 10,000 points have been thrown, whichever step the learner is on.

- `logic.js`: the pure logic: the seeded generator (mulberry32), the estimate, the error to expect, the log-spaced
  samples behind the chart. Unit tests: `demo-content/tests/unit/ulam-monte-carlo.test.mjs` (a frozen first value,
  the same counts however the throws are split into batches, the mean error over 60 seeds is the expected size).
- A seed (shown, editable) makes a run repeatable: seed 2026 gives 7,812 points inside after 10,000 throws.
- Accessible: native buttons and an input; the picture and the chart have text descriptions that update after every
  throw, the chart has a data table, nothing animates (reduced motion needs no change).

```bash
node demo-content/scripts/pack.mjs demo-content/ulam/monte-carlo
node demo-content/scripts/posters.mjs demo-content/ulam/monte-carlo
yarn workspace @ulams/demo-content test:e2e ulam-monte-carlo
```
