# Interactive demo landings: Stitch prompts

These prompts were sent to the Stitch project "Wellms E-Learning Experience System"
(`9081291570384881657`, desktop) on 2026-10-09 for the three new demo academies in
`docs/plans/interactive-demos.md` (section 8). They are kept here so the screens can be regenerated.
Status per screen is in [`README.md`](README.md).

The landings are built from `@ulams/ui` catalogue components (`front/web/src/docs/<slug>.json`), not
from the Stitch HTML. Use the screens for layout, colour and type only.

## gravity-landing: Gravity Lab

Landing page for a free online course academy called "Gravity Lab" (learning platform demo), course
"How gravity shapes the solar system". Dark deep-space theme: near-black navy background (#05070F),
starfield, a large live 3D solar system render as the full-bleed hero background (Sun glow, orbit
rings drawn as thin cyan ellipses, planets as small lit spheres). Accent colour electric cyan #3DD6F5
with warm sun-gold #FFB547 for highlights. Typography: Space Grotesk headings, Inter body, JetBrains
Mono for numbers. Sections: 1) Hero: headline "Feel the pull of every orbit", subhead about 8 modules
built on a real-data 3D simulator, primary button "Start free course", secondary "Log in as demo
admin", a small badge "Free · Certificate · 8 modules · ~4 h". 2) "How it works": three cards —
"Explore the live simulation", "Read the short explanation", "Check yourself with a quiz". 3) Syllabus
as an orbit diagram: modules placed on concentric orbits — Kepler's laws, Newton's gravity, Orbits and
speed, Escape velocity, Tides and the Moon, Lagrange points, Resonances and gaps, Missions and
transfers; each with lesson count. 4) A lesson preview strip: a screenshot-like card with the 3D scene
behind a translucent text panel and a quiz card ("Which planet moves fastest at perihelion?")
overlaid. 5) Data you can trust: sources list (NASA JPL, NASA planetary fact sheets) with small
citation chips. 6) Certificate preview card with constellation line art. 7) Footer with licence note
"Simulation: GPL-3.0, open source". Accessible contrast, generous spacing, no stock photos.

## poland-landing: Poland, Measured / Polska w liczbach

Landing page for a free bilingual (English / Polish) online course academy "Poland, Measured / Polska
w liczbach" (learning platform demo). Cartographic theme: warm paper background (#F4EFE6), a
full-bleed pale world map in fine ink lines as the hero background with Poland highlighted in crimson
#C8102E, graticule lines, small map legend and scale bar as decoration. Accent crimson with deep ink
navy #1B2A3A for text, muted sea teal #5E8C8A for charts. Typography: a serif for headings (Fraunces
or Source Serif), IBM Plex Sans body, tabular numerals. EN | PL language toggle in the top bar.
Sections: 1) Hero: headline "Poland, measured: 35 years in data", subhead "A free course built on
public statistics, every number cited", buttons "Start free course" and "Log in as demo admin", badge
"Free · EN/PL · Certificate". 2) Three cards: "Map in the background", "Charts you can read", "Quizzes
on the numbers". 3) Syllabus laid out like an atlas table of contents with small chart thumbnails per
chapter: Economy, Incomes and prices, Health, Education, Infrastructure and transport, Energy,
Demography, Poland in Europe. 4) Lesson preview: map background with a translucent text card and a
line chart, a quiz card "By how much did GDP per capita grow?" with numeric answer. 5) "Sources"
section like footnotes with publisher names (Eurostat, GUS Statistics Poland, World Bank, OECD) as
citation chips. 6) Certificate preview with map-border ornament. Accessible contrast, crisp editorial
layout, no photos.

Note: the chapter list in this prompt predates the content plan. The course follows the app's
chapters (plan section 7.2): Energy, Prosperity, Security, Made here, Daily life, Mobility, Health,
People and Unfinished work. There is no Education chapter.

## ulam-landing: The Scottish Book

Landing page for a free online course academy "The Scottish Book" (learning platform demo), course
"Stanisław Ulam and the Lwów School of Mathematics". Mathematical archival notebook theme: cream
squared-paper background (#F7F3E8 with faint blue 5 mm grid), fountain-pen ink blue #1D3B8F text
accents, a muted marginal red #B23A2E rule line, archival sepia photo frames with paper-corner mounts.
Typography: EB Garamond or Cormorant headings, IBM Plex Sans body, IBM Plex Mono for formulas.
Sections: 1) Hero: an open notebook spread; left page headline "Problems worth a bottle of wine",
subhead "The mathematician behind Monte Carlo, the Ulam spiral and cellular automata, and the café
where his friends wrote problems in a notebook"; right page a live Ulam spiral of primes drawn as
small ink dots; buttons "Start free course" and "Log in as demo admin"; badge "Free · 7 modules ·
Certificate". 2) Five interactive cards with tiny previews: Ulam spiral generator, Monte Carlo π
estimator (dots in a square and quarter circle), cellular automaton grid, Scottish Book problem
explorer (handwritten-looking problem cards with a prize tag like "Prize: a live goose"), historical
map of Lwów. 3) Timeline module list as a horizontal hand-ruled timeline: Lwów, The Scottish Café,
Princeton and Harvard, Los Alamos, Monte Carlo, Spiral and automata, Fermi–Pasta–Ulam–Tsingou,
Legacy. 4) Lesson preview with a flip card and a quiz card. 5) Sources as numbered footnotes
(MacTutor, Los Alamos Science, Adventures of a Mathematician). 6) Certificate preview styled like a
page from the notebook with a stamp. Accessible contrast, no real photos (use placeholder archival
frames).

Note: the fonts in this prompt are only a design direction. The implementation uses fonts already
bundled or self-hosted (plan section 8.3).
