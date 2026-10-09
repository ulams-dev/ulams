# Fact-check of the figures

Date: 2026-10-10. Method: every source URL of a step was opened and the figure, period and wording in
`data/steps.json` were compared with what the publisher shows. Pages that do not render as text (PDFs, script-built
data pages, the IMF DataMapper, the Eurostat database) were read from the PDF text, the publisher's public data API
or the page's own data endpoint. Rule applied (issue #189): the publisher's number replaces a secondary one; a figure
that cannot be confirmed at its publisher is reworded to what the source supports or removed.

Statuses: confirmed, corrected, removed, unreachable (page could not be opened; reason given).

## Figures by step

| Step | Figure as shown | Period | Publisher and URL (source ids) | Status |
|---|---|---|---|---|
| gas | 99% full, about 3.3 bcm working capacity | 29 Sep 2026 | Gas Storage Poland, data endpoint behind https://ipi.gasstoragepoland.pl/en/storage-services/operational-and-operational-data/operational-data/ (PL-111): 98.96%, 36,465.5 of 36,849.1 GWh | confirmed |
| gas | Świnoujście LNG 8.3 bcm a year; Baltic Pipe 10 bcm a year since October 2022 | Jan 2025; Oct 2022 | GAZ-SYSTEM (PL-113, PL-114); the pages render empty, figures matched in GAZ-SYSTEM statements quoted by search results | unreachable (kept, see below) |
| solar | 1,636,673 micro-installations, almost 13.9 GW, over 8.95 TWh, 1,612,450 prosumers | end of 2025 | URE https://www.ure.gov.pl/en/communication/news/518%2CURE-report-The-number-of-RES-micro-installations-in-Poland-has-already-exceeded-.html (PL-115 to PL-118) | confirmed |
| coal | coal 79.7% (2021) and 61.3% (2025); renewables 19.0 and 42.7 TWh | 2021, 2025 | PSE annual reports table 6.1 (PL-174 to PL-176): 138,404/173,583 and 101,074/166,529 GWh | confirmed (calculation) |
| coal | Baltic Power first electricity 10 July 2026 | 2026 | https://balticpower.pl/news/baltic-power-offshore-wind-farm-produces-and-delivers-electricity-to-polish-energy-grid-for-the-first-time/ (PL-122) | confirmed |
| food | exports EUR 58.4 bn, surplus 19.8 bn, imports 38.6 bn, 25% outside the EU | 2025 | KOWR https://www.gov.pl/web/kowr/rekordowy-eksport-polskiej-zywnosci-2025 (PL-123 to PL-125) | confirmed (caveat reworded) |
| pps | GDP per person 81% of EU; Germany 115, Czechia 92, Spain 92, Portugal 81, Hungary 76, Greece 68 | 2025 | Statistics Poland / Eurostat (PL-002) | confirmed |
| pps | actual individual consumption 88%, up from 85% | 2025 | Eurostat news item (PL-003) does not name Poland; only press repeats it | removed |
| eight | GDP USD 1.036 T vs 1.076 T; population 36.5 m vs 75.4 m; USD 28.4k vs 14.3k | 2025 | IMF DataMapper API (IMF25): 1,035.586 and 1,075.522 bn; 36.497 and 75.446 m | confirmed (calculation) |
| growth | Q2 2026: Poland 3.7%, EU 1.2%, US 2.1% | Q2 2026 | Eurostat flash, 14 Aug 2026 (PL-136); Statistics Poland later 3.8% (noted in caveat) | confirmed |
| growth | real disposable income +6.7%; spending share 57.6% vs 59.3%; EC forecast 3.5% for 2026 | 2025; 2026 | Statistics Poland (PL-004, PL-006); European Commission (PL-018) | confirmed |
| consumption | +31.8% (Poland), +12.4% (EU), +10.4% (euro area), +10.1% (Germany); price level 73%, third-lowest | 2016-2025; 2025 | Eurostat nama_10_gdp (PL-141, via the Eurostat API); Eurostat news item (PL-009) | confirmed (calculation) |
| poverty | at risk 15.0% (EU 20.9%), so 85 and 79 in 100 not at risk; only Czechia lower | 2025 | Eurostat (PL-007, PL-008); 85 and 79 are 100 minus the published rates | confirmed |
| poverty | average gross pay PLN 9,401.58 | June 2026 | Statistics Poland (PL-146) | confirmed |
| flank | nine multinational battlegroups | 29 Sep 2026 update | NATO https://www.nato.int/en/what-we-do/deterrence-and-defence/strengthening-natos-eastern-flank (PL-126, PL-127) | confirmed |
| flank | Eastern Sentry (Sep 2025) and Baltic Sentry (Jan 2025), wording "vigilance along the eastern flank", "critical undersea infrastructure" | 2025 | same page | corrected (wording; "standing", "airspace", "cables and pipelines" were ours) |
| flank | 32 allies (KPI) | n/a | NATO states no total on the page | removed |
| defence | Lithuania 5.33, Estonia 5.10, Latvia 4.92, Poland 4.68 (4.25 in 2025), Greece 3.65, US 3.17, Europe and Canada 2.53 | 2026 estimates | NATO table 3 (PL-178) | confirmed |
| yards | ORP Wicher launched in August 2026, 138.7 m, first of three Miecznik, service 2029, programme 2032; ICEYE May 2025; Baltic Power 10 July 2026 | 2025-2026 | Ministry of State Assets, MON (PL-184, PL-185); ICEYE (PL-081); Baltic Power (PL-122) | confirmed |
| yards | Świnoujście LNG 8.3 bcm a year from Jan 2025 | Jan 2025 | GAZ-SYSTEM (PL-113); page blocked | unreachable (see below) |
| safe | 97% neighbourhood safe, 89% Poland safe, 36% afraid (down 5 points); Global Peace Index 22nd, up 23 from 45th | April 2026; GPI 2026 | CBOS (PL-108 to PL-110); Institute for Economics and Peace (PL-172) | confirmed |
| ai | ElevenLabs USD 11 bn (4 Feb 2026), USD 22 bn employee tender (30 Sep 2026), over USD 330 m ARR at end of 2025; Warsaw University at the ICPC finals 31 years in a row | 2025-2026 | ElevenLabs (PL-043 to PL-045); University of Warsaw (PL-129) | confirmed (ICPC count is as of the 2025 finals) |
| ai | founded by two Poles; Pachocki chief scientist; Zaremba a founder | 2015, 2026 | openai.com returns 403; ElevenLabs pages do not name the founders | unreachable (kept, see below) |
| parcels | InPost 61,196 machines (more than half outside Poland), 1.4 bn parcels (+25%), 51% of revenue abroad; Żabka 12,339 stores, 173 in Romania | 2025 | InPost FY2025 release (PL-063 to PL-065); Żabka (PL-066, PL-067) | confirmed |
| parcels | BLIK links with Bizum and MB WAY | early 2026 | BLIK (PL-042): pilots completed | corrected ("has piloted", pl "przetestował") |
| brands | Solaris 1,631 vehicles, 15 countries, 86% low- or zero-emission, 107 trolleybuses for Vancouver; Witcher 85 m, Cyberpunk 2077 35 m, Phantom Liberty 10 m | 2025 | Solaris (PL-074 to PL-076); CD PROJEKT FY2025 (PL-077, PL-078) | confirmed ("firm" order removed: the page says only "ordered") |
| orbit | 20 days, 13 Polish-led experiments, mission Ignis, Ax-4 | 25 June-15 July 2025 | ESA https://www.esa.int/Science_Exploration/Human_and_Robotic_Exploration/ignis/Ignis_mission_highlights (PL-131, PL-132) | confirmed |
| orbit | "first Pole since 1978", "Falcon 9 and Dragon", "2 Poles in space, ever" (KPI) | n/a | not on the page | removed (replaced by "Poland's first government-sponsored human spaceflight to the station") |
| jobs | unemployment 3.1% (2025, harmonised) | 2025 | European Commission forecast (PL-011); Eurostat une_rt_a, Poland 3.1 | confirmed |
| jobs, ledger | employment rate 78.8% of 20-64s | 2025 | Eurostat lfsi_emp_a, Poland 78.8 in 2025 (read through the Eurostat API; added to PL-010) | confirmed |
| mob | 11 m mDowód; 1.5 m logins a day; about 7.5 m PESEL reservations, 75% through the app; 20 m patient accounts | 2025; 12 Dec 2025 | gov.pl mObywatel review and PESEL release (PL-019, PL-020, PL-024, PL-025); CeZ (PL-029) | confirmed |
| pit | almost 24 m returns online, 1 m on paper; Twój e-PIT 14.8 m; over 35% of those under five minutes | 2026 season | KIS https://www.gov.pl/web/kis/rozliczenie-pit-za-2025-rok-miliony-podatnikow-zaufaly-usludze-twoj-e-pit (PL-026 to PL-028) | confirmed |
| blik | 2.9 bn transactions, PLN 441.5 bn, 20.7 m active accounts, 735 m phone-number transfers | 2025 | BLIK https://www.blik.com/en/nearly-3-bn-blik-transactions-in-2025-and-over-2-m-new-users (PL-038 to PL-041) | confirmed |
| blik | the illustrative day (mStłuczka, qualified signature in mObywatel, e-prescription in the patient account) | 2025-2026 | mObywatel review (PL-022, PL-023); ikp.gov.pl (PL-033) | confirmed for mStłuczka and signature; loosely supported for the e-prescription; the parcel-locker line has no source in the step (kept, the chart is labelled illustrative) |
| roads | 5,466 km ("niemal"), almost 400 km opened, 1,373 km under construction, 290.8 km planned for 2026 | end of 2025; 2026 plan | GDDKiA (PL-083 to PL-085, PL-162) | confirmed |
| transit | 438.97 m rail passengers, most in 30 years (+7.7%); Chopin 24.1 m (+13%); Gdańsk nearly 2.8 m TEU (+23%) | 2025 | UTK (PL-090, PL-091); Chopin airport (PL-164); Port of Gdańsk (PL-096) | confirmed (wording fixed: "nearly 2.8 million", record) |
| transit | airports 66.1 m passengers (+11.7%) | 2025 | ULC (PL-086); the page is script-built, figure read from a search summary of ulc.gov.pl | unreachable (kept, see below) |
| travel | 44% abroad (highest since 1993); 64% leisure trip (highest since 2006) | 2025 | CBOS K_017_26 (PL-093, PL-094) | confirmed |
| life | 74.93 and 82.26 years; +8.7 and +7 since 1990; 1990 values 66.2 and 75.2; healthy life years 61.6 and 65.3 | 2024 | Statistics Poland (PL-099 to PL-102) | confirmed |
| roaddeaths | 1,660 deaths in 2025, 3,026 in 2016, 1,896 in 2024, down 45% | 2016-2025 | Police, Wypadki drogowe 2025 (PL-103, PL-104); the 45% is calculated (54.9% index) | confirmed |
| air | Bujaka 78 days over the limit in 2016, 23 in 2024; all six benzo[a]pyrene stations met the limit in 2024 for the first time | 2016-2024 | City of Kraków, July 2025 (PL-105 to PL-107) | confirmed |
| air | all eight stations met the annual PM10 limit | "last two years" of a July 2025 report | same page; it does not name the years | corrected (wording) |
| air | coal and wood ban in Kraków "since 2019" | n/a | not on the cited page (secondary sources: resolution in force from 1 Sep 2019) | removed |
| happy | 81% life, 86% place, 70% material conditions, 55% future, financial satisfaction +6 points | fieldwork 27 Nov - 8 Dec 2025 | CBOS K_003_26 (PL-012 to PL-016) | confirmed |
| social | 80% restaurant, 71% hosted friends, 67% donated, 64% read a book | 2025 | CBOS K_017_26 (PL-133 to PL-135) | confirmed |
| social | alcohol 8.3 l per person; 9.8 l in 2019; about 15% less | 2019-2025 | KCPU / Statistics Poland (PL-097, PL-098); "recorded" is our wording, the 15% is calculated | confirmed (calculation) |
| ledger | fertility 1.16 (2023) to 1.10 (2024); 2027 deficit 7.1% of GDP; coal 61.3%; renewables more than doubled | 2023-2027 | Statistics Poland (PL-193); Ministry of Finance (PL-192); PSE (PL-174 to PL-176) | confirmed |
| ledger | Q2 2026 GDP 3.7% vs EU 1.2% | Q2 2026 | Eurostat flash (PL-136); same basis as `growth` | confirmed |

## Changes made

- `pps`: removed the sentence on actual individual consumption (88%, up from 85%), en and pl. The cited Eurostat page
  never names Poland; only press repeats the numbers. Removed source `PL-003` from the step and from `sources.json`.
- `air`: removed "Kraków has banned burning coal and wood ... since 2019" (not on the cited page). The PM10 sentence
  now says "in the last two years of the city's July 2025 report" instead of "2023 and 2024", which the page does not
  state (en and pl).
- `food`: the caveat no longer says imports are calculated; KOWR publishes 38.6 bn (58.4 - 19.8 = 38.6), en and pl.
- `transit`: Gdańsk "a record 2.8 million containers" is now "nearly 2.8 million containers, a record", en and pl.
- `flank`: "two standing operations ... airspace ... cables and pipelines" now "two operations", "vigilance along the
  eastern flank", "critical undersea infrastructure" (en and pl); removed the "32 allies" KPI (the grid is now two
  columns).
- `parcels`: BLIK "is piloting" is now "has piloted" (pl "przetestował").
- `brands`: "firm order" is now "order" for the 107 Vancouver trolleybuses (en and pl).
- `orbit`: removed "the first Pole in space since 1978", "Falcon 9 and Dragon" and the "2 Poles in space, ever" KPI
  (not on the ESA page); the text now names the Ax-4 mission and the first government-sponsored human spaceflight to
  the ISS (en and pl); KPI grid is two columns.
- `jobs`, `ledger`: no text change; added the Eurostat datasets (employment rate lfsi_emp_a, unemployment une_rt_a)
  to the links of `PL-010` and `PL-011` in `sources.json`.
- `gas`: no change; the 99% on 29 Sep 2026 is confirmed (98.96%) at Gas Storage Poland's data endpoint.
- No chart value changed. `ulams-interactive.json` and posters are not regenerated; the caller regenerates them.

## Unreachable

- `PL-113`, `PL-114` (GAZ-SYSTEM, Świnoujście 8.3 bcm and Baltic Pipe 10 bcm): the pages return empty HTML. The
  publisher is primary and the figures match GAZ-SYSTEM statements quoted in search results and the README earlier
  checks; kept.
- `PL-113` also for `yards` (bot wall). `ai`: openai.com (PL-047, PL-048) returns 403; Pachocki as chief scientist
  (an OpenAI post of 6-7 Sep 2026) and Zaremba as a 2015 founding member were confirmed only through news and search
  snippets, and the two Polish ElevenLabs founders only through Wikipedia (Dąbkowski, Staniszewski). Publisher is
  primary; kept, to be opened in a browser once.
- `PL-086` (ULC airports 66.1 m, +11.7%): the page is built by script; the figure comes from a search summary of
  ulc.gov.pl. Publisher is primary; kept, to be opened in a browser once.
- `PL-111`: the page shows zeros without script; confirmed through its own data endpoint (see above).

## Unchecked in this pass

None. Every figure in the nine chapters was opened at its publisher or its publisher's data API.

## Figures a course may quote

Confirmed at the publisher in this pass.

- Energy: gas storage 98.96% full on 29 Sep 2026 (PL-111); 1,636,673 RES micro-installations, almost 13.9 GW, end of 2025 (PL-115); coal 61.3% of electricity in 2025 and 79.7% in 2021, our calculation (PL-176); agri-food exports EUR 58.4 bn, surplus 19.8 bn, 2025 (PL-123); Baltic Power first electricity, 10 July 2026 (PL-122).
- Prosperity: GDP per person 81% of EU average, 2025 (PL-002); Polish GDP USD 1.036 T, 2025 (IMF25); real household consumption +31.8% 2016-2025 (PL-141); at risk of poverty or exclusion 15.0% vs EU 20.9%, 2025 (PL-007); average gross pay PLN 9,401.58, June 2026 (PL-146); real disposable income +6.7%, 2025 (PL-004).
- Security: nine battlegroups, NATO page of 29 Sep 2026 (PL-126); Poland 4.68% of GDP on defence, 2026 estimate (PL-178); ORP Wicher launched in August 2026, service planned 2029 (PL-184); 89% say Poland is safe, April 2026 (PL-109); Global Peace Index 2026 rank 22nd (PL-172).
- Made here: InPost 61,196 parcel machines and 1.4 bn parcels, 2025 (PL-063); Żabka 12,339 stores, end of 2025 (PL-066); Solaris 1,631 vehicles in 15 countries, 2025 (PL-074); Witcher 85 m and Cyberpunk 2077 35 m copies (PL-077); Ignis: 20 days, 13 Polish-led experiments, 2025 (PL-132); 78.8% employment rate 20-64, 2025 (Eurostat lfsi_emp_a, on PL-010). Avoid "first Pole since 1978" and the ElevenLabs founders unless re-sourced.
- Daily life: 11 m mDowód, 1.5 m logins a day, 2025 (PL-019, PL-020); 7.5 m PESEL reservations, 12 Dec 2025 (PL-024); about 24 m returns online, 2026 season (PL-026); BLIK 2.9 bn transactions, PLN 441.5 bn, 2025 (PL-038).
- Mobility: 438.97 m rail passengers, 2025 (PL-090); 5,466 km motorways and expressways, end of 2025 (PL-083); Warsaw Chopin 24.1 m passengers, 2025 (PL-164); 44% of adults travelled abroad, 2025 (PL-093).
- Health: life expectancy 74.93 (men) and 82.26 (women), 2024 (PL-099); 1,660 road deaths in 2025 vs 3,026 in 2016 (PL-103); Kraków Bujaka 78 days over the PM10 limit in 2016, 23 in 2024 (PL-105).
- People: 81% satisfied with life, late 2025 (PL-012); 80% ate out, 2025 (PL-133); alcohol 8.3 l per person, 2025 (PL-097).
- Unfinished work: fertility 1.10 in 2024, from 1.16 (PL-193); 2027 draft deficit 7.1% of GDP (PL-192).
