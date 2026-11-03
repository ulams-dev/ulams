Przez lata w ESCOLA S.A. budowałem Wellms, open source headless LMS na Laravelu. Kursy, lekcje, postępy, SCORM, H5P, cała współczesna maszyna e-learningowa.

Kiedy odchodziłem, wiedziałem, że nie skończyłem się bawić tym tematem. Więc zrobiłem to, co builder robi najlepiej: sforkowałem Wellms i zacząłem dokładać rzeczy, oraz przebudowałem architekturę na monorepo.

Tak powstaje ULAMS ([ulams.app](https://ulams.app/)):

- kurs z dokumentacji, którą zespół już ma
- każde wygenerowane zdanie wskazuje fragment źródła
- kiedy dokumentacja się zmienia, kurs dostaje propozycję aktualizacji
- a AI tylko proponuje, decyzja zostaje przy autorze

Robię to po godzinach, bo sprawia mi to ogromną frajdę. I dlatego, że chcę na żywym produkcie eksplorować generative UI (AG-UI, A2UI): interfejs, który model proponuje, a autor zatwierdza. Przez najbliższe posty pokażę jeden przykład od początku do końca: runbook → kurs → aktualizacja. Prawdziwy produkt, bez slajdów.
