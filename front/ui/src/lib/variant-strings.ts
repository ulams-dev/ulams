/**
 * Interface strings of the course variant picker, the hint to switch and the placement check (CV-5) in
 * English, Polish and Simplified Chinese. Course titles, variant labels, levels typed by authors, roles,
 * rule names and every other API text are never translated here. The hint reasons arrive from the API as
 * message keys with parameters and are rendered from this table, so they follow the learner's language
 * whatever language the API answered in. Plural forms go through `Intl.PluralRules` (Polish has `one`,
 * `few`, `many`).
 *
 * Native review of the Polish and Chinese text is an owner action (docs/translators.md in the docs site).
 */
import { fill, plural, trainingLocale, type Plural, type TrainingLocale } from "./training-strings.ts";

export { fill, plural, type Plural };
export type VariantLocale = TrainingLocale;

export const VARIANT_LEVELS = ["beginner", "intermediate", "expert"] as const;
export type VariantLevel = (typeof VARIANT_LEVELS)[number];

export interface VariantStrings {
  // ----- the picker
  pickerHeading: string;
  pickerLead: string;
  /** The legend of the radio group. */
  legend: string;
  levels: Record<VariantLevel, string>;
  /** `{level}` is replaced. */
  levelBadge: string;
  /** `{role}` is replaced. */
  roleBadge: string;
  /** `{duration}` is replaced. */
  durationBadge: string;
  defaultBadge: string;
  currentBadge: string;
  visitorNote: string;
  switchCta: string;
  keepsProgress: string;
  lockedNote: string;
  assignedByOrg: string;
  /** `{name}` is replaced. */
  assignedByRule: string;
  reasonSelf: string;
  reasonPlacement: string;
  reasonHintEasier: string;
  reasonHintHarder: string;
  placementLink: string;
  // ----- the status after a switch (the flash message)
  /** `{title}` is replaced. */
  statusSwitched: string;
  /** `{n}` is replaced. */
  statusCarried: Plural;
  statusCarriedNone: string;
  statusUnchanged: string;
  statusFailed: string;
  statusLocked: string;
  statusNoAccess: string;
  statusDismissed: string;
  // ----- the hint to switch
  hintHeading: string;
  hintTitleEasier: string;
  hintTitleHarder: string;
  hintLead: string;
  /** `{label}` is replaced. */
  hintSwitch: string;
  hintDismiss: string;
  hintDismissNote: string;
  hintWhy: string;
  /** `{n}` (modules) is replaced. */
  hintTooHard: Plural;
  /** `{n}` (modules) and `{percent}` are replaced. */
  hintTooEasy: Plural;
  // ----- the placement check
  placementTitle: string;
  placementLead: string;
  placementAdvisory: string;
  placementNoTimer: string;
  /** `{n}` and `{total}` are replaced. */
  questionOf: string;
  chooseOne: string;
  chooseMany: string;
  placementSubmit: string;
  placementResultTitle: string;
  /** `{label}` is replaced. */
  placementSuggest: string;
  /** `{correct}`, `{total}`, `{level}` (the level of those questions, in a sentence) and `{label}` are replaced. */
  placementReason: string;
  placementNoSuggestion: string;
  levelsInSentence: Record<VariantLevel, string>;
  scoresHeading: string;
  /** `{level}`, `{correct}` and `{total}` are replaced. */
  scoreLine: string;
  usePlacement: string;
  chooseAnother: string;
  placementAlreadyUsing: string;
  placementUnavailable: string;
  placementNeedsEnrol: string;
  placementSignIn: string;
  placementFailed: string;
  backToCourse: string;
  retake: string;
}

const en: VariantStrings = {
  pickerHeading: "Choose your version",
  pickerLead: "This course comes in more than one version. Each one is a full course for a different level or role.",
  legend: "Versions of this course",
  levels: { beginner: "Beginner", intermediate: "Intermediate", expert: "Expert" },
  levelBadge: "Level: {level}",
  roleBadge: "Role: {role}",
  durationBadge: "Duration: {duration}",
  defaultBadge: "Standard version",
  currentBadge: "Your current version",
  visitorNote: "You can choose your version once you are enrolled.",
  switchCta: "Switch to this version",
  keepsProgress: "Your progress is kept. Completed lessons that are the same in the new version carry over, and quizzes are taken again.",
  lockedNote: "Your organisation chose this version for you, so you cannot change it here.",
  assignedByOrg: "Assigned by your organisation",
  assignedByRule: "Assigned by your organisation (rule: {name})",
  reasonSelf: "You chose this version.",
  reasonPlacement: "You chose this version after the placement check.",
  reasonHintEasier: "You chose this version after a suggestion that an easier version might suit you better.",
  reasonHintHarder: "You chose this version after a suggestion that a more advanced version might suit you better.",
  placementLink: "Not sure? Take the placement check",
  statusSwitched: "Your version is now “{title}”.",
  statusCarried: {
    one: "{n} completed lesson was carried over from your previous version.",
    other: "{n} completed lessons were carried over from your previous version.",
  },
  statusCarriedNone: "No completed lessons needed to be carried over. Your earlier progress is still saved.",
  statusUnchanged: "You are already using this version. Nothing was changed.",
  statusFailed: "We could not switch your version. Nothing was changed. Please try again.",
  statusLocked: "Your organisation has locked this version, so it was not changed.",
  statusNoAccess: "You need to be enrolled in the course to choose a version.",
  statusDismissed: "Suggestions are turned off for this course.",
  hintHeading: "A suggestion for you",
  hintTitleEasier: "An easier version may suit you better",
  hintTitleHarder: "A more advanced version may suit you better",
  hintLead: "This is only a suggestion. Your version never changes unless you choose to switch.",
  hintSwitch: "Switch to {label}",
  hintDismiss: "No thanks",
  hintDismissNote: "If you choose “No thanks”, we stop making suggestions for this course.",
  hintWhy: "Why we suggest this",
  hintTooHard: {
    one: "You are finding the first module hard; an easier version of this course may suit you better.",
    other: "You are finding the first {n} modules hard; an easier version of this course may suit you better.",
  },
  hintTooEasy: {
    one: "You pass every quiz in the first module at the first attempt and need about {percent}% of the usual time; a more advanced version may suit you better.",
    other: "You pass every quiz in the first {n} modules at the first attempt and need about {percent}% of the usual time; a more advanced version may suit you better.",
  },
  placementTitle: "Placement check",
  placementLead: "Answer a few questions from this course and we suggest the version that fits you best.",
  placementAdvisory: "This check is advisory. It only suggests a version: nothing decides your level for you, and you choose which version to use.",
  placementNoTimer: "There is no timer. Your answers are not saved, only your score for each level.",
  questionOf: "Question {n} of {total}",
  chooseOne: "Choose one answer.",
  chooseMany: "Choose every answer that applies.",
  placementSubmit: "See my suggestion",
  placementResultTitle: "Your result",
  placementSuggest: "We suggest {label}",
  placementReason: "You answered {correct} of {total} {level} questions correctly, so we suggest {label}.",
  placementNoSuggestion: "We could not suggest a version from your answers. You can choose one yourself.",
  levelsInSentence: { beginner: "beginner", intermediate: "intermediate", expert: "expert" },
  scoresHeading: "Your score for each level",
  scoreLine: "{level}: {correct} of {total} correct",
  usePlacement: "Use this version",
  chooseAnother: "Choose another version",
  placementAlreadyUsing: "You are already using this version.",
  placementUnavailable: "There is no placement check for this course.",
  placementNeedsEnrol: "The placement check is available once you are enrolled.",
  placementSignIn: "Sign in to take the placement check.",
  placementFailed: "We could not score your answers. Please try again.",
  backToCourse: "Back to the course",
  retake: "Take the check again",
};

const pl: VariantStrings = {
  pickerHeading: "Wybierz swoją wersję",
  pickerLead: "Ten kurs ma kilka wersji. Każda z nich to pełny kurs dla innego poziomu lub innej roli.",
  legend: "Wersje tego kursu",
  levels: { beginner: "Początkujący", intermediate: "Średniozaawansowany", expert: "Ekspercki" },
  levelBadge: "Poziom: {level}",
  roleBadge: "Rola: {role}",
  durationBadge: "Czas trwania: {duration}",
  defaultBadge: "Wersja standardowa",
  currentBadge: "Twoja obecna wersja",
  visitorNote: "Wersję możesz wybrać po zapisaniu się na kurs.",
  switchCta: "Przełącz na tę wersję",
  keepsProgress: "Twój postęp zostanie zachowany. Ukończone lekcje, które są takie same w nowej wersji, zostaną przeniesione, a quizy rozwiążesz ponownie.",
  lockedNote: "Twoja organizacja wybrała dla Ciebie tę wersję, więc nie możesz jej tutaj zmienić.",
  assignedByOrg: "Przypisana przez Twoją organizację",
  assignedByRule: "Przypisana przez Twoją organizację (reguła: {name})",
  reasonSelf: "Ta wersja została wybrana przez Ciebie.",
  reasonPlacement: "Ta wersja została wybrana przez Ciebie po teście poziomującym.",
  reasonHintEasier: "Ta wersja została wybrana przez Ciebie po sugestii, że łatwiejsza wersja może lepiej Ci pasować.",
  reasonHintHarder: "Ta wersja została wybrana przez Ciebie po sugestii, że bardziej zaawansowana wersja może lepiej Ci pasować.",
  placementLink: "Nie masz pewności? Rozwiąż test poziomujący",
  statusSwitched: "Twoja wersja to teraz „{title}”.",
  statusCarried: {
    one: "{n} ukończona lekcja została przeniesiona z poprzedniej wersji.",
    few: "{n} ukończone lekcje zostały przeniesione z poprzedniej wersji.",
    many: "{n} ukończonych lekcji zostało przeniesionych z poprzedniej wersji.",
    other: "{n} ukończonych lekcji zostało przeniesionych z poprzedniej wersji.",
  },
  statusCarriedNone: "Żadna ukończona lekcja nie wymagała przeniesienia. Twój wcześniejszy postęp nadal jest zapisany.",
  statusUnchanged: "Już korzystasz z tej wersji. Nic nie zostało zmienione.",
  statusFailed: "Nie udało się przełączyć wersji. Nic nie zostało zmienione. Spróbuj ponownie.",
  statusLocked: "Twoja organizacja zablokowała tę wersję, więc nie została zmieniona.",
  statusNoAccess: "Aby wybrać wersję, trzeba najpierw zapisać się na kurs.",
  statusDismissed: "Sugestie są wyłączone dla tego kursu.",
  hintHeading: "Sugestia dla Ciebie",
  hintTitleEasier: "Łatwiejsza wersja może lepiej Ci pasować",
  hintTitleHarder: "Bardziej zaawansowana wersja może lepiej Ci pasować",
  hintLead: "To tylko sugestia. Twoja wersja nigdy się nie zmieni, jeśli sam(a) tego nie zdecydujesz.",
  hintSwitch: "Przełącz na „{label}”",
  hintDismiss: "Nie, dziękuję",
  hintDismissNote: "Jeśli wybierzesz „Nie, dziękuję”, przestaniemy proponować zmianę wersji w tym kursie.",
  hintWhy: "Dlaczego to proponujemy",
  hintTooHard: {
    one: "Pierwszy moduł sprawia Ci trudność; łatwiejsza wersja tego kursu może lepiej Ci pasować.",
    few: "Pierwsze {n} moduły sprawiają Ci trudność; łatwiejsza wersja tego kursu może lepiej Ci pasować.",
    many: "Pierwsze {n} modułów sprawia Ci trudność; łatwiejsza wersja tego kursu może lepiej Ci pasować.",
    other: "Pierwsze {n} modułów sprawia Ci trudność; łatwiejsza wersja tego kursu może lepiej Ci pasować.",
  },
  hintTooEasy: {
    one: "Wszystkie quizy w pierwszym module zaliczasz za pierwszym razem i potrzebujesz około {percent}% typowego czasu; bardziej zaawansowana wersja może lepiej Ci pasować.",
    few: "Wszystkie quizy w pierwszych {n} modułach zaliczasz za pierwszym razem i potrzebujesz około {percent}% typowego czasu; bardziej zaawansowana wersja może lepiej Ci pasować.",
    many: "Wszystkie quizy w pierwszych {n} modułach zaliczasz za pierwszym razem i potrzebujesz około {percent}% typowego czasu; bardziej zaawansowana wersja może lepiej Ci pasować.",
    other: "Wszystkie quizy w pierwszych {n} modułach zaliczasz za pierwszym razem i potrzebujesz około {percent}% typowego czasu; bardziej zaawansowana wersja może lepiej Ci pasować.",
  },
  placementTitle: "Test poziomujący",
  placementLead: "Odpowiedz na kilka pytań z tego kursu, a zaproponujemy wersję, która najlepiej do Ciebie pasuje.",
  placementAdvisory: "Ten test ma charakter doradczy. Tylko proponuje wersję: nic nie decyduje za Ciebie o Twoim poziomie, a wersję wybierasz sam(a).",
  placementNoTimer: "Nie ma limitu czasu. Twoje odpowiedzi nie są zapisywane, zapisujemy tylko wynik dla każdego poziomu.",
  questionOf: "Pytanie {n} z {total}",
  chooseOne: "Wybierz jedną odpowiedź.",
  chooseMany: "Zaznacz wszystkie pasujące odpowiedzi.",
  placementSubmit: "Pokaż moją sugestię",
  placementResultTitle: "Twój wynik",
  placementSuggest: "Proponujemy wersję „{label}”",
  placementReason: "Poprawne odpowiedzi: {correct} z {total} pytań na poziomie {level}. Dlatego proponujemy wersję „{label}”.",
  placementNoSuggestion: "Na podstawie Twoich odpowiedzi nie udało się zaproponować wersji. Możesz wybrać ją samodzielnie.",
  levelsInSentence: { beginner: "początkującym", intermediate: "średniozaawansowanym", expert: "eksperckim" },
  scoresHeading: "Twój wynik na każdym poziomie",
  scoreLine: "{level}: poprawnych odpowiedzi {correct} z {total}",
  usePlacement: "Użyj tej wersji",
  chooseAnother: "Wybierz inną wersję",
  placementAlreadyUsing: "Już korzystasz z tej wersji.",
  placementUnavailable: "Ten kurs nie ma testu poziomującego.",
  placementNeedsEnrol: "Test poziomujący jest dostępny po zapisaniu się na kurs.",
  placementSignIn: "Zaloguj się, aby rozwiązać test poziomujący.",
  placementFailed: "Nie udało się ocenić Twoich odpowiedzi. Spróbuj ponownie.",
  backToCourse: "Wróć do kursu",
  retake: "Rozwiąż test jeszcze raz",
};

const zh: VariantStrings = {
  pickerHeading: "选择你的版本",
  pickerLead: "这门课程有多个版本，每个版本都是面向不同水平或岗位的完整课程。",
  legend: "本课程的各个版本",
  levels: { beginner: "入门", intermediate: "中级", expert: "专家" },
  levelBadge: "级别：{level}",
  roleBadge: "岗位：{role}",
  durationBadge: "时长：{duration}",
  defaultBadge: "标准版本",
  currentBadge: "你当前的版本",
  visitorNote: "报名课程后即可选择你的版本。",
  switchCta: "切换到此版本",
  keepsProgress: "你的进度会保留。在新版本中内容相同的已完成课时会自动沿用，测验需要重新完成。",
  lockedNote: "你所在的机构已为你选定此版本，因此无法在这里更改。",
  assignedByOrg: "由你所在的机构指定",
  assignedByRule: "由你所在的机构指定（规则：{name}）",
  reasonSelf: "这是你自己选择的版本。",
  reasonPlacement: "你在完成分级测试后选择了此版本。",
  reasonHintEasier: "系统建议较简单的版本可能更适合你，你据此选择了此版本。",
  reasonHintHarder: "系统建议更进阶的版本可能更适合你，你据此选择了此版本。",
  placementLink: "不确定？做一下分级测试",
  statusSwitched: "你现在使用的版本是“{title}”。",
  statusCarried: { other: "已从你之前的版本沿用 {n} 个已完成的课时。" },
  statusCarriedNone: "没有需要沿用的已完成课时。你之前的进度仍然保留。",
  statusUnchanged: "你已在使用此版本，未做任何更改。",
  statusFailed: "无法切换版本，未做任何更改。请重试。",
  statusLocked: "你所在的机构已锁定此版本，因此未做更改。",
  statusNoAccess: "需要先报名课程才能选择版本。",
  statusDismissed: "已关闭本课程的建议。",
  hintHeading: "给你的建议",
  hintTitleEasier: "较简单的版本可能更适合你",
  hintTitleHarder: "更进阶的版本可能更适合你",
  hintLead: "这只是一个建议。除非你自己选择切换，否则你的版本不会改变。",
  hintSwitch: "切换到“{label}”",
  hintDismiss: "不用了",
  hintDismissNote: "如果选择“不用了”，我们将不再为本课程提供此类建议。",
  hintWhy: "为什么提出这个建议",
  hintTooHard: { other: "前 {n} 个模块对你来说有些吃力；这门课程的较简单版本可能更适合你。" },
  hintTooEasy: { other: "前 {n} 个模块的所有测验你都一次通过，且只用了通常约 {percent}% 的时间；更进阶的版本可能更适合你。" },
  placementTitle: "分级测试",
  placementLead: "回答几道本课程的题目，我们会建议最适合你的版本。",
  placementAdvisory: "此测试仅供参考，只会给出版本建议：不会替你决定水平，使用哪个版本由你自己选择。",
  placementNoTimer: "没有时间限制。你的答案不会被保存，只会记录每个级别的得分。",
  questionOf: "第 {n} 题，共 {total} 题",
  chooseOne: "请选择一个答案。",
  chooseMany: "请选择所有适用的答案。",
  placementSubmit: "查看我的建议",
  placementResultTitle: "你的结果",
  placementSuggest: "我们建议“{label}”",
  placementReason: "在{level}难度的 {total} 道题中你答对了 {correct} 道，因此我们建议“{label}”。",
  placementNoSuggestion: "根据你的答案无法给出版本建议，你可以自己选择。",
  levelsInSentence: { beginner: "入门", intermediate: "中级", expert: "专家" },
  scoresHeading: "你在各级别的得分",
  scoreLine: "{level}：答对 {correct} 道，共 {total} 道",
  usePlacement: "使用此版本",
  chooseAnother: "选择其他版本",
  placementAlreadyUsing: "你已在使用此版本。",
  placementUnavailable: "本课程没有分级测试。",
  placementNeedsEnrol: "报名课程后即可进行分级测试。",
  placementSignIn: "请登录后进行分级测试。",
  placementFailed: "无法评分，请重试。",
  backToCourse: "返回课程",
  retake: "重新测试",
};

const BY_LOCALE: Record<VariantLocale, VariantStrings> = { en, pl, zh };

export const variantStrings = (lang: string | null | undefined): VariantStrings => BY_LOCALE[trainingLocale(lang)];

/** A level the API reports (`beginner`, `intermediate`, `expert`); authors may also leave it empty or use their own word. */
export const isVariantLevel = (level: unknown): level is VariantLevel => typeof level === "string" && (VARIANT_LEVELS as readonly string[]).includes(level);

/** The name of a level for a badge: translated for the three known levels, the author's own text otherwise. */
export const levelName = (lang: string | null | undefined, level: string): string => (isVariantLevel(level) ? variantStrings(lang).levels[level] : level);

/** The name of a level inside a sentence ("5 of 6 intermediate questions"). */
export const levelInSentence = (lang: string | null | undefined, level: string): string => (isVariantLevel(level) ? variantStrings(lang).levelsInSentence[level] : level);

export interface HintReason {
  messageKey?: string;
  params?: Record<string, unknown>;
  message?: string;
}

const HINT_KEYS = { "insights.reason.variant_too_hard": "hintTooHard", "insights.reason.variant_too_easy": "hintTooEasy" } as const;

/**
 * The text of one hint reason in the learner's language, from the message key and its parameters (`modules`,
 * `percent`). A key this table does not know falls back to the API's own message; null when there is neither.
 */
export function hintReasonText(lang: string | null | undefined, reason: HintReason): string | null {
  const locale = trainingLocale(lang);
  const entry = reason.messageKey ? HINT_KEYS[reason.messageKey as keyof typeof HINT_KEYS] : undefined;
  if (entry) {
    const modules = Number(reason.params?.modules);
    const percent = Number(reason.params?.percent);
    if (Number.isFinite(modules) && modules >= 0 && (entry === "hintTooHard" || Number.isFinite(percent))) {
      const forms = BY_LOCALE[locale][entry];
      // `plural` would blank `{percent}`, so pick the form here and fill every parameter at once
      return fill(forms[new Intl.PluralRules(locale).select(modules)] ?? forms.other, { n: modules, percent: Math.round(percent) });
    }
  }
  const message = typeof reason.message === "string" ? reason.message.trim() : "";
  return message === "" ? null : message;
}
