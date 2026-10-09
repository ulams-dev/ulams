---
title: 6. Orbits in 3D and a moving Sun
summary: Tilted orbits, spinning bodies, a wobbling pole and helices through space.
duration: 45 min
---

::: interactive title="Tilts, spins and the North Star" start=into-3d end=polaris duration="9 min"
The Moon's orbit is tilted 5.1° to the Earth's {{src:G-02}}, and every planet's orbit is inclined to the ecliptic. The Earth spins once in 23 h 56 min about an axis tilted 23.4°, and that tilt gives the seasons {{src:G-01}}. Polaris, about 430 light-years away, stays put all year {{src:G-43}}.

**Try this.** Rotate the view into 3D. Which is tilted more, the Moon's orbit or the Earth's axis?
:::

::: interactive title="The Sun moves too" start=sun-moving end=sun-moving-moons duration="7 min"
The Sun carries the solar system around the galaxy at about 230 km/s {{src:G-34}}. A planet's real path through space never closes: it winds around the Sun's straight line as a helix, and the Moon winds around the Earth's coil.

**Try this.** Follow the Earth's trail. Is it a closed loop? What would a coil look like if the Sun's speed were half as fast?
:::

::: richtext title="Spin, tilt and helices" duration="10 min"
**Tilted orbits.** The orbits are not exactly flat. The Moon's orbit is inclined 5.145° to the Earth's orbital plane {{src:G-02}}, and each planet's orbit is inclined to the ecliptic by a few degrees {{src:G-13}}.

**Spin is separate from orbit.** Earth turns once every 23.9345 hours (one sidereal day) about an axis tilted 23.44° to its orbit {{src:G-01}}. The spin makes day and night; the tilt makes the seasons. Spin rates differ: Jupiter turns in under 10 hours {{src:G-08}} and Venus takes 243 days and turns backwards {{src:G-06}}.

**Why Polaris stands still.** Two reasons: the Earth's axis keeps pointing the same way as the Earth moves around the Sun, and Polaris is about 430 light-years away {{src:G-43}}, so the whole width of the Earth's orbit shifts it far less than the eye can notice. The aim is not frozen, though: the Sun and the Moon tug on the Earth's equatorial bulge and make the axis wobble like a slow top once every 26,000 years or so (the literature gives about 25,772) {{src:G-44}}.

**A moving frame.** We draw orbits as closed loops relative to the Sun. The Sun itself moves around the Milky Way at about 230 km/s, with a lap of about 230 million years {{src:G-34}}. In space, the planets trace helices around the Sun's path. The same law F = G·m₁·m₂ / r² still bends the path at every instant.
:::

::: layout title="The pole star over 26,000 years" duration="5 min" sources=G-44,G-43
{
  "document": [
    {"component": "Timeline", "props": {
      "title": "Axial precession",
      "intro": "The pole of the sky traces a circle once every 26,000 years or so.",
      "items": [
        {"label": "Today", "title": "Polaris is the North Star", "text": "About 430 light-years away; the axis points within a degree of it."},
        {"label": "About 6,500 years", "title": "A quarter of the way round", "text": "The axis has swung a quarter of the way round its circle of the sky."},
        {"label": "About 13,000 years", "title": "Vega is near the pole", "text": "Half a cycle: NASA gives Vega as the pole star in about 13,000 years."},
        {"label": "About 26,000 years", "title": "Back to Polaris", "text": "One full cycle of the wobble caused by the Sun's and the Moon's pull on the Earth's equatorial bulge."}
      ]
    }}
  ],
  "fallback": "## The pole star over 26,000 years\n\nToday Polaris; in about 13,000 years Vega is near the pole; after about 26,000 years the axis is back to Polaris."
}
:::

::: quiz title="Quiz: orbits in 3D" duration="8 min" pass=60
::Seasons:: What gives the Earth its seasons? {=The tilt of its axis, about 23.4 degrees ~Its changing distance from the Sun ~The Moon's pull ~Its spin once every day}

::Why Polaris stays put:: Polaris appears fixed all year because the Earth's axis keeps pointing the same way and Polaris is very far away. {T}

::Precession:: About how many years does one wobble of the Earth's axis take? {=About 26,000 ~About 26 ~About 2,600 ~About 260,000}

::The Sun's speed:: About how fast does the Sun carry the solar system around the galaxy? {=About 230 km/s ~About 30 km/s ~About 2,300 km/s ~About 0.46 km/s}

::A closed loop:: In space the Earth's path around the Sun, once the Sun's own motion is counted, is a closed ellipse. {F}
:::
