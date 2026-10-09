---
title: 5. The Earth and the Moon
summary: A moon that never falls, a shared centre, tides twice a day and a lumpy Earth.
duration: 45 min
---

::: interactive title="An orbit within an orbit" start=earth-moon end=moon-no-fall duration="7 min"
The Moon holds about 1.2% of the Earth's mass and orbits at 384,400 km every 27.3 days {{src:G-02}}. It moves sideways at about 1.02 km/s, so it keeps missing the Earth: the same balance as the Earth and the Sun, one level down.

**Try this.** Find the arrows on the Moon. Which one is gravity and which is velocity? Why does the Moon always show us the same face?
:::

::: interactive title="Tides and the shape of the Earth" start=tides end=geoid duration="7 min"
The Moon pulls the near side of the ocean harder than the Earth's centre and the centre harder than the far side. That difference, the tidal force, raises two bulges {{src:G-39}}. The Earth is not a smooth ball either: its geoid, the shape of the sea surface under gravity alone, spans about 190 metres {{src:G-38}}.

**Try this.** The relief is exaggerated about 10,000 times so you can see it. Where is it a low and where a high?
:::

::: richtext title="Two bodies, one dance" duration="10 min"
**A shared centre.** The Earth and the Moon both orbit their common centre of mass, the barycentre. Because the Earth has about 81 times the Moon's mass {{src:G-14}}, the barycentre sits about 4,671 km from the Earth's centre, inside the Earth {{src:G-02}}.

**Why the Moon does not fall.** Earth's gravity pulls the Moon with about 2 × 10²⁰ newtons. The Moon moves sideways at about 1.02 km/s, which you can check: its path is a circle of radius 384,400 km traversed in 27.32 days, so v = 2π × 384,400 km / (27.32 × 86,400 s) ≈ 1.02 km/s {{src:G-02}}.

**Tidal locking.** The Earth's pull raised a bulge on the Moon and slowly braked its spin until one rotation took exactly as long as one orbit, 27.3 days. That is why the same side faces us. The far side is not the dark side: over a month it gets as much sunlight as the near side. It was unseen until Luna 3 photographed it on 7 October 1959 {{src:G-49}}.

**Why two tides a day.** Gravity weakens with distance, so the Moon pulls the near-side water more than the Earth's centre and the centre more than the far-side water. The difference stretches the oceans into two bulges, one facing the Moon and one opposite. The Earth rotates through both each day, so most coasts see two high tides and two low tides {{src:G-39}}.

**A lumpy Earth.** Mountains, trenches and denser regions of the mantle pull a little harder or softer, so gravity differs from place to place. The geoid ranges from about +85 m near Iceland to −106 m south of India {{src:G-38}}.
:::

::: layout title="The barycentre and the tides" duration="6 min" sources=G-02,G-39,G-14
{
  "document": [
    {"component": "Callout", "props": {"tone": "key", "title": "Tides come from a difference", "text": "It is not that the Moon pulls the near side and pushes the far side. The Moon pulls the near water more than the Earth's centre, and the centre more than the far water. The difference stretches the sea into two bulges."}},
    {"component": "FlipCards", "props": {
      "title": "Earth and Moon vocabulary",
      "cards": [
        {"front": "Barycentre", "back": "The common centre of mass of two bodies. The Earth-Moon barycentre lies about 4,671 km from the Earth's centre, inside the Earth."},
        {"front": "Tidal locking", "back": "A body whose rotation period equals its orbital period, so it always shows the same face. The Moon: 27.3 days for both."},
        {"front": "Why two tides a day?", "back": "Two bulges, one facing the Moon and one opposite, and the Earth rotates through both."},
        {"front": "Geoid", "back": "The shape the sea surface would take under gravity alone. It spans about 190 metres, from about +85 m near Iceland to -106 m south of India."}
      ]
    }}
  ],
  "fallback": "## The barycentre and the tides\n\nTides come from a difference in the Moon's pull across the Earth, which raises two bulges. The Earth-Moon barycentre lies about 4,671 km from the Earth's centre. The Moon is tidally locked: 27.3 days for both rotation and orbit. The geoid spans about 190 metres."
}
:::

::: quiz title="Quiz: the Earth and the Moon" duration="8 min" pass=60
::Why two tides?:: Why do most coasts have two high tides a day? {=The Earth rotates through two bulges, one toward and one away from the Moon ~The Moon pulls the sea up and the Sun pushes it down ~The Moon circles the Earth twice a day ~The sea sloshes back after each high tide}

::The Moon's speed:: The Moon's orbit is a circle of radius 384,400 km traversed in 27.32 days. What is its speed in km/s? {#1.02:0.03}

::Far side:: The far side of the Moon is the dark side: it never gets sunlight. {F}

::Barycentre:: Where is the Earth-Moon barycentre? {=Inside the Earth, about 4,671 km from its centre ~Halfway between the Earth and the Moon ~At the centre of the Moon ~Outside the Earth, about 100,000 km away}
:::
