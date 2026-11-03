/**
 * Diagrams of the two economic pages (content cost, reporting): their copy in English, Polish and Chinese, and
 * where each one sits in the page document built by `impact-pages.ts`. Kept apart from that file so the page copy
 * and the diagrams can change independently.
 *
 * Every figure here is either the one measured run already stated in the page copy, the cited Chapman range, or a
 * placeholder shape with no number. Planned features carry the catalogue's "coming" status, as on the page.
 */
import type { UiNode } from "@ulams/ui/render-core";
import type { Locale } from "../i18n/locales.ts";
import { impactCopy, type ImpactPage } from "./impact-pages.ts";

export type DiagramName = "benchmark" | "pipeline" | "living-loop" | "audiences" | "dashboard" | "dataflow" | "roles";

export interface DiagramHead {
  title: string;
  lede: string;
  /** Short label on the diagram: what kind of evidence it is (one run, illustration, shipped, coming). */
  badge: string;
}

export interface Controls {
  replay: string;
  pause: string;
  play: string;
}

export interface BenchmarkCopy extends DiagramHead {
  unit: string;
  axis: string;
  low: { value: string; label: string };
  high: { value: string; label: string };
  /** Plain-language reading of the bar, for every reader. */
  summary: string;
  noteTitle: string;
  note: string;
}

export interface PipelineCopy extends DiagramHead {
  stages: Array<{ title: string; detail: string; metric?: string; results?: string[] }>;
  timingTitle: string;
  /** Seconds of each timed stage of the run; bar widths are drawn to scale from them. */
  timing: Array<{ label: string; seconds: number; value: string }>;
  timingNote: string;
  totals: Array<{ value: string; label: string }>;
  citation: { title: string; passage: string; block: string; chip: string; note: string };
}

export interface LoopCopy extends DiagramHead {
  steps: Array<{ title: string; text: string }>;
  again: string;
}

export interface AudiencesCopy extends DiagramHead {
  source: string;
  base: string;
  baseText: string;
  levelsTitle: string;
  levels: string[];
  rolesTitle: string;
  roles: string[];
  variantNote: string;
}

export interface DashboardCopy extends DiagramHead {
  frame: string;
  funnel: { title: string; text: string; steps: string[] };
  support: { title: string; text: string };
  questions: { title: string; text: string; standout: string };
  training: { title: string; text: string; done: string; due: string; overdue: string };
  hidden: { title: string; text: string };
}

export interface FlowCopy extends DiagramHead {
  columns: Array<{ title: string; items: string[]; coming?: string[] }>;
}

export interface RolesCopy extends DiagramHead {
  roles: Array<{ name: string; items: string[] }>;
}

export interface CostDiagrams {
  controls: Controls;
  benchmark: BenchmarkCopy;
  pipeline: PipelineCopy;
  loop: LoopCopy;
  audiences: AudiencesCopy;
}

export interface ReportingDiagrams {
  controls: Controls;
  dashboard: DashboardCopy;
  flow: FlowCopy;
  roles: RolesCopy;
}

const CONTROLS: Record<Locale, Controls> = {
  en: { replay: "Replay animation", pause: "Pause animation", play: "Play animation" },
  pl: { replay: "Odtwórz animację ponownie", pause: "Wstrzymaj animację", play: "Wznów animację" },
  zh: { replay: "重新播放动画", pause: "暂停动画", play: "播放动画" },
};

/** Seconds of the measured run (outline, lessons/quizzes/checks, apply), as stated in the page copy. */
export const RUN_SECONDS = [24, 6 * 60 + 57, 23] as const;

const COST: Record<Locale, CostDiagrams> = {
  en: {
    controls: CONTROLS.en,
    benchmark: {
      title: "The benchmark, on its own scale",
      lede: "Hours of work behind every hour of finished e-learning, by how interactive it is.",
      badge: "Industry figure, 2010",
      unit: "1 hour of finished e-learning",
      axis: "Hours of work",
      low: { value: "49 h", label: "low interactivity" },
      high: { value: "716 h", label: "high interactivity" },
      summary: "One finished hour took from 49 to 716 hours of work in the Chapman Alliance figures.",
      noteTitle: "Not comparable with our run",
      note: "This measures a whole team's effort per finished hour, in 2010. The run below measures one generation of a draft course. They sit on separate scales on purpose.",
    },
    pipeline: {
      title: "From source to published course",
      lede: "The stages of the one run we measured. The times are that run's, not an average.",
      badge: "One measured run",
      stages: [
        { title: "Your source", detail: "A Git repository, Google Drive, Notion or an upload", metric: "This run: a public docs repository" },
        { title: "Passages read", detail: "The source is split into passages that can be cited", metric: "13 passages" },
        { title: "Outline", detail: "The plan the lessons follow", metric: "24 s" },
        { title: "Lessons and quizzes", detail: "Drafted, then checked: unsupported claims are flagged", metric: "6 min 57 s", results: ["6 lessons", "18 questions", "35 cited blocks"] },
        { title: "Draft applied", detail: "Written into the course as a draft", metric: "23 s" },
        { title: "Author review", detail: "Every change is a diff to accept or reject", metric: "Not timed" },
        { title: "Published", detail: "Only what the author approved reaches learners" },
      ],
      timingTitle: "Time per stage in this run, 7 min 44 s in total",
      timing: [
        { label: "Outline", seconds: RUN_SECONDS[0], value: "24 s" },
        { label: "Lessons, quizzes and checks", seconds: RUN_SECONDS[1], value: "6 min 57 s" },
        { label: "Apply", seconds: RUN_SECONDS[2], value: "23 s" },
      ],
      timingNote: "Bar lengths are to scale. The author's review time is not included.",
      totals: [
        { value: "27", label: "model calls" },
        { value: "USD 0.28", label: "model cost" },
        { value: "35 of 35", label: "blocks cite a passage" },
      ],
      citation: {
        title: "Every block points to its passage",
        passage: "Passage 7 of the source",
        block: "Lesson block",
        chip: "Cites passage 7",
        note: "Schematic: each generated block carries a citation to the passage it is based on.",
      },
    },
    loop: {
      title: "How a Living Course stays current",
      lede: "When a source changes, the course follows, one approved diff at a time.",
      badge: "Available",
      steps: [
        { title: "Source changes", text: "Found by webhook, polling or a manual check, down to the passage" },
        { title: "Affected elements flagged", text: "Lessons and questions that cite the changed passage are marked stale" },
        { title: "AI proposes a diff", text: "A matching update instead of a rewrite, with its citations" },
        { title: "Author approves", text: "Accept all, accept per element, or reject" },
        { title: "Course updated", text: "Learners get the change and keep their progress" },
      ],
      again: "The next source change starts the loop again",
    },
    audiences: {
      title: "One source, many audiences",
      lede: "Variants of one course by level and by role, drafted in the builder and each reviewed as a diff against the base course. Routing each learner to a variant is coming.",
      badge: "Available, routing coming",
      source: "One source",
      base: "Base course",
      baseText: "Shared sources, passages and citations",
      levelsTitle: "By level",
      levels: ["Beginner", "Intermediate", "Expert"],
      rolesTitle: "By role",
      roles: ["A role you name", "Another role"],
      variantNote: "Each variant is reviewed as a diff against the base",
    },
  },
  pl: {
    controls: CONTROLS.pl,
    benchmark: {
      title: "Punkt odniesienia na osobnej skali",
      lede: "Godziny pracy na każdą godzinę gotowego e-learningu, zależnie od interaktywności.",
      badge: "Dane branżowe, 2010",
      unit: "1 godzina gotowego e-learningu",
      axis: "Godziny pracy",
      low: { value: "49 h", label: "niska interaktywność" },
      high: { value: "716 h", label: "wysoka interaktywność" },
      summary: "Według danych Chapman Alliance jedna gotowa godzina wymagała od 49 do 716 godzin pracy.",
      noteTitle: "Nieporównywalne z naszym przebiegiem",
      note: "To praca całego zespołu na gotową godzinę, w 2010 roku. Przebieg poniżej mierzy jedno wygenerowanie szkicu kursu. Celowo stoją na osobnych skalach.",
    },
    pipeline: {
      title: "Od źródła do opublikowanego kursu",
      lede: "Etapy jednego zmierzonego przebiegu. Czasy pochodzą z tego przebiegu, a nie ze średniej.",
      badge: "Jeden zmierzony przebieg",
      stages: [
        { title: "Twoje źródło", detail: "Repozytorium Git, Google Drive, Notion albo wgrany plik", metric: "Tu: publiczne repozytorium dokumentacji" },
        { title: "Przeczytane fragmenty", detail: "Źródło dzielone na fragmenty, które można cytować", metric: "13 fragmentów" },
        { title: "Konspekt", detail: "Plan, według którego powstają lekcje", metric: "24 s" },
        { title: "Lekcje i quizy", detail: "Napisane, potem sprawdzone: twierdzenia bez oparcia są oznaczane", metric: "6 min 57 s", results: ["6 lekcji", "18 pytań", "35 bloków z cytatem"] },
        { title: "Szkic zastosowany", detail: "Zapisany w kursie jako szkic", metric: "23 s" },
        { title: "Przegląd autora", detail: "Każda zmiana to diff do przyjęcia lub odrzucenia", metric: "Bez pomiaru" },
        { title: "Publikacja", detail: "Do uczących się trafia tylko to, co autor zatwierdził" },
      ],
      timingTitle: "Czas etapów w tym przebiegu, łącznie 7 min 44 s",
      timing: [
        { label: "Konspekt", seconds: RUN_SECONDS[0], value: "24 s" },
        { label: "Lekcje, quizy i kontrole", seconds: RUN_SECONDS[1], value: "6 min 57 s" },
        { label: "Zastosowanie", seconds: RUN_SECONDS[2], value: "23 s" },
      ],
      timingNote: "Długości pasków są w skali. Czas przeglądu przez autora nie jest wliczony.",
      totals: [
        { value: "27", label: "wywołań modelu" },
        { value: "0,28 USD", label: "koszt modelu" },
        { value: "35 z 35", label: "bloków cytuje fragment" },
      ],
      citation: {
        title: "Każdy blok wskazuje swój fragment",
        passage: "Fragment 7 źródła",
        block: "Blok lekcji",
        chip: "Cytuje fragment 7",
        note: "Schemat: każdy wygenerowany blok ma cytat fragmentu, na którym się opiera.",
      },
    },
    loop: {
      title: "Jak Living Course pozostaje aktualny",
      lede: "Gdy źródło się zmienia, kurs idzie za nim, jeden zatwierdzony diff naraz.",
      badge: "Dostępne",
      steps: [
        { title: "Źródło się zmienia", text: "Wykryte przez webhook, odpytywanie albo ręczne sprawdzenie, aż do fragmentu" },
        { title: "Oznaczone elementy", text: "Lekcje i pytania cytujące zmieniony fragment dostają status nieaktualnych" },
        { title: "AI proponuje diff", text: "Pasującą poprawkę zamiast przepisywania, razem z cytatami" },
        { title: "Autor zatwierdza", text: "Przyjmij wszystko, przyjmij element po elemencie albo odrzuć" },
        { title: "Kurs zaktualizowany", text: "Uczący się dostają zmianę i zachowują postęp" },
      ],
      again: "Następna zmiana źródła zaczyna pętlę od nowa",
    },
    audiences: {
      title: "Jedno źródło, wielu odbiorców",
      lede: "Warianty jednego kursu według poziomu i roli, przygotowane w kreatorze i oceniane jako diff względem kursu bazowego. Kierowanie uczących się do wariantu jest w drodze.",
      badge: "Dostępne, kierowanie wkrótce",
      source: "Jedno źródło",
      base: "Kurs bazowy",
      baseText: "Wspólne źródła, fragmenty i cytaty",
      levelsTitle: "Według poziomu",
      levels: ["Początkujący", "Średniozaawansowany", "Ekspert"],
      rolesTitle: "Według roli",
      roles: ["Rola, którą nazwiesz", "Kolejna rola"],
      variantNote: "Każdy wariant oceniany jako diff względem bazy",
    },
  },
  zh: {
    controls: CONTROLS.zh,
    benchmark: {
      title: "行业基准,单独一把尺子",
      lede: "每 1 小时成品在线课程背后的工作小时数,取决于交互程度。",
      badge: "行业数据,2010 年",
      unit: "1 小时成品在线课程",
      axis: "工作小时数",
      low: { value: "49 小时", label: "低交互" },
      high: { value: "716 小时", label: "高交互" },
      summary: "根据 Chapman Alliance 的数据,1 小时成品课程需要 49 至 716 小时的工作。",
      noteTitle: "不能与我们的实测比较",
      note: "这是 2010 年整个团队制作每小时成品的工作量。下方的实测度量的是一次生成课程草稿。两者特意放在不同的尺子上。",
    },
    pipeline: {
      title: "从资料到发布的课程",
      lede: "我们实测的那一次运行的各个阶段。时间来自那一次运行,不是平均值。",
      badge: "一次实测",
      stages: [
        { title: "你的资料", detail: "Git 仓库、Google Drive、Notion 或上传的文件", metric: "本次:一个公开文档仓库" },
        { title: "读取片段", detail: "资料被拆分为可引用的片段", metric: "13 个片段" },
        { title: "大纲", detail: "课时依照的计划", metric: "24 秒" },
        { title: "课时与测验", detail: "先撰写,再检查:没有依据的说法会被标出", metric: "6 分 57 秒", results: ["6 个课时", "18 道题", "35 个带出处的内容块"] },
        { title: "应用草稿", detail: "作为草稿写入课程", metric: "23 秒" },
        { title: "作者审阅", detail: "每项改动都是可接受或拒绝的差异", metric: "未计时" },
        { title: "发布", detail: "只有作者批准的内容才会到达学员" },
      ],
      timingTitle: "本次运行各阶段用时,总计 7 分 44 秒",
      timing: [
        { label: "大纲", seconds: RUN_SECONDS[0], value: "24 秒" },
        { label: "课时、测验与检查", seconds: RUN_SECONDS[1], value: "6 分 57 秒" },
        { label: "应用", seconds: RUN_SECONDS[2], value: "23 秒" },
      ],
      timingNote: "条形长度按比例绘制。不含作者审阅时间。",
      totals: [
        { value: "27", label: "次模型调用" },
        { value: "0.28 美元", label: "模型成本" },
        { value: "35 / 35", label: "个内容块引用了片段" },
      ],
      citation: {
        title: "每个内容块都指向它的片段",
        passage: "资料第 7 个片段",
        block: "课时内容块",
        chip: "引用片段 7",
        note: "示意图:每个生成的内容块都带有其依据片段的出处。",
      },
    },
    loop: {
      title: "Living Course 如何保持最新",
      lede: "资料变化时,课程随之更新,每次一个经批准的差异。",
      badge: "已可用",
      steps: [
        { title: "资料变化", text: "通过 webhook、轮询或手动检查发现,精确到片段" },
        { title: "标出受影响的元素", text: "引用了该片段的课时和题目被标为过时" },
        { title: "AI 提出差异", text: "相应的更新而不是重写,附带出处" },
        { title: "作者批准", text: "全部接受、逐项接受或拒绝" },
        { title: "课程已更新", text: "学员收到改动,学习进度保留" },
      ],
      again: "下一次资料变化会重新开始这个循环",
    },
    audiences: {
      title: "一份资料,多类学员",
      lede: "同一门课程按级别和角色生成的版本,在生成器中起草,每个版本都以与基础课程的差异形式审阅。将每位学员分配到合适版本的功能即将推出。",
      badge: "已可用,分配即将推出",
      source: "一份资料",
      base: "基础课程",
      baseText: "共享资料、片段和出处",
      levelsTitle: "按级别",
      levels: ["初学者", "中级", "专家"],
      rolesTitle: "按角色",
      roles: ["你命名的角色", "另一个角色"],
      variantNote: "每个版本都以与基础课程的差异形式审阅",
    },
  },
};

const REPORTING: Record<Locale, ReportingDiagrams> = {
  en: {
    controls: CONTROLS.en,
    dashboard: {
      title: "Reporting at a glance",
      lede: "A schematic of the analytics and compliance views. Shapes stand in for data, so there are no numbers on it.",
      badge: "Illustration, not live data",
      frame: "Course analytics",
      funnel: { title: "Completion", text: "How many learners enrolled, started and completed", steps: ["Enrolled", "Started", "Completed"] },
      support: { title: "Learners who need support", text: "Each one listed with a readable reason" },
      questions: { title: "Quiz performance by question", text: "Questions most learners get wrong stand out", standout: "Stands out" },
      training: { title: "Mandatory training", text: "Who is done, due or overdue", done: "Done", due: "Due", overdue: "Overdue" },
      hidden: { title: "Hidden group", text: "Groups under 10 learners are not shown" },
    },
    flow: {
      title: "Where the data goes",
      lede: "From learner activity to reports you can take with you. Planned parts are dashed and labelled.",
      badge: "Available, two parts coming",
      columns: [
        { title: "Learner activity", items: ["xAPI", "SCORM", "cmi5", "H5P", "Quizzes"] },
        { title: "Your own deployment", items: ["xAPI learning record store", "Progress, results and learner signals"] },
        { title: "Reports", items: ["Per-course analytics", "Compliance reports and audit trail", "Learner insights"] },
        { title: "Out to you", items: ["CSV and JSON-lines export", "Scheduled e-mail reports", "Report API"], coming: ["Report builder with saved filters", "BI connector"] },
      ],
    },
    roles: {
      title: "Who sees what",
      lede: "Each audience gets the view it needs. In the analytics dashboard, groups under 10 learners are hidden.",
      badge: "Available",
      roles: [
        { name: "Authors", items: ["Per-course progress, completion and quiz performance", "Learners who need support and the sections that confuse people", "Learner insights with an AI-proposed fix, reviewed as a diff"] },
        { name: "Admins", items: ["The same per-course analytics, on the course form", "Report schedules, set up through the API"] },
        { name: "Compliance teams", items: ["Mandatory-training status", "A tamper-evident audit trail of who changed what, exportable"] },
        { name: "Academy users on a schedule", items: ["A report by e-mail, daily, weekly or monthly"] },
        { name: "Your own tools", items: ["Any report as CSV or JSON lines", "The report API"] },
      ],
    },
  },
  pl: {
    controls: CONTROLS.pl,
    dashboard: {
      title: "Raportowanie w skrócie",
      lede: "Schemat widoków analityki i zgodności. Kształty zastępują dane, więc nie ma na nim liczb.",
      badge: "Ilustracja, nie dane na żywo",
      frame: "Analityka kursu",
      funnel: { title: "Ukończenia", text: "Ile osób się zapisało, zaczęło i ukończyło", steps: ["Zapisani", "Rozpoczęli", "Ukończyli"] },
      support: { title: "Osoby potrzebujące wsparcia", text: "Każda z czytelnym powodem" },
      questions: { title: "Wyniki quizu według pytań", text: "Pytania, na które większość odpowiada źle, wyróżniają się", standout: "Wyróżnia się" },
      training: { title: "Szkolenia obowiązkowe", text: "Kto ukończył, komu zbliża się termin, kto jest po terminie", done: "Ukończone", due: "Termin", overdue: "Po terminie" },
      hidden: { title: "Ukryta grupa", text: "Grupy poniżej 10 osób nie są pokazywane" },
    },
    flow: {
      title: "Dokąd trafiają dane",
      lede: "Od aktywności uczących się do raportów, które możesz zabrać. Planowane części są przerywane i opisane.",
      badge: "Dostępne, dwie części wkrótce",
      columns: [
        { title: "Aktywność uczących się", items: ["xAPI", "SCORM", "cmi5", "H5P", "Quizy"] },
        { title: "Twoje własne wdrożenie", items: ["Magazyn rekordów xAPI", "Postępy, wyniki i sygnały uczących się"] },
        { title: "Raporty", items: ["Analityka każdego kursu", "Raporty zgodności i ślad audytowy", "Learner insights"] },
        { title: "Do Ciebie", items: ["Eksport CSV i JSON lines", "Raporty e-mailem według harmonogramu", "API raportów"], coming: ["Kreator raportów z zapisanymi filtrami", "Konektor BI"] },
      ],
    },
    roles: {
      title: "Kto co widzi",
      lede: "Każdy odbiorca dostaje widok, którego potrzebuje. W panelu analitycznym grupy poniżej 10 osób są ukryte.",
      badge: "Dostępne",
      roles: [
        { name: "Autorzy", items: ["Postępy, ukończenia i wyniki quizów każdego kursu", "Osoby potrzebujące wsparcia i fragmenty, które mylą", "Learner insights z propozycją poprawki AI, ocenianą jako diff"] },
        { name: "Administratorzy", items: ["Ta sama analityka kursu, w formularzu kursu", "Harmonogramy raportów, ustawiane przez API"] },
        { name: "Działy zgodności", items: ["Status szkoleń obowiązkowych", "Odporny na manipulacje ślad audytowy, kto co zmienił, z eksportem"] },
        { name: "Użytkownicy akademii według harmonogramu", items: ["Raport e-mailem codziennie, co tydzień lub co miesiąc"] },
        { name: "Twoje narzędzia", items: ["Dowolny raport jako CSV lub JSON lines", "API raportów"] },
      ],
    },
  },
  zh: {
    controls: CONTROLS.zh,
    dashboard: {
      title: "报表一览",
      lede: "分析与合规视图的示意图。图形代替数据,因此图上没有数字。",
      badge: "示意图,非实时数据",
      frame: "课程分析",
      funnel: { title: "完成情况", text: "多少学员已报名、已开始、已完成", steps: ["已报名", "已开始", "已完成"] },
      support: { title: "需要支持的学员", text: "每人都附有易读的原因" },
      questions: { title: "按题目的测验表现", text: "多数学员答错的题目会突出显示", standout: "突出" },
      training: { title: "必修培训", text: "谁已完成、即将到期或已逾期", done: "已完成", due: "即将到期", overdue: "已逾期" },
      hidden: { title: "隐藏的分组", text: "少于 10 名学员的分组不显示" },
    },
    flow: {
      title: "数据流向哪里",
      lede: "从学员活动到你可以带走的报表。计划中的部分以虚线标出并注明。",
      badge: "已可用,两项即将推出",
      columns: [
        { title: "学员活动", items: ["xAPI", "SCORM", "cmi5", "H5P", "测验"] },
        { title: "你自己的部署", items: ["xAPI 学习记录库", "进度、成绩和学员信号"] },
        { title: "报表", items: ["按课程的分析", "合规报表与审计记录", "学习洞察"] },
        { title: "交付给你", items: ["CSV 与 JSON-lines 导出", "定时邮件报表", "报表 API"], coming: ["带已保存筛选条件的报表构建器", "BI 连接器"] },
      ],
    },
    roles: {
      title: "谁能看到什么",
      lede: "每类用户都得到所需的视图。在分析仪表板中,少于 10 名学员的分组不显示。",
      badge: "已可用",
      roles: [
        { name: "作者", items: ["按课程的进度、完成率和测验表现", "需要支持的学员以及令人困惑的章节", "学习洞察及 AI 提出的改进,以差异形式审阅"] },
        { name: "管理员", items: ["同样的按课程分析,位于课程表单中", "报表定时,通过 API 设置"] },
        { name: "合规团队", items: ["必修培训状态", "防篡改的审计记录,显示谁改了什么,可导出"] },
        { name: "定时接收报表的学院用户", items: ["按日、周或月通过邮件收到报表"] },
        { name: "你自己的工具", items: ["任意报表的 CSV 或 JSON lines", "报表 API"] },
      ],
    },
  },
};

export const costDiagrams = (locale: Locale): CostDiagrams => COST[locale];
export const reportingDiagrams = (locale: Locale): ReportingDiagrams => REPORTING[locale];

/** Which section of the page document a diagram follows. */
type Anchor = "problem" | "run" | "today" | "coming";

const PLACEMENT: Record<ImpactPage, Array<{ name: DiagramName; after: Anchor }>> = {
  "content-cost": [
    { name: "benchmark", after: "problem" },
    { name: "pipeline", after: "run" },
    { name: "living-loop", after: "today" },
    { name: "audiences", after: "coming" },
  ],
  reporting: [
    { name: "dashboard", after: "problem" },
    { name: "dataflow", after: "today" },
    { name: "roles", after: "today" },
  ],
};

export type BodyPart = { kind: "node"; node: UiNode } | { kind: "diagram"; name: DiagramName };

export interface ImpactBodyPlan {
  /** Nodes before the main landmark (the site header). */
  before: UiNode[];
  /** Children of the main landmark, with the diagrams placed between them. */
  main: BodyPart[];
  /** Nodes after the main landmark (the site footer). */
  after: UiNode[];
  /** Diagrams whose section was not found; they were placed before the closing call to action. */
  unanchored: DiagramName[];
}

const proseOf = (node: UiNode): string[] =>
  node.component === "Prose" ? [String(node.props?.markdown ?? "")] : (node.children ?? []).flatMap(proseOf);

function isAnchor(node: UiNode, anchor: Anchor, page: ImpactPage, locale: Locale): boolean {
  const copy = impactCopy(page, locale);
  if (anchor === "today" || anchor === "coming") return node.props?.id === anchor;
  const texts = proseOf(node);
  if (anchor === "problem") return texts.some((md) => md.startsWith(`## ${copy.problemTitle}`));
  return Boolean(copy.run) && texts.some((md) => md === copy.run);
}

/**
 * Splits the page document around its main landmark and places each diagram after the section it explains.
 * Returns null for a document without a Page > Main structure, which is then rendered as it is.
 */
export function planImpactBody(doc: UiNode, page: ImpactPage, locale: Locale): ImpactBodyPlan | null {
  const children = doc.component === "Page" ? (doc.children ?? []) : [];
  const mainIndex = children.findIndex((node) => node.component === "Main");
  if (mainIndex < 0) return null;
  const sections = children[mainIndex]!.children ?? [];
  const placed = new Set<DiagramName>();
  const main: BodyPart[] = [];
  const pending = PLACEMENT[page];
  const unanchored = pending.filter((d) => !sections.some((node) => isAnchor(node, d.after, page, locale))).map((d) => d.name);
  for (const node of sections) {
    if (node.props?.id === "next" || node.component === "CtaBand") {
      for (const name of unanchored) {
        if (placed.has(name)) continue;
        main.push({ kind: "diagram", name });
        placed.add(name);
      }
    }
    main.push({ kind: "node", node });
    for (const d of pending) {
      if (!placed.has(d.name) && isAnchor(node, d.after, page, locale)) {
        main.push({ kind: "diagram", name: d.name });
        placed.add(d.name);
      }
    }
  }
  for (const name of unanchored) if (!placed.has(name)) main.push({ kind: "diagram", name });
  return { before: children.slice(0, mainIndex), main, after: children.slice(mainIndex + 1), unanchored };
}
