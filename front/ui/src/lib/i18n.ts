/**
 * Fixed interface strings of the catalogue (button labels, accessible names, badges) in the
 * languages of the product landing: English (default), Polish and Simplified Chinese. Page
 * content is never here: it comes from the document. Components read the language from
 * `pageLocale(Astro)` (Astro i18n routing, see front/web/astro.config.mjs); without a prefix, English.
 */
export type UiLocale = "en" | "pl" | "zh";

export interface UiStrings {
  skip: string;
  navMain: string;
  navMobile: string;
  navFooter: string;
  menu: string;
  /** `{brand}` is replaced. */
  home: string;
  language: string;
  coming: string;
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
  studioWindow: string;
}

const en: UiStrings = {
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
  footerBuiltOn: "Built on {link}, the open headless LMS.",
};

const pl: UiStrings = {
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
  footerBuiltOn: "Zbudowane na {link}, otwartym headless LMS.",
};

const zh: UiStrings = {
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
  footerBuiltOn: "基于 {link} 构建，一个开放的无头 LMS。",
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
