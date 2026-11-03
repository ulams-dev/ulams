/**
 * The animated story of the platform home page: how ULAMS works end to end, in six steps and a loop, with what each
 * step means for an L&D team's budget and work. Written for instructors, instructional designers and L&D managers.
 * Copy in English, Polish and Chinese; the story is placed right after the hero by `planHomeBody`, before the two
 * call-to-action blocks of the page document.
 *
 * Figures: only the one measured run, identical to the "faster courses" block that follows the story (which also
 * carries the cited Chapman range and the links to the detail pages). The pictures are labelled examples or
 * illustrations. Status follows docs/ROADMAP-TODO.md:
 * course variants are drafted and reviewed in the builder (CV-4, merged); routing learners to a variant is coming.
 */
import type { UiNode } from "@ulams/ui/render-core";
import type { Locale } from "../i18n/locales.ts";

export const STORY_ID = "how-it-works";

export interface StoryStep {
  /** Anchor of the step, linked from the step list at the top. */
  id: string;
  /** Short name in the step list. */
  label: string;
  title: string;
  text: string;
  /** What the step means for the budget and the work of an L&D team. */
  why: string;
  /** Text alternative of the picture; the step text above it carries the full meaning. */
  alt: string;
}

export interface HomeStoryCopy {
  eyebrow: string;
  title: string;
  lede: string;
  stepsLabel: string;
  whyLabel: string;
  example: string;
  replay: string;
  sources: StoryStep & { items: string[]; passages: string };
  draft: StoryStep & {
    block: { lesson: string; line: string; chip: string };
    run: { badge: string; facts: Array<{ value: string; label: string }>; note: string };
  };
  review: StoryStep & { card: { title: string; removed: string; added: string; reject: string; accept: string; approved: string } };
  living: StoryStep & {
    card: { doc: string; section: string; line: string; before: string; after: string; flagged: string[]; flag: string; proposal: string; waiting: string };
  };
  learners: StoryStep & {
    status: Array<{ label: string; state: "available" | "coming" }>;
    card: { base: string; variants: string[]; learners: string; routing: string };
  };
  evidence: StoryStep & {
    badge: string;
    card: { title: string; rows: Array<{ label: string; tone: "ok" | "muted" | "warn" | "bad"; level: number }>; export: string };
  };
  again: { title: string; text: string };
}

/** The steps in story order, for the step list and the tests. */
export const STEP_KEYS = ["sources", "draft", "review", "living", "learners", "evidence"] as const;
export type StepKey = (typeof STEP_KEYS)[number];

/** Bar lengths of the analytics illustration: shapes only, never shown as numbers. */
const LEVELS = [72, 46, 18, 10] as const;

const EN: HomeStoryCopy = {
  eyebrow: "How it works",
  title: "From your documents to evidence of learning",
  lede: "Six steps and a loop, written for L&D teams. Each step shows what happens and why it matters for your budget and your work.",
  stepsLabel: "The six steps",
  whyLabel: "Why it matters",
  example: "Example",
  replay: "Replay the story",
  sources: {
    id: "how-sources",
    label: "Your sources",
    title: "Start from the material you already have",
    text: "Connect a Notion page, a Git repository or Google Drive, or upload policies and product docs as PDF, Word, Markdown or plain text. ULAMS splits them into short passages it can quote.",
    why: "No blank page and no copy-and-paste: experts keep their knowledge where they already write it, and the course is built from that.",
    alt: "Example: five sources, from a policy PDF to Google Drive, become passages that can be cited.",
    items: ["Privacy policy (PDF)", "Product docs", "Notion page", "Git repository", "Google Drive"],
    passages: "Passages ready to cite",
  },
  draft: {
    id: "how-draft",
    label: "Cited draft",
    title: "A cited course draft in minutes",
    text: "AI drafts the outline, the lessons and the quiz questions. Every block links to the passage it is based on, and claims the source does not support are flagged.",
    why: "Far less authoring effort: your team edits a sourced draft instead of starting from zero, and reviewers check each statement against its passage.",
    alt: "Example: a lesson block with a citation chip that points to section 4 of the privacy policy.",
    block: { lesson: "Lesson 2 · Handling customer data", line: "Customer exports are deleted after 30 days.", chip: "Privacy policy § 4" },
    run: {
      badge: "One measured run · draft for your review",
      facts: [
        { value: "13", label: "passages read" },
        { value: "6", label: "lessons" },
        { value: "18", label: "quiz questions" },
        { value: "35/35", label: "blocks cite the source" },
        { value: "7 min 44 s", label: "of generation" },
        { value: "USD 0.28", label: "model cost" },
      ],
      note: "One run on a public docs repository. Generation time only; your review comes on top.",
    },
  },
  review: {
    id: "how-review",
    label: "Your review",
    title: "You stay in control",
    text: "Every AI change arrives as a reviewable diff: what is removed, what is added and the passage behind it. Accept it all, accept element by element, or reject it. Nothing is published without your approval.",
    why: "Accountability stays with your team. The audit trail records who approved what, when, and against which version of the source.",
    alt: "Example: a proposed change shown as a removed line and an added line, with Accept and Reject buttons; it is published only after approval.",
    card: {
      title: "Proposed change · Lesson 2",
      removed: "Delete exports when a project ends.",
      added: "Delete customer exports after 30 days.",
      reject: "Reject",
      accept: "Accept",
      approved: "Approved by you · published",
    },
  },
  living: {
    id: "how-living",
    label: "Stays current",
    title: "When a source changes, the course follows",
    text: "ULAMS notices the change down to the passage, flags the lessons and questions that quote it, and prepares an update proposal that waits for your review. Learners keep their progress.",
    why: "Lower maintenance cost: you review the few lessons a change touches instead of re-checking the whole course, and outdated training does not stay online unnoticed.",
    alt: "Example: the privacy policy changes from 30 to 14 days; two course elements that cite it are flagged and an update proposal waits for review.",
    card: {
      doc: "Privacy policy · v2",
      section: "§ 4 Customer data",
      line: "Delete customer exports after",
      before: "30 days",
      after: "14 days",
      flagged: ["Lesson 2", "Quiz question 5"],
      flag: "Needs update",
      proposal: "Update proposal",
      waiting: "Waiting for your review",
    },
  },
  learners: {
    id: "how-learners",
    label: "Learners",
    title: "One course, or a version for each level and role",
    text: "Publish one course to your academy, or draft versions for beginners, experienced staff or a specific role from the same sources. Each version is reviewed as a diff against the base course.",
    why: "Training fits the audience without writing a separate course for every group.",
    alt: "Example: one base course branches into versions for beginners, experts and a sales team; the link that routes each learner to a version is dashed because it is coming.",
    status: [
      { label: "Versions by level and role in the course builder", state: "available" },
      { label: "Routing each learner to the right version", state: "coming" },
    ],
    card: { base: "Base course", variants: ["Beginner", "Expert", "Sales team"], learners: "Learners", routing: "Routing" },
  },
  evidence: {
    id: "how-evidence",
    label: "Evidence",
    title: "Evidence for managers and auditors",
    text: "Per-course analytics show who is stuck, who finished and which quiz questions trip people up. Compliance reports show who still owes mandatory training, with an audit export.",
    why: "Reports answer managers' questions without spreadsheets. The data stays in your own deployment: open source under MIT, with SCORM, cmi5, xAPI and LTI 1.3 built in, so there is no lock-in.",
    alt: "Illustration of a course analytics panel: finished, in progress, needs support and overdue mandatory training, with an audit export. Not real data.",
    badge: "Illustration, not real data",
    card: {
      title: "Course analytics",
      rows: [
        { label: "Finished", tone: "ok", level: LEVELS[0] },
        { label: "In progress", tone: "muted", level: LEVELS[1] },
        { label: "Needs support", tone: "warn", level: LEVELS[2] },
        { label: "Mandatory training overdue", tone: "bad", level: LEVELS[3] },
      ],
      export: "Audit export · CSV",
    },
  },
  again: {
    title: "Then the loop starts again",
    text: "Learner insights show which sections confuse people and can turn them into an improvement proposal, which you review like any other change.",
  },
};

const PL: HomeStoryCopy = {
  eyebrow: "Jak to działa",
  title: "Od Twoich dokumentów do dowodów nauki",
  lede: "Sześć kroków i pętla, opisane dla zespołów L&D. Każdy krok pokazuje, co się dzieje i dlaczego ma to znaczenie dla budżetu i Twojej pracy.",
  stepsLabel: "Sześć kroków",
  whyLabel: "Dlaczego to ważne",
  example: "Przykład",
  replay: "Odtwórz historię ponownie",
  sources: {
    id: "how-sources",
    label: "Twoje źródła",
    title: "Zacznij od materiałów, które już masz",
    text: "Podłącz stronę Notion, repozytorium Git lub Google Drive albo wgraj polityki i dokumentację produktu jako PDF, Word, Markdown lub zwykły tekst. ULAMS dzieli je na krótkie fragmenty, które może cytować.",
    why: "Bez pustej kartki i kopiowania: eksperci trzymają wiedzę tam, gdzie już ją piszą, a kurs powstaje właśnie z niej.",
    alt: "Przykład: pięć źródeł, od PDF z polityką po Google Drive, zamienia się we fragmenty, które można cytować.",
    items: ["Polityka prywatności (PDF)", "Dokumentacja produktu", "Strona Notion", "Repozytorium Git", "Google Drive"],
    passages: "Fragmenty gotowe do cytowania",
  },
  draft: {
    id: "how-draft",
    label: "Szkic z cytatami",
    title: "Szkic kursu z cytatami w kilka minut",
    text: "AI przygotowuje konspekt, lekcje i pytania quizowe. Każdy blok wskazuje fragment, na którym się opiera, a twierdzenia bez oparcia w źródle są oznaczane.",
    why: "Znacznie mniej pracy autorskiej: zespół poprawia szkic ze źródłami zamiast zaczynać od zera, a recenzent sprawdza każde zdanie przy jego fragmencie.",
    alt: "Przykład: blok lekcji z etykietą cytatu, która wskazuje § 4 polityki prywatności.",
    block: { lesson: "Lekcja 2 · Dane klientów", line: "Eksporty danych klientów usuwamy po 30 dniach.", chip: "Polityka prywatności § 4" },
    run: {
      badge: "Jeden zmierzony przebieg · szkic do Twojego przeglądu",
      facts: [
        { value: "13", label: "przeczytanych fragmentów" },
        { value: "6", label: "lekcji" },
        { value: "18", label: "pytań quizowych" },
        { value: "35/35", label: "bloków cytuje źródło" },
        { value: "7 min 44 s", label: "generowania" },
        { value: "0,28 USD", label: "koszt modelu" },
      ],
      note: "Jeden przebieg na publicznym repozytorium dokumentacji. Tylko czas generowania; Twój przegląd dochodzi do tego.",
    },
  },
  review: {
    id: "how-review",
    label: "Twój przegląd",
    title: "Decyzja zostaje po Twojej stronie",
    text: "Każda zmiana AI przychodzi jako diff do przejrzenia: co znika, co dochodzi i na jakim fragmencie się opiera. Przyjmij całość, przyjmij element po elemencie albo odrzuć. Nic nie zostaje opublikowane bez Twojej zgody.",
    why: "Odpowiedzialność zostaje w Twoim zespole. Ślad audytowy zapisuje, kto co zatwierdził, kiedy i względem której wersji źródła.",
    alt: "Przykład: proponowana zmiana jako usunięty i dodany wiersz, z przyciskami Przyjmij i Odrzuć; publikacja następuje dopiero po zatwierdzeniu.",
    card: {
      title: "Proponowana zmiana · Lekcja 2",
      removed: "Usuwaj eksporty po zakończeniu projektu.",
      added: "Usuwaj eksporty danych klientów po 30 dniach.",
      reject: "Odrzuć",
      accept: "Przyjmij",
      approved: "Zatwierdzone przez Ciebie · opublikowane",
    },
  },
  living: {
    id: "how-living",
    label: "Zawsze aktualny",
    title: "Gdy źródło się zmienia, kurs idzie za nim",
    text: "ULAMS wykrywa zmianę aż do fragmentu, oznacza lekcje i pytania, które go cytują, i przygotowuje propozycję aktualizacji, która czeka na Twój przegląd. Uczący się zachowują postęp.",
    why: "Niższy koszt utrzymania: przeglądasz kilka lekcji, których dotyczy zmiana, zamiast sprawdzać cały kurs, a nieaktualne szkolenie nie zostaje w obiegu niezauważone.",
    alt: "Przykład: polityka prywatności zmienia 30 dni na 14; dwa elementy kursu, które ją cytują, są oznaczone, a propozycja aktualizacji czeka na przegląd.",
    card: {
      doc: "Polityka prywatności · v2",
      section: "§ 4 Dane klientów",
      line: "Usuwaj eksporty danych klientów po",
      before: "30 dniach",
      after: "14 dniach",
      flagged: ["Lekcja 2", "Pytanie quizowe 5"],
      flag: "Do aktualizacji",
      proposal: "Propozycja aktualizacji",
      waiting: "Czeka na Twój przegląd",
    },
  },
  learners: {
    id: "how-learners",
    label: "Uczący się",
    title: "Jeden kurs albo wersja dla każdego poziomu i roli",
    text: "Opublikuj jeden kurs w akademii albo przygotuj z tych samych źródeł wersje dla początkujących, doświadczonych lub konkretnej roli. Każdą wersję przeglądasz jako diff względem kursu bazowego.",
    why: "Szkolenie pasuje do odbiorców bez pisania osobnego kursu dla każdej grupy.",
    alt: "Przykład: jeden kurs bazowy rozgałęzia się na wersje dla początkujących, ekspertów i zespołu sprzedaży; połączenie kierujące uczących się do wersji jest przerywane, bo ta funkcja będzie dostępna wkrótce.",
    status: [
      { label: "Wersje według poziomu i roli w kreatorze kursu", state: "available" },
      { label: "Kierowanie każdej osoby do właściwej wersji", state: "coming" },
    ],
    card: { base: "Kurs bazowy", variants: ["Początkujący", "Ekspert", "Zespół sprzedaży"], learners: "Uczący się", routing: "Kierowanie" },
  },
  evidence: {
    id: "how-evidence",
    label: "Dowody",
    title: "Dowody dla menedżerów i audytorów",
    text: "Analityka każdego kursu pokazuje, kto utknął, kto skończył i na których pytaniach ludzie się mylą. Raporty zgodności pokazują, kto jeszcze nie odbył obowiązkowego szkolenia, z eksportem do audytu.",
    why: "Raporty odpowiadają na pytania menedżerów bez arkuszy kalkulacyjnych. Dane zostają w Twoim wdrożeniu: open source na licencji MIT, z wbudowanym SCORM, cmi5, xAPI i LTI 1.3, więc bez uzależnienia od dostawcy.",
    alt: "Ilustracja panelu analityki kursu: ukończyli, w trakcie, potrzebują wsparcia i zaległe szkolenie obowiązkowe, z eksportem audytu. To nie są prawdziwe dane.",
    badge: "Ilustracja, nie prawdziwe dane",
    card: {
      title: "Analityka kursu",
      rows: [
        { label: "Ukończyli", tone: "ok", level: LEVELS[0] },
        { label: "W trakcie", tone: "muted", level: LEVELS[1] },
        { label: "Potrzebują wsparcia", tone: "warn", level: LEVELS[2] },
        { label: "Zaległe szkolenie obowiązkowe", tone: "bad", level: LEVELS[3] },
      ],
      export: "Eksport audytu · CSV",
    },
  },
  again: {
    title: "Potem pętla zaczyna się od nowa",
    text: "Learner insights pokazują, które fragmenty mylą uczących się, i mogą zamienić je w propozycję poprawki, którą przeglądasz jak każdą inną zmianę.",
  },
};

const ZH: HomeStoryCopy = {
  eyebrow: "工作原理",
  title: "从你的文档到学习成效的证据",
  lede: "六个步骤加一个循环，专为培训与学习发展（L&D）团队撰写。每一步都说明发生了什么，以及它对预算和你的工作意味着什么。",
  stepsLabel: "六个步骤",
  whyLabel: "为什么重要",
  example: "示例",
  replay: "重新播放",
  sources: {
    id: "how-sources",
    label: "你的资料",
    title: "从你已有的资料开始",
    text: "连接 Notion 页面、Git 仓库或 Google Drive，或者上传 PDF、Word、Markdown 或纯文本格式的制度与产品文档。ULAMS 会把它们拆分成可以引用的短片段。",
    why: "无需从零开始，也无需复制粘贴：专家继续在原来的地方编写知识，课程就从这些资料生成。",
    alt: "示例：从隐私政策 PDF 到 Google Drive 的五个资料来源，被拆分为可引用的片段。",
    items: ["隐私政策（PDF）", "产品文档", "Notion 页面", "Git 仓库", "Google Drive"],
    passages: "可引用的片段",
  },
  draft: {
    id: "how-draft",
    label: "带出处的草稿",
    title: "几分钟得到带出处的课程草稿",
    text: "AI 起草大纲、课时和测验题。每个内容块都链接到它所依据的片段，资料不支持的说法会被标出。",
    why: "大幅减少编写工作量：团队修改有出处的草稿，而不是从零开始；审阅者可以对照片段逐句核查。",
    alt: "示例：一个课时内容块带有出处标签，指向隐私政策第 4 节。",
    block: { lesson: "第 2 课 · 处理客户数据", line: "客户导出数据在 30 天后删除。", chip: "隐私政策 § 4" },
    run: {
      badge: "一次实测 · 草稿，待你审阅",
      facts: [
        { value: "13", label: "个片段已读取" },
        { value: "6", label: "个课时" },
        { value: "18", label: "道测验题" },
        { value: "35/35", label: "个内容块引用了资料" },
        { value: "7 分 44 秒", label: "生成时间" },
        { value: "0.28 美元", label: "模型成本" },
      ],
      note: "在一个公开文档仓库上的一次运行。仅含生成时间；你的审阅另需时间。",
    },
  },
  review: {
    id: "how-review",
    label: "你的审阅",
    title: "掌控权始终在你手中",
    text: "AI 的每项改动都以可审阅的差异呈现：删除了什么、新增了什么，以及依据的片段。你可以全部接受、逐项接受或拒绝。未经你批准，任何内容都不会发布。",
    why: "责任留在你的团队。审计记录会保存谁在何时批准了什么，以及对应的资料版本。",
    alt: "示例：改动提议以删除的一行和新增的一行显示，并带有接受和拒绝按钮；只有批准后才会发布。",
    card: {
      title: "改动提议 · 第 2 课",
      removed: "项目结束时删除导出数据。",
      added: "客户导出数据在 30 天后删除。",
      reject: "拒绝",
      accept: "接受",
      approved: "已由你批准 · 已发布",
    },
  },
  living: {
    id: "how-living",
    label: "保持最新",
    title: "资料变化时，课程随之更新",
    text: "ULAMS 能精确到片段地发现变化，标出引用该片段的课时和题目，并准备一份更新提议等待你审阅。学员的学习进度会保留。",
    why: "降低维护成本：你只需审阅受影响的少数课时，而不必重新检查整门课程；过时的培训内容也不会在无人察觉的情况下继续上线。",
    alt: "示例：隐私政策从 30 天改为 14 天；引用它的两个课程元素被标出，一份更新提议等待审阅。",
    card: {
      doc: "隐私政策 · v2",
      section: "§ 4 客户数据",
      line: "客户导出数据的删除期限：",
      before: "30 天",
      after: "14 天",
      flagged: ["第 2 课", "测验题 5"],
      flag: "需要更新",
      proposal: "更新提议",
      waiting: "等待你审阅",
    },
  },
  learners: {
    id: "how-learners",
    label: "学员",
    title: "一门课程，或按级别和角色提供不同版本",
    text: "把一门课程发布到你的学院，或者基于同一批资料，为初学者、有经验的员工或特定角色起草不同版本。每个版本都以与基础课程的差异形式审阅。",
    why: "培训内容贴合学员，而无需为每个群体单独编写一门课程。",
    alt: "示例：一门基础课程分出面向初学者、专家和销售团队的版本；把学员分配到版本的连线为虚线，因为该功能即将推出。",
    status: [
      { label: "课程构建器中按级别和角色生成版本", state: "available" },
      { label: "把每位学员分配到合适的版本", state: "coming" },
    ],
    card: { base: "基础课程", variants: ["初学者", "专家", "销售团队"], learners: "学员", routing: "分配" },
  },
  evidence: {
    id: "how-evidence",
    label: "证据",
    title: "给管理者和审计人员的证据",
    text: "按课程的分析显示谁卡住了、谁学完了，以及哪些测验题让人出错。合规报表显示谁还没有完成必修培训，并支持审计导出。",
    why: "报表直接回答管理者的问题，无需电子表格。数据保留在你自己的部署中：MIT 许可的开源软件，内置 SCORM、cmi5、xAPI 和 LTI 1.3，不会被供应商锁定。",
    alt: "课程分析面板示意图：已学完、学习中、需要支持和必修培训已逾期，并带有审计导出。并非真实数据。",
    badge: "示意图，非真实数据",
    card: {
      title: "课程分析",
      rows: [
        { label: "已学完", tone: "ok", level: LEVELS[0] },
        { label: "学习中", tone: "muted", level: LEVELS[1] },
        { label: "需要支持", tone: "warn", level: LEVELS[2] },
        { label: "必修培训已逾期", tone: "bad", level: LEVELS[3] },
      ],
      export: "审计导出 · CSV",
    },
  },
  again: {
    title: "然后，循环再次开始",
    text: "学习洞察会显示哪些章节让学员困惑，并可以把它们转化为改进提议，由你像审阅其他改动一样审阅。",
  },
};

const COPY: Record<Locale, HomeStoryCopy> = { en: EN, pl: PL, zh: ZH };

/** The story in one language. */
export const homeStory = (locale: Locale): HomeStoryCopy => COPY[locale];

export type HomePart = { kind: "node"; node: UiNode } | { kind: "story" } | { kind: "walkthrough" } | { kind: "style-guide" } | { kind: "learning" };

/** Id of the white-label band of the home document; the style-guide section follows it. */
export const WHITE_LABEL_ID = "white-label";

export interface HomeBodyPlan {
  /** Nodes before the main landmark (the site header). */
  before: UiNode[];
  /** Children of the main landmark, with the builder walkthrough after the advantages grid. */
  main: HomePart[];
  /** Nodes after the main landmark (the site footer). */
  after: UiNode[];
}

/** Id of the advantages grid on the home page; the builder walkthrough follows it. */
export const ADVANTAGES_ID = "advantages";

/**
 * Splits the home document around its main landmark and places the builder walkthrough right after the advantages grid
 * (right after the hero when the grid is missing, first when both are). The how-it-works story, the learning elements
 * and the style guide have their own pages; the hero journey and the advantages link to them, so the home page does
 * not repeat them. Returns null for a document without a Page > Main structure, which is then rendered as it is.
 */
export function planHomeBody(doc: UiNode): HomeBodyPlan | null {
  const children = doc.component === "Page" ? (doc.children ?? []) : [];
  const mainIndex = children.findIndex((node) => node.component === "Main");
  if (mainIndex < 0) return null;
  const sections = children[mainIndex]!.children ?? [];
  const main: HomePart[] = sections.map((node) => ({ kind: "node", node }));
  const advantages = sections.findIndex((node) => node.props?.id === ADVANTAGES_ID);
  const anchor = advantages >= 0 ? advantages : sections.findIndex((node) => node.component === "Hero");
  main.splice(anchor + 1, 0, { kind: "walkthrough" });
  return { before: children.slice(0, mainIndex), main, after: children.slice(mainIndex + 1) };
}
