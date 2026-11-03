Twój kurs podąża za repozytorium, dokumentem na Dysku albo stroną w Notion, z których powstał. Tym właśnie zajmowałem się w tym tygodniu 🙂

Do tej pory Living Course pilnował wgranego pliku albo kilku stron WWW. Teraz potrafi śledzić:

- **dowolny serwer Git** obsługujący https (GitHub, GitLab, Gitea, własny), odczytywany zwykłym poleceniem `git`
- **plik lub folder na Dysku Google**: dokumenty Google, pliki tekstowe, Worda i PDF
- **stronę w Notion**, opcjonalnie razem ze stronami pod nią

Co się dzieje, gdy źródło się zmienia:

- konektor to zauważa (commit, nowa wersja na Dysku, nowsza edycja w Notion) i zapisuje nową rewizję
- ULAMS pokazuje diff i znajduje lekcje oraz pytania quizu, które cytują zmieniony fragment
- proponuje nowe wersje, a każda cytuje fragment, z którego pochodzi. AI proponuje, Ty zatwierdzasz. Nic w kursie nie zmienia się, dopóki tego nie zastosujesz

Sprawdziłem to na małym runbooku dyżurnego. Jeden commit zmienił „eskaluj po 10 minutach” na 15 i dodał nowy rozdział. Wynik: 5 elementów kursu do przeglądu (2 bloki tekstu, 2 pytania quizu, 1 cel), 2 odpowiedzi oznaczone jako zmienione, a nowy rozdział pojawił się jako niepokryty. Jedno kliknięcie przycisku „Suggest a lesson” w Studiu dało szkic lekcji z cytatami. Cała kontrola zajęła około pół sekundy na moim laptopie. Pełny przebieg, z prawdziwymi żądaniami i liczbami, jest tutaj: [/creators/git-sync-worked-example/](/creators/git-sync-worked-example/)

Najbardziej zależy mi na bezpieczeństwie, bo kierujesz to na serwery, których nie kontrolujesz:

- git działa w piaskownicy: tylko https, bez przekierowań, bez hooków, bez submodułów, bez LFS, z limitem rozmiaru i czasu, z pustym środowiskiem i katalogiem tymczasowym, który zawsze jest usuwany
- adres przechodzi te same kontrole SSRF co wszystko inne, a sprawdzony adres jest przypinany
- token trafia do nagłówka żądania, nigdy do adresu URL ani logu, a komunikatów błędów samego gita nigdy nie pokazujemy
- w Dysku i Notion tokeny logowania zostają na serwerze. Przeglądarka dostaje tylko jednorazowy kod

Co jest prawdziwe:

- konektor Git jest testowany na prawdziwych repozytoriach git i na nagrywającym zamienniku programu
- Dysk i Notion są testowane tylko na skryptowanych serwerach. Nie wywołałem jeszcze prawdziwych API Google ani Notion, więc traktuj je jako gotowe do wypróbowania, nie jako sprawdzone
- wszystkie trzy są domyślnie wyłączone. Administrator włącza je przez `LIVING_COURSE_CONNECTORS`, obraz musi zawierać `git`, a Dysk i Notion potrzebują aplikacji OAuth należącej do Twojej instalacji. Dysk i Notion są częścią pakietu living-course
- w moim przebiegu AI było wbudowaną symulacją, więc koszt 0,02 USD to wyliczenie, a nie rachunek, i nic nie mówi o jakości prawdziwego modelu

Jak je włączyć: [/admin/living-course/](/admin/living-course/)

Na które źródło skierowałbyś swój kurs najpierw: repozytorium, dokument na Dysku czy Notion?
