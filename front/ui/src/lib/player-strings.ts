/**
 * Interface strings of the learner pages in English, Polish and Simplified Chinese: the lesson player, the
 * course page, the finish page, the quiz and the progress and media elements (ADR 0106, amended by the
 * tenant default language, P5R-1). Course titles, topic text, quiz questions and every other API text are
 * never translated here. The browser elements read the language from the closest `lang` attribute
 * (`elementLocale`), the pages from `learnerLocale()`. Plurals go through `Intl.PluralRules` (Polish has
 * `one`, `few`, `many`).
 *
 * Native review of the Polish and Chinese text is an owner action (docs/translators.md in the docs site).
 */
import { fill, plural, trainingLocale, type Plural, type TrainingLocale } from "./training-strings.ts";

export { fill, plural, type Plural };
export type PlayerLocale = TrainingLocale;

/** The theme keys of the tenant themes; the lesson noun and the finish headline differ per theme. */
export const THEME_KEYS = ["coffee", "oncall", "nightsky", "gravity", "poland", "ulam", "platform"] as const;
export type ThemeKey = (typeof THEME_KEYS)[number];
type ByTheme = Record<ThemeKey, string>;

export const FORMAT_KEYS = ["video", "audio", "reading", "image", "pdf", "embed", "interactive", "scorm", "tracked", "quiz", "project"] as const;
type ByFormat = Record<(typeof FORMAT_KEYS)[number], string>;

export const QUESTION_TYPES = [
  "multiple_choice",
  "multiple_choice_with_multiple_right_answers",
  "true_false",
  "short_answers",
  "matching",
  "numerical_question",
  "essay",
  "description",
] as const;
type ByQuestionType = Record<(typeof QUESTION_TYPES)[number], string>;

export interface PlayerStrings {
  // ----- lesson player chrome
  courseProgress: string;
  home: string;
  program: string;
  programNav: string;
  freePreview: string;
  completed: string;
  previewMode: string;
  downloads: string;
  previous: string;
  previousTopic: string;
  next: string;
  nextTopic: string;
  finish: string;
  finishCourse: string;
  markComplete: string;
  topicMarked: string;
  progressFailed: string;
  /** "Chapter", "Module"…: the name of a lesson in the tenant theme. */
  lessonNoun: ByTheme;
  formats: ByFormat;
  /** `{n}` is replaced. */
  minutes: string;
  /** `{h}` is replaced. */
  hours: string;
  /** `{h}` and `{m}` are replaced. */
  hoursMinutes: string;

  // ----- quiz (component and element)
  quizTimeLeft: string;
  quizMinutes: Plural;
  quizAttempts: Plural;
  quizPassPoints: Plural;
  quizStart: string;
  quizTypes: ByQuestionType;
  quizQuestions: string;
  /** `{n}` is replaced. */
  quizQuestionN: string;
  /** `{n}` and `{total}` are replaced. */
  quizQuestionOf: string;
  /** `{n}` is replaced. */
  quizPoints: Plural;
  quizPrevious: string;
  quizNext: string;
  quizFinish: string;
  quizTrue: string;
  quizFalse: string;
  quizChoose: string;
  quizNoAnswer: string;
  /** `{n}` is replaced. */
  quizWords: Plural;
  quizScoring: string;
  quizSaveFailed: string;
  quizFinishFailed: string;
  quizNoAttempts: string;
  quizStartFailed: string;
  quizTryAgain: string;
  /** `{score}` and `{max}` are replaced. */
  quizScoreLabel: string;
  quizPassed: string;
  quizNotPassed: string;
  /** `{percent}` is replaced. */
  quizPassedText: string;
  /** `{pass}` is replaced. */
  quizNotPassedText: string;
  quizTutorReview: string;
  quizCorrect: string;
  quizNotQuite: string;
  quizNotAnswered: string;
  quizNewAttempt: string;
  quizContinue: string;
  /** `{score}` and `{max}` are replaced. */
  quizFinishedLive: string;

  // ----- media and frames
  /** `{title}` is replaced. */
  videoPlay: string;
  videoChapters: string;
  audioDownload: string;
  pdfOpen: string;
  pdfDownload: string;
  packageFullScreen: string;
  /** `{n}` is replaced. */
  liaSections: Plural;
  liaCompletes: string;

  // ----- topic cards of the player
  topicNotLoadedTitle: string;
  topicLockedTitle: string;
  topicNotLoadedText: string;
  topicLockedText: string;
  backToCourse: string;
  previewQuizTitle: string;
  previewQuizText: string;
  previewOtherTitle: string;
  previewOtherText: string;
  previewInteractiveText: string;
  /** `{title}` is replaced. */
  scormTitle: string;
  scormText: string;
  scormTry: string;
  /** `{tool}` is replaced. */
  ltiOpens: string;
  ltiToolFallback: string;
  ltiOpen: string;
  ltiNeedsEnrolment: string;
  liaNeedsEnrolment: string;
  projectTitle: string;
  projectText: string;
  cmi5Text: string;
  cmi5Step1: string;
  cmi5Step2: string;
  cmi5Step3: string;
  unsupportedTopic: string;

  // ----- course page
  factLevel: string;
  factLength: string;
  factTopics: string;
  factLanguage: string;
  hoursValue: Plural;
  selfPaced: string;
  inLessons: Plural;
  eyebrowLessons: Plural;
  /** The lesson count of the course card, `{n}` is replaced. */
  lessonsCount: Plural;
  topicsCount: Plural;
  /** `{n}` is replaced. */
  materialHours: string;
  continueLearning: string;
  startLearning: string;
  yourProgress: string;
  tutors: string;
  syllabusEyebrow: string;
  syllabusTitleDefault: string;
  syllabusTitle: Record<"nightsky" | "gravity" | "poland" | "ulam", string>;
  free: string;
  launchMission: string;
  openMission: string;
  aboutCourse: string;
  enrol: string;
  demoAccess: string;
  listPrice: string;
  /** `{list}` is replaced. */
  formatsList: string;
  progressSaved: string;
  everyLessonOpen: string;
  openCourse: string;
  paymentsNote: string;

  // ----- "what is next" line
  courseComplete: string;
  /** `{topics}` and `{time}` are replaced. */
  standingTotal: string;
  /** `{title}` and `{left}` are replaced. */
  standingNext: string;
  /** `{time}` is replaced. */
  timeLeft: string;
  topicsLeft: Plural;

  // ----- finish page
  finishTitle: string;
  /** `{n}` is replaced. */
  finishRing: string;
  finishComplete: string;
  /** `{n}` is replaced. */
  stillOpen: string;
  finishBack: string;
  finishDone: ByTheme;
  finishAlmost: ByTheme;
  demoStudent: string;

  // ----- Living Course notices
  /** `{date}` is replaced. */
  noticeUpdatedAfterStart: string;
  /** `{date}` is replaced. */
  noticeUpdatedAfterDone: string;
  noticeUpdatedAfterStartPlain: string;
  noticeUpdatedAfterDonePlain: string;
  /** `{message}` is replaced. */
  noticeChange: string;
  noticeReviewed: string;
  noticeReviewedDone: string;
  noticeReviewedFailed: string;
  reattemptTitle: string;
  reattemptText: string;
  reattemptLink: string;
  retired: string;
  /** `{date}` is replaced. */
  pendingDate: string;
  pending: string;
  extended: string;
  courseUpdatesTitle: string;
  /** `{date}` is replaced. */
  updatedSinceDone: string;
}

const en: PlayerStrings = {
  courseProgress: "Course progress",
  home: "Home",
  program: "Program",
  programNav: "Course program",
  freePreview: "Free preview",
  completed: "Completed",
  previewMode: "Preview mode: this demo account has no access to the full course, so only free topics open.",
  downloads: "Downloads",
  previous: "Previous",
  previousTopic: "Previous topic",
  next: "Next",
  nextTopic: "Next topic",
  finish: "Finish",
  finishCourse: "Finish the course",
  markComplete: "Mark as complete",
  topicMarked: "Topic marked as complete.",
  progressFailed: "Could not save your progress. Try again.",
  lessonNoun: { coffee: "Chapter", oncall: "Module", nightsky: "Mission", gravity: "Module", poland: "Chapter", ulam: "Notebook", platform: "Lesson" },
  formats: { video: "Video", audio: "Audio", reading: "Reading", image: "Image", pdf: "PDF", embed: "Embed", interactive: "Interactive", scorm: "Simulation", tracked: "Tracked activity", quiz: "Quiz", project: "Project" },
  minutes: "{n} min",
  hours: "{h} h",
  hoursMinutes: "{h} h {m} min",

  quizTimeLeft: "Time left",
  quizMinutes: { one: "{n} minute", other: "{n} minutes" },
  quizAttempts: { one: "{n} attempt", other: "{n} attempts" },
  quizPassPoints: { one: "{n} point to pass", other: "{n} points to pass" },
  quizStart: "Start the quiz",
  quizTypes: {
    multiple_choice: "Choose one answer",
    multiple_choice_with_multiple_right_answers: "Choose all that apply",
    true_false: "True or false?",
    short_answers: "Type a short answer",
    matching: "Match each item",
    numerical_question: "Enter a number",
    essay: "Write your answer",
    description: "Take a breath",
  },
  quizQuestions: "Questions",
  quizQuestionN: "Question {n}",
  quizQuestionOf: "Question {n} of {total}",
  quizPoints: { one: "{n} pt", other: "{n} pts" },
  quizPrevious: "Previous",
  quizNext: "Next",
  quizFinish: "Finish quiz",
  quizTrue: "True",
  quizFalse: "False",
  quizChoose: "Choose…",
  quizNoAnswer: "No answer needed. Continue when you are ready.",
  quizWords: { one: "{n} word", other: "{n} words" },
  quizScoring: "Scoring your answers…",
  quizSaveFailed: "Your last answer could not be saved.",
  quizFinishFailed: "Finishing failed. Try again.",
  quizNoAttempts: "No attempts left for this quiz.",
  quizStartFailed: "The quiz could not start. Try again in a moment.",
  quizTryAgain: "Try again",
  quizScoreLabel: "Score {score} of {max}",
  quizPassed: "Passed. Well done!",
  quizNotPassed: "Not passed yet",
  quizPassedText: "You scored {percent}%. Answers that need a tutor are reviewed separately.",
  quizNotPassedText: "You need {pass} points to pass. Review the answers and try again.",
  quizTutorReview: "Reviewed by your tutor",
  quizCorrect: "Correct",
  quizNotQuite: "Not quite",
  quizNotAnswered: "Not answered",
  quizNewAttempt: "New attempt",
  quizContinue: "Continue",
  quizFinishedLive: "Quiz finished. Score {score} of {max}.",

  videoPlay: "Play: {title}",
  videoChapters: "Chapters",
  audioDownload: "Download the recording",
  pdfOpen: "Open",
  pdfDownload: "Download",
  packageFullScreen: "Full screen",
  liaSections: { one: "{n} section", other: "{n} sections" },
  liaCompletes: "completes at the last one",

  topicNotLoadedTitle: "This topic could not be loaded",
  topicLockedTitle: "Locked in this demo account",
  topicNotLoadedText: "The API returned the topic without its content. Try again in a moment.",
  topicLockedText:
    "The demo student has no access to this course yet, so only the free preview topics open. Turn on demo mode for the tenant (or grant the student access in the admin) to open every topic.",
  backToCourse: "Back to the course",
  previewQuizTitle: "Quiz: not run in preview",
  previewQuizText: "Learners answer this quiz and see their score here. The preview does not start attempts, so nothing is saved.",
  previewOtherTitle: "Not launched in preview",
  previewOtherText: "Learners open this activity here and it reports their progress. The preview does not launch it, so nothing is recorded.",
  previewInteractiveText: "Learners open this interactive here and it reports their progress. This preview does not launch it, so nothing is recorded.",
  scormTitle: "{title} (SCORM package)",
  scormText: "The package is uploaded, but this API does not serve its files yet (tenant storage is not published under /storage). The player opens here as soon as the files load.",
  scormTry: "Try the player in a new tab",
  ltiOpens: "Opens in {tool} in a new window; your result comes back here.",
  ltiToolFallback: "the tool",
  ltiOpen: "Open the activity",
  ltiNeedsEnrolment: "This activity opens once you are enrolled.",
  liaNeedsEnrolment: "This course opens once you are enrolled.",
  projectTitle: "Hand in your work",
  projectText: "In the full app you upload your files here and your tutor replies in a feedback thread. File upload is not part of this demo yet.",
  cmi5Text: "A tracked activity that reports your progress back. It opens here once you are enrolled and the tenant has a content origin.",
  cmi5Step1: "Open the activity",
  cmi5Step2: "Complete it",
  cmi5Step3: "Your progress is saved",
  unsupportedTopic: "Unsupported topic type",

  factLevel: "Level",
  factLength: "Length",
  factTopics: "Topics",
  factLanguage: "Language",
  hoursValue: { one: "{n} hour", other: "{n} hours" },
  selfPaced: "self-paced",
  inLessons: { one: "in {n} lesson", other: "in {n} lessons" },
  eyebrowLessons: { one: "{n} lesson", other: "{n} lessons" },
  lessonsCount: { one: "{n} lesson", other: "{n} lessons" },
  topicsCount: { one: "{n} topic", other: "{n} topics" },
  materialHours: "about {n} h of material",
  continueLearning: "Continue learning",
  startLearning: "Start learning",
  yourProgress: "Your progress",
  tutors: "Tutors",
  syllabusEyebrow: "Program",
  syllabusTitleDefault: "What you will do",
  syllabusTitle: { nightsky: "Your mission map", gravity: "Your flight plan", poland: "Contents", ulam: "The notebooks" },
  free: "Free",
  launchMission: "Launch mission",
  openMission: "Open mission",
  aboutCourse: "About the course",
  enrol: "Enrol",
  demoAccess: "Demo access",
  listPrice: "list price",
  formatsList: "Formats: {list}",
  progressSaved: "Progress saved as you go",
  everyLessonOpen: "Every lesson open in this demo",
  openCourse: "Open the course",
  paymentsNote: "Payments run through the shop service in production; this demo opens the course directly.",

  courseComplete: "Course complete. Review any lesson, or check your certificate.",
  standingTotal: "{topics} · about {time} in total",
  standingNext: "Next: {title} · {left}",
  timeLeft: "about {time} left",
  topicsLeft: { one: "{n} topic left", other: "{n} topics left" },

  finishTitle: "Finish",
  finishRing: "{n}% of the course completed",
  finishComplete: "complete",
  stillOpen: "Still open ({n})",
  finishBack: "Back to the course",
  finishDone: {
    coffee: "Your signature cup is ready.",
    oncall: "Incident closed. Well commanded.",
    nightsky: "Mission complete, explorer!",
    gravity: "Escape velocity reached",
    poland: "Measured and done",
    ulam: "Problem solved — no goose required",
    platform: "Course complete",
  },
  finishAlmost: {
    coffee: "The last page, almost.",
    oncall: "Almost there: a few items left.",
    nightsky: "So close to the stars!",
    gravity: "Almost in orbit",
    poland: "Nearly measured",
    ulam: "One more problem to go",
    platform: "Almost there",
  },
  demoStudent: "Demo student",

  noticeUpdatedAfterStart: "This lesson was updated after you started it, {date}.",
  noticeUpdatedAfterDone: "Updated since you completed it, {date}.",
  noticeUpdatedAfterStartPlain: "This lesson was updated after you started it.",
  noticeUpdatedAfterDonePlain: "Updated since you completed it.",
  noticeChange: "What changed: {message}",
  noticeReviewed: "Mark as reviewed",
  noticeReviewedDone: "Marked as reviewed. This notice will not show again.",
  noticeReviewedFailed: "Could not save that. Try again.",
  reattemptTitle: "One question was corrected.",
  reattemptText: "Your previous score stays on record. Retake it to update your result.",
  reattemptLink: "Go to the quiz",
  retired: "Retired lesson, no longer part of the course",
  pendingDate: "The source of this lesson changed on {date}; an update is under review.",
  pending: "The source of this lesson changed; an update is under review.",
  extended: "New since you finished",
  courseUpdatesTitle: "What changed in this course",
  updatedSinceDone: "Updated since you completed it, {date}",
};

const pl: PlayerStrings = {
  courseProgress: "Postęp w kursie",
  home: "Strona główna",
  program: "Program",
  programNav: "Program kursu",
  freePreview: "Darmowy podgląd",
  completed: "Ukończono",
  previewMode: "Tryb podglądu: to konto demo nie ma dostępu do całego kursu, więc otwierają się tylko darmowe tematy.",
  downloads: "Materiały do pobrania",
  previous: "Wstecz",
  previousTopic: "Poprzedni temat",
  next: "Dalej",
  nextTopic: "Następny temat",
  finish: "Zakończ",
  finishCourse: "Zakończ kurs",
  markComplete: "Oznacz jako ukończone",
  topicMarked: "Temat oznaczono jako ukończony.",
  progressFailed: "Nie udało się zapisać postępu. Spróbuj ponownie.",
  lessonNoun: { coffee: "Rozdział", oncall: "Moduł", nightsky: "Misja", gravity: "Moduł", poland: "Rozdział", ulam: "Zeszyt", platform: "Lekcja" },
  formats: { video: "Wideo", audio: "Audio", reading: "Lektura", image: "Obraz", pdf: "PDF", embed: "Osadzona treść", interactive: "Interaktywne", scorm: "Symulacja", tracked: "Aktywność ze śledzeniem", quiz: "Quiz", project: "Projekt" },
  minutes: "{n} min",
  hours: "{h} godz.",
  hoursMinutes: "{h} godz. {m} min",

  quizTimeLeft: "Pozostały czas",
  quizMinutes: { one: "{n} minuta", few: "{n} minuty", many: "{n} minut", other: "{n} minuty" },
  quizAttempts: { one: "{n} podejście", few: "{n} podejścia", many: "{n} podejść", other: "{n} podejścia" },
  quizPassPoints: { one: "{n} punkt do zaliczenia", few: "{n} punkty do zaliczenia", many: "{n} punktów do zaliczenia", other: "{n} punktu do zaliczenia" },
  quizStart: "Rozpocznij quiz",
  quizTypes: {
    multiple_choice: "Wybierz jedną odpowiedź",
    multiple_choice_with_multiple_right_answers: "Zaznacz wszystkie poprawne",
    true_false: "Prawda czy fałsz?",
    short_answers: "Wpisz krótką odpowiedź",
    matching: "Dopasuj każdy element",
    numerical_question: "Wpisz liczbę",
    essay: "Napisz odpowiedź",
    description: "Złap oddech",
  },
  quizQuestions: "Pytania",
  quizQuestionN: "Pytanie {n}",
  quizQuestionOf: "Pytanie {n} z {total}",
  quizPoints: { one: "{n} pkt", few: "{n} pkt", many: "{n} pkt", other: "{n} pkt" },
  quizPrevious: "Wstecz",
  quizNext: "Dalej",
  quizFinish: "Zakończ quiz",
  quizTrue: "Prawda",
  quizFalse: "Fałsz",
  quizChoose: "Wybierz…",
  quizNoAnswer: "Odpowiedź nie jest potrzebna. Przejdź dalej, gdy będziesz gotowy.",
  quizWords: { one: "{n} słowo", few: "{n} słowa", many: "{n} słów", other: "{n} słowa" },
  quizScoring: "Sprawdzamy Twoje odpowiedzi…",
  quizSaveFailed: "Nie udało się zapisać ostatniej odpowiedzi.",
  quizFinishFailed: "Nie udało się zakończyć. Spróbuj ponownie.",
  quizNoAttempts: "Nie zostały już żadne podejścia do tego quizu.",
  quizStartFailed: "Nie udało się rozpocząć quizu. Spróbuj za chwilę.",
  quizTryAgain: "Spróbuj ponownie",
  quizScoreLabel: "Wynik {score} z {max}",
  quizPassed: "Zaliczone. Dobra robota!",
  quizNotPassed: "Jeszcze niezaliczone",
  quizPassedText: "Twój wynik to {percent}%. Odpowiedzi wymagające oceny prowadzącego są sprawdzane osobno.",
  quizNotPassedText: "Do zaliczenia potrzeba {pass} pkt. Przejrzyj odpowiedzi i spróbuj ponownie.",
  quizTutorReview: "Oceni prowadzący",
  quizCorrect: "Poprawnie",
  quizNotQuite: "Nie całkiem",
  quizNotAnswered: "Bez odpowiedzi",
  quizNewAttempt: "Nowe podejście",
  quizContinue: "Dalej",
  quizFinishedLive: "Quiz zakończony. Wynik {score} z {max}.",

  videoPlay: "Odtwórz: {title}",
  videoChapters: "Rozdziały",
  audioDownload: "Pobierz nagranie",
  pdfOpen: "Otwórz",
  pdfDownload: "Pobierz",
  packageFullScreen: "Pełny ekran",
  liaSections: { one: "{n} sekcja", few: "{n} sekcje", many: "{n} sekcji", other: "{n} sekcji" },
  liaCompletes: "kończy się na ostatniej",

  topicNotLoadedTitle: "Nie udało się wczytać tego tematu",
  topicLockedTitle: "Zablokowane na tym koncie demo",
  topicNotLoadedText: "API zwróciło temat bez treści. Spróbuj za chwilę.",
  topicLockedText:
    "Uczeń demo nie ma jeszcze dostępu do tego kursu, więc otwierają się tylko darmowe tematy podglądu. Włącz tryb demo dla tej instancji (albo nadaj uczniowi dostęp w panelu administratora), aby otworzyć każdy temat.",
  backToCourse: "Wróć do kursu",
  previewQuizTitle: "Quiz: nieuruchamiany w podglądzie",
  previewQuizText: "Uczniowie odpowiadają tu na ten quiz i widzą swój wynik. Podgląd nie rozpoczyna podejść, więc nic się nie zapisuje.",
  previewOtherTitle: "Nieuruchamiane w podglądzie",
  previewOtherText: "Uczniowie otwierają tu tę aktywność, a ona zgłasza ich postęp. Podgląd jej nie uruchamia, więc nic się nie zapisuje.",
  previewInteractiveText: "Uczniowie otwierają tu ten materiał interaktywny, a on zgłasza ich postęp. Ten podgląd go nie uruchamia, więc nic się nie zapisuje.",
  scormTitle: "{title} (pakiet SCORM)",
  scormText: "Pakiet został wgrany, ale to API jeszcze nie udostępnia jego plików (pamięć instancji nie jest opublikowana w /storage). Odtwarzacz otworzy się tutaj, gdy tylko pliki się wczytają.",
  scormTry: "Wypróbuj odtwarzacz w nowej karcie",
  ltiOpens: "Otwiera się w narzędziu {tool} w nowym oknie; wynik wróci tutaj.",
  ltiToolFallback: "narzędziu",
  ltiOpen: "Otwórz aktywność",
  ltiNeedsEnrolment: "Ta aktywność otworzy się po zapisaniu na kurs.",
  liaNeedsEnrolment: "Ten kurs otworzy się po zapisaniu na kurs.",
  projectTitle: "Oddaj swoją pracę",
  projectText: "W pełnej aplikacji wgrywasz tu pliki, a prowadzący odpowiada w wątku z opinią. Wgrywanie plików nie jest jeszcze częścią tego dema.",
  cmi5Text: "Aktywność ze śledzeniem, która zgłasza Twój postęp. Otworzy się tutaj po zapisaniu na kurs i gdy instancja ma źródło treści.",
  cmi5Step1: "Otwórz aktywność",
  cmi5Step2: "Ukończ ją",
  cmi5Step3: "Twój postęp zostanie zapisany",
  unsupportedTopic: "Nieobsługiwany typ tematu",

  factLevel: "Poziom",
  factLength: "Czas trwania",
  factTopics: "Tematy",
  factLanguage: "Język",
  hoursValue: { one: "{n} godzina", few: "{n} godziny", many: "{n} godzin", other: "{n} godziny" },
  selfPaced: "we własnym tempie",
  inLessons: { one: "w {n} lekcji", few: "w {n} lekcjach", many: "w {n} lekcjach", other: "w {n} lekcji" },
  eyebrowLessons: { one: "{n} lekcja", few: "{n} lekcje", many: "{n} lekcji", other: "{n} lekcji" },
  lessonsCount: { one: "{n} lekcja", few: "{n} lekcje", many: "{n} lekcji", other: "{n} lekcji" },
  topicsCount: { one: "{n} temat", few: "{n} tematy", many: "{n} tematów", other: "{n} tematu" },
  materialHours: "około {n} godz. materiału",
  continueLearning: "Kontynuuj naukę",
  startLearning: "Zacznij naukę",
  yourProgress: "Twój postęp",
  tutors: "Prowadzący",
  syllabusEyebrow: "Program",
  syllabusTitleDefault: "Co będziesz robić",
  syllabusTitle: { nightsky: "Twoja mapa misji", gravity: "Twój plan lotu", poland: "Spis treści", ulam: "Zeszyty" },
  free: "Za darmo",
  launchMission: "Rozpocznij misję",
  openMission: "Otwórz misję",
  aboutCourse: "O kursie",
  enrol: "Zapis na kurs",
  demoAccess: "Dostęp demo",
  listPrice: "cena katalogowa",
  formatsList: "Formaty: {list}",
  progressSaved: "Postęp zapisuje się na bieżąco",
  everyLessonOpen: "Każda lekcja otwarta w tym demo",
  openCourse: "Otwórz kurs",
  paymentsNote: "W wersji produkcyjnej płatności obsługuje usługa sklepu; to demo otwiera kurs bezpośrednio.",

  courseComplete: "Kurs ukończony. Wróć do dowolnej lekcji albo sprawdź swój certyfikat.",
  standingTotal: "{topics} · łącznie około {time}",
  standingNext: "Dalej: {title} · {left}",
  timeLeft: "zostało około {time}",
  topicsLeft: { one: "został {n} temat", few: "zostały {n} tematy", many: "zostało {n} tematów", other: "zostało {n} tematu" },

  finishTitle: "Koniec",
  finishRing: "Ukończono {n}% kursu",
  finishComplete: "ukończono",
  stillOpen: "Jeszcze otwarte ({n})",
  finishBack: "Wróć do kursu",
  finishDone: {
    coffee: "Twoja firmowa filiżanka jest gotowa.",
    oncall: "Incydent zamknięty. Dobrze poprowadzony.",
    nightsky: "Misja ukończona, odkrywco!",
    gravity: "Prędkość ucieczki osiągnięta",
    poland: "Zmierzone i gotowe",
    ulam: "Problem rozwiązany — bez gęsi",
    platform: "Kurs ukończony",
  },
  finishAlmost: {
    coffee: "Ostatnia strona, już prawie.",
    oncall: "Prawie koniec: zostało kilka punktów.",
    nightsky: "Tak blisko gwiazd!",
    gravity: "Prawie na orbicie",
    poland: "Prawie zmierzone",
    ulam: "Jeszcze jeden problem",
    platform: "Już prawie",
  },
  demoStudent: "Uczeń demo",

  noticeUpdatedAfterStart: "Ta lekcja została zaktualizowana po rozpoczęciu przez Ciebie, {date}.",
  noticeUpdatedAfterDone: "Zaktualizowano po ukończeniu przez Ciebie, {date}.",
  noticeUpdatedAfterStartPlain: "Ta lekcja została zaktualizowana po rozpoczęciu przez Ciebie.",
  noticeUpdatedAfterDonePlain: "Zaktualizowano po ukończeniu przez Ciebie.",
  noticeChange: "Co się zmieniło: {message}",
  noticeReviewed: "Oznacz jako przejrzane",
  noticeReviewedDone: "Oznaczono jako przejrzane. To powiadomienie nie pojawi się ponownie.",
  noticeReviewedFailed: "Nie udało się tego zapisać. Spróbuj ponownie.",
  reattemptTitle: "Jedno pytanie zostało poprawione.",
  reattemptText: "Twój poprzedni wynik pozostaje w historii. Rozwiąż quiz ponownie, aby zaktualizować wynik.",
  reattemptLink: "Przejdź do quizu",
  retired: "Wycofana lekcja, nie należy już do kursu",
  pendingDate: "Źródło tej lekcji zmieniło się {date}; aktualizacja czeka na przegląd.",
  pending: "Źródło tej lekcji się zmieniło; aktualizacja czeka na przegląd.",
  extended: "Nowe od ukończenia kursu",
  courseUpdatesTitle: "Co zmieniło się w tym kursie",
  updatedSinceDone: "Zaktualizowano po ukończeniu przez Ciebie, {date}",
};

const zh: PlayerStrings = {
  courseProgress: "课程进度",
  home: "首页",
  program: "课程大纲",
  programNav: "课程大纲",
  freePreview: "免费试看",
  completed: "已完成",
  previewMode: "预览模式：此演示账号无法访问完整课程，只能打开免费内容。",
  downloads: "下载资料",
  previous: "上一个",
  previousTopic: "上一个主题",
  next: "下一个",
  nextTopic: "下一个主题",
  finish: "完成",
  finishCourse: "完成课程",
  markComplete: "标记为已完成",
  topicMarked: "已将此主题标记为已完成。",
  progressFailed: "无法保存你的进度，请重试。",
  lessonNoun: { coffee: "章节", oncall: "模块", nightsky: "任务", gravity: "模块", poland: "章节", ulam: "笔记本", platform: "课程" },
  formats: { video: "视频", audio: "音频", reading: "阅读", image: "图片", pdf: "PDF", embed: "嵌入内容", interactive: "互动", scorm: "模拟", tracked: "可追踪活动", quiz: "测验", project: "项目" },
  minutes: "{n} 分钟",
  hours: "{h} 小时",
  hoursMinutes: "{h} 小时 {m} 分钟",

  quizTimeLeft: "剩余时间",
  quizMinutes: { other: "{n} 分钟" },
  quizAttempts: { other: "{n} 次机会" },
  quizPassPoints: { other: "{n} 分及格" },
  quizStart: "开始测验",
  quizTypes: {
    multiple_choice: "选择一个答案",
    multiple_choice_with_multiple_right_answers: "选择所有正确答案",
    true_false: "判断对错",
    short_answers: "输入简短答案",
    matching: "匹配每一项",
    numerical_question: "输入数字",
    essay: "写出你的答案",
    description: "休息一下",
  },
  quizQuestions: "题目",
  quizQuestionN: "第 {n} 题",
  quizQuestionOf: "第 {n} 题，共 {total} 题",
  quizPoints: { other: "{n} 分" },
  quizPrevious: "上一题",
  quizNext: "下一题",
  quizFinish: "完成测验",
  quizTrue: "正确",
  quizFalse: "错误",
  quizChoose: "请选择…",
  quizNoAnswer: "无需作答。准备好后继续。",
  quizWords: { other: "{n} 个词" },
  quizScoring: "正在为你的答案评分…",
  quizSaveFailed: "无法保存你的上一个答案。",
  quizFinishFailed: "无法完成，请重试。",
  quizNoAttempts: "此测验已没有剩余次数。",
  quizStartFailed: "无法开始测验，请稍后重试。",
  quizTryAgain: "重试",
  quizScoreLabel: "得分 {score} / {max}",
  quizPassed: "通过，做得好！",
  quizNotPassed: "尚未通过",
  quizPassedText: "你的得分是 {percent}%。需要导师评阅的答案会单独批改。",
  quizNotPassedText: "需要 {pass} 分才能通过。请查看答案后重试。",
  quizTutorReview: "由导师评阅",
  quizCorrect: "正确",
  quizNotQuite: "不太对",
  quizNotAnswered: "未作答",
  quizNewAttempt: "重新作答",
  quizContinue: "继续",
  quizFinishedLive: "测验已完成。得分 {score} / {max}。",

  videoPlay: "播放：{title}",
  videoChapters: "章节",
  audioDownload: "下载录音",
  pdfOpen: "打开",
  pdfDownload: "下载",
  packageFullScreen: "全屏",
  liaSections: { other: "{n} 节" },
  liaCompletes: "学完最后一节即完成",

  topicNotLoadedTitle: "无法加载此主题",
  topicLockedTitle: "此演示账号无法打开",
  topicNotLoadedText: "API 返回了没有内容的主题，请稍后重试。",
  topicLockedText: "演示学员尚无此课程的访问权限，因此只能打开免费试看的主题。请为该租户开启演示模式（或在管理后台授予学员访问权限）以打开所有主题。",
  backToCourse: "返回课程",
  previewQuizTitle: "测验：预览中不运行",
  previewQuizText: "学员会在这里作答并查看得分。预览不会开始作答，因此不会保存任何内容。",
  previewOtherTitle: "预览中不会启动",
  previewOtherText: "学员会在这里打开此活动，并由它上报学习进度。预览不会启动它，因此不会记录任何内容。",
  previewInteractiveText: "学员会在这里打开此互动内容，并由它上报学习进度。此预览不会启动它，因此不会记录任何内容。",
  scormTitle: "{title}（SCORM 包）",
  scormText: "该包已上传，但此 API 暂未提供其文件（租户存储尚未发布在 /storage 下）。文件加载后，播放器会在这里打开。",
  scormTry: "在新标签页中试用播放器",
  ltiOpens: "将在新窗口中通过 {tool} 打开；你的结果会返回到这里。",
  ltiToolFallback: "该工具",
  ltiOpen: "打开活动",
  ltiNeedsEnrolment: "报名后即可打开此活动。",
  liaNeedsEnrolment: "报名后即可打开此课程。",
  projectTitle: "提交你的作业",
  projectText: "在完整版应用中，你可以在这里上传文件，导师会在反馈讨论串中回复。本演示暂不支持上传文件。",
  cmi5Text: "一项会回传你学习进度的可追踪活动。报名且租户配置了内容源后，会在这里打开。",
  cmi5Step1: "打开活动",
  cmi5Step2: "完成活动",
  cmi5Step3: "你的进度会被保存",
  unsupportedTopic: "不支持的主题类型",

  factLevel: "难度",
  factLength: "时长",
  factTopics: "主题",
  factLanguage: "语言",
  hoursValue: { other: "{n} 小时" },
  selfPaced: "自定进度",
  inLessons: { other: "共 {n} 节课" },
  eyebrowLessons: { other: "{n} 节课" },
  lessonsCount: { other: "{n} 节课" },
  topicsCount: { other: "{n} 个主题" },
  materialHours: "约 {n} 小时的学习内容",
  continueLearning: "继续学习",
  startLearning: "开始学习",
  yourProgress: "你的进度",
  tutors: "导师",
  syllabusEyebrow: "课程大纲",
  syllabusTitleDefault: "你将学到什么",
  syllabusTitle: { nightsky: "你的任务地图", gravity: "你的飞行计划", poland: "目录", ulam: "笔记本" },
  free: "免费",
  launchMission: "开始任务",
  openMission: "打开任务",
  aboutCourse: "关于本课程",
  enrol: "报名",
  demoAccess: "演示访问",
  listPrice: "标价",
  formatsList: "形式：{list}",
  progressSaved: "进度随学随存",
  everyLessonOpen: "本演示中所有课程均已开放",
  openCourse: "打开课程",
  paymentsNote: "正式环境中由商店服务处理付款；此演示直接打开课程。",

  courseComplete: "课程已完成。可以回顾任意一节课，或查看你的证书。",
  standingTotal: "{topics} · 总计约 {time}",
  standingNext: "下一个：{title} · {left}",
  timeLeft: "还剩约 {time}",
  topicsLeft: { other: "还剩 {n} 个主题" },

  finishTitle: "完成",
  finishRing: "已完成 {n}% 的课程",
  finishComplete: "已完成",
  stillOpen: "尚未完成（{n}）",
  finishBack: "返回课程",
  finishDone: {
    coffee: "你的招牌咖啡已经准备好了。",
    oncall: "事件已关闭，指挥得当。",
    nightsky: "任务完成，探索者！",
    gravity: "已达到逃逸速度",
    poland: "测量完成",
    ulam: "问题解决——无需鹅",
    platform: "课程完成",
  },
  finishAlmost: {
    coffee: "最后一页，就快到了。",
    oncall: "快完成了：还剩几项。",
    nightsky: "离星星只差一步！",
    gravity: "即将入轨",
    poland: "几乎测量完毕",
    ulam: "还有一个问题",
    platform: "就快完成了",
  },
  demoStudent: "演示学员",

  noticeUpdatedAfterStart: "本课在你开始学习后已更新，{date}。",
  noticeUpdatedAfterDone: "自你完成后已更新，{date}。",
  noticeUpdatedAfterStartPlain: "本课在你开始学习后已更新。",
  noticeUpdatedAfterDonePlain: "自你完成后已更新。",
  noticeChange: "更改内容：{message}",
  noticeReviewed: "标记为已查看",
  noticeReviewedDone: "已标记为已查看，此通知不会再显示。",
  noticeReviewedFailed: "无法保存，请重试。",
  reattemptTitle: "有一道题已更正。",
  reattemptText: "你之前的得分会保留在记录中。重新作答即可更新成绩。",
  reattemptLink: "前往测验",
  retired: "已下线的课程，不再属于本课程",
  pendingDate: "本课的来源已于 {date} 发生变化；更新正在审核中。",
  pending: "本课的来源已发生变化；更新正在审核中。",
  extended: "你完成后新增",
  courseUpdatesTitle: "本课程有哪些变化",
  updatedSinceDone: "自你完成后已更新，{date}",
};

const BY_LOCALE: Record<PlayerLocale, PlayerStrings> = { en, pl, zh };

/** `pl` or `zh` when the tag starts with that code, else `en`. */
export const playerLocale = (lang: string | null | undefined): PlayerLocale => trainingLocale(lang);

export const playerStrings = (lang: string | null | undefined): PlayerStrings => BY_LOCALE[playerLocale(lang)];

/** The theme key for the per-theme strings; an unknown theme is the platform one. */
export const themeKey = (theme: string): ThemeKey => ((THEME_KEYS as readonly string[]).includes(theme) ? (theme as ThemeKey) : "platform");

/** "5 min", "1 h 15 min" in the language; empty for no time. */
export function minutesLabel(locale: PlayerLocale, minutes: number): string {
  const t = BY_LOCALE[locale];
  if (!minutes || minutes < 0) return "";
  if (minutes < 60) return fill(t.minutes, { n: minutes });
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  return m ? fill(t.hoursMinutes, { h, m }) : fill(t.hours, { h });
}
