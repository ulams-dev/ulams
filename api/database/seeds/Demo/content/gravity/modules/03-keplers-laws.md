---
title: 3. Kepler's laws
summary: Ellipses, equal areas and the link between a planet's distance and its year.
duration: 45 min
---

::: interactive title="The whole system on real orbits" start=solar-system end=solar-system duration="6 min"
Every planet in the model moves on an orbit with the JPL elements: a size (semi-major axis a), a shape (eccentricity e) and a tilt {{src:G-13}}. Mercury, at 0.387 AU, circles the Sun in about 88 days. Neptune, at about 30 AU, takes 165 years.

**Try this.** Speed up time and compare the inner and outer planets. A planet near the Sun moves faster than one far away: write the pattern in one sentence.
:::

::: interactive title="Venus and Earth draw a rose" start=venus-rose end=venus-rose duration="5 min"
Venus orbits in 224.7 days and the Earth in 365.3 {{src:G-06}}. Thirteen Venus years are almost exactly eight Earth years, so every eight years the pair repeat their arrangement. Draw a line between them every few days and the near-repeats trace a five-petalled rose.

**Try this.** Count the petals. The rose is nearly perfect, not perfect: the pattern drifts by about two days per cycle.
:::

::: richtext title="Three laws from the sky" duration="12 min"
Johannes Kepler found three rules from Tycho Brahe's measurements of Mars. Newton later showed that they follow from the inverse-square law of gravity {{src:G-45}}.

**1. Ellipses.** Each planet moves on an ellipse with the Sun at one focus. The ellipse is described by its semi-major axis a, in astronomical units (AU), and its eccentricity e. For Earth e is about 0.017, almost a circle. For Mercury it is about 0.21 {{src:G-13}}.

**2. Equal areas.** A line from the Sun to a planet sweeps out equal areas in equal times. So a planet moves fastest at its closest point (perihelion) and slowest at its farthest (aphelion).

**3. Harmonic law.** The square of the orbital period P, in years, equals the cube of the semi-major axis a, in AU: P² = a³ {{src:G-45}}. Mars has a = 1.5237 AU, so P = 1.5237^1.5 = 1.88 years, which matches its observed 686.98 days {{src:G-07}}. Jupiter has a = 5.2034 AU {{src:G-08}} and so P = 11.87 years.

**Resonance and roses.** When two periods form a ratio of small whole numbers the same arrangement repeats. Venus (224.701 days) and Earth (365.256 days) are close to 13 : 8, which is why Venus returns to the same place in the evening sky about every eight years {{src:G-06}}.
:::

::: layout title="Kepler's three laws, step by step" duration="6 min" sources=G-45,G-13,G-07
{
  "document": [
    {"component": "Steps", "props": {
      "title": "Kepler's laws",
      "intro": "Three rules, each with a number to try.",
      "items": [
        {"icon": "planet", "title": "1. Ellipses", "text": "Orbits are ellipses with the Sun at one focus. Earth e = 0.017, Mercury e = 0.21."},
        {"icon": "clock", "title": "2. Equal areas", "text": "A planet sweeps equal areas in equal times, so it is fastest at perihelion."},
        {"icon": "sigma", "title": "3. P² = a³", "text": "Period in years squared equals semi-major axis in AU cubed. Mars: a = 1.5237 AU, P = 1.88 years."}
      ]
    }}
  ],
  "fallback": "## Kepler's laws\n\n1. Orbits are ellipses with the Sun at one focus.\n2. A planet sweeps equal areas in equal times.\n3. P² = a³ (years and AU). Mars: a = 1.5237 AU, P = 1.88 years."
}
:::

::: quiz title="Quiz: Kepler's laws" duration="8 min" pass=60
::Mars year:: Mars orbits at a semi-major axis of 1.524 AU. Use Kepler's third law (P² = a³) to find its orbital period in years. {#1.88:0.05}

::Jupiter year:: Jupiter orbits at 5.2034 AU. What is its period in years? {#11.87:0.1}

::Where is a planet fastest?:: Where on its orbit does a planet move fastest? {=Closest to the Sun (perihelion) ~Farthest from the Sun (aphelion) ~At the ends of the minor axis ~At the same speed everywhere}

::Shape:: Kepler's first law says orbits are perfect circles around the Sun. {F}

::Venus and Earth:: Venus orbits in 224.7 days and the Earth in 365.3 days. Thirteen Venus orbits are close to how many Earth orbits? {=8 ~5 ~12 ~13}
:::
