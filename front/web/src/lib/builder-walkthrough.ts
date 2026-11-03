/**
 * "The builder asks, you get a course": the home-page walkthrough of the real Course Builder flow, told with the
 * screenshots of two recorded runs (the docs-repository and the Notion-page case studies, lib/course-generation.ts).
 *
 * The questions are the builder's own: the six brief questions the model words for each source (audience, level,
 * duration, tone, assessments, language; `BriefService::KEYS`) and the three it always asks itself (other audiences
 * for course variants, price, and the site's look; `InterviewService::fixedQuestions`). The captions under each
 * screenshot are the case studies' own captions, so the two pages never disagree. The figures are the one measured
 * docs-repository run, labelled as such; the lesson titles and citation chips are copied from its screens.
 */
import type { Locale } from "../i18n/locales.ts";
import { SHOTS, generationCopy, type CaseId } from "./course-generation.ts";

export const WALKTHROUGH_ID = "builder-walkthrough";

/** The frames of the walkthrough, in order: which recorded run, which screenshot. */
export const WALK_FRAMES = [
  { key: "connect", run: "docs-repo", file: "03-connect.png" },
  { key: "audience", run: "docs-repo", file: "04-interview.png" },
  { key: "tone", run: "notion-page", file: "04-tone.png" },
  { key: "brief", run: "docs-repo", file: "05-brief.png" },
  { key: "outline", run: "docs-repo", file: "06-outline.png" },
  { key: "progress", run: "docs-repo", file: "08-progress.png" },
  { key: "checks", run: "docs-repo", file: "09-apply.png" },
  { key: "publish", run: "docs-repo", file: "11-published.png" },
  { key: "lesson", run: "docs-repo", file: "13-lesson.png" },
] as const satisfies ReadonlyArray<{ key: string; run: CaseId; file: string }>;

export type WalkKey = (typeof WALK_FRAMES)[number]["key"];

export interface WalkStepCopy {
  /** Short name in the step list. */
  label: string;
  title: string;
  /** The builder's questions or prompts on this screen, in plain words; empty when there is nothing to answer. */
  asks: string[];
  /** What the author decides or checks here, and why it matters for an L&D team. */
  decide: string;
  /** Text alternative of the screenshot. */
  alt: string;
}

export interface WalkthroughCopy {
  eyebrow: string;
  title: string;
  lede: string;
  stepsLabel: string;
  asksLabel: string;
  decideLabel: string;
  previous: string;
  next: string;
  /** "Step {n} of {total}". */
  status: string;
  /** Prefix of the link to the full-size screenshot. */
  open: string;
  /** Which recorded run a screenshot comes from. */
  from: Record<"docs-repo" | "notion-page", string>;
  steps: Record<WalkKey, WalkStepCopy>;
  result: {
    title: string;
    badge: string;
    course: string;
    courseNote: string;
    modules: Array<{ title: string; lessons: string[] }>;
    quiz: string;
    citesLabel: string;
    cites: string[];
    facts: Array<{ value: string; label: string }>;
    note: string;
    cases: Array<{ label: string; page: "case-docs-repo" | "case-notion" }>;
  };
}

/** The course of the measured run, as its screens show it (lesson titles stay in the language it was written in). */
const MODULES = [
  { title: "Pigeon Foundations and Sending Events", lessons: ["1.1 How Pigeon Works: Concepts and Roles", "1.2 Sending Events: Requests, Idempotency and Limits"] },
  { title: "Receiving Deliveries Securely", lessons: ["2.1 Verifying Pigeon Signatures", "2.2 Rotating Signing Secrets Without Downtime"] },
  { title: "Operating Delivery Health", lessons: ["3.1 Retries, Exhaustion and Endpoint Disabling", "3.2 Reading the Delivery Log and Replaying Deliveries"] },
];
const COURSE = "Pigeon Webhook Delivery: Sending, Verifying and Operating";
const CITES = ["overview.md §1 Concepts", "overview.md §2 Who uses what", "retries-and-replay.md §3 Replay", "retries-and-replay.md §4 Reading the delivery log"];
const CASES = (docs: string, notion: string) => [
  { label: docs, page: "case-docs-repo" as const },
  { label: notion, page: "case-notion" as const },
];

const EN: WalkthroughCopy = {
  eyebrow: "The builder asks, you get a course",
  title: "Answer a few questions. Review. Publish.",
  lede: "The real Course Builder, screen by screen, from two recorded runs. Next to each screen: the questions it asks, in plain words, and what you decide.",
  stepsLabel: "Steps of the walkthrough",
  asksLabel: "The builder asks",
  decideLabel: "What you decide",
  previous: "Previous step",
  next: "Next step",
  status: "Step {n} of {total}",
  open: "Open the full-size screenshot",
  from: { "docs-repo": "Screen from the docs-repository run", "notion-page": "Screen from the Notion-page run" },
  steps: {
    connect: {
      label: "Source",
      title: "Point it at the material you already have",
      asks: ["Where does the material live? A GitHub repository, Google Drive or Notion, or an uploaded PDF, Word or Markdown file.", "Which repository and branch? A public repository needs no access token."],
      decide: "The builder reads the source first and splits it into short passages it can cite: 13 in this run. Uploaded content is treated as data, never as instructions.",
      alt: "Course Builder, Connect a repository: GitHub selected, the repository ulams-dev/demo-docs, the default branch and an empty access-token field.",
    },
    audience: {
      label: "Audience",
      title: "Who it is for, at what level, for how long",
      asks: ["Who is this course for?", "What is the starting level of your learners?", "How long should the whole course take, and how long is one lesson?"],
      decide: "Each question says why it is asked, based on what the source contains, and offers a default. Skip one and the default is used. Every answer goes into the course brief, where you can edit it later.",
      alt: "The interview, question 3 of 8: how long should the whole course take, with 30 min, 1 h and 2 h options and a Why I ask note. The course brief on the right shows both roles and Intermediate.",
    },
    tone: {
      label: "Tone",
      title: "Tone, testing and language",
      asks: ["What tone should the course have? Friendly, professional, playful or academic.", "Should learners be tested? A quiz after each lesson, a final test, or both.", "In which language should the course be written?"],
      decide: "The builder suggests a tone from the source itself: a friendly onboarding guide gets a friendly course. You keep the last word.",
      alt: "The interview, question 4 of 8: what tone should the course have, with a Why I ask note about the warm voice of customer service. The brief shows new support agents, Beginner and 60 minutes.",
    },
    brief: {
      label: "Versions",
      title: "Versions by level or role, price and look",
      asks: ["Who else is this course for? One course for everyone, or extra versions for beginner, intermediate or expert learners, or for named roles.", "Will the course be free or paid?", "Which look should the site have? Asked when you may change the theme."],
      decide: "The course you approve first is the base. Each extra level or role gets its own version from the same sources, every block cited, and you review it as a list of changes against the base.",
      alt: "Six of eight questions answered: both developers and operators, intermediate, 60 minutes in 10-minute lessons, professional, a quiz after each lesson, English. The brief on the right is filled in.",
    },
    outline: {
      label: "Outline",
      title: "Approve the outline before anything is written",
      asks: ["Approve outline and generate, or request changes with a comment."],
      decide: "Every learning objective shows the source passage it will cite. Nothing reaches your academy until you approve the generated course.",
      alt: "The end of the proposed outline: learning objectives with citation chips such as retries-and-replay.md §3 Replay, a comment field, and the Approve outline & generate and Request changes buttons.",
    },
    progress: {
      label: "Writing",
      title: "Watch it write, with the cost in view",
      asks: [],
      decide: "Lessons, a grounding check, interactive elements and quizzes run in order. Each lesson shows its citations and its cost against the course's spending cap (USD 2.50 in this run).",
      alt: "Generation progress: six lessons, three ready and three being written, each with its number of citations and its cost; USD 0.21 of USD 2.50 spent.",
    },
    checks: {
      label: "Checks",
      title: "Read the warnings before your academy changes",
      asks: ["Apply to my academy? The course is created unpublished."],
      decide: "You see what will be created, item by item, and every statement the grounding check could not fully support: three in this run, each with its reason.",
      alt: "Create the course in your academy: a table of items to create (1 course, 3 lessons, 12 topics, 18 quiz questions, 1 page) and three warnings about possibly unsupported statements, above the Apply to my academy button.",
    },
    publish: {
      label: "Publish",
      title: "Acknowledge the warnings, then publish",
      asks: ["Publish? The warnings have to be acknowledged first."],
      decide: "Before publishing: 35 of 35 blocks cite 12 source passages and all 18 quiz questions passed the support check. Applying and publishing are separate steps.",
      alt: "The publish step after the warnings were acknowledged: the button reads Published.",
    },
    lesson: {
      label: "Learner",
      title: "What the learner gets",
      asks: [],
      decide: "A lesson in the learner player, with the passages it cites listed under the text, and a quiz after each lesson in the course outline.",
      alt: "A lesson in the learner player: the course outline in three modules on the left, the lesson text on the right and its three sources listed under it.",
    },
  },
  result: {
    title: "The course this run produced",
    badge: "One measured run · draft for author review",
    course: COURSE,
    courseNote: "Three modules, six lessons, as the screens show them.",
    modules: MODULES,
    quiz: "A quiz after each lesson: 18 questions in all",
    citesLabel: "Citation chips from the course",
    cites: CITES,
    facts: [
      { value: "13", label: "passages read" },
      { value: "6", label: "lessons" },
      { value: "18", label: "quiz questions" },
      { value: "35/35", label: "blocks cite the source" },
      { value: "7 min 44 s", label: "of generation" },
      { value: "USD 0.28", label: "model cost, 27 calls" },
    ],
    note: "One run on a public documentation repository about a fictional product. Generation time only; your review comes on top.",
    cases: CASES("Full case study: a docs repository", "Full case study: a Notion page"),
  },
};

const PL: WalkthroughCopy = {
  eyebrow: "Kreator pyta, Ty dostajesz kurs",
  title: "Odpowiedz na kilka pytań. Przejrzyj. Opublikuj.",
  lede: "Prawdziwy kreator kursów, ekran po ekranie, z dwóch nagranych przebiegów. Obok każdego ekranu: pytania, które zadaje, prostym językiem, i to, o czym decydujesz.",
  stepsLabel: "Kroki przewodnika",
  asksLabel: "Kreator pyta",
  decideLabel: "O czym decydujesz",
  previous: "Poprzedni krok",
  next: "Następny krok",
  status: "Krok {n} z {total}",
  open: "Otwórz zrzut ekranu w pełnym rozmiarze",
  from: { "docs-repo": "Ekran z przebiegu na repozytorium dokumentacji", "notion-page": "Ekran z przebiegu na stronie Notion" },
  steps: {
    connect: {
      label: "Źródło",
      title: "Wskaż materiały, które już masz",
      asks: ["Gdzie są materiały? Repozytorium GitHub, Google Drive lub Notion albo wgrany plik PDF, Word lub Markdown.", "Które repozytorium i która gałąź? Publiczne repozytorium nie wymaga tokenu dostępu."],
      decide: "Kreator najpierw czyta źródło i dzieli je na krótkie fragmenty, które może cytować: w tym przebiegu 13. Wgrana treść jest traktowana jako dane, nigdy jako polecenia.",
      alt: "Kreator kursów, Połącz repozytorium: wybrany GitHub, repozytorium ulams-dev/demo-docs, domyślna gałąź i puste pole tokenu dostępu.",
    },
    audience: {
      label: "Odbiorcy",
      title: "Dla kogo, na jakim poziomie, na jak długo",
      asks: ["Dla kogo jest ten kurs?", "Od jakiego poziomu zaczynają uczący się?", "Ile ma trwać cały kurs i ile jedna lekcja?"],
      decide: "Każde pytanie mówi, dlaczego jest zadane, na podstawie treści źródła, i proponuje wartość domyślną. Pominiesz pytanie, zostanie wartość domyślna. Każda odpowiedź trafia do briefu kursu, gdzie możesz ją później zmienić.",
      alt: "Wywiad, pytanie 3 z 8: ile ma trwać cały kurs, z opcjami 30 min, 1 h i 2 h oraz notatką Why I ask. Brief kursu po prawej pokazuje obie role i poziom Intermediate.",
    },
    tone: {
      label: "Ton",
      title: "Ton, sprawdzanie wiedzy i język",
      asks: ["Jaki ton ma mieć kurs? Przyjazny, profesjonalny, swobodny czy akademicki.", "Czy sprawdzać wiedzę? Quiz po każdej lekcji, test końcowy albo jedno i drugie.", "W jakim języku ma powstać kurs?"],
      decide: "Kreator proponuje ton na podstawie samego źródła: przyjazny poradnik wdrożeniowy dostaje przyjazny kurs. Ostatnie słowo należy do Ciebie.",
      alt: "Wywiad, pytanie 4 z 8: jaki ton ma mieć kurs, z notatką Why I ask o ciepłym głosie obsługi klienta. Brief pokazuje nowych konsultantów, poziom Beginner i 60 minut.",
    },
    brief: {
      label: "Wersje",
      title: "Wersje według poziomu lub roli, cena i wygląd",
      asks: ["Dla kogo jeszcze jest ten kurs? Jeden kurs dla wszystkich albo dodatkowe wersje dla początkujących, średnio zaawansowanych lub ekspertów, albo dla wskazanych ról.", "Kurs będzie bezpłatny czy płatny?", "Jaki wygląd ma mieć strona? Pytanie pada, gdy możesz zmieniać motyw."],
      decide: "Kurs zatwierdzony jako pierwszy jest bazą. Każdy dodatkowy poziom lub rola dostaje własną wersję z tych samych źródeł, z cytatem przy każdym bloku, a Ty przeglądasz ją jako listę zmian względem bazy.",
      alt: "Sześć z ośmiu odpowiedzi: programiści i operatorzy, poziom średni, 60 minut w lekcjach po 10 minut, ton profesjonalny, quiz po każdej lekcji, angielski. Brief po prawej jest wypełniony.",
    },
    outline: {
      label: "Konspekt",
      title: "Zatwierdź konspekt, zanim cokolwiek powstanie",
      asks: ["Zatwierdź konspekt i generuj albo poproś o zmiany z komentarzem."],
      decide: "Każdy cel nauczania pokazuje fragment źródła, który zacytuje. Nic nie trafia do akademii, dopóki nie zatwierdzisz wygenerowanego kursu.",
      alt: "Koniec proponowanego konspektu: cele nauczania z etykietami cytatów, np. retries-and-replay.md §3 Replay, pole komentarza oraz przyciski Approve outline & generate i Request changes.",
    },
    progress: {
      label: "Pisanie",
      title: "Patrz, jak powstaje, z kosztem na widoku",
      asks: [],
      decide: "Lekcje, sprawdzenie oparcia w źródłach, elementy interaktywne i quizy powstają po kolei. Każda lekcja pokazuje swoje cytaty i koszt na tle limitu wydatków kursu (w tym przebiegu 2,50 USD).",
      alt: "Postęp generowania: sześć lekcji, trzy gotowe i trzy w trakcie pisania, każda z liczbą cytatów i kosztem; wydano 0,21 z 2,50 USD.",
    },
    checks: {
      label: "Kontrole",
      title: "Przeczytaj ostrzeżenia, zanim akademia się zmieni",
      asks: ["Zastosować w akademii? Kurs powstaje jako nieopublikowany."],
      decide: "Widzisz, co powstanie, pozycja po pozycji, i każde stwierdzenie, którego sprawdzenie oparcia nie potwierdziło w pełni: w tym przebiegu trzy, każde z uzasadnieniem.",
      alt: "Utwórz kurs w akademii: tabela pozycji do utworzenia (1 kurs, 3 lekcje, 12 tematów, 18 pytań quizowych, 1 strona) i trzy ostrzeżenia o możliwie nieopartych stwierdzeniach, nad przyciskiem Apply to my academy.",
    },
    publish: {
      label: "Publikacja",
      title: "Potwierdź ostrzeżenia, potem opublikuj",
      asks: ["Opublikować? Najpierw trzeba potwierdzić ostrzeżenia."],
      decide: "Przed publikacją: 35 z 35 bloków cytuje 12 fragmentów źródła, a wszystkie 18 pytań quizowych przeszło sprawdzenie oparcia. Zastosowanie i publikacja to osobne kroki.",
      alt: "Krok publikacji po potwierdzeniu ostrzeżeń: przycisk pokazuje Published.",
    },
    lesson: {
      label: "Uczący się",
      title: "Co dostaje uczący się",
      asks: [],
      decide: "Lekcja w odtwarzaczu uczącego się, z cytowanymi fragmentami wypisanymi pod tekstem, i quiz po każdej lekcji w spisie kursu.",
      alt: "Lekcja w odtwarzaczu uczącego się: po lewej spis kursu w trzech modułach, po prawej tekst lekcji i jej trzy źródła pod spodem.",
    },
  },
  result: {
    title: "Kurs, który powstał w tym przebiegu",
    badge: "Jeden zmierzony przebieg · szkic do przeglądu autora",
    course: COURSE,
    courseNote: "Trzy moduły, sześć lekcji, tak jak na ekranach. Kurs powstał po angielsku, więc tytuły podajemy w oryginale.",
    modules: MODULES,
    quiz: "Quiz po każdej lekcji: łącznie 18 pytań",
    citesLabel: "Etykiety cytatów z kursu",
    cites: CITES,
    facts: [
      { value: "13", label: "przeczytanych fragmentów" },
      { value: "6", label: "lekcji" },
      { value: "18", label: "pytań quizowych" },
      { value: "35/35", label: "bloków cytuje źródło" },
      { value: "7 min 44 s", label: "generowania" },
      { value: "0,28 USD", label: "koszt modelu, 27 wywołań" },
    ],
    note: "Jeden przebieg na publicznym repozytorium dokumentacji fikcyjnego produktu. Tylko czas generowania; Twój przegląd dochodzi do tego.",
    cases: CASES("Pełne studium przypadku: repozytorium dokumentacji", "Pełne studium przypadku: strona Notion"),
  },
};

const ZH: WalkthroughCopy = {
  eyebrow: "生成器提问，你得到课程",
  title: "回答几个问题。审阅。发布。",
  lede: "真实的课程生成器，一屏一屏地展示，来自两次录制的运行。每个界面旁边：它提出的问题（用通俗的话说）以及由你决定的事。",
  stepsLabel: "演示步骤",
  asksLabel: "生成器会问",
  decideLabel: "由你决定",
  previous: "上一步",
  next: "下一步",
  status: "第 {n} 步，共 {total} 步",
  open: "打开全尺寸截图",
  from: { "docs-repo": "来自文档仓库那次运行的界面", "notion-page": "来自 Notion 页面那次运行的界面" },
  steps: {
    connect: {
      label: "来源",
      title: "指向你已有的资料",
      asks: ["资料在哪里？GitHub 仓库、Google Drive 或 Notion，或者上传的 PDF、Word 或 Markdown 文件。", "哪个仓库、哪个分支？公开仓库不需要访问令牌。"],
      decide: "生成器先读取来源，并把它拆成可以引用的短段落：这次运行有 13 段。上传的内容只被当作数据，绝不当作指令。",
      alt: "课程生成器，连接仓库：已选 GitHub，仓库 ulams-dev/demo-docs，默认分支，访问令牌一栏为空。",
    },
    audience: {
      label: "受众",
      title: "给谁、什么水平、多长时间",
      asks: ["这门课是给谁的？", "学员的起始水平是什么？", "整门课要多长，每节课多长？"],
      decide: "每个问题都会根据来源的内容说明为什么要问，并给出默认值。跳过一个问题就使用默认值。每个回答都写进课程简报，之后可以修改。",
      alt: "访谈第 3 问（共 8 问）：整门课要多长，选项为 30 分钟、1 小时和 2 小时，并附 Why I ask 说明。右侧课程简报显示两种角色和 Intermediate。",
    },
    tone: {
      label: "语气",
      title: "语气、测验与语言",
      asks: ["课程应该用什么语气？友好、专业、轻松或学术。", "要不要测验学员？每节课后测验、期末测试，或两者都要。", "课程用哪种语言撰写？"],
      decide: "生成器根据来源本身建议语气：友好的入职指南得到一门友好的课程。最终由你决定。",
      alt: "访谈第 4 问（共 8 问）：课程应该用什么语气，并附 Why I ask 说明客服的温暖语气。简报显示新客服人员、Beginner 和 60 分钟。",
    },
    brief: {
      label: "版本",
      title: "按水平或角色的版本、价格与外观",
      asks: ["这门课还面向谁？一门课给所有人，或为初级、中级、专家学员或指定角色增加版本。", "课程免费还是付费？", "网站应该是什么外观？在你有权更改主题时才会问。"],
      decide: "你最先批准的课程是基础版。每增加一个水平或角色，就会根据同样的来源生成一个独立版本，每个块都有出处，你以相对基础版的修改列表来审阅它。",
      alt: "八个问题中已回答六个：开发者和运维人员、中级、60 分钟每节 10 分钟、专业语气、每节课后测验、英语。右侧简报已填好。",
    },
    outline: {
      label: "大纲",
      title: "在写任何内容之前批准大纲",
      asks: ["批准大纲并生成，或附上意见要求修改。"],
      decide: "每个学习目标都显示它将引用的来源段落。在你批准生成的课程之前，什么都不会进入你的学院。",
      alt: "提议大纲的结尾：带引用标签的学习目标，例如 retries-and-replay.md §3 Replay，一个意见输入框，以及 Approve outline & generate 和 Request changes 按钮。",
    },
    progress: {
      label: "撰写",
      title: "看着它撰写，费用一目了然",
      asks: [],
      decide: "课时、依据检查、交互元素和测验依次进行。每节课显示它的引用数量和费用，以及课程的支出上限（这次运行为 2.50 美元）。",
      alt: "生成进度：六节课，三节已就绪、三节正在撰写，每节都显示引用数量和费用；已花费 0.21 美元，上限 2.50 美元。",
    },
    checks: {
      label: "检查",
      title: "在学院发生变化之前阅读警告",
      asks: ["应用到我的学院？课程会以未发布状态创建。"],
      decide: "你会逐项看到将要创建的内容，以及依据检查无法完全支持的每一条陈述：这次运行有三条，每条都附原因。",
      alt: "在你的学院中创建课程：将创建的项目表（1 门课程、3 个课时、12 个主题、18 道测验题、1 个页面）和三条可能缺乏依据的警告，位于 Apply to my academy 按钮上方。",
    },
    publish: {
      label: "发布",
      title: "确认警告，然后发布",
      asks: ["发布？必须先确认警告。"],
      decide: "发布前：35 个块全部引用了 12 段来源，18 道测验题全部通过依据检查。应用和发布是两个独立的步骤。",
      alt: "确认警告后的发布步骤：按钮显示 Published。",
    },
    lesson: {
      label: "学员",
      title: "学员得到什么",
      asks: [],
      decide: "学员播放器中的一节课，正文下方列出它引用的段落，课程目录中每节课后都有测验。",
      alt: "学员播放器中的一节课：左侧是分为三个模块的课程目录，右侧是课文，下方列出它的三个来源。",
    },
  },
  result: {
    title: "这次运行产出的课程",
    badge: "一次实测 · 供作者审阅的草稿",
    course: COURSE,
    courseNote: "三个模块、六节课，与界面所示一致。课程以英语生成，因此标题保留原文。",
    modules: MODULES,
    quiz: "每节课后一次测验：共 18 道题",
    citesLabel: "课程中的引用标签",
    cites: CITES,
    facts: [
      { value: "13", label: "段来源被读取" },
      { value: "6", label: "节课" },
      { value: "18", label: "道测验题" },
      { value: "35/35", label: "个块标注了出处" },
      { value: "7 分 44 秒", label: "生成时间" },
      { value: "0.28 美元", label: "模型成本，27 次调用" },
    ],
    note: "在一个关于虚构产品的公开文档仓库上的一次运行。仅为生成时间；你的审阅时间另计。",
    cases: CASES("完整案例：文档仓库", "完整案例：Notion 页面"),
  },
};

const COPY: Record<Locale, WalkthroughCopy> = { en: EN, pl: PL, zh: ZH };

export const walkthroughCopy = (locale: Locale): WalkthroughCopy => COPY[locale];

export interface WalkStep extends WalkStepCopy {
  key: WalkKey;
  id: string;
  run: "docs-repo" | "notion-page";
  /** The case study's own caption of this screenshot. */
  caption: string;
  image: { src: string; width: number; height: number };
}

/** The steps with their screenshot (geometry from the case studies) and its caption, in one language. */
export function walkthroughSteps(locale: Locale): WalkStep[] {
  const copy = COPY[locale];
  const cases = generationCopy(locale).cases;
  return WALK_FRAMES.map((frame) => {
    const shots = SHOTS[frame.run];
    const index = shots.frames.findIndex((shot) => shot.file === frame.file);
    const shot = shots.frames[index]!;
    return {
      ...copy.steps[frame.key],
      key: frame.key,
      id: `wt-${frame.key}`,
      run: frame.run,
      caption: cases[frame.run].captions[index]!,
      image: { src: `/course-generation/${shots.folder}/${shot.file}`, width: shot.width, height: shot.height },
    };
  });
}

/** "Step {n} of {total}" filled in. */
export const stepStatus = (template: string, n: number, total: number): string => template.replace("{n}", String(n)).replace("{total}", String(total));
