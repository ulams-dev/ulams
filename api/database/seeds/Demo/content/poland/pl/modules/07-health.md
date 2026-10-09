---
title: 7. Zdrowie
summary: Długość życia, ofiary wypadków i jakość powietrza oraz to, jak policzyć zmianę z dwóch liczb.
duration: 50 min
---

::: interactive title="Dłuższe życie" start=c-health end=life duration="6 min"
W 2024 roku przeciętne trwanie życia wyniosło 74,93 roku dla mężczyzn i 82,26 dla kobiet, o 8,7 i 7 lat więcej niż w 1990 {{src:PL-099}}. Wzrosła też liczba lat zdrowego życia, do 61,6 dla mężczyzn i 65,3 dla kobiet {{src:PL-101}}.

**Spróbuj.** Przeczytaj wykres od 1990 do 2024. Która linia wzrosła bardziej w latach i dlaczego przeciętne trwanie życia przy urodzeniu nie jest prognozą dla jednej osoby?
:::

::: interactive title="Drogi i powietrze" start=roaddeaths end=air duration="8 min"
Na polskich drogach zginęło w 2025 roku 1 660 osób, wobec 3 026 w 2016 {{src:PL-103}}. W Krakowie, na stacji przy ul. Bujaka, dni z przekroczeniem normy PM10 spadły z 78 w 2016 do 23 w 2024 {{src:PL-106}}.

**Spróbuj.** Każda kropka to 20 istnień. Policz, ile kropek znika między 2016 a 2025, i sprawdź to z dwiema liczbami.
:::

::: richtext title="Jak czytać liczby o zdrowiu" duration="10 min"
**Miary okresowe.** Przeciętne trwanie życia przy urodzeniu to miara okresowa: opisuje umieralność w jednym roku i nie jest prognozą dla człowieka. GUS podaje 74,93 roku dla mężczyzn i 82,26 dla kobiet w 2024, o 8,7 i 7 lat więcej niż w 1990 {{src:PL-099}}. Lata zdrowego życia, czyli lata przeżyte w dobrym zdrowiu, wyniosły w 2024 roku 61,6 dla mężczyzn i 65,3 dla kobiet {{src:PL-101}}.

**Zmiana w czasie.** Policja naliczyła w 2025 roku 1 660 ofiar śmiertelnych wypadków drogowych, wobec 3 026 w 2016 i 1 896 rok wcześniej {{src:PL-103}}. Zmiana z 3 026 na 1 660 to spadek o 1 366 osób, czyli 45,1% z 3 026: nagłówek „o 45% mniej” to nasze wyliczenie {{src:PL-104}}. Wzór to (nowa minus stara) podzielone przez starą, razy 100.

**Licz właściwą rzecz.** Przy jakości powietrza celem są mniej dni ze złym powietrzem, więc krótszy słupek to dobra wiadomość. W Krakowie wszystkie osiem stacji pomiarowych spełniło roczną normę PM10 w ostatnich dwóch latach raportu miasta z lipca 2025. Na stacji przy ul. Bujaka dni z przekroczeniem normy spadły z 78 w 2016 do 23 w 2024, a w 2024 po raz pierwszy wszystkie stacje spełniły prawną normę dla benzo[a]pirenu {{src:PL-105}}.
:::

::: layout title="Ćwiczenie: odczytaj wykres" duration="12 min" sources=PL-103,PL-106,PL-099
{
  "document": [
    {"component": "PracticeActivity", "props": {
      "title": "Policz zmianę z dwóch liczb",
      "intro": "Zmiana procentowa = (nowa minus stara) podzielone przez starą, razy 100. Użyj podanych wartości; podpowiedzi są opcjonalne.",
      "toolbox": [
        {"label": "Wzór", "text": "Zmiana w procentach = (nowa - stara) / stara x 100."},
        {"label": "Wartości", "text": "Ofiary wypadków: 3 026 w 2016, 1 660 w 2025. Dni z PM10 przy ul. Bujaka: 78 w 2016, 23 w 2024."}
      ],
      "challenges": [
        {"id": "road-deaths", "level": 1, "prompt": "Liczba ofiar wypadków spadła z 3 026 w 2016 do 1 660 w 2025. Mniej więcej o ile procent?",
         "hints": [
           {"tier": "nudge", "text": "Najpierw policz, o ile osób zginęło mniej."},
           {"tier": "pointer", "text": "3 026 minus 1 660 to spadek. Podziel go przez starą wartość, 3 026."},
           {"tier": "near_solution", "text": "1 366 podzielone przez 3 026 to około 0,45."}
         ],
         "options": [
           {"label": "Około 45% mniej", "correct": true, "feedback": "Dobrze: 1 366 / 3 026 to 45,1%."},
           {"label": "Około 82% mniej", "correct": false, "feedback": "To dzieli spadek przez nową wartość, 1 660. Dziel przez starą."},
           {"label": "Około 55% mniej", "correct": false, "feedback": "To wartość z 2025 jako udział wartości z 2016 (54,9%), a nie spadek."}
         ],
         "workedSolution": "(1 660 - 3 026) / 3 026 = -1 366 / 3 026 = -0,451, czyli spadek o 45,1%."},
        {"id": "pm10", "level": 2, "prompt": "Dni z przekroczeniem normy PM10 na stacji przy ul. Bujaka spadły z 78 w 2016 do 23 w 2024. Mniej więcej o ile procent?",
         "hints": [{"tier": "pointer", "text": "Użyj tego samego wzoru; stara wartość to 78."}],
         "options": [
           {"label": "Około 70% mniej", "correct": true, "feedback": "Dobrze: 55 / 78 to 70,5%."},
           {"label": "Około 30% mniej", "correct": false, "feedback": "23 / 78 to 29,5%, wartość z 2024 jako udział wartości z 2016, a nie spadek."},
           {"label": "Około 55% mniej", "correct": false, "feedback": "55 to liczba dni, a nie procent."}
         ],
         "workedSolution": "(23 - 78) / 78 = -55 / 78 = -0,705, czyli spadek o 70,5%."},
        {"id": "own-question", "level": 3, "prompt": "Znajomy mówi: „Przeciętne trwanie życia mężczyzn wzrosło o 8,7 roku, więc mężczyzna urodzony dziś będzie żył o 8,7 roku dłużej niż w 1990”. Napisz jednym zdaniem, co jest w tym nie tak.",
         "workedSolution": "Przeciętne trwanie życia przy urodzeniu to miara okresowa umieralności w jednym roku (2024), a nie prognoza dla człowieka. Mówi, że gdyby umieralność z 2024 roku pozostała bez zmian, chłopiec urodzony wtedy żyłby średnio 74,93 roku."}
      ]
    }}
  ],
  "fallback": "## Ćwiczenie: policz zmianę z dwóch liczb\n\nZmiana procentowa = (nowa - stara) / stara x 100.\n\n1. Ofiary wypadków spadły z 3 026 (2016) do 1 660 (2025): spadek o 45,1%.\n2. Dni z PM10 przy ul. Bujaka spadły z 78 (2016) do 23 (2024): spadek o 70,5%.\n3. Przeciętne trwanie życia przy urodzeniu to miara okresowa, a nie prognoza dla człowieka."
}
:::

::: quiz title="Quiz: zdrowie" duration="8 min" pass=60
::Ofiary wypadków:: Liczba ofiar wypadków spadła z 3 026 w 2016 do 1 660 w 2025. Jaki był spadek w procentach, z dokładnością do jednego miejsca po przecinku? {#45.1:0.2}

::Dni ze złym powietrzem:: Na stacji przy ul. Bujaka w Krakowie dni z przekroczeniem normy PM10 spadły z 78 w 2016 do 23 w 2024. Jaki był spadek w procentach? {#70.5:0.5}

::Długość życia:: Przeciętne trwanie życia przy urodzeniu w 2024 roku wyniosło 82,26 roku dla kobiet i 74,93 roku dla mężczyzn. O ile lat było wyższe u kobiet? {#7.33:0.05}

::Miara okresowa:: Przeciętne trwanie życia przy urodzeniu jest prognozą, jak długo będzie żyć konkretne dziecko. {F}

::Lata zdrowego życia:: W 2024 roku lata zdrowego życia kobiet wyniosły 65,3. U mężczyzn było to {=61,6 ~74,9 ~82,3 ~8,7}
:::
