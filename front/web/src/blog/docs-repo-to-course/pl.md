Wskazałem ULAMS repozytorium GitHub z dokumentacją produktu i dostałem z tego kurs z cytatami. Potem zmieniłem dokumentację i patrzyłem, jak kurs reaguje.

Dokumentacja to `ulams-dev/demo-docs`: cztery pliki Markdown o Pigeon, usłudze webhooków, którą wymyśliłem (MIT). Studio przeczytało z nich 13 fragmentów.

Co zrobiłem w Studio:

- połączyłem repozytorium: owner/name, domyślna gałąź, bez tokenu
- odpowiedziałem na wywiad: programiści i operatorzy, poziom średni, 60 minut, ton profesjonalny, quiz po każdej lekcji
- zatwierdziłem konspekt: 3 moduły, 6 lekcji, 22 cele nauczania. README pominięto, bo opisuje repozytorium, a nie produkt
- zastosowałem kurs, potwierdziłem ostrzeżenia, opublikowałem

Co wyszło: 6 lekcji, 18 pytań, 35 bloków. Wszystkie 35 cytują jeden z 12 fragmentów źródła. Sprawdzenie oparcia oznaczyło trzy stwierdzenia jako możliwe bez oparcia i widziałem je przed publikacją.

Czasy z własnych zapisów Studio: konspekt 24 sekundy, lekcje, quizy i sprawdzenia 6 min 57 s, zastosowanie 23 sekundy. Cały build kosztował 0,28 USD, łącznie 27 wywołań modelu (Sonnet do konspektu, lekcji i quizów, Haiku do wywiadu, sprawdzenia oparcia i metadanych). Dwa wywołania zwróciły wynik, który nie przeszedł walidacji.

A teraz to, na czym mi zależy. Wrzuciłem commit na inną gałąź repozytorium: limit czasu z 10 na 15 sekund, okno wyłączenia z 3 na 5 dni i nową sekcję o wstrzymywaniu endpointu. Wskazałem kursowi tę gałąź i kliknąłem „Check now”:

- nowa rewizja pokazała 2 zmienione fragmenty i 1 dodany, wykryte bez modelu
- kurs wypisał 12 elementów w 3 lekcjach, które mogą wymagać aktualizacji, 2 odpowiedzi w quizach do sprawdzenia i 1 sekcję, której nie obejmuje żadna lekcja
- AI zaproponowało poprawki jako diffy, każda z cytatem fragmentu. Analiza trwała 1 min 23 s i kosztowała około 0,09 USD
- w kursie nic się nie zmieniło. AI proponuje, autor zatwierdza

Co jest prawdziwe, a co nie:

- prawdziwy model, prawdziwe repozytorium, prawdziwe Studio, na lokalnej akademii
- Pigeon i jego dokumentacja są fikcyjne. Napisałem je na potrzeby tego przebiegu
- kursu nigdzie nie wdrożyłem, więc nie ma linku dla uczącego się
- źródła Notion w tym przebiegu nie sprawdzałem i nic o nim tu nie twierdzę
- to jeden przebieg na małym repozytorium. Nic nie mówi o dużych zbiorach dokumentacji

Macie dokumentację, która rozjeżdża się ze szkoleniem zbudowanym na jej podstawie?
