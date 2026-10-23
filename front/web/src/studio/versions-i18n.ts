/**
 * Strings of the studio Versions page (/studio/courses/:id/versions) in English, Polish and Simplified Chinese (the
 * languages of the product, glossary in src/i18n/GLOSSARY.md). Placeholders are `{name}`. Counts are written as
 * "label: number" so no plural form is needed. The language comes from `?lang=`, else the browser's `Accept-Language`,
 * else English (the same rule as the Insights page).
 */
export { fill, INSIGHTS_LOCALES as VERSIONS_LOCALES, pickInsightsLocale as pickVersionsLocale, type InsightsLocale as VersionsLocale } from "./insights-i18n.ts";
import type { InsightsLocale as VersionsLocale } from "./insights-i18n.ts";

export interface VersionsStrings {
  htmlLang: string;
  pageTitle: string;
  heading: string;
  courseFallback: string;
  intro: string;
  languageLabel: string;
  loading: string;
  needsScript: string;
  error: string;
  retry: string;
  forbidden: string;
  notBuilt: string;
  empty: string;
  releasesTitle: string;
  caption: string;
  scrollLabel: string;
  colRelease: string;
  colReleased: string;
  colStartedBy: string;
  colApprovedBy: string;
  colChanges: string;
  colVersion: string;
  nobody: string;
  firstRelease: string;
  noChange: string;
  lessonsAdded: string;
  lessonsRemoved: string;
  lessonsChanged: string;
  otherElements: string;
  citationsChanged: string;
  answerKeysChanged: string;
  pagerLabel: string;
  previous: string;
  next: string;
  pageStatus: string;
  announcePage: string;
  triggers: Record<string, string>;
  movedElements: string;
  openCompare: string;
  selectHint: string;
  colSelect: string;
  colActions: string;
  selectRelease: string;
  selectVersion: string;
  selectedStatus: string;
  limitReached: string;
  compareSelected: string;
  clearSelection: string;
  compareWithPrevious: string;
  compareWithPreviousLabel: string;
  firstReleaseAction: string;
  showAll: string;
  loadingAll: string;
  errorAll: string;
  allVersionsTitle: string;
  allCaption: string;
  allScrollLabel: string;
  colVersionNo: string;
  colKind: string;
  colState: string;
  colCreated: string;
  colBranch: string;
  colReleasedAs: string;
  neverReleased: string;
  releaseNumber: string;
  baseCourse: string;
  statuses: Record<string, string>;
  kinds: Record<string, string>;
}

const en: VersionsStrings = {
  htmlLang: "en",
  pageTitle: "Versions",
  heading: "Course versions",
  courseFallback: "Course {id}",
  intro: "Each release is a version of this course that learners got. One is recorded every time a change is applied to the course. Proposals you rejected and changes you undid are not releases.",
  languageLabel: "Language",
  loading: "Loading the releases…",
  needsScript: "This page needs JavaScript to list the releases.",
  error: "The releases could not be loaded.",
  retry: "Try again",
  forbidden: "This course belongs to another author. Ask them, or an administrator, to open its versions.",
  notBuilt: "This course was not made with the Course Builder, so it has no releases yet. Adopt the course to track its versions.",
  empty: "No releases yet: the course has not been applied.",
  releasesTitle: "Releases, newest first",
  caption: "Releases of this course, newest first",
  scrollLabel: "Releases (scrolls sideways on small screens)",
  colRelease: "Release",
  colReleased: "Released",
  colStartedBy: "What started it",
  colApprovedBy: "Approved by",
  colChanges: "What changed since the previous release",
  colVersion: "Blueprint version",
  nobody: "Not recorded",
  firstRelease: "First release (lessons: {n})",
  noChange: "No content change",
  lessonsAdded: "Lessons added: {n}",
  lessonsRemoved: "Lessons removed: {n}",
  lessonsChanged: "Lessons changed: {n}",
  otherElements: "Other elements added, changed or removed: {n}",
  citationsChanged: "Elements with changed citations: {n}",
  answerKeysChanged: "Quiz answer keys changed: {n}",
  pagerLabel: "Pages of releases",
  previous: "Newer releases",
  next: "Older releases",
  pageStatus: "Page {page} of {last} (releases: {total})",
  announcePage: "Releases updated: page {page} of {last}.",
  triggers: {
    generate: "Generated from the sources",
    author: "Edited by the author",
    chat: "Changed in the element chat",
    update: "Source update accepted",
    insights: "Improvement from learner insights",
    style: "Restyle for the style profile",
    import: "Pushed from a course folder",
    restore: "Earlier version restored",
    rebase: "Variant rebased on the base course",
  },
  movedElements: "Elements moved: {n}",
  openCompare: "Open the compare screen",
  selectHint: "Tick two releases or versions, then compare them.",
  colSelect: "Compare",
  colActions: "Compare with previous",
  selectRelease: "Select release {n} to compare",
  selectVersion: "Select version {n} to compare",
  selectedStatus: "Selected for comparison: {n} of 2",
  limitReached: "Two are selected. Clear one to choose another.",
  compareSelected: "Compare the two selected",
  clearSelection: "Clear the selection",
  compareWithPrevious: "Compare with previous",
  compareWithPreviousLabel: "Compare release {n} with the previous release",
  firstReleaseAction: "First release",
  showAll: "Show all versions, including proposals, rejected and replaced ones",
  loadingAll: "Loading all versions…",
  errorAll: "All versions could not be loaded.",
  allVersionsTitle: "All versions, newest first",
  allCaption: "All versions of this course, newest first",
  allScrollLabel: "All versions (scrolls sideways on small screens)",
  colVersionNo: "Version",
  colKind: "Made by",
  colState: "State",
  colCreated: "Created",
  colBranch: "Branch",
  colReleasedAs: "Released as",
  neverReleased: "Never released",
  releaseNumber: "Release {n}",
  baseCourse: "Base course",
  statuses: { proposed: "Proposed, not applied", approved: "Approved", rejected: "Rejected", superseded: "Replaced by a newer version" },
  kinds: { outline: "Generated outline", content: "Generated lessons", patch: "Element chat", author: "Author edit", restore: "Restored version", update: "Source update", import: "Course folder push" },
};

const pl: VersionsStrings = {
  htmlLang: "pl",
  pageTitle: "Wersje",
  heading: "Wersje kursu",
  courseFallback: "Kurs {id}",
  intro: "Każde wydanie to wersja kursu, którą dostali uczestnicy. Wydanie powstaje za każdym razem, gdy zmiana trafia do kursu. Odrzucone propozycje i cofnięte zmiany nie są wydaniami.",
  languageLabel: "Język",
  loading: "Wczytuję wydania…",
  needsScript: "Ta strona potrzebuje JavaScriptu, żeby pokazać wydania.",
  error: "Nie udało się wczytać wydań.",
  retry: "Spróbuj ponownie",
  forbidden: "Ten kurs należy do innego autora. Poproś jego lub administratora o otwarcie wersji kursu.",
  notBuilt: "Ten kurs nie powstał w Kreatorze kursów, więc nie ma jeszcze wydań. Przejmij kurs, aby śledzić jego wersje.",
  empty: "Brak wydań: kurs nie został jeszcze zastosowany.",
  releasesTitle: "Wydania, od najnowszego",
  caption: "Wydania tego kursu, od najnowszego",
  scrollLabel: "Wydania (przewijane w bok na małych ekranach)",
  colRelease: "Wydanie",
  colReleased: "Data wydania",
  colStartedBy: "Co je wywołało",
  colApprovedBy: "Zatwierdził",
  colChanges: "Co zmieniło się od poprzedniego wydania",
  colVersion: "Wersja planu",
  nobody: "Brak danych",
  firstRelease: "Pierwsze wydanie (lekcje: {n})",
  noChange: "Bez zmian w treści",
  lessonsAdded: "Dodane lekcje: {n}",
  lessonsRemoved: "Usunięte lekcje: {n}",
  lessonsChanged: "Zmienione lekcje: {n}",
  otherElements: "Inne elementy dodane, zmienione lub usunięte: {n}",
  citationsChanged: "Elementy ze zmienionymi cytatami: {n}",
  answerKeysChanged: "Zmienione klucze odpowiedzi w quizach: {n}",
  pagerLabel: "Strony wydań",
  previous: "Nowsze wydania",
  next: "Starsze wydania",
  pageStatus: "Strona {page} z {last} (wydania: {total})",
  announcePage: "Wydania zaktualizowane: strona {page} z {last}.",
  triggers: {
    generate: "Wygenerowano ze źródeł",
    author: "Edycja autora",
    chat: "Zmiana w czacie elementu",
    update: "Przyjęta aktualizacja źródła",
    insights: "Poprawka z analizy uczestników",
    style: "Zmiana stylu zgodnie z profilem stylu",
    import: "Wysłano z folderu kursu",
    restore: "Przywrócono wcześniejszą wersję",
    rebase: "Wariant przeniesiony na kurs bazowy",
  },
  movedElements: "Przeniesione elementy: {n}",
  openCompare: "Otwórz porównanie wersji",
  selectHint: "Zaznacz dwa wydania lub dwie wersje, a potem je porównaj.",
  colSelect: "Porównaj",
  colActions: "Porównaj z poprzednim",
  selectRelease: "Wybierz wydanie {n} do porównania",
  selectVersion: "Wybierz wersję {n} do porównania",
  selectedStatus: "Wybrane do porównania: {n} z 2",
  limitReached: "Wybrano dwie pozycje. Odznacz jedną, aby wybrać inną.",
  compareSelected: "Porównaj wybrane dwie",
  clearSelection: "Wyczyść wybór",
  compareWithPrevious: "Porównaj z poprzednim",
  compareWithPreviousLabel: "Porównaj wydanie {n} z poprzednim wydaniem",
  firstReleaseAction: "Pierwsze wydanie",
  showAll: "Pokaż wszystkie wersje, także propozycje, odrzucone i zastąpione",
  loadingAll: "Wczytuję wszystkie wersje…",
  errorAll: "Nie udało się wczytać wszystkich wersji.",
  allVersionsTitle: "Wszystkie wersje, od najnowszej",
  allCaption: "Wszystkie wersje tego kursu, od najnowszej",
  allScrollLabel: "Wszystkie wersje (przewijane w bok na małych ekranach)",
  colVersionNo: "Wersja",
  colKind: "Czym powstała",
  colState: "Stan",
  colCreated: "Utworzona",
  colBranch: "Gałąź",
  colReleasedAs: "Wydana jako",
  neverReleased: "Bez wydania",
  releaseNumber: "Wydanie {n}",
  baseCourse: "Kurs bazowy",
  statuses: { proposed: "Propozycja, niezastosowana", approved: "Zatwierdzona", rejected: "Odrzucona", superseded: "Zastąpiona nowszą wersją" },
  kinds: { outline: "Wygenerowany plan", content: "Wygenerowane lekcje", patch: "Czat elementu", author: "Edycja autora", restore: "Przywrócona wersja", update: "Aktualizacja źródła", import: "Wysłano z folderu kursu" },
};

const zh: VersionsStrings = {
  htmlLang: "zh-Hans",
  pageTitle: "版本",
  heading: "课程版本",
  courseFallback: "课程 {id}",
  intro: "每个发布版本都是学员实际获得的课程版本。每次把更改应用到课程时都会记录一个。被你拒绝的建议和已撤销的更改不算发布版本。",
  languageLabel: "语言",
  loading: "正在加载发布版本…",
  needsScript: "此页面需要 JavaScript 才能列出发布版本。",
  error: "无法加载发布版本。",
  retry: "重试",
  forbidden: "此课程属于其他作者。请请他或管理员打开其版本。",
  notBuilt: "此课程不是用课程构建器制作的，因此还没有发布版本。接管该课程即可跟踪其版本。",
  empty: "还没有发布版本：课程尚未应用。",
  releasesTitle: "发布版本，最新的在前",
  caption: "此课程的发布版本，最新的在前",
  scrollLabel: "发布版本（小屏幕上可横向滚动）",
  colRelease: "发布版本",
  colReleased: "发布时间",
  colStartedBy: "触发原因",
  colApprovedBy: "批准人",
  colChanges: "与上一个发布版本相比的变化",
  colVersion: "蓝图版本",
  nobody: "未记录",
  firstRelease: "首个发布版本（课时：{n}）",
  noChange: "内容无变化",
  lessonsAdded: "新增课时：{n}",
  lessonsRemoved: "删除课时：{n}",
  lessonsChanged: "修改课时：{n}",
  otherElements: "其他新增、修改或删除的元素：{n}",
  citationsChanged: "引用有变化的元素：{n}",
  answerKeysChanged: "测验答案已更改：{n}",
  pagerLabel: "发布版本分页",
  previous: "较新的发布版本",
  next: "较早的发布版本",
  pageStatus: "第 {page} 页，共 {last} 页（发布版本：{total}）",
  announcePage: "发布版本已更新：第 {page} 页，共 {last} 页。",
  triggers: {
    generate: "由资料生成",
    author: "作者编辑",
    chat: "在元素对话中修改",
    update: "接受了资料更新",
    insights: "来自学员洞察的改进",
    style: "按样式配置调整样式",
    import: "从课程文件夹推送",
    restore: "恢复了早期版本",
    rebase: "变体已基于基础课程重新对齐",
  },
  movedElements: "移动的元素：{n}",
  openCompare: "打开对比页面",
  selectHint: "勾选两个发布版本或版本，然后进行对比。",
  colSelect: "对比",
  colActions: "与上一个对比",
  selectRelease: "选择发布版本 {n} 进行对比",
  selectVersion: "选择版本 {n} 进行对比",
  selectedStatus: "已选择对比：{n} / 2",
  limitReached: "已选择两个。请取消一个再选择其他的。",
  compareSelected: "对比所选的两个",
  clearSelection: "清除选择",
  compareWithPrevious: "与上一个对比",
  compareWithPreviousLabel: "将发布版本 {n} 与上一个发布版本对比",
  firstReleaseAction: "首个发布版本",
  showAll: "显示所有版本，包括建议、已拒绝和已被取代的版本",
  loadingAll: "正在加载所有版本…",
  errorAll: "无法加载所有版本。",
  allVersionsTitle: "所有版本，最新的在前",
  allCaption: "此课程的所有版本，最新的在前",
  allScrollLabel: "所有版本（小屏幕上可横向滚动）",
  colVersionNo: "版本",
  colKind: "产生方式",
  colState: "状态",
  colCreated: "创建时间",
  colBranch: "分支",
  colReleasedAs: "发布为",
  neverReleased: "未发布",
  releaseNumber: "发布版本 {n}",
  baseCourse: "基础课程",
  statuses: { proposed: "建议，尚未应用", approved: "已批准", rejected: "已拒绝", superseded: "已被较新版本取代" },
  kinds: { outline: "生成的大纲", content: "生成的课时", patch: "元素对话", author: "作者编辑", restore: "恢复的版本", update: "资料更新", import: "课程文件夹推送" },
};

export const VERSIONS_STRINGS: Record<VersionsLocale, VersionsStrings> = { en, pl, zh };
