/**
 * Interface strings of the course tutor panel in English, Polish and Simplified Chinese. The
 * elements run in the browser, so the language comes from the element's `lang` attribute or the
 * page's `<html lang>`; without a match, English. Answers, citations and course text come from the
 * API and are never translated here.
 */
export interface TutorStrings {
  launcher: string;
  title: string;
  close: string;
  /** Visible on every answer. */
  aiLabel: string;
  log: string;
  you: string;
  tutor: string;
  sources: string;
  /** `{n}` and `{limit}` are replaced. */
  remaining: string;
  /** `{n}` is replaced. */
  remainingNone: string;
  attemptNotice: string;
  question: string;
  hint: string;
  /** `{n}` and `{max}` are replaced. */
  counter: string;
  ask: string;
  writing: string;
  empty: string;
  refusalOutOfScope: string;
  refusalAttempt: string;
  refusalUnsafe: string;
  limit: string;
  backToLesson: string;
  disabled: string;
  network: string;
  failed: string;
  retry: string;
  earlier: string;
  /** `{date}` is replaced. */
  conversationOf: string;
  noEarlier: string;
  /** `{date}` is replaced. */
  keptUntil: string;
  open: string;
  del: string;
  deleteConfirm: string;
  deleteYes: string;
  deleteCancel: string;
  deleted: string;
  newConversation: string;
  loading: string;
  opened: string;
}

const en: TutorStrings = {
  launcher: "Ask the course tutor",
  title: "Course tutor",
  close: "Close the tutor",
  aiLabel: "AI tutor · answers only from this course",
  log: "Conversation with the course tutor",
  you: "You asked",
  tutor: "Tutor",
  sources: "Sources in this course",
  remaining: "{n} of {limit} questions left today",
  remainingNone: "You have used today's {n} questions. Come back tomorrow.",
  attemptNotice: "A quiz is in progress. The tutor explains concepts from the lessons but will not help with quiz answers until you submit it.",
  question: "Your question",
  hint: "Ctrl+Enter sends. The tutor answers only from this course.",
  counter: "{n} of {max} characters",
  ask: "Ask",
  writing: "The tutor is writing…",
  empty: "Ask about anything in this course. Every answer links to the lesson it comes from.",
  refusalOutOfScope: "This is outside the course, so the tutor did not answer it.",
  refusalAttempt: "A quiz is in progress, so the tutor did not answer this.",
  refusalUnsafe: "The tutor could not answer that. Try asking in a different way.",
  limit: "You have used today's questions. Try again tomorrow.",
  backToLesson: "Back to the lesson",
  disabled: "The tutor is not available right now.",
  network: "Could not reach the tutor. Your question is kept.",
  failed: "Something went wrong. Your question is kept.",
  retry: "Try again",
  earlier: "Earlier conversations",
  conversationOf: "Conversation of {date}",
  noEarlier: "No earlier conversations in this course.",
  keptUntil: "Deleted automatically on {date}",
  open: "Open",
  del: "Delete",
  deleteConfirm: "Delete this conversation for good?",
  deleteYes: "Yes, delete it",
  deleteCancel: "Keep it",
  deleted: "Conversation deleted.",
  newConversation: "New conversation",
  loading: "Loading…",
  opened: "Conversation opened.",
};

const pl: TutorStrings = {
  launcher: "Zapytaj korepetytora kursu",
  title: "Korepetytor kursu",
  close: "Zamknij korepetytora",
  aiLabel: "Korepetytor AI · odpowiada tylko na podstawie tego kursu",
  log: "Rozmowa z korepetytorem kursu",
  you: "Pytasz",
  tutor: "Korepetytor",
  sources: "Źródła w tym kursie",
  remaining: "Pozostało pytań na dziś: {n} z {limit}",
  remainingNone: "Wykorzystano dzisiejsze pytania ({n}). Wróć jutro.",
  attemptNotice: "Trwa quiz. Korepetytor wyjaśnia pojęcia z lekcji, ale nie pomoże w odpowiedziach na quiz, dopóki go nie zakończysz.",
  question: "Twoje pytanie",
  hint: "Ctrl+Enter wysyła. Korepetytor odpowiada tylko na podstawie tego kursu.",
  counter: "{n} z {max} znaków",
  ask: "Zapytaj",
  writing: "Korepetytor pisze…",
  empty: "Zapytaj o cokolwiek z tego kursu. Każda odpowiedź prowadzi do lekcji, z której pochodzi.",
  refusalOutOfScope: "To wykracza poza kurs, więc korepetytor nie odpowiedział.",
  refusalAttempt: "Trwa quiz, więc korepetytor nie odpowiedział na to pytanie.",
  refusalUnsafe: "Korepetytor nie mógł odpowiedzieć. Spróbuj zadać pytanie inaczej.",
  limit: "Wykorzystano dzisiejsze pytania. Spróbuj jutro.",
  backToLesson: "Wróć do lekcji",
  disabled: "Korepetytor jest teraz niedostępny.",
  network: "Nie udało się połączyć z korepetytorem. Pytanie zostało zachowane.",
  failed: "Coś poszło nie tak. Pytanie zostało zachowane.",
  retry: "Spróbuj ponownie",
  earlier: "Wcześniejsze rozmowy",
  conversationOf: "Rozmowa z {date}",
  noEarlier: "Brak wcześniejszych rozmów w tym kursie.",
  keptUntil: "Zostanie usunięta automatycznie {date}",
  open: "Otwórz",
  del: "Usuń",
  deleteConfirm: "Usunąć tę rozmowę na stałe?",
  deleteYes: "Tak, usuń",
  deleteCancel: "Zostaw",
  deleted: "Rozmowa usunięta.",
  newConversation: "Nowa rozmowa",
  loading: "Wczytywanie…",
  opened: "Rozmowa otwarta.",
};

const zh: TutorStrings = {
  launcher: "向课程助教提问",
  title: "课程助教",
  close: "关闭助教",
  aiLabel: "AI 助教 · 仅根据本课程作答",
  log: "与课程助教的对话",
  you: "你的提问",
  tutor: "助教",
  sources: "本课程中的来源",
  remaining: "今日还可提问 {n} / {limit} 次",
  remainingNone: "今日 {n} 次提问已用完，请明天再来。",
  attemptNotice: "测验正在进行。助教会讲解课程中的概念，但在你提交测验之前不会协助回答测验题。",
  question: "你的问题",
  hint: "按 Ctrl+Enter 发送。助教仅根据本课程作答。",
  counter: "{n} / {max} 个字符",
  ask: "提问",
  writing: "助教正在回答…",
  empty: "可以询问本课程的任何内容。每个回答都会链接到其出处的课时。",
  refusalOutOfScope: "这超出了课程范围，助教没有作答。",
  refusalAttempt: "测验正在进行，助教没有回答这个问题。",
  refusalUnsafe: "助教无法回答这个问题，请换一种问法。",
  limit: "今日提问次数已用完，请明天再试。",
  backToLesson: "返回课时",
  disabled: "助教暂时不可用。",
  network: "无法连接助教，你的问题已保留。",
  failed: "出了点问题，你的问题已保留。",
  retry: "重试",
  earlier: "以往的对话",
  conversationOf: "{date} 的对话",
  noEarlier: "本课程还没有以往的对话。",
  keptUntil: "将于 {date} 自动删除",
  open: "打开",
  del: "删除",
  deleteConfirm: "确定永久删除这段对话吗？",
  deleteYes: "是，删除",
  deleteCancel: "保留",
  deleted: "对话已删除。",
  newConversation: "新对话",
  loading: "加载中…",
  opened: "对话已打开。",
};

const BY_LANGUAGE: Record<string, TutorStrings> = { en, pl, zh };

export function tutorStrings(lang: string | null | undefined): TutorStrings {
  const code = (lang ?? "").toLowerCase().split("-")[0] ?? "";
  return BY_LANGUAGE[code] ?? en;
}

export const fill = (template: string, values: Record<string, string | number>): string =>
  template.replace(/\{(\w+)\}/g, (_, key: string) => String(values[key] ?? ""));
