---
title: 7. Health
summary: Life expectancy, road deaths and air quality, and how to compute a change from two numbers.
duration: 50 min
---

::: interactive title="Longer lives" start=c-health end=life duration="6 min"
In 2024 life expectancy reached 74.93 years for men and 82.26 for women, 8.7 and 7 years more than in 1990 {{src:PL-099}}. Healthy life years rose too, to 61.6 for men and 65.3 for women {{src:PL-101}}.

**Try this.** Read the slope chart from 1990 to 2024. Which line rose more in years, and why is life expectancy at birth not a forecast for one person?
:::

::: interactive title="Roads and air" start=roaddeaths end=air duration="8 min"
1,660 people died on Polish roads in 2025, against 3,026 in 2016 {{src:PL-103}}. In Kraków, at the Bujaka station, days over the PM10 limit fell from 78 in 2016 to 23 in 2024 {{src:PL-106}}.

**Try this.** Each dot is 20 lives. Count how many dots fade between 2016 and 2025 and check it against the two numbers.
:::

::: richtext title="Reading health numbers" duration="10 min"
**Period measures.** Life expectancy at birth is a period measure: it describes mortality in one year and is not a forecast for a person. Statistics Poland gives 74.93 years for men and 82.26 for women in 2024, 8.7 and 7 years more than in 1990 {{src:PL-099}}. Healthy life years, the years lived in good health, were 61.6 for men and 65.3 for women in 2024 {{src:PL-101}}.

**Change over time.** The Polish Police counted 1,660 road deaths in 2025, against 3,026 in 2016 and 1,896 a year earlier {{src:PL-103}}. The change from 3,026 to 1,660 is a fall of 1,366 people, which is 45.1% of 3,026: the headline "down 45%" is our calculation {{src:PL-104}}. The formula is (new minus old) divided by old, times 100.

**Count the right thing.** Fewer bad-air days is the goal for air quality, so the shorter bar is the good news. In Kraków, all eight monitoring stations met the annual PM10 limit in the last two years of the city's July 2025 report. At the Bujaka station days over the limit fell from 78 in 2016 to 23 in 2024, and in 2024 every benzo[a]pyrene station met its legal limit for the first time {{src:PL-105}}.
:::

::: layout title="Practice: read the chart" duration="12 min" sources=PL-103,PL-106,PL-099
{
  "document": [
    {"component": "PracticeActivity", "props": {
      "title": "Compute a change from two numbers",
      "intro": "Percentage change = (new minus old) divided by old, times 100. Use the cited values; hints are optional.",
      "toolbox": [
        {"label": "Formula", "text": "Change in per cent = (new - old) / old x 100."},
        {"label": "Values", "text": "Road deaths: 3,026 in 2016, 1,660 in 2025. PM10 days at Bujaka: 78 in 2016, 23 in 2024."}
      ],
      "challenges": [
        {"id": "road-deaths", "level": 1, "prompt": "Road deaths fell from 3,026 in 2016 to 1,660 in 2025. By about what per cent?",
         "hints": [
           {"tier": "nudge", "text": "First find how many fewer people died."},
           {"tier": "pointer", "text": "3,026 minus 1,660 is the fall. Divide it by the old value, 3,026."},
           {"tier": "near_solution", "text": "1,366 divided by 3,026 is about 0.45."}
         ],
         "options": [
           {"label": "About 45% lower", "correct": true, "feedback": "Right: 1,366 / 3,026 is 45.1%."},
           {"label": "About 82% lower", "correct": false, "feedback": "That divides the fall by the new value, 1,660. Divide by the old value."},
           {"label": "About 55% lower", "correct": false, "feedback": "That is the 2025 value as a share of 2016 (54.9%), not the fall."}
         ],
         "workedSolution": "(1,660 - 3,026) / 3,026 = -1,366 / 3,026 = -0.451, a fall of 45.1%."},
        {"id": "pm10", "level": 2, "prompt": "PM10 days over the limit at the Bujaka station fell from 78 in 2016 to 23 in 2024. By about what per cent?",
         "hints": [{"tier": "pointer", "text": "Use the same formula; the old value is 78."}],
         "options": [
           {"label": "About 70% lower", "correct": true, "feedback": "Right: 55 / 78 is 70.5%."},
           {"label": "About 30% lower", "correct": false, "feedback": "23 / 78 is 29.5%, the 2024 value as a share of 2016, not the fall."},
           {"label": "About 55% lower", "correct": false, "feedback": "55 is the number of days, not a per cent."}
         ],
         "workedSolution": "(23 - 78) / 78 = -55 / 78 = -0.705, a fall of 70.5%."},
        {"id": "own-question", "level": 3, "prompt": "A friend says: 'Life expectancy of men rose 8.7 years, so a man born today will live 8.7 years longer than in 1990.' Write down in one sentence what is wrong with that.",
         "workedSolution": "Life expectancy at birth is a period measure of mortality in one year (2024), not a forecast for a person. It says that if the mortality rates of 2024 stayed unchanged, a boy born then would live 74.93 years on average."}
      ]
    }}
  ],
  "fallback": "## Practice: compute a change from two numbers\n\nPercentage change = (new - old) / old x 100.\n\n1. Road deaths fell from 3,026 (2016) to 1,660 (2025): a fall of 45.1%.\n2. PM10 days at Bujaka fell from 78 (2016) to 23 (2024): a fall of 70.5%.\n3. Life expectancy at birth is a period measure, not a forecast for a person."
}
:::

::: quiz title="Health quiz" duration="8 min" pass=60
::Road deaths:: Road deaths fell from 3,026 in 2016 to 1,660 in 2025. What was the fall in per cent, to one decimal place? {#45.1:0.2}

::Bad-air days:: At the Bujaka station in Kraków, PM10 days over the limit fell from 78 in 2016 to 23 in 2024. What was the fall in per cent? {#70.5:0.5}

::Life expectancy:: Life expectancy at birth in 2024 was 82.26 years for women and 74.93 years for men. By how many years was women's higher? {#7.33:0.05}

::Period measure:: Life expectancy at birth is a forecast of how long a particular child will live. {F}

::Healthy life years:: In 2024 healthy life years for women were 65.3. For men they were {=61.6 ~74.9 ~82.3 ~8.7}
:::
