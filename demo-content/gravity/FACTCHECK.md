# Gravity demo: fact-check

Date: 2026-10-10. Scope: every numerical or factual claim in the English tour texts (`src/ui/tour.ts`, 44 steps), the constants in `src/data/constants.ts`, and the body data in `src/data/bodies.ts` that the tour displays. The check itself edited no source; the text fixes listed under Mismatches were applied afterwards.

## Method

- Each claim was compared with a primary reference opened during this check: NASA/JPL planetary fact sheets, JPL Standish elements, NASA Science and NASA history pages, ESA/ESO releases, LIGO, Planck, CODATA/NIST. Source ids (`G-nn`) refer to `sources.json`; the same ids can be cited in the course as [n].
- "calc" means the value was recomputed from the reference inputs (G-01, G-03, G-13, G-14) because no page states it. The inputs are cited next to it.
- Verdicts: **confirmed** (matches, or matches after the rounding the text announces), **rounded** (correct but the reference gives a different number of digits or a differently rounded figure; usable as is), **mismatch** (the app value differs from the reference beyond rounding, or the sentence says something the reference does not), **unverified** (no reference that was actually opened states it; see the note).
- Where the app and a reference disagree, the course uses the reference value listed under "Mismatches".
- Not reached: nobelprize.org (HTTP 403), ligo.caltech.edu (certificate error) and voyager.jpl.nasa.gov (redirects to a missing page). Claims that depended only on those pages are marked unverified rather than taken from search snippets.

Result: 148 claims checked: 91 confirmed, 24 rounded, 11 mismatch rows (grouped into 9 items under "Mismatches"), 22 unverified (no opened source states them; listed in the tables).

## 1. Gravity, orbits and cosmic velocities

| Step id | Claim as shown | Reference value | Src | Verdict |
|---|---|---|---|---|
| what-is-gravity | F = G m1 m2 / r^2 | Newton's law of gravitation | G-45 | confirmed |
| what-is-gravity | Sun-Earth force "about 3.5 x 10^22 N" | 3.54e22 N (calc: G, GM_Sun, Earth mass, 1 AU) | G-14, G-01 | confirmed |
| what-is-gravity | Sun "~333 000x heavier" than Earth | 332,900 (fact sheet), 332,946 (calc) | G-03 | rounded |
| birth-of-sun | Solar nebula collapse about 4.6 billion years ago | 4.6 billion years | G-33, G-34 | confirmed |
| birth-of-earth | Accretion of dust to planetesimals to planets | qualitative (no figure) | G-34 | unverified |
| inertia, why-no-fall | Earth speed "29.8 km/s" | 29.78 km/s mean orbital velocity | G-01 | confirmed |
| too-slow / too-fast | Below circular speed it falls in; above escape speed (sqrt 2 x) it leaves | qualitative; sqrt(2) relation stated in G-46 | G-46 | confirmed |
| rocket-too-slow | Too slow it arcs back | qualitative | G-46 | confirmed |
| first-cosmic | First cosmic velocity "about 7.9 km/s", low-orbit satellites | 7.9 km/s (7.91 calc from GM Earth, R = 6371 km) | G-46, G-14 | confirmed |
| second-cosmic | Second cosmic velocity "about 11.2 km/s", exactly sqrt 2 x first | 11.186 km/s; 7.9 x sqrt 2 = 11.2 | G-01, G-46 | confirmed |
| third-cosmic | Third cosmic velocity "about 16.7 km/s from Earth" | 16.65 km/s (calc: v_inf = (sqrt2 - 1) x 29.78 = 12.34; sqrt(12.34^2 + 11.186^2)). NASA pages opened name no "third cosmic velocity" | G-01, G-46 | confirmed (calc) |
| third-cosmic | "the path Voyager is on" | Voyager 1 and 2 leave the solar system | G-19 | confirmed |

## 2. Earth, Moon and rotation

| Step id | Claim as shown | Reference value | Src | Verdict |
|---|---|---|---|---|
| earth-moon | Moon "1.2% of Earth's mass" | mass ratio 0.0123 | G-02 | confirmed |
| earth-moon | Orbit "every 27.3 days at 384 400 km" | 27.3217 d; 384.4e3 km semimajor axis | G-02 | confirmed |
| earth-moon | Rotation 27.3 days, tidally locked | sidereal rotation 655.720 h = 27.32 d | G-02 | confirmed |
| earth-moon | Luna 3 photographed the far side in 1959 | 7 Oct 1959, 29 photographs, about 70% of far side | G-49 | confirmed |
| earth-moon | Far side gets as much sunlight over a month | follows from synchronous rotation; no page states it | none | unverified |
| moon-no-fall | Earth pulls the Moon with "about 2 x 10^20 N" | 1.98e20 N (calc: G, masses, 384,400 km) | G-01, G-02 | confirmed |
| moon-no-fall | Moon speed "1.02 km/s" | 1.022 km/s | G-02 | confirmed |
| moon-no-fall | Falling around us "for 4.5 billion years" | age of the Moon; no opened page states it | none | unverified |
| into-3d | Moon's path tilts "5.1°" | 5.145° | G-02 | confirmed |
| self-rotation | Earth turns once every "23 h 56 min" | 23.9345 h = 23 h 56 m 4 s | G-01 | confirmed |
| self-rotation | Axial tilt "23.4°" | 23.44° | G-01 | confirmed |
| self-rotation | Jupiter spins in "under 10 hours" | 9.925 h | G-08 | confirmed |
| self-rotation | Venus "243 days ... backwards" | -5832.6 h = -243.0 d, obliquity 177.36° | G-06 | confirmed |
| solar-system | "eight planets (plus Pluto)", J2000 orbits | Standish Table 1 elements for 8 planets (Pluto removed from that table) | G-13 | confirmed (8 planets); Pluto elements unverified here |

## 3. The Sun moves too

| Step id | Claim as shown | Reference value | Src | Verdict |
|---|---|---|---|---|
| sun-moving | Sun sweeps around the galaxy "at about 230 km/s" | 828,000 km/h = 230 km/s (Solar System: Facts); 720,000 km/h = 200 km/s (Sun: Facts) | G-34, G-33 | mismatch (NASA pages disagree) |
| sun-moving-vectors / -moons | Helix motion, nested orbits | geometry only | none | unverified |

## 4. Spheres of influence and Voyager

| Step id | Claim as shown | Reference value | Src | Verdict |
|---|---|---|---|---|
| sphere-of-influence | Earth's sphere of influence "≈924,000 km" | 924,600 km (calc: a (m/M)^(2/5), a = 1 AU, GM ratio from G-14); textbooks often quote 929,000 km | G-14, G-13 | rounded |
| sphere-of-influence | Moon "at 384,400 km" | 384.4e3 km | G-02 | confirmed |
| gravity-assist-1 | Voyager 1 launched September 1977 | 5 Sep 1977 | G-17 | confirmed |
| gravity-assist-1 | Jupiter 1979, Saturn 1980 | Jupiter 5 Mar 1979; Saturn 12 Nov 1980 | G-17 | confirmed |
| gravity-assist-1 | Titan pass bent it out of the planets' plane | after Saturn it moved north out of the ecliptic; Titan flyby requirements ruled out Uranus/Neptune | G-17 | confirmed |
| gravity-assist-2 | Voyager 2 launched August 1977 | 20 Aug 1977 | G-18 | confirmed |
| gravity-assist-2 | Alignment "once every ~175 years" | "once-every-175-year alignment" | G-20 | confirmed |
| gravity-assist-2 | Jupiter 1979, Saturn 1981, Uranus 1986, Neptune 1989 | 9 Jul 1979; 25 Aug 1981; 24 Jan 1986; 25 Aug 1989 | G-18 | confirmed |

## 5. Relativity

| Step id | Claim as shown | Reference value | Src | Verdict |
|---|---|---|---|---|
| spacetime | Einstein's general relativity (1915) | 1915 | G-32 | confirmed |
| spacetime | Newton is the weak-field, low-speed limit of GR | standard result; no opened page states it | none | unverified |
| mercury-precession | Shortfall "43 arcseconds per century" | 42.98 arcsec/century (GR 42.9815; observed unexplained 42.980 +/- 0.002) | G-31 | confirmed |
| mercury-precession | "unexplained for decades" | historical (Le Verrier 1859, Newcomb 1882); no opened page | none | unverified |
| lensing | Eddington measured the bend at the 1919 eclipse | NASA: Eddington's expedition found the predicted bending, with caveat that the 1919 equipment's sensitivity is debated; later measurements are more robust | G-32 | rounded |
| lensing | "measured exactly that bend", "world-famous overnight" | "exactly" overstates the 1919 accuracy (see Mismatches) | G-32 | mismatch (wording) |
| black-hole | Event horizon, accretion disk, photon ring; "millions of degrees" | qualitative; no figure on any opened page | none | unverified |
| gravitational-waves | LIGO 2015 detection | 14 Sep 2015 (announced 11 Feb 2016) | G-27, G-28 | confirmed |
| gravitational-waves | Black holes collided "1.3 billion years ago" | about 1.3 billion light-years | G-27 | confirmed |
| gravitational-waves | 4-kilometre arms | LIGO arm length; not stated on opened pages | none | unverified |
| gravitational-waves | Arms stretched by "less than a thousandth the width of a proton" | peak strain about 1e-21 gives ΔL = hL/2 ≈ 2e-18 m ≈ 1/850 proton width (calc); G-28 gives "ten thousand times smaller than a proton" for typical waves | G-28 | rounded |
| gravitational-waves | "A century after Einstein predicted them" | prediction 1916, detection 2015 | none | unverified |
| time-dilation | GPS clocks corrected "about 38 microseconds a day" | +45 (GR) - 7 (SR) = +38 microseconds/day | G-37 | confirmed |
| time-dilation | "drift kilometres off within hours" | about 10 km per day uncorrected (11.4 km/day calc), so about 1 km in 2 hours | G-37 | confirmed |

## 6. Tides, Lagrange points, resonance, exoplanets

| Step id | Claim as shown | Reference value | Src | Verdict |
|---|---|---|---|---|
| tides | Two bulges (near and far side), two high and two low tides per day | same | G-39 | confirmed |
| tides | Tidal locking of the Moon's spin | consistent with G-02 (rotation = orbit period) | G-02 | confirmed |
| lagrange | L1 and L2 "1.5 million km" from Earth | about 1.5 million km | G-22, G-23 | confirmed |
| lagrange | SOHO and DSCOVR at L1 | DSCOVR, WIND, SOHO, ACE at L1 | G-22 | confirmed |
| lagrange | Webb and Euclid at L2 | Webb, Euclid at L2 | G-22, G-24 | confirmed |
| lagrange | Probes orbit round the point, not sit on it | Webb orbit about 6 months; Euclid Lissajous/halo about 1e6 km | G-22, G-24 | confirmed |
| lagrange | L1-L3 unstable, thrust "every few weeks" | L1-L3 metastable, small periodic thrusts; the interval is not stated | G-22 | rounded |
| lagrange | L4 and L5 60° ahead/behind, stable | equilateral-triangle points, stable | G-22, G-23 | confirmed |
| lagrange | "thousands" of Trojans at Jupiter | swarms at L4/L5; no count on opened pages | none | unverified |
| lagrange | "two confirmed at Earth's L4" | two Earth Trojans confirmed (2010 TK7, 2020 XL5); opened pages do not say which point | G-26, G-53 | rounded (count confirmed, L4 unverified) |
| lagrange | ESA's Vigil "at L5 in the 2030s" | L5, launch planned 2031 | G-25 | confirmed |
| resonance | Io, Europa, Ganymede 1:2:4 | Ganymede 1 : Europa 2 : Io 4 | G-54 | confirmed |
| resonance | Kirkwood gaps, Saturn's rings | no opened page | none | unverified |
| exoplanet | Star and planet orbit the barycenter | definition | G-47 | confirmed |
| exoplanet | "Jupiter makes our own Sun loop by about its own radius" | Sun-Jupiter barycenter is just outside the Sun's surface: 1.07 solar radii (calc: 5.2034 AU / 1048, R = 695,700 km). Wording ("its") is ambiguous | G-47, G-08, G-03 | rounded |
| exoplanet | "thousands of distant worlds" | 6,000 confirmed planets (17 Sep 2025) | G-48 | confirmed |

## 7. The galaxy, dark matter, universe

| Step id | Claim as shown | Reference value | Src | Verdict |
|---|---|---|---|---|
| milky-way | "few hundred billion stars" | about 400 billion | G-50 | confirmed |
| milky-way | "two-thirds of the way out from the center" | ESA: about two-thirds, 28,000 ly; NASA: "about halfway" | G-50, G-51 | rounded (sources differ) |
| milky-way | Orbits at "roughly 230 km/s" | 230 km/s (G-34) / 200 km/s (G-33) | G-34, G-33 | mismatch (NASA pages disagree) |
| milky-way | One lap "about 230 million years" | about 230 million years | G-33, G-34 | confirmed |
| milky-way | "dinosaurs were just beginning" | earliest dinosaur fossils about 235 Ma | G-57 | confirmed |
| sagittarius-a | "mass of about four million Suns" | about 4 million solar masses | G-29 | confirmed |
| sagittarius-a | S2 "every sixteen years" | 16-year orbit | G-30 | confirmed |
| sagittarius-a | S2 reaches "3% of the speed of light" | about 7650 km/s = 2.55% of c | G-30 | mismatch |
| sagittarius-a | "Those orbits won a Nobel Prize" | 2020 prize (Genzel, Ghez); nobelprize.org returned 403; ESO release credits the same two teams for the orbits | G-29 | rounded (prize unverified) |
| dark-matter | Outer stars orbit as fast as inner ones (flat curve) | NASA page: Rubin found outer stars move fast enough to need extra mass; no "flat curve" wording | G-42 | rounded |
| dark-matter | Neptune crawls, Mercury races | 5.4 vs 47.4 km/s | G-04 | confirmed |
| dark-matter | Dark matter outweighs "all the stars five to one" | about 5.4 : 1 relative to all ordinary matter (27% vs 5%), not relative to stars alone | G-42, G-40 | mismatch |
| early-universe | "13.8 billion years" | 13.80 +/- 0.02 Gyr (Planck 2018 tables); WMAP-era NASA page says 13.7 +/- 0.2 | G-40, G-58 | confirmed |
| early-universe | Smooth to "one part in 100,000" | 1 part in 100,000 | G-56 | confirmed |
| early-universe | Hydrogen and helium; first stars after hundreds of millions of years | no opened page | none | unverified |

## 8. Earth's gravity, field and motion

| Step id | Claim as shown | Reference value | Src | Verdict |
|---|---|---|---|---|
| geoid | Relief spans "about 200 metres" | +85 m to -106 m = 191 m | G-38 | confirmed |
| geoid | Low south of India, high over North Atlantic | -106 m south of India; +85 m at Iceland | G-38 | confirmed |
| geoid | High over the west Pacific | not stated on the opened page | none | unverified |
| geoid | Relief exaggerated "tens of thousands of times" | an app design choice (NASA's own images use 10,000x) | G-38 | unverified |
| geoid | GRACE measures it with two spacecraft | GRACE and GOCE are among the satellites behind the model; the two-craft method is not described on the page | G-38 | unverified |
| polaris | Earth at "30 kilometres a second" | 29.78 km/s | G-01 | rounded |
| polaris | "crossing 300 million kilometres" | 2 AU = 299 million km (calc) | G-14 | confirmed |
| polaris | Polaris "433 light-years" away | about 430 light-years (NASA Hubble) | G-43 | rounded |
| polaris | Axis wobble "once every 26,000 years" | about 26,000 years (25,772 in the literature) | G-44 | confirmed |
| polaris | "In 12,000 years the pole star will be Vega" | StarChild: about 13,000 years; other NASA pages: 12,000 | G-44 | rounded |
| light-lag | Light "300,000 km/s", "8 minutes 19 seconds" | 299,792.458 km/s; 499.0 s = 8 min 19.0 s (calc: AU / c) | G-14 | confirmed |
| light-lag | Mars at "12 minutes" | 12.7 min at mean distance 1.524 AU (varies about 3 to 22 min) | G-13, G-14 | rounded |
| light-lag | Jupiter "three quarters of an hour" | 43.3 min | G-13, G-14 | rounded |
| light-lag | Neptune "more than four hours" | 4.17 h | G-13, G-14 | confirmed |
| light-lag | Gravity travels "at exactly the same speed" | GR prediction; no opened page measures it | none | unverified |
| magnetosphere | "million tonnes a second" of wind | about 1 million tons per second | G-36 | confirmed |
| magnetosphere | "400 kilometres a second" | about 400 km/s near the equator (800 km/s at high latitude) | G-36 | confirmed |
| magnetosphere | Squashed to "ten Earth-radii" sunward | 6 to 10 Earth radii | G-35 | rounded |
| magnetosphere | Tail "far past the Moon" | hundreds of Earth radii; Moon at 60 | G-35 | confirmed |
| magnetosphere | Molten iron dynamo | convective motion of molten iron in the outer core | G-35 | confirmed |
| magnetosphere | Mars lost its field and its atmosphere is stripped | not on any opened page | none | unverified |
| venus-rose | Venus "224.7 days", Earth "365.3" | 224.701 d; 365.256 d | G-06, G-01 | confirmed |
| venus-rose | 13 laps against 8 in eight years | 13 x 224.701 = 2921.1 d; 8 x 365.256 = 2922.1 d (calc) | G-06, G-01 | confirmed |
| venus-rose | Drift "about two days per cycle" | 5 synodic periods (583.92 d) = 2919.6 d vs 2922.05 d: 2.4 d (calc) | G-06 | rounded |
| venus-rose | "five-petalled rose" | five synodic periods per cycle | G-06 | confirmed |
| heliosphere | Termination shock "about 94 times" Earth's distance | Voyager 2 crossed at 84 AU (Aug 2007); the Voyager 1 figure (94 AU, Dec 2004) is not on an opened page | G-21 | unverified |
| heliosphere | Heliopause "roughly 120 AU" | Voyager 2 about 119 AU; Voyager 1 "around 125 AU" now | G-21 | confirmed |
| heliosphere | Voyager 1 crossed in 2012, Voyager 2 in 2018 | 25 Aug 2012; 2018 | G-17, G-19 | confirmed |
| heliosphere | "the only human objects ever to leave the bubble" | both Voyagers | G-19 | confirmed |
| heliosphere | Comets "a thousand times beyond" the boundary | Oort Cloud about 1,000 to 100,000 AU | G-21 | confirmed |
| cosmic-motion | Earth's spin "0.46 km/s at the equator" | 0.465 km/s (calc: 2π x 6378 km / 86164 s) | G-01 | confirmed |
| cosmic-motion | "29.8 km/s", "230 km/s" | 29.78 km/s; see sun-moving for 230 vs 200 | G-01, G-34 | confirmed / mismatch (230) |
| cosmic-motion | "about 370 km/s" against the CMB | 369.82 +/- 0.11 km/s | G-41 | confirmed |
| cosmic-motion | "falling toward the Great Attractor" | no opened page | none | unverified |
| stopped-galaxy | Fall times: Mercury 15.5 d, Venus 40, Earth 65, Mars 121 d, Jupiter 2 y, Neptune 29 y | T / (4 sqrt 2): 15.5, 39.7, 64.6, 121.4 d; 2.1 y; 29.1 y (calc from NASA orbit periods) | G-05, G-06, G-01, G-07, G-08, G-11 | confirmed (calc) |
| stopped-galaxy | Fall lasts "1/(4 sqrt 2), about 18%" of the period | 0.1768 | G-45 | confirmed (calc) |
| stopped-galaxy | Sun "26,000 light-years out", "about 44 million years" | 28,000 ly (ESA); 44 Myr is the app's N-body result, a point-mass estimate gives about 38 to 41 Myr | G-50 | unverified (model output) |

## 9. Body data and info-panel notes (bodies.ts)

| Item | App value | Reference | Src | Verdict |
|---|---|---|---|---|
| Orbital elements, 8 planets (a, e, i, node, peri, L) | Standish J2000 values | identical to Table 1 for all 8 planets (Earth row is "EM Bary") | G-13 | confirmed |
| Pluto elements (a 39.48211675, e 0.2488273, i 17.14) | Standish 1992 | Pluto is not in the current Table 1; NASA fact sheet J2000 gives e 0.2488, i 17.14 | G-12 | rounded |
| Sun mass | 1.98892e30 kg | 1.9884e30 kg (GM_Sun / G) | G-03, G-14 | mismatch (0.03%) |
| Sun radius | 696,340 km | 695,700 km | G-03, G-16 | mismatch (0.09%) |
| Sun axial tilt | 7.25° | 7.25° | G-03 | confirmed |
| Sun rotation 25.38 d "at the equator" | 25.38 d | 609.12 h = 25.38 d is at 16° latitude; equator about 25.05 d (calc from NASA formula) | G-03 | mismatch (label) |
| Sun "99.86% of the system mass" | 99.86% | 99.8% (NASA); 99.87% (calc from fact-sheet masses) | G-33, G-04 | confirmed |
| Mercury mass, radius, tilt, rotation | 3.3011e23, 2439.7, 0.034, 58.646 d | 3.3010e23, 2439.7, 0.034, 1407.6 h = 58.65 d | G-05 | confirmed |
| Venus mass, radius, tilt, rotation | 4.8675e24, 6051.8, 177.36, -243.025 d | 4.8673e24, 6051.8, 177.36, -5832.6 h | G-06 | rounded |
| Venus 464 °C | ~464 °C | 464 °C | G-06 | confirmed |
| Earth mass, radius, tilt | 5.97237e24, 6371.0, 23.44 | 5.9722e24, 6371.0, 23.44 | G-01 | rounded |
| Moon mass | 7.342e22 | 0.07346e24 (7.346e22; GM-based 7.346e22) | G-02, G-14 | rounded |
| Barycenter "4 671 km from Earth's center" | 4,671 km | 384,400 / (1 + 81.3006) = 4,671 km (calc) | G-02, G-14 | confirmed |
| Mars mass, radius, tilt | 6.4171e23, 3389.5, 25.19 | 6.4169e23, 3389.5, 25.19 | G-07 | rounded |
| Phobos / Deimos semimajor axis | 9376, 23463 km | 9378, 23459 km | G-07 | rounded |
| Jupiter mass, radius, tilt, rotation | 1.8982e27, 69911, 3.13, 0.41354 d | 1898.13e24, 69911, 3.13, 9.925 h = 0.41354 d | G-08 | confirmed |
| Jupiter "2.5x all others combined" | 2.5 | 1898.13 / 769.3 = 2.47 (calc from fact-sheet masses) | G-04, G-08 | rounded |
| Saturn "lower density than water" | <water | 687 kg/m3 | G-09 | confirmed |
| Uranus tilt 97.77°, retrograde | 97.77 | 97.77° | G-10 | confirmed |
| Neptune "winds >2000 km/h" | >2000 | 0 to 580 m/s = 2,090 km/h | G-11 | confirmed |
| Neptune discovered in 1846 by prediction | 1846 | 23-24 Sep 1846 | G-55 | confirmed |
| Pluto reclassified in 2006 | 2006 | 2006 | G-52 | confirmed |
| Pluto axial tilt | 122.53° | NASA fact sheet 119.51°; NASA Pluto page 57° to the orbit plane (equals about 123° retrograde) | G-12, G-52 | mismatch (sources differ; not displayed in tour text) |
| Mercury "3:2 spin-orbit resonance" | 3:2 | no opened page | none | unverified |
| Info-panel notes on Olympus Mons, Great Red Spot, Titan atmosphere | n/a | not on any opened page | none | unverified |

## 10. Constants (constants.ts)

| Constant | App value | Reference | Src | Verdict |
|---|---|---|---|---|
| G | 6.6743e-11 | 6.67430(15)e-11 (CODATA 2022; same as 2018) | G-15, G-14 | confirmed |
| AU | 1.495978707e11 m | 149,597,870,700 m, IAU 2012 B2 | G-14 | confirmed |
| M_SUN | 1.98892e30 kg | 1.9884e30 kg | G-14, G-03 | mismatch (+0.026%) |
| GM_SUN = G x M_SUN | 1.32746e20 m3/s2 | 1.32712440041e20 m3/s2 (IAU 2015 B3 nominal) | G-14, G-16 | mismatch (+0.026%) |
| DAY, YEAR | 86400 s, 365.25 d | Julian year definition | G-14 | confirmed |

## Mismatches

Applied in the package for M8 (the texts a learner reads as the text version of a step, so they must not contradict the
course): rows 1, 2, 7 and 8 and the `exoplanet` wording below, in English and Polish (`src/ui/tour.ts`). Rows 3 to 6 and 9
are internal constants or comments with no visible effect (0.03% on the Sun's mass); they stay as proposals for the
upstream repository, `qunabu/Gravity`.

| # | Where | App says | Course uses (NASA/JPL value) | Proposed upstream fix |
|---|---|---|---|---|
| 1 | tour step `dark-matter` | "outweighing all the stars five to one" | Dark matter is about 5.4 times ordinary matter (27% vs 5% of the universe). Stars are only a fraction of ordinary matter, so the ratio to stars is much larger (G-42, G-40) | Reword to "outweighing all ordinary matter about five to one" |
| 2 | tour step `sagittarius-a` | S2 reaches "3% of the speed of light" | 7650 km/s = 2.55% of c at pericentre (G-30) | "about 2.5% of the speed of light" |
| 3 | `constants.ts` M_SUN and GM_SUN | 1.98892e30 kg, GM = 1.32746e20 | M = 1.9884e30 kg; GM = 1.32712440041e20 m3/s2 (G-14, G-16) | Set `GM_SUN = 1.32712440041e20` and `M_SUN = GM_SUN / G`. Effect is 0.03%, invisible in the demo |
| 4 | `bodies.ts` SUN.radius | 696,340 km | 695,700 km (IAU nominal, G-03, G-16) | Use 695700 |
| 5 | `bodies.ts` SUN.rotationPeriod comment | 25.38 d "at the equator" | 25.38 d is the value at 16° latitude (609.12 h); equatorial about 25.05 d (G-03) | Fix the comment, or set 25.05 for the equator |
| 6 | tour steps `sun-moving`, `milky-way`, `cosmic-motion` | Sun moves around the galaxy at "230 km/s" | NASA Solar System: Facts: 828,000 km/h = 230 km/s (G-34); NASA Sun: Facts: 720,000 km/h = 200 km/s (G-33). Course uses 230 km/s and cites G-34 | Keep, or write "roughly 200-230 km/s"; avoid claiming a single NASA figure |
| 7 | tour step `milky-way` | "about two-thirds of the way out" | ESA: about two-thirds, 28,000 ly (G-50); NASA: "about halfway" (G-51) | Say "about 26,000-28,000 light-years from the center" |
| 8 | tour step `lensing` | Eddington "measured exactly that bend" | NASA notes the 1919 equipment's sensitivity is debated and that later, more robust measurements confirm the prediction (G-32) | "measured a bend consistent with it; later measurements confirmed it" |
| 9 | `bodies.ts` Pluto.axialTilt | 122.53° | NASA fact sheet: 119.51° (G-12); NASA Pluto page: 57° to the orbit plane, equivalent to about 123° retrograde (G-52). Sources disagree; not shown in tour text | Keep, add a comment naming the IAU source, or use 119.51 to match the fact sheet |

Soft items (rounded, no change needed but note when quoting): Earth's sphere of influence 924,600 km (textbooks 929,000); gravitational-wave arm stretch is about 1/850 of a proton width, not "less than a thousandth"; Mars light time 12.7 min is a mean; Polaris 430 ly (NASA) vs 433; Vega in 12,000 vs 13,000 years; step `exoplanet` wording "its own radius" should read "the Sun's own radius".

## Values a course should quote

| Quantity | Value | Src |
|---|---|---|
| Earth mean orbital speed | 29.78 km/s (29.8) | G-01 |
| Earth escape velocity | 11.186 km/s (11.2) | G-01 |
| First cosmic velocity (circular, low Earth orbit) | 7.9 km/s (7.91 at R = 6371 km) | G-46, G-14 |
| Second cosmic velocity | 11.2 km/s = sqrt 2 x 7.9 | G-46, G-01 |
| Third cosmic velocity | 16.7 km/s (calc: 16.65 from 12.34 km/s excess over Earth's 29.78 km/s, plus Earth's escape) | G-01 |
| Kepler's third law | p^2 = a^3 (years, AU) | G-45 |
| Mars example | a = 1.52371 AU, p = 1.8808 yr = 686.98 d; NASA 686.980 d | G-13, G-07 |
| Mercury example | a = 0.38710 AU, p = 87.97 d; NASA 87.969 d | G-13, G-05 |
| Moon distance and period | 384,400 km (perigee 363,300, apogee 405,500); sidereal 27.3217 d; synodic 29.53 d | G-02 |
| Moon mass ratio, barycenter | 0.0123 of Earth (1/81.30); barycenter 4,671 km from Earth's center | G-02, G-14 |
| Tides | Two bulges from the difference in the Moon's pull; most coasts two highs and two lows a day | G-39 |
| L1 and L2 distance from Earth | about 1.5 million km (sunward and anti-sunward) | G-22, G-23 |
| L3, L4, L5 | L3 on the far side of the Sun, opposite Earth (about 2 AU from Earth); L4 and L5 60° ahead and behind on Earth's orbit, stable | G-23, G-22 |
| Voyager 1 | launch 5 Sep 1977; Jupiter 5 Mar 1979; Saturn 12 Nov 1980; left the heliosphere 25 Aug 2012 | G-17 |
| Voyager 2 | launch 20 Aug 1977; Jupiter 9 Jul 1979; Saturn 25 Aug 1981; Uranus 24 Jan 1986; Neptune 25 Aug 1989; left the heliosphere 2018 | G-18, G-19 |
| Voyager Grand Tour alignment | once every 175 years | G-20 |
| Termination shock / heliopause | Voyager 2: 84 AU (Aug 2007); about 119 AU at the 2018 crossing | G-21 |
| Mercury perihelion precession | 42.98 arcsec per century (GR 42.9815) | G-31 |
| GW150914 | 14 Sep 2015; about 1.3 billion light-years; announced 11 Feb 2016 | G-27, G-28 |
| Light time Sun to Earth | 499.0 s = 8 min 19 s (1 AU / c); "about 8 min 20 s" in rounded form | G-14 |
| Light time to Neptune | 4.17 h (30.07 AU) | G-13, G-14 |
| Earth axial precession | about 26,000 years (25,772 in the literature) | G-44 |
| Polaris distance | about 430 light-years | G-43 |
| Sun around the Galaxy | about 230 km/s (NASA Solar System: Facts; Sun: Facts says 200); lap about 230 million years | G-34, G-33 |
| Sun distance from the galactic center | about 28,000 light-years (ESA) | G-50 |
| Sagittarius A* | about 4 million solar masses; image released 12 May 2022 | G-29 |
| S2 | 16-year orbit, 7650 km/s at pericentre (2.55% of c) | G-30 |
| Motion against the CMB | 369.82 +/- 0.11 km/s | G-41 |
| GPS relativistic offset | +38 microseconds per day (+45 GR, -7 SR); about 10 km/day if uncorrected | G-37 |
| Age of the universe | 13.80 +/- 0.02 billion years (Planck 2018 tables) | G-40 |
| Constants | G = 6.67430e-11; AU = 149,597,870,700 m; GM_Sun = 1.32712440041e20 | G-15, G-14 |
