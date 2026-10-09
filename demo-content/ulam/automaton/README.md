# automaton: cellular automata

Five steps: `rule30`, `rule90` and `rule110` (an elementary automaton on a ring of 201 cells, 100 generations from
one cell; the rule number, 0 to 255, is the table of answers for the eight neighbourhoods), `life` (Conway's
Game of Life on a 64 x 40 torus with five presets, editable) and `ulam-growth` (an off cell turns on when exactly
one of its four neighbours is on, up to 60 generations). It completes once three different steps were visited.
The last step says it is an illustration of a pattern grown from one cell, not a reconstruction of any
historical experiment: no history is claimed until the fact sheet is cleared (M9a).

- `logic.js`: the pure logic. Unit tests: `demo-content/tests/unit/ulam-automaton.test.mjs` (the rule tables, rule
  90 draws Sierpinski's triangle and has 2^(ones in g) live cells at generation g, a blinker oscillates, a glider
  moves one cell diagonally every four generations, the growth rule counts 1, 5, 9, 21, 25, 37, 49, 85 and
  (4^(k+1) - 1)/3 after 2^k - 1 generations).
- Controls: a rule input, a pattern select, Step, Play and Pause (a toggle), Run to the end, Reset. In Life the
  grid is a focusable region: arrow keys move a cursor, Space toggles a cell, each move is announced.
- Reduced motion (the system setting or `init.reducedMotion`): the Play button is removed, Step advances one
  generation and Run to the end draws the finished picture at once. `pause` and `resume` from the lesson page hold
  and release a running animation.

```bash
node demo-content/scripts/pack.mjs demo-content/ulam/automaton
node demo-content/scripts/posters.mjs demo-content/ulam/automaton
yarn workspace @ulams/demo-content test:e2e ulam-automaton
```
