---
title: 4. Cosmic velocities
summary: How fast a rocket must go to orbit the Earth, to leave it, and to leave the solar system.
duration: 40 min
---

::: interactive title="Falling back, orbiting, escaping" start=rocket-too-slow end=second-cosmic duration="7 min"
Launch a rocket too slowly and it arcs back down. At the first cosmic velocity, about 7.9 km/s, it falls around the Earth instead of into it: a circular orbit {{src:G-46}}. At the second, about 11.2 km/s (exactly √2 times the first), it escapes the Earth for good {{src:G-01}}.

**Try this.** Watch the three launches in turn. What changes between the second and the third: the direction or the speed?
:::

::: interactive title="Leaving the solar system" start=third-cosmic end=third-cosmic duration="4 min"
Even after escaping the Earth a probe is still bound to the Sun. The third cosmic velocity, about 16.7 km/s measured from the Earth, is what it takes to leave the solar system as well, the path Voyager is on.

**Try this.** Follow the probe past the orbits of the planets. Roughly how many planets does it cross before leaving the picture?
:::

::: richtext title="Three speeds" duration="10 min"
**First cosmic velocity: 7.9 km/s.** A body that moves sideways at v = √(GM/r) keeps a circular orbit of radius r. At the Earth's surface radius, 6,371 km, that is about 7.9 km/s {{src:G-46}}. It is the speed of every satellite in low orbit.

**Second cosmic velocity: 11.2 km/s.** Escape velocity from a distance r is √(2GM/r), exactly √2 times the circular speed. The NASA fact sheet gives 11.186 km/s for the Earth {{src:G-01}}, and 7.9 × √2 = 11.2 km/s.

**Third cosmic velocity: 16.7 km/s.** To leave the solar system from the Earth's orbit a probe must also escape the Sun. The Earth already moves at about 29.8 km/s, and the escape speed from the Sun at the Earth's distance is about 42.1 km/s {{src:G-14}}, so the probe needs about 12.3 km/s more than the Earth's own motion, once it is far from the Earth. Launching from the Earth's surface it also has to climb out of the Earth's pull (11.2 km/s), and the two combine as the square root of 11.2² + 12.3², about 16.7 km/s. This is our calculation from NASA data {{src:G-01}}.

**Reading the numbers.** None of these is a "speed limit": a rocket can fire its engine for a long time and gain speed slowly, so the speeds describe a coasting body, not a launch.
:::

::: layout title="Three speeds at a glance" duration="4 min" sources=G-46,G-01
{
  "document": [
    {"component": "ComparisonTable", "props": {
      "title": "The cosmic velocities",
      "intro": "Three speeds from the Earth, and what each one does.",
      "caption": "First, second and third cosmic velocity, with the result of each",
      "asOf": "NASA data",
      "columns": [{"label": "First"}, {"label": "Second"}, {"label": "Third"}],
      "rows": [
        {"label": "Speed", "cells": [{"value": "7.9 km/s"}, {"value": "11.2 km/s", "note": "√2 × the first"}, {"value": "16.7 km/s"}]},
        {"label": "What it does", "cells": [{"value": "Circular low orbit"}, {"value": "Escapes the Earth"}, {"value": "Leaves the solar system"}]},
        {"label": "Example", "cells": [{"value": "A satellite in low orbit"}, {"value": "A probe to the Moon or Mars"}, {"value": "Voyager"}]}
      ]
    }}
  ],
  "fallback": "## The cosmic velocities\n\n- First: 7.9 km/s, a circular low orbit.\n- Second: 11.2 km/s (√2 times the first), escape from the Earth.\n- Third: 16.7 km/s, leaving the solar system."
}
:::

::: quiz title="Quiz: cosmic velocities" duration="7 min" pass=60
::Escape velocity:: The first cosmic velocity is 7.9 km/s and the second is exactly the square root of 2 times that. What is the second, in km/s? {#11.2:0.1}

::Match the speed:: Match each speed with what it gives a rocket at the Earth. {=7.9 km/s -> A circular low orbit =11.2 km/s -> Escape from the Earth =16.7 km/s -> Escape from the solar system}

::Too slow:: A rocket launched below orbital speed arcs back to the ground. {T}

::Which statement:: Which is true of escape velocity? {=It is √2 times the circular orbital speed ~It is twice the circular orbital speed ~It depends on the rocket's mass ~It is the same on the Moon as on the Earth}
:::
