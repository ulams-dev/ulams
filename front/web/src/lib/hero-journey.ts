/**
 * The home hero's journey: the ten-second overview of an L&D person's work with ULAMS, in eight beats that loop
 * (source → chat with the builder → the course assembled from blocks → diff and approval → learners → analytics → source change →
 * update proposal). The walkthrough below the story is the detail; this is the overview, so it shares the example
 * of the how-it-works story (a privacy policy that changes from 30 to 14 days) and none of the screenshots.
 *
 * The builder's lines are its own question labels (api/packages/course-builder: the brief questions and
 * InterviewService::fixedQuestions; the versions question is shortened to its options). Status follows
 * docs/ROADMAP-TODO.md: routing each learner to a version (CV-5) is not merged, so it carries "Coming"; the analytics
 * beat is labelled as an illustration.
 */
import type { Locale } from "../i18n/locales.ts";

export const JOURNEY_ID = "hero-journey";
export const BEATS = ["source", "chat", "draft", "review", "learners", "analytics", "change", "update"] as const;
export type Beat = (typeof BEATS)[number];
/** Milliseconds each beat stays on screen; the loop (8 beats) is far over the 5 s that WCAG 2.2.2 allows without a pause control. */
export const BEAT_MS = 3400;
/** The beat shown without JS and with reduced motion: the course assembled from its blocks (the block palette). */
export const STATIC_BEAT: Beat = "draft";
/** Beats that need longer on screen: the blocks snap in one by one. */
export const BEAT_MS_LONG: Partial<Record<Beat, number>> = { draft: 5200, chat: 4200 };
/** Icons of the course blocks, in snapping order; every block type is shipped (docs/ROADMAP-TODO.md). */
export const BLOCK_ICONS = ["quote", "quiz", "image", "puzzle", "code", "video", "compass", "medal"] as const;

export interface HeroJourneyCopy {
  /** Name of the region and of the beat list. */
  label: string;
  pause: string;
  play: string;
  previous: string;
  next: string;
  /** One line per beat, in plain L&D language. */
  captions: Record<Beat, string>;
  source: { files: string[]; passages: string };
  chat: { builder: string; you: string; lines: Array<{ q: string; a: string }> };
  /** The course outline the blocks snap into: one tiny label per block (BLOCK_ICONS order) and the citation on the text block. */
  draft: { course: string; blocks: string[]; chip: string };
  review: { removed: string; added: string; approve: string; approved: string };
  learners: { base: string; versions: string[]; routing: string; coming: string };
  analytics: { badge: string; funnel: Array<{ label: string; level: number }>; risk: string; quiz: string; quizBars: number[]; compliance: string; complianceValue: string };
  change: { doc: string; before: string; after: string; flagged: string[] };
  update: { title: string; items: string[]; waiting: string };
}

const EN: HeroJourneyCopy = {
  label: "An L&D team's journey with ULAMS",
  pause: "Pause the animation",
  play: "Play the animation",
  previous: "Previous beat",
  next: "Next beat",
  captions: {
    source: "Drop in a policy, your docs or a Notion page.",
    chat: "The builder asks who it is for, level, tone and length.",
    draft: "The course is assembled from blocks: cited text, quiz, video and more.",
    review: "You review each change as a diff and approve it.",
    learners: "Learners take the course, or a version for their level or role.",
    analytics: "Analytics fill in: progress, at-risk learners, quiz results.",
    change: "Your policy changes.",
    update: "The course proposes an update for your review.",
  },
  source: { files: ["Privacy policy.pdf", "Support docs", "Notion page"], passages: "13 passages" },
  chat: {
    builder: "Builder",
    you: "You",
    lines: [
      { q: "Who is this course for?", a: "Support and sales staff" },
      { q: "What is the starting level of your learners?", a: "Beginner" },
      { q: "What tone should the course have?", a: "Friendly" },
      { q: "How long should the whole course take?", a: "60 min" },
      { q: "One course, or versions by level or role?", a: "+ Sales version" },
    ],
  },
  draft: { course: "Course · Handling customer data", blocks: ["Text", "Quiz", "Diagram", "H5P interactive", "Code exercise", "Video + transcript", "Branching scenario", "Certificate"], chip: "Privacy policy § 4" },
  review: { removed: "Delete exports when a project ends.", added: "Delete customer exports after 30 days.", approve: "Approve", approved: "Approved · published" },
  learners: { base: "Course", versions: ["Beginner", "Expert", "Sales"], routing: "Routing each learner to a version", coming: "Coming" },
  analytics: {
    badge: "Illustration, not live data",
    funnel: [
      { label: "Started", level: 100 },
      { label: "In progress", level: 72 },
      { label: "Finished", level: 48 },
    ],
    risk: "At risk",
    quiz: "Hardest questions",
    quizBars: [82, 64, 38, 22],
    compliance: "Mandatory training",
    complianceValue: "On track",
  },
  change: { doc: "Privacy policy · v2", before: "30 days", after: "14 days", flagged: ["Lesson 2", "Quiz question 5"] },
  update: { title: "Update proposal", items: ["Lesson 2: 30 → 14 days", "Quiz question 5: new answer"], waiting: "Waiting for your review" },
};

const PL: HeroJourneyCopy = {
  label: "Droga zespołu L&D z ULAMS",
  pause: "Wstrzymaj animację",
  play: "Odtwórz animację",
  previous: "Poprzedni krok",
  next: "Następny krok",
  captions: {
    source: "Wgraj politykę, dokumentację lub stronę Notion.",
    chat: "Kreator pyta o odbiorców, poziom, ton i długość.",
    draft: "Kurs składa się z bloków: tekst z cytatem, quiz, wideo i inne.",
    review: "Przeglądasz każdą zmianę jako diff i ją zatwierdzasz.",
    learners: "Uczący się przechodzą kurs albo wersję dla swojego poziomu lub roli.",
    analytics: "Analityka się wypełnia: postępy, zagrożeni, wyniki quizów.",
    change: "Twoja polityka się zmienia.",
    update: "Kurs proponuje aktualizację do Twojego przeglądu.",
  },
  source: { files: ["Polityka prywatności.pdf", "Dokumentacja wsparcia", "Strona Notion"], passages: "13 fragmentów" },
  chat: {
    builder: "Kreator",
    you: "Ty",
    lines: [
      { q: "Dla kogo jest ten kurs?", a: "Wsparcie i sprzedaż" },
      { q: "Od jakiego poziomu zaczynają uczący się?", a: "Początkujący" },
      { q: "Jaki ton ma mieć kurs?", a: "Przyjazny" },
      { q: "Ile ma trwać cały kurs?", a: "60 min" },
      { q: "Jeden kurs czy wersje według poziomu lub roli?", a: "+ wersja dla sprzedaży" },
    ],
  },
  draft: { course: "Kurs · Dane klientów", blocks: ["Tekst", "Quiz", "Diagram", "Interakcja H5P", "Ćwiczenie z kodu", "Wideo + transkrypcja", "Scenariusz z wyborami", "Certyfikat"], chip: "Polityka prywatności § 4" },
  review: { removed: "Usuwaj eksporty po zakończeniu projektu.", added: "Usuwaj eksporty danych klientów po 30 dniach.", approve: "Zatwierdź", approved: "Zatwierdzone · opublikowane" },
  learners: { base: "Kurs", versions: ["Początkujący", "Ekspert", "Sprzedaż"], routing: "Kierowanie każdego do jego wersji", coming: "Wkrótce" },
  analytics: {
    badge: "Ilustracja, nie dane na żywo",
    funnel: [
      { label: "Rozpoczęli", level: 100 },
      { label: "W trakcie", level: 72 },
      { label: "Ukończyli", level: 48 },
    ],
    risk: "Zagrożeni",
    quiz: "Najtrudniejsze pytania",
    quizBars: [82, 64, 38, 22],
    compliance: "Szkolenia obowiązkowe",
    complianceValue: "Zgodnie z planem",
  },
  change: { doc: "Polityka prywatności · v2", before: "30 dni", after: "14 dni", flagged: ["Lekcja 2", "Pytanie quizowe 5"] },
  update: { title: "Propozycja aktualizacji", items: ["Lekcja 2: 30 → 14 dni", "Pytanie quizowe 5: nowa odpowiedź"], waiting: "Czeka na Twój przegląd" },
};

const ZH: HeroJourneyCopy = {
  label: "培训团队使用 ULAMS 的全过程",
  pause: "暂停动画",
  play: "播放动画",
  previous: "上一步",
  next: "下一步",
  captions: {
    source: "放入一份政策、你的文档或一个 Notion 页面。",
    chat: "生成器询问受众、水平、语气和时长。",
    draft: "课程由一个个模块组装而成：带引用的文字、测验、视频等。",
    review: "你以差异形式审阅每处修改并批准。",
    learners: "学员学习这门课，或适合其水平或角色的版本。",
    analytics: "分析数据逐步汇集：进度、掉队学员、测验结果。",
    change: "你的政策发生了变化。",
    update: "课程提出更新建议，等你审阅。",
  },
  source: { files: ["隐私政策.pdf", "客服文档", "Notion 页面"], passages: "13 段" },
  chat: {
    builder: "生成器",
    you: "你",
    lines: [
      { q: "这门课是给谁的？", a: "客服和销售人员" },
      { q: "学员的起始水平是什么？", a: "初级" },
      { q: "课程应该用什么语气？", a: "友好" },
      { q: "整门课要多长？", a: "60 分钟" },
      { q: "一门课，还是按水平或角色分版本？", a: "+ 销售版" },
    ],
  },
  draft: { course: "课程 · 处理客户数据", blocks: ["文字", "测验", "图示", "H5P 互动", "编程练习", "视频 + 文字稿", "分支情景", "证书"], chip: "隐私政策 § 4" },
  review: { removed: "项目结束时删除导出数据。", added: "客户导出数据在 30 天后删除。", approve: "批准", approved: "已批准 · 已发布" },
  learners: { base: "课程", versions: ["初级", "专家", "销售"], routing: "把每位学员分配到其版本", coming: "即将推出" },
  analytics: {
    badge: "示意图，非实时数据",
    funnel: [
      { label: "已开始", level: 100 },
      { label: "学习中", level: 72 },
      { label: "已完成", level: 48 },
    ],
    risk: "有掉队风险",
    quiz: "最难的题目",
    quizBars: [82, 64, 38, 22],
    compliance: "必修培训",
    complianceValue: "按计划进行",
  },
  change: { doc: "隐私政策 · v2", before: "30 天", after: "14 天", flagged: ["第 2 课", "测验题 5"] },
  update: { title: "更新建议", items: ["第 2 课：30 → 14 天", "测验题 5：新答案"], waiting: "等待你审阅" },
};

const COPY: Record<Locale, HeroJourneyCopy> = { en: EN, pl: PL, zh: ZH };

export const heroJourneyCopy = (locale: Locale): HeroJourneyCopy => COPY[locale];
