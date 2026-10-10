---
title: 6. Patterns: the spiral and cellular automata
summary: The prime spiral, Ulam's growth rule and the grids that grow by simple rules. Build them yourself.
duration: 50 min
---

::: richtext title="6.1 The prime spiral" duration="8 min" intro="How a doodle in a boring talk became a picture of the primes."
In Martin Gardner's account, at a scientific meeting in the autumn of 1963, listening to a "long and very boring paper", Ulam numbered a grid in a counterclockwise spiral from 1 at the centre, circled the primes, and saw them "crowd into straight lines" {{src:U-41,U-42}}.

A short paper by M. L. Stein, S. M. Ulam and M. B. Wells, "A Visual Display of Some Properties of the Distribution of Primes", *American Mathematical Monthly* 71 (5), May 1964, pages 516 to 520, put the picture on paper {{src:U-40}}. Gardner's column "The remarkable lore of the prime numbers" in *Scientific American* of March 1964 made the spiral widely known {{src:U-41,U-42}}.

The lines of primes in the spiral correspond to quadratic polynomials, such as Euler's n² + n + 41 {{src:U-41}}. The interactive below starts the spiral at 41 so that you can see that one line.
:::

::: interactive title="6.1 Build the spiral" package=ulam-spiral start=grid end=explore completion=on_complete display=inline height=700 duration="12 min"
Write the numbers on a spiral, mark the primes, highlight the polynomial n² + n + 41, and then explore with your own settings.

**Try this.** In the last step, start the spiral at 41. Then try other starting numbers. Which starting numbers make the diagonals clearest?
:::

::: layout title="Steps: draw a spiral by hand" duration="8 min" sources=U-41,U-42
{
  "document": [
    {"component": "Steps", "props": {
      "title": "Draw a spiral by hand",
      "intro": "You need squared paper and a pencil. This is how Gardner describes the doodle.",
      "items": [
        {"icon": "book", "title": "Put 1 in the centre", "text": "Choose a square near the middle of the page and write 1 in it."},
        {"icon": "arrow", "title": "Write 2 next to it", "text": "Move one square to the right and write 2."},
        {"icon": "sync", "title": "Turn counterclockwise and keep going", "text": "Write 3, 4, 5, … winding outwards. Gardner's account turns counterclockwise."},
        {"icon": "check", "title": "Circle the primes", "text": "Circle 2, 3, 5, 7, 11, 13 and every other prime you reach."},
        {"icon": "eye", "title": "Look for diagonals", "text": "Do the circles line up? Compare your page with the interactive above."}
      ]
    }}
  ],
  "fallback": "## Draw a spiral by hand\n\n1. Put 1 in the centre of a sheet of squared paper.\n2. Move one square to the right and write 2.\n3. Turn counterclockwise and keep writing the numbers, winding outwards.\n4. Circle the primes: 2, 3, 5, 7, 11, 13 and so on.\n5. Look for diagonals: do the circles line up?"
}
:::

::: richtext title="6.2 Grids that grow" duration="10 min" intro="Cellular automata, von Neumann's self-reproducing automaton and Ulam's growth rule."
A cellular automaton is a grid of cells, each in one of a few states, that all update at once by a rule that looks only at a cell's neighbours. Following a suggestion of Ulam's, von Neumann built his model of self-reproduction as a discrete two-dimensional system; his automaton used 29 states per cell {{src:U-22}}.

Ulam studied how figures grow on a lattice under simple rules, in a paper of 1962: S. M. Ulam, "On some mathematical problems connected with patterns of growth of figures", *Proceedings of Symposia in Applied Mathematics* 14 (American Mathematical Society), pages 215 to 224 {{src:U-43,U-44,U-45}}. The Schrandt–Ulam rule is one of the rules of this kind: cells, once on, stay on, and a cell turns on when exactly one edge-neighbour turned on in the previous generation, with two exclusion conditions. From one cell the number of cells on runs 1, 5, 9, 13, 25, 29, 41 and so on {{src:U-44}}.

John Conway introduced the Game of Life in 1970 {{src:U-22}}. Rules 30, 90 and 110 in the interactive are illustrations of how simple rules grow patterns; they are not part of this history.
:::

::: interactive title="6.2 Rules that grow" package=ulam-automaton start=rule30 end=ulam-growth completion=on_complete display=inline height=720 duration="12 min"
Run an elementary rule from a single cell, play the Game of Life, and then grow a pattern with the Schrandt–Ulam rule. Visit three different steps to complete this topic.

**Try this.** In the last step press Step and compare the number of cells with 1, 5, 9, 13, 25, 29, 41. Do they match?
:::

::: quiz title="Quiz: the spiral and growing grids" duration="6 min" pass=60
// facts: 6.1 [U-41][U-42]
::M6-Q1::According to Martin Gardner, how did Ulam first come upon the prime spiral? {
  =He doodled a numbered grid during a long talk at a scientific meeting
  ~He printed a table of primes on the ENIAC
  ~He found it in the Scottish Book
  ~He drew it while recovering in hospital in 1946
}

// facts: 6.1, 6.4 [U-41]
::M6-Q2::In the Ulam spiral, prime numbers tend to crowd along certain diagonal lines. {T}

// facts: 6.3 [U-41][U-42]
::M6-Q3::Which publication made the spiral widely known in March 1964? {
  =Martin Gardner's Mathematical Games column in Scientific American
  ~The journal Studia Mathematica
  ~Ulam's memoir Adventures of a Mathematician
  ~The Los Alamos Science special issue
}

// facts: 7.1 [U-22]
::M6-Q4::How many states per cell did von Neumann's self-reproducing automaton use? {#29:0}

// facts: 7.1 [U-22]
::M6-Q5::Von Neumann built his model of self-reproduction on a discrete grid following a suggestion of Ulam's. {T}
:::
