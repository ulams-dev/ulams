/**
 * Copy for the public course-generation page and its two case studies. The screenshots and
 * screencasts are a real Studio run: a Scientific Reports paper and the November 2021 US
 * interagency stablecoin report. The same frames, in the same order, in every language.
 */
import type { Locale } from "../i18n/locales.ts";

export const CASE_IDS = ["microdosing-study", "stablecoin-report"] as const;
export type CaseId = (typeof CASE_IDS)[number];

/** Learner URLs of the two recorded courses, each on its own demo academy. */
export const COURSE_LINKS: Record<CaseId, string> = {
  "microdosing-study": "https://microdosing.ulams.app/learn/1/1",
  "stablecoin-report": "https://stablecoins.ulams.app/learn/1/1",
};

/** Public PDFs the two courses cite. */
export const SOURCE_LINKS: Record<CaseId, string> = {
  "microdosing-study": "https://www.nature.com/articles/s41598-021-01811-4.pdf",
  "stablecoin-report": "https://home.treasury.gov/system/files/136/StableCoinReport_Nov1_508.pdf",
};

export interface ShotGeometry {
  file: string;
  width: number;
  height: number;
}

export const SHOTS: Record<CaseId, { folder: string; frames: ShotGeometry[] }> = {
  "microdosing-study": {
    folder: "microdosing",
    frames: [
      { file: "01-sign-in.png", width: 1440, height: 900 },
      { file: "02-studio.png", width: 1440, height: 900 },
      { file: "03-audience.png", width: 1425, height: 1400 },
      { file: "04-shell.png", width: 1425, height: 1400 },
      { file: "05-style.png", width: 1425, height: 1400 },
      { file: "06-outline.png", width: 1425, height: 1400 },
      { file: "07-generating.png", width: 1425, height: 1400 },
      { file: "08-apply.png", width: 1425, height: 1400 },
      { file: "09-checks.png", width: 1425, height: 1006 },
      { file: "10-published.png", width: 1425, height: 1370 },
      { file: "11-course.png", width: 1425, height: 1400 },
      { file: "12-lesson.png", width: 1425, height: 1400 },
    ],
  },
  "stablecoin-report": {
    folder: "stablecoins",
    frames: [
      { file: "01-sign-in.png", width: 1440, height: 900 },
      { file: "02-studio.png", width: 1440, height: 900 },
      { file: "03-audience.png", width: 1425, height: 1400 },
      { file: "04-shell.png", width: 1425, height: 1400 },
      { file: "05-style.png", width: 1425, height: 1400 },
      { file: "06-outline.png", width: 1425, height: 1400 },
      { file: "07-generating.png", width: 1425, height: 1400 },
      { file: "08-apply.png", width: 1425, height: 1400 },
      { file: "09-checks.png", width: 1425, height: 1006 },
      { file: "10-published.png", width: 1440, height: 1337 },
      { file: "11-course.png", width: 1440, height: 1400 },
      { file: "12-lesson.png", width: 1440, height: 1400 },
    ],
  },
};

export interface OverviewCopy {
  title: string;
  description: string;
  eyebrow: string;
  heading: string;
  subtitle: string;
  body: string;
  primary: string;
  secondary: string;
  stepsTitle: string;
  stepsIntro: string;
  steps: Array<{ title: string; text: string }>;
  casesTitle: string;
  casesIntro: string;
  cards: Array<{ title: string; text: string; facts: Array<{ label: string; value: string }>; cta: string }>;
  note: string;
  boundaryTitle: string;
  boundary: string;
  ctaTitle: string;
  ctaText: string;
  cta: string;
  docs: string;
}

export interface CaseCopy {
  title: string;
  description: string;
  eyebrow: string;
  heading: string;
  subtitle: string;
  body: string;
  facts: Array<{ label: string; value: string }>;
  boundaryTitle: string;
  boundary: string;
  buildHeading: string;
  buildText: string;
  buildVideo: string;
  publishHeading: string;
  publishText: string;
  publishVideo: string;
  stepsTitle: string;
  stepsIntro: string;
  steps: Array<{ title: string; text: string }>;
  framesTitle: string;
  framesIntro: string;
  captions: string[];
  back: string;
  other: string;
  preview: string;
  previewTitle: string;
  previewText: string;
  source: string;
  sourceTitle: string;
  sourceText: string;
  previous: string;
  next: string;
}

export interface GenerationCopy {
  overview: OverviewCopy;
  cases: Record<CaseId, CaseCopy>;
}

const en: GenerationCopy = {
  overview: {
    title: "ulams — How a course is generated",
    description: "Upload a source, answer the interview, approve the outline, then publish a course where every block cites that source.",
    eyebrow: "Course Builder",
    heading: "A course from your source, with you in the loop",
    subtitle: "The builder asks. You answer. Nothing is published until you approve it.",
    body: "A PDF, Markdown or DOCX file goes in. The interview sets the audience, the length, the tone and how learners should move through the course. The outline shows the lessons and the pages they will cite. You approve it, then you apply and publish.",
    primary: "See the two case studies",
    secondary: "Read the docs",
    stepsTitle: "What happens, in order",
    stepsIntro: "The author stays in the loop at the outline, at apply and at publish.",
    steps: [
      { title: "Upload the source", text: "PDF, Markdown or DOCX. The text of that file is what later lessons are allowed to cite." },
      { title: "Answer the interview", text: "Audience, level, duration, tone, assessments, language, the shape of the course, the kind of activity, what learners already know, and the style." },
      { title: "Approve the outline", text: "Modules and lessons appear with the source pages they will use. Writing starts only after you approve." },
      { title: "Review the lessons", text: "Each block is checked against the cited pages. Unsupported claims are flagged before anyone can publish." },
      { title: "Apply, then publish", text: "Apply creates the course through the academy's own services. Publishing is a separate step, after the checks." },
    ],
    casesTitle: "Two recorded builds",
    casesIntro: "The same Studio, two public documents, every interview question answered on screen.",
    cards: [
      {
        title: "Reading a microdosing survey",
        text: "A Scientific Reports paper becomes a short course on what the survey found and where its conclusions stop.",
        facts: [
          { label: "Source", value: "Scientific Reports, 2021" },
          { label: "Course", value: "30 min · cited" },
          { label: "Shape", value: "Standard course" },
        ],
        cta: "Read the case study",
      },
      {
        title: "Reading the stablecoin report",
        text: "The November 2021 US interagency report becomes a course on payment stablecoins and the risks the authors name.",
        facts: [
          { label: "Source", value: "PWG report, Nov 2021" },
          { label: "Course", value: "30 min · cited" },
          { label: "Asked for", value: "Slides" },
        ],
        cta: "Read the case study",
      },
    ],
    note: "Recorded on a local academy. The courses teach the documents. They are not instructions for using what those documents discuss.",
    boundaryTitle: "The author publishes",
    boundary: "AI drafts the outline and the lessons. The author approves the outline, applies the course and publishes it. A course with a blocking check cannot be published, and warnings have to be acknowledged first.",
    ctaTitle: "The same path for your own sources",
    ctaText: "Self-host the stack, or read how the builder and the citation checks work.",
    cta: "Course Builder guide",
    docs: "Documentation",
  },
  cases: {
    "microdosing-study": {
      title: "ulams — Case study: a microdosing survey",
      description: "How a Scientific Reports paper became a cited course on the survey's findings and the limits of those findings.",
      eyebrow: "Case study",
      heading: "Reading the Microdosing Survey",
      subtitle: "From a Scientific Reports PDF to a published course.",
      body: "The source is Anderson and colleagues, 2021: a survey of adults who microdose psychedelics and a comparison group. The course walks through who was studied, what participants reported, and where a self-selected survey cannot support a stronger claim.",
      facts: [
        { label: "Audience", value: "Clinicians reading the study" },
        { label: "Length", value: "About 30 minutes" },
        { label: "Shape", value: "Course with an outline" },
        { label: "Grounding", value: "Every block cited" },
      ],
      boundaryTitle: "Findings and limits only",
      boundary: "This course teaches how to read the survey. It does not explain how to obtain or use psychedelics. The lessons stay inside what the paper reports and what the authors say still needs study.",
      buildHeading: "The interview, the outline and generation",
      buildText: "Sign in, upload the PDF, answer every question, approve the outline, and wait while the lessons are written from the cited pages.",
      buildVideo: "Screencast: building the microdosing survey course, from sign-in through generation",
      publishHeading: "Apply, publish and open the course",
      publishText: "The author applies the course, reads the checks, acknowledges the heading warnings and publishes. The recording then opens the course as a learner.",
      publishVideo: "Screencast: applying, publishing and opening the microdosing survey course",
      stepsTitle: "What was decided",
      stepsIntro: "These are the answers given in the recording, and what the academy then held.",
      steps: [
        { title: "Who it is for", text: "Clinicians and researchers reading the study. Teach the findings and the limits. Beginner, academic tone, a quiz, English." },
        { title: "How it should feel", text: "A standard course with an outline. Balanced activity, some prior knowledge, editorial style. No design system was uploaded." },
        { title: "The outline", text: "Two modules: what the survey found, and where the evidence stops. Six lessons, including a closing check." },
        { title: "The checks", text: "Every lesson block cites the paper. Nine questions passed the support check. No grounding flags." },
        { title: "Published", text: "Free, Gravity theme, about 30 minutes. Heading-level warnings were shown and acknowledged before publish." },
      ],
      framesTitle: "Each screen, in order",
      framesIntro: "Arrows at the top move through the Studio screens. The note sits above the image, and the image fills the width. Open it for full size.",
      captions: [
        "Sign in to the Studio of the academy used for this recording.",
        "The Studio home, before a source is uploaded.",
        "The first question asks who the course is for. The answer is clinicians and researchers reading the study: teach the findings and the limits.",
        "The interview asks how learners should move: a course with an outline, one scrolling page, or slides. This run chooses the standard course.",
        "No design system was uploaded, so the interview asks for an overall style. This run chooses editorial.",
        "The proposed outline: Reading the Microdosing Survey: Findings and Limits. Two modules, six lessons. Writing starts only after Approve.",
        "Lessons are generated against the cited pages of the paper. The course is not published at this point.",
        "Apply creates the course through the academy's own services. The author chooses this step.",
        "Before publishing: every block cites the paper, quiz answers are supported, and the grounding check reports no unsupported claims.",
        "The course is published. Heading warnings were listed and acknowledged. The button now reads Published.",
        "The learner view of the published course, about 30 minutes, in English, on the Gravity theme.",
        "A lesson opened from the outline. The page keeps the source the builder cited.",
      ],
      back: "All case studies",
      other: "The stablecoin report",
      preview: "Open the published course",
      previewTitle: "The source and the published course",
      previewText: "The PDF the builder cited, and the course it produced. Open either: the paper is the material, the course is a reading of its findings and limits.",
      source: "Open the source PDF",
      sourceTitle: "The source and the published course",
      sourceText: "The PDF the builder cited, and the course it produced. Open either: the paper is the material, the course is a reading of its findings and limits.",
      previous: "Previous",
      next: "Next",
    },
    "stablecoin-report": {
      title: "ulams — Case study: the stablecoin report",
      description: "How the November 2021 US stablecoin report became a cited course on arrangements, risks and the authors' recommendations.",
      eyebrow: "Case study",
      heading: "Payment Stablecoins: Risks and Regulatory Recommendations",
      subtitle: "From the November 2021 interagency report to a published course.",
      body: "The source is the President's Working Group report on stablecoins, with the FDIC and the OCC. The course covers how payment stablecoins are minted, redeemed and reserved, which risks the report names, and which legislative steps it recommends.",
      facts: [
        { label: "Audience", value: "Policy staff and students" },
        { label: "Length", value: "About 30 minutes" },
        { label: "Asked for", value: "Slides, scenarios" },
        { label: "Grounding", value: "Every block cited" },
      ],
      boundaryTitle: "A reading of the report",
      boundary: "This course teaches the report. It is not legal, investment or product advice. The interview asked for slides. That answer is stored on the brief. The learner view in this recording is the standard course player, with an outline and lessons.",
      buildHeading: "The interview, the outline and generation",
      buildText: "The PDF is uploaded, all twelve questions are answered, the outline is approved, and the lessons are written from the cited pages of the report.",
      buildVideo: "Screencast: building the stablecoin report course, from sign-in through generation",
      publishHeading: "Publish, after the checks",
      publishText: "The checks pass. Two heading warnings have to be acknowledged. The author then publishes, and the course can be opened.",
      publishVideo: "Screencast: publishing the stablecoin report course",
      stepsTitle: "What was decided",
      stepsIntro: "The interview asked for a longer, slide-shaped course. The published course is the shorter course the outline produced, in the standard player.",
      steps: [
        { title: "Who it is for", text: "Policy staff and finance students. Intermediate, professional tone, a quiz, English, about 60 minutes requested." },
        { title: "Shape and style", text: "Slides, scenario activities, some prior knowledge, technical style. The brief stores slides. The player in this run is the course outline." },
        { title: "The outline", text: "Two modules: how stablecoin arrangements work, and the risks plus the recommended response. Four lessons were proposed." },
        { title: "The checks", text: "Nineteen blocks cite the report. Nine questions passed the support check. No grounding flags." },
        { title: "Published", text: "Free, Gravity theme, about 30 minutes. Two heading warnings were acknowledged, then the course was published." },
      ],
      framesTitle: "Each screen, in order",
      framesIntro: "Arrows at the top move through the Studio screens. The note sits above the image, and the image fills the width. Open it for full size.",
      captions: [
        "Sign in to the Studio of the academy used for this recording.",
        "The Studio home, before the report is uploaded.",
        "The first question asks who the course is for. The answer is policy staff and finance students reading the report.",
        "The interview asks how learners should move. This run answers slides. The brief stores that. The player opened later is still the standard course.",
        "No design system was uploaded. The style chosen here is technical, with scenario activities.",
        "The approved outline: how arrangements work, then the risks and the recommended response. Generation starts only after Approve.",
        "Lessons are written from the cited pages of the November 2021 report. The course is not published yet.",
        "Apply creates the course in the academy. The summary shows two modules and the run's citation check.",
        "The pre-publish checks: citations, supported quiz answers, and no grounding flags.",
        "Publishing required acknowledging heading-level warnings. The course is then published.",
        "The learner course page. The interview asked for slides and about 60 minutes. This page is the standard player, about 30 minutes.",
        "The lesson Minting, Redemption, Reserves and Transfer, with the report named as the source.",
      ],
      back: "All case studies",
      other: "The microdosing survey",
      preview: "Open the published course",
      previewTitle: "The source and the published course",
      previewText: "The PDF the builder cited, and the course it produced. Open either: the report is the material, the course is a reading of it, not legal or investment advice.",
      source: "Open the source PDF",
      sourceTitle: "The source and the published course",
      sourceText: "The PDF the builder cited, and the course it produced. Open either: the report is the material, the course is a reading of it, not legal or investment advice.",
      previous: "Previous",
      next: "Next",
    },
  },
};

const pl: GenerationCopy = {
  overview: {
    title: "ulams — Jak powstaje kurs",
    description: "Wgrywasz źródło, odpowiadasz na wywiad, zatwierdzasz konspekt i publikujesz kurs, w którym każdy blok cytuje to źródło.",
    eyebrow: "Course Builder",
    heading: "Kurs ze źródła, z autorem w pętli",
    subtitle: "Builder pyta. Ty odpowiadasz. Nic nie jest publikowane, dopóki tego nie zatwierdzisz.",
    body: "Na wejściu jest PDF, Markdown albo DOCX. Wywiad ustala odbiorców, czas, ton i sposób przejścia przez kurs. Konspekt pokazuje lekcje i strony, które będą cytowane. Zatwierdzasz go, a potem stosujesz kurs i publikujesz.",
    primary: "Zobacz dwa studia przypadku",
    secondary: "Czytaj dokumentację",
    stepsTitle: "Co dzieje się po kolei",
    stepsIntro: "Autor zostaje w pętli przy konspekcie, przy zastosowaniu i przy publikacji.",
    steps: [
      { title: "Wgraj źródło", text: "PDF, Markdown albo DOCX. Tekst tego pliku jest tym, co późniejsze lekcje mogą cytować." },
      { title: "Odpowiedz na wywiad", text: "Odbiorcy, poziom, czas, ton, sprawdzanie, język, kształt kursu, rodzaj aktywności, to, co uczący się już wiedzą, i styl." },
      { title: "Zatwierdź konspekt", text: "Moduły i lekcje pojawiają się ze stronami źródła. Pisanie zaczyna się dopiero po zatwierdzeniu." },
      { title: "Przejrzyj lekcje", text: "Każdy blok jest sprawdzany wobec cytowanych stron. Tezy bez oparcia są oznaczane, zanim ktokolwiek opublikuje kurs." },
      { title: "Zastosuj, potem publikuj", text: "Zastosowanie tworzy kurs przez usługi akademii. Publikacja jest osobnym krokiem, po sprawdzeniach." },
    ],
    casesTitle: "Dwa nagrane przebiegi",
    casesIntro: "To samo Studio, dwa publiczne dokumenty, każde pytanie wywiadu odpowiedziane na ekranie.",
    cards: [
      {
        title: "Czytanie ankiety o mikrodawkowaniu",
        text: "Artykuł z Scientific Reports staje się krótkim kursem o tym, co ankieta znalazła i gdzie kończą się jej wnioski.",
        facts: [
          { label: "Źródło", value: "Scientific Reports, 2021" },
          { label: "Kurs", value: "30 min · cytowany" },
          { label: "Kształt", value: "Zwykły kurs" },
        ],
        cta: "Czytaj studium",
      },
      {
        title: "Czytanie raportu o stablecoinach",
        text: "Raport amerykańskich agencji z listopada 2021 staje się kursem o stablecoinach płatniczych i ryzykach, które autorzy nazywają.",
        facts: [
          { label: "Źródło", value: "Raport PWG, lis 2021" },
          { label: "Kurs", value: "30 min · cytowany" },
          { label: "Prośba", value: "Slajdy" },
        ],
        cta: "Czytaj studium",
      },
    ],
    note: "Nagrane na lokalnej akademii. Kursy uczą dokumentów. Nie są instrukcją używania tego, o czym te dokumenty mówią.",
    boundaryTitle: "Publikuje autor",
    boundary: "AI szkicuje konspekt i lekcje. Autor zatwierdza konspekt, stosuje kurs i go publikuje. Kursu z blokującą kontrolą nie da się opublikować, a ostrzeżenia trzeba najpierw potwierdzić.",
    ctaTitle: "Ta sama ścieżka dla własnych źródeł",
    ctaText: "Postaw stos u siebie albo przeczytaj, jak działają builder i kontrola cytowań.",
    cta: "Przewodnik Course Buildera",
    docs: "Dokumentacja",
  },
  cases: {
    "microdosing-study": {
      title: "ulams — Studium: ankieta o mikrodawkowaniu",
      description: "Jak artykuł z Scientific Reports stał się cytowanym kursem o wynikach ankiety i o granicy tych wyników.",
      eyebrow: "Studium przypadku",
      heading: "Czytanie ankiety o mikrodawkowaniu",
      subtitle: "Od PDF-u z Scientific Reports do opublikowanego kursu.",
      body: "Źródłem jest Anderson i współpracownicy, 2021: ankieta dorosłych, którzy mikrodawkują psychodeliki, oraz grupy porównawczej. Kurs prowadzi przez to, kogo badano, co uczestnicy zgłaszali i gdzie ankieta ochotników nie uniesie mocniejszej tezy.",
      facts: [
        { label: "Odbiorcy", value: "Klinicyści czytający badanie" },
        { label: "Czas", value: "Około 30 minut" },
        { label: "Kształt", value: "Kurs ze spisem" },
        { label: "Oparcie", value: "Każdy blok cytowany" },
      ],
      boundaryTitle: "Tylko wyniki i ich granice",
      boundary: "Ten kurs uczy, jak czytać ankietę. Nie wyjaśnia, jak zdobyć ani użyć psychodelików. Lekcje zostają przy tym, co podaje artykuł i co autorzy uznają za nadal wymagające badań.",
      buildHeading: "Wywiad, konspekt i generowanie",
      buildText: "Logowanie, wgranie PDF-u, odpowiedź na każde pytanie, zatwierdzenie konspektu i czekanie, aż lekcje powstaną z cytowanych stron.",
      buildVideo: "Nagranie: budowa kursu o ankiecie, od logowania do generowania",
      publishHeading: "Zastosowanie, publikacja i otwarcie kursu",
      publishText: "Autor stosuje kurs, czyta kontrole, potwierdza ostrzeżenia o nagłówkach i publikuje. Nagranie otwiera potem kurs jako uczący się.",
      publishVideo: "Nagranie: zastosowanie, publikacja i otwarcie kursu o ankiecie",
      stepsTitle: "Co zostało ustalone",
      stepsIntro: "To odpowiedzi z nagrania i to, co akademia potem przechowała.",
      steps: [
        { title: "Dla kogo", text: "Klinicyści i badacze czytający badanie. Uczyć wyników i ich granic. Poziom początkujący, ton akademicki, quiz, angielski." },
        { title: "Jaki ma być", text: "Zwykły kurs ze spisem. Aktywność zrównoważona, pewna wiedza wstępna, styl redakcyjny. Systemu projektu nie wgrano." },
        { title: "Konspekt", text: "Dwa moduły: co ankieta znalazła i gdzie kończą się dowody. Sześć lekcji, w tym sprawdzian na końcu." },
        { title: "Kontrole", text: "Każdy blok lekcji cytuje artykuł. Dziewięć pytań przeszło kontrolę oparcia. Brak flag o niepopartych tezach." },
        { title: "Opublikowany", text: "Bezpłatny, motyw Gravity, około 30 minut. Ostrzeżenia o poziomach nagłówków pokazano i potwierdzono przed publikacją." },
      ],
      framesTitle: "Każdy ekran po kolei",
      framesIntro: "Strzałki na górze przesuwają kadry Studia. Wyjaśnienie jest nad obrazem, a obraz zajmuje całą szerokość. Otwórz go, żeby zobaczyć pełny rozmiar.",
      captions: [
        "Logowanie do Studia akademii użytej w tym nagraniu.",
        "Strona główna Studia, zanim wgrano źródło.",
        "Pierwsze pytanie: dla kogo jest kurs. Odpowiedź: klinicyści i badacze czytający badanie, z naciskiem na wyniki i ich granice.",
        "Wywiad pyta, jak uczący się ma iść przez kurs: kurs ze spisem, jedna przewijana strona albo slajdy. Ten przebieg wybiera zwykły kurs.",
        "Systemu projektu nie wgrano, więc wywiad pyta o styl. Ten przebieg wybiera styl redakcyjny.",
        "Proponowany konspekt: czytanie ankiety, wyniki i granice. Dwa moduły, sześć lekcji. Pisanie zaczyna się po zatwierdzeniu.",
        "Lekcje powstają wobec cytowanych stron artykułu. W tym momencie kurs nie jest opublikowany.",
        "Zastosowanie tworzy kurs przez usługi akademii. Ten krok wybiera autor.",
        "Przed publikacją: każdy blok cytuje artykuł, odpowiedzi quizu mają oparcie, a kontrola nie zgłasza niepopartych tez.",
        "Kurs jest opublikowany. Ostrzeżenia o nagłówkach były wymienione i potwierdzone. Przycisk pokazuje, że opublikowano.",
        "Widok uczącego się: opublikowany kurs, około 30 minut, po angielsku, w motywie Gravity.",
        "Lekcja otwarta ze spisu. Strona zostawia źródło, które builder zacytował.",
      ],
      back: "Wszystkie studia",
      other: "Raport o stablecoinach",
      preview: "Otwórz opublikowany kurs",
      previewTitle: "Źródło i opublikowany kurs",
      previewText: "PDF, który builder cytował, i kurs, który z niego powstał. Otwórz którykolwiek: artykuł jest materiałem, kurs jest lekturą wyników i ich granic.",
      source: "Otwórz PDF źródła",
      sourceTitle: "Źródło i opublikowany kurs",
      sourceText: "PDF, który builder cytował, i kurs, który z niego powstał. Otwórz którykolwiek: artykuł jest materiałem, kurs jest lekturą wyników i ich granic.",
      previous: "Poprzedni",
      next: "Następny",
    },
    "stablecoin-report": {
      title: "ulams — Studium: raport o stablecoinach",
      description: "Jak raport o stablecoinach z listopada 2021 stał się cytowanym kursem o konstrukcji, ryzykach i zaleceniach autorów.",
      eyebrow: "Studium przypadku",
      heading: "Stablecoiny płatnicze: ryzyka i zalecenia",
      subtitle: "Od raportu agencji z listopada 2021 do opublikowanego kursu.",
      body: "Źródłem jest raport President's Working Group o stablecoinach, z FDIC i OCC. Kurs opisuje, jak stablecoiny płatnicze są emitowane, umarzane i zabezpieczane, jakie ryzyka raport nazywa i jakie kroki legislacyjne zaleca.",
      facts: [
        { label: "Odbiorcy", value: "Analitycy i studenci" },
        { label: "Czas", value: "Około 30 minut" },
        { label: "Prośba", value: "Slajdy, scenariusze" },
        { label: "Oparcie", value: "Każdy blok cytowany" },
      ],
      boundaryTitle: "Lektura raportu",
      boundary: "Ten kurs uczy raportu. Nie jest poradą prawną, inwestycyjną ani produktową. Wywiad poprosił o slajdy. Ta odpowiedź jest zapisana w briefie. Widok uczącego się w tym nagraniu to zwykły odtwarzacz kursu, ze spisem i lekcjami.",
      buildHeading: "Wywiad, konspekt i generowanie",
      buildText: "PDF zostaje wgrany, pada odpowiedź na wszystkie dwanaście pytań, konspekt jest zatwierdzony, a lekcje powstają z cytowanych stron raportu.",
      buildVideo: "Nagranie: budowa kursu o raporcie, od logowania do generowania",
      publishHeading: "Publikacja po kontrolach",
      publishText: "Kontrole przechodzą. Dwa ostrzeżenia o nagłówkach trzeba potwierdzić. Autor potem publikuje i kurs można otworzyć.",
      publishVideo: "Nagranie: publikacja kursu o raporcie stablecoinów",
      stepsTitle: "Co zostało ustalone",
      stepsIntro: "Wywiad prosił o dłuższy kurs w formie slajdów. Opublikowany kurs jest krótszy i idzie zwykłym odtwarzaczem.",
      steps: [
        { title: "Dla kogo", text: "Pracownicy polityk publicznych i studenci finansów. Poziom średni, ton profesjonalny, quiz, angielski, prośba o około 60 minut." },
        { title: "Kształt i styl", text: "Slajdy, scenariusze, pewna wiedza wstępna, styl techniczny. Brief pamięta slajdy. Odtwarzacz w tym przebiegu to spis kursu." },
        { title: "Konspekt", text: "Dwa moduły: jak działają porozumienia stablecoinowe oraz ryzyka i zalecana odpowiedź. Zaproponowano cztery lekcje." },
        { title: "Kontrole", text: "Dziewiętnaście bloków cytuje raport. Dziewięć pytań przeszło kontrolę oparcia. Brak flag." },
        { title: "Opublikowany", text: "Bezpłatny, motyw Gravity, około 30 minut. Dwa ostrzeżenia o nagłówkach potwierdzono, potem kurs opublikowano." },
      ],
      framesTitle: "Każdy ekran po kolei",
      framesIntro: "Strzałki na górze przesuwają kadry Studia. Wyjaśnienie jest nad obrazem, a obraz zajmuje całą szerokość. Otwórz go, żeby zobaczyć pełny rozmiar.",
      captions: [
        "Logowanie do Studia akademii użytej w tym nagraniu.",
        "Strona główna Studia, zanim wgrano raport.",
        "Pierwsze pytanie: dla kogo jest kurs. Odpowiedź: analitycy polityk i studenci finansów czytający raport.",
        "Wywiad pyta o sposób przejścia. Ten przebieg odpowiada: slajdy. Brief to zapisuje. Później otwarty odtwarzacz to nadal zwykły kurs.",
        "Systemu projektu nie wgrano. Wybrany styl jest techniczny, z aktywnościami scenariuszowymi.",
        "Zatwierdzony konspekt: jak działają porozumienia, potem ryzyka i zalecana odpowiedź. Generowanie startuje po zatwierdzeniu.",
        "Lekcje powstają z cytowanych stron raportu z listopada 2021. Kurs nie jest jeszcze opublikowany.",
        "Zastosowanie tworzy kurs w akademii. Podsumowanie pokazuje dwa moduły i kontrolę cytowań.",
        "Kontrole przed publikacją: cytowania, poparte odpowiedzi quizu i brak flag o niepopartych tezach.",
        "Publikacja wymagała potwierdzenia ostrzeżeń o poziomach nagłówków. Potem kurs jest opublikowany.",
        "Strona kursu dla uczącego się. Wywiad prosił o slajdy i około 60 minut. Ta strona to zwykły odtwarzacz, około 30 minut.",
        "Lekcja o emisji, umorzeniu, rezerwach i transferze. Raport jest nazwany jako źródło.",
      ],
      back: "Wszystkie studia",
      other: "Ankieta o mikrodawkowaniu",
      preview: "Otwórz opublikowany kurs",
      previewTitle: "Źródło i opublikowany kurs",
      previewText: "PDF, który builder cytował, i kurs, który z niego powstał. Otwórz którykolwiek: raport jest materiałem, kurs jest jego lekturą, nie poradą prawną ani inwestycyjną.",
      source: "Otwórz PDF źródła",
      sourceTitle: "Źródło i opublikowany kurs",
      sourceText: "PDF, który builder cytował, i kurs, który z niego powstał. Otwórz którykolwiek: raport jest materiałem, kurs jest jego lekturą, nie poradą prawną ani inwestycyjną.",
      previous: "Poprzedni",
      next: "Następny",
    },
  },
};

const zh: GenerationCopy = {
  overview: {
    title: "ulams — 课程是怎样生成的",
    description: "上传来源，回答访谈，批准大纲，再发布一门每一段都引用来源的课程。",
    eyebrow: "课程构建器",
    heading: "从来源生成课程，作者始终在环里",
    subtitle: "构建器提问。你来回答。未经你批准，不会发布。",
    body: "输入是 PDF、Markdown 或 DOCX。访谈确定受众、时长、语气，以及学习者如何通过课程。大纲列出课时和将引用的页码。你批准之后，再应用并发布。",
    primary: "看两个案例",
    secondary: "阅读文档",
    stepsTitle: "按顺序发生的事",
    stepsIntro: "作者在大纲、应用和发布这三处留在流程里。",
    steps: [
      { title: "上传来源", text: "PDF、Markdown 或 DOCX。后来的课时只能引用这份文件里的文字。" },
      { title: "回答访谈", text: "受众、水平、时长、语气、考核、语言、课程形态、活动类型、学习者已有基础，以及整体风格。" },
      { title: "批准大纲", text: "模块和课时连同将引用的来源页一起出现。只有批准之后才开始写课。" },
      { title: "审阅课时", text: "每一段都对照被引用的页面检查。无法支持的说法会在发布前被标出。" },
      { title: "先应用，再发布", text: "应用通过学院自己的服务创建课程。发布是检查通过之后的另一步。" },
    ],
    casesTitle: "两次录下的构建",
    casesIntro: "同一个 Studio，两份公开文档，屏幕上回答了每一个访谈问题。",
    cards: [
      {
        title: "阅读一项微剂量调查",
        text: "一篇 Scientific Reports 论文变成一门短课，说明调查发现了什么，以及结论停在哪里。",
        facts: [
          { label: "来源", value: "Scientific Reports，2021" },
          { label: "课程", value: "30 分钟 · 有引用" },
          { label: "形态", value: "标准课程" },
        ],
        cta: "阅读案例",
      },
      {
        title: "阅读稳定币报告",
        text: "2021 年 11 月的美国跨部门报告变成一门课，讲解支付型稳定币，以及作者点名的风险。",
        facts: [
          { label: "来源", value: "PWG 报告，2021 年 11 月" },
          { label: "课程", value: "30 分钟 · 有引用" },
          { label: "请求", value: "幻灯片" },
        ],
        cta: "阅读案例",
      },
    ],
    note: "录制于本地学院。课程讲解的是文档本身，不是使用文档所讨论事物的操作说明。",
    boundaryTitle: "由作者发布",
    boundary: "AI 起草大纲和课时。作者批准大纲、应用课程并发布。有阻断项的课程不能发布，警告也必须先确认。",
    ctaTitle: "你自己的来源走同一条路",
    ctaText: "自行托管整套系统，或阅读构建器与引用检查如何工作。",
    cta: "课程构建器指南",
    docs: "文档",
  },
  cases: {
    "microdosing-study": {
      title: "ulams — 案例：微剂量调查",
      description: "一篇 Scientific Reports 论文如何变成一门有引用的课，讲调查发现以及这些发现的边界。",
      eyebrow: "案例",
      heading: "阅读微剂量调查",
      subtitle: "从一篇 Scientific Reports PDF 到一门已发布的课程。",
      body: "来源是 Anderson 及其同事 2021 年的调查：一组自我报告微剂量使用致幻剂的成年人，以及一个对照组。课程走过研究对象、参与者报告的内容，以及一份自选样本的调查不能支撑更强结论的地方。",
      facts: [
        { label: "受众", value: "阅读该研究的临床人员" },
        { label: "时长", value: "约 30 分钟" },
        { label: "形态", value: "带大纲的课程" },
        { label: "依据", value: "每一段都有引用" },
      ],
      boundaryTitle: "只讲发现和边界",
      boundary: "这门课教的是如何阅读这项调查。它不说明如何获得或使用致幻剂。课时停留在论文报告的内容，以及作者认为仍需研究的部分。",
      buildHeading: "访谈、大纲和生成",
      buildText: "登录，上传 PDF，回答每一个问题，批准大纲，然后等待课时根据被引用的页面写出来。",
      buildVideo: "录屏：构建微剂量调查课程，从登录到生成",
      publishHeading: "应用、发布并打开课程",
      publishText: "作者应用课程，阅读检查，确认标题警告，然后发布。录屏接着以学习者身份打开课程。",
      publishVideo: "录屏：应用、发布并打开微剂量调查课程",
      stepsTitle: "当时做了哪些决定",
      stepsIntro: "这些是录屏里给出的回答，以及学院随后保存的结果。",
      steps: [
        { title: "给谁学", text: "阅读该研究的临床人员和研究人员。讲发现和边界。初级、学术语气、测验、英语。" },
        { title: "课程气质", text: "带大纲的标准课程。活动均衡，已有一些基础，社论风格。没有上传设计系统。" },
        { title: "大纲", text: "两个模块：调查发现了什么，以及证据停在哪里。六节课，包括结尾的检查。" },
        { title: "检查", text: "每个课时块都引用论文。九道题通过了依据检查。没有无依据说法的标记。" },
        { title: "已发布", text: "免费，Gravity 主题，约 30 分钟。标题层级警告已展示并确认，然后才发布。" },
      ],
      framesTitle: "按顺序的每一屏",
      framesIntro: "顶部箭头切换 Studio 画面。说明在图像上方，图像占满宽度。打开图像可看全尺寸。",
      captions: [
        "登录这次录制所用学院的 Studio。",
        "Studio 首页，尚未上传来源。",
        "第一个问题问课程给谁。回答是阅读该研究的临床人员和研究人员，讲发现和边界。",
        "访谈询问学习者如何通过课程：带大纲的课、单页滚动，或幻灯片。这次选择标准课程。",
        "没有上传设计系统，所以访谈询问整体风格。这次选择社论风格。",
        "提议的大纲：阅读微剂量调查，发现与边界。两个模块，六节课。批准之后才开始写。",
        "课时对照论文被引用的页面生成。此时课程尚未发布。",
        "应用通过学院自己的服务创建课程。这一步由作者决定。",
        "发布前：每一段引用论文，测验答案有依据，依据检查没有无支持的说法。",
        "课程已发布。标题警告已列出并确认。按钮显示为已发布。",
        "学习者看到的已发布课程，约 30 分钟，英语，Gravity 主题。",
        "从大纲打开的一节课。页面保留构建器引用的来源。",
      ],
      back: "全部案例",
      other: "稳定币报告",
      preview: "打开已发布的课程",
      previewTitle: "来源与已发布课程",
      previewText: "构建器引用的 PDF，以及由此生成的课程。打开任意一个：论文是材料，课程是对发现和边界的阅读。",
      source: "打开来源 PDF",
      sourceTitle: "来源与已发布课程",
      sourceText: "构建器引用的 PDF，以及由此生成的课程。打开任意一个：论文是材料，课程是对发现和边界的阅读。",
      previous: "上一张",
      next: "下一张",
    },
    "stablecoin-report": {
      title: "ulams — 案例：稳定币报告",
      description: "2021 年 11 月的美国稳定币报告如何变成一门有引用的课，讲安排、风险和作者的建议。",
      eyebrow: "案例",
      heading: "支付型稳定币：风险与监管建议",
      subtitle: "从 2021 年 11 月的跨部门报告到一门已发布的课程。",
      body: "来源是总统工作组与 FDIC、OCC 的稳定币报告。课程说明支付型稳定币如何发行、赎回和储备，报告点名了哪些风险，以及它建议的立法步骤。",
      facts: [
        { label: "受众", value: "政策人员与学生" },
        { label: "时长", value: "约 30 分钟" },
        { label: "请求", value: "幻灯片、情景" },
        { label: "依据", value: "每一段都有引用" },
      ],
      boundaryTitle: "这是对报告的阅读",
      boundary: "这门课讲解的是报告。它不是法律、投资或产品建议。访谈要求幻灯片，这个回答保存在简报里。本次录屏里学习者打开的仍是标准课程播放器，带大纲和课时。",
      buildHeading: "访谈、大纲和生成",
      buildText: "上传 PDF，回答全部十二个问题，批准大纲，课时根据报告被引用的页面写成。",
      buildVideo: "录屏：构建稳定币报告课程，从登录到生成",
      publishHeading: "检查通过后发布",
      publishText: "检查通过。两条标题警告需要确认。作者随后发布，课程可以打开。",
      publishVideo: "录屏：发布稳定币报告课程",
      stepsTitle: "当时做了哪些决定",
      stepsIntro: "访谈要求一门更长的幻灯片课程。发布出来的是大纲生成的较短课程，使用标准播放器。",
      steps: [
        { title: "给谁学", text: "政策人员与金融学生。中级、专业语气、测验、英语，请求约 60 分钟。" },
        { title: "形态与风格", text: "幻灯片、情景活动、已有一些基础、技术风格。简报记下了幻灯片。这次播放器仍是课程大纲。" },
        { title: "大纲", text: "两个模块：稳定币安排如何运作，以及风险和建议的回应。提议了四节课。" },
        { title: "检查", text: "十九个块引用报告。九道题通过依据检查。没有标记。" },
        { title: "已发布", text: "免费，Gravity 主题，约 30 分钟。确认了两条标题警告，然后发布。" },
      ],
      framesTitle: "按顺序的每一屏",
      framesIntro: "顶部箭头切换 Studio 画面。说明在图像上方，图像占满宽度。打开图像可看全尺寸。",
      captions: [
        "登录这次录制所用学院的 Studio。",
        "Studio 首页，尚未上传报告。",
        "第一个问题问课程给谁。回答是阅读报告的政策人员和金融学生。",
        "访谈询问如何通过课程。这次回答幻灯片。简报保存该答案。后来打开的播放器仍是标准课程。",
        "没有上传设计系统。这里选择的风格是技术型，并带情景活动。",
        "已批准的大纲：安排如何运作，然后是风险和建议的回应。批准之后才开始生成。",
        "课时根据 2021 年 11 月报告被引用的页面写成。课程尚未发布。",
        "应用在学院中创建课程。摘要显示两个模块和这次的引用检查。",
        "发布前检查：引用、有依据的测验答案，以及没有无依据说法的标记。",
        "发布前需要确认标题层级警告。确认之后课程发布。",
        "学习者的课程页。访谈要求幻灯片和约 60 分钟。这一页是标准播放器，约 30 分钟。",
        "课时《发行、赎回、储备与转移》，报告被标为来源。",
      ],
      back: "全部案例",
      other: "微剂量调查",
      preview: "打开已发布的课程",
      previewTitle: "来源与已发布课程",
      previewText: "构建器引用的 PDF，以及由此生成的课程。打开任意一个：报告是材料，课程是对它的阅读，不是法律或投资建议。",
      source: "打开来源 PDF",
      sourceTitle: "来源与已发布课程",
      sourceText: "构建器引用的 PDF，以及由此生成的课程。打开任意一个：报告是材料，课程是对它的阅读，不是法律或投资建议。",
      previous: "上一张",
      next: "下一张",
    },
  },
};

const COPY: Record<Locale, GenerationCopy> = { en, pl, zh };

export const generationCopy = (locale: Locale): GenerationCopy => COPY[locale];
