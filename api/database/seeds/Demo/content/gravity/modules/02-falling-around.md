---
title: 2. Falling around: orbits
summary: Inertia, falling and missing, and what happens when a body goes too slowly or too fast.
duration: 45 min
---

::: interactive title="Inertia, and falling and missing" start=inertia end=why-no-fall duration="7 min"
Switch the Sun off and the Earth would coast in a straight line at about 29.8 km/s {{src:G-01}}. Switch it on and gravity bends that straight line into a closed loop. An orbit is a body falling toward the Sun all the time and always missing it, because it also moves sideways.

**Try this.** Compare the green arrow (velocity) with the red one (gravity). Which one points along the path and which one points at the Sun?
:::

::: interactive title="Too slow, too fast" start=too-slow end=too-fast duration="6 min"
Orbiting is a balance and speed is what holds a body up. Too little sideways speed and the path curves too hard: the body falls in. Past escape velocity the path still bends but never closes: the body leaves and does not come back.

**Try this.** Between "falls in" and "escapes" lies a narrow band of speeds. Watch which of the two the dashed line of the middle step looks like.
:::

::: richtext title="An orbit is falling that keeps missing" duration="10 min"
**Newton's first law.** A body that is not pushed or pulled moves in a straight line at constant speed. With the Sun removed, the Earth would drift in a straight line at about 29.8 km/s, its mean orbital speed {{src:G-01}}. Something has to bend the path.

**Gravity bends it.** The Sun pulls the Earth toward itself the whole time. The Earth is also moving sideways, so every moment it falls toward the Sun and its sideways speed carries it past. The result is a closed loop. In one sentence: an orbit is falling, continuously, and always missing.

**Speed decides the path.** For a given distance, there is a speed at which a body keeps a circular orbit. Slower, and gravity wins: the body plunges inward. Faster, and the orbit stretches into a longer ellipse. At escape velocity, the square root of two times the circular speed, the path no longer closes at all {{src:G-46}}.

**It holds at every scale.** The same balance keeps the Moon around the Earth, satellites around the Earth and the Earth around the Sun. The Moon moves sideways at about 1.02 km/s and has been falling around us and missing for billions of years {{src:G-02}}.
:::

::: layout title="Practice: predict the path" duration="10 min" sources=G-46,G-01
{
  "document": [
    {"component": "PracticeActivity", "props": {
      "title": "Predict the path",
      "intro": "For each case, say what the Earth does next. Hints are optional and revealed one at a time.",
      "toolbox": [
        {"label": "Rule 1", "text": "With no force a body moves in a straight line at constant speed."},
        {"label": "Rule 2", "text": "Gravity pulls toward the Sun. The sideways speed decides whether the body misses it."},
        {"label": "Rule 3", "text": "Escape velocity is the square root of 2 times the circular orbital speed."}
      ],
      "challenges": [
        {"id": "no-sun", "level": 1, "prompt": "The Sun suddenly disappears. What does the Earth do?",
         "hints": [
           {"tier": "nudge", "text": "What force is left acting on the Earth?"},
           {"tier": "pointer", "text": "No force means constant velocity."}
         ],
         "options": [
           {"label": "It moves off in a straight line at about 29.8 km/s", "correct": true, "feedback": "Right: Newton's first law, no force and no change in velocity."},
           {"label": "It stops", "correct": false, "feedback": "A body only stops when a force acts. Motion does not need a force to continue."},
           {"label": "It keeps circling the empty point", "correct": false, "feedback": "Circling needs a force toward the centre, and the force is gone."}
         ],
         "workedSolution": "With no Sun there is no force to bend the path. By Newton's first law the Earth keeps its velocity, about 29.8 km/s, and moves in a straight line."},
        {"id": "too-slow", "level": 2, "prompt": "The Earth's sideways speed is cut to half. What happens?",
         "hints": [{"tier": "pointer", "text": "Below the circular speed gravity pulls harder than the sideways motion can carry the Earth past."}],
         "options": [
           {"label": "It falls toward the Sun along a path that curves inward", "correct": true, "feedback": "Right: too little sideways speed, so the orbit dips in toward the Sun."},
           {"label": "It stays on the same circle", "correct": false, "feedback": "The circle needs the circular speed. Half of it is not enough."},
           {"label": "It flies out of the solar system", "correct": false, "feedback": "Escaping needs more speed, not less."}
         ],
         "workedSolution": "The circular speed balances gravity at that distance. At half of it the Earth falls inward on an ellipse whose far point is the current distance, and in the course's model it plunges toward the Sun."},
        {"id": "too-fast", "level": 3, "prompt": "The Earth's speed is raised to 1.5 times the circular speed. Is that enough to escape? Use the factor from rule 3.",
         "hints": [{"tier": "near_solution", "text": "Escape needs about 1.41 times the circular speed."}],
         "options": [
           {"label": "Yes: 1.5 is more than 1.41, the path no longer closes", "correct": true, "feedback": "Right. The Earth would swing past the Sun once and leave."},
           {"label": "No: only a speed of 2 times works", "correct": false, "feedback": "The factor is the square root of 2, about 1.41, not 2."}
         ],
         "workedSolution": "Escape speed is the square root of 2, about 1.41, times the circular speed. 1.5 is above that, so the path is open: the Earth leaves and does not return."}
      ]
    }}
  ],
  "fallback": "## Practice: predict the path\n\n1. The Sun vanishes: the Earth moves in a straight line at about 29.8 km/s.\n2. Half the sideways speed: the Earth falls inward.\n3. 1.5 times the circular speed: more than the square root of 2, so the Earth escapes."
}
:::

::: quiz title="Quiz: orbits" duration="8 min" pass=60
::Speed and outcome:: Match each situation with what happens to the body. {=Sideways speed far too low -> It falls in =The circular orbital speed -> It keeps a circular orbit =A speed above escape velocity -> It leaves and does not return}

::Newton's first law:: A body with no force on it slows down and stops by itself. {F}

::Why the Earth does not fall in:: Which statement explains why the Earth does not crash into the Sun? {=It moves sideways fast enough to keep missing it ~Gravity does not reach that far ~The Sun pulls and the Earth pushes back equally ~The Earth is too light to be pulled}

::Straight line:: With the Sun removed, the Earth would move at about 29.8 km/s in a straight line. {T}
:::
