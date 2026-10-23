/**
 * The white paper of the public product site, "From policy to proof", in English, Polish and Chinese.
 *
 * Rules for the copy: every figure has its source in the text and in the source list; ULAMS figures are only the two
 * measured case-study runs (lib/course-generation.ts), labelled as single runs; no hours-saved figure, speed-up ratio,
 * ROI, testimonial or customer count. Status follows docs/ROADMAP-TODO.md: shipped items are stated plainly, the rest
 * is marked "Coming" (SCIM 2.0, identity attribute mapping and enforcement, HRIS import, routing learners to a
 * variant, AI labels on the remaining surfaces, the data-flow documentation, the Helm chart).
 *
 * There is no build-time PDF: the repository's PDF service (api/pdf, pdfme) fills fixed certificate templates and
 * cannot lay out a long HTML page, so the page carries a print stylesheet and a "Print / save as PDF" button.
 */
import type { Locale } from "../i18n/locales.ts";
import { BRANDON_HALL_SOURCE, CHAPMAN_SOURCE } from "./impact-pages.ts";
import { productShell } from "./product-pages.ts";

export const WHITE_PAPER_ID = "white-paper";
export const AI_ACT_SOURCE = "https://eur-lex.europa.eu/eli/reg/2024/1689/oj";
const DOCS = "https://ulams-dev.github.io/ulams/";
const INSTALL = `${DOCS}operators/one-command-stack/`;

export const WP_SECTIONS = ["summary", "problem", "mechanism", "architecture", "trust", "example", "try"] as const;
export type WpSection = (typeof WP_SECTIONS)[number];

export interface WhitePaperCopy {
  title: string;
  description: string;
  eyebrow: string;
  heading: string;
  subtitle: string;
  body: string;
  primary: string;
  secondary: string;
  facts: Array<{ label: string; value: string; note?: string }>;
  version: string;
  contents: string;
  print: string;
  printNote: string;
  sections: Record<WpSection, { title: string; markdown: string }>;
  sourcesTitle: string;
  sources: Array<{ label: string; href: string }>;
  ctaTitle: string;
  ctaText: string;
  /** Label of the link to the installation guide. */
  install: string;
}

const EN: WhitePaperCopy = {
  title: "ulams — White paper: from policy to proof",
  description: "How ULAMS builds and maintains cited courses: the cost problem, the mechanism, architecture and standards, trust and the AI Act, and a measured run.",
  eyebrow: "White paper",
  heading: "From policy to proof: how ULAMS builds and maintains cited courses",
  subtitle: "For L&D leads, instructional designers and the people who sign off on training.",
  body: "What it costs to make and keep e-learning, why reports disappoint, and how ULAMS turns your own documents into cited courses that follow their sources, with a person approving every change.",
  primary: "Read the paper",
  secondary: "Explore the demos",
  facts: [
    { label: "Hand-built benchmark", value: "49–716 h", note: "per finished hour (Chapman)" },
    { label: "One measured run", value: "7 min 44 s", note: "cited draft, before review" },
    { label: "Grounding in that run", value: "35/35", note: "content blocks cite the source" },
  ],
  version: "Version of October 2026. Status as of that month.",
  contents: "Contents",
  print: "Print / save as PDF",
  printNote: "Your browser's print dialog can save this page as a PDF. The print layout drops the site navigation and spells out every link.",
  sections: {
    summary: {
      title: "Summary",
      markdown: `- **The cost sits in authoring and upkeep.** Industry ratios put hand-built e-learning at 49 to 716 hours of work per finished hour, and content keeps costing after launch.
- **Reports disappoint.** In Brandon Hall Group's 2013 survey, poor reporting was among the top reasons buyers were looking to change their LMS.
- **ULAMS starts from your sources.** It drafts courses from your documents, cites the passage behind every block, flags what the source does not support and follows the source when it changes.
- **People decide.** Every AI change is a reviewable diff. Nothing reaches learners until an author approves it, and the audit trail records who approved what.
- **Open and standard.** MIT licensed, self-hosted, headless, with SCORM, cmi5, xAPI and LTI 1.3 built in.
- **Measured, not promised.** One recorded run turned a 13-passage docs repository into a 6-lesson draft with 18 quiz questions in 7 min 44 s for USD 0.28. We publish no hours-saved figure.`,
    },
    problem: {
      title: "The problem: content is expensive, and reports are weak",
      markdown: `### Making a course takes hundreds of hours

Chapman Alliance development ratios run from **49 to 716 hours of work for every hour of finished e-learning**, depending on how interactive it is. The study dates from 2010, so treat it as an order of magnitude, not a quote for your project. Industry observers add that keeping content current costs effort after launch, and policy-driven training changes whenever the policy does.

### Keeping it true is a second job

A course written from a policy is correct on the day it is published. When the policy changes, someone has to find every lesson and quiz question that quotes it. Without a link from each statement to its source, that search is manual, and outdated training stays online unnoticed.

### Reporting is where buyers give up

In Brandon Hall Group's 2013 survey, reported by Training magazine, **45.5% of 457 respondents** gave their LMS high marks for value and **47.7%** were looking to move to a new provider; poor reporting was among the top reasons. In the 2012 survey, reporting ranked first. Managers and auditors need to know who is stuck, who finished and who still owes mandatory training, without a spreadsheet.`,
    },
    mechanism: {
      title: "The mechanism: sources, cited draft, diff, living course, variants, evidence",
      markdown: `| Step | What happens | Status |
|---|---|---|
| 1. Sources | Connect a Git repository, Google Drive or Notion, or upload PDF, Word, Markdown or text. The source is split into short passages that can be cited. Uploaded content is treated as data, never as instructions. | Shipped |
| 2. Cited draft | The builder interviews the author (audience, level, length, tone, assessment, language, versions, price, look), proposes an outline and writes lessons and quizzes. Every block cites its passage; a grounding check flags claims the source does not support. | Shipped |
| 3. Reviewable diff | Every AI change arrives as a diff: accept all, accept per element, or reject. Nothing is written to the academy before approval, and publishing is a separate step. | Shipped |
| 4. Living course | When a source changes, ULAMS finds the changed passages, lists the lessons and questions that cite them and proposes updates as a diff. Learners keep their progress, and course versions can be compared side by side. | Shipped |
| 5. Variants | From the same sources, the builder drafts versions for beginners, experts or a named role, each reviewed as a diff against the base course. | Shipped |
| 5a. Routing | Each learner reaches the right variant: chosen by the learner, by an optional placement check or by an admin. | Coming |
| 6. Evidence | Per-course analytics show who is stuck and who finished; compliance reports show who owes mandatory training, with CSV and JSON-lines exports and an audit trail. | Shipped |

Every model call is logged with the model, the tokens and the cost, and each course has a spending cap.`,
    },
    architecture: {
      title: "Architecture and standards",
      markdown: `### Headless and multi-tenant

ULAMS is a Laravel REST API built from composer packages, with separate frontends: an admin panel, the Studio and a reference learner site that ships no JavaScript by default. Each academy is a tenant on its own subdomain, with its own database, storage bucket, keys and theme, so a token from one academy is rejected by every other.

### Standards

| Area | What ULAMS supports | Status |
|---|---|---|
| Content packages | SCORM, cmi5, H5P | Shipped |
| Learning records | xAPI, with a learning record store you own | Shipped |
| Tool interoperability | LTI 1.3, as platform and as tool | Shipped |
| Single sign-on | SAML 2.0 and OpenID Connect per academy, next to Google and Facebook sign-in | Shipped |
| Identity mapping | Attribute, group and role mapping, enforced SSO | Coming |
| Provisioning | SCIM 2.0 users and groups | Coming |
| HR data | HRIS import, CSV and API first | Coming |

### Deployment

One command starts the whole stack on a laptop, a Docker server or a Kubernetes cluster, on your own servers or any cloud. A local trial uses a deterministic AI provider, so it makes no paid model calls. A Helm chart is coming. The code is open source under the MIT licence.`,
    },
    trust: {
      title: "Trust and compliance",
      markdown: `### AI drafts, people decide

The model proposes; an author approves. Each change is a diff with the passage it rests on, the audit trail records who approved it and against which version of the source, and statements the source does not support are flagged before anyone can publish.

### The EU AI Act

Article 50 of the EU AI Act (Regulation (EU) 2024/1689) sets transparency duties, including marking content generated by AI. Our planning date for those duties is **2 December 2026**, the date that applies after the digital omnibus; check the current consolidated text for your own case.

- Shipped: AI labels on tutor answers, extra-help suggestions, reflection feedback and AI-assisted lesson topics.
- Coming, before that date: labels on the remaining AI surfaces (builder drafts, variants and exports).

### Your data stays in your deployment

Courses, learner records and the xAPI store live in the database and storage of your own installation. What leaves it is the text sent to the model provider you configure, for generation, tutoring and checks. A documented data-flow map is coming, and so is a choice of AI provider per academy.

### Accessibility

Learner-facing pages are built and tested for WCAG 2.2 AA, and the admin refuses to save an accent colour that fails AA contrast.`,
    },
    example: {
      title: "A worked example: two measured runs",
      markdown: `Two recorded runs of the Course Builder, each on a source written for the demonstration. The timings and costs are copied from the Studio's own records. They are single runs: no average, and no promise for your content.

| | Docs repository | Notion page |
|---|---|---|
| Source | 13 passages from a public GitHub repository | 6 passages from one Notion page |
| Course | 6 lessons, 18 quiz questions | 6 lessons, 18 quiz questions |
| Grounding | 35 of 35 blocks cite the source | 31 of 31 blocks cite the source |
| Flagged before publishing | 3 possibly unsupported statements | 1 possibly unsupported statement |
| Time | 7 min 44 s in all (outline 24 s, writing and checks 6 min 57 s, apply 23 s) | outline 27 s, writing and checks 4 min 58 s, apply 1 s |
| Model cost | USD 0.28, 27 model calls | USD 0.1973, 26 model calls |

In both runs the author answered eight questions, approved the outline, read the warnings, acknowledged them and published. The author's review time is not included. In the docs-repository run, a later commit changed two numbers and added a section: ULAMS listed 12 elements in 3 lessons for review and proposed the edits as a diff.

### What we do not claim

No hours saved, no speed-up ratio against the benchmark (the two measure different things), no return on investment and no testimonials. A measured metric of authoring time and AI cost per course, on your own data, is coming.`,
    },
    try: {
      title: "How to try it",
      markdown: `1. **Open a live demo.** Six demo academies, each with its own theme and courses, plus their admin panels.
2. **Read the case studies.** Every screen of the docs-repository and Notion-page runs, with captions and screencasts.
3. **Run it yourself.** Install the CLI and run \`ulams stack up\`: a complete local academy with a deterministic AI provider and no paid model calls.
4. **Talk to us.** The contact form on the home page reaches the ULAMS team.`,
    },
  },
  sourcesTitle: "Sources",
  sources: [
    { label: "Chapman Alliance development ratios, as reported by eLearning Industry", href: CHAPMAN_SOURCE },
    { label: "Training magazine on the Brandon Hall Group LMS surveys (2012, 2013)", href: BRANDON_HALL_SOURCE },
    { label: "Regulation (EU) 2024/1689 (AI Act), EUR-Lex", href: AI_ACT_SOURCE },
    { label: "Case study: from a docs repository to a course", href: "/course-generation/docs-repo/" },
    { label: "Case study: from a Notion page to a course", href: "/course-generation/notion-page/" },
    { label: "ULAMS documentation", href: DOCS },
  ],
  ctaTitle: "See it on your own material",
  ctaText: "Open a demo academy, or start the stack locally with no paid model calls.",
  install: "Installation guide",
};

const PL: WhitePaperCopy = {
  title: "ulams — White paper: od polityki do dowodu",
  description: "Jak ULAMS buduje i utrzymuje kursy z cytatami: problem kosztów, mechanizm, architektura i standardy, zaufanie i AI Act oraz zmierzony przebieg.",
  eyebrow: "White paper",
  heading: "Od polityki do dowodu: jak ULAMS buduje i utrzymuje kursy z cytatami",
  subtitle: "Dla liderów L&D, projektantów dydaktycznych i osób, które zatwierdzają szkolenia.",
  body: "Ile kosztuje stworzenie i utrzymanie e-learningu, dlaczego raporty rozczarowują i jak ULAMS zamienia Twoje dokumenty w kursy z cytatami, które idą za źródłami, a każdą zmianę zatwierdza człowiek.",
  primary: "Czytaj dokument",
  secondary: "Zobacz demo",
  facts: [
    { label: "Punkt odniesienia, ręcznie", value: "49–716 godz.", note: "na godzinę kursu (Chapman)" },
    { label: "Jeden zmierzony przebieg", value: "7 min 44 s", note: "szkic z cytatami, przed przeglądem" },
    { label: "Oparcie w źródłach w tym przebiegu", value: "35/35", note: "bloków treści cytuje źródło" },
  ],
  version: "Wersja z października 2026. Stan na ten miesiąc.",
  contents: "Spis treści",
  print: "Drukuj / zapisz jako PDF",
  printNote: "Okno drukowania przeglądarki pozwala zapisać tę stronę jako PDF. Układ do druku pomija nawigację strony i wypisuje adres każdego linku.",
  sections: {
    summary: {
      title: "Podsumowanie",
      markdown: `- **Koszt leży w tworzeniu i utrzymaniu.** Wskaźniki branżowe mówią o 49 do 716 godzinach pracy na godzinę gotowego e-learningu, a treść kosztuje także po premierze.
- **Raporty rozczarowują.** W badaniu Brandon Hall Group z 2013 roku słabe raportowanie należało do głównych powodów, dla których kupujący chcieli zmienić LMS.
- **ULAMS zaczyna od Twoich źródeł.** Tworzy szkice kursów z Twoich dokumentów, cytuje fragment stojący za każdym blokiem, oznacza to, czego źródło nie potwierdza, i idzie za źródłem, gdy się zmienia.
- **Decydują ludzie.** Każda zmiana AI to diff do przejrzenia. Nic nie trafia do uczących się, dopóki autor tego nie zatwierdzi, a ślad audytowy zapisuje, kto co zatwierdził.
- **Otwarty i zgodny ze standardami.** Licencja MIT, własny hosting, architektura headless, wbudowane SCORM, cmi5, xAPI i LTI 1.3.
- **Zmierzone, nie obiecane.** Jeden nagrany przebieg zamienił repozytorium dokumentacji (13 fragmentów) w szkic 6 lekcji z 18 pytaniami quizowymi w 7 min 44 s za 0,28 USD. Nie podajemy liczby zaoszczędzonych godzin.`,
    },
    problem: {
      title: "Problem: treści są drogie, a raporty słabe",
      markdown: `### Kurs to setki godzin pracy

Wskaźniki Chapman Alliance wahają się od **49 do 716 godzin pracy na każdą godzinę gotowego e-learningu**, zależnie od interaktywności. Badanie pochodzi z 2010 roku, więc traktuj je jako rząd wielkości, a nie wycenę Twojego projektu. Obserwatorzy branży dodają, że utrzymanie treści w aktualności wymaga pracy po premierze, a szkolenie oparte na polityce zmienia się za każdym razem, gdy zmienia się polityka.

### Utrzymanie prawdziwości to drugi etat

Kurs napisany na podstawie polityki jest poprawny w dniu publikacji. Gdy polityka się zmienia, ktoś musi znaleźć każdą lekcję i każde pytanie, które ją cytują. Bez powiązania każdego twierdzenia ze źródłem to szukanie odbywa się ręcznie, a nieaktualne szkolenie zostaje w obiegu niezauważone.

### Na raportach kupujący się poddają

W badaniu Brandon Hall Group z 2013 roku, opisanym przez magazyn Training, **45,5% z 457 respondentów** wysoko oceniło wartość swojego LMS, a **47,7%** szukało nowego dostawcy; słabe raportowanie należało do głównych powodów. W badaniu z 2012 roku raportowanie zajęło pierwsze miejsce. Menedżerowie i audytorzy muszą wiedzieć, kto utknął, kto skończył i kto wciąż zalega z obowiązkowym szkoleniem, bez arkusza kalkulacyjnego.`,
    },
    mechanism: {
      title: "Mechanizm: źródła, szkic z cytatami, diff, żywy kurs, wersje, dowody",
      markdown: `| Krok | Co się dzieje | Status |
|---|---|---|
| 1. Źródła | Podłącz repozytorium Git, Google Drive lub Notion albo wgraj PDF, Word, Markdown lub tekst. Źródło jest dzielone na krótkie fragmenty, które można cytować. Wgrana treść jest traktowana jako dane, nigdy jako polecenia. | Dostępne |
| 2. Szkic z cytatami | Kreator przeprowadza wywiad z autorem (odbiorcy, poziom, długość, ton, sprawdzanie wiedzy, język, wersje, cena, wygląd), proponuje konspekt i pisze lekcje oraz quizy. Każdy blok cytuje swój fragment; sprawdzenie oparcia oznacza twierdzenia, których źródło nie potwierdza. | Dostępne |
| 3. Diff do przejrzenia | Każda zmiana AI przychodzi jako diff: przyjmij całość, przyjmij element po elemencie albo odrzuć. Nic nie trafia do akademii przed zatwierdzeniem, a publikacja to osobny krok. | Dostępne |
| 4. Żywy kurs | Gdy źródło się zmienia, ULAMS znajduje zmienione fragmenty, wypisuje lekcje i pytania, które je cytują, i proponuje aktualizacje jako diff. Uczący się zachowują postęp, a wersje kursu można porównać obok siebie. | Dostępne |
| 5. Wersje | Z tych samych źródeł kreator przygotowuje wersje dla początkujących, ekspertów lub wskazanej roli, każdą przeglądaną jako diff względem kursu bazowego. | Dostępne |
| 5a. Kierowanie | Każdy uczący się trafia do właściwej wersji: z własnego wyboru, przez opcjonalny test poziomujący lub decyzją administratora. | Wkrótce |
| 6. Dowody | Analityka kursu pokazuje, kto utknął i kto skończył; raporty zgodności pokazują, kto zalega z obowiązkowym szkoleniem, z eksportem CSV i JSON lines oraz śladem audytowym. | Dostępne |

Każde wywołanie modelu jest zapisywane z modelem, liczbą tokenów i kosztem, a każdy kurs ma limit wydatków.`,
    },
    architecture: {
      title: "Architektura i standardy",
      markdown: `### Headless i wielu najemców

ULAMS to REST API w Laravelu złożone z pakietów composera, z osobnymi frontendami: panelem administratora, Studio i referencyjną stroną dla uczących się, która domyślnie nie wysyła JavaScriptu. Każda akademia to najemca na własnej subdomenie, z własną bazą danych, magazynem plików, kluczami i motywem, więc token z jednej akademii jest odrzucany przez każdą inną.

### Standardy

| Obszar | Co obsługuje ULAMS | Status |
|---|---|---|
| Pakiety treści | SCORM, cmi5, H5P | Dostępne |
| Zapis aktywności | xAPI, z własnym magazynem LRS | Dostępne |
| Współpraca narzędzi | LTI 1.3, jako platforma i jako narzędzie | Dostępne |
| Logowanie jednokrotne | SAML 2.0 i OpenID Connect dla każdej akademii, obok logowania przez Google i Facebook | Dostępne |
| Mapowanie tożsamości | Mapowanie atrybutów, grup i ról, wymuszone SSO | Wkrótce |
| Provisioning | Użytkownicy i grupy przez SCIM 2.0 | Wkrótce |
| Dane kadrowe | Import z HRIS, najpierw CSV i API | Wkrótce |

### Wdrożenie

Jedno polecenie uruchamia cały stos na laptopie, serwerze z Dockerem lub klastrze Kubernetes, na Twoich serwerach lub w dowolnej chmurze. Lokalna próba używa deterministycznego dostawcy AI, więc nie generuje płatnych wywołań modelu. Helm chart jest w drodze. Kod jest otwarty na licencji MIT.`,
    },
    trust: {
      title: "Zaufanie i zgodność",
      markdown: `### AI przygotowuje szkic, decydują ludzie

Model proponuje, autor zatwierdza. Każda zmiana to diff z fragmentem, na którym się opiera, ślad audytowy zapisuje, kto ją zatwierdził i względem której wersji źródła, a twierdzenia bez oparcia w źródle są oznaczane, zanim ktokolwiek je opublikuje.

### AI Act

Artykuł 50 unijnego AI Act (rozporządzenie (UE) 2024/1689) nakłada obowiązki przejrzystości, w tym oznaczanie treści wygenerowanych przez AI. Planujemy je na **2 grudnia 2026**, czyli datę obowiązującą po pakiecie digital omnibus; sprawdź aktualny tekst skonsolidowany dla swojego przypadku.

- Dostępne: etykiety AI przy odpowiedziach tutora, sugestiach dodatkowej pomocy, informacji zwrotnej do refleksji i tematach lekcji tworzonych z pomocą AI.
- Wkrótce, przed tą datą: etykiety na pozostałych powierzchniach AI (szkice kreatora, wersje i eksporty).

### Dane zostają w Twoim wdrożeniu

Kursy, dane uczących się i magazyn xAPI są w bazie danych i magazynie plików Twojej instalacji. Na zewnątrz trafia tekst wysyłany do skonfigurowanego przez Ciebie dostawcy modelu, na potrzeby generowania, tutora i kontroli. Udokumentowana mapa przepływów danych jest w drodze, podobnie jak wybór dostawcy AI dla każdej akademii.

### Dostępność

Strony dla uczących się są budowane i testowane pod kątem WCAG 2.2 AA, a panel nie zapisze koloru akcentu, który nie spełnia kontrastu AA.`,
    },
    example: {
      title: "Przykład: dwa zmierzone przebiegi",
      markdown: `Dwa nagrane przebiegi kreatora kursów, każdy na źródle przygotowanym do demonstracji. Czasy i koszty pochodzą z zapisów samego Studio. To pojedyncze przebiegi: bez średniej i bez obietnicy dla Twoich treści.

| | Repozytorium dokumentacji | Strona Notion |
|---|---|---|
| Źródło | 13 fragmentów z publicznego repozytorium GitHub | 6 fragmentów z jednej strony Notion |
| Kurs | 6 lekcji, 18 pytań quizowych | 6 lekcji, 18 pytań quizowych |
| Oparcie w źródłach | 35 z 35 bloków cytuje źródło | 31 z 31 bloków cytuje źródło |
| Oznaczone przed publikacją | 3 możliwie nieoparte stwierdzenia | 1 możliwie nieoparte stwierdzenie |
| Czas | łącznie 7 min 44 s (konspekt 24 s, pisanie i kontrole 6 min 57 s, zastosowanie 23 s) | konspekt 27 s, pisanie i kontrole 4 min 58 s, zastosowanie 1 s |
| Koszt modelu | 0,28 USD, 27 wywołań modelu | 0,1973 USD, 26 wywołań modelu |

W obu przebiegach autor odpowiedział na osiem pytań, zatwierdził konspekt, przeczytał ostrzeżenia, potwierdził je i opublikował kurs. Czas przeglądu autora nie jest wliczony. W przebiegu na repozytorium późniejszy commit zmienił dwie liczby i dodał sekcję: ULAMS wypisał do przeglądu 12 elementów w 3 lekcjach i zaproponował poprawki jako diff.

### Czego nie twierdzimy

Żadnych zaoszczędzonych godzin, żadnego przelicznika względem punktu odniesienia (to mierzy co innego), żadnego zwrotu z inwestycji i żadnych referencji. Zmierzony wskaźnik czasu tworzenia i kosztu AI na kurs, na Twoich danych, jest w drodze.`,
    },
    try: {
      title: "Jak spróbować",
      markdown: `1. **Otwórz demo.** Sześć akademii demonstracyjnych, każda z własnym motywem i kursami, wraz z panelami administratora.
2. **Przeczytaj studia przypadku.** Każdy ekran przebiegów na repozytorium dokumentacji i stronie Notion, z podpisami i nagraniami.
3. **Uruchom u siebie.** Zainstaluj CLI i uruchom \`ulams stack up\`: kompletna lokalna akademia z deterministycznym dostawcą AI i bez płatnych wywołań modelu.
4. **Porozmawiaj z nami.** Formularz kontaktowy na stronie głównej trafia do zespołu ULAMS.`,
    },
  },
  sourcesTitle: "Źródła",
  sources: [
    { label: "Wskaźniki Chapman Alliance w opisie eLearning Industry", href: CHAPMAN_SOURCE },
    { label: "Magazyn Training o badaniach LMS Brandon Hall Group (2012, 2013)", href: BRANDON_HALL_SOURCE },
    { label: "Rozporządzenie (UE) 2024/1689 (AI Act), EUR-Lex", href: AI_ACT_SOURCE },
    { label: "Studium przypadku: od repozytorium dokumentacji do kursu", href: "/pl/course-generation/docs-repo/" },
    { label: "Studium przypadku: od strony Notion do kursu", href: "/pl/course-generation/notion-page/" },
    { label: "Dokumentacja ULAMS", href: DOCS },
  ],
  ctaTitle: "Zobacz to na własnych materiałach",
  ctaText: "Otwórz akademię demonstracyjną albo uruchom stos lokalnie, bez płatnych wywołań modelu.",
  install: "Instrukcja instalacji",
};

const ZH: WhitePaperCopy = {
  title: "ulams — 白皮书：从政策到证据",
  description: "ULAMS 如何构建并维护有出处的课程：成本问题、运作机制、架构与标准、信任与《人工智能法》，以及一次实测运行。",
  eyebrow: "白皮书",
  heading: "从政策到证据：ULAMS 如何构建并维护有出处的课程",
  subtitle: "写给培训与发展负责人、教学设计师以及负责审批培训的人。",
  body: "制作和维护在线课程要花多少成本，为什么报表令人失望，以及 ULAMS 如何把你自己的文档变成有出处、随来源更新的课程，并由人来批准每一处修改。",
  primary: "阅读白皮书",
  secondary: "浏览演示",
  facts: [
    { label: "人工制作基准", value: "49–716 小时", note: "每 1 小时成品（Chapman）" },
    { label: "一次实测", value: "7 分 44 秒", note: "带引用的草稿，审阅前" },
    { label: "该次运行的依据", value: "35/35", note: "个内容块标注了出处" },
  ],
  version: "2026 年 10 月版。状态以当月为准。",
  contents: "目录",
  print: "打印 / 另存为 PDF",
  printNote: "浏览器的打印对话框可以把本页另存为 PDF。打印版式会去掉网站导航，并写出每个链接的地址。",
  sections: {
    summary: {
      title: "摘要",
      markdown: `- **成本在于制作和维护。** 行业比例显示，人工制作的在线课程每 1 小时成品需要 49 至 716 小时工作量，上线后内容仍会持续产生成本。
- **报表令人失望。** 在 Brandon Hall Group 2013 年的调查中，报表不足是买家想更换 LMS 的主要原因之一。
- **ULAMS 从你的来源出发。** 它根据你的文档起草课程，为每个内容块标注依据的段落，标出来源不支持的内容，并在来源变化时跟进。
- **由人决定。** 每一处 AI 修改都是可审阅的差异。在作者批准之前，什么都不会到达学员，审计记录会写明谁批准了什么。
- **开放且符合标准。** MIT 许可，自托管，无头架构，内置 SCORM、cmi5、xAPI 和 LTI 1.3。
- **实测，而非承诺。** 一次录制的运行把一个含 13 个段落的文档仓库变成了 6 节课、18 道测验题的草稿，用时 7 分 44 秒，花费 0.28 美元。我们不公布节省了多少工时。`,
    },
    problem: {
      title: "问题：内容昂贵，报表薄弱",
      markdown: `### 制作一门课要数百小时

Chapman Alliance 的开发比例为**每 1 小时成品在线课程需要 49 至 716 小时工作量**，取决于交互程度。该研究发表于 2010 年，请当作数量级参考，而不是你项目的报价。业内人士还指出，上线后保持内容更新需要额外投入，而基于政策的培训每逢政策变化都要随之修改。

### 保持内容准确是第二份工作

根据政策写成的课程在发布当天是正确的。政策一旦变化，就得有人找出引用它的每一节课和每一道测验题。如果每条陈述都没有与来源相连，这种查找只能靠人工，过时的培训会在无人察觉的情况下继续上线。

### 买家在报表上失去耐心

在 Brandon Hall Group 2013 年的调查中（由 Training 杂志报道），457 名受访者里有 **45.5%** 对其 LMS 的价值给予高分，**47.7%** 正在寻找新的供应商；报表不足是主要原因之一。在 2012 年的调查中，报表问题排名第一。管理者和审计人员需要知道谁卡住了、谁学完了、谁还没有完成必修培训，而不必借助电子表格。`,
    },
    mechanism: {
      title: "机制：来源、带引用的草稿、差异、活课程、版本、证据",
      markdown: `| 步骤 | 发生了什么 | 状态 |
|---|---|---|
| 1. 来源 | 连接 Git 仓库、Google Drive 或 Notion，或上传 PDF、Word、Markdown 或文本。来源被拆成可以引用的短段落。上传的内容只被当作数据，绝不当作指令。 | 已提供 |
| 2. 带引用的草稿 | 生成器访谈作者（受众、水平、时长、语气、测验、语言、版本、价格、外观），提出大纲并撰写课时和测验。每个内容块都引用其段落；依据检查会标出来源不支持的说法。 | 已提供 |
| 3. 可审阅的差异 | 每一处 AI 修改都以差异形式提交：全部接受、逐项接受或拒绝。批准前不会写入学院，发布是单独的一步。 | 已提供 |
| 4. 活课程 | 来源变化时，ULAMS 找出变化的段落，列出引用它们的课时和题目，并以差异形式提出更新。学员保留进度，课程版本可以并排比较。 | 已提供 |
| 5. 版本 | 根据同样的来源，生成器为初学者、专家或指定角色起草版本，每个版本都以相对基础课程的差异来审阅。 | 已提供 |
| 5a. 分配 | 每位学员进入合适的版本：由学员选择、通过可选的分级测试或由管理员分配。 | 即将推出 |
| 6. 证据 | 按课程的分析显示谁卡住了、谁学完了；合规报表显示谁还欠必修培训，支持 CSV 和 JSON lines 导出以及审计记录。 | 已提供 |

每次模型调用都会记录模型、令牌数和费用，每门课程都有支出上限。`,
    },
    architecture: {
      title: "架构与标准",
      markdown: `### 无头与多租户

ULAMS 是由 composer 包构成的 Laravel REST API，配有独立的前端：管理后台、Studio，以及默认不发送 JavaScript 的参考学员网站。每个学院都是位于自己子域名上的租户，拥有自己的数据库、存储桶、密钥和主题，因此一个学院的令牌会被其他所有学院拒绝。

### 标准

| 领域 | ULAMS 支持的内容 | 状态 |
|---|---|---|
| 内容包 | SCORM、cmi5、H5P | 已提供 |
| 学习记录 | xAPI，配有你自己掌控的学习记录存储 | 已提供 |
| 工具互操作 | LTI 1.3，既可作为平台也可作为工具 | 已提供 |
| 单点登录 | 每个学院可用 SAML 2.0 和 OpenID Connect，另有 Google 和 Facebook 登录 | 已提供 |
| 身份映射 | 属性、群组和角色映射，强制 SSO | 即将推出 |
| 账号同步 | SCIM 2.0 用户和群组 | 即将推出 |
| 人事数据 | HRIS 导入，先支持 CSV 和 API | 即将推出 |

### 部署

一条命令即可在笔记本电脑、Docker 服务器或 Kubernetes 集群上启动整套系统，可以部署在你自己的服务器或任何云上。本地试用使用确定性的 AI 提供方，因此不会产生付费模型调用。Helm chart 即将推出。代码以 MIT 许可开源。`,
    },
    trust: {
      title: "信任与合规",
      markdown: `### AI 起草，人来决定

模型提出建议，作者批准。每一处修改都是附带其依据段落的差异，审计记录会写明谁批准了它以及针对来源的哪个版本，来源不支持的陈述会在任何人发布之前被标出。

### 欧盟《人工智能法》

欧盟《人工智能法》（条例 (EU) 2024/1689）第 50 条规定了透明度义务，包括标注 AI 生成的内容。我们为这些义务设定的规划日期是 **2026 年 12 月 2 日**，即数字综合方案（digital omnibus）之后适用的日期；请根据你的情况查阅最新的合并文本。

- 已提供：在导师回答、额外帮助建议、反思反馈以及 AI 辅助的课时主题上显示 AI 标签。
- 即将推出，在该日期之前：其余 AI 界面（生成器草稿、版本和导出）上的标签。

### 你的数据留在你的部署中

课程、学员记录和 xAPI 存储都位于你自己安装的数据库和存储中。离开部署的是发送给你所配置的模型提供方的文本，用于生成、辅导和检查。成文的数据流图即将推出，按学院选择 AI 提供方也即将推出。

### 无障碍

面向学员的页面按 WCAG 2.2 AA 构建和测试，管理后台不会保存不符合 AA 对比度的强调色。`,
    },
    example: {
      title: "实例：两次实测运行",
      markdown: `课程生成器的两次录制运行，每次都使用为演示而写的来源。时间和费用取自 Studio 自己的记录。它们都是单次运行：不是平均值，也不是对你内容的承诺。

| | 文档仓库 | Notion 页面 |
|---|---|---|
| 来源 | 公开 GitHub 仓库中的 13 个段落 | 一个 Notion 页面中的 6 个段落 |
| 课程 | 6 节课，18 道测验题 | 6 节课，18 道测验题 |
| 依据 | 35 个块全部标注了出处 | 31 个块全部标注了出处 |
| 发布前被标出 | 3 条可能缺乏依据的陈述 | 1 条可能缺乏依据的陈述 |
| 时间 | 共 7 分 44 秒（大纲 24 秒，撰写与检查 6 分 57 秒，应用 23 秒） | 大纲 27 秒，撰写与检查 4 分 58 秒，应用 1 秒 |
| 模型成本 | 0.28 美元，27 次模型调用 | 0.1973 美元，26 次模型调用 |

两次运行中，作者都回答了八个问题、批准大纲、阅读并确认警告，然后发布。作者的审阅时间不计在内。在文档仓库那次运行中，后来的一次提交改了两个数字并新增一节：ULAMS 列出了 3 节课中的 12 个元素供审阅，并以差异形式提出了修改。

### 我们不声称的内容

不声称节省了多少工时，不与基准给出“快多少倍”的比例（两者度量的对象不同），不提投资回报，也没有客户评价。基于你自己数据的、按课程衡量制作时间和 AI 成本的指标即将推出。`,
    },
    try: {
      title: "如何试用",
      markdown: `1. **打开在线演示。** 六个演示学院，各有自己的主题和课程，并附管理后台。
2. **阅读案例。** 文档仓库和 Notion 页面两次运行的每个界面，附说明和录屏。
3. **自己运行。** 安装 CLI 并运行 \`ulams stack up\`：一个完整的本地学院，使用确定性的 AI 提供方，不产生付费模型调用。
4. **联系我们。** 首页的联系表单会直达 ULAMS 团队。`,
    },
  },
  sourcesTitle: "来源",
  sources: [
    { label: "eLearning Industry 转述的 Chapman Alliance 开发比例", href: CHAPMAN_SOURCE },
    { label: "Training 杂志关于 Brandon Hall Group LMS 调查的报道（2012、2013）", href: BRANDON_HALL_SOURCE },
    { label: "条例 (EU) 2024/1689（《人工智能法》），EUR-Lex", href: AI_ACT_SOURCE },
    { label: "案例：从文档仓库到课程", href: "/zh/course-generation/docs-repo/" },
    { label: "案例：从 Notion 页面到课程", href: "/zh/course-generation/notion-page/" },
    { label: "ULAMS 文档", href: DOCS },
  ],
  ctaTitle: "用你自己的资料来看看",
  ctaText: "打开一个演示学院，或在本地启动整套系统，不产生付费模型调用。",
  install: "安装指南",
};

const COPY: Record<Locale, WhitePaperCopy> = { en: EN, pl: PL, zh: ZH };

export const whitePaperCopy = (locale: Locale): WhitePaperCopy => COPY[locale];

/** The links of the closing call to action: the demos on the home page of the language and the install guide. */
export const whitePaperLinks = (locale: Locale) => ({ demos: `${locale === "en" ? "" : `/${locale}`}/#demos`, install: INSTALL });

/** The /white-paper/ page shell in one language (lib/product-pages.ts); WhitePaperBody renders the sections. */
export function whitePaperPage(locale: Locale, request: Request, url: URL) {
  const c = COPY[locale];
  return productShell("white-paper", locale, {
    title: c.title,
    description: c.description,
    eyebrow: c.eyebrow,
    heading: c.heading,
    subtitle: c.subtitle,
    body: c.body,
    primary: { label: c.primary, href: "#summary" },
    secondary: { label: c.secondary, href: whitePaperLinks(locale).demos },
    facts: c.facts,
  }, request, url, "Article");
}
