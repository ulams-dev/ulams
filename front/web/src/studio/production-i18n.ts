/**
 * Strings of the studio Production page in English, Polish and Simplified Chinese (glossary in src/i18n/GLOSSARY.md).
 * Placeholders are `{name}`. Counts are written as "label: number", so no plural form is needed.
 */
export { fill, INSIGHTS_LOCALES as PRODUCTION_LOCALES, pickInsightsLocale as pickProductionLocale, type InsightsLocale as ProductionLocale } from "./insights-i18n.ts";
import type { InsightsLocale as ProductionLocale } from "./insights-i18n.ts";

export interface ProductionStrings {
  htmlLang: string;
  pageTitle: string;
  heading: string;
  courseFallback: string;
  intro: string;
  ownFigures: string;
  languageLabel: string;
  loading: string;
  needsScript: string;
  error: string;
  retry: string;
  forbidden: string;
  notBuilt: string;
  computed: string;
  /** "{h} h {m} min", "{m} min {s} s", "{s} s": the three shapes of a duration. */
  durationHours: string;
  durationMinutes: string;
  durationSeconds: string;
  timeTitle: string;
  timeCaption: string;
  timeScroll: string;
  colStage: string;
  colRun: string;
  colSeconds: string;
  colQuality: string;
  quality: Record<string, string>;
  qualityNote: string;
  stages: Record<string, string>;
  runs: Record<string, string>;
  totalsTitle: string;
  generation: string;
  generationNote: string;
  aiEdit: string;
  interview: string;
  interviewNote: string;
  model: string;
  modelNote: string;
  authorTime: string;
  authorTimeNotRecorded: string;
  authorTimeValue: string;
  costTitle: string;
  costBuild: string;
  costBuildNote: string;
  costMaintenance: string;
  costMaintenanceNote: string;
  calls: string;
  callsFailed: string;
  costNote: string;
  countsTitle: string;
  lessons: string;
  questions: string;
  blocks: string;
  citedBlocks: string;
  learnerMinutes: string;
  elementsTitle: string;
  elementsIntro: string;
  elementsCaption: string;
  elementsScroll: string;
  colClass: string;
  colCount: string;
  colShare: string;
  classAccepted: string;
  classRevised: string;
  classEdited: string;
  classUnknown: string;
  classAuthor: string;
  classUnknownNote: string;
  barLabel: string;
  noElements: string;
  exportTitle: string;
  exportCsv: string;
  exportJson: string;
  exported: string;
  methodVersion: string;
}

const en: ProductionStrings = {
  htmlLang: "en",
  pageTitle: "Production",
  heading: "How this course was produced",
  courseFallback: "Course {id}",
  intro: "How long the Course Builder took for each stage of this course, what the model calls cost, and how much of what the AI wrote was kept as it was.",
  ownFigures: "These are this academy's own measurements of this course. They are not compared with any other course or any industry figure.",
  languageLabel: "Language",
  loading: "Loading the figures…",
  needsScript: "This page needs JavaScript to show the figures.",
  error: "The figures could not be loaded.",
  retry: "Try again",
  forbidden: "This course belongs to another author. Ask them, or an administrator, to open its production figures.",
  notBuilt: "This course was not made with the Course Builder, so there is nothing recorded about how it was produced.",
  computed: "Computed {date}",
  durationHours: "{h} h {m} min",
  durationMinutes: "{m} min {s} s",
  durationSeconds: "{s} s",
  timeTitle: "Time per stage",
  timeCaption: "Time the Course Builder took for each stage, in the order they ran",
  timeScroll: "Stage times (scrolls sideways on small screens)",
  colStage: "Stage",
  colRun: "Part of",
  colSeconds: "Time",
  colQuality: "How it was measured",
  quality: { exact: "Measured start to finish", coarse: "Whole run only (older build)", incomplete: "Did not finish normally" },
  qualityNote: "Times are wall-clock: the time you waited, not the sum of the model calls, which overlap.",
  stages: { outline: "Outline", lessons: "Lessons", grounding: "Grounding check", interactions: "Interactive elements", quizzes: "Quizzes", metadata: "Metadata and landing page", apply: "Apply to the academy", ingest: "Reading the sources", interview: "Interview", patch: "Edit by chat", sync: "Update from sources", assemble: "Assemble the draft" },
  runs: { outline: "Outline", generate: "Writing", apply: "Apply", ingest: "Sources", interview: "Interview", patch: "Edit by chat", sync: "Update", action: "Action" },
  totalsTitle: "Totals",
  generation: "Generation time",
  generationNote: "Outline, writing, apply and updates: what the machine did while you waited.",
  aiEdit: "Time for AI edits by chat",
  interview: "Interview time",
  interviewNote: "You answering questions. It counts as your time, not machine time.",
  model: "Model time",
  modelNote: "Summed time of the model calls, for comparison. Longer than the generation time because lessons are written in parallel.",
  authorTime: "Author time",
  authorTimeNotRecorded: "Not recorded. Review time is only recorded when your administrator turns it on.",
  authorTimeValue: "{time} of active screen time",
  costTitle: "Cost",
  costBuild: "Build cost",
  costBuildNote: "Model calls that made and changed this course, including variants.",
  costMaintenance: "Maintenance cost",
  costMaintenanceNote: "Model calls that kept it up to date: proposals from sources and from learner insights.",
  calls: "Model calls",
  callsFailed: "Of which failed",
  costNote: "Failed calls are included because they were paid for. Learner tutoring and personal help are not authoring and are not counted.",
  countsTitle: "What was produced",
  lessons: "Lessons",
  questions: "Quiz questions",
  blocks: "Content blocks",
  citedBlocks: "Blocks that cite a source passage",
  learnerMinutes: "Learner minutes (declared)",
  elementsTitle: "What the AI wrote and what happened to it",
  elementsIntro: "Modules, lessons, blocks and quiz questions the AI wrote, by what became of them.",
  elementsCaption: "Elements the AI wrote, by what happened to them",
  elementsScroll: "Element classes (scrolls sideways on small screens)",
  colClass: "What happened",
  colCount: "Elements",
  colShare: "Share",
  classAccepted: "Kept as the AI wrote it",
  classRevised: "Revised by the AI on your request",
  classEdited: "Edited by an author",
  classUnknown: "Unknown (written before this was recorded)",
  classAuthor: "Written by an author (not counted as AI)",
  classUnknownNote: "Elements from before provenance was recorded are not guessed.",
  barLabel: "Share of AI elements: {parts}",
  noElements: "No AI elements to classify yet.",
  exportTitle: "Export",
  exportCsv: "Download CSV",
  exportJson: "Download JSON",
  exported: "Downloaded {name}",
  methodVersion: "Definitions version {n}",
};

const pl: ProductionStrings = {
  htmlLang: "pl",
  pageTitle: "Produkcja",
  heading: "Jak powstał ten kurs",
  courseFallback: "Kurs {id}",
  intro: "Ile czasu zajął kreator kursów na każdym etapie tego kursu, ile kosztowały wywołania modelu i jaka część tego, co napisało AI, została bez zmian.",
  ownFigures: "To własne pomiary tej akademii dotyczące tego kursu. Nie są porównywane z innym kursem ani z żadnymi danymi branżowymi.",
  languageLabel: "Język",
  loading: "Wczytuję dane…",
  needsScript: "Ta strona wymaga JavaScriptu, aby pokazać dane.",
  error: "Nie udało się wczytać danych.",
  retry: "Spróbuj ponownie",
  forbidden: "Ten kurs należy do innego autora. Poproś jego lub administratora o otwarcie danych o produkcji.",
  notBuilt: "Ten kurs nie powstał w kreatorze kursów, więc nic nie zapisano o tym, jak go wytworzono.",
  computed: "Obliczono {date}",
  durationHours: "{h} godz. {m} min",
  durationMinutes: "{m} min {s} s",
  durationSeconds: "{s} s",
  timeTitle: "Czas na etapach",
  timeCaption: "Czas, jaki kreator kursów poświęcił na każdy etap, w kolejności wykonania",
  timeScroll: "Czasy etapów (na małych ekranach przewijane w bok)",
  colStage: "Etap",
  colRun: "Część",
  colSeconds: "Czas",
  colQuality: "Jak zmierzono",
  quality: { exact: "Zmierzono od początku do końca", coarse: "Tylko całe uruchomienie (starsza wersja)", incomplete: "Nie zakończył się normalnie" },
  qualityNote: "To czas zegarowy: tyle, ile czekasz, a nie suma wywołań modelu, które nakładają się na siebie.",
  stages: { outline: "Konspekt", lessons: "Lekcje", grounding: "Sprawdzenie źródeł", interactions: "Elementy interaktywne", quizzes: "Quizy", metadata: "Metadane i strona kursu", apply: "Zastosowanie w akademii", ingest: "Czytanie źródeł", interview: "Wywiad", patch: "Edycja czatem", sync: "Aktualizacja ze źródeł", assemble: "Składanie wersji roboczej" },
  runs: { outline: "Konspekt", generate: "Pisanie", apply: "Zastosowanie", ingest: "Źródła", interview: "Wywiad", patch: "Edycja czatem", sync: "Aktualizacja", action: "Akcja" },
  totalsTitle: "Sumy",
  generation: "Czas generowania",
  generationNote: "Konspekt, pisanie, zastosowanie i aktualizacje: to, co robiła maszyna, gdy czekasz.",
  aiEdit: "Czas edycji AI czatem",
  interview: "Czas wywiadu",
  interviewNote: "Twoje odpowiedzi na pytania. Liczy się jako Twój czas, a nie czas maszyny.",
  model: "Czas modelu",
  modelNote: "Suma czasu wywołań modelu, dla porównania. Dłuższa niż czas generowania, bo lekcje powstają równolegle.",
  authorTime: "Czas autora",
  authorTimeNotRecorded: "Nie zapisano. Czas przeglądu jest zapisywany tylko wtedy, gdy administrator to włączy.",
  authorTimeValue: "{time} aktywnej pracy przy ekranie",
  costTitle: "Koszt",
  costBuild: "Koszt budowy",
  costBuildNote: "Wywołania modelu, które stworzyły i zmieniały ten kurs, także jego warianty.",
  costMaintenance: "Koszt utrzymania",
  costMaintenanceNote: "Wywołania modelu, które utrzymywały kurs na bieżąco: propozycje ze źródeł i z analizy kursantów.",
  calls: "Wywołania modelu",
  callsFailed: "W tym nieudane",
  costNote: "Nieudane wywołania są wliczone, bo zostały opłacone. Korepetycje i pomoc dla kursantów nie są tworzeniem kursu i nie są liczone.",
  countsTitle: "Co powstało",
  lessons: "Lekcje",
  questions: "Pytania w quizach",
  blocks: "Bloki treści",
  citedBlocks: "Bloki ze wskazanym źródłem",
  learnerMinutes: "Minuty kursanta (deklarowane)",
  elementsTitle: "Co napisało AI i co się z tym stało",
  elementsIntro: "Moduły, lekcje, bloki i pytania quizowe napisane przez AI, według tego, co się z nimi stało.",
  elementsCaption: "Elementy napisane przez AI według tego, co się z nimi stało",
  elementsScroll: "Klasy elementów (na małych ekranach przewijane w bok)",
  colClass: "Co się stało",
  colCount: "Elementy",
  colShare: "Udział",
  classAccepted: "Zostały tak, jak napisało AI",
  classRevised: "Poprawione przez AI na Twoją prośbę",
  classEdited: "Zmienione przez autora",
  classUnknown: "Nieznane (napisane, zanim to zapisywano)",
  classAuthor: "Napisane przez autora (nie liczone jako AI)",
  classUnknownNote: "Elementów sprzed zapisywania pochodzenia nie zgadujemy.",
  barLabel: "Udział elementów AI: {parts}",
  noElements: "Nie ma jeszcze elementów AI do sklasyfikowania.",
  exportTitle: "Eksport",
  exportCsv: "Pobierz CSV",
  exportJson: "Pobierz JSON",
  exported: "Pobrano {name}",
  methodVersion: "Wersja definicji {n}",
};

const zh: ProductionStrings = {
  htmlLang: "zh-Hans",
  pageTitle: "制作情况",
  heading: "这门课程是如何制作的",
  courseFallback: "课程 {id}",
  intro: "课程构建器在每个阶段花了多长时间，模型调用花了多少钱，以及 AI 写的内容有多少被原样保留。",
  ownFigures: "这些是本学院对这门课程自己的测量数据，不与其他课程或任何行业数据比较。",
  languageLabel: "语言",
  loading: "正在加载数据…",
  needsScript: "此页面需要 JavaScript 才能显示数据。",
  error: "无法加载数据。",
  retry: "重试",
  forbidden: "这门课程属于其他作者。请联系该作者或管理员打开其制作数据。",
  notBuilt: "这门课程不是用课程构建器制作的，因此没有关于其制作过程的记录。",
  computed: "计算于 {date}",
  durationHours: "{h} 小时 {m} 分钟",
  durationMinutes: "{m} 分 {s} 秒",
  durationSeconds: "{s} 秒",
  timeTitle: "各阶段耗时",
  timeCaption: "课程构建器在每个阶段花费的时间，按运行顺序排列",
  timeScroll: "各阶段耗时（小屏幕上可横向滚动）",
  colStage: "阶段",
  colRun: "所属环节",
  colSeconds: "耗时",
  colQuality: "测量方式",
  quality: { exact: "从开始到结束完整测量", coarse: "仅整次运行（较早的构建）", incomplete: "未正常结束" },
  qualityNote: "耗时为实际经过的时间，即你等待的时间，而不是相互重叠的模型调用之和。",
  stages: { outline: "大纲", lessons: "课时", grounding: "出处核对", interactions: "互动元素", quizzes: "测验", metadata: "元数据与课程页面", apply: "应用到学院", ingest: "读取来源", interview: "访谈", patch: "聊天修改", sync: "按来源更新", assemble: "组装草稿" },
  runs: { outline: "大纲", generate: "撰写", apply: "应用", ingest: "来源", interview: "访谈", patch: "聊天修改", sync: "更新", action: "操作" },
  totalsTitle: "合计",
  generation: "生成时间",
  generationNote: "大纲、撰写、应用和更新：你等待期间机器所做的工作。",
  aiEdit: "AI 聊天修改时间",
  interview: "访谈时间",
  interviewNote: "你回答问题的时间。它算作你的时间，而不是机器时间。",
  model: "模型时间",
  modelNote: "模型调用的累计时间，仅供对比。由于课时并行撰写，它比生成时间长。",
  authorTime: "作者时间",
  authorTimeNotRecorded: "未记录。只有管理员开启后才会记录审阅时间。",
  authorTimeValue: "{time} 的活跃屏幕时间",
  costTitle: "成本",
  costBuild: "构建成本",
  costBuildNote: "创建和修改这门课程的模型调用，包括其变体。",
  costMaintenance: "维护成本",
  costMaintenanceNote: "让课程保持最新的模型调用：来自来源和学员洞察的建议。",
  calls: "模型调用次数",
  callsFailed: "其中失败",
  costNote: "失败的调用也计入，因为已经付费。学员辅导和个人帮助不属于课程制作，不计入。",
  countsTitle: "产出内容",
  lessons: "课时",
  questions: "测验题目",
  blocks: "内容块",
  citedBlocks: "标明来源段落的内容块",
  learnerMinutes: "学员学习分钟数（声明值）",
  elementsTitle: "AI 写了什么，之后发生了什么",
  elementsIntro: "AI 撰写的模块、课时、内容块和测验题目，按其后续情况分类。",
  elementsCaption: "AI 撰写的元素，按后续情况分类",
  elementsScroll: "元素分类（小屏幕上可横向滚动）",
  colClass: "后续情况",
  colCount: "元素数",
  colShare: "占比",
  classAccepted: "保持 AI 所写原样",
  classRevised: "应你的要求由 AI 修改",
  classEdited: "由作者编辑",
  classUnknown: "未知（记录功能出现之前所写）",
  classAuthor: "由作者撰写（不计为 AI）",
  classUnknownNote: "对于记录出处之前的元素，我们不做猜测。",
  barLabel: "AI 元素占比：{parts}",
  noElements: "暂无可分类的 AI 元素。",
  exportTitle: "导出",
  exportCsv: "下载 CSV",
  exportJson: "下载 JSON",
  exported: "已下载 {name}",
  methodVersion: "定义版本 {n}",
};

export const PRODUCTION_STRINGS: Record<ProductionLocale, ProductionStrings> = { en, pl, zh };
