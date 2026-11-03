/**
 * Strings of the studio Compare page (/studio/courses/:id/compare) in English, Polish and Simplified Chinese (the
 * languages of the product, glossary in src/i18n/GLOSSARY.md). Placeholders are `{name}`. Counts are written as
 * "label: number" so no plural form is needed. The language comes from `?lang=`, else the browser's `Accept-Language`,
 * else English (the same rule as the Versions and Insights pages). The API sends English element labels and sentences;
 * this page writes its own from the structured fields.
 */
export { fill, INSIGHTS_LOCALES as COMPARE_LOCALES, pickInsightsLocale as pickCompareLocale, type InsightsLocale as CompareLocale } from "./insights-i18n.ts";
import type { InsightsLocale as CompareLocale } from "./insights-i18n.ts";

export interface CompareStrings {
  htmlLang: string;
  pageTitle: string;
  heading: string;
  courseFallback: string;
  intro: string;
  languageLabel: string;
  backToVersions: string;
  loading: string;
  needsScript: string;
  error: string;
  retry: string;
  forbidden: string;
  notBuilt: string;
  notFound: string;
  pickTitle: string;
  fromLabel: string;
  toLabel: string;
  compareButton: string;
  swap: string;
  variantLabel: string;
  variantButton: string;
  variantNoVersions: string;
  variantsOnly: string;
  pickBoth: string;
  sameVersion: string;
  noVersions: string;
  versionLabel: string;
  versionReleased: string;
  versionNotReleased: string;
  baseCourse: string;
  otherId: string;
  statuses: Record<string, string>;
  sideFrom: string;
  sideTo: string;
  sideLine: string;
  summaryTitle: string;
  nothingChanged: string;
  firstRelease: string;
  figAdded: string;
  figRemoved: string;
  figChanged: string;
  figMoved: string;
  figReordered: string;
  figCitAdded: string;
  figCitRemoved: string;
  figCitRepointed: string;
  figKeys: string;
  figReworded: string;
  figQuestionsAdded: string;
  figQuestionsRemoved: string;
  impactTitle: string;
  impactIntro: string;
  impKeeping: string;
  impUpdated: string;
  impSilent: string;
  impRemoved: string;
  impAdded: string;
  impReattempt: string;
  quizTitle: string;
  quizNone: string;
  quizKey: string;
  quizWording: string;
  quizTypeChanged: string;
  quizConsequence: string;
  changesTitle: string;
  filtersLabel: string;
  kindGroup: string;
  typeGroup: string;
  clearFilters: string;
  showing: string;
  noMatch: string;
  treeLabel: string;
  viewLabel: string;
  viewInline: string;
  viewSide: string;
  detailTitle: string;
  detailEmpty: string;
  back: string;
  contextNote: string;
  kinds: Record<string, string>;
  types: Record<string, string>;
  finalTest: string;
  check: string;
  fields: Record<string, string>;
  before: string;
  after: string;
  addedContent: string;
  removedContent: string;
  movedFromTo: string;
  topLevel: string;
  reorderedLine: string;
  linesRemoved: string;
  linesAdded: string;
  linesOmitted: string;
  longField: string;
  yes: string;
  no: string;
  empty: string;
  origins: Record<string, string>;
  citationsPanel: string;
  citAdded: string;
  citRemoved: string;
  citRepointed: string;
  passageBefore: string;
  passageAfter: string;
  revision: string;
  passageMissing: string;
  quizPanel: string;
  quizStem: string;
  quizType: string;
  optionsAdded: string;
  optionsRemoved: string;
  optionsReworded: string;
  keyChanged: string;
  correctBefore: string;
  correctAfter: string;
  correctMark: string;
  wrongMark: string;
  explanationChanged: string;
  announceLoaded: string;
  announceFiltered: string;
  srAdded: string;
  srRemoved: string;
  pathSeparator: string;
}

const en: CompareStrings = {
  htmlLang: "en",
  pageTitle: "Compare versions",
  heading: "Compare versions",
  courseFallback: "Course {id}",
  intro: "See what changed between two versions of this course: lessons added, removed or moved, the words that changed, the citations behind them, quiz answers and what learners would notice.",
  languageLabel: "Language",
  backToVersions: "Back to the versions of this course",
  loading: "Loading the comparison…",
  needsScript: "This page needs JavaScript to compare versions.",
  error: "The comparison could not be loaded.",
  retry: "Try again",
  forbidden: "This course belongs to another author. Ask them, or an administrator, to open its versions.",
  notBuilt: "This course was not made with the Course Builder, so it has no versions to compare. Adopt the course to track its versions.",
  notFound: "One of these versions or releases is not part of this course. Pick the two sides again.",
  pickTitle: "Pick two versions",
  fromLabel: "From (older)",
  toLabel: "To (newer)",
  compareButton: "Compare",
  swap: "Swap the two sides",
  variantLabel: "Compare the base course with a variant",
  variantButton: "Compare base with variant",
  variantNoVersions: "That variant has no version to compare yet.",
  variantsOnly: "Variant",
  pickBoth: "Pick two versions and choose Compare.",
  sameVersion: "The same version is on both sides, so nothing differs. Pick two different versions.",
  noVersions: "This course has no versions yet.",
  versionLabel: "Version {n}",
  versionReleased: "release #{n}",
  versionNotReleased: "never released",
  baseCourse: "base course",
  otherId: "Other: {id}",
  statuses: { proposed: "proposed", rejected: "rejected", superseded: "replaced", approved: "approved" },
  sideFrom: "From",
  sideTo: "To",
  sideLine: "{version} ({release}), {date}",
  summaryTitle: "What changed",
  nothingChanged: "These two versions have the same content: nothing was added, removed, changed or moved.",
  firstRelease: "The older side has no content, so everything counts as added.",
  figAdded: "Elements added",
  figRemoved: "Elements removed",
  figChanged: "Elements changed",
  figMoved: "Elements moved to another parent",
  figReordered: "Elements reordered",
  figCitAdded: "Citations added",
  figCitRemoved: "Citations removed",
  figCitRepointed: "Citations repointed to a newer source revision",
  figKeys: "Quiz answer keys changed",
  figReworded: "Questions reworded",
  figQuestionsAdded: "Questions added",
  figQuestionsRemoved: "Questions removed",
  impactTitle: "What learners would notice",
  impactIntro: "If the newer side went live over the older one. Completion is never taken away; a major change to a lesson shows an “Updated” notice and a minor edit or a citation update is silent.",
  impKeeping: "Lessons whose completion is kept",
  impUpdated: "Lessons that show an “Updated” notice",
  impSilent: "Lessons changed without a notice",
  impRemoved: "Lessons removed",
  impAdded: "Lessons added",
  impReattempt: "Questions that give a re-attempt notice",
  quizTitle: "Quiz impact",
  quizNone: "No quiz question changed.",
  quizKey: "answer key changed",
  quizWording: "wording changed",
  quizTypeChanged: "question type changed",
  quizConsequence: "Learners who already answered this question get a re-attempt notice and one extra attempt. Their past score and attempt stay as they were.",
  changesTitle: "Changes",
  filtersLabel: "Filter the changes",
  kindGroup: "Kind of change",
  typeGroup: "Element type",
  clearFilters: "Show all changes",
  showing: "Showing {shown} of {total} changed elements",
  noMatch: "No changed element matches these filters.",
  treeLabel: "Changed elements, as a tree",
  viewLabel: "How to show text changes",
  viewInline: "Inline",
  viewSide: "Side by side",
  detailTitle: "Details of the selected element",
  detailEmpty: "Select a changed element in the tree to see what changed.",
  back: "Back to the list of changes",
  contextNote: "This element did not change itself. The changes inside it are listed under it in the tree.",
  kinds: { added: "Added", removed: "Removed", changed: "Changed", moved: "Moved", reordered: "Reordered" },
  types: { course: "Course", objective: "Outcome", module: "Module", lesson: "Lesson", block: "Block", quiz: "Quiz", question: "Question", option: "Option", interaction: "Activity" },
  finalTest: "Final test",
  check: "Self-check",
  fields: {
    title: "Title", subtitle: "Subtitle", description: "Description", summary: "Summary", minutes: "Minutes", text: "Text", markdown: "Content", kind: "Block type", stem: "Question",
    explanation: "Explanation", type: "Question type", correct: "Correct answer", citations: "Citations", origin: "Written by", contentType: "Lesson format", asset: "File",
    library: "Activity type", data: "Activity content", caption: "Caption", packageId: "Interactive package", startStep: "Start step", endStep: "End step",
  },
  before: "Before",
  after: "After",
  addedContent: "Content of the new element",
  removedContent: "Content of the removed element",
  movedFromTo: "Moved from {from} to {to}.",
  topLevel: "the top level",
  reorderedLine: "Moved from position {from} to position {to} among its siblings.",
  linesRemoved: "Lines removed",
  linesAdded: "Lines added",
  linesOmitted: "{n} more changed lines are not shown.",
  longField: "This field is very long, so only the changed lines are shown.",
  yes: "yes",
  no: "no",
  empty: "(empty)",
  origins: { ai: "AI", author: "the author", restore: "a restored version" },
  citationsPanel: "Citations",
  citAdded: "Citation added",
  citRemoved: "Citation removed",
  citRepointed: "Citation repointed to a newer source revision",
  passageBefore: "The passage before",
  passageAfter: "The passage after",
  revision: "source revision {n}",
  passageMissing: "The passage is not available.",
  quizPanel: "Quiz impact",
  quizStem: "Question text",
  quizType: "Question type",
  optionsAdded: "Options added",
  optionsRemoved: "Options removed",
  optionsReworded: "Options reworded",
  keyChanged: "Options whose correct flag changed",
  correctBefore: "Correct before",
  correctAfter: "Correct after",
  correctMark: "correct",
  wrongMark: "incorrect",
  explanationChanged: "The explanation changed (see the fields above).",
  announceLoaded: "Comparison loaded: changed elements: {n}.",
  announceFiltered: "Showing {shown} of {total} changed elements.",
  srAdded: "added: ",
  srRemoved: "removed: ",
  pathSeparator: " › ",
};

const pl: CompareStrings = {
  htmlLang: "pl",
  pageTitle: "Porównanie wersji",
  heading: "Porównanie wersji",
  courseFallback: "Kurs {id}",
  intro: "Zobacz, co zmieniło się między dwiema wersjami kursu: dodane, usunięte i przeniesione lekcje, zmienione słowa, cytowania źródeł, odpowiedzi w quizach i to, co zauważą kursanci.",
  languageLabel: "Język",
  backToVersions: "Wróć do wersji tego kursu",
  loading: "Wczytuję porównanie…",
  needsScript: "Ta strona potrzebuje JavaScriptu, żeby porównać wersje.",
  error: "Nie udało się wczytać porównania.",
  retry: "Spróbuj ponownie",
  forbidden: "Ten kurs należy do innego autora. Poproś jego lub administratora o otwarcie wersji kursu.",
  notBuilt: "Ten kurs nie powstał w Kreatorze kursów, więc nie ma wersji do porównania. Przejmij kurs, aby śledzić jego wersje.",
  notFound: "Jedna z tych wersji lub jedno z wydań nie należy do tego kursu. Wybierz obie strony jeszcze raz.",
  pickTitle: "Wybierz dwie wersje",
  fromLabel: "Od (starsza)",
  toLabel: "Do (nowsza)",
  compareButton: "Porównaj",
  swap: "Zamień strony miejscami",
  variantLabel: "Porównaj kurs bazowy z wariantem",
  variantButton: "Porównaj bazę z wariantem",
  variantNoVersions: "Ten wariant nie ma jeszcze wersji do porównania.",
  variantsOnly: "Wariant",
  pickBoth: "Wybierz dwie wersje i naciśnij Porównaj.",
  sameVersion: "Po obu stronach jest ta sama wersja, więc nic się nie różni. Wybierz dwie różne wersje.",
  noVersions: "Ten kurs nie ma jeszcze wersji.",
  versionLabel: "Wersja {n}",
  versionReleased: "wydanie nr {n}",
  versionNotReleased: "bez wydania",
  baseCourse: "kurs bazowy",
  otherId: "Inna: {id}",
  statuses: { proposed: "propozycja", rejected: "odrzucona", superseded: "zastąpiona", approved: "zatwierdzona" },
  sideFrom: "Od",
  sideTo: "Do",
  sideLine: "{version} ({release}), {date}",
  summaryTitle: "Co się zmieniło",
  nothingChanged: "Obie wersje mają tę samą treść: nic nie zostało dodane, usunięte, zmienione ani przeniesione.",
  firstRelease: "Starsza strona nie ma treści, więc wszystko liczy się jako dodane.",
  figAdded: "Dodane elementy",
  figRemoved: "Usunięte elementy",
  figChanged: "Zmienione elementy",
  figMoved: "Elementy przeniesione do innego rodzica",
  figReordered: "Elementy w zmienionej kolejności",
  figCitAdded: "Dodane cytowania",
  figCitRemoved: "Usunięte cytowania",
  figCitRepointed: "Cytowania przepięte na nowszą wersję źródła",
  figKeys: "Zmienione klucze odpowiedzi w quizach",
  figReworded: "Pytania ze zmienionym brzmieniem",
  figQuestionsAdded: "Dodane pytania",
  figQuestionsRemoved: "Usunięte pytania",
  impactTitle: "Co zauważą kursanci",
  impactIntro: "Gdyby nowsza strona zastąpiła starszą. Ukończenie nigdy nie jest odbierane; duża zmiana lekcji pokazuje komunikat „Zaktualizowano”, a drobna poprawka lub aktualizacja cytowania nie pokazuje nic.",
  impKeeping: "Lekcje, których ukończenie zostaje",
  impUpdated: "Lekcje z komunikatem „Zaktualizowano”",
  impSilent: "Lekcje zmienione bez komunikatu",
  impRemoved: "Usunięte lekcje",
  impAdded: "Dodane lekcje",
  impReattempt: "Pytania z komunikatem o ponownej próbie",
  quizTitle: "Wpływ na quizy",
  quizNone: "Żadne pytanie quizu nie zmieniło się.",
  quizKey: "zmieniony klucz odpowiedzi",
  quizWording: "zmienione brzmienie",
  quizTypeChanged: "zmieniony typ pytania",
  quizConsequence: "Kursanci, którzy już odpowiedzieli na to pytanie, dostają komunikat o ponownej próbie i jedną dodatkową próbę. Ich dotychczasowy wynik i próba zostają bez zmian.",
  changesTitle: "Zmiany",
  filtersLabel: "Filtruj zmiany",
  kindGroup: "Rodzaj zmiany",
  typeGroup: "Typ elementu",
  clearFilters: "Pokaż wszystkie zmiany",
  showing: "Widoczne: {shown} z {total} zmienionych elementów",
  noMatch: "Żaden zmieniony element nie pasuje do tych filtrów.",
  treeLabel: "Zmienione elementy, jako drzewo",
  viewLabel: "Jak pokazywać zmiany tekstu",
  viewInline: "W jednej kolumnie",
  viewSide: "Obok siebie",
  detailTitle: "Szczegóły wybranego elementu",
  detailEmpty: "Wybierz zmieniony element w drzewie, żeby zobaczyć, co się zmieniło.",
  back: "Wróć do listy zmian",
  contextNote: "Ten element sam się nie zmienił. Zmiany wewnątrz niego są na liście pod nim w drzewie.",
  kinds: { added: "Dodano", removed: "Usunięto", changed: "Zmieniono", moved: "Przeniesiono", reordered: "Zmieniono kolejność" },
  types: { course: "Kurs", objective: "Efekt", module: "Moduł", lesson: "Lekcja", block: "Blok", quiz: "Quiz", question: "Pytanie", option: "Odpowiedź", interaction: "Aktywność" },
  finalTest: "Test końcowy",
  check: "Sprawdzenie wiedzy",
  fields: {
    title: "Tytuł", subtitle: "Podtytuł", description: "Opis", summary: "Podsumowanie", minutes: "Minuty", text: "Tekst", markdown: "Treść", kind: "Typ bloku", stem: "Pytanie",
    explanation: "Wyjaśnienie", type: "Typ pytania", correct: "Poprawna odpowiedź", citations: "Cytowania źródeł", origin: "Autor treści", contentType: "Format lekcji", asset: "Plik",
    library: "Typ aktywności", data: "Treść aktywności", caption: "Podpis", packageId: "Pakiet interaktywny", startStep: "Krok początkowy", endStep: "Krok końcowy",
  },
  before: "Przed",
  after: "Po",
  addedContent: "Treść nowego elementu",
  removedContent: "Treść usuniętego elementu",
  movedFromTo: "Przeniesiono z: {from} do: {to}.",
  topLevel: "najwyższy poziom",
  reorderedLine: "Przeniesiono z pozycji {from} na pozycję {to} wśród elementów tego samego poziomu.",
  linesRemoved: "Usunięte wiersze",
  linesAdded: "Dodane wiersze",
  linesOmitted: "Pozostałe zmienione wiersze ({n}) nie są pokazane.",
  longField: "To pole jest bardzo długie, więc pokazujemy tylko zmienione wiersze.",
  yes: "tak",
  no: "nie",
  empty: "(puste)",
  origins: { ai: "AI", author: "autor", restore: "przywrócona wersja" },
  citationsPanel: "Cytowania źródeł",
  citAdded: "Dodano cytowanie",
  citRemoved: "Usunięto cytowanie",
  citRepointed: "Cytowanie przepięte na nowszą wersję źródła",
  passageBefore: "Fragment przed zmianą",
  passageAfter: "Fragment po zmianie",
  revision: "wersja źródła {n}",
  passageMissing: "Fragment jest niedostępny.",
  quizPanel: "Wpływ na quiz",
  quizStem: "Treść pytania",
  quizType: "Typ pytania",
  optionsAdded: "Dodane odpowiedzi",
  optionsRemoved: "Usunięte odpowiedzi",
  optionsReworded: "Odpowiedzi ze zmienionym brzmieniem",
  keyChanged: "Odpowiedzi ze zmienionym oznaczeniem poprawności",
  correctBefore: "Poprawne przed zmianą",
  correctAfter: "Poprawne po zmianie",
  correctMark: "poprawna",
  wrongMark: "niepoprawna",
  explanationChanged: "Zmieniło się wyjaśnienie (zobacz pola powyżej).",
  announceLoaded: "Porównanie wczytane: zmienione elementy: {n}.",
  announceFiltered: "Widoczne: {shown} z {total} zmienionych elementów.",
  srAdded: "dodano: ",
  srRemoved: "usunięto: ",
  pathSeparator: " › ",
};

const zh: CompareStrings = {
  htmlLang: "zh-Hans",
  pageTitle: "对比版本",
  heading: "对比版本",
  courseFallback: "课程 {id}",
  intro: "查看此课程两个版本之间的变化：新增、删除或移动的课时，修改的文字，背后的引用，测验答案，以及学员会注意到什么。",
  languageLabel: "语言",
  backToVersions: "返回此课程的版本",
  loading: "正在加载对比…",
  needsScript: "此页面需要 JavaScript 才能对比版本。",
  error: "无法加载对比。",
  retry: "重试",
  forbidden: "此课程属于其他作者。请请他或管理员打开其版本。",
  notBuilt: "此课程不是用课程构建器制作的，因此没有可对比的版本。接管该课程即可跟踪其版本。",
  notFound: "所选的某个版本或发布版本不属于此课程。请重新选择两侧。",
  pickTitle: "选择两个版本",
  fromLabel: "从（较早）",
  toLabel: "到（较新）",
  compareButton: "对比",
  swap: "交换两侧",
  variantLabel: "将基础课程与变体对比",
  variantButton: "对比基础课程与变体",
  variantNoVersions: "该变体还没有可对比的版本。",
  variantsOnly: "变体",
  pickBoth: "选择两个版本，然后点击“对比”。",
  sameVersion: "两侧是同一个版本，因此没有差异。请选择两个不同的版本。",
  noVersions: "此课程还没有版本。",
  versionLabel: "版本 {n}",
  versionReleased: "发布版本 #{n}",
  versionNotReleased: "未发布",
  baseCourse: "基础课程",
  otherId: "其他：{id}",
  statuses: { proposed: "建议", rejected: "已拒绝", superseded: "已被取代", approved: "已批准" },
  sideFrom: "从",
  sideTo: "到",
  sideLine: "{version}（{release}），{date}",
  summaryTitle: "变化概览",
  nothingChanged: "这两个版本的内容相同：没有新增、删除、修改或移动。",
  firstRelease: "较早一侧没有内容，因此全部算作新增。",
  figAdded: "新增的元素",
  figRemoved: "删除的元素",
  figChanged: "修改的元素",
  figMoved: "移到其他父级的元素",
  figReordered: "顺序改变的元素",
  figCitAdded: "新增的引用",
  figCitRemoved: "删除的引用",
  figCitRepointed: "改指向较新资料版本的引用",
  figKeys: "测验答案已更改",
  figReworded: "措辞已修改的题目",
  figQuestionsAdded: "新增的题目",
  figQuestionsRemoved: "删除的题目",
  impactTitle: "学员会注意到什么",
  impactIntro: "假设较新一侧取代较旧一侧上线。已完成的进度不会被取消；课时的重大更改会显示“已更新”提示，小幅修改或引用更新则不会提示。",
  impKeeping: "保留完成状态的课时",
  impUpdated: "显示“已更新”提示的课时",
  impSilent: "无提示的已修改课时",
  impRemoved: "删除的课时",
  impAdded: "新增的课时",
  impReattempt: "会给出重新作答提示的题目",
  quizTitle: "对测验的影响",
  quizNone: "没有测验题目发生变化。",
  quizKey: "答案已更改",
  quizWording: "措辞已修改",
  quizTypeChanged: "题型已更改",
  quizConsequence: "已经回答过此题的学员会收到重新作答提示，并多得到一次作答机会。他们过去的成绩和作答记录保持不变。",
  changesTitle: "变化",
  filtersLabel: "筛选变化",
  kindGroup: "变化类型",
  typeGroup: "元素类型",
  clearFilters: "显示全部变化",
  showing: "显示 {shown} / {total} 个变化的元素",
  noMatch: "没有变化的元素符合这些筛选条件。",
  treeLabel: "变化的元素（树状）",
  viewLabel: "文字变化的显示方式",
  viewInline: "行内",
  viewSide: "并排",
  detailTitle: "所选元素的详情",
  detailEmpty: "在树中选择一个变化的元素，查看具体变化。",
  back: "返回变化列表",
  contextNote: "此元素本身没有变化。其内部的变化列在树中它的下面。",
  kinds: { added: "新增", removed: "删除", changed: "修改", moved: "移动", reordered: "重新排序" },
  types: { course: "课程", objective: "学习成果", module: "模块", lesson: "课时", block: "内容块", quiz: "测验", question: "题目", option: "选项", interaction: "活动" },
  finalTest: "结业测试",
  check: "自测题",
  fields: {
    title: "标题", subtitle: "副标题", description: "描述", summary: "摘要", minutes: "分钟", text: "文字", markdown: "内容", kind: "内容块类型", stem: "题目",
    explanation: "解析", type: "题型", correct: "正确答案", citations: "引用", origin: "撰写者", contentType: "课时格式", asset: "文件",
    library: "活动类型", data: "活动内容", caption: "说明文字", packageId: "交互包", startStep: "起始步骤", endStep: "结束步骤",
  },
  before: "之前",
  after: "之后",
  addedContent: "新元素的内容",
  removedContent: "已删除元素的内容",
  movedFromTo: "从 {from} 移到 {to}。",
  topLevel: "顶层",
  reorderedLine: "在同级元素中从第 {from} 位移到第 {to} 位。",
  linesRemoved: "删除的行",
  linesAdded: "新增的行",
  linesOmitted: "另有 {n} 行变化未显示。",
  longField: "此字段很长，因此只显示变化的行。",
  yes: "是",
  no: "否",
  empty: "（空）",
  origins: { ai: "AI", author: "作者", restore: "恢复的版本" },
  citationsPanel: "引用",
  citAdded: "新增引用",
  citRemoved: "删除引用",
  citRepointed: "引用改指向较新的资料版本",
  passageBefore: "更改前的段落",
  passageAfter: "更改后的段落",
  revision: "资料版本 {n}",
  passageMissing: "该段落不可用。",
  quizPanel: "对测验的影响",
  quizStem: "题目文字",
  quizType: "题型",
  optionsAdded: "新增的选项",
  optionsRemoved: "删除的选项",
  optionsReworded: "措辞已修改的选项",
  keyChanged: "正确标记已更改的选项",
  correctBefore: "之前的正确答案",
  correctAfter: "之后的正确答案",
  correctMark: "正确",
  wrongMark: "错误",
  explanationChanged: "解析已更改（见上方字段）。",
  announceLoaded: "对比已加载：变化的元素：{n}。",
  announceFiltered: "显示 {shown} / {total} 个变化的元素。",
  srAdded: "新增：",
  srRemoved: "删除：",
  pathSeparator: " › ",
};

export const COMPARE_STRINGS: Record<CompareLocale, CompareStrings> = { en, pl, zh };
