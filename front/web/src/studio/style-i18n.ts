/**
 * Strings of the studio Style page (/studio/style) in English, Polish and Simplified Chinese (the languages of the
 * product, glossary in src/i18n/GLOSSARY.md). Placeholders are `{name}`. Counts are written as "label: number" so no
 * plural form is needed. The language comes from `?lang=`, else the browser's `Accept-Language`, else English (the same
 * rule as the Insights and Versions pages).
 */
export { fill, INSIGHTS_LOCALES as STYLE_LOCALES, pickInsightsLocale as pickStyleLocale, type InsightsLocale as StyleLocale } from "./insights-i18n.ts";
import type { InsightsLocale as StyleLocale } from "./insights-i18n.ts";

export interface StyleStrings {
  htmlLang: string;
  pageTitle: string;
  heading: string;
  intro: string;
  languageLabel: string;
  loading: string;
  needsScript: string;
  error: string;
  retry: string;
  forbidden: string;
  uploadTitle: string;
  uploadHint: string;
  fileLabel: string;
  nameLabel: string;
  readButton: string;
  reading: string;
  readDone: string;
  needFile: string;
  listTitle: string;
  listEmpty: string;
  caption: string;
  scrollLabel: string;
  colName: string;
  colStatus: string;
  colScope: string;
  colCreated: string;
  colAction: string;
  review: string;
  reviewLabel: string;
  statuses: Record<string, string>;
  scopes: Record<string, string>;
  detailTitle: string;
  loadingDetail: string;
  approvedLine: string;
  warningsTitle: string;
  warningsIntro: string;
  changesTitle: string;
  changesIntro: string;
  changesNone: string;
  noCurrent: string;
  changeKinds: Record<string, string>;
  changeSymbols: Record<string, string>;
  changeFrom: string;
  changeTo: string;
  valuesTitle: string;
  valuesEmpty: string;
  parts: Record<string, string>;
  fields: Record<string, string>;
  persons: Record<string, string>;
  yes: string;
  no: string;
  insteadOf: string;
  citedBy: string;
  showPassage: string;
  hidePassage: string;
  noPassage: string;
  ownValue: string;
  themeTitle: string;
  themeText: string;
  themeLink: string;
  themeNoAdmin: string;
  editTitle: string;
  editIntro: string;
  nameField: string;
  personField: string;
  personNone: string;
  formalityField: string;
  formalityHint: string;
  sentenceField: string;
  sentenceHint: string;
  blockingField: string;
  blockingHint: string;
  removeItem: string;
  removed: string;
  jsonSummary: string;
  jsonLabel: string;
  jsonHint: string;
  jsonInvalid: string;
  save: string;
  saving: string;
  saved: string;
  approve: string;
  approving: string;
  approveHint: string;
  approvedDone: string;
  approvedReadOnly: string;
  supersededReadOnly: string;
  revise: string;
  revised: string;
  deleteButton: string;
  deleteConfirm: string;
  deleteCancel: string;
  deleted: string;
}

const en: StyleStrings = {
  htmlLang: "en",
  pageTitle: "Style profiles",
  heading: "Style profiles",
  intro: "Upload your style guide and the builder reads it into a style profile: theme, voice, terminology, banned phrases, reading level, components and image rules. Every value cites the passage it came from. Nothing applies until an admin approves the profile, and nothing changes in a course that already exists.",
  languageLabel: "Language of this page",
  loading: "Loading the style profiles…",
  needsScript: "This page needs JavaScript.",
  error: "The style profiles could not be loaded.",
  retry: "Try again",
  forbidden: "Only an admin approves a style profile, and you need access to the Course Builder to propose one.",
  uploadTitle: "Propose a profile from a style guide",
  uploadHint: "Markdown, PDF or DOCX. The guide is read as untrusted text: instructions inside it are ignored, and values that are out of range, unknown or uncited are dropped and listed below the profile.",
  fileLabel: "Style guide file",
  nameLabel: "Name of the profile (optional)",
  readButton: "Read the style guide",
  reading: "Reading the style guide…",
  readDone: "The style guide was read. Review the proposal below.",
  needFile: "Choose a style guide file first.",
  listTitle: "Profiles",
  listEmpty: "There are no style profiles yet. Upload a style guide above.",
  caption: "Style profiles of this academy, newest first",
  scrollLabel: "Style profiles (scrolls sideways on small screens)",
  colName: "Name",
  colStatus: "Status",
  colScope: "Applies to",
  colCreated: "Created",
  colAction: "Action",
  review: "Review",
  reviewLabel: "Review the style profile {name}",
  statuses: { suggested: "Suggested, not approved", approved: "Approved", superseded: "Replaced by a newer profile" },
  scopes: { tenant: "The whole academy", entry_point: "One entry point", brief: "One course brief" },
  detailTitle: "Style profile: {name}",
  loadingDetail: "Loading the profile…",
  approvedLine: "Approved on {date}.",
  warningsTitle: "Dropped from the proposal",
  warningsIntro: "These values did not fit the profile and were left out. Add them by hand if you still want them.",
  changesTitle: "Changes against the approved profile",
  changesIntro: "What approving this profile would change, value by value.",
  changesNone: "This profile has no changes against the approved profile.",
  noCurrent: "There is no approved profile yet, so every value is new.",
  changeKinds: { added: "Added", changed: "Changed", removed: "Removed" },
  changeSymbols: { added: "+", changed: "~", removed: "−" },
  changeFrom: "was",
  changeTo: "now",
  valuesTitle: "Values",
  valuesEmpty: "The profile has no values yet. Edit it to add some.",
  parts: { theme: "Theme", voice: "Voice", readingLevel: "Reading level", terminology: "Terminology", banned: "Banned phrases", components: "Components", imagery: "Images", examples: "Approved examples" },
  fields: { preset: "Theme preset", accent: "Accent colour", secondary: "Secondary colour", fonts: "Fonts", logoFileId: "Logo file", person: "Person", formality: "Formality (1 to 5)", sentenceLength: "Longest sentence (words)", preferred: "Preferred", disallowed: "Not allowed", altTextRequired: "Every image needs alt text", decorativeAllowed: "Decorative images allowed", noTextInImages: "No text inside images", blocking: "A banned phrase blocks publishing", phrase: "Phrase" },
  persons: { you: "you", we: "we", neutral: "neutral" },
  yes: "yes",
  no: "no",
  insteadOf: "instead of {terms}",
  citedBy: "From the style guide:",
  showPassage: "Show the passage {label}",
  hidePassage: "Hide the passage {label}",
  noPassage: "That passage is not available.",
  ownValue: "Your own value, no passage cited",
  themeTitle: "Theme",
  themeText: "This profile sets theme values. They change the academy's theme only after an admin accepts them as a diff on the theme screen.",
  themeLink: "Review the theme in settings",
  themeNoAdmin: "The address of the admin app is not known here: open Settings, Theme in the admin app.",
  editTitle: "Edit the profile",
  editIntro: "Only a suggestion can be edited. Values you add have no citation and are marked as yours.",
  nameField: "Name",
  personField: "Person",
  personNone: "Not set",
  formalityField: "Formality",
  formalityHint: "1 is casual, 5 is formal. Leave empty for no rule.",
  sentenceField: "Longest sentence in words",
  sentenceHint: "Between 5 and 60. Leave empty for no rule.",
  blockingField: "A banned phrase blocks publishing",
  blockingHint: "Off by default. When on, a course that uses a banned phrase cannot be published until it is removed.",
  removeItem: "Remove {item}",
  removed: "Removed. Save to keep the change.",
  jsonSummary: "Edit the values as JSON",
  jsonLabel: "Profile values (JSON)",
  jsonHint: "The full profile as stored. Unknown or invalid values are dropped when you save, and the API lists them.",
  jsonInvalid: "That is not valid JSON.",
  save: "Save the profile",
  saving: "Saving…",
  saved: "The profile was saved.",
  approve: "Approve this profile",
  approving: "Approving…",
  approveHint: "Approving replaces the approved profile of the academy. Only an admin can do it.",
  approvedDone: "Approved. This is now the academy's style profile.",
  approvedReadOnly: "This profile is approved. To change a rule, create a revision, review its changes and approve that.",
  supersededReadOnly: "A newer profile replaced this one. It is kept for reference.",
  revise: "Create a revision",
  revised: "A revision was created. Edit it, then approve it.",
  deleteButton: "Delete this profile",
  deleteConfirm: "Yes, delete it",
  deleteCancel: "Keep it",
  deleted: "The profile was deleted.",
};

const pl: StyleStrings = {
  htmlLang: "pl",
  pageTitle: "Profile stylu",
  heading: "Profile stylu",
  intro: "Prześlij swój przewodnik stylu, a kreator przeczyta go do profilu stylu: motyw, ton, terminologia, zakazane zwroty, poziom czytelności, komponenty i zasady dla obrazów. Każda wartość wskazuje fragment, z którego pochodzi. Nic nie zacznie obowiązywać, dopóki administrator nie zatwierdzi profilu, a istniejące kursy się nie zmienią.",
  languageLabel: "Język tej strony",
  loading: "Wczytywanie profili stylu…",
  needsScript: "Ta strona wymaga JavaScriptu.",
  error: "Nie udało się wczytać profili stylu.",
  retry: "Spróbuj ponownie",
  forbidden: "Profil stylu zatwierdza tylko administrator, a żeby go zaproponować, potrzebujesz dostępu do Course Buildera.",
  uploadTitle: "Zaproponuj profil z przewodnika stylu",
  uploadHint: "Markdown, PDF lub DOCX. Przewodnik jest czytany jak niezaufany tekst: polecenia w nim są ignorowane, a wartości spoza zakresu, nieznane lub bez cytowania są pomijane i wypisane pod profilem.",
  fileLabel: "Plik z przewodnikiem stylu",
  nameLabel: "Nazwa profilu (opcjonalnie)",
  readButton: "Przeczytaj przewodnik stylu",
  reading: "Czytanie przewodnika stylu…",
  readDone: "Przewodnik stylu został przeczytany. Sprawdź propozycję poniżej.",
  needFile: "Najpierw wybierz plik z przewodnikiem stylu.",
  listTitle: "Profile",
  listEmpty: "Nie ma jeszcze profili stylu. Prześlij przewodnik stylu powyżej.",
  caption: "Profile stylu tej akademii, od najnowszego",
  scrollLabel: "Profile stylu (na małych ekranach przewija się w bok)",
  colName: "Nazwa",
  colStatus: "Status",
  colScope: "Dotyczy",
  colCreated: "Utworzono",
  colAction: "Akcja",
  review: "Sprawdź",
  reviewLabel: "Sprawdź profil stylu {name}",
  statuses: { suggested: "Propozycja, niezatwierdzony", approved: "Zatwierdzony", superseded: "Zastąpiony nowszym profilem" },
  scopes: { tenant: "Cała akademia", entry_point: "Jedno wejście", brief: "Jeden brief kursu" },
  detailTitle: "Profil stylu: {name}",
  loadingDetail: "Wczytywanie profilu…",
  approvedLine: "Zatwierdzono {date}.",
  warningsTitle: "Pominięte w propozycji",
  warningsIntro: "Te wartości nie pasowały do profilu i zostały pominięte. Dodaj je ręcznie, jeśli nadal ich chcesz.",
  changesTitle: "Zmiany względem zatwierdzonego profilu",
  changesIntro: "Co zmieni zatwierdzenie tego profilu, wartość po wartości.",
  changesNone: "Ten profil nie zmienia niczego względem zatwierdzonego profilu.",
  noCurrent: "Nie ma jeszcze zatwierdzonego profilu, więc każda wartość jest nowa.",
  changeKinds: { added: "Dodano", changed: "Zmieniono", removed: "Usunięto" },
  changeSymbols: { added: "+", changed: "~", removed: "−" },
  changeFrom: "było",
  changeTo: "jest",
  valuesTitle: "Wartości",
  valuesEmpty: "Profil nie ma jeszcze wartości. Edytuj go, aby je dodać.",
  parts: { theme: "Motyw", voice: "Ton", readingLevel: "Poziom czytelności", terminology: "Terminologia", banned: "Zakazane zwroty", components: "Komponenty", imagery: "Obrazy", examples: "Zatwierdzone przykłady" },
  fields: { preset: "Motyw", accent: "Kolor akcentu", secondary: "Kolor dodatkowy", fonts: "Czcionki", logoFileId: "Plik logo", person: "Osoba", formality: "Formalność (1 do 5)", sentenceLength: "Najdłuższe zdanie (słowa)", preferred: "Preferowane", disallowed: "Niedozwolone", altTextRequired: "Każdy obraz wymaga tekstu alternatywnego", decorativeAllowed: "Obrazy dekoracyjne dozwolone", noTextInImages: "Bez tekstu na obrazach", blocking: "Zakazany zwrot blokuje publikację", phrase: "Zwrot" },
  persons: { you: "ty", we: "my", neutral: "bezosobowo" },
  yes: "tak",
  no: "nie",
  insteadOf: "zamiast {terms}",
  citedBy: "Z przewodnika stylu:",
  showPassage: "Pokaż fragment {label}",
  hidePassage: "Ukryj fragment {label}",
  noPassage: "Ten fragment nie jest dostępny.",
  ownValue: "Twoja własna wartość, bez cytowania",
  themeTitle: "Motyw",
  themeText: "Ten profil ustawia wartości motywu. Zmienią motyw akademii dopiero wtedy, gdy administrator zaakceptuje je jako podgląd zmian (diff) na ekranie motywu.",
  themeLink: "Sprawdź motyw w ustawieniach",
  themeNoAdmin: "Adres aplikacji administratora nie jest tu znany: otwórz Ustawienia, Motyw w aplikacji administratora.",
  editTitle: "Edytuj profil",
  editIntro: "Edytować można tylko propozycję. Dodane przez Ciebie wartości nie mają cytowania i są oznaczone jako Twoje.",
  nameField: "Nazwa",
  personField: "Osoba",
  personNone: "Nie ustawiono",
  formalityField: "Formalność",
  formalityHint: "1 to swobodnie, 5 to formalnie. Zostaw puste, jeśli nie ma reguły.",
  sentenceField: "Najdłuższe zdanie w słowach",
  sentenceHint: "Od 5 do 60. Zostaw puste, jeśli nie ma reguły.",
  blockingField: "Zakazany zwrot blokuje publikację",
  blockingHint: "Domyślnie wyłączone. Gdy włączone, kursu z zakazanym zwrotem nie można opublikować, dopóki go nie usuniesz.",
  removeItem: "Usuń {item}",
  removed: "Usunięto. Zapisz, aby zachować zmianę.",
  jsonSummary: "Edytuj wartości jako JSON",
  jsonLabel: "Wartości profilu (JSON)",
  jsonHint: "Cały profil w zapisanej postaci. Nieznane lub nieprawidłowe wartości są pomijane przy zapisie, a API je wypisuje.",
  jsonInvalid: "To nie jest poprawny JSON.",
  save: "Zapisz profil",
  saving: "Zapisywanie…",
  saved: "Profil został zapisany.",
  approve: "Zatwierdź ten profil",
  approving: "Zatwierdzanie…",
  approveHint: "Zatwierdzenie zastępuje zatwierdzony profil akademii. Może to zrobić tylko administrator.",
  approvedDone: "Zatwierdzono. To jest teraz profil stylu akademii.",
  approvedReadOnly: "Ten profil jest zatwierdzony. Aby zmienić regułę, utwórz wersję poprawioną, sprawdź jej zmiany i zatwierdź ją.",
  supersededReadOnly: "Nowszy profil zastąpił ten. Zachowujemy go jako archiwum.",
  revise: "Utwórz wersję poprawioną",
  revised: "Utworzono wersję poprawioną. Edytuj ją, a potem zatwierdź.",
  deleteButton: "Usuń ten profil",
  deleteConfirm: "Tak, usuń",
  deleteCancel: "Zachowaj",
  deleted: "Profil został usunięty.",
};

const zh: StyleStrings = {
  htmlLang: "zh-Hans",
  pageTitle: "样式配置",
  heading: "样式配置",
  intro: "上传您的样式指南，构建器会将其读成样式配置：主题、语气、术语、禁用语、阅读难度、组件和图片规则。每个值都会标明它来自哪一段。管理员批准之前不会生效，已有课程也不会改变。",
  languageLabel: "本页语言",
  loading: "正在加载样式配置…",
  needsScript: "此页面需要 JavaScript。",
  error: "无法加载样式配置。",
  retry: "重试",
  forbidden: "只有管理员可以批准样式配置，提出配置需要有 Course Builder 的访问权限。",
  uploadTitle: "根据样式指南提出配置",
  uploadHint: "Markdown、PDF 或 DOCX。指南按不受信任的文本读取：其中的指令会被忽略，超出范围、未知或没有引用的值会被舍弃，并列在配置下方。",
  fileLabel: "样式指南文件",
  nameLabel: "配置名称（可选）",
  readButton: "读取样式指南",
  reading: "正在读取样式指南…",
  readDone: "样式指南已读取。请查看下方的建议。",
  needFile: "请先选择样式指南文件。",
  listTitle: "配置",
  listEmpty: "还没有样式配置。请在上方上传样式指南。",
  caption: "本学院的样式配置，最新的在前",
  scrollLabel: "样式配置（小屏幕上可横向滚动）",
  colName: "名称",
  colStatus: "状态",
  colScope: "适用范围",
  colCreated: "创建时间",
  colAction: "操作",
  review: "查看",
  reviewLabel: "查看样式配置 {name}",
  statuses: { suggested: "建议，尚未批准", approved: "已批准", superseded: "已被较新的配置取代" },
  scopes: { tenant: "整个学院", entry_point: "一个入口", brief: "一份课程简介" },
  detailTitle: "样式配置：{name}",
  loadingDetail: "正在加载配置…",
  approvedLine: "批准于 {date}。",
  warningsTitle: "建议中被舍弃的内容",
  warningsIntro: "这些值不符合配置要求，已被舍弃。如仍需要，请手动添加。",
  changesTitle: "与已批准配置的差异",
  changesIntro: "批准此配置会带来哪些更改，逐项列出。",
  changesNone: "此配置与已批准的配置没有差异。",
  noCurrent: "还没有已批准的配置，因此每一项都是新的。",
  changeKinds: { added: "新增", changed: "更改", removed: "移除" },
  changeSymbols: { added: "+", changed: "~", removed: "−" },
  changeFrom: "原为",
  changeTo: "现为",
  valuesTitle: "值",
  valuesEmpty: "配置还没有值。请编辑以添加。",
  parts: { theme: "主题", voice: "语气", readingLevel: "阅读难度", terminology: "术语", banned: "禁用语", components: "组件", imagery: "图片", examples: "已批准的示例" },
  fields: { preset: "主题预设", accent: "强调色", secondary: "辅助色", fonts: "字体", logoFileId: "标志文件", person: "人称", formality: "正式程度（1 到 5）", sentenceLength: "最长句子（词数）", preferred: "优先使用", disallowed: "不允许", altTextRequired: "每张图片都需要替代文本", decorativeAllowed: "允许装饰性图片", noTextInImages: "图片中不含文字", blocking: "禁用语会阻止发布", phrase: "短语" },
  persons: { you: "您", we: "我们", neutral: "中性" },
  yes: "是",
  no: "否",
  insteadOf: "而不是 {terms}",
  citedBy: "来自样式指南：",
  showPassage: "显示段落 {label}",
  hidePassage: "隐藏段落 {label}",
  noPassage: "该段落不可用。",
  ownValue: "您自己的值，没有引用段落",
  themeTitle: "主题",
  themeText: "此配置设置了主题值。只有管理员在主题页面上将它们作为差异对比接受后，学院的主题才会改变。",
  themeLink: "在设置中查看主题",
  themeNoAdmin: "此处不知道管理应用的地址：请在管理应用中打开“设置”，再打开“主题”。",
  editTitle: "编辑配置",
  editIntro: "只有建议可以编辑。您添加的值没有引用，并标记为您自己的。",
  nameField: "名称",
  personField: "人称",
  personNone: "未设置",
  formalityField: "正式程度",
  formalityHint: "1 为随意，5 为正式。留空表示没有规则。",
  sentenceField: "最长句子的词数",
  sentenceHint: "5 到 60 之间。留空表示没有规则。",
  blockingField: "禁用语会阻止发布",
  blockingHint: "默认关闭。开启后，使用了禁用语的课程在移除之前无法发布。",
  removeItem: "移除 {item}",
  removed: "已移除。保存以保留更改。",
  jsonSummary: "以 JSON 编辑值",
  jsonLabel: "配置的值（JSON）",
  jsonHint: "完整的已存储配置。保存时未知或无效的值会被舍弃，API 会列出它们。",
  jsonInvalid: "这不是有效的 JSON。",
  save: "保存配置",
  saving: "正在保存…",
  saved: "配置已保存。",
  approve: "批准此配置",
  approving: "正在批准…",
  approveHint: "批准会取代学院当前已批准的配置。只有管理员可以操作。",
  approvedDone: "已批准。这现在是学院的样式配置。",
  approvedReadOnly: "此配置已批准。要更改规则，请创建修订版，查看其差异并批准它。",
  supersededReadOnly: "已有较新的配置取代了它。保留仅供参考。",
  revise: "创建修订版",
  revised: "已创建修订版。请编辑后再批准。",
  deleteButton: "删除此配置",
  deleteConfirm: "是，删除",
  deleteCancel: "保留",
  deleted: "配置已删除。",
};

export const STYLE_STRINGS: Record<StyleLocale, StyleStrings> = { en, pl, zh };
