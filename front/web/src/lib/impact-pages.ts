/**
 * The two economic pages of the public product site: what it costs to create content, and reporting.
 * Shared by the English, Polish and Chinese routes. Only shipped features are shown as available; the
 * rest carries the catalogue's "coming" status. No ULAMS savings or speed-up ratios are claimed; the cost page shows one measured run, labelled as such.
 */
import type { UiNode } from "@ulams/ui/render-core";
import { comparisonDocFor, withLanguages } from "./docs.ts";
import { LOCALE_META, languageLinks, pageAlternateLinks, pageCanonicalUrl, publicOrigin, type Locale, type PublicPage } from "../i18n/locales.ts";

export const IMPACT_PAGES = ["content-cost", "reporting"] as const;
export type ImpactPage = (typeof IMPACT_PAGES)[number];

export const CHAPMAN_SOURCE = "https://elearningindustry.com/development-costs-for-your-online-learning-start-estimating";
export const BRANDON_HALL_SOURCE = "https://trainingmag.com/?p=13564";
const DOCS = "https://ulams-dev.github.io/ulams/";

interface Item { icon: string; title: string; text: string }
export interface ImpactCopy {
  title: string;
  description: string;
  eyebrow: string;
  heading: string;
  subtitle: string;
  body: string;
  primary: string;
  secondary: string;
  problemTitle: string;
  /** Markdown; the cost page links its source here. */
  problem: string;
  /** Optional Markdown section after the benchmark (the cost page's measured run). */
  run?: string;
  todayTitle: string;
  todayIntro: string;
  today: Item[];
  comingTitle: string;
  comingIntro: string;
  coming: Item[];
  boundaryTitle: string;
  boundary: string;
  ctaTitle: string;
  ctaText: string;
  cta: string;
  docs: string;
  /** Link of the primary call to action at the end. */
  ctaHref: string;
}

interface Fact { label: string; value: string; note?: string }
/** At-a-glance figures under the hero. The cost page's figures are the measured run, labelled as such. */
const FACTS: Record<ImpactPage, Record<Locale, Fact[]>> = {
  "content-cost": {
    en: [
      { label: "One measured run", value: "7.5 min", note: "of generation" },
      { label: "Result", value: "6 + 18", note: "lessons + quiz questions" },
      { label: "Model cost", value: "USD 0.28", note: "27 model calls" },
      { label: "Hand-built benchmark", value: "49–716 h", note: "per finished hour" },
    ],
    pl: [
      { label: "Jeden zmierzony przebieg", value: "7,5 min", note: "generowania" },
      { label: "Wynik", value: "6 + 18", note: "lekcji + pytań quizowych" },
      { label: "Koszt modelu", value: "0,28 USD", note: "27 wywołań modelu" },
      { label: "Punkt odniesienia, ręcznie", value: "49–716 godz.", note: "na godzinę kursu" },
    ],
    zh: [
      { label: "一次实测", value: "7.5 分钟", note: "生成时间" },
      { label: "结果", value: "6 + 18", note: "课时 + 测验题" },
      { label: "模型成本", value: "0.28 美元", note: "27 次模型调用" },
      { label: "人工制作基准", value: "49–716 小时", note: "每 1 小时成品" },
    ],
  },
  reporting: {
    en: [
      { label: "Who is stuck", value: "At-risk learners", note: "per course" },
      { label: "Who finished", value: "Progress and completion" },
      { label: "Compliance", value: "Status and audit trail" },
      { label: "Export", value: "CSV · JSON lines" },
    ],
    pl: [
      { label: "Kto utknął", value: "Zagrożeni uczestnicy", note: "w każdym kursie" },
      { label: "Kto skończył", value: "Postępy i ukończenia" },
      { label: "Zgodność", value: "Status i ślad audytowy" },
      { label: "Eksport", value: "CSV · JSON lines" },
    ],
    zh: [
      { label: "谁卡住了", value: "有掉队风险的学员", note: "按课程" },
      { label: "谁学完了", value: "进度与完成率" },
      { label: "合规", value: "状态与审计记录" },
      { label: "导出", value: "CSV · JSON lines" },
    ],
  },
};

const COST_EN: ImpactCopy = {
  title: "ulams — The cost of creating e-learning content",
  description: "Creating content is the main cost of e-learning. What ULAMS already does to cut it, and what is still coming.",
  eyebrow: "Content cost",
  heading: "Creating content is the expensive part",
  subtitle: "ULAMS attacks the hours before the first learner logs in.",
  body: "Most of an e-learning budget goes into writing, reviewing and updating courses, not into running them. ULAMS builds courses from your own sources, cites every element and keeps them in step with those sources, with the author approving every change.",
  primary: "What ULAMS does today",
  secondary: "Explore the demos",
  problemTitle: "The benchmark",
  problem: "Chapman Alliance development ratios run from **49 to 716 hours of work for every 1 hour of finished e-learning**, depending on interactivity. Source: [Chapman Alliance figures, as reported by eLearning Industry](" + CHAPMAN_SOURCE + "). Industry observers also note that keeping content up to date adds effort after launch. The study dates from 2010; treat it as an order of magnitude, not a quote for your project.",
  run: `## One measured run

This is a single run we measured, not an average and not a promise. From a public documentation repository (13 passages), ULAMS generated a 6-lesson course with 18 quiz questions. Every one of its 35 content blocks cites the source.

- Generation time: about 7.5 minutes in total. Outline 24 s, lessons, quizzes and checks 6 min 57 s, apply 23 s.
- Model cost: USD 0.28 across 27 model calls.
- The result is a draft. The author reviews and approves it before publishing, and that time is not counted above.

We set no hours-saved figure and no "times faster" ratio against the benchmark above: the two measure different things.`,
  todayTitle: "What ULAMS does today",
  todayIntro: "All of this is in the product now.",
  today: [
    { icon: "sparkle", title: "AI course builder", text: "Upload a source, answer a short interview, approve the outline. The builder drafts the lessons from it." },
    { icon: "quote", title: "Every element cited", text: "Each generated block points to the source fragment behind it. Unsupported claims are flagged before anyone can publish." },
    { icon: "sync", title: "Living Course", text: "When a source changes, ULAMS detects it at fragment level and proposes the matching updates instead of a rewrite." },
    { icon: "eye", title: "The author approves every AI change", text: "Every AI edit is a reviewable diff: accept all, accept per element, or reject. Nothing reaches learners unapproved." },
    { icon: "chart", title: "Cost logged per call", text: "Every model call records model, tokens and cost. The running cost of a course is visible in the Studio, with hard limits." },
  ],
  comingTitle: "What is coming",
  comingIntro: "Planned, not shipped. Labelled as such.",
  coming: [
    { icon: "layers", title: "One source, one course per level and role", text: "Variants of one course for beginners, experts or a given role, each reviewed as a diff against the base. First part in development." },
    { icon: "clock", title: "Course versions with a visual diff", text: "Compare any two releases of a course: structure, text, citations and quiz changes side by side." },
    { icon: "chart", title: "Time and cost to create a course", text: "A measured metric of authoring time and AI cost per course, so the saving can be shown with your own data." },
  ],
  boundaryTitle: "No invented numbers",
  boundary: "We publish what we measured and nothing else. The run above is one run, with no averages. There is no hours-saved claim, no \"times faster\" ratio and no testimonials yet. The metric below is how we plan to measure it with your data.",
  ctaTitle: "See how a course is built from a source",
  ctaText: "Read how the builder, the citations and the approval step work.",
  cta: "Course Builder guide",
  docs: "Documentation",
  ctaHref: DOCS + "creators/course-builder/",
};

const COST_PL: ImpactCopy = {
  title: "ulams — Koszt tworzenia treści e-learningowych",
  description: "Tworzenie treści to główny koszt e-learningu. Co ULAMS już robi, żeby go obniżyć, i co jest w drodze.",
  eyebrow: "Koszt treści",
  heading: "Tworzenie treści to najdroższa część",
  subtitle: "ULAMS skraca godziny pracy, zanim zaloguje się pierwszy uczestnik.",
  body: "Większość budżetu e-learningu idzie na pisanie, recenzowanie i aktualizację kursów, a nie na ich prowadzenie. ULAMS buduje kursy z Twoich źródeł, cytuje każdy element i utrzymuje je w zgodzie ze źródłami, a autor zatwierdza każdą zmianę.",
  primary: "Co ULAMS robi dziś",
  secondary: "Zobacz demo",
  problemTitle: "Punkt odniesienia",
  problem: "Wskaźniki Chapman Alliance wahają się od **49 do 716 godzin pracy na 1 godzinę gotowego e-learningu**, zależnie od interaktywności. Źródło: [dane Chapman Alliance w opisie eLearning Industry](" + CHAPMAN_SOURCE + "). Obserwatorzy branży zauważają też, że utrzymywanie treści w aktualności wymaga dodatkowej pracy po premierze kursu. Badanie pochodzi z 2010 roku; traktuj je jako rząd wielkości, a nie wycenę Twojego projektu.",
  run: `## Jeden zmierzony przebieg

To pojedynczy zmierzony przebieg, a nie średnia ani obietnica. Z publicznego repozytorium dokumentacji (13 fragmentów) ULAMS wygenerował kurs z 6 lekcjami i 18 pytaniami quizowymi. Każdy z 35 bloków treści cytuje źródło.

- Czas generowania: łącznie około 7,5 minuty. Konspekt 24 s, lekcje, quizy i kontrole 6 min 57 s, zastosowanie 23 s.
- Koszt modelu: 0,28 USD przy 27 wywołaniach modelu.
- Wynik to szkic. Autor przegląda go i zatwierdza przed publikacją, a tego czasu powyżej nie liczymy.

Nie podajemy zaoszczędzonych godzin ani przelicznika „razy szybciej” względem powyższego punktu odniesienia: to mierzy co innego.`,
  todayTitle: "Co ULAMS robi dziś",
  todayIntro: "Wszystko to jest już w produkcie.",
  today: [
    { icon: "sparkle", title: "Kreator kursów AI", text: "Wgrywasz źródło, odpowiadasz na krótki wywiad, zatwierdzasz konspekt. Kreator pisze z niego lekcje." },
    { icon: "quote", title: "Każdy element z cytatem", text: "Każdy wygenerowany blok wskazuje fragment źródła, na którym się opiera. Twierdzenia bez oparcia są oznaczane przed publikacją." },
    { icon: "sync", title: "Living Course", text: "Gdy źródło się zmienia, ULAMS wykrywa to na poziomie fragmentów i proponuje pasujące poprawki zamiast przepisywania kursu." },
    { icon: "eye", title: "Autor zatwierdza każdą zmianę AI", text: "Każda zmiana AI to diff do przejrzenia: przyjmij wszystko, przyjmij element po elemencie albo odrzuć. Nic nie trafia do uczących się bez zatwierdzenia." },
    { icon: "chart", title: "Koszt zapisany przy każdym wywołaniu", text: "Każde wywołanie modelu zapisuje model, tokeny i koszt. Bieżący koszt kursu widać w Studio, z twardymi limitami." },
  ],
  comingTitle: "Co jest w drodze",
  comingIntro: "Zaplanowane, jeszcze niedostępne. Oznaczone uczciwie.",
  coming: [
    { icon: "layers", title: "Jedno źródło, jeden kurs na poziom i rolę", text: "Warianty jednego kursu dla początkujących, ekspertów lub danej roli, każdy oceniany jako diff względem bazy. Pierwsza część w budowie." },
    { icon: "clock", title: "Wersje kursu z wizualnym diffem", text: "Porównaj dowolne dwa wydania kursu: strukturę, tekst, cytaty i zmiany w quizach obok siebie." },
    { icon: "chart", title: "Czas i koszt stworzenia kursu", text: "Mierzony wskaźnik czasu autora i kosztu AI na kurs, żeby oszczędność pokazać na Twoich danych." },
  ],
  boundaryTitle: "Bez wymyślonych liczb",
  boundary: "Publikujemy to, co zmierzyliśmy, i nic ponad to. Powyższy przebieg to jeden przebieg, bez średnich. Nie ma deklaracji zaoszczędzonych godzin, przelicznika „razy szybciej” ani opinii klientów. Wskaźnik poniżej to sposób, w jaki zmierzymy to na Twoich danych.",
  ctaTitle: "Zobacz, jak kurs powstaje ze źródła",
  ctaText: "Przeczytaj, jak działają kreator, cytaty i zatwierdzanie.",
  cta: "Przewodnik po kreatorze kursów",
  docs: "Dokumentacja",
  ctaHref: DOCS + "creators/course-builder/",
};

const COST_ZH: ImpactCopy = {
  title: "ulams — 在线课程内容的制作成本",
  description: "内容制作是在线学习的主要成本。ULAMS 现在已经能做什么来降低它,以及哪些功能即将推出。",
  eyebrow: "内容成本",
  heading: "内容制作是最贵的部分",
  subtitle: "ULAMS 缩短第一位学员登录之前的工作时间。",
  body: "在线学习的大部分预算花在撰写、审阅和更新课程上,而不是运行课程。ULAMS 依据你自己的资料生成课程,为每个元素标注出处,并让课程与资料保持同步,每次改动都由作者批准。",
  primary: "ULAMS 现在能做什么",
  secondary: "查看演示",
  problemTitle: "行业基准",
  problem: "Chapman Alliance 的开发比例为每 1 小时成品在线课程需要 **49 至 716 小时**工作量,取决于交互程度。来源:[eLearning Industry 转述的 Chapman Alliance 数据](" + CHAPMAN_SOURCE + ")。业内人士还指出,课程上线后保持内容更新需要额外投入。该研究发表于 2010 年,请当作数量级参考,而不是你项目的报价。",
  run: `## 一次实测

这是我们实测的一次运行,不是平均值,也不是承诺。ULAMS 从一个公开文档仓库(13 个片段)生成了含 6 个课时和 18 道测验题的课程。35 个内容块中的每一个都标注了资料出处。

- 生成时间:总计约 7.5 分钟。大纲 24 秒,课时、测验与检查 6 分 57 秒,应用 23 秒。
- 模型成本:27 次模型调用,共 0.28 美元。
- 结果是草稿。发布前由作者审阅并批准,这段时间不计入上述数字。

我们不提供节省工时的数字,也不与上面的基准给出“快多少倍”的比例:两者度量的对象不同。`,
  todayTitle: "ULAMS 现在能做什么",
  todayIntro: "以下功能均已在产品中提供。",
  today: [
    { icon: "sparkle", title: "AI 课程生成器", text: "上传资料,回答简短访谈,批准大纲,生成器据此撰写课时。" },
    { icon: "quote", title: "每个元素都有出处", text: "每个生成的内容块都指向其依据的资料片段。没有依据的说法会在发布前被标出。" },
    { icon: "sync", title: "Living Course", text: "资料变化时,ULAMS 在片段级别检测并提出相应的更新建议,而不是重写整门课程。" },
    { icon: "eye", title: "每项 AI 改动都由作者批准", text: "每次 AI 编辑都是可审阅的差异:全部接受、逐项接受或拒绝。未经批准的内容不会到达学员。" },
    { icon: "chart", title: "每次调用都记录成本", text: "每次模型调用都记录模型、令牌和成本。课程的累计成本显示在 Studio 中,并有硬性上限。" },
  ],
  comingTitle: "即将推出",
  comingIntro: "已规划,尚未发布,并如实标注。",
  coming: [
    { icon: "layers", title: "一份资料,按级别和角色生成多门课程", text: "同一门课程的初学者、专家或特定角色版本,每个版本都以与基础课程的差异形式审阅。第一部分开发中。" },
    { icon: "clock", title: "带可视化差异的课程版本", text: "比较课程的任意两个版本:结构、文本、出处和测验改动并排显示。" },
    { icon: "chart", title: "制作课程的时间与成本", text: "按课程度量作者耗时和 AI 成本,让节省的效果用你自己的数据说话。" },
  ],
  boundaryTitle: "不编造数字",
  boundary: "我们只发布实测到的数据,仅此而已。上面是一次运行,没有平均值。没有节省工时的说法,没有“快多少倍”的比例,也暂无客户评价。下面的指标是我们计划用你的数据来衡量的方式。",
  ctaTitle: "看看课程如何从资料生成",
  ctaText: "了解生成器、出处标注和批准步骤如何运作。",
  cta: "课程生成器指南",
  docs: "文档",
  ctaHref: DOCS + "creators/course-builder/",
};

const REPORTING_EN: ImpactCopy = {
  title: "ulams — LMS reporting and analytics",
  description: "Weak reporting is a top reason to switch LMS. The analytics, exports and compliance reports ULAMS ships, and what is coming.",
  eyebrow: "Reporting",
  heading: "Reporting you can act on and take with you",
  subtitle: "Dashboards for authors, reports for compliance, and exports your own tools can read.",
  body: "Teams outgrow an LMS when they cannot get answers out of it. ULAMS gives authors per-course analytics, gives compliance teams reports with an audit export, and keeps the data in formats and systems you control.",
  primary: "What ULAMS has today",
  secondary: "Explore the demos",
  problemTitle: "Why it matters",
  problem: "In Brandon Hall Group's 2013 survey, reported by Training magazine, **45.5% of 457 respondents** gave their LMS high marks for value, **47.7%** were looking to move to a new provider, and poor reporting was among the top reasons. In its 2012 survey, reporting ranked number one. Source: [Training magazine on the Brandon Hall Group LMS surveys](" + BRANDON_HALL_SOURCE + ").",
  todayTitle: "What ULAMS has today",
  todayIntro: "Shipped and documented.",
  today: [
    { icon: "chart", title: "Per-course analytics dashboard", text: "Progress, completion, quiz performance, learners who need support and the sections that confuse people. Groups under 10 learners are hidden." },
    { icon: "shield", title: "Compliance reports and audit export", text: "Mandatory-training status and a tamper-evident audit trail of who changed what in a course, exportable." },
    { icon: "pager", title: "CSV and JSON-lines export", text: "Download any report as CSV or as JSON lines for your own tools. Spreadsheet formula injection is neutralised." },
    { icon: "mail", title: "Scheduled e-mail reports", text: "A report e-mailed daily, weekly or monthly to users of your academy. Scheduling is set up through the API." },
    { icon: "eye", title: "Learner insights", text: "Where learners struggle, tied to the exact lesson element, with an AI-proposed improvement you review as a diff." },
    { icon: "server", title: "xAPI data in your own deployment", text: "A built-in xAPI learning record store runs in your own deployment, so the statements stay with you." },
  ],
  comingTitle: "What is coming",
  comingIntro: "Planned, not shipped. Labelled as such.",
  coming: [
    { icon: "compass", title: "Report builder with saved filters", text: "Build your own report from the available data and save its filters for next time." },
    { icon: "server", title: "BI connector", text: "A direct connector for BI tools. Today, use the export or the report API." },
    { icon: "clock", title: "Authoring time and cost metric", text: "How long a course took to create and what its AI cost, measured per course." },
  ],
  boundaryTitle: "Partly there",
  boundary: "The export and the report API work today. There is no BI connector beyond them yet, no link to your business KPIs, and the admin dashboard does not show struggle and recovery numbers yet.",
  ctaTitle: "Look at the data your academy produces",
  ctaText: "Read how analytics, compliance reports and exports work.",
  cta: "Analytics guide",
  docs: "Documentation",
  ctaHref: DOCS + "creators/course-insights/",
};

const REPORTING_PL: ImpactCopy = {
  title: "ulams — Raportowanie i analityka w LMS",
  description: "Słabe raportowanie to jeden z głównych powodów zmiany LMS. Analityka, eksporty i raporty zgodności w ULAMS oraz to, co jest w drodze.",
  eyebrow: "Raportowanie",
  heading: "Raporty, z których można korzystać i które można zabrać",
  subtitle: "Panele dla autorów, raporty zgodności i eksporty czytelne dla Twoich narzędzi.",
  body: "Zespoły wyrastają z LMS, gdy nie mogą z niego wyciągnąć odpowiedzi. ULAMS daje autorom analitykę każdego kursu, działom zgodności raporty z eksportem audytu, a dane trzyma w formatach i systemach, które kontrolujesz.",
  primary: "Co ULAMS ma dziś",
  secondary: "Zobacz demo",
  problemTitle: "Dlaczego to ważne",
  problem: "W badaniu Brandon Hall Group z 2013 roku, opisanym przez magazyn Training, **45,5% z 457 respondentów** wysoko oceniło wartość swojego LMS, **47,7%** szukało nowego dostawcy, a słabe raportowanie należało do głównych powodów. W badaniu z 2012 roku raportowanie zajęło pierwsze miejsce. Źródło: [magazyn Training o badaniach LMS Brandon Hall Group](" + BRANDON_HALL_SOURCE + ").",
  todayTitle: "Co ULAMS ma dziś",
  todayIntro: "Dostępne i opisane w dokumentacji.",
  today: [
    { icon: "chart", title: "Panel analityczny każdego kursu", text: "Postępy, ukończenia, wyniki quizów, osoby potrzebujące wsparcia i fragmenty, które mylą. Grupy poniżej 10 osób są ukryte." },
    { icon: "shield", title: "Raporty zgodności i eksport audytu", text: "Status obowiązkowych szkoleń i odporny na manipulacje ślad audytowy, kto co zmienił w kursie, z eksportem." },
    { icon: "pager", title: "Eksport CSV i JSON lines", text: "Pobierz dowolny raport jako CSV lub JSON lines do własnych narzędzi. Wstrzyknięcie formuł do arkusza jest neutralizowane." },
    { icon: "mail", title: "Raporty e-mailem według harmonogramu", text: "Raport wysyłany codziennie, co tydzień lub co miesiąc do użytkowników akademii. Harmonogram ustawia się przez API." },
    { icon: "eye", title: "Learner insights", text: "Gdzie uczący się mają trudności, przypisane do konkretnego elementu lekcji, z propozycją poprawki AI, którą oceniasz jako diff." },
    { icon: "server", title: "Dane xAPI we własnym wdrożeniu", text: "Wbudowany magazyn rekordów xAPI działa w Twoim wdrożeniu, więc zapisy zostają u Ciebie." },
  ],
  comingTitle: "Co jest w drodze",
  comingIntro: "Zaplanowane, jeszcze niedostępne. Oznaczone uczciwie.",
  coming: [
    { icon: "compass", title: "Kreator raportów z zapisanymi filtrami", text: "Zbuduj własny raport z dostępnych danych i zapisz jego filtry na później." },
    { icon: "server", title: "Konektor BI", text: "Bezpośredni konektor do narzędzi BI. Dziś użyj eksportu lub API raportów." },
    { icon: "clock", title: "Metryka czasu i kosztu tworzenia", text: "Ile trwało stworzenie kursu i ile kosztowało AI, mierzone dla każdego kursu." },
  ],
  boundaryTitle: "Częściowo gotowe",
  boundary: "Eksport i API raportów działają dziś. Nie ma jeszcze konektora BI poza nimi ani powiązania z Twoimi wskaźnikami biznesowymi, a panel administratora nie pokazuje jeszcze liczb o trudnościach i odrabianiu.",
  ctaTitle: "Zobacz dane, które produkuje Twoja akademia",
  ctaText: "Przeczytaj, jak działają analityka, raporty zgodności i eksporty.",
  cta: "Przewodnik po analityce",
  docs: "Dokumentacja",
  ctaHref: DOCS + "creators/course-insights/",
};

const REPORTING_ZH: ImpactCopy = {
  title: "ulams — LMS 报表与分析",
  description: "报表不足是更换 LMS 的主要原因之一。ULAMS 已提供的分析、导出与合规报表,以及即将推出的功能。",
  eyebrow: "报表",
  heading: "可行动、可带走的报表",
  subtitle: "面向作者的仪表板、面向合规的报表,以及你自己的工具能读取的导出。",
  body: "当团队无法从 LMS 中得到答案时,就会离开它。ULAMS 为作者提供按课程的分析,为合规团队提供带审计导出的报表,并把数据保存在你可控的格式和系统中。",
  primary: "ULAMS 现在有什么",
  secondary: "查看演示",
  problemTitle: "为什么重要",
  problem: "在 Brandon Hall Group 2013 年的调查中(由 Training 杂志报道),457 名受访者里有 **45.5%** 对其 LMS 的价值给予高分,**47.7%** 正在寻找新的供应商,报表不足是主要原因之一。在 2012 年的调查中,报表问题排名第一。来源:[Training 杂志关于 Brandon Hall Group LMS 调查的报道](" + BRANDON_HALL_SOURCE + ")。",
  todayTitle: "ULAMS 现在有什么",
  todayIntro: "已发布并有文档说明。",
  today: [
    { icon: "chart", title: "按课程的分析仪表板", text: "进度、完成率、测验表现、需要支持的学员以及令人困惑的章节。少于 10 名学员的分组不显示。" },
    { icon: "shield", title: "合规报表与审计导出", text: "必修培训状态,以及防篡改的审计记录,显示谁改了课程的什么内容,可导出。" },
    { icon: "pager", title: "CSV 与 JSON-lines 导出", text: "任意报表都可下载为 CSV 或 JSON lines,供你自己的工具使用。电子表格公式注入已被中和。" },
    { icon: "mail", title: "定时邮件报表", text: "按日、周或月把报表通过邮件发给学院用户。定时通过 API 设置。" },
    { icon: "eye", title: "学习洞察", text: "学员在哪里遇到困难,对应到具体的课时元素,并给出 AI 提出的改进建议,由你以差异形式审阅。" },
    { icon: "server", title: "xAPI 数据留在你的部署中", text: "内置的 xAPI 学习记录库运行在你自己的部署里,语句数据始终归你所有。" },
  ],
  comingTitle: "即将推出",
  comingIntro: "已规划,尚未发布,并如实标注。",
  coming: [
    { icon: "compass", title: "带已保存筛选条件的报表构建器", text: "基于现有数据自建报表,并保存筛选条件以便下次使用。" },
    { icon: "server", title: "BI 连接器", text: "直连 BI 工具的连接器。目前请使用导出或报表 API。" },
    { icon: "clock", title: "制作耗时与成本指标", text: "按课程度量制作所用时间及其 AI 成本。" },
  ],
  boundaryTitle: "部分已实现",
  boundary: "导出和报表 API 现已可用。除此之外尚无 BI 连接器,也未与你的业务指标关联,管理后台的仪表板还不显示困难与恢复数据。",
  ctaTitle: "看看你的学院产生的数据",
  ctaText: "了解分析、合规报表和导出如何运作。",
  cta: "分析指南",
  docs: "文档",
  ctaHref: DOCS + "creators/course-insights/",
};

const COPY: Record<ImpactPage, Record<Locale, ImpactCopy>> = {
  "content-cost": { en: COST_EN, pl: COST_PL, zh: COST_ZH },
  reporting: { en: REPORTING_EN, pl: REPORTING_PL, zh: REPORTING_ZH },
};

export const impactCopy = (page: ImpactPage, locale: Locale): ImpactCopy => COPY[page][locale];

const OVERVIEW_NAME = { en: "Overview", pl: "Przegląd", zh: "产品概览" } as const;

function chrome(locale: Locale) {
  const doc = comparisonDocFor(locale);
  const header = doc?.children?.find((node) => node.component === "SiteHeader");
  const footer = doc?.children?.find((node) => node.component === "SiteFooter");
  return header && footer ? { header, footer } : null;
}

const list = (id: string, title: string, intro: string, items: Item[], status: "available" | "coming"): UiNode => ({
  component: "FeatureList",
  props: { id, variant: "tiles", title, intro, items: items.map((item) => ({ ...item, status })) },
});

export function impactPage(page: ImpactPage, locale: Locale, request: Request, url: URL) {
  const copy = impactCopy(page, locale);
  const parts = chrome(locale);
  if (!parts) return null;
  const lang = LOCALE_META[locale].lang;
  const base: UiNode = {
    component: "Page",
    props: { theme: "platform", title: copy.title, description: copy.description, lang },
    children: [
      parts.header,
      {
        component: "Main",
        children: [
          { component: "Hero", props: { variant: "product", eyebrow: copy.eyebrow, title: copy.heading, subtitle: copy.subtitle, body: copy.body, primaryCta: { label: copy.primary, href: "#today" }, secondaryCta: { label: copy.secondary, href: "/#demos" }, facts: FACTS[page][locale] } },
          { component: "Stack", props: { gap: "lg" }, children: [{ component: "Prose", props: { markdown: `## ${copy.problemTitle}\n\n${copy.problem}` } }] },
          ...(copy.run ? [{ component: "Stack", props: { gap: "lg" }, children: [{ component: "Prose", props: { markdown: copy.run } }] }] : []),
          list("today", copy.todayTitle, copy.todayIntro, copy.today, "available"),
          list("coming", copy.comingTitle, copy.comingIntro, copy.coming, "coming"),
          { component: "Stack", props: { gap: "lg" }, children: [{ component: "Callout", props: { tone: "key", title: copy.boundaryTitle, text: copy.boundary } }] },
          { component: "CtaBand", props: { id: "next", title: copy.ctaTitle, text: copy.ctaText, cta: { label: copy.cta, href: copy.ctaHref }, secondary: { label: copy.docs, href: DOCS } } },
        ],
      },
      parts.footer,
    ],
  };
  const doc = withLanguages(base, languageLinks(locale, page as PublicPage), LOCALE_META[locale].path);
  const origin = publicOrigin(request.headers, url);
  const canonical = pageCanonicalUrl(origin, locale, page);
  const jsonLd = [
    { "@context": "https://schema.org", "@type": "Organization", "@id": `${origin}/#organization`, name: "ulams", url: origin, logo: `${origin}/favicon.svg`, sameAs: ["https://github.com/ulams-dev"] },
    { "@context": "https://schema.org", "@type": "WebSite", "@id": `${origin}/#website`, url: origin, name: "ulams", inLanguage: lang, publisher: { "@id": `${origin}/#organization` } },
    { "@context": "https://schema.org", "@type": "WebPage", name: copy.title, description: copy.description, url: canonical, inLanguage: lang, isPartOf: { "@id": `${origin}/#website` }, breadcrumb: { "@id": `${canonical}#breadcrumb` } },
    { "@context": "https://schema.org", "@type": "BreadcrumbList", "@id": `${canonical}#breadcrumb`, itemListElement: [
      { "@type": "ListItem", position: 1, name: OVERVIEW_NAME[locale], item: new URL(LOCALE_META[locale].path, origin).href },
      { "@type": "ListItem", position: 2, name: copy.heading, item: canonical },
    ] },
  ];
  return {
    doc,
    data: {},
    meta: { title: copy.title, description: copy.description, lang },
    canonical,
    alternates: pageAlternateLinks(origin, page),
    ogLocale: { en: "en_US", pl: "pl_PL", zh: "zh_CN" }[locale],
    jsonLd,
  };
}
