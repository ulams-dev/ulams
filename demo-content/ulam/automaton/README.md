# automaton: cellular automata

Five steps: `rule30`, `rule90` and `rule110` (an elementary automaton on a ring of 201 cells, 100 generations from
one cell; the rule number, 0 to 255, is the table of answers for the eight neighbourhoods), `life` (Conway's
Game of Life on a 64 x 40 torus with five presets, editable) and `ulam-growth` (the Schrandt-Ulam rule, OEIS A170896,
up to 60 generations). It completes once three different steps were visited.
The last step is the Schrandt-Ulam rule (fact 7.3 of the fact sheet, source OEIS A170896, which the unit test
reproduces for 66 generations); it claims no more history than that the rule bears Ulam's name.

- `logic.js`: the pure logic. Unit tests: `demo-content/tests/unit/ulam-automaton.test.mjs` (the rule tables, rule
  90 draws Sierpinski's triangle and has 2^(ones in g) live cells at generation g, a blinker oscillates, a glider
  moves one cell diagonally every four generations, the Schrandt-Ulam rule gives the 67 terms of A170896 and the first generations
  by hand).
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
