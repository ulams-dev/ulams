---
title: 1. Energia
summary: Magazyny gazu, panele na dachach, węgiel i wiatr oraz to, co Polska sprzedaje jako żywność.
duration: 45 min
---

::: interactive title="Gaz i słońce na dachach" start=c-energy end=solar duration="8 min"
Dwie liczby otwierają rozdział o energii. 29 września 2026 magazyny gazu były pełne w 99%, to około 3,3 mld m³ {{src:PL-111}}. Na koniec 2025 w Polsce było 1 636 673 mikroinstalacji odnawialnych, około 13,9 GW, głównie fotowoltaika na dachach {{src:PL-115}}.

**Spróbuj.** Otwórz panel liczb i porównaj moc z produkcją. Dlaczego 13,9 GW mocy daje w roku 8,95 TWh? Pomyśl o nocy i zimie.
:::

::: interactive title="Węgiel, wiatr i eksport żywności" start=coal end=food duration="8 min"
W krajowym systemie elektroenergetycznym węgiel dawał 79,7% prądu w 2021 i 61,3% w 2025 {{src:PL-176}}. Wiatr i inne OZE wzrosły z 19,0 do 42,7 TWh. Eksport rolno-spożywczy wyniósł w 2025 roku 58,4 mld € {{src:PL-123}}.

**Spróbuj.** Znajdź wykres „slope”. W którą stronę idzie linia i czy spadek jest w punktach procentowych, czy w procentach?
:::

::: richtext title="Jak czytać liczby o energii" duration="10 min"
Cztery myśli pomagają w tym rozdziale.

**Magazyn to udział pojemności.** Gas Storage Poland podał 29 września 2026, że jego magazyny są pełne w 99%, to około 3,3 mld m³ gazu roboczego {{src:PL-111}}. To mówi, jak pełne są zbiorniki, a nie ile gazu zużyje zima, a pełny magazyn nie usuwa wysokich cen. Dostawy idą przez Baltic Pipe z Norwegii (do 10 mld m³ rocznie od października 2022) {{src:PL-114}} i terminal LNG w Świnoujściu, rozbudowany do 8,3 mld m³ rocznie w styczniu 2025 {{src:PL-113}}.

**Moc to nie produkcja.** Panel o danej mocy daje ją tylko w dobrym świetle. Urząd Regulacji Energetyki liczy na koniec 2025 roku 1 636 673 mikroinstalacje OZE o mocy około 13,9 GW, a w 2025 oddały do sieci 8,95 TWh {{src:PL-115}}.

**Udziały zmieniają się z dwóch powodów.** Udział węgla w produkcji prądu spadł z 79,7% do 61,3% między 2021 a 2025. To spadek o 18,4 punktu procentowego. To udział w tym, co wyprodukował krajowy system elektroenergetyczny, nasze wyliczenie z rocznych tabel operatora sieci {{src:PL-176}}; inne źródła przyjmują inne podstawy i podają inne procenty. W tych samych latach wiatr i inne OZE wzrosły ponad dwukrotnie, z 19,0 do 42,7 TWh {{src:PL-174}}. Pierwsza polska morska farma wiatrowa, Baltic Power, wysłała pierwszy prąd do sieci 10 lipca 2026 {{src:PL-122}}.

**Żywność to też energia, tylko inna.** W 2025 roku polski eksport rolno-spożywczy sięgnął 58,4 mld €, z nadwyżką 19,8 mld €, więc import wyniósł około 38,6 mld €. Około jednej czwartej wartości eksportu trafiło poza UE {{src:PL-123}}.
:::

::: layout title="Węgiel i wiatr, 2021 i 2025" duration="5 min" sources=PL-176,PL-174,PL-175
{
  "document": [
    {"component": "ComparisonTable", "props": {
      "title": "Prąd w krajowym systemie elektroenergetycznym",
      "intro": "Te same dwie miary w dwóch latach, z rocznych raportów operatora sieci.",
      "caption": "Udział węgla w produkcji prądu oraz wiatr i inne OZE, 2021 i 2025",
      "asOf": "październik 2026",
      "columns": [{"label": "2021"}, {"label": "2025", "highlight": true}],
      "rows": [
        {"label": "Udział węgla w produkcji prądu", "help": "Węgiel kamienny i brunatny do całej produkcji", "cells": [{"value": "79,7%"}, {"value": "61,3%", "note": "o 18,4 pkt niżej"}]},
        {"label": "Wiatr i inne OZE", "help": "Wyprodukowane terawatogodziny", "cells": [{"value": "19,0 TWh"}, {"value": "42,7 TWh", "note": "ponad dwa razy więcej"}]}
      ]
    }}
  ],
  "fallback": "## Prąd w krajowym systemie elektroenergetycznym\n\nUdział węgla w produkcji prądu: 79,7% w 2021 i 61,3% w 2025 (o 18,4 punktu procentowego mniej). Wiatr i inne OZE: 19,0 TWh w 2021 i 42,7 TWh w 2025."
}
:::

::: quiz title="Quiz: energia" duration="8 min" pass=60
::Magazyny gazu:: Jak pełne były magazyny gazu według Gas Storage Poland 29 września 2026? {=99% ~79% ~59% ~39%}

::Udział węgla:: Udział węgla w produkcji prądu spadł z 79,7% w 2021 do 61,3% w 2025. O ile punktów procentowych? {#18.4:0.5}

::Moc to nie produkcja:: Instalacja fotowoltaiczna na dachu o danej mocy zawsze daje tyle mocy. {F}

::Dopasuj liczbę:: Dopasuj każdą pozycję do jej liczby. {=Baltic Pipe, rocznie -> do 10 mld m³ =LNG w Świnoujściu, rocznie -> 8,3 mld m³ =Eksport rolno-spożywczy w 2025 -> 58,4 mld € =Mikroinstalacje OZE -> 1,6 miliona}
:::
