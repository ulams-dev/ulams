export default {
  'menu.Analytics.authoring': 'Tworzenie kursów',
  authoring_metrics_title: 'Metryki tworzenia kursów',
  authoring_metrics_intro:
    'Ile czasu zajęło wytworzenie każdego kursu zbudowanego w kreatorze kursów, ile kosztowały wywołania modelu i jaka część tego, co napisało AI, została bez zmian.',
  authoring_metrics_own_figures:
    'To własne pomiary tej akademii. Nie są porównywane z inną akademią ani z żadnymi danymi branżowymi.',
  authoring_metrics_filters: 'Filtr i eksport',
  authoring_metrics_from: 'Kursy utworzone od',
  authoring_metrics_to: 'Kursy utworzone do',
  authoring_metrics_export_csv: 'Pobierz CSV',
  authoring_metrics_export_jsonl: 'Pobierz JSON lines',
  authoring_metrics_downloaded: 'Pobrano {name}',
  authoring_metrics_export_failed: 'Nie udało się pobrać pliku.',
  authoring_metrics_loading: 'Wczytuję dane…',
  authoring_metrics_forbidden:
    'Potrzebujesz uprawnienia do odczytu metryk tworzenia kursów. Autorzy widzą dane własnych kursów w studiu.',
  authoring_metrics_error: 'Nie udało się wczytać danych.',
  authoring_metrics_retry: 'Spróbuj ponownie',
  authoring_metrics_empty:
    'W tym okresie żaden kurs zbudowany w kreatorze kursów nie ma jeszcze danych.',
  authoring_metrics_scroll: 'Metryki tworzenia kursów (na małych ekranach przewijane w bok)',
  authoring_metrics_caption: 'Metryki tworzenia kursów, jeden wiersz na kurs',
  authoring_metrics_course_fallback: 'Kurs {id}',
  authoring_metrics_col_course: 'Kurs',
  authoring_metrics_col_generation: 'Czas generowania i edycji AI',
  authoring_metrics_col_interview: 'Czas wywiadu',
  authoring_metrics_col_author: 'Czas autora',
  authoring_metrics_col_build: 'Koszt budowy',
  authoring_metrics_col_maintenance: 'Koszt utrzymania',
  authoring_metrics_col_content: 'Treść',
  authoring_metrics_col_hours: 'Praca na godzinę kursanta',
  authoring_metrics_col_elements: 'Co napisało AI',
  authoring_metrics_hours_value: '{n} godz.',
  authoring_metrics_hours_formula:
    'Praca na godzinę kursanta = (czas generowania + czas wywiadu + czas autora) / czas kursanta, gdzie czas kursanta to minuty zadeklarowane dla lekcji. Pokazujemy ją tylko wtedy, gdy dla kursu zapisano czas autora. Utrzymanie pokazujemy osobno, jako koszt.',
  authoring_metrics_summary_title: 'W całej akademii',
  authoring_metrics_summary_median:
    'Mediana: {n} godz. pracy na godzinę czasu kursanta, z {courses} kursów z zapisanym czasem autora.',
  authoring_metrics_summary_not_enough:
    'Za mało kursów: {courses} z zapisanym czasem autora, do mediany potrzeba co najmniej {min}.',
  authoring_metrics_summary_no_author_time:
    'Jeszcze bez wyniku: żaden kurs nie ma zapisanego czasu autora. Włącz czas przeglądu w Ustawieniach, aby go zapisywać.',
  authoring_metrics_summary_own:
    'Twój własny wcześniejszy wynik, wpisany w Ustawieniach: {n} godz. pracy na godzinę czasu kursanta (Twoja własna ocena, nie pomiar).',
  authoring_metrics_summary_maintenance:
    'Utrzymanie, pokazane osobno: {cost} wywołań modelu w {courses} kursach.',
  authoring_metrics_not_recorded: 'Nie zapisano',
  authoring_metrics_content: 'Lekcje: {lessons}, pytania: {questions}',
  authoring_metrics_no_ai_elements: 'Jeszcze nic',
  authoring_metrics_split:
    'Bez zmian: {kept} ({share}), poprawione na prośbę: {revised}, zmienione: {edited}, nieznane: {unknown}',
  authoring_metrics_note:
    'Czasy są zegarowe. Koszt obejmuje nieudane wywołania i pomija korepetycje oraz pomoc dla kursantów. Czas autora jest zapisywany tylko po włączeniu zapisu czasu przeglądu.',
  authoring_metrics_review_title: 'Czas przeglądu',
  authoring_metrics_review_intro:
    'Gdy czas przeglądu jest włączony, akademia sumuje minuty, które osoby aktywnie spędzają nad kursem w studiu i w edytorze kursu, aby móc powiedzieć, ile trwało jego przygotowanie. Poinformuj autorów, zanim to włączysz: mierzenie pracy może wymagać podstawy prawnej, a w niektórych krajach udziału rady zakładowej. To nie jest porada prawna.',
  authoring_metrics_review_measured:
    'Mierzone: pełne minuty aktywnej pracy przy ekranie nad jednym kursem. Minuta się liczy, gdy karta jest widoczna i na wierzchu, a w ciągu ostatnich dwóch minut użyto klawiatury, wskaźnika albo przewijano.',
  authoring_metrics_review_stored:
    'Zapisywane: kurs, początek minuty, miejsce (studio lub panel) i losowy kod utworzony przy otwarciu strony. Nie zapisujemy: imienia ani konta, adresu, przeglądarki ani urządzenia, ani tego, która lekcja, blok lub pytanie było otwarte.',
  authoring_metrics_review_shown:
    'Pokazywane: minuty autorskie na kurs, na dzień i łącznie, nigdy na osobę. Kurs z jednym autorem pokazuje czas tego autora.',
  authoring_metrics_review_kept:
    'Przechowywane: co noc minuty są sumowane na kurs i dzień, a pojedyncze minuty usuwane po 7 dniach; sumy dzienne zostają tak długo jak kurs.',
  authoring_metrics_review_switch:
    'Włącz lub wyłącz to ustawieniem „review_time” poniżej. Wyłączenie natychmiast zatrzymuje zbieranie.',
  authoring_metrics_review_delete: 'Usuń zapisany czas przeglądu',
  authoring_metrics_review_delete_confirm:
    'To usuwa każdą zapisaną minutę przeglądu i sumę dzienną tej akademii. Czas generowania, koszt i liczby zostają. Nie można tego cofnąć.',
  authoring_metrics_review_delete_yes: 'Tak, usuń',
  authoring_metrics_review_delete_cancel: 'Anuluj',
  authoring_metrics_review_deleted: 'Usunięto {minutes} minut i {days} sum dziennych.',
  authoring_metrics_review_delete_failed: 'Nie udało się usunąć czasu przeglądu.',
  authoring_metrics_duration_seconds: '{s} s',
  authoring_metrics_duration_minutes: '{m} min {s} s',
  authoring_metrics_duration_hours: '{h} godz. {m} min',
};
