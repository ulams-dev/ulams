# spiral: the Ulam spiral

Write the whole numbers on a square spiral (1 in the middle, then right, up, left, down, …), mark the primes and
see them gather on diagonals. Four steps: `grid` (1 to 100), `primes` (2,500 numbers, a sieve of Eratosthenes),
`diagonals` (start at 41 and Euler's polynomial n² + n + 41 falls on one diagonal: forty values in a row, all
prime) and `explore` (up to 40,000 numbers, any starting number). It completes when `explore` is reached.

- `logic.js`: the pure logic (a segmented sieve, the spiral walk, the highlighted diagonal, the summary and the
  cell text). Unit tests: `demo-content/tests/unit/ulam-spiral.test.mjs` (π(100) = 25, π(40,000) = 4,203, the
  first positions of the spiral, n² + n + 41 prime for n = 0 … 39 and on one diagonal).
- `main.js`: the page. The picture is a focusable canvas: the arrow keys move from number to number, Home goes
  back to the first number, and each number is read out with whether it is prime (and its smallest factor when
  it is not). Settings are native controls; the summary under the picture is text.
- Nothing moves in the lesson, so reduced motion needs no change. No storage, no network.
- Showcase (the landing hero, ADR 0093): with `init.showcase` the shell hides the card and the controls, the page
  becomes inert, and the spiral winds out from the centre over a few seconds, number by number, then waits for the
  page's next `goToStep` (`diagonals`, `primes`). Under reduced motion it is one still frame. `posters/showcase.webp`
  is rendered with `?ulams-poster&ulams-showcase#diagonals`.

```bash
node demo-content/scripts/pack.mjs demo-content/ulam/spiral        # release/spiral-ulams.zip
node demo-content/scripts/posters.mjs demo-content/ulam/spiral     # the four posters
yarn workspace @ulams/demo-content test:e2e ulam-spiral
```
