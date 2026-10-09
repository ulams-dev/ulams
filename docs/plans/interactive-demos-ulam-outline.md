# Ulam course: outline (research step M9a)

Course: **"Stanisław Ulam and the Lwów School of Mathematics"**, tenant `ulam` ("The Scottish
Book"), English, free, about 3.5 hours. Companion to `docs/plans/interactive-demos.md` (sections
6.3, 6.4, 7.3 and 7.4) and to the fact sheet `docs/plans/interactive-demos-ulam-facts.md`.

This outline is the brief for M9b. Whoever writes the lessons (a person or a Sonnet agent) works
only from it and the fact sheet:

- Every sentence of lesson text, every card and every quiz answer rests on a fact id from the sheet
  (`1.4`, `3b/153`, `C5` …) and cites its source numbers as `[n]`. The seeder appends the "Sources"
  list from `demo-content/ulam/sources.json`.
- Only facts with status ✅, or ⚠ in the "course wording" of section 0, may be used. (2nd) facts may
  appear in text with their hedge, never in quizzes. ✗ facts never appear.
- Quotes: only those marked `"…"` in the sheet, verbatim.
- Quizzes are written below in GIFT. A `// facts:` comment above each question names the fact ids
  and sources; keep it when the question is moved into the PHP lesson table. Quiz settings follow
  plan 6.4 (module quizzes: `max_attempts` 3, pass about 60 %, `weight` 1; final test: 14
  questions, `weight` 10, pass about 70 %, `max_attempts` 2, `max_execution_time` 20).

## Summary

| Module | Lessons | Interactive | Learning element | Quiz |
|---|---|---|---|---|
| 1. Lwów | 1.1 A boy from Lemberg · 1.2 Kuratowski's student | `lwow-map` (`lwow`) | Timeline | 5 |
| 2. The Lwów School and the Scottish Café | 2.1 The Lwów School · 2.2 The café and the book · 2.3 Famous problems | `scottish-book` (`intro`, then the problems) | FlipCards (the people) | 7 |
| 3. America and the war | 3.1 Princeton and Harvard · 3.2 August 1939 · 3.3 Madison and the family's fate | `lwow-map` (`princeton` … `los-alamos`) | Callout (why sources differ) | 5 |
| 4. Monte Carlo | 4.1 From solitaire to the ENIAC · 4.2 Estimate π yourself | `monte-carlo` (all steps) | PracticeActivity | 5 |
| 5. Los Alamos, briefly: the Teller–Ulam design | 5.1 The 1951 idea | none | Callout | 3 |
| 6. Patterns: the spiral and cellular automata | 6.1 The prime spiral · 6.2 Grids that grow | `spiral`, `automaton` | Steps (draw a spiral by hand) | 5 |
| 7. Fermi–Pasta–Ulam–Tsingou | 7.1 The first numerical experiment | `automaton` (the "computer experiment" idea only) | Timeline | 4 |
| 8. Legacy and the name | 8.1 Other ideas that carry his name · 8.2 Why "ulams" | `lwow-map` (`boulder`, `santa-fe`) | FlipCards | 4 |
| Final test | — | — | — | 14 |

**8 modules, 16 lessons, 38 module-quiz questions and a 14-question final test (52 questions).**

---

## Module 1. Lwów

### Lesson 1.1 A boy from Lemberg

- **Objectives.** Place Ulam's birth in time and place, and name today's city. Describe his family
  in one sentence.
- **Key facts.** 1.1 [1][5][29], 1.2 [1].
- **Items.**
  1. Text (about 250 words): Lemberg/Lwów/Lviv and its three names; the Ulam family (1.2). Image:
     Ulam's Los Alamos badge photo (section 10, "Use") as the course portrait, with its credit line.
  2. Interactive: `lwow-map`, step `lwow` (`display: inline`). The schematic inset shows the
     university, the Polytechnic and the café building at 27 Shevchenko Avenue (3.3). Caption: "No
     historical borders are drawn."

### Lesson 1.2 Kuratowski's student

- **Objectives.** Explain how a first-year engineering student became a mathematician. State when
  and where Ulam got his doctorate.
- **Key facts.** 1.3, 1.4, 1.4a [1][28]; C2.
- **Items.**
  1. Text (about 300 words): engineering at the Polytechnic (1927), Kuratowski's set theory course,
     the first two papers (1929), the 1930 measure theory result that strengthened Banach's (1.4a),
     the 1933 doctorate under Kuratowski. Ulam's "six hours during a single night" claim may be
     told only as his own claim reported by MacTutor.
  2. Layout, **Timeline**: 1909 born (1.1) → 1927 enters the Polytechnic (1.3) → 1929 first papers
     (1.3) → 1930 improves Banach's result (1.4a) → 1933 doctorate (1.4) → 1935 invitation to
     Princeton (1.5).

### Quiz 1

```gift
// facts: 1.1 [1][5]
::M1-Q1::In which city was Ulam born in 1909? {
  =Lwów (Lemberg), today Lviv
  ~Kraków
  ~Warsaw
  ~Vienna
}

// facts: 1.1 [1][5][29] (year only; see C1)
::M1-Q2::In which year was Stanisław Ulam born? {#1909:0}

// facts: 1.3 [1]
::M1-Q3::Whose set theory course drew Ulam into research mathematics in his first year at the Lwów Polytechnic? {
  =Kazimierz Kuratowski
  ~Stefan Banach
  ~Hugo Steinhaus
  ~John von Neumann
}

// facts: 1.3 [1]
::M1-Q4::Ulam first enrolled at the Lwów Polytechnic to study electrical engineering. {T}

// facts: 1.4 [1][28]
::M1-Q5::In which year did Ulam receive his doctorate from the Lwów Polytechnic? {
  =1933
  ~1927
  ~1929
  ~1936
}
```

---

## Module 2. The Lwów School and the Scottish Café

### Lesson 2.1 The Lwów School

- **Objectives.** Describe the Lwów School in one sentence. Name four of its members and one thing
  each is known for.
- **Key facts.** 2.1 [7], 2.2 [35], 2.3–2.6 [9][10], 2.7 [11], 2.8 [10], 2.9 [7], 2.10 [34],
  2.11 [33]; C15.
- **Items.**
  1. Text (about 300 words): MacTutor's "about a dozen mathematicians" (2.1); Steinhaus overhearing
     "Lebesgue measure" in 1916 (2.3); *Studia Mathematica* (2.4); Banach spaces (2.5).
  2. Layout, **FlipCards** (drawn portraits, no photos):
     - Stefan Banach (1892–1945): defined Banach spaces in his 1920 dissertation; co-founded *Studia
       Mathematica*; fed lice for typhus research during the occupation (2.5, 2.4, 2.6).
     - Hugo Steinhaus (1887–1972): "discovered" Banach in 1916; wrote the last problem in the book;
       started the *New Scottish Book* in Wrocław in 1946 (2.3, 3.4, 3.10).
     - Stanisław Mazur (1905–1981): functional analysis and infinite games; 24 problems of his own;
       the goose (2.9, 3.5, 3b/153).
     - Juliusz Schauder (b. 1899): murdered during the German occupation; "where and when is not
       known" (2.7).
     - Mark Kac: PhD under Steinhaus (1937), left for the US in 1938 (2.11).
     - Kazimierz Kuratowski: Ulam's teacher at the Polytechnic, moved to Warsaw in 1934 (2.10,
       C15).
     - Stanisław Ulam: the youngest regular until Kac arrived (1.1, 2.11).

### Lesson 2.2 The café and the book

- **Objectives.** Tell the story of the Scottish Book from 1935 to 1941. Explain how prizes worked.
  Say what is uncertain about the notebook and its survival.
- **Key facts.** 3.1–3.12 [12][33][10][35][54]; C3, C4, C9, C11.
- **Items.**
  1. Text (about 400 words): the café near the university (3.3); writing on marble tables and the
     notebook kept by the head waiter, with C3's wording; problem 1 on 17 July 1935 (3.1); the
     prizes (C9, table 3b; no brandy); the Soviet occupation and the visitors' problems (3.11); the
     last entry on 31 May 1941 (3.4); Mazur's plan to bury it (3.7, told as a plan); C4's wording
     on survival; Ulam's 1957 translation (3.8); Mauldin's editions (3.9); the 2014 copy in Lviv
     (3.12). Image: the café building (Rbrechko, CC BY-SA 4.0) with its credit line.
  2. Interactive: `scottish-book`, step `intro`.

### Lesson 2.3 Famous problems

- **Objectives.** Match well-known problems to the people who posed them. Retell the goose story.
  Explain why some problems took decades.
- **Key facts.** Table 3b (problems 1, 19, 38, 43, 59, 152, 153, 193; 77(a) and 184 only once their
  summaries are added from Mauldin) [33][16][7][38][12].
- **Items.**
  1. Interactive: `scottish-book`, one step per problem in table 3b, in number order. Each card
     shows number, poser, date (where the table has one), the plain-language question, prize and
     outcome, with source ids. The "guess the outcome" choice per card sends `score` (`on_score`,
     pass 60 %).
  2. Text (about 200 words): problem 153 (6 November 1936, the live goose, Enflo in 1972, the goose
     handed over in Warsaw), using C12's wording.

### Quiz 2

```gift
// facts: 3b/1, 3b/19, 3b/153, 3b/193 [33][7][12]
::M2-Q1::Match each Scottish Book problem to the person who posed it. {
  =Problem 1 -> Stefan Banach
  =Problem 19 (the floating body) -> Stanisław Ulam
  =Problem 153 (the goose problem) -> Stanisław Mazur
  =Problem 193 (the last entry) -> Hugo Steinhaus
}

// facts: 3b/153 [7]
::M2-Q2::Mazur offered a live goose as the prize for solving Problem 153. {T}

// facts: 3b/153 [7][38]
::M2-Q3::Who solved Problem 153, and when? {
  =Per Enflo, in 1972
  ~Stefan Banach, in 1936
  ~Hugo Steinhaus, in 1946
  ~Stanisław Ulam, in 1957
}

// facts: 3.4 [12][35]
::M2-Q4::How many problems does the Scottish Book contain? {#193:0}

// facts: 2.4 [9][10]
::M2-Q5::Which journal did Banach and Steinhaus found in 1929? {
  =Studia Mathematica
  ~Fundamenta Mathematicae
  ~Acta Mathematica
  ~Annals of Mathematics
}

// facts: 3b/19 [16][33]
::M2-Q6::Ulam's Problem 19 asked whether only a sphere can float in water in every position. The answer turned out to be yes. {F}

// facts: 2.3 [10]
::M2-Q7::In 1916 Steinhaus overheard two young men in a Kraków park and introduced himself. Which words caught his attention? {
  ="Lebesgue measure"
  ~"Banach space"
  ~"prime number"
  ~"Monte Carlo"
}
```

---

## Module 3. America and the war

### Lesson 3.1 Princeton and Harvard

- **Objectives.** Explain how Ulam came to the United States and what the Society of Fellows was.
- **Key facts.** 1.5 [1][12], 1.6a [1]; C8.
- **Items.**
  1. Text (about 250 words): von Neumann's invitation (1935), arrival in New York in January 1936,
     the Harvard Society of Fellows, summers in Lwów until 1939, the Harvard lectureship 1939–40.
  2. Interactive: `lwow-map`, steps `princeton` and `harvard` (one range, `on_range_end`).

### Lesson 3.2 August 1939

- **Objectives.** Describe Ulam's last weeks in Poland and the conversation with Mazur about the
  book.
- **Key facts.** 1.6 [1], 3.7 [12]; C7.
- **Items.**
  1. Text (about 200 words): C7's wording; Mazur's words about the war and the Scottish Book, as
     quoted in Ulam's preface (3.7).
  2. Layout, **Callout** ("Why sources differ"): Ulam wrote from memory 18 years later; MacTutor's
     authors checked passenger lists. Examples: the ship and port in 1939 (C7), the day of birth
     (C1), the Wisconsin and Los Alamos years (C5, C6). The point for learners: memoirs and
     reference works disagree, and the course says so instead of picking silently.

### Lesson 3.3 Madison and the family's fate

- **Objectives.** Place the Wisconsin years and his marriage. State what happened to his family in
  Lwów, in MacTutor's words.
- **Key facts.** 1.7 [1][4][30], 1.8 [1][30], 1.11 [1]; C5, C6.
- **Items.**
  1. Text (about 300 words): Madison from 1940 (C5's wording), marriage on 19 August 1941,
     citizenship in 1943, the invitation to Los Alamos and the arrival on 4 February 1944, Claire's
     birth (1.8). Then 1.11 as written, with MacTutor's sentence verbatim and nothing added.
  2. Interactive: `lwow-map`, steps `madison` and `los-alamos`.

### Quiz 3

```gift
// facts: 1.5 [1]
::M3-Q1::Who invited Ulam to the Institute for Advanced Study in Princeton in 1935? {
  =John von Neumann
  ~Enrico Fermi
  ~Edward Teller
  ~Albert Einstein
}

// facts: 1.5 [1]
::M3-Q2::Which Harvard institution did Ulam join after his first visit to Princeton? {
  =The Society of Fellows
  ~The Harvard Computation Laboratory
  ~The Department of Physics
  ~The Radiation Laboratory
}

// facts: 1.6 [1]
::M3-Q3::Ulam left Poland for the United States in August 1939, a few weeks before the war began. {T}

// facts: 1.7 [1][4] (place only; see C5)
::M3-Q4::In which city did Ulam teach in the early 1940s and marry Françoise Aron? {
  =Madison, Wisconsin
  ~Princeton, New Jersey
  ~Chicago, Illinois
  ~Boulder, Colorado
}

// facts: 1.7 [1][4]
::M3-Q5::Ulam became a United States citizen in 1943. {T}
```

---

## Module 4. Monte Carlo

### Lesson 4.1 From solitaire to the ENIAC

- **Objectives.** Retell how the Monte Carlo idea started. Name the people and the machine
  involved. Explain where the name comes from.
- **Key facts.** 1.8a [1][18][31], 4.1–4.7 [17][18][31][39]; C13, C14.
- **Items.**
  1. Text (about 400 words): the 1946 illness (1.8a); Ulam's own words about solitaire (4.1, quote);
     the Canfield question (4.2); von Neumann's 1947 letter to Richtmyer (4.3); the first ENIAC runs
     in April–May 1948 (4.4); Metropolis and the name (4.5, quote; the uncle stays unnamed);
     Fermi's earlier sampling and the FERMIAC (4.6); the 1949 paper (4.7). Image: the FERMIAC in
     the Bradbury Science Museum (Mark Pellegrini, CC BY-SA 1.0) with its credit line.
  2. Interactive: `monte-carlo`, step `idea`.

### Lesson 4.2 Estimate π yourself

- **Objectives.** Estimate π from random points. Explain why the error shrinks slowly as the number
  of points grows.
- **Key facts.** 4.5, 4.7 [18][39] for the history. The mathematics (π ≈ 4 × share of points inside
  the quarter circle; the error shrinks roughly like 1/√N) is a derivation, not a historical claim,
  and needs no citation.
- **Items.**
  1. Interactive: `monte-carlo`, steps `throw`, `converge`, `error` (complete at 10 000 points).
  2. Layout, **PracticeActivity** "Estimate the error after N throws" (every scaffolding slot
     filled, as plan 7.4 requires):
     - Intro: the ratio of areas (quarter circle to square) is π/4.
     - Toolbox: the formula π̂ = 4k/N, the absolute error |π̂ − π|.
     - Challenges: (1) 79 of 100 points fall inside; estimate π (3.16). (2) 7 850 of 10 000; estimate
       π (3.14). (3) Run the interactive with 100, then 10 000 points and compare the errors.
     - Hints: multiply the share by 4; compare errors on the log-log chart.
     - Feedback: errors around 0.1 at N = 100 and around 0.01 at N = 10 000 are typical, because
       a hundred times more points gives about ten times less error.
     - Worked solution for each challenge.

### Quiz 4

```gift
// facts: 4.2 [17]
::M4-Q1::Which card game set Ulam thinking about the Monte Carlo method in 1946? {
  =Canfield solitaire
  ~Poker
  ~Bridge
  ~Blackjack
}

// facts: derivation (lesson 4.2); no historical claim
::M4-Q2::In a simulation, 7850 of 10000 random points in the unit square fall inside the quarter circle. Estimate π to two decimal places. {#3.14:0.01}

// facts: 4.5 [18][31]
::M4-Q3::Who suggested the name "Monte Carlo" for the method? {
  =Nicholas Metropolis
  ~John von Neumann
  ~Enrico Fermi
  ~Robert Richtmyer
}

// facts: 4.4 [31]
::M4-Q4::On which computer did the first Monte Carlo calculations run, in 1948? {
  =ENIAC
  ~MANIAC I
  ~UNIVAC I
  ~Harvard Mark I
}

// facts: 4.6 [18][31]
::M4-Q5::Fermi's analogue "Monte Carlo trolley" was later called the FERMIAC. {T}
```

---

## Module 5. Los Alamos, briefly: the Teller–Ulam design

### Lesson 5.1 The 1951 idea

- **Objectives.** State who proposed what in early 1951, the date of the joint report and of the
  first test. Explain that credit was disputed. (ADR 0089 rule 5: factual, brief, no technical
  detail beyond the sheet.)
- **Key facts.** 5.1–5.4 [19][1][32].
- **Items.**
  1. Text (at most 200 words): 5.1 to 5.4 in that order. Image: the c. 1945 LANL portrait of Ulam
     with its credit line and the LANL notice.
  2. Layout, **Callout** ("Credit"): Ford's characterisation of the dispute, attributed to Ford
     (5.4). No opinion of our own.

### Quiz 5

```gift
// facts: 5.3 [32]
::M5-Q1::When was the first test of the Teller–Ulam design, the "Mike" shot? {
  =November 1952
  ~July 1945
  ~March 1951
  ~August 1949
}

// facts: 5.2 [19]
::M5-Q2::What is LAMS-1225? {
  =The joint report by Teller and Ulam dated 9 March 1951
  ~The first Monte Carlo program for the ENIAC
  ~The report on the Fermi–Pasta–Ulam–Tsingou experiment
  ~The report proposing Project Orion
}

// facts: 5.4 [19]
::M5-Q3::According to the physicist Kenneth Ford, credit for the design was disputed between Teller and Ulam for decades. {T}
```

---

## Module 6. Patterns: the spiral and cellular automata

### Lesson 6.1 The prime spiral

- **Objectives.** Build an Ulam spiral. Describe what Ulam noticed and how it became known.
  Connect the lines of primes to quadratic polynomials.
- **Key facts.** 6.1 [41][42], 6.2 [40], 6.3 [41][42], 6.4 [41]; 6.2a and 6.3a only with their
  (2nd) hedge or not at all.
- **Items.**
  1. Text (about 250 words): Gardner's account of the doodle (6.1, quotes attributed to Gardner);
     the 1964 *Monthly* paper (6.2); Gardner's March 1964 column (6.3); Euler's n² + n + 41 (6.4).
  2. Layout, **Steps** "Draw a spiral by hand": (1) grid paper, write 1 in the centre; (2) move one
     step right and write 2; (3) turn counterclockwise and keep going (Gardner's direction, 6.1);
     (4) circle 2, 3, 5, 7, 11, 13 …; (5) look for diagonals. A drawn figure of 1–49, or our own
     render from the `spiral` interactive.
  3. Interactive: `spiral`, steps `grid`, `primes`, `diagonals`, `explore`.

### Lesson 6.2 Grids that grow

- **Objectives.** Explain what a cellular automaton is. Name Ulam's part in von Neumann's
  self-reproducing automaton. Run Ulam's growth rule and an elementary rule.
- **Key facts.** 7.1 [22], 7.2 [43][44][45], 7.3 [44], 7.4 [22].
- **Items.**
  1. Text (about 250 words): 7.1 to 7.4. Rules 30, 90 and 110 are shown as illustrations, not as
     history.
  2. Interactive: `automaton`, steps `ulam-growth` (cited to 7.3 [44], so it is no longer labelled
     "illustration only"), `rule30`, `rule110`, `life`.

### Quiz 6

```gift
// facts: 6.1 [41][42]
::M6-Q1::According to Martin Gardner, how did Ulam first come upon the prime spiral? {
  =He doodled a numbered grid during a long talk at a scientific meeting
  ~He printed a table of primes on the ENIAC
  ~He found it in the Scottish Book
  ~He drew it while recovering in hospital in 1946
}

// facts: 6.1, 6.4 [41]
::M6-Q2::In the Ulam spiral, prime numbers tend to crowd along certain diagonal lines. {T}

// facts: 6.3 [41][42]
::M6-Q3::Which publication made the spiral widely known in March 1964? {
  =Martin Gardner's Mathematical Games column in Scientific American
  ~The journal Studia Mathematica
  ~Ulam's memoir Adventures of a Mathematician
  ~The Los Alamos Science special issue
}

// facts: 7.1 [22]
::M6-Q4::How many states per cell did von Neumann's self-reproducing automaton use? {#29:0}

// facts: 7.1 [22]
::M6-Q5::Von Neumann built his model of self-reproduction on a discrete grid following a suggestion of Ulam's. {T}
```

---

## Module 7. Fermi–Pasta–Ulam–Tsingou

### Lesson 7.1 The first numerical experiment

- **Objectives.** Describe what Fermi, Pasta, Ulam and Tsingou set out to test and what surprised
  them. Explain Mary Tsingou's role and why her name was added in 2008.
- **Key facts.** 8.1–8.7 [24][60][46].
- **Items.**
  1. Text (about 350 words): 8.1 to 8.7. The quote in 8.4 is Dauxois's wording.
  2. Interactive: `automaton`, step `rule30`, framed only as "computers used for experiments, not
     just sums" (no FPUT simulation in v1, per the plan).
  3. Layout, **Timeline**: November 1954 Fermi dies (8.5) → May 1955 report LA-1940 (8.1) → 1965
     Zabusky and Kruskal (8.6) → 2008 Dauxois proposes "FPUT" (8.7). This replaces the plan's
     "1953–1955" start, which has no source.

### Quiz 7

```gift
// facts: 8.2, 8.3, 8.6, 8.7 [24][46]
::M7-Q1::Match each person to their part in the story. {
  =Mary Tsingou -> programmed the computation on the MANIAC I
  =Zabusky and Kruskal -> linked the recurrence to solitons in 1965
  =Thierry Dauxois -> proposed adding Tsingou's name in 2008
  =Fermi, Pasta and Ulam -> wrote the 1955 report LA-1940
}

// facts: 8.4 [24]
::M7-Q2::In the experiment the energy spread evenly over all the modes, as the scientists expected. {F}

// facts: 8.3 [24]
::M7-Q3::On which computer did Mary Tsingou run the calculation? {
  =MANIAC I
  ~ENIAC
  ~UNIVAC I
  ~IBM 701
}

// facts: 8.1 [24]
::M7-Q4::In which year is the Los Alamos report LA-1940, "Studies of Nonlinear Problems", dated? {#1955:0}
```

---

## Module 8. Legacy and the name

### Lesson 8.1 Other ideas that carry his name

- **Objectives.** State the Borsuk–Ulam theorem in plain words. Name two more ideas linked to Ulam.
  Place his later career.
- **Key facts.** 9.1 [36], 9.2 (text only, "named after Hyers and Ulam" [25]), 9.3 [1][48], 9.5
  [33], 1.9 [1][5][49], 1.10 [1][4][5], 1.13 [1].
- **Items.**
  1. Text (about 300 words): Colorado from 1965 and Florida 1974–84 (1.9); his books (1.13);
     Borsuk–Ulam with Borsuk's footnote (9.1); Hyers–Ulam stability (name only); Orion (9.3);
     Problem 38 and random graphs (9.5); death in Santa Fe (1.10). The "singularity" passage is left
     out (C16).
  2. Layout, **FlipCards**: Borsuk–Ulam (front: "two opposite points, one value"; back: 9.1); Monte
     Carlo (4.1, 4.7); the Ulam spiral (6.2); Fermi–Pasta–Ulam–Tsingou (8.7); Project Orion (9.3);
     the Scottish Book (3.1, 3.8).
  3. Interactive: `lwow-map`, steps `boulder` and `santa-fe`.

### Lesson 8.2 Why "ulams"

- **Objectives.** Explain why the product is named after Ulam and what the name does and does not
  claim.
- **Key facts.** The product name sentence (fact sheet, end of section 10); 3.1, 3.8 [12].
- **Items.**
  1. Text (about 150 words): the tribute sentence verbatim. Then one paragraph, clearly labelled as
     the product's own view rather than history: the Scottish Book was a shared record of open
     questions, each tied to the person who asked it; ULAMS tries to keep courses tied to their
     sources in the same spirit. No claim about Ulam's views of education or software.

### Quiz 8

```gift
// facts: 9.1 [36]
::M8-Q1::Borsuk's 1933 paper says that the theorem now called the Borsuk–Ulam theorem was first stated as a conjecture by Ulam. {T}

// facts: 9.3 [1][48]
::M8-Q2::What did Ulam and C. J. Everett propose in the Los Alamos report that led to Project Orion? {
  =Driving a spacecraft with a series of nuclear explosions behind it
  ~A rocket engine burning liquid hydrogen
  ~A solar sail pushed by sunlight
  ~An ion drive powered by a reactor
}

// facts: product name sentence
::M8-Q3::The ULAMS project is endorsed by Stanisław Ulam's estate. {F}

// facts: 1.10 [1][4][5]
::M8-Q4::Where did Ulam die in 1984? {
  =Santa Fe, New Mexico
  ~Boulder, Colorado
  ~Gainesville, Florida
  ~Lviv, Ukraine
}
```

---

## Final test (14 questions)

```gift
// facts: 1.1 [1][5][29]
::F-Q1::Ulam was born in 1909 in a city that is today called Lviv. What was it called in Polish? {
  =Lwów
  ~Kraków
  ~Wrocław
  ~Gdańsk
}

// facts: 1.4 [1][28][33] (see C2; Stożek is never offered as a wrong answer)
::F-Q2::Under whom did Ulam write his doctoral thesis in 1933? {
  =Kazimierz Kuratowski
  ~Hugo Steinhaus
  ~John von Neumann
  ~Stanisław Mazur
}

// facts: 2.5, 3.4, 3b/153 [9][12][7][38]
::F-Q3::Match each person to what they are remembered for. {
  =Stefan Banach -> defined the spaces now named after him in 1920
  =Hugo Steinhaus -> wrote the last problem in the Scottish Book
  =Stanisław Mazur -> offered a live goose for Problem 153
  =Per Enflo -> solved Problem 153 in 1972
}

// facts: 3.1, 3.3 [12][33][54]
::F-Q4::The Scottish Book was kept at a café in Lwów, and its first problem was entered in 1935. {T}

// facts: 3.4 [12][35]
::F-Q5::How many problems were written in the Scottish Book between 1935 and 1941? {#193:0}

// facts: 1.8, 1.8b [1][30]
::F-Q6::Where did Ulam work on the Manhattan Project and, later, on the hydrogen bomb? {
  =Los Alamos
  ~Oak Ridge
  ~Chicago
  ~Princeton
}

// facts: 4.1 [17][1]
::F-Q7::What was Ulam doing when the Monte Carlo idea first came to him in 1946? {
  =Recovering from an illness and playing solitaire
  ~Programming the ENIAC
  ~Gambling in Monte Carlo
  ~Writing the Scottish Book translation
}

// facts: derivation (lesson 4.2)
::F-Q8::A Monte Carlo run puts 3130 of 4000 random points inside the quarter circle. Estimate π to two decimal places. {#3.13:0.01}

// facts: 5.3 [32]
::F-Q9::On which date was the "Mike" test, the first test of the Teller–Ulam design? {
  =1 November 1952
  ~16 July 1945
  ~9 March 1951
  ~29 August 1949
}

// facts: 6.2 [40]
::F-Q10::The Ulam spiral was described in a 1964 American Mathematical Monthly paper by Stein, Ulam and Wells. {T}

// facts: 7.3 [44]
::F-Q11::In the Schrandt–Ulam growth rule, a cell that has turned ON stays ON. {T}

// facts: 8.3 [24]
::F-Q12::Who programmed the Fermi–Pasta–Ulam–Tsingou computation? {
  =Mary Tsingou
  ~Klara von Neumann
  ~Nicholas Metropolis
  ~John Pasta
}

// facts: 8.4 [24]
::F-Q13::What surprised Fermi, Pasta, Ulam and Tsingou? {
  =After a longer run almost all the energy came back to the starting mode
  ~The computer gave a different answer every time
  ~The energy spread evenly over all modes at once
  ~The chain of masses broke apart
}

// facts: 9.1 [36]
::F-Q14::By the Borsuk–Ulam theorem, any continuous map from a sphere to the plane sends some pair of opposite points to the same point. {T}
```

Note on F-Q12: "Klara von Neumann" is a distractor only; the sheet makes no claim about her, and
M9b may swap her for another name if a reviewer finds it misleading.

---

## Changes against plan section 7.3 and 6.3

These follow from the research; M9b applies them unless the owner objects in #151.

1. **Module 1 quiz** may now ask about Kuratowski (C2 is resolved), but never about the day of
   birth.
2. **Module 2 FlipCards** add Kac and Kuratowski, and Kuratowski is a teacher, not a café regular
   (C15).
3. **Module 7 Timeline** starts at November 1954 (Fermi's death), not "1953–1955".
4. **`automaton` `ulam-growth`** implements the Schrandt–Ulam rule from OEIS A170896 and is cited
   to fact 7.3, no longer "illustration only".
5. **`scottish-book`** gets eight cleared problems (1, 19, 38, 43, 59, 152, 153, 193) instead of
   three; 77(a) and 184 are added once their summaries come from Mauldin.
6. **`lwow-map`** (new, optional): a `los-angeles` step for the 1945–46 USC year (1.8a). Without it,
   the text mentions USC and the map skips it.
7. **Images**: the badge photo and the c. 1945 portrait (both PD-LosAlamos), the FERMIAC museum
   photo (CC BY-SA 1.0) and the café building (CC BY-SA 4.0) are used; "Ulam holding the FERMIAC"
   and both Scottish Book page photos are dropped (fact sheet section 10).
8. **Wine and beer prizes** are no longer banned (C9); brandy stays out.
