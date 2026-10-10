---
title: 4. Monte Carlo
summary: A game of solitaire, an early computer and a method that estimates answers by chance. Then throw the points yourself.
duration: 45 min
---

::: richtext title="4.1 From solitaire to the ENIAC" duration="12 min" intro="How the Monte Carlo idea started, who named it, and what a FERMIAC is."
In 1946 Ulam was ill. He had been appointed at the University of Southern California, fell ill with encephalitis and had emergency brain surgery, and then returned to Los Alamos {{src:U-01,U-18,U-31}}. Later he described what came next in his own words: "The first thoughts and attempts I made to practice [the Monte Carlo method] were suggested by a question which occurred to me in 1946 as I was convalescing from an illness and playing solitaires" {{src:U-17,U-01}}.

The question was: what are the chances that a Canfield solitaire laid out with 52 cards will come out successfully? Later in 1946 Ulam described the idea to von Neumann {{src:U-17}}. In March 1947 von Neumann wrote to Robert Richtmyer outlining a neutron-diffusion calculation for the ENIAC. The Los Alamos laboratory calls it "the first formulation of a Monte Carlo computation for an electronic computer" {{src:U-17,U-31}}. The first Monte Carlo calculations ran on the ENIAC in April and May 1948 {{src:U-31}}.

**The name.** Nicholas Metropolis suggested it, "a suggestion not unrelated to the fact that Stan had an uncle who would borrow money from relatives because he 'just had to go to Monte Carlo.'" {{src:U-18,U-31}}

**Fermi's trolley.** Enrico Fermi had used statistical sampling in Rome in the early 1930s without publishing it. At Los Alamos he had an analogue "Monte Carlo trolley" built, later called the FERMIAC {{src:U-18,U-31}}.

![The FERMIAC in the Bradbury Science Museum, Los Alamos]({{asset:fermiac.webp}})

*The FERMIAC, Bradbury Science Museum. Photo: Mark Pellegrini, CC BY-SA 1.0, via Wikimedia Commons.*

The method was published by Metropolis and Ulam: "The Monte Carlo Method", *Journal of the American Statistical Association* 44 (247), 1949, pages 335 to 341 {{src:U-39}}.
:::

::: interactive title="4.1 The idea: ask the dice" package=ulam-monte-carlo start=idea end=idea display=inline height=640 duration="4 min"
A square one unit wide, a quarter circle of radius 1 inside it, and a lot of random points. The share of points that land inside the circle estimates π/4.

**Try this.** Why would the share be about 78.5%? Think of the areas of the circle and the square.
:::

::: richtext title="4.2 Estimate π yourself" duration="6 min" intro="The mathematics of the experiment in a paragraph, and what to look for."
The idea needs no history to check. A quarter circle of radius 1 in a square of side 1 covers π/4 of the square. If k of N random points fall inside the circle, then 4k/N is an estimate of π. The error of such an estimate shrinks roughly like 1/√N, which means a hundred times as many points buy one more correct digit. That is slow, and the interactive below lets you see it. The history of the method and its name are in the previous lesson {{src:U-18,U-39}}.
:::

::: interactive title="4.2 Throw the points" package=ulam-monte-carlo start=throw end=error completion=on_complete display=inline height=700 duration="10 min"
Throw points one at a time, then a hundred, then ten thousand. Watch the estimate and the error. The topic is complete when you have thrown 10,000 points.

**Try this.** Run 100 points and note the error, then run 10,000 and compare. Change the seed and repeat.
:::

::: layout title="Practice: estimate the error after N throws" duration="10 min" sources=U-39,U-18
{
  "document": [
    {"component": "PracticeActivity", "props": {
      "title": "Estimate the error after N throws",
      "intro": "Turn counts of points into an estimate of π, then see how the error behaves when N grows a hundred times. Hints are optional and revealed one at a time.",
      "toolbox": [
        {"label": "The ratio of areas", "text": "A quarter circle of radius 1 inside a unit square covers π/4 of it."},
        {"label": "The estimate", "text": "If k of N points fall inside, π ≈ 4k/N."},
        {"label": "The error", "text": "The absolute error is |estimate − π|, with π ≈ 3.14159."}
      ],
      "challenges": [
        {"id": "hundred", "level": 1, "prompt": "In a run of 100 random points, 79 fall inside the quarter circle. What is the estimate of π?",
         "hints": [
           {"tier": "nudge", "text": "What share of the points is inside?"},
           {"tier": "pointer", "text": "Multiply that share by 4."}
         ],
         "options": [
           {"label": "3.16", "correct": true, "feedback": "Right: 4 × 79/100 = 3.16."},
           {"label": "0.79", "correct": false, "feedback": "That is the share of points inside, which estimates π/4. Multiply it by 4."},
           {"label": "3.95", "correct": false, "feedback": "That is 5 × 79/100. The factor is 4, because the quarter circle covers π/4 of the square."}
         ],
         "workedSolution": "The share of points inside is 79/100 = 0.79, which estimates π/4. So π is about 4 × 0.79 = 3.16. The absolute error is about 0.02."},
        {"id": "ten-thousand", "level": 2, "prompt": "In a run of 10,000 points, 7,850 fall inside. What is the estimate of π?",
         "hints": [{"tier": "pointer", "text": "The same formula, with the new counts."}],
         "options": [
           {"label": "3.14", "correct": true, "feedback": "Right: 4 × 7,850/10,000 = 3.14."},
           {"label": "3.16", "correct": false, "feedback": "That is the estimate of the run with 100 points."},
           {"label": "0.785", "correct": false, "feedback": "That is the share inside, which estimates π/4. Multiply by 4."}
         ],
         "workedSolution": "The share is 7,850/10,000 = 0.785, so π is about 4 × 0.785 = 3.14."},
        {"id": "compare", "level": 3, "prompt": "In the interactive above, run 100 points and note the error, then run 10,000 points and note the error. About how many times smaller is the second error?",
         "hints": [{"tier": "near_solution", "text": "The error shrinks like one over the square root of N. What is the square root of 100?"}],
         "options": [
           {"label": "About ten times smaller", "correct": true, "feedback": "Right: a hundred times more points give about √100 = 10 times less error. Single runs vary, so your own ratio can be a little different."},
           {"label": "About a hundred times smaller", "correct": false, "feedback": "That would be an error that shrinks like 1/N. For random points it shrinks like 1/√N, the slower rule."},
           {"label": "The same size", "correct": false, "feedback": "More points do help, only slowly: the error shrinks by the square root of the ratio of the counts."}
         ],
         "workedSolution": "Typical errors are around 0.1 at N = 100 and around 0.01 at N = 10,000. A hundred times more points give about ten times less error, because the error shrinks like 1/√N and √100 = 10."}
      ]
    }}
  ],
  "fallback": "## Estimate the error after N throws\n\n1. 79 of 100 points inside: π is about 4 × 79/100 = 3.16.\n2. 7,850 of 10,000 inside: π is about 4 × 0.785 = 3.14.\n3. Run the interactive with 100 and with 10,000 points: the error is about ten times smaller, because it shrinks like 1/√N."
}
:::

::: quiz title="Quiz: Monte Carlo" duration="6 min" pass=60
// facts: 4.2 [U-17]
::M4-Q1::Which card game set Ulam thinking about the Monte Carlo method in 1946? {
  =Canfield solitaire
  ~Poker
  ~Bridge
  ~Blackjack
}

// facts: derivation (lesson 4.2); no historical claim
::M4-Q2::In a simulation, 7850 of 10000 random points in the unit square fall inside the quarter circle. Estimate π to two decimal places. {#3.14:0.01}

// facts: 4.5 [U-18][U-31]
::M4-Q3::Who suggested the name "Monte Carlo" for the method? {
  =Nicholas Metropolis
  ~John von Neumann
  ~Enrico Fermi
  ~Robert Richtmyer
}

// facts: 4.4 [U-31]
::M4-Q4::On which computer did the first Monte Carlo calculations run, in 1948? {
  =ENIAC
  ~MANIAC I
  ~UNIVAC I
  ~Harvard Mark I
}

// facts: 4.6 [U-18][U-31]
::M4-Q5::Fermi's analogue "Monte Carlo trolley" was later called the FERMIAC. {T}
:::
