/**
 * Strings of "Propose an improvement" (ADR 0181) in English, Polish and Simplified Chinese: the button and side panel of
 * the studio Insights page, the evidence card ("Why this was proposed") and the few improvement-specific texts of
 * the proposal review screen. Same languages, glossary and placeholder rules as `insights-i18n.ts`; the language is the
 * one the page was opened in (`?lang=`, else the browser's, else English).
 */
import type { InsightsLocale } from "./insights-i18n.ts";

export interface ImproveStrings {
  htmlLang: string;
  colAction: string;
  button: string;
  panelTitle: string;
  panelCaption: string;
  checking: string;
  evidenceTitle: string;
  evidenceIntro: string;
  evidenceAria: string;
  figStruggle: string;
  figHelp: string;
  figRecovery: string;
  figHelpful: string;
  figTutor: string;
  noFigure: string;
  reasonsTitle: string;
  noReasons: string;
  wrongTitle: string;
  wrongIntro: string;
  wrongOption: string;
  wrongShare: string;
  wrongScroll: string;
  wrongOther: string;
  sentTitle: string;
  sentText: string;
  estimate: string;
  confirm: string;
  cancel: string;
  creating: string;
  created: string;
  createdReview: string;
  existing: string;
  existingReview: string;
  statusAnalysing: string;
  statusReady: string;
  statusAwaiting: string;
  close: string;
  errSmallGroup: string;
  errDailyLimit: string;
  errAiOff: string;
  errBudget: string;
  errNotBuilder: string;
  errNotImprovable: string;
  errNoSources: string;
  errNoCourse: string;
  errForbidden: string;
  errGeneric: string;
  /** Review screen */
  reviewHeading: string;
  reviewIntro: string;
  reviewSub: string;
  badge: string;
  listHeading: string;
  appliedTitle: string;
  appliedText: string;
  rejectedTitle: string;
  rejectedText: string;
  rejectTitle: string;
  rejectText: string;
  reasons: Record<string, string>;
}

const en: ImproveStrings = {
  htmlLang: "en",
  colAction: "Action",
  button: "Propose an improvement",
  panelTitle: "Improve “{label}”",
  panelCaption: "An AI suggestion for this section, based on how learners did",
  checking: "Checking what would be sent…",
  evidenceTitle: "Why this was proposed",
  evidenceIntro: "Results of {learners} learners in the last {days} days, as numbers. No individual learner is shown here, and none is sent to the AI.",
  evidenceAria: "Learner evidence in numbers",
  figStruggle: "Struggled here",
  figHelp: "Were offered extra help",
  figRecovery: "Recovered after help",
  figHelpful: "Found the help useful",
  figTutor: "Tutor questions declined as off-topic (whole course)",
  noFigure: "Not enough data",
  reasonsTitle: "Most common reasons",
  noReasons: "No single reason stands out.",
  wrongTitle: "Wrong answers learners chose",
  wrongIntro: "Share of the learners active here who picked each wrong option. Options chosen by fewer than 5 learners are grouped.",
  wrongOption: "Answer",
  wrongShare: "Share of learners",
  wrongScroll: "Wrong answers (scrolls sideways on small screens)",
  wrongOther: "Other wrong answers",
  sentTitle: "What the AI receives",
  sentText: "The text and quiz answers of this section, the source passages it cites, the objectives of the lesson, and the numbers above. It proposes a change; nothing in the course changes until you accept and apply it.",
  estimate: "Estimated cost: about {cost}. It counts against this course’s monthly AI budget.",
  confirm: "Create the proposal",
  cancel: "Cancel",
  creating: "Creating the proposal and asking the AI…",
  created: "Proposal {number} was created ({status}).",
  createdReview: "Review the proposal",
  existing: "There is already an open proposal for this section (proposal {number}).",
  existingReview: "Open it",
  statusAnalysing: "the AI is working on it",
  statusReady: "ready for your review",
  statusAwaiting: "waiting to be analysed",
  close: "Close",
  errSmallGroup: "Fewer than {n} learners were active here in this period, so no improvement can be proposed from their results.",
  errDailyLimit: "This course already has its limit of improvement proposals for today. Review them, or try again tomorrow.",
  errAiOff: "AI is switched off on this installation. Edit the section by hand in the workspace.",
  errBudget: "The AI budget of this course is used up. The proposal was kept; try again later or edit the section by hand.",
  errNotBuilder: "This section was not built with the Course Builder, so it cannot be improved from here. Edit it by hand instead.",
  errNotImprovable: "Only lessons, text blocks and quiz questions can be improved from learner insights.",
  errNoSources: "This section cites no passage of your source material, so an improvement could not be grounded in it. Edit it by hand in the workspace.",
  errNoCourse: "This course has no approved version yet, so there is nothing to improve.",
  errForbidden: "Only the author of this course, or someone who reviews Living Course updates, may propose improvements.",
  errGeneric: "The proposal could not be created. Try again in a moment.",
  reviewHeading: "Improvement from learner insights",
  reviewIntro: "Learners struggle with this part of the course. The AI suggests a clearer version from your source material. Review the change; nothing in the course changes until you accept and apply it.",
  reviewSub: "Proposal {number}",
  badge: "From learner insights",
  listHeading: "Updates and improvements",
  appliedTitle: "The improvement is applied",
  appliedText: "Your course now includes the change. Learners keep their progress.",
  rejectedTitle: "You kept the course as it was",
  rejectedText: "Nothing in the course changed. The source was not affected.",
  rejectTitle: "Reject the whole improvement?",
  rejectText: "This keeps your course exactly as it is. You can propose a new improvement from the Insights page later.",
  reasons: {
    failed_attempts: "repeated failed attempts",
    slow_element: "taking much longer than most",
    abandoned_element: "leaving it open while working elsewhere",
    repetition: "replays or revisits",
    inactivity: "going inactive",
    struggling_elements: "several hard parts at once",
  },
};

const pl: ImproveStrings = {
  htmlLang: "pl",
  colAction: "Działanie",
  button: "Zaproponuj poprawkę",
  panelTitle: "Popraw „{label}”",
  panelCaption: "Propozycja AI dla tego fragmentu, na podstawie wyników kursantów",
  checking: "Sprawdzam, co zostałoby wysłane…",
  evidenceTitle: "Dlaczego to zaproponowano",
  evidenceIntro: "Wyniki {learners} kursantów z ostatnich {days} dni, jako liczby. Żaden kursant nie jest tu pokazany ani wysyłany do AI.",
  evidenceAria: "Wyniki kursantów w liczbach",
  figStruggle: "Mieli tu trudności",
  figHelp: "Dostali dodatkową pomoc",
  figRecovery: "Poradzili sobie po pomocy",
  figHelpful: "Uznali pomoc za przydatną",
  figTutor: "Pytania do tutora odrzucone jako nie na temat (cały kurs)",
  noFigure: "Za mało danych",
  reasonsTitle: "Najczęstsze powody",
  noReasons: "Żaden powód się nie wyróżnia.",
  wrongTitle: "Błędne odpowiedzi wybierane przez kursantów",
  wrongIntro: "Odsetek aktywnych tu kursantów, którzy wybrali daną błędną opcję. Opcje wybrane przez mniej niż 5 osób są zgrupowane.",
  wrongOption: "Odpowiedź",
  wrongShare: "Odsetek kursantów",
  wrongScroll: "Błędne odpowiedzi (przewijane w bok na małych ekranach)",
  wrongOther: "Pozostałe błędne odpowiedzi",
  sentTitle: "Co otrzymuje AI",
  sentText: "Tekst i odpowiedzi quizu z tego fragmentu, cytowane przez niego fragmenty źródła, cele lekcji oraz powyższe liczby. AI proponuje zmianę; nic w kursie się nie zmieni, dopóki jej nie zatwierdzisz i nie zastosujesz.",
  estimate: "Szacowany koszt: około {cost}. Wlicza się do miesięcznego budżetu AI tego kursu.",
  confirm: "Utwórz propozycję",
  cancel: "Anuluj",
  creating: "Tworzę propozycję i pytam AI…",
  created: "Utworzono propozycję {number} ({status}).",
  createdReview: "Przejrzyj propozycję",
  existing: "Dla tego fragmentu jest już otwarta propozycja (propozycja {number}).",
  existingReview: "Otwórz ją",
  statusAnalysing: "AI nad nią pracuje",
  statusReady: "gotowa do przeglądu",
  statusAwaiting: "czeka na analizę",
  close: "Zamknij",
  errSmallGroup: "W tym okresie aktywnych było mniej niż {n} kursantów, więc nie można zaproponować poprawki na podstawie ich wyników.",
  errDailyLimit: "Ten kurs wykorzystał dziś limit propozycji poprawek. Przejrzyj je albo spróbuj jutro.",
  errAiOff: "AI jest wyłączone w tej instalacji. Edytuj fragment ręcznie w obszarze roboczym.",
  errBudget: "Budżet AI tego kursu został wykorzystany. Propozycja została zachowana; spróbuj później albo edytuj fragment ręcznie.",
  errNotBuilder: "Ten fragment nie powstał w kreatorze kursów, więc nie można go stąd poprawić. Edytuj go ręcznie.",
  errNotImprovable: "Na podstawie wyników kursantów można poprawiać tylko lekcje, bloki tekstu i pytania quizu.",
  errNoSources: "Ten fragment nie cytuje żadnego fragmentu Twojego źródła, więc poprawka nie miałaby oparcia w źródle. Edytuj go ręcznie w obszarze roboczym.",
  errNoCourse: "Ten kurs nie ma jeszcze zatwierdzonej wersji, więc nie ma czego poprawiać.",
  errForbidden: "Poprawki może proponować tylko autor kursu lub osoba, która przegląda aktualizacje Living Course.",
  errGeneric: "Nie udało się utworzyć propozycji. Spróbuj za chwilę.",
  reviewHeading: "Poprawka na podstawie wyników kursantów",
  reviewIntro: "Kursanci mają trudności z tą częścią kursu. AI proponuje jaśniejszą wersję na podstawie Twojego źródła. Przejrzyj zmianę; nic w kursie się nie zmieni, dopóki jej nie zatwierdzisz i nie zastosujesz.",
  reviewSub: "Propozycja {number}",
  badge: "Z wyników kursantów",
  listHeading: "Aktualizacje i poprawki",
  appliedTitle: "Poprawka została zastosowana",
  appliedText: "Kurs zawiera już tę zmianę. Kursanci zachowują swój postęp.",
  rejectedTitle: "Kurs został bez zmian",
  rejectedText: "Nic w kursie się nie zmieniło. Źródło nie zostało naruszone.",
  rejectTitle: "Odrzucić całą poprawkę?",
  rejectText: "Kurs zostanie dokładnie taki, jaki jest. Nową poprawkę możesz zaproponować później na stronie Wgląd w kurs.",
  reasons: {
    failed_attempts: "powtarzane nieudane próby",
    slow_element: "dużo dłuższy czas niż u większości",
    abandoned_element: "zostawienie otwartego i nauka gdzie indziej",
    repetition: "powtórne odtwarzanie lub powroty",
    inactivity: "przerwanie nauki",
    struggling_elements: "kilka trudnych fragmentów naraz",
  },
};

const zh: ImproveStrings = {
  htmlLang: "zh-Hans",
  colAction: "操作",
  button: "提出改进建议",
  panelTitle: "改进“{label}”",
  panelCaption: "根据学员的表现，为本部分生成的 AI 建议",
  checking: "正在检查将发送的内容…",
  evidenceTitle: "提出此建议的原因",
  evidenceIntro: "最近 {days} 天内 {learners} 名学员的结果，仅以数字呈现。此处不显示任何个人，也不会把个人信息发送给 AI。",
  evidenceAria: "学员表现数据",
  figStruggle: "在此遇到困难",
  figHelp: "获得了额外帮助",
  figRecovery: "获得帮助后掌握",
  figHelpful: "认为帮助有用",
  figTutor: "被判定为偏题而拒答的导师提问（整门课程）",
  noFigure: "数据不足",
  reasonsTitle: "最常见的原因",
  noReasons: "没有特别突出的原因。",
  wrongTitle: "学员选择的错误答案",
  wrongIntro: "在此活跃的学员中选择每个错误选项的比例。少于 5 人选择的选项合并显示。",
  wrongOption: "答案",
  wrongShare: "学员比例",
  wrongScroll: "错误答案（小屏幕上可横向滚动）",
  wrongOther: "其他错误答案",
  sentTitle: "AI 会收到什么",
  sentText: "本部分的文字和测验答案、它引用的来源段落、课时目标，以及上面的数字。AI 只提出修改建议；在你接受并应用之前，课程不会有任何变化。",
  estimate: "预计费用：约 {cost}。计入本课程每月的 AI 预算。",
  confirm: "创建建议",
  cancel: "取消",
  creating: "正在创建建议并请求 AI…",
  created: "已创建建议 {number}（{status}）。",
  createdReview: "查看建议",
  existing: "本部分已有未处理的建议（建议 {number}）。",
  existingReview: "打开",
  statusAnalysing: "AI 正在处理",
  statusReady: "可供你审阅",
  statusAwaiting: "等待分析",
  close: "关闭",
  errSmallGroup: "此期间在此活跃的学员不足 {n} 人，无法根据他们的结果提出改进建议。",
  errDailyLimit: "本课程今天的改进建议数量已达上限。请先审阅，或明天再试。",
  errAiOff: "本系统已关闭 AI。请在工作区中手动编辑这一部分。",
  errBudget: "本课程的 AI 预算已用完。建议已保留；请稍后再试，或手动编辑这一部分。",
  errNotBuilder: "这一部分不是用课程构建器创建的，无法在此改进。请手动编辑。",
  errNotImprovable: "只有课时、文本块和测验题可以根据学员洞察进行改进。",
  errNoSources: "这一部分没有引用你的来源材料中的任何段落，因此无法为改进提供依据。请在工作区中手动编辑。",
  errNoCourse: "本课程还没有已批准的版本，没有可改进的内容。",
  errForbidden: "只有本课程的作者或负责审阅 Living Course 更新的人才能提出改进建议。",
  errGeneric: "无法创建建议。请稍后再试。",
  reviewHeading: "来自学员洞察的改进建议",
  reviewIntro: "学员在课程的这一部分遇到困难。AI 根据你的来源材料给出更清晰的版本。请审阅这项修改；在你接受并应用之前，课程不会有任何变化。",
  reviewSub: "建议 {number}",
  badge: "来自学员洞察",
  listHeading: "更新与改进",
  appliedTitle: "改进已应用",
  appliedText: "课程现已包含这项修改。学员保留各自的进度。",
  rejectedTitle: "课程保持原样",
  rejectedText: "课程没有任何变化，来源也未受影响。",
  rejectTitle: "拒绝整个改进建议？",
  rejectText: "课程将保持现状。你之后可以在洞察页面重新提出改进建议。",
  reasons: {
    failed_attempts: "多次作答失败",
    slow_element: "耗时远超多数人",
    abandoned_element: "打开后转去学习别处",
    repetition: "重复播放或回看",
    inactivity: "停止学习",
    struggling_elements: "同时在多个难点上受阻",
  },
};

export const IMPROVE_STRINGS: Record<InsightsLocale, ImproveStrings> = { en, pl, zh };
