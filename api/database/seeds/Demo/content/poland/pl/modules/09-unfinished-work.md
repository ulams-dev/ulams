---
title: 9. Niedokończona robota
summary: Co wciąż trudne, obok tego, co się rusza.
duration: 30 min
---

::: interactive title="Źle i lepiej jednocześnie" start=c-ledger end=ledger duration="8 min"
Rzeczy mogą być trudne i jednocześnie się poprawiać. Ostatni krok zestawia trzy problemy z trzema poprawami: demografię, finanse publiczne i energię. Żadna nie znosi drugiej {{src:PL-193}}.

**Spróbuj.** Przeczytaj każdy wiersz zestawienia dwa razy, najpierw lewą stronę, potem prawą. Czy jakaś liczba po lewej jest zaprzeczona przez liczbę po prawej? Dlaczego nie?
:::

::: richtext title="Jak czytać zestawienie" duration="8 min"
**Wciąż trudne.** Współczynnik dzietności spadł z 1,16 w 2023 do 1,10 w 2024 {{src:PL-193}}. Projekt budżetu na 2027 zakłada deficyt sektora instytucji rządowych i samorządowych na poziomie 7,1% PKB {{src:PL-192}}. Węgiel wciąż dawał 61,3% prądu w 2025 {{src:PL-176}}.

**Rusza się.** Mężczyźni żyją o 8,7 roku, a kobiety o 7 lat dłużej niż w 1990 {{src:PL-099}}, a pracuje 78,8% osób w wieku 20–64 lata {{src:PL-010}}. PKB wzrósł o 3,7% w II kwartale 2026, wobec 1,2% w UE {{src:PL-136}}. Magazyny gazu były pełne w 99%, a wiatr i inne OZE wzrosły od 2021 ponad dwukrotnie {{src:PL-111}} {{src:PL-174}}.

**Dwie listy, bez werdyktu.** Sens zestawienia jest taki, że problem i poprawa mogą być jednocześnie prawdziwe. Liczba po jednej stronie nie znosi liczby po drugiej. To, czy deficyt jest problemem, albo jak szybko powinien spadać udział węgla, jest wyborem priorytetów, który liczby informują, ale nie rozstrzygają.

**Co zrobić z tym kursem.** Sprawdź u wydawcy nowsze liczby, podawaj okres, gdy cytujesz liczbę, i mów, jakiego rodzaju to liczba: policzona, wskaźnik, szacunek, odpowiedź w sondażu czy plan.
:::

::: layout title="Wciąż trudne i rusza się" duration="5 min" sources=PL-193,PL-192,PL-176,PL-099,PL-010,PL-136
{
  "document": [
    {"component": "ComparisonTable", "props": {
      "title": "Zestawienie",
      "intro": "Trzy obszary, w każdym trudna liczba i liczba, która się rusza.",
      "caption": "Co wciąż trudne i co się rusza w demografii, finansach publicznych i energii",
      "asOf": "październik 2026",
      "columns": [{"label": "Wciąż trudne"}, {"label": "Co się rusza", "highlight": true}],
      "rows": [
        {"label": "Demografia", "cells": [{"value": "Dzietność 1,16 do 1,10", "note": "2023 do 2024"}, {"value": "Mężczyźni +8,7 roku, kobiety +7", "note": "od 1990; pracuje 78,8% osób 20–64"}]},
        {"label": "Finanse publiczne", "cells": [{"value": "Deficyt 7,1% PKB w 2027", "note": "projekt budżetu"}, {"value": "PKB +3,7%", "note": "II kw. 2026, UE +1,2%"}]},
        {"label": "Energia", "cells": [{"value": "Węgiel 61,3% prądu", "note": "2025"}, {"value": "Wiatr i inne OZE z 19,0 do 42,7 TWh", "note": "2021 do 2025"}]}
      ]
    }}
  ],
  "fallback": "## Zestawienie\n\n- Demografia: dzietność spadła z 1,16 (2023) do 1,10 (2024); mężczyźni żyją o 8,7, a kobiety o 7 lat dłużej niż w 1990.\n- Finanse publiczne: projekt budżetu na 2027 zakłada deficyt 7,1% PKB; PKB wzrósł o 3,7% w II kw. 2026, wobec 1,2% w UE.\n- Energia: węgiel dawał 61,3% prądu w 2025; wiatr i inne OZE wzrosły od 2021 z 19,0 do 42,7 TWh."
}
:::

::: quiz title="Quiz: niedokończona robota" duration="5 min" pass=60
::Dzietność:: Współczynnik dzietności spadł z 1,16 w 2023 do jakiej wartości w 2024? {=1,10 ~1,07 ~1,2 ~0,9}

::Obie prawdziwe:: Dobra liczba po jednej stronie zestawienia znosi złą liczbę po drugiej. {F}

::Dopasuj:: Dopasuj każdą liczbę do jej obszaru. {=Dzietność 1,10 -> Demografia =Deficyt 7,1% PKB, projekt na 2027 -> Finanse publiczne =Węgiel 61,3% prądu -> Energia}
:::
