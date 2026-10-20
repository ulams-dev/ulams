Ostatnio napisałem, że na żywym produkcie chcę eksplorować generative UI. Oto pierwszy konkret: 2 minuty filmu o tym, jak z dokumentu powstaje kurs.

Generative UI ma trzy poziomy: kontrolowany, deklaratywny i otwarty. ULAMS używa dwóch pierwszych, a sandbox z kodem pisanym przez model dopiero planuję.

Model nie pisze kodu strony. Składa dokument JSON z zatwierdzonych komponentów i motywów (design system ma ich 53). Nieznany komponent zamienia się w bezpieczny tekst. Potem kontrole sprawdzają cytowania, to, czy odpowiedzi w quizach mają oparcie w źródle, i strukturę nagłówków.

Cały przebieg:

- źródło (PDF, Markdown lub DOCX)
- wywiad, 12 pytań
- konspekt zatwierdzony przez autora
- komponenty i kontrole
- publikacja

Dwa prawdziwe kursy z tego procesu, bez makiet:

- mikrodawkowanie, na podstawie artykułu ze Scientific Reports (2021)
- stablecoins, na podstawie raportu PWG (XI 2021)

Każdy ma 2 moduły, 3 lekcje, 9 pytań, 100% treści z cytatem i około 30 minut nauki. Koszt AI: 0,49 USD i 0,52 USD. Kurs o stablecoinach ma 19 bloków, które cytują 14 fragmentów źródła. W przebiegu o mikrodawkowaniu pojawiły się 3 ostrzeżenia o nagłówkach, które autor potwierdził.

AI proponuje, autor zatwierdza. Dlatego marka, dostępność i standardy (H5P, GIFT) są zapewnione z konstrukcji, a nie dopisywane na końcu.

Oba kursy, ze zrzutami z każdego kroku, są na [stronie o generowaniu kursów](https://ulams.app/pl/course-generation/). ULAMS jest open source (MIT) i można go uruchomić u siebie.
