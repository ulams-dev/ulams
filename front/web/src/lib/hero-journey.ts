/**
 * The home hero's journey: the ten-second overview of an L&D person's work with ULAMS, in eight beats that loop
 * (your material → chat with the builder → your design system → the course assembled from blocks → diff and approval →
 * learners → analytics → a source change and the update proposal). Each beat is a tab the visitor can open; the
 * walkthrough below the story is the detail. The example is neutral product documentation, not a policy.
 *
 * The builder's lines are its own question labels (api/packages/course-builder: the brief questions and
 * InterviewService::fixedQuestions; the versions question is shortened to its options). Sources are the shipped
 * connectors (upload, Git, URL, Google Drive, Notion); Confluence appears only as an exported file. The interactive
 * blocks are the H5P types played in the demos (image hotspots, drag and drop, dialog cards). Status follows
 * docs/ROADMAP-TODO.md: routing each learner to a version (CV-5) is not merged, so it carries "Coming"; the analytics
 * beat is labelled as an illustration.
 */
import type { Locale } from "../i18n/locales.ts";

export const JOURNEY_ID = "hero-journey";
export const BEATS = ["source", "chat", "brand", "draft", "review", "learners", "analytics", "update"] as const;
export type Beat = (typeof BEATS)[number];
/** Milliseconds each beat stays on screen; the loop is far over the 5 s that WCAG 2.2.2 allows without a pause control. */
export const BEAT_MS = 3600;
/** The beat shown without JS: the course assembled from its blocks (the block palette). */
export const STATIC_BEAT: Beat = "draft";
/** Beats that need longer on screen: the chat types its turns, the blocks snap in one by one. */
export const BEAT_MS_LONG: Partial<Record<Beat, number>> = { chat: 8600, draft: 6400, review: 7200, update: 7400 };
/** Icons of the course blocks, in snapping order; every block type is shipped (docs/ROADMAP-TODO.md). */
export const BLOCK_ICONS = ["quote", "quiz", "puzzle", "image", "layers", "video", "code", "medal"] as const;

export interface HeroJourneyCopy {
  /** Name of the region and of the beat list. */
  label: string;
  pause: string;
  play: string;
  previous: string;
  next: string;
  /** Short name of each beat on its tab. */
  tabs: Record<Beat, string>;
  whyLabel: string;
  /** Tag on the picture of a beat: it is an example, not a screen. */
  example: string;
  /** What happens in each beat and why it matters to an L&D team, in plain language. */
  steps: Record<Beat, { title: string; text: string; why: string }>;
  /** Closing line under the steps: learner insights start the next round. */
  loop: { title: string; text: string };
  /** The material and the passages it is split into: where each comes from and what it says. */
  source: { files: string[]; passages: { title: string; items: Array<{ ref: string; text: string }>; more: string } };
  /** The builder chat: its questions (its own wording), the preset options it offers, and your answer; the first one is typed. */
  chat: { builder: string; placeholder: string; lines: Array<{ q: string; options?: string[]; a: string }> };
  brand: { source: string; tokens: string[]; font: string; domain: string; academy: string; approved: string };
  /**
   * The full course the blocks snap into: modules group the lessons, each lesson is its standard cited text plus
   * blocks (indexes into `blocks`, BLOCK_ICONS order), and the course ends with a final test and the certificate.
   */
  draft: {
    course: string;
    blocks: string[];
    chip: string;
    lessonText: string;
    modules: Array<{ title: string; lessons: Array<{ title: string; blocks: number[] }> }>;
    final: { title: string; blocks: number[] };
  };
  /** A lesson's proposed blocks: most accepted in one go, one replaced through an instruction, one rejected. */
  review: {
    lesson: string;
    items: Array<{ kind: string; text: string; state: "accept" | "replace" | "reject"; note: string }>;
    ask: string;
    replacement: string;
    approve: string;
    approved: string;
  };
  /** One base course, a version per role, each published on its own academy subdomain; linking them across academies is coming. */
  learners: { base: string; versions: Array<{ name: string; domain: string; meta: string }>; linked: string; coming: string };
  /** Course funnel and compliance, and every block of the course with its own result (BLOCK_ICONS order); one needs attention. */
  analytics: {
    badge: string;
    funnel: Array<{ label: string; level: number }>;
    risk: string;
    compliance: string;
    complianceValue: string;
    blocksTitle: string;
    blocks: Array<{ label: string; value: string; level: number; warn?: boolean }>;
    attention: string;
  };
  /** A document change, the update proposal it triggers, the new course version, and what learners see before and after. */
  update: {
    doc: string;
    section: string;
    before: string;
    after: string;
    proposal: string;
    affected: string[];
    approved: string;
    from: string;
    to: string;
    lesson: string;
    oldLabel: string;
    newLabel: string;
    updated: string;
    keep: string;
  };
}

const EN: HeroJourneyCopy = {
  label: "An L&D team's journey with ULAMS",
  pause: "Pause the animation",
  play: "Play the animation",
  previous: "Previous step",
  next: "Next step",
  tabs: { source: "Your material", chat: "Builder chat", brand: "Your brand", draft: "Course blocks", review: "Your review", learners: "Learners", analytics: "Analytics", update: "Updates" },
  whyLabel: "Why it matters",
  example: "Example",
  steps: {
    source: {
      title: "Start from the material you already have",
      text: "Upload documents or connect Notion, Google Drive or Git; a Confluence space comes in as an export. ULAMS splits everything into short passages it can quote.",
      why: "No blank page and no copy-and-paste: experts keep writing where they already do, and the course is built from that.",
    },
    chat: {
      title: "Answer a few questions in a chat",
      text: "The builder asks who the course is for, their starting level, the length and whether you need versions. Your answers become the course brief.",
      why: "A few answers replace a briefing meeting and a template.",
    },
    brand: {
      title: "Your design system, your academy",
      text: "Import colours, fonts and logo from a DESIGN.md, a Figma file or a brand guide. You approve the theme as a diff, and each academy runs white-label on its own subdomain.",
      why: "Training looks like your company, and every colour is checked for WCAG 2.2 AA contrast before it is saved.",
    },
    draft: {
      title: "A full course: modules, lessons and blocks",
      text: "Modules group the lessons. Each lesson is standard text with cited passages, plus interactive blocks: quizzes, drag and drop, image hotspots, dialog cards, video and code exercises. The course ends with a final test and a certificate.",
      why: "Your team edits a sourced draft instead of starting from zero, and reviewers check each statement against its passage.",
    },
    review: {
      title: "You stay in control",
      text: "Every AI change arrives as a diff with the passage behind it. Approve a lesson in one go, reject a block, or ask for an alternative, such as a diagram instead of a quiz.",
      why: "Nothing is published without your approval, and the audit trail records who approved what and when.",
    },
    learners: {
      title: "A version per role, each in its own academy",
      text: "Draft a version for each role or level from the same sources, and publish each in its own academy on its own subdomain, with its own branding. Keeping the versions linked across academies is coming.",
      why: "Support, sales and partners each get training that fits them, without writing three courses.",
    },
    analytics: {
      title: "Evidence for managers and auditors",
      text: "Every block reports its own results: who read the text, who got the quiz right, how many tries the drag and drop took. Per course you see who is stuck, who finished and who still owes mandatory training, with an audit export.",
      why: "You see which block to fix, not just which course, and the data stays in your own deployment.",
    },
    update: {
      title: "When your documents change, the course follows",
      text: "When a document changes, ULAMS finds the passage, proposes an update for the lessons and questions that quote it and, once you approve, publishes a new version of the course. Learners see what changed and keep their progress.",
      why: "You review the few lessons a change touches instead of re-checking the whole course.",
    },
  },
  loop: { title: "Then the loop starts again", text: "Learner insights show which sections confuse people and turn them into an improvement proposal you review like any other change." },
  source: {
    files: ["Product handbook.pdf", "Confluence export.docx", "Notion page", "Google Drive folder"],
    passages: {
      title: "13 passages, each one quotable with its source",
      items: [{ ref: "Handbook § 3", text: "Reports can be exported as CSV." }, { ref: "Confluence · Support FAQ", text: "Customers ask how to share a report." }, { ref: "Notion · Onboarding", text: "New users start on the Reports page." }],
      more: "+ 10 more passages",
    },
  },
  chat: {
    builder: "Builder",
    placeholder: "Reply, or pick an option…",
    lines: [
      { q: "Who is this course for?", a: "New customers and support staff" },
      { q: "What is the starting level of your learners?", options: ["Beginner", "Intermediate", "Expert"], a: "Beginner" },
      { q: "One course, or versions by level or role?", options: ["One course for everyone", "Beginner version", "Expert version"], a: "Expert version" },
    ],
  },
  brand: { source: "DESIGN.md · Figma", tokens: ["Primary", "Accent", "Surface"], font: "Aa", domain: "academy.yourcompany.com", academy: "Your academy", approved: "Theme approved as a diff" },
  draft: {
    course: "Course · Getting started with reports",
    blocks: ["Cited text", "Quiz", "Drag and drop", "Image hotspots", "Dialog cards", "Video + transcript", "Code exercise", "Certificate"],
    chip: "Product handbook § 3",
    lessonText: "Lesson text",
    modules: [
      {
        title: "Module 1 · Reports basics",
        lessons: [
          { title: "1.1 What a report shows", blocks: [1] },
          { title: "1.2 Filtering the data", blocks: [2, 5] },
        ],
      },
      {
        title: "Module 2 · Exporting and sharing",
        lessons: [
          { title: "2.1 Exporting reports", blocks: [3, 1] },
          { title: "2.2 Sharing a report", blocks: [4, 6] },
        ],
      },
    ],
    final: { title: "Final test", blocks: [1, 7] },
  },
  review: {
    lesson: "Lesson 3 · Exporting reports · 4 proposed blocks",
    items: [
      { kind: "Text", text: "Choose Export, then CSV or Excel.", state: "accept", note: "Accepted" },
      { kind: "Quiz", text: "Which formats can you export?", state: "accept", note: "Accepted" },
      { kind: "Quiz", text: "Match each format to its use.", state: "replace", note: "Replaced with a diagram" },
      { kind: "Text", text: "Exports run every night.", state: "reject", note: "Rejected · not in the source" },
    ],
    ask: "Show this as a diagram of the export flow",
    replacement: "Diagram · The export flow",
    approve: "Approve lesson",
    approved: "Approved · published",
  },
  learners: {
    base: "Base course · Exporting reports",
    versions: [
      { name: "Support team", domain: "support.yourcompany.com", meta: "Beginner · 45 min" },
      { name: "Sales team", domain: "sales.yourcompany.com", meta: "Expert · 30 min" },
      { name: "Partners", domain: "partners.yourcompany.com", meta: "Partner version · 20 min" },
    ],
    linked: "Versions kept linked across academies",
    coming: "Coming",
  },
  analytics: {
    badge: "Illustration, not live data",
    funnel: [
      { label: "Started", level: 100 },
      { label: "In progress", level: 72 },
      { label: "Finished", level: 48 },
    ],
    risk: "At risk",
    compliance: "Mandatory training",
    complianceValue: "On track",
    blocksTitle: "Per block",
    blocks: [
      { label: "Cited text", value: "92% read", level: 92 },
      { label: "Quiz", value: "71% correct", level: 71 },
      { label: "Drag and drop", value: "48% solved first try", level: 48, warn: true },
      { label: "Image hotspots", value: "88% explored", level: 88 },
      { label: "Video + transcript", value: "63% watched", level: 63 },
    ],
    attention: "Needs attention → improvement proposal",
  },
  update: {
    doc: "Product handbook",
    section: "§ 3 · Exporting",
    before: "Export reports as CSV.",
    after: "Export reports as CSV or Excel.",
    proposal: "Update proposal",
    affected: ["Lesson 3 · text", "Quiz question 4"],
    approved: "Approved",
    from: "Course v1.1",
    to: "v1.2",
    lesson: "Lesson 3 · Exporting reports",
    oldLabel: "Learners on v1.1",
    newLabel: "Learners on v1.2",
    updated: "Updated",
    keep: "Progress is kept",
  },
};

const PL: HeroJourneyCopy = {
  label: "Droga zespołu L&D z ULAMS",
  pause: "Wstrzymaj animację",
  play: "Odtwórz animację",
  previous: "Poprzedni krok",
  next: "Następny krok",
  tabs: { source: "Twoje materiały", chat: "Rozmowa", brand: "Twoja marka", draft: "Bloki kursu", review: "Przegląd", learners: "Uczący się", analytics: "Analityka", update: "Aktualizacje" },
  whyLabel: "Dlaczego to ważne",
  example: "Przykład",
  steps: {
    source: {
      title: "Zacznij od materiałów, które już masz",
      text: "Wgraj dokumenty albo podłącz Notion, Google Drive lub Git; przestrzeń Confluence trafia jako eksport. ULAMS dzieli wszystko na krótkie fragmenty, które może cytować.",
      why: "Bez pustej strony i kopiowania: eksperci piszą tam, gdzie zawsze, a kurs powstaje z tego.",
    },
    chat: {
      title: "Odpowiedz na kilka pytań w rozmowie",
      text: "Kreator pyta, dla kogo jest kurs, od jakiego poziomu zaczynają uczący się, ile ma trwać i czy potrzebujesz wersji. Odpowiedzi stają się briefem kursu.",
      why: "Kilka odpowiedzi zastępuje spotkanie briefingowe i szablon.",
    },
    brand: {
      title: "Twój design system, Twoja akademia",
      text: "Zaimportuj kolory, fonty i logo z DESIGN.md, pliku Figma albo brand booka. Motyw zatwierdzasz jako diff, a każda akademia działa jako white label na własnej subdomenie.",
      why: "Szkolenia wyglądają jak Twoja firma, a każdy kolor jest sprawdzany pod kątem kontrastu WCAG 2.2 AA przed zapisaniem.",
    },
    draft: {
      title: "Pełny kurs: moduły, lekcje i bloki",
      text: "Moduły grupują lekcje. Każda lekcja to standardowy tekst z cytowanymi fragmentami plus bloki interaktywne: quizy, przeciąganie, punkty na obrazie, karty dialogowe, wideo i ćwiczenia z kodu. Kurs kończy test końcowy i certyfikat.",
      why: "Zespół poprawia szkic ze źródłami zamiast zaczynać od zera, a recenzenci sprawdzają każde zdanie z jego fragmentem.",
    },
    review: {
      title: "Decyzja należy do Ciebie",
      text: "Każda zmiana AI przychodzi jako diff z fragmentem źródła. Zatwierdź lekcję za jednym razem, odrzuć blok albo poproś o alternatywę, na przykład diagram zamiast quizu.",
      why: "Nic nie zostaje opublikowane bez Twojej zgody, a rejestr zapisuje, kto co zatwierdził i kiedy.",
    },
    learners: {
      title: "Wersja dla każdej roli, każda we własnej akademii",
      text: "Przygotuj z tych samych źródeł wersję dla każdej roli lub poziomu i opublikuj każdą we własnej akademii, na własnej subdomenie i z własnym wyglądem. Utrzymywanie wersji powiązanych między akademiami jest w drodze.",
      why: "Wsparcie, sprzedaż i partnerzy dostają szkolenie dopasowane do siebie, bez pisania trzech kursów.",
    },
    analytics: {
      title: "Dowody dla menedżerów i audytorów",
      text: "Każdy blok raportuje własne wyniki: kto przeczytał tekst, kto odpowiedział poprawnie w quizie, ile prób zajęło przeciąganie. Dla kursu widzisz, kto utknął, kto skończył i kto zalega ze szkoleniem obowiązkowym, z eksportem do audytu.",
      why: "Widzisz, który blok poprawić, a nie tylko który kurs, a dane zostają w Twoim wdrożeniu.",
    },
    update: {
      title: "Gdy dokumenty się zmieniają, kurs idzie za nimi",
      text: "Gdy dokument się zmienia, ULAMS znajduje fragment, proponuje aktualizację lekcji i pytań, które go cytują, a po Twojej zgodzie publikuje nową wersję kursu. Uczący się widzą, co się zmieniło, i zachowują postępy.",
      why: "Przeglądasz tylko kilka lekcji, których dotyczy zmiana, zamiast sprawdzać cały kurs.",
    },
  },
  loop: { title: "Potem pętla zaczyna się od nowa", text: "Analityka uczących się pokazuje, które części są niejasne, i zamienia je w propozycję poprawki, którą przeglądasz jak każdą inną zmianę." },
  source: {
    files: ["Podręcznik produktu.pdf", "Eksport z Confluence.docx", "Strona Notion", "Folder Google Drive"],
    passages: {
      title: "13 fragmentów, każdy do zacytowania ze źródłem",
      items: [{ ref: "Podręcznik § 3", text: "Raporty można eksportować do CSV." }, { ref: "Confluence · FAQ wsparcia", text: "Klienci pytają, jak udostępnić raport." }, { ref: "Notion · Onboarding", text: "Nowi użytkownicy zaczynają od strony Raporty." }],
      more: "+ 10 kolejnych fragmentów",
    },
  },
  chat: {
    builder: "Kreator",
    placeholder: "Odpowiedz albo wybierz opcję…",
    lines: [
      { q: "Dla kogo jest ten kurs?", a: "Nowi klienci i zespół wsparcia" },
      { q: "Od jakiego poziomu zaczynają uczący się?", options: ["Początkujący", "Średniozaawansowany", "Ekspert"], a: "Początkujący" },
      { q: "Jeden kurs czy wersje według poziomu lub roli?", options: ["Jeden kurs dla wszystkich", "Wersja dla początkujących", "Wersja dla ekspertów"], a: "Wersja dla ekspertów" },
    ],
  },
  brand: { source: "DESIGN.md · Figma", tokens: ["Główny", "Akcent", "Tło"], font: "Aa", domain: "akademia.twojafirma.pl", academy: "Twoja akademia", approved: "Motyw zatwierdzony jako diff" },
  draft: {
    course: "Kurs · Pierwsze kroki z raportami",
    blocks: ["Tekst z cytatem", "Quiz", "Przeciąganie", "Punkty na obrazie", "Karty dialogowe", "Wideo + transkrypcja", "Ćwiczenie z kodu", "Certyfikat"],
    chip: "Podręcznik produktu § 3",
    lessonText: "Tekst lekcji",
    modules: [
      {
        title: "Moduł 1 · Podstawy raportów",
        lessons: [
          { title: "1.1 Co pokazuje raport", blocks: [1] },
          { title: "1.2 Filtrowanie danych", blocks: [2, 5] },
        ],
      },
      {
        title: "Moduł 2 · Eksport i udostępnianie",
        lessons: [
          { title: "2.1 Eksport raportów", blocks: [3, 1] },
          { title: "2.2 Udostępnianie raportu", blocks: [4, 6] },
        ],
      },
    ],
    final: { title: "Test końcowy", blocks: [1, 7] },
  },
  review: {
    lesson: "Lekcja 3 · Eksport raportów · 4 proponowane bloki",
    items: [
      { kind: "Tekst", text: "Wybierz Eksport, potem CSV lub Excel.", state: "accept", note: "Zatwierdzone" },
      { kind: "Quiz", text: "Do jakich formatów można eksportować?", state: "accept", note: "Zatwierdzone" },
      { kind: "Quiz", text: "Dopasuj format do zastosowania.", state: "replace", note: "Zamienione na diagram" },
      { kind: "Tekst", text: "Eksporty działają co noc.", state: "reject", note: "Odrzucone · nie ma tego w źródle" },
    ],
    ask: "Pokaż to jako diagram przebiegu eksportu",
    replacement: "Diagram · Przebieg eksportu",
    approve: "Zatwierdź lekcję",
    approved: "Zatwierdzone · opublikowane",
  },
  learners: {
    base: "Kurs bazowy · Eksport raportów",
    versions: [
      { name: "Zespół wsparcia", domain: "wsparcie.twojafirma.pl", meta: "Początkujący · 45 min" },
      { name: "Zespół sprzedaży", domain: "sprzedaz.twojafirma.pl", meta: "Ekspert · 30 min" },
      { name: "Partnerzy", domain: "partnerzy.twojafirma.pl", meta: "Wersja partnerska · 20 min" },
    ],
    linked: "Wersje powiązane między akademiami",
    coming: "Wkrótce",
  },
  analytics: {
    badge: "Ilustracja, nie dane na żywo",
    funnel: [
      { label: "Rozpoczęli", level: 100 },
      { label: "W trakcie", level: 72 },
      { label: "Ukończyli", level: 48 },
    ],
    risk: "Zagrożeni",
    compliance: "Szkolenia obowiązkowe",
    complianceValue: "Zgodnie z planem",
    blocksTitle: "Na blok",
    blocks: [
      { label: "Tekst z cytatem", value: "92% przeczytało", level: 92 },
      { label: "Quiz", value: "71% poprawnie", level: 71 },
      { label: "Przeciąganie", value: "48% za pierwszym razem", level: 48, warn: true },
      { label: "Punkty na obrazie", value: "88% zbadało", level: 88 },
      { label: "Wideo + transkrypcja", value: "63% obejrzało", level: 63 },
    ],
    attention: "Wymaga uwagi → propozycja poprawki",
  },
  update: {
    doc: "Podręcznik produktu",
    section: "§ 3 · Eksport",
    before: "Eksportuj raporty do CSV.",
    after: "Eksportuj raporty do CSV lub Excela.",
    proposal: "Propozycja aktualizacji",
    affected: ["Lekcja 3 · tekst", "Pytanie quizowe 4"],
    approved: "Zatwierdzone",
    from: "Kurs v1.1",
    to: "v1.2",
    lesson: "Lekcja 3 · Eksport raportów",
    oldLabel: "Uczący się na v1.1",
    newLabel: "Uczący się na v1.2",
    updated: "Zaktualizowane",
    keep: "Postępy zostają",
  },
};

const ZH: HeroJourneyCopy = {
  label: "培训团队使用 ULAMS 的全过程",
  pause: "暂停动画",
  play: "播放动画",
  previous: "上一步",
  next: "下一步",
  tabs: { source: "你的资料", chat: "对话", brand: "你的品牌", draft: "课程模块", review: "审阅", learners: "学员", analytics: "分析", update: "更新" },
  whyLabel: "为什么重要",
  example: "示例",
  steps: {
    source: {
      title: "从你已有的资料开始",
      text: "上传文档，或连接 Notion、Google Drive 或 Git；Confluence 空间以导出文件导入。ULAMS 把一切拆分成可以引用的短段落。",
      why: "无需从空白页开始，也无需复制粘贴：专家照常在原处写作，课程由此生成。",
    },
    chat: {
      title: "在对话中回答几个问题",
      text: "生成器询问课程对象、学员起始水平、时长以及是否需要不同版本。你的回答就是课程简报。",
      why: "几个回答就能取代一次简报会议和一份模板。",
    },
    brand: {
      title: "你的设计系统，你的学院",
      text: "从 DESIGN.md、Figma 文件或品牌手册导入颜色、字体和标志。你以差异形式批准主题，每个学院都以白标形式运行在自己的子域名上。",
      why: "培训看起来就像你的公司，每种颜色在保存前都会按 WCAG 2.2 AA 检查对比度。",
    },
    draft: {
      title: "完整的课程：模块、课时和模块化内容",
      text: "模块把课时归组。每节课由带引用段落的标准课文加上互动模块组成：测验、拖放、图片热点、对话卡片、视频和编程练习。课程以期末测验和证书结束。",
      why: "团队修改有出处的草稿，而不是从零开始；审阅者逐句对照原文段落。",
    },
    review: {
      title: "一切由你掌控",
      text: "每处 AI 修改都以差异形式呈现，并附上依据的原文段落。可以一次批准整课、拒绝某个模块，或要求替代方案，比如用图示代替测验。",
      why: "未经你批准，什么都不会发布；审计记录会写明谁在何时批准了什么。",
    },
    learners: {
      title: "每个角色一个版本，各有自己的学院",
      text: "用同样的资料为每个角色或水平起草版本，并把每个版本发布到自己的学院、自己的子域名上，使用自己的品牌外观。让各学院之间的版本保持关联的功能即将推出。",
      why: "客服、销售和合作伙伴都能得到适合自己的培训，而无需写三门课。",
    },
    analytics: {
      title: "给管理者和审计人员的证据",
      text: "每个模块都报告自己的结果：谁读了文字、谁答对了测验、拖放尝试了几次。按课程可以看到谁卡住了、谁已完成、谁还欠必修培训，并可导出审计数据。",
      why: "你看到的是该改哪个模块，而不只是哪门课；数据留在你自己的部署中。",
    },
    update: {
      title: "文档一变，课程随之更新",
      text: "文档变化时，ULAMS 找到对应段落，为引用它的课时和题目提出更新建议；你批准后，发布新版本的课程。学员能看到改动之处，并保留学习进度。",
      why: "你只需审阅受影响的几节课，而不必重新检查整门课。",
    },
  },
  loop: { title: "然后循环重新开始", text: "学习洞察显示哪些部分让人困惑，并把它们变成改进建议，你像审阅其他修改一样审阅它。" },
  source: {
    files: ["产品手册.pdf", "Confluence 导出.docx", "Notion 页面", "Google Drive 文件夹"],
    passages: {
      title: "13 个段落，每段都可连同出处引用",
      items: [{ ref: "手册 § 3", text: "报表可以导出为 CSV。" }, { ref: "Confluence · 客服 FAQ", text: "客户询问如何分享报表。" }, { ref: "Notion · 新手入门", text: "新用户从“报表”页面开始。" }],
      more: "+ 另外 10 个段落",
    },
  },
  chat: {
    builder: "生成器",
    placeholder: "回复或选择一个选项…",
    lines: [
      { q: "这门课是给谁的？", a: "新客户和客服人员" },
      { q: "学员的起始水平是什么？", options: ["初级", "中级", "专家"], a: "初级" },
      { q: "一门课，还是按水平或角色分版本？", options: ["所有人一门课", "初级版", "专家版"], a: "专家版" },
    ],
  },
  brand: { source: "DESIGN.md · Figma", tokens: ["主色", "强调色", "背景"], font: "Aa", domain: "academy.yourcompany.com", academy: "你的学院", approved: "主题以差异形式批准" },
  draft: {
    course: "课程 · 报表入门",
    blocks: ["带引用的文字", "测验", "拖放", "图片热点", "对话卡片", "视频 + 文字稿", "编程练习", "证书"],
    chip: "产品手册 § 3",
    lessonText: "课文",
    modules: [
      {
        title: "模块 1 · 报表基础",
        lessons: [
          { title: "1.1 报表展示什么", blocks: [1] },
          { title: "1.2 筛选数据", blocks: [2, 5] },
        ],
      },
      {
        title: "模块 2 · 导出与分享",
        lessons: [
          { title: "2.1 导出报表", blocks: [3, 1] },
          { title: "2.2 分享报表", blocks: [4, 6] },
        ],
      },
    ],
    final: { title: "期末测验", blocks: [1, 7] },
  },
  review: {
    lesson: "第 3 课 · 导出报表 · 4 个建议模块",
    items: [
      { kind: "文字", text: "选择“导出”，然后选 CSV 或 Excel。", state: "accept", note: "已接受" },
      { kind: "测验", text: "可以导出哪些格式？", state: "accept", note: "已接受" },
      { kind: "测验", text: "把每种格式与其用途配对。", state: "replace", note: "已替换为图示" },
      { kind: "文字", text: "导出每晚自动运行。", state: "reject", note: "已拒绝 · 原文中没有" },
    ],
    ask: "把这部分做成导出流程的图示",
    replacement: "图示 · 导出流程",
    approve: "批准本课",
    approved: "已批准 · 已发布",
  },
  learners: {
    base: "基础课程 · 导出报表",
    versions: [
      { name: "客服团队", domain: "support.yourcompany.com", meta: "初级 · 45 分钟" },
      { name: "销售团队", domain: "sales.yourcompany.com", meta: "专家 · 30 分钟" },
      { name: "合作伙伴", domain: "partners.yourcompany.com", meta: "合作伙伴版 · 20 分钟" },
    ],
    linked: "各学院之间的版本保持关联",
    coming: "即将推出",
  },
  analytics: {
    badge: "示意图，非实时数据",
    funnel: [
      { label: "已开始", level: 100 },
      { label: "学习中", level: 72 },
      { label: "已完成", level: 48 },
    ],
    risk: "有掉队风险",
    compliance: "必修培训",
    complianceValue: "按计划进行",
    blocksTitle: "按模块",
    blocks: [
      { label: "带引用的文字", value: "92% 已读", level: 92 },
      { label: "测验", value: "71% 答对", level: 71 },
      { label: "拖放", value: "48% 一次完成", level: 48, warn: true },
      { label: "图片热点", value: "88% 已探索", level: 88 },
      { label: "视频 + 文字稿", value: "63% 已观看", level: 63 },
    ],
    attention: "需要关注 → 改进建议",
  },
  update: {
    doc: "产品手册",
    section: "§ 3 · 导出",
    before: "将报表导出为 CSV。",
    after: "将报表导出为 CSV 或 Excel。",
    proposal: "更新建议",
    affected: ["第 3 课 · 文字", "测验题 4"],
    approved: "已批准",
    from: "课程 v1.1",
    to: "v1.2",
    lesson: "第 3 课 · 导出报表",
    oldLabel: "v1.1 的学员",
    newLabel: "v1.2 的学员",
    updated: "已更新",
    keep: "学习进度保留",
  },
};

const COPY: Record<Locale, HeroJourneyCopy> = { en: EN, pl: PL, zh: ZH };

export const heroJourneyCopy = (locale: Locale): HeroJourneyCopy => COPY[locale];
