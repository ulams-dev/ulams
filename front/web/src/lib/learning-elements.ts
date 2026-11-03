/**
 * "Learning elements": the didactic side of ULAMS on the public product site, as a home-page section and the
 * /learning-elements/ page. Each element has one plain-language line on why it helps people learn and the learning
 * principle behind it, and is shown LIVE where the static landing can run it: the real catalogue components rendered
 * by `<Render>` from catalogue-style documents (practice question, self-test cards, worked example, elaborative
 * question, timeline, step-by-step reading, math, a figure, a narrated video and a project card). Elements that
 * need an academy behind them (the graded quiz runner, H5P, code exercises, LiaScript, SCORM and cmi5 packages,
 * simulations, the AI tutor, extra help, certificates) link to a demo academy or a case study instead, with a real
 * screenshot where one exists, labelled as such.
 *
 * Status follows docs/ROADMAP-TODO.md: Python exercises (AX-2) and AI hints for code exercises are not built and carry
 * "Coming". The quiz types are the ones the quiz player renders (front/ui/src/elements/quiz.ts).
 */
import type { UiNode } from "@ulams/ui/render-core";
import type { Locale } from "../i18n/locales.ts";
import { productShell } from "./product-pages.ts";

export const LEARNING_ID = "learning-elements";

export const PRINCIPLES = ["retrieval", "feedback", "worked", "elaboration", "dual", "chunking", "scenario", "practice", "support", "evidence"] as const;
export type Principle = (typeof PRINCIPLES)[number];

export const GROUPS = ["practice", "explain", "interactive", "support"] as const;
export type Group = (typeof GROUPS)[number];

export const ELEMENT_KEYS = [
  "practice-question",
  "quiz",
  "flip-cards",
  "worked-example",
  "elaborative",
  "reflection",
  "math",
  "timeline",
  "steps",
  "figure",
  "video",
  "h5p",
  "code",
  "simulation",
  "liascript",
  "packages",
  "project",
  "tutor",
  "extra-help",
  "insights",
  "certificate",
] as const;
export type ElementKey = (typeof ELEMENT_KEYS)[number];

/** A real screenshot from a recorded run, shown when the element cannot run on the static landing. */
export interface Shot {
  src: string;
  width: number;
  height: number;
}

export interface ElementDef {
  key: ElementKey;
  group: Group;
  principle: Principle;
  /** Rendered live on the page (a catalogue document), or shown by a real screenshot, or described only. */
  demo: "live" | "shot" | "text";
  shot?: Shot;
  /** A demo academy lesson or a case study where the element can be tried. */
  link?: string;
  /** Parts not built yet, labelled "Coming" next to the element. */
  coming?: true;
}

/** Elements in page order. Links point at the public demo academies and case studies. */
export const ELEMENTS: ElementDef[] = [
  { key: "practice-question", group: "practice", principle: "feedback", demo: "live" },
  { key: "quiz", group: "practice", principle: "retrieval", demo: "text", link: "https://coffee.ulams.app/learn/1/17" },
  { key: "flip-cards", group: "practice", principle: "retrieval", demo: "live" },
  { key: "worked-example", group: "practice", principle: "worked", demo: "live" },
  { key: "elaborative", group: "practice", principle: "elaboration", demo: "live" },
  { key: "reflection", group: "practice", principle: "elaboration", demo: "text" },
  { key: "math", group: "explain", principle: "dual", demo: "live" },
  { key: "timeline", group: "explain", principle: "chunking", demo: "live" },
  { key: "steps", group: "explain", principle: "chunking", demo: "live" },
  { key: "figure", group: "explain", principle: "dual", demo: "live" },
  { key: "video", group: "explain", principle: "dual", demo: "live", link: "https://coffee.ulams.app/learn/1/13" },
  { key: "h5p", group: "interactive", principle: "scenario", demo: "text", link: "https://coffee.ulams.app/learn/1/14" },
  { key: "code", group: "interactive", principle: "practice", demo: "text", coming: true },
  { key: "simulation", group: "interactive", principle: "scenario", demo: "text", link: "https://gravity.ulams.app/learn/1/2" },
  { key: "liascript", group: "interactive", principle: "practice", demo: "text", link: "https://coffee.ulams.app/learn/1/11" },
  { key: "packages", group: "interactive", principle: "practice", demo: "text", link: "https://coffee.ulams.app/learn/1/10" },
  { key: "project", group: "interactive", principle: "practice", demo: "live", link: "https://coffee.ulams.app/learn/1/18" },
  { key: "tutor", group: "support", principle: "support", demo: "text" },
  { key: "extra-help", group: "support", principle: "support", demo: "text" },
  { key: "insights", group: "support", principle: "evidence", demo: "text" },
  { key: "certificate", group: "support", principle: "evidence", demo: "shot", shot: { src: "/learning-elements/coffee-finish.webp", width: 1280, height: 800 }, link: "https://coffee.ulams.app/" },
];

/** The elements the home section renders live, side by side. */
export const HOME_LIVE: ElementKey[] = ["practice-question", "flip-cards", "worked-example"];

interface ElementCopy {
  name: string;
  /** One line: why it helps people learn. */
  purpose: string;
  /** What exactly ships, in a sentence (types, formats, limits). */
  detail: string;
  /** Text alternative of the screenshot, when there is one. */
  alt?: string;
  /** What is not built yet, when the element is marked coming. */
  comingNote?: string;
}

export interface LearningCopy {
  title: string;
  description: string;
  eyebrow: string;
  heading: string;
  subtitle: string;
  body: string;
  primary: string;
  secondary: string;
  home: { eyebrow: string; title: string; lede: string; live: string; all: string; cta: string };
  groups: Record<Group, { title: string; intro: string }>;
  principles: Record<Principle, string>;
  principleLabel: string;
  liveLabel: string;
  shotLabel: string;
  textLabel: string;
  tryLabel: string;
  coming: string;
  available: string;
  elements: Record<ElementKey, ElementCopy>;
  live: {
    practice: { title: string; markdown: string; stem: string; options: Array<{ text: string; correct: boolean }>; explanation: string };
    cards: { title: string; intro: string; cards: Array<{ front: string; back: string }> };
    worked: { title: string; markdown: string };
    elaborative: { title: string; markdown: string };
    math: string;
    timeline: { title: string; intro: string; items: Array<{ label: string; title: string; text?: string; status: "done" | "current" | "upcoming" }> };
    steps: { intro: string; sections: Array<{ heading: string; markdown: string }> };
    figure: { alt: string; caption: string };
    video: { title: string };
    project: { title: string; text: string; steps: string[]; cta: string };
  };
}

const EN: LearningCopy = {
  title: "ulams — Learning elements: practice, feedback and examples",
  description: "The learning elements ULAMS ships, live: practice questions, self-test cards, worked examples, quizzes, H5P, code exercises, simulations and more.",
  eyebrow: "Learning elements",
  heading: "Built for learning, not just for content",
  subtitle: "Every element has a job: make people recall, explain, practise and get feedback.",
  body: "The elements below are the real components a ULAMS course is made of. The ones a static page can run are live here; the rest open in a demo academy.",
  primary: "Try the live elements",
  secondary: "Explore the demos",
  home: {
    eyebrow: "Learning design",
    title: "Courses that make people practise, not just read",
    lede: "Quizzes, self-test cards, worked examples, scenarios and code exercises: each element is there for a reason from learning science. Three of them, live:",
    live: "Try them here",
    all: "All learning elements",
    cta: "See every element, live",
  },
  groups: {
    practice: { title: "Practice and feedback", intro: "Recalling and explaining beats rereading. These elements make the learner do the thinking." },
    explain: { title: "Explanations that stick", intro: "Words and pictures together, in small steps, with structure the learner can see." },
    interactive: { title: "Interactive and hands-on", intro: "Scenarios, simulations and real tasks, played inside the lesson." },
    support: { title: "Support and evidence", intro: "Help when someone is stuck, and proof of what was learned." },
  },
  principles: {
    retrieval: "Retrieval practice",
    feedback: "Immediate feedback",
    worked: "Worked examples",
    elaboration: "Elaboration",
    dual: "Dual coding",
    chunking: "Chunking and structure",
    scenario: "Scenario-based learning",
    practice: "Deliberate practice",
    support: "Help at the right moment",
    evidence: "Evidence of learning",
  },
  principleLabel: "Learning principle",
  liveLabel: "Live",
  shotLabel: "Screenshot from a recorded run",
  textLabel: "In the learner player",
  tryLabel: "Try it in a demo academy",
  coming: "Coming",
  available: "Available",
  elements: {
    "practice-question": { name: "Practice question", purpose: "Answer, check, and read why: the explanation arrives right when it matters.", detail: "One question with an explanation; the result is said in words, never by colour alone, and nothing is recorded." },
    quiz: { name: "Graded quizzes", purpose: "Low-stakes testing makes knowledge last longer than rereading.", detail: "GIFT quizzes with one or several right answers, true/false, short answer, numerical, matching and essay questions, with retakes and a timer." },
    "flip-cards": { name: "Self-test cards", purpose: "Say the answer before you look: recalling strengthens memory.", detail: "Front and back cards; the answer opens on a button, and without JavaScript both sides stay readable." },
    "worked-example": { name: "Worked example", purpose: "See a problem solved step by step before trying one alone.", detail: "A titled example in steps, used in lessons and in extra help." },
    elaborative: { name: "Elaborative question", purpose: "Explaining why in your own words connects new ideas to old ones.", detail: "An open question that asks the learner to explain, with an optional model answer." },
    reflection: { name: "Reflection with AI feedback", purpose: "Explain it as if to a beginner; the gaps show up.", detail: "The learner writes an explanation and asks for feedback grounded in the course's sources, labelled as AI." },
    math: { name: "Formulas", purpose: "Show the formula next to the words, so both carry the idea.", detail: "LaTeX in any text block, rendered as MathML that screen readers can read." },
    timeline: { name: "Timeline", purpose: "A visible sequence makes a process or a history easier to hold.", detail: "Dated steps marked done, current or upcoming." },
    steps: { name: "Step-by-step reading", purpose: "One part at a time keeps a long reading manageable.", detail: "A long text split into two to twelve parts, read in order." },
    figure: { name: "Figures and diagrams", purpose: "A picture next to the text explains what words alone cannot.", detail: "Responsive images with a caption and click to zoom." },
    video: { name: "Video and audio", purpose: "Short, signposted segments are easier to follow than one long take.", detail: "Video with a poster and chapter links, audio in the native player, and the transcript as text in the lesson; captions come from the author's file." },
    h5p: { name: "H5P activities", purpose: "Drag, match and explore: active tasks instead of passive reading.", detail: "Image hotspots, drag and drop, dialog cards and branching scenarios in the demos; H5P plays inside the lesson and its results feed learner insights." },
    code: { name: "Code exercises", purpose: "Write code, run the tests, fix, run again: practice with instant results.", detail: "JavaScript and TypeScript exercises with starter files and visible tests, run in a sandbox in the learner's browser.", comingNote: "Python exercises and AI hints are coming." },
    simulation: { name: "Simulations", purpose: "Change a variable and see what happens: learning by exploring.", detail: "Interactive packages (3D scenes, maps, simulations) in a sandboxed frame, reporting progress to the course." },
    liascript: { name: "LiaScript lessons", purpose: "Text, quizzes and slides from one Markdown file, easy to keep up to date.", detail: "LiaScript courses played in a sandboxed frame on the content origin." },
    packages: { name: "SCORM and cmi5 packages", purpose: "Reuse the e-learning you already have, with tracking intact.", detail: "SCORM 1.2 and 2004 packages and cmi5 activities, played and tracked on a separate content origin." },
    project: { name: "Projects", purpose: "A real task at the end turns knowledge into a skill.", detail: "A project card with a checklist; cmi5 activities use the same card." },
    tutor: { name: "AI tutor with citations", purpose: "Questions answered from the course itself, with the passage to check.", detail: "Answers only from the course's sources with citations, says when a question is out of scope, never gives quiz answers during an attempt; off by default per academy." },
    "extra-help": { name: "Extra help", purpose: "When someone keeps getting it wrong, a short explanation and one more try.", detail: "Personal, grounded help after repeated mistakes, with the reasons shown and an AI label." },
    insights: { name: "Learner insights", purpose: "See which sections confuse people and fix them, with evidence.", detail: "Per-course analytics of where learners struggle; an author can turn a confusing section into an improvement proposal to review." },
    certificate: { name: "Certificates", purpose: "A visible milestone that motivates and proves completion.", detail: "PDF certificates per academy with a verification page, QR code, expiry and recertification.", alt: "The finish page of the coffee demo course: what is still open and the certificate the course issues." },
  },
  live: {
    practice: {
      title: "Try it yourself",
      markdown: "A customer asks you to delete their exported data.",
      stem: "Under the privacy policy, how long do we keep customer exports?",
      options: [
        { text: "Until the project ends", correct: false },
        { text: "30 days", correct: true },
        { text: "One year", correct: false },
      ],
      explanation: "Section 4 of the privacy policy says customer exports are deleted after 30 days.",
    },
    cards: {
      title: "Key terms",
      intro: "Say the definition out loud, then check it.",
      cards: [
        { front: "Personal data", back: "Any information that identifies a person, directly or indirectly." },
        { front: "Data export", back: "A copy of a customer's data, produced on request." },
        { front: "Retention period", back: "How long we keep data before deleting it." },
      ],
    },
    worked: { title: "Answering a deletion request", markdown: "Step 1. Confirm who is asking: the request must come from the account owner.\n\nStep 2. Check the policy: exports are deleted after 30 days.\n\nStep 3. Reply with the deletion date and log the request." },
    elaborative: { title: "In your own words", markdown: "Why does a short retention period lower the risk of a data breach?" },
    math: "A quiz score as a share of the maximum: $$s = \\frac{\\text{points}}{\\text{max}} \\times 100\\%$$",
    timeline: {
      title: "A deletion request, step by step",
      intro: "What happens after a customer asks.",
      items: [
        { label: "Day 0", title: "The request arrives", status: "done" },
        { label: "Day 1", title: "Identity confirmed", text: "Only the account owner can ask.", status: "done" },
        { label: "Day 2", title: "Data deleted and logged", status: "current" },
        { label: "Day 30", title: "Backups roll over", status: "upcoming" },
      ],
    },
    steps: { intro: "Three rules for handling customer data.", sections: [{ heading: "Ask who it is", markdown: "Confirm the account owner before you act." }, { heading: "Check the policy", markdown: "The **retention period** decides what you keep." }, { heading: "Log it", markdown: "Every request goes in the log with its date." }] },
    figure: { alt: "The lesson player of the coffee demo academy: the course outline on the left and a video lesson on the right.", caption: "Screenshot of the coffee demo academy, shown with the Figure component: click to zoom." },
    video: { title: "Generative UI in ULAMS (narrated blog clip, about 2 minutes)" },
    project: { title: "Handle a real request", text: "Take one anonymised request from last month and walk it through the policy.", steps: ["Confirm the requester", "Find the retention rule", "Write the reply"], cta: "Open a demo academy" },
  },
};

const PL: LearningCopy = {
  title: "ulams — Elementy nauki: ćwiczenia, informacja zwrotna, przykłady",
  description: "Elementy nauki w ULAMS na żywo: pytania ćwiczeniowe, fiszki, przykłady rozwiązane krok po kroku, quizy, H5P, ćwiczenia z kodu, symulacje i więcej.",
  eyebrow: "Elementy nauki",
  heading: "Zbudowane do nauki, nie tylko do treści",
  subtitle: "Każdy element ma zadanie: sprawić, by ludzie przypominali sobie, wyjaśniali, ćwiczyli i dostawali informację zwrotną.",
  body: "Poniżej są prawdziwe komponenty, z których składa się kurs ULAMS. Te, które może uruchomić statyczna strona, działają tu na żywo; pozostałe otwierają się w akademii demonstracyjnej.",
  primary: "Wypróbuj elementy na żywo",
  secondary: "Zobacz demo",
  home: {
    eyebrow: "Projektowanie nauki",
    title: "Kursy, w których się ćwiczy, a nie tylko czyta",
    lede: "Quizy, fiszki, przykłady rozwiązane krok po kroku, scenariusze i ćwiczenia z kodu: każdy element ma uzasadnienie w nauce o uczeniu się. Trzy z nich, na żywo:",
    live: "Wypróbuj tutaj",
    all: "Wszystkie elementy nauki",
    cta: "Zobacz wszystkie elementy na żywo",
  },
  groups: {
    practice: { title: "Ćwiczenie i informacja zwrotna", intro: "Przypominanie i wyjaśnianie działają lepiej niż ponowne czytanie. Te elementy każą uczącemu się myśleć." },
    explain: { title: "Wyjaśnienia, które zostają w głowie", intro: "Słowa i obrazy razem, małymi krokami, z widoczną strukturą." },
    interactive: { title: "Interaktywnie i w praktyce", intro: "Scenariusze, symulacje i prawdziwe zadania, w samej lekcji." },
    support: { title: "Wsparcie i dowody", intro: "Pomoc, gdy ktoś utknie, i dowód tego, czego się nauczył." },
  },
  principles: {
    retrieval: "Praktyka przypominania",
    feedback: "Natychmiastowa informacja zwrotna",
    worked: "Przykłady rozwiązane",
    elaboration: "Elaboracja",
    dual: "Podwójne kodowanie",
    chunking: "Porcjowanie i struktura",
    scenario: "Nauka przez scenariusze",
    practice: "Celowe ćwiczenie",
    support: "Pomoc we właściwym momencie",
    evidence: "Dowody nauki",
  },
  principleLabel: "Zasada uczenia się",
  liveLabel: "Na żywo",
  shotLabel: "Zrzut ekranu z nagranego przebiegu",
  textLabel: "W odtwarzaczu uczącego się",
  tryLabel: "Wypróbuj w akademii demonstracyjnej",
  coming: "Wkrótce",
  available: "Dostępne",
  elements: {
    "practice-question": { name: "Pytanie ćwiczeniowe", purpose: "Odpowiedz, sprawdź i przeczytaj dlaczego: wyjaśnienie przychodzi wtedy, gdy jest potrzebne.", detail: "Jedno pytanie z wyjaśnieniem; wynik jest podany słowami, nigdy samym kolorem, i nic nie jest zapisywane." },
    quiz: { name: "Quizy z oceną", purpose: "Sprawdzanie wiedzy bez stawki utrwala ją lepiej niż ponowne czytanie.", detail: "Quizy GIFT: jedna lub kilka poprawnych odpowiedzi, prawda/fałsz, krótka odpowiedź, liczbowe, dopasowanie i odpowiedź opisowa, z powtórkami i limitem czasu." },
    "flip-cards": { name: "Fiszki", purpose: "Powiedz odpowiedź, zanim spojrzysz: przypominanie wzmacnia pamięć.", detail: "Karty z awersem i rewersem; odpowiedź otwiera przycisk, a bez JavaScriptu obie strony są czytelne." },
    "worked-example": { name: "Przykład rozwiązany", purpose: "Zobacz problem rozwiązany krok po kroku, zanim spróbujesz sam.", detail: "Przykład w krokach, w lekcjach i w dodatkowej pomocy." },
    elaborative: { name: "Pytanie elaboracyjne", purpose: "Wyjaśnienie własnymi słowami łączy nowe idee z tym, co już wiesz.", detail: "Otwarte pytanie, które prosi o wyjaśnienie, z opcjonalną odpowiedzią wzorcową." },
    reflection: { name: "Refleksja z informacją zwrotną AI", purpose: "Wyjaśnij jak początkującemu; luki wyjdą same.", detail: "Uczący się pisze wyjaśnienie i prosi o informację zwrotną opartą na źródłach kursu, oznaczoną jako AI." },
    math: { name: "Wzory", purpose: "Wzór obok słów, żeby oba niosły tę samą myśl.", detail: "LaTeX w każdym bloku tekstu, wyświetlany jako MathML czytelny dla czytników ekranu." },
    timeline: { name: "Oś czasu", purpose: "Widoczna kolejność ułatwia zapamiętanie procesu lub historii.", detail: "Etapy z datami, oznaczone jako zakończone, bieżące lub nadchodzące." },
    steps: { name: "Czytanie krok po kroku", purpose: "Po jednej części naraz: długi tekst staje się do ogarnięcia.", detail: "Długi tekst podzielony na od dwóch do dwunastu części, czytanych po kolei." },
    figure: { name: "Ilustracje i diagramy", purpose: "Obraz obok tekstu wyjaśnia to, czego same słowa nie przekażą.", detail: "Responsywne obrazy z podpisem i powiększeniem po kliknięciu." },
    video: { name: "Wideo i audio", purpose: "Krótkie, opisane fragmenty łatwiej śledzić niż jedno długie nagranie.", detail: "Wideo z plakatem i linkami do rozdziałów, audio w natywnym odtwarzaczu i transkrypcja jako tekst w lekcji; napisy pochodzą z pliku autora." },
    h5p: { name: "Aktywności H5P", purpose: "Przeciągaj, dopasowuj i odkrywaj: aktywne zadania zamiast biernego czytania.", detail: "Punkty na obrazie, przeciąganie, karty dialogowe i scenariusze z wyborami w demach; H5P działa w lekcji, a wyniki trafiają do analityki uczących się." },
    code: { name: "Ćwiczenia z kodu", purpose: "Napisz kod, uruchom testy, popraw, uruchom znowu: ćwiczenie z natychmiastowym wynikiem.", detail: "Ćwiczenia w JavaScripcie i TypeScripcie z plikami startowymi i widocznymi testami, uruchamiane w piaskownicy w przeglądarce uczącego się.", comingNote: "Ćwiczenia w Pythonie i podpowiedzi AI są w drodze." },
    simulation: { name: "Symulacje", purpose: "Zmień zmienną i zobacz, co się stanie: nauka przez odkrywanie.", detail: "Pakiety interaktywne (sceny 3D, mapy, symulacje) w izolowanej ramce, raportujące postęp do kursu." },
    liascript: { name: "Lekcje LiaScript", purpose: "Tekst, quizy i slajdy z jednego pliku Markdown, łatwe do aktualizacji.", detail: "Kursy LiaScript odtwarzane w izolowanej ramce na osobnym originie treści." },
    packages: { name: "Pakiety SCORM i cmi5", purpose: "Wykorzystaj e-learning, który już masz, z zachowanym śledzeniem.", detail: "Pakiety SCORM 1.2 i 2004 oraz aktywności cmi5, odtwarzane i śledzone na osobnym originie treści." },
    project: { name: "Projekty", purpose: "Prawdziwe zadanie na koniec zamienia wiedzę w umiejętność.", detail: "Karta projektu z listą kroków; aktywności cmi5 używają tej samej karty." },
    tutor: { name: "Tutor AI z cytatami", purpose: "Odpowiedzi z samego kursu, z fragmentem do sprawdzenia.", detail: "Odpowiada tylko ze źródeł kursu, z cytatami, mówi, gdy pytanie wykracza poza kurs, nie podaje odpowiedzi do quizu w trakcie podejścia; domyślnie wyłączony w akademii." },
    "extra-help": { name: "Dodatkowa pomoc", purpose: "Gdy ktoś wciąż się myli: krótkie wyjaśnienie i jeszcze jedna próba.", detail: "Osobista pomoc oparta na źródłach po powtarzających się błędach, z podanymi powodami i etykietą AI." },
    insights: { name: "Analityka uczących się", purpose: "Zobacz, które sekcje sprawiają kłopot, i popraw je na podstawie danych.", detail: "Analityka kursu pokazuje, gdzie uczący się mają trudności; autor może zamienić niejasną sekcję w propozycję poprawki do przeglądu." },
    certificate: { name: "Certyfikaty", purpose: "Widoczny kamień milowy, który motywuje i potwierdza ukończenie.", detail: "Certyfikaty PDF dla każdej akademii ze stroną weryfikacji, kodem QR, datą ważności i recertyfikacją.", alt: "Strona ukończenia kursu w demo coffee: co jeszcze zostało i certyfikat, który wydaje kurs." },
  },
  live: {
    practice: {
      title: "Spróbuj sam",
      markdown: "Klient prosi o usunięcie wyeksportowanych danych.",
      stem: "Jak długo według polityki prywatności przechowujemy eksporty danych klientów?",
      options: [
        { text: "Do końca projektu", correct: false },
        { text: "30 dni", correct: true },
        { text: "Rok", correct: false },
      ],
      explanation: "Paragraf 4 polityki prywatności mówi, że eksporty danych klientów usuwamy po 30 dniach.",
    },
    cards: {
      title: "Kluczowe pojęcia",
      intro: "Powiedz definicję na głos, potem sprawdź.",
      cards: [
        { front: "Dane osobowe", back: "Każda informacja, która pozwala zidentyfikować osobę, bezpośrednio lub pośrednio." },
        { front: "Eksport danych", back: "Kopia danych klienta przygotowana na jego prośbę." },
        { front: "Okres przechowywania", back: "Jak długo trzymamy dane, zanim je usuniemy." },
      ],
    },
    worked: { title: "Odpowiedź na prośbę o usunięcie", markdown: "Krok 1. Potwierdź, kto pyta: prośba musi pochodzić od właściciela konta.\n\nKrok 2. Sprawdź politykę: eksporty usuwamy po 30 dniach.\n\nKrok 3. Odpisz z datą usunięcia i zapisz prośbę w rejestrze." },
    elaborative: { title: "Własnymi słowami", markdown: "Dlaczego krótki okres przechowywania zmniejsza ryzyko wycieku danych?" },
    math: "Wynik quizu jako odsetek maksimum: $$s = \\frac{\\text{punkty}}{\\text{maks}} \\times 100\\%$$",
    timeline: {
      title: "Prośba o usunięcie, krok po kroku",
      intro: "Co się dzieje, gdy klient poprosi.",
      items: [
        { label: "Dzień 0", title: "Prośba wpływa", status: "done" },
        { label: "Dzień 1", title: "Tożsamość potwierdzona", text: "Prosić może tylko właściciel konta.", status: "done" },
        { label: "Dzień 2", title: "Dane usunięte i zapisane w rejestrze", status: "current" },
        { label: "Dzień 30", title: "Kopie zapasowe się nadpisują", status: "upcoming" },
      ],
    },
    steps: { intro: "Trzy zasady pracy z danymi klientów.", sections: [{ heading: "Ustal, kto pyta", markdown: "Potwierdź właściciela konta, zanim cokolwiek zrobisz." }, { heading: "Sprawdź politykę", markdown: "**Okres przechowywania** decyduje, co zostaje." }, { heading: "Zapisz to", markdown: "Każda prośba trafia do rejestru z datą." }] },
    figure: { alt: "Odtwarzacz lekcji w akademii demonstracyjnej coffee: spis kursu po lewej i lekcja wideo po prawej.", caption: "Zrzut ekranu z akademii demonstracyjnej coffee, pokazany komponentem Figure: kliknij, by powiększyć." },
    video: { title: "Generatywny interfejs w ULAMS (klip z bloga z lektorem, ok. 2 minut, po angielsku)" },
    project: { title: "Obsłuż prawdziwą prośbę", text: "Weź jedną zanonimizowaną prośbę z zeszłego miesiąca i przeprowadź ją przez politykę.", steps: ["Potwierdź, kto prosi", "Znajdź zasadę przechowywania", "Napisz odpowiedź"], cta: "Otwórz akademię demonstracyjną" },
  },
};

const ZH: LearningCopy = {
  title: "ulams — 学习元素：练习、反馈与示例",
  description: "ULAMS 提供的学习元素实时演示：练习题、自测卡片、示例解析、测验、H5P、编程练习、模拟等。",
  eyebrow: "学习元素",
  heading: "为学习而建，不只是为了内容",
  subtitle: "每个元素都有任务：让学员回忆、解释、练习并获得反馈。",
  body: "下面是构成 ULAMS 课程的真实组件。静态页面能运行的，这里直接实时演示；其余的在演示学院中打开。",
  primary: "试用实时元素",
  secondary: "浏览演示",
  home: {
    eyebrow: "学习设计",
    title: "让人动手练习，而不只是阅读的课程",
    lede: "测验、自测卡片、示例解析、情景和编程练习：每个元素都有学习科学的依据。其中三个可以直接试用：",
    live: "在这里试试",
    all: "全部学习元素",
    cta: "查看全部元素的实时演示",
  },
  groups: {
    practice: { title: "练习与反馈", intro: "回忆和解释比反复阅读更有效。这些元素让学员自己动脑。" },
    explain: { title: "记得住的讲解", intro: "文字与图像结合，小步推进，结构一目了然。" },
    interactive: { title: "互动与实操", intro: "情景、模拟和真实任务，直接在课时中进行。" },
    support: { title: "支持与证据", intro: "在学员卡住时提供帮助，并证明学到了什么。" },
  },
  principles: {
    retrieval: "提取练习",
    feedback: "即时反馈",
    worked: "示例解析",
    elaboration: "精细加工",
    dual: "双重编码",
    chunking: "分块与结构",
    scenario: "情景式学习",
    practice: "刻意练习",
    support: "适时帮助",
    evidence: "学习证据",
  },
  principleLabel: "学习原理",
  liveLabel: "实时",
  shotLabel: "录制运行的截图",
  textLabel: "在学员播放器中",
  tryLabel: "在演示学院中试用",
  coming: "即将推出",
  available: "已提供",
  elements: {
    "practice-question": { name: "练习题", purpose: "作答、检查、看原因：解释在最需要的时候出现。", detail: "一道带解释的题目；结果用文字说明而不只靠颜色，且不做任何记录。" },
    quiz: { name: "计分测验", purpose: "低风险的测试比反复阅读记得更牢。", detail: "GIFT 测验：单选或多选、判断、简答、数值、匹配和问答题，支持重做和计时。" },
    "flip-cards": { name: "自测卡片", purpose: "先说出答案再看：回忆会加深记忆。", detail: "正反两面的卡片；点按钮显示答案，没有 JavaScript 时两面都可读。" },
    "worked-example": { name: "示例解析", purpose: "先看问题如何一步步解决，再自己尝试。", detail: "分步骤的示例，用于课时和额外帮助。" },
    elaborative: { name: "精细加工问题", purpose: "用自己的话解释原因，把新知识和旧知识联系起来。", detail: "要求学员解释的开放式问题，可附参考答案。" },
    reflection: { name: "带 AI 反馈的反思", purpose: "像给初学者讲解一样写出来，漏洞自然显现。", detail: "学员写下解释并请求基于课程来源的反馈，标注为 AI。" },
    math: { name: "公式", purpose: "公式与文字并列，共同表达同一个概念。", detail: "任何文本块中都可写 LaTeX，渲染为屏幕阅读器可读的 MathML。" },
    timeline: { name: "时间线", purpose: "看得见的顺序让流程或历史更容易记住。", detail: "带日期的阶段，标记为已完成、进行中或即将进行。" },
    steps: { name: "分步阅读", purpose: "一次一部分，长篇阅读也不吃力。", detail: "长文本分成二到十二个部分，按顺序阅读。" },
    figure: { name: "图片与图示", purpose: "文字旁的图片能说明文字难以表达的内容。", detail: "带说明的响应式图片，点击可放大。" },
    video: { name: "视频与音频", purpose: "简短、有标示的片段比一整段长视频更容易跟上。", detail: "带封面和章节链接的视频、原生播放器中的音频，以及课时中的文字稿；字幕来自作者提供的文件。" },
    h5p: { name: "H5P 活动", purpose: "拖放、匹配和探索：主动任务取代被动阅读。", detail: "演示中有图片热点、拖放、对话卡片和分支情景；H5P 在课时内运行，结果汇入学习洞察。" },
    code: { name: "编程练习", purpose: "写代码、运行测试、修改、再运行：即时得到结果的练习。", detail: "JavaScript 和 TypeScript 练习，带起始文件和可见测试，在学员浏览器的沙箱中运行。", comingNote: "Python 练习和 AI 提示即将推出。" },
    simulation: { name: "模拟", purpose: "改变一个变量，看看会发生什么：在探索中学习。", detail: "交互包（3D 场景、地图、模拟）在沙箱框架中运行，并向课程报告进度。" },
    liascript: { name: "LiaScript 课时", purpose: "一个 Markdown 文件里有文字、测验和幻灯片，易于更新。", detail: "LiaScript 课程在独立内容源的沙箱框架中播放。" },
    packages: { name: "SCORM 与 cmi5 包", purpose: "沿用你已有的在线课程，跟踪记录完整保留。", detail: "SCORM 1.2 和 2004 包以及 cmi5 活动，在独立的内容源上播放和跟踪。" },
    project: { name: "项目", purpose: "最后的真实任务把知识变成技能。", detail: "带检查清单的项目卡片；cmi5 活动使用同样的卡片。" },
    tutor: { name: "带引用的 AI 导师", purpose: "从课程本身回答问题，并附上可核对的段落。", detail: "只依据课程来源回答并附引用，超出范围时会说明，测验进行中不给答案；各学院默认关闭。" },
    "extra-help": { name: "额外帮助", purpose: "当学员反复出错时：一段简短讲解，再试一次。", detail: "在反复出错后提供基于来源的个性化帮助，说明原因并标注 AI。" },
    insights: { name: "学习洞察", purpose: "看到哪些章节让人困惑，并据此修改。", detail: "按课程分析学员在哪里遇到困难；作者可以把令人困惑的章节变成待审阅的改进建议。" },
    certificate: { name: "证书", purpose: "看得见的里程碑，既激励学员，也证明完成。", detail: "每个学院的 PDF 证书，带验证页面、二维码、有效期和重新认证。", alt: "咖啡演示课程的完成页：尚未完成的内容以及课程颁发的证书。" },
  },
  live: {
    practice: {
      title: "自己试试",
      markdown: "一位客户要求删除其导出的数据。",
      stem: "根据隐私政策，我们保留客户导出数据多长时间？",
      options: [
        { text: "直到项目结束", correct: false },
        { text: "30 天", correct: true },
        { text: "一年", correct: false },
      ],
      explanation: "隐私政策第 4 节规定，客户导出数据在 30 天后删除。",
    },
    cards: {
      title: "关键术语",
      intro: "先大声说出定义，再核对。",
      cards: [
        { front: "个人数据", back: "能直接或间接识别某个人的任何信息。" },
        { front: "数据导出", back: "应客户要求制作的客户数据副本。" },
        { front: "保留期限", back: "数据在删除前保留的时间。" },
      ],
    },
    worked: { title: "回复删除请求", markdown: "第 1 步：确认请求人：请求必须来自账户所有者。\n\n第 2 步：查看政策：导出数据在 30 天后删除。\n\n第 3 步：回复删除日期并记录该请求。" },
    elaborative: { title: "用你自己的话", markdown: "为什么较短的保留期限能降低数据泄露的风险？" },
    math: "测验得分占满分的比例：$$s = \\frac{\\text{得分}}{\\text{满分}} \\times 100\\%$$",
    timeline: {
      title: "删除请求的处理步骤",
      intro: "客户提出请求之后会发生什么。",
      items: [
        { label: "第 0 天", title: "收到请求", status: "done" },
        { label: "第 1 天", title: "确认身份", text: "只有账户所有者可以提出请求。", status: "done" },
        { label: "第 2 天", title: "删除数据并记录", status: "current" },
        { label: "第 30 天", title: "备份轮换完成", status: "upcoming" },
      ],
    },
    steps: { intro: "处理客户数据的三条规则。", sections: [{ heading: "确认身份", markdown: "行动前先确认账户所有者。" }, { heading: "查看政策", markdown: "**保留期限**决定保留什么。" }, { heading: "做好记录", markdown: "每个请求都连同日期记入日志。" }] },
    figure: { alt: "咖啡演示学院的课时播放器：左侧是课程目录，右侧是视频课时。", caption: "咖啡演示学院的截图，以 Figure 组件展示：点击可放大。" },
    video: { title: "ULAMS 中的生成式界面（博客解说短片，约 2 分钟，英语）" },
    project: { title: "处理一个真实请求", text: "取上个月的一个匿名请求，按照政策走一遍流程。", steps: ["确认请求人", "找到保留规则", "撰写回复"], cta: "打开演示学院" },
  },
};

const COPY: Record<Locale, LearningCopy> = { en: EN, pl: PL, zh: ZH };

export const learningCopy = (locale: Locale): LearningCopy => COPY[locale];

/** A real screenshot of the coffee demo academy's lesson player (front/docs-site screens). */
export const FIGURE_SHOT: Shot = { src: "/learning-elements/coffee-lesson.webp", width: 1280, height: 800 };
/** The narrated blog clip about generative UI, the only public video with sound. */
export const VIDEO = { src: "/blog/generative-ui-in-ulams/clip-en.mp4", poster: "/blog/generative-ui-in-ulams/poster.jpg", width: 960, height: 540 };

/** The catalogue document that renders an element live, in one language; null when the element is not live. */
export function liveNode(key: ElementKey, locale: Locale): UiNode | null {
  const l = COPY[locale].live;
  const join = (a: string, b: string) => (locale === "zh" ? `${a}。${b}` : `${a}. ${b}`);
  switch (key) {
    case "practice-question":
      return { component: "PracticeQuestion", props: { id: `le-${locale}-pq`, title: l.practice.title, markdown: l.practice.markdown, practice: { stem: l.practice.stem, options: l.practice.options, explanation: l.practice.explanation } } };
    case "flip-cards":
      // no title: the card's own heading names the element, and FlipCards would add a second-level heading inside it
      return { component: "FlipCards", props: { id: "le-cards", intro: join(l.cards.title, l.cards.intro), cards: l.cards.cards } };
    case "worked-example":
      return { component: "WorkedExample", props: { title: l.worked.title, markdown: l.worked.markdown } };
    case "elaborative":
      return { component: "ElaborativeQuestion", props: { title: l.elaborative.title, markdown: l.elaborative.markdown } };
    case "math":
      return { component: "Prose", props: { markdown: l.math, size: "md" } };
    case "timeline":
      return { component: "Timeline", props: { id: "le-timeline", intro: join(l.timeline.title, l.timeline.intro), items: l.timeline.items } };
    case "steps":
      return { component: "ProseSteps", props: { id: "le-steps", intro: l.steps.intro, sections: l.steps.sections.map((s) => ({ ...s, level: 3 })), size: "md" } };
    case "figure":
      return { component: "Figure", props: { image: { ...FIGURE_SHOT, alt: l.figure.alt }, caption: l.figure.caption } };
    case "video":
      return { component: "VideoPlayer", props: { ...VIDEO, title: l.video.title } };
    case "project":
      return { component: "ActivityCard", props: { kind: "project", title: l.project.title, text: l.project.text, steps: l.project.steps, cta: { label: l.project.cta, href: "https://coffee.ulams.app/learn/1/18" } } };
    default:
      return null;
  }
}

/** The /learning-elements/ page shell in one language (lib/product-pages.ts). */
export function learningPage(locale: Locale, request: Request, url: URL) {
  const c = COPY[locale];
  return productShell("learning-elements", locale, {
    title: c.title,
    description: c.description,
    eyebrow: c.eyebrow,
    heading: c.heading,
    subtitle: c.subtitle,
    body: c.body,
    primary: { label: c.primary, href: "#practice" },
    secondary: { label: c.secondary, href: `${locale === "en" ? "" : `/${locale}`}/#demos` },
  }, request, url);
}
