/**
 * Strings of the studio Insights page in English, Polish and Simplified Chinese (the languages of the product,
 * glossary in src/i18n/GLOSSARY.md). Placeholders are `{name}`. Counts are always written as "label: number" so no
 * plural form is needed. The language comes from `?lang=`, else the browser's `Accept-Language`, else English.
 */
export type InsightsLocale = "en" | "pl" | "zh";
export const INSIGHTS_LOCALES: readonly InsightsLocale[] = ["en", "pl", "zh"];

export interface InsightsStrings {
  htmlLang: string;
  pageTitle: string;
  heading: string;
  courseFallback: string;
  intro: string;
  languageLabel: string;
  periodLabel: string;
  period7: string;
  period30: string;
  period90: string;
  privacyTitle: string;
  privacyText: string;
  loading: string;
  needsScript: string;
  error: string;
  retry: string;
  forbidden: string;
  moduleOff: string;
  moduleOffLink: string;
  smallGroup: string;
  progressTitle: string;
  progressIntro: string;
  enrolled: string;
  active: string;
  completed: string;
  completionRate: string;
  quizTitle: string;
  quizAttempts: string;
  quizPassRate: string;
  quizAverage: string;
  quizEmpty: string;
  quizNote: string;
  supportTitle: string;
  supportIntro: string;
  onTrack: string;
  struggling: string;
  atRisk: string;
  elementsTitle: string;
  elementsIntro: string;
  elementsEmpty: string;
  elementsCaption: string;
  elementsScroll: string;
  colSection: string;
  colActive: string;
  colStruggle: string;
  colHelped: string;
  colRecovered: string;
  colReasons: string;
  withheldRow: string;
  noFigure: string;
  noReasons: string;
  helpTitle: string;
  helpIntro: string;
  helpShown: string;
  helpHelpful: string;
  helpNotHelpful: string;
  helpRecovery: string;
  nudgesSent: string;
  nudgesOpened: string;
  nudgesHeld: string;
  nudgesRecovery: string;
  recoveryNote: string;
  individualsTitle: string;
  individualsIntro: string;
  individualsForbidden: string;
  individualsEmpty: string;
  individualsCaption: string;
  individualsScroll: string;
  colLearner: string;
  colStatus: string;
  colWhy: string;
  colSince: string;
  colActivity: string;
  showActivity: string;
  activityTitle: string;
  activityLoading: string;
  activityForbidden: string;
  activityError: string;
  activityEmpty: string;
  activityCaption: string;
  activityScroll: string;
  colWhen: string;
  colWhat: string;
  colWhere: string;
  statusAnnounce: string;
  reasons: Record<string, string>;
  signals: Record<string, string>;
}

const en: InsightsStrings = {
  htmlLang: "en",
  pageTitle: "Insights",
  heading: "Course insights",
  courseFallback: "Course {id}",
  intro: "Where learners progress, where they struggle and what helps. Everything here is an aggregate: nobody is named unless you have the permission to see individual learners.",
  languageLabel: "Language",
  periodLabel: "Period",
  period7: "Last 7 days",
  period30: "Last 30 days",
  period90: "Last 90 days",
  privacyTitle: "Privacy",
  privacyText: "Groups smaller than {n} learners are hidden. The server withholds those figures, they never reach this page, so nobody in a small group can be singled out.",
  loading: "Loading insights…",
  needsScript: "Insights need JavaScript to load the figures.",
  error: "The insights could not be loaded.",
  retry: "Try again",
  forbidden: "You can see insights for courses you author, or with the Learner Insights permission. Ask an administrator if you need access.",
  moduleOff: "Learner Insights is off for this academy. Nothing is recorded, so there is nothing to show yet.",
  moduleOffLink: "Open the academy settings",
  smallGroup: "Not enough learners yet: {enrolled} enrolled. Insights appear once at least {n} learners are enrolled, so that no individual stands out.",
  progressTitle: "Progress and completion",
  progressIntro: "Active means the learner opened or progressed a lesson, or answered a question, in the period.",
  enrolled: "Enrolled",
  active: "Active in the period",
  completed: "Completed the course",
  completionRate: "Completion rate",
  quizTitle: "Quiz performance",
  quizAttempts: "Finished attempts",
  quizPassRate: "Pass rate",
  quizAverage: "Average score",
  quizEmpty: "No quiz attempts were finished in this period.",
  quizNote: "All quizzes of the course together.",
  supportTitle: "Who needs support",
  supportIntro: "How many enrolled learners are on track, struggling or at risk of dropping out.",
  onTrack: "On track",
  struggling: "Struggling",
  atRisk: "At risk",
  elementsTitle: "Sections that confuse people",
  elementsIntro: "Sections ranked by the share of their active learners who struggled in the period. Recovered is the share who got back on track after help.",
  elementsEmpty: "No section had learner activity in this period.",
  elementsCaption: "Sections ranked by struggle rate",
  elementsScroll: "Sections that confuse people (scrolls sideways on small screens)",
  colSection: "Section",
  colActive: "Active learners",
  colStruggle: "Struggled",
  colHelped: "Got help",
  colRecovered: "Recovered",
  colReasons: "Main reasons",
  withheldRow: "Hidden: fewer than {n} learners",
  noFigure: "Not enough data",
  noReasons: "None recorded",
  helpTitle: "Personal help and nudges",
  helpIntro: "Extra help shown to learners who struggled, and reminders sent to learners who went quiet.",
  helpShown: "Help shown",
  helpHelpful: "Marked helpful",
  helpNotHelpful: "Marked not helpful",
  helpRecovery: "Recovered after help",
  nudgesSent: "Nudges sent",
  nudgesOpened: "Nudges opened",
  nudgesHeld: "Nudges held back",
  nudgesRecovery: "Recovered after a nudge",
  recoveryNote: "Shown once at least {n} cases are closed.",
  individualsTitle: "Learners who are struggling or at risk",
  individualsIntro: "Individual data. Use it to reach out, not to rank people.",
  individualsForbidden: "Individual learners are visible only to people with the Learner Insights individuals permission. Ask an administrator if you need it.",
  individualsEmpty: "Nobody is struggling or at risk right now.",
  individualsCaption: "Learners who are struggling or at risk",
  individualsScroll: "Learners (scrolls sideways on small screens)",
  colLearner: "Learner",
  colStatus: "Status",
  colWhy: "Why",
  colSince: "Since",
  colActivity: "Activity",
  showActivity: "Show activity",
  activityTitle: "Activity of {name}",
  activityLoading: "Loading activity…",
  activityForbidden: "Only administrators can open the activity of a learner.",
  activityError: "The activity could not be loaded.",
  activityEmpty: "No activity is recorded for this learner.",
  activityCaption: "Recorded activity, newest first",
  activityScroll: "Activity (scrolls sideways on small screens)",
  colWhen: "When",
  colWhat: "What",
  colWhere: "Where",
  statusAnnounce: "Insights updated: {period}.",
  reasons: {
    failed_attempts: "Repeated failed attempts",
    slow_element: "Takes much longer than most",
    abandoned_element: "Left open while working elsewhere",
    repetition: "Replays or revisits",
    inactivity: "Inactive for days",
    struggling_elements: "Several hard parts",
  },
  signals: {
    "course.started": "Started the course",
    "course.completed": "Completed the course",
    "topic.opened": "Opened a lesson",
    "topic.progressed": "Progressed in a lesson",
    "topic.completed": "Completed a lesson",
    "quiz.attempt_started": "Started a quiz attempt",
    "quiz.attempt_finished": "Finished a quiz attempt",
    "quiz.answer": "Answered a question",
    "code.attempt_finished": "Ran the tests of a code exercise",
    "scorm.status": "SCORM status changed",
    "xapi.result": "xAPI result",
    "h5p.result": "H5P result",
    "media.replayed": "Replayed media",
    "media.seeked_back": "Went back in media",
    "session.login": "Signed in",
    "remediation.shown": "Was shown extra help",
    "remediation.feedback": "Rated extra help",
    "nudge.sent": "Was sent a reminder",
    "nudge.opened": "Opened a reminder",
    "tutor.question": "Asked the course tutor",
  },
};

const pl: InsightsStrings = {
  htmlLang: "pl",
  pageTitle: "Wgląd w naukę",
  heading: "Wgląd w kurs",
  courseFallback: "Kurs {id}",
  intro: "Gdzie kursanci idą naprzód, gdzie mają trudności i co im pomaga. Wszystko tu jest zbiorcze: nikt nie jest wskazany z nazwiska, chyba że masz uprawnienie do przeglądania pojedynczych kursantów.",
  languageLabel: "Język",
  periodLabel: "Okres",
  period7: "Ostatnie 7 dni",
  period30: "Ostatnie 30 dni",
  period90: "Ostatnie 90 dni",
  privacyTitle: "Prywatność",
  privacyText: "Grupy mniejsze niż {n} kursantów są ukryte. Serwer wstrzymuje te dane, więc w ogóle nie trafiają na tę stronę i nikogo z małej grupy nie da się rozpoznać.",
  loading: "Wczytywanie danych…",
  needsScript: "Do wczytania danych potrzebny jest JavaScript.",
  error: "Nie udało się wczytać danych.",
  retry: "Spróbuj ponownie",
  forbidden: "Dane widzisz dla kursów, których jesteś autorem, albo z uprawnieniem Learner Insights. Poproś administratora, jeśli potrzebujesz dostępu.",
  moduleOff: "Learner Insights jest wyłączone w tej akademii. Nic nie jest zapisywane, więc nie ma jeszcze czego pokazać.",
  moduleOffLink: "Otwórz ustawienia akademii",
  smallGroup: "Za mało kursantów: zapisanych {enrolled}. Dane pojawią się, gdy zapisanych będzie co najmniej {n} osób, żeby nikt nie był rozpoznawalny.",
  progressTitle: "Postępy i ukończenie",
  progressIntro: "Aktywny to kursant, który w tym okresie otworzył lub kontynuował lekcję albo odpowiedział na pytanie.",
  enrolled: "Zapisani",
  active: "Aktywni w okresie",
  completed: "Ukończyli kurs",
  completionRate: "Odsetek ukończeń",
  quizTitle: "Wyniki quizów",
  quizAttempts: "Zakończone podejścia",
  quizPassRate: "Odsetek zaliczeń",
  quizAverage: "Średni wynik",
  quizEmpty: "W tym okresie nie zakończono żadnego podejścia do quizu.",
  quizNote: "Wszystkie quizy kursu razem.",
  supportTitle: "Kto potrzebuje wsparcia",
  supportIntro: "Ilu zapisanych kursantów idzie dobrze, ma trudności albo grozi im rezygnacja.",
  onTrack: "Idą dobrze",
  struggling: "Mają trudności",
  atRisk: "Zagrożeni rezygnacją",
  elementsTitle: "Fragmenty, które sprawiają trudność",
  elementsIntro: "Fragmenty uszeregowane według udziału aktywnych kursantów, którzy mieli w tym okresie trudności. „Wrócili na tor” to odsetek tych, którzy po pomocy znów szli dobrze.",
  elementsEmpty: "W tym okresie żaden fragment nie miał aktywności kursantów.",
  elementsCaption: "Fragmenty uszeregowane według odsetka trudności",
  elementsScroll: "Fragmenty, które sprawiają trudność (na małym ekranie przewijane w bok)",
  colSection: "Fragment",
  colActive: "Aktywni kursanci",
  colStruggle: "Mieli trudności",
  colHelped: "Dostali pomoc",
  colRecovered: "Wrócili na tor",
  colReasons: "Główne przyczyny",
  withheldRow: "Ukryte: mniej niż {n} kursantów",
  noFigure: "Za mało danych",
  noReasons: "Brak zapisanych",
  helpTitle: "Pomoc indywidualna i przypomnienia",
  helpIntro: "Dodatkowa pomoc pokazana kursantom, którzy mieli trudności, oraz przypomnienia wysłane tym, którzy ucichli.",
  helpShown: "Pokazana pomoc",
  helpHelpful: "Oceniona jako pomocna",
  helpNotHelpful: "Oceniona jako niepomocna",
  helpRecovery: "Wrócili na tor po pomocy",
  nudgesSent: "Wysłane przypomnienia",
  nudgesOpened: "Otwarte przypomnienia",
  nudgesHeld: "Wstrzymane przypomnienia",
  nudgesRecovery: "Wrócili na tor po przypomnieniu",
  recoveryNote: "Pokazywane, gdy zamkniętych przypadków jest co najmniej {n}.",
  individualsTitle: "Kursanci z trudnościami lub zagrożeni rezygnacją",
  individualsIntro: "Dane indywidualne. Użyj ich, by pomóc, a nie by porównywać ludzi.",
  individualsForbidden: "Pojedynczych kursantów widzą tylko osoby z uprawnieniem do danych indywidualnych w Learner Insights. Poproś administratora, jeśli go potrzebujesz.",
  individualsEmpty: "Teraz nikt nie ma trudności ani nie jest zagrożony rezygnacją.",
  individualsCaption: "Kursanci z trudnościami lub zagrożeni rezygnacją",
  individualsScroll: "Kursanci (na małym ekranie przewijane w bok)",
  colLearner: "Kursant",
  colStatus: "Status",
  colWhy: "Dlaczego",
  colSince: "Od",
  colActivity: "Aktywność",
  showActivity: "Pokaż aktywność",
  activityTitle: "Aktywność: {name}",
  activityLoading: "Wczytywanie aktywności…",
  activityForbidden: "Aktywność kursanta mogą otworzyć tylko administratorzy.",
  activityError: "Nie udało się wczytać aktywności.",
  activityEmpty: "Dla tego kursanta nie zapisano aktywności.",
  activityCaption: "Zapisana aktywność, od najnowszej",
  activityScroll: "Aktywność (na małym ekranie przewijana w bok)",
  colWhen: "Kiedy",
  colWhat: "Co",
  colWhere: "Gdzie",
  statusAnnounce: "Dane zaktualizowane: {period}.",
  reasons: {
    failed_attempts: "Powtarzane nieudane próby",
    slow_element: "Zajmuje dużo więcej czasu niż innym",
    abandoned_element: "Otwarte, a kursant uczy się gdzie indziej",
    repetition: "Powtórne odtwarzanie lub powroty",
    inactivity: "Nieaktywni od kilku dni",
    struggling_elements: "Kilka trudnych fragmentów",
  },
  signals: {
    "course.started": "Rozpoczął kurs",
    "course.completed": "Ukończył kurs",
    "topic.opened": "Otworzył lekcję",
    "topic.progressed": "Postęp w lekcji",
    "topic.completed": "Ukończył lekcję",
    "quiz.attempt_started": "Rozpoczął podejście do quizu",
    "quiz.attempt_finished": "Zakończył podejście do quizu",
    "quiz.answer": "Odpowiedział na pytanie",
    "code.attempt_finished": "Uruchomił testy ćwiczenia z kodem",
    "scorm.status": "Zmiana statusu SCORM",
    "xapi.result": "Wynik xAPI",
    "h5p.result": "Wynik H5P",
    "media.replayed": "Odtworzył materiał ponownie",
    "media.seeked_back": "Cofnął materiał",
    "session.login": "Zalogował się",
    "remediation.shown": "Pokazano mu dodatkową pomoc",
    "remediation.feedback": "Ocenił dodatkową pomoc",
    "nudge.sent": "Wysłano mu przypomnienie",
    "nudge.opened": "Otworzył przypomnienie",
    "tutor.question": "Zapytał tutora kursu",
  },
};

const zh: InsightsStrings = {
  htmlLang: "zh-Hans",
  pageTitle: "学习洞察",
  heading: "课程洞察",
  courseFallback: "课程 {id}",
  intro: "学员在哪里进步、在哪里遇到困难，以及什么有帮助。这里的一切都是汇总数据：除非你有查看单个学员的权限，否则不会显示任何人的姓名。",
  languageLabel: "语言",
  periodLabel: "时间范围",
  period7: "最近 7 天",
  period30: "最近 30 天",
  period90: "最近 90 天",
  privacyTitle: "隐私",
  privacyText: "少于 {n} 名学员的群体会被隐藏。这些数据由服务器直接扣留，根本不会到达此页面，因此小群体中的任何人都无法被识别。",
  loading: "正在加载数据…",
  needsScript: "加载数据需要启用 JavaScript。",
  error: "无法加载数据。",
  retry: "重试",
  forbidden: "你可以查看自己担任作者的课程的数据，或在拥有 Learner Insights 权限时查看。如需访问，请联系管理员。",
  moduleOff: "此学院已关闭 Learner Insights。目前不会记录任何数据，因此暂无内容可显示。",
  moduleOffLink: "打开学院设置",
  smallGroup: "学员人数还不够：已报名 {enrolled} 人。报名人数达到至少 {n} 人后才会显示数据，以免任何个人被识别。",
  progressTitle: "进度与完成情况",
  progressIntro: "“活跃”指学员在该时间范围内打开或推进了课时，或回答了问题。",
  enrolled: "已报名",
  active: "期间活跃",
  completed: "已完成课程",
  completionRate: "完成率",
  quizTitle: "测验表现",
  quizAttempts: "已完成的作答次数",
  quizPassRate: "通过率",
  quizAverage: "平均分",
  quizEmpty: "此期间没有完成任何测验作答。",
  quizNote: "课程所有测验合计。",
  supportTitle: "谁需要支持",
  supportIntro: "已报名学员中，进展顺利、遇到困难或有流失风险的人数。",
  onTrack: "进展顺利",
  struggling: "遇到困难",
  atRisk: "有流失风险",
  elementsTitle: "让人困惑的部分",
  elementsIntro: "按期间遇到困难的活跃学员占比对各部分排序。“已恢复”指获得帮助后重新回到正轨的学员占比。",
  elementsEmpty: "此期间没有任何部分有学员活动。",
  elementsCaption: "按困难率排序的各部分",
  elementsScroll: "让人困惑的部分（在小屏幕上可横向滚动）",
  colSection: "部分",
  colActive: "活跃学员",
  colStruggle: "遇到困难",
  colHelped: "获得帮助",
  colRecovered: "已恢复",
  colReasons: "主要原因",
  withheldRow: "已隐藏：少于 {n} 名学员",
  noFigure: "数据不足",
  noReasons: "无记录",
  helpTitle: "个性化帮助与提醒",
  helpIntro: "向遇到困难的学员展示的额外帮助，以及发给沉寂学员的提醒。",
  helpShown: "已展示的帮助",
  helpHelpful: "标记为有帮助",
  helpNotHelpful: "标记为没有帮助",
  helpRecovery: "获得帮助后恢复",
  nudgesSent: "已发送的提醒",
  nudgesOpened: "已打开的提醒",
  nudgesHeld: "被暂缓的提醒",
  nudgesRecovery: "收到提醒后恢复",
  recoveryNote: "已结案的情况至少有 {n} 个时才会显示。",
  individualsTitle: "遇到困难或有流失风险的学员",
  individualsIntro: "个人数据。请用它来主动联系，而不是给人排名。",
  individualsForbidden: "只有拥有 Learner Insights 个人数据权限的人才能看到单个学员。如需此权限，请联系管理员。",
  individualsEmpty: "目前没有人遇到困难或有流失风险。",
  individualsCaption: "遇到困难或有流失风险的学员",
  individualsScroll: "学员（在小屏幕上可横向滚动）",
  colLearner: "学员",
  colStatus: "状态",
  colWhy: "原因",
  colSince: "自",
  colActivity: "活动",
  showActivity: "查看活动",
  activityTitle: "{name} 的活动",
  activityLoading: "正在加载活动…",
  activityForbidden: "只有管理员可以查看学员的活动。",
  activityError: "无法加载活动。",
  activityEmpty: "没有该学员的活动记录。",
  activityCaption: "已记录的活动，最新的在前",
  activityScroll: "活动（在小屏幕上可横向滚动）",
  colWhen: "时间",
  colWhat: "内容",
  colWhere: "位置",
  statusAnnounce: "数据已更新：{period}。",
  reasons: {
    failed_attempts: "多次作答失败",
    slow_element: "耗时远超多数人",
    abandoned_element: "打开后转去学习别处",
    repetition: "重复播放或回看",
    inactivity: "已有数天未活动",
    struggling_elements: "多个难点",
  },
  signals: {
    "course.started": "开始了课程",
    "course.completed": "完成了课程",
    "topic.opened": "打开了课时",
    "topic.progressed": "推进了课时",
    "topic.completed": "完成了课时",
    "quiz.attempt_started": "开始了测验作答",
    "quiz.attempt_finished": "完成了测验作答",
    "quiz.answer": "回答了问题",
    "code.attempt_finished": "运行了代码练习的测试",
    "scorm.status": "SCORM 状态变化",
    "xapi.result": "xAPI 结果",
    "h5p.result": "H5P 结果",
    "media.replayed": "重新播放了媒体",
    "media.seeked_back": "回退了媒体",
    "session.login": "登录",
    "remediation.shown": "看到了额外帮助",
    "remediation.feedback": "评价了额外帮助",
    "nudge.sent": "收到了提醒",
    "nudge.opened": "打开了提醒",
    "tutor.question": "向课程导师提问",
  },
};

export const INSIGHTS_STRINGS: Record<InsightsLocale, InsightsStrings> = { en, pl, zh };

/** `?lang=` wins; else the first language of `Accept-Language` that the page speaks; else English. */
export function pickInsightsLocale(query: string | null | undefined, acceptLanguage: string | null | undefined): InsightsLocale {
  const wanted = (value: string): InsightsLocale | null => {
    const code = value.trim().toLowerCase().split(/[-_;]/)[0] ?? "";
    return (INSIGHTS_LOCALES as readonly string[]).includes(code) ? (code as InsightsLocale) : null;
  };
  if (query) {
    const fromQuery = wanted(query);
    if (fromQuery) return fromQuery;
  }
  for (const part of (acceptLanguage ?? "").split(",")) {
    const found = wanted(part);
    if (found) return found;
  }
  return "en";
}

/** Replaces `{name}` placeholders; an unknown placeholder stays visible so a missing parameter is noticed. */
export function fill(template: string, params: Record<string, string | number> = {}): string {
  return template.replace(/\{(\w+)\}/g, (whole, key: string) => (key in params ? String(params[key]) : whole));
}
