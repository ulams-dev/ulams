/**
 * Fixed interface strings of the catalogue (button labels, accessible names, badges) in the
 * languages of the product landing: English (default), Polish and Simplified Chinese. Page
 * content is never here: it comes from the document. Components read the language from
 * `pageLocale(Astro)` (Astro i18n routing, see front/web/astro.config.mjs); without a prefix, English.
 */
export type UiLocale = "en" | "pl" | "zh";

export interface UiStrings {
  featuresOf: string;
  featureCount: string;
  /** Link from a blog post back to the list of posts. */
  blogBack: string;
  /** Byline of a blog post; `{author}` is replaced. */
  blogBy: string;
  /** Accessible name of the list of topics of a post. */
  blogTags: string;
  /** Shown inside a blog video when the browser cannot play it. */
  blogNoVideo: string;
  /** Link text that downloads the video instead. */
  blogDownload: string;
  packageDocs: string;
  skip: string;
  navMain: string;
  navMobile: string;
  navFooter: string;
  menu: string;
  /** `{brand}` is replaced. */
  home: string;
  language: string;
  coming: string;
  /** Label of a package link in the feature index. */
  /** Accessible name of a feature group list; `{title}` is replaced. */
  roadmap: string;
  available: string;
  preview: string;
  quiz: string;
  pause: string;
  play: string;
  replay: string;
  orbitLabel: string;
  orbitPause: string;
  ringEngine: string;
  ringInterfaces: string;
  ringPlatform: string;
  waysToRun: string;
  returned: string;
  sr: Record<"prompt" | "cmd" | "cont" | "agent" | "out" | "spin" | "tool" | "add" | "del" | "ctx" | "note" | "user" | "assistant" | "card", string>;
  source: string;
  interview: string;
  outline: string;
  lessonDraft: string;
  sources: string;
  logo: string;
  radius: string;
  compareHint: string;
  compareLegend: string;
  compareFeature: string;
  /** `{date}` is replaced. */
  compareAsOfCaption: string;
  compareAsOf: string;
  notDocumented: string;
  /** `{n}` is replaced. */
  sourcesCount: string;
  /** `{date}` is replaced. */
  checked: string;
  terminal: string;
  /** Contains `{link}` where the ulams link goes. */
  footerBuiltOn: string;
  /** Placeholder until the browser fills in the contact address. */
  mailLabel: string;
  newTab: string;
  studioWindow: string;
  adminSection: string;
  adminNew: string;
  adminCourse: string;
  adminStatus: string;
  adminLearners: string;
  /** "Extra help for you" card on the lesson page (Learner Insights). */
  supportTitle: string;
  supportGenerating: string;
  supportFailed: string;
  supportWhy: string;
  supportSources: string;
  supportFeedbackLabel: string;
  supportHelped: string;
  supportNotHelpful: string;
  supportSaved: string;
  supportSaveFailed: string;
  supportHide: string;
  supportHideAsk: string;
  supportHideYes: string;
  supportHideNo: string;
  supportHidden: string;
  supportJump: string;
  supportCheck: string;
  supportChoose: string;
  supportCorrect: string;
  supportNotQuite: string;
  supportNoScript: string;
  supportThoughts: string;
}

const en: UiStrings = {
  featuresOf: "Features for {title}",
  featureCount: "{n} features",
  blogBack: "All posts",
  blogBy: "By {author}",
  blogTags: "Topics",
  blogNoVideo: "Your browser cannot play this video.",
  blogDownload: "Download it",
  packageDocs: "Package documentation",
  skip: "Skip to content",
  navMain: "Main",
  navMobile: "Mobile",
  navFooter: "Footer",
  menu: "Menu",
  home: "{brand}, home",
  language: "Language",
  coming: "Coming",
  roadmap: "On the roadmap",
  available: "Available",
  preview: "Preview",
  quiz: "Quiz",
  pause: "Pause",
  play: "Play",
  replay: "Replay",
  orbitLabel: "What ulams covers",
  orbitPause: "Pause the orbit animation",
  ringEngine: "Course engine",
  ringInterfaces: "Interfaces",
  ringPlatform: "Platform",
  waysToRun: "Ways to run ulams",
  returned: "returned",
  sr: {
    prompt: "You ask Claude Code",
    cmd: "Command",
    cont: "Command, continued",
    agent: "Claude Code",
    out: "Output",
    spin: "Running",
    tool: "Tool call",
    add: "Added line",
    del: "Removed line",
    ctx: "Unchanged line",
    note: "Comment",
    user: "You",
    assistant: "Claude",
    card: "Proposal",
  },
  source: "Source",
  interview: "Interview",
  outline: "Outline",
  lessonDraft: "Lesson draft",
  sources: "Sources",
  logo: "Logo",
  radius: "Radius",
  compareHint: "Scroll sideways to see every product →",
  compareLegend: "Which products to compare",
  compareFeature: "Feature",
  compareAsOfCaption: ", as of {date}",
  compareAsOf: "As of {date}. Facts about other products come from their official documentation, pricing pages and licences; they change, so check the sources.",
  notDocumented: "Not documented",
  sourcesCount: "Sources ({n})",
  checked: " (checked {date})",
  terminal: "Terminal",
  studioWindow: "Studio · Course builder",
  adminSection: "Section",
  adminNew: "New course",
  adminCourse: "Course",
  adminStatus: "Status",
  adminLearners: "Learners",
  footerBuiltOn: "Built on {link}, the open headless LMS.",
  mailLabel: "Email",
  newTab: "(opens in a new tab)",
  supportTitle: "Extra help for you",
  supportGenerating: "Preparing an explanation…",
  supportFailed: "We could not prepare extra help this time.",
  supportWhy: "Why am I seeing this?",
  supportSources: "Sources",
  supportFeedbackLabel: "Was this extra help useful?",
  supportHelped: "This helped",
  supportNotHelpful: "Not helpful",
  supportSaved: "Thanks. Your answer is saved.",
  supportSaveFailed: "Could not save that. Try again.",
  supportHide: "Hide extra help for this lesson",
  supportHideAsk: "Hide this help? It will not come back for this lesson.",
  supportHideYes: "Yes, hide it",
  supportHideNo: "Keep it",
  supportHidden: "Extra help hidden for this lesson.",
  supportJump: "Extra help is available below",
  supportCheck: "Check",
  supportChoose: "Choose an answer first.",
  supportCorrect: "Correct",
  supportNotQuite: "Not quite",
  supportNoScript: "Checking an answer needs JavaScript.",
  supportThoughts: "Your thoughts (stay on this device)",
};

const pl: UiStrings = {
  featuresOf: "Funkcje: {title}",
  featureCount: "Funkcje: {n}",
  blogBack: "Wszystkie wpisy",
  blogBy: "Autor: {author}",
  blogTags: "Tematy",
  blogNoVideo: "Twoja przeglądarka nie odtworzy tego filmu.",
  blogDownload: "Pobierz go",
  packageDocs: "Dokumentacja pakietu",
  skip: "Przejdź do treści",
  navMain: "Główna",
  navMobile: "Mobilna",
  navFooter: "Stopka",
  menu: "Menu",
  home: "{brand}, strona główna",
  language: "Język",
  coming: "Wkrótce",
  roadmap: "W planach",
  available: "Dostępne",
  preview: "Wersja zapoznawcza",
  quiz: "Quiz",
  pause: "Wstrzymaj",
  play: "Wznów",
  replay: "Odtwórz ponownie",
  orbitLabel: "Co obejmuje ulams",
  orbitPause: "Zatrzymaj animację orbity",
  ringEngine: "Silnik kursów",
  ringInterfaces: "Interfejsy",
  ringPlatform: "Platforma",
  waysToRun: "Sposoby obsługi ulams",
  returned: "zwróciło",
  sr: {
    prompt: "Prosisz Claude Code",
    cmd: "Polecenie",
    cont: "Polecenie, ciąg dalszy",
    agent: "Claude Code",
    out: "Wynik",
    spin: "W toku",
    tool: "Wywołanie narzędzia",
    add: "Dodany wiersz",
    del: "Usunięty wiersz",
    ctx: "Wiersz bez zmian",
    note: "Komentarz",
    user: "Ty",
    assistant: "Claude",
    card: "Propozycja",
  },
  source: "Źródło",
  interview: "Wywiad",
  outline: "Konspekt",
  lessonDraft: "Szkic lekcji",
  sources: "Źródła",
  logo: "Logo",
  radius: "Zaokrąglenie",
  compareHint: "Przewiń w bok, aby zobaczyć wszystkie produkty →",
  compareLegend: "Które produkty porównać",
  compareFeature: "Funkcja",
  compareAsOfCaption: ", stan na {date}",
  compareAsOf: "Stan na {date}. Informacje o innych produktach pochodzą z ich oficjalnej dokumentacji, cenników i licencji; zmieniają się, więc sprawdź źródła.",
  notDocumented: "Brak danych",
  sourcesCount: "Źródła ({n})",
  checked: " (sprawdzono {date})",
  terminal: "Terminal",
  studioWindow: "Studio · Kreator kursów",
  adminSection: "Sekcja",
  adminNew: "Nowy kurs",
  adminCourse: "Kurs",
  adminStatus: "Status",
  adminLearners: "Kursanci",
  footerBuiltOn: "Zbudowane na {link}, otwartym headless LMS.",
  mailLabel: "E-mail",
  newTab: "(otwiera się w nowej karcie)",
  supportTitle: "Dodatkowa pomoc dla Ciebie",
  supportGenerating: "Przygotowujemy wyjaśnienie…",
  supportFailed: "Tym razem nie udało się przygotować dodatkowej pomocy.",
  supportWhy: "Dlaczego to widzę?",
  supportSources: "Źródła",
  supportFeedbackLabel: "Czy ta pomoc była przydatna?",
  supportHelped: "To pomogło",
  supportNotHelpful: "Nie pomogło",
  supportSaved: "Dziękujemy. Odpowiedź została zapisana.",
  supportSaveFailed: "Nie udało się tego zapisać. Spróbuj ponownie.",
  supportHide: "Ukryj dodatkową pomoc w tej lekcji",
  supportHideAsk: "Ukryć tę pomoc? Nie wróci w tej lekcji.",
  supportHideYes: "Tak, ukryj",
  supportHideNo: "Zostaw",
  supportHidden: "Dodatkowa pomoc w tej lekcji jest ukryta.",
  supportJump: "Dodatkowa pomoc jest poniżej",
  supportCheck: "Sprawdź",
  supportChoose: "Najpierw wybierz odpowiedź.",
  supportCorrect: "Dobrze",
  supportNotQuite: "Jeszcze nie",
  supportNoScript: "Sprawdzanie odpowiedzi wymaga JavaScriptu.",
  supportThoughts: "Twoje przemyślenia (zostają na tym urządzeniu)",
};

const zh: UiStrings = {
  featuresOf: "{title} 的功能",
  featureCount: "{n} 项功能",
  blogBack: "全部文章",
  blogBy: "作者：{author}",
  blogTags: "主题",
  blogNoVideo: "您的浏览器无法播放此视频。",
  blogDownload: "下载视频",
  packageDocs: "软件包文档",
  skip: "跳到正文",
  navMain: "主导航",
  navMobile: "移动端导航",
  navFooter: "页脚导航",
  menu: "菜单",
  home: "{brand}，返回首页",
  language: "语言",
  coming: "即将推出",
  roadmap: "已列入路线图",
  available: "已可用",
  preview: "预览版",
  quiz: "测验",
  pause: "暂停",
  play: "播放",
  replay: "重播",
  orbitLabel: "ulams 的能力范围",
  orbitPause: "暂停轨道动画",
  ringEngine: "课程引擎",
  ringInterfaces: "操作界面",
  ringPlatform: "平台",
  waysToRun: "运行 ulams 的方式",
  returned: "返回",
  sr: {
    prompt: "你对 Claude Code 说",
    cmd: "命令",
    cont: "命令（续）",
    agent: "Claude Code",
    out: "输出",
    spin: "进行中",
    tool: "工具调用",
    add: "新增行",
    del: "删除行",
    ctx: "未改动行",
    note: "注释",
    user: "你",
    assistant: "Claude",
    card: "修改建议",
  },
  source: "来源",
  interview: "访谈",
  outline: "大纲",
  lessonDraft: "课程草稿",
  sources: "来源",
  logo: "标志",
  radius: "圆角",
  compareHint: "向左滑动查看所有产品 →",
  compareLegend: "选择要对比的产品",
  compareFeature: "功能",
  compareAsOfCaption: "，截至 {date}",
  compareAsOf: "截至 {date}。其他产品的信息来自其官方文档、定价页面和许可证；这些内容会变化，请以来源为准。",
  notDocumented: "未说明",
  sourcesCount: "来源（{n}）",
  checked: "（核查于 {date}）",
  terminal: "终端",
  studioWindow: "工作室 · 课程构建器",
  adminSection: "栏目",
  adminNew: "新建课程",
  adminCourse: "课程",
  adminStatus: "状态",
  adminLearners: "学员",
  footerBuiltOn: "基于 {link} 构建，一个开放的无头 LMS。",
  mailLabel: "邮箱",
  newTab: "（在新标签页中打开）",
  supportTitle: "为你准备的额外帮助",
  supportGenerating: "正在准备讲解……",
  supportFailed: "这次未能准备好额外帮助。",
  supportWhy: "为什么会看到这个？",
  supportSources: "来源",
  supportFeedbackLabel: "这份额外帮助有用吗？",
  supportHelped: "有帮助",
  supportNotHelpful: "没有帮助",
  supportSaved: "谢谢，你的回答已保存。",
  supportSaveFailed: "无法保存，请重试。",
  supportHide: "在本课中隐藏额外帮助",
  supportHideAsk: "隐藏这份帮助？本课中将不再显示。",
  supportHideYes: "是，隐藏",
  supportHideNo: "保留",
  supportHidden: "本课的额外帮助已隐藏。",
  supportJump: "下方有额外帮助",
  supportCheck: "检查",
  supportChoose: "请先选择一个答案。",
  supportCorrect: "正确",
  supportNotQuite: "不太对",
  supportNoScript: "检查答案需要启用 JavaScript。",
  supportThoughts: "你的想法（仅保存在此设备上）",
};

const TABLE: Record<UiLocale, UiStrings> = { en, pl, zh };

/** The strings for a locale (`pl`, `zh`, `zh-Hans`…); anything else is English. */
export function uiStrings(locale?: string | null): UiStrings {
  const base = (locale ?? "en").toLowerCase().split("-")[0] as UiLocale;
  return TABLE[base] ?? en;
}

export const fill = (template: string, values: Record<string, string | number>): string =>
  template.replace(/\{(\w+)\}/g, (_, key: string) => String(values[key] ?? ""));

/**
 * The language of the page being rendered: Astro's i18n routing (`Astro.currentLocale`), or the language
 * prefix of the path when the renderer has no routing configured (the container API of the tests).
 */
export const pageLocale = (astro: { currentLocale?: string; url?: URL }): string | undefined =>
  astro.currentLocale ?? /^\/(pl|zh)(?:\/|$)/.exec(astro.url?.pathname ?? "")?.[1];
