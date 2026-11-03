/**
 * Interface strings of the sign-in page and the account page ("My learning", my courses, my tokens) in
 * English, Polish and Simplified Chinese. The language is the tenant default (`global.language` of the
 * public settings, P5R-1): these pages have no course to follow. Course titles, names and every API text
 * are never translated here. Plurals go through `Intl.PluralRules`.
 *
 * Native review of the Polish and Chinese text is an owner action (docs/translators.md in the docs site).
 */
import { fill, plural, trainingLocale, type Plural, type TrainingLocale } from "./training-strings.ts";

export { fill, plural, type Plural };
export type AccountLocale = TrainingLocale;

export const TOKEN_PRESET_KEYS = ["read-only", "author", "ci", "learner", "admin"] as const;
export const TOKEN_KIND_KEYS = ["cli", "agent", "ci", "integration"] as const;
export const TOKEN_FAILURES = ["unauthenticated", "forbidden", "invalid", "throttled", "unavailable"] as const;
export const SOCIAL_ERRORS = ["provider_denied", "provider_failed", "link_required", "signup_disabled", "email_unverified", "account_unavailable", "email_required"] as const;

interface Preset {
  label: string;
  description: string;
}

export interface AccountStrings {
  // ----- sign in
  signIn: string;
  /** The header link of a signed-in visitor. */
  myLearning: string;
  email: string;
  password: string;
  wrongCredentials: string;
  loginFailed: string;
  otherWays: string;
  /** `{provider}` is replaced. */
  continueWith: string;
  continueDemo: string;
  homeFallback: string;
  socialErrors: Record<(typeof SOCIAL_ERRORS)[number], string>;
  socialGeneric: string;

  // ----- account
  /** `{name}` is replaced. */
  pageTitle: string;
  learner: string;
  logOut: string;
  myCourses: string;
  coursesFailed: string;
  tryAgain: string;
  noCourses: string;
  browseAcademy: string;
  /** `{title}` is replaced. */
  progressIn: string;
  /** `{percent}`, `{done}` and `{total}` are replaced. */
  progressLine: string;
  start: string;
  review: string;
  continue: string;
  personalisationLink: string;
  personalisationText: string;

  // ----- my tokens
  tokensTitle: string;
  /** Text before and after the `ulams` command name. */
  tokensLeadBefore: string;
  tokensLeadAfter: string;
  tokenRevoked: string;
  tokenRevokeFailed: string;
  copyNowTitle: string;
  copyNowText: string;
  /** `{name}` is replaced. */
  newTokenLabel: string;
  secretUseBefore: string;
  secretUseMiddle: string;
  secretUseAfter: string;
  tokenListLabel: string;
  noTokens: string;
  unnamedToken: string;
  statusRevoked: string;
  statusExpired: string;
  statusActive: string;
  access: string;
  created: string;
  expires: string;
  lastUsed: string;
  commandLineLogin: string;
  revoke: string;
  /** `{name}` is replaced. */
  revokeLabel: string;
  never: string;
  unknown: string;
  createTitle: string;
  name: string;
  nameHint: string;
  whatMayItDo: string;
  validFor: string;
  /** `{n}` is replaced. */
  days: Plural;
  usedBy: string;
  createButton: string;
  presets: Record<(typeof TOKEN_PRESET_KEYS)[number], Preset>;
  kinds: Record<(typeof TOKEN_KIND_KEYS)[number], string>;
  failures: Record<(typeof TOKEN_FAILURES)[number], string>;
  errNameRequired: string;
  errNameLong: string;
  errNameChars: string;
  errAccessRequired: string;
  scopesEverything: string;
  /** `{list}` is replaced. */
  scopesChange: string;
  /** `{list}` is replaced. */
  scopesRead: string;
  scopesNone: string;
}

const en: AccountStrings = {
  signIn: "Sign in",
  myLearning: "My learning",
  email: "E-mail",
  password: "Password",
  wrongCredentials: "Wrong e-mail or password.",
  loginFailed: "Login failed. Try again.",
  otherWays: "Other ways to sign in",
  continueWith: "Continue with {provider}",
  continueDemo: "Continue as the demo student",
  homeFallback: "Home",
  socialErrors: {
    provider_denied: "Sign-in was cancelled.",
    provider_failed: "The sign-in provider did not accept the request. Try again.",
    link_required: "An account with this e-mail address already exists. Sign in with your password first.",
    signup_disabled: "This academy does not create accounts through this sign-in. Ask your administrator.",
    email_unverified: "Confirm your e-mail address with the link we sent you, then sign in again.",
    account_unavailable: "This account is not available.",
    email_required: "The provider did not share an e-mail address. Sign in with your e-mail and password instead.",
  },
  socialGeneric: "Sign-in failed. Try again.",

  pageTitle: "My learning — {name}",
  learner: "Learner",
  logOut: "Log out",
  myCourses: "My courses",
  coursesFailed: "Your courses could not be loaded right now.",
  tryAgain: "Try again",
  noCourses: "No courses yet.",
  browseAcademy: "Browse the academy",
  progressIn: "Progress in {title}",
  progressLine: "{percent}% · {done}/{total} topics",
  start: "Start",
  review: "Review",
  continue: "Continue",
  personalisationLink: "Personalisation & privacy",
  personalisationText: ": what this academy knows about how you learn, and your choices.",

  tokensTitle: "My tokens",
  tokensLeadBefore: "Tokens let the ",
  tokensLeadAfter:
    " command line, scripts and AI agents act as you. A token can never do more than your account can, and you choose how much less. Copy a new token once, store it safely, and revoke it when you no longer need it.",
  tokenRevoked: "Token revoked. It stops working at once.",
  tokenRevokeFailed: "The token could not be revoked. Try again.",
  copyNowTitle: "Copy your new token now",
  copyNowText: "It is shown only once. Keep it in a password manager or a secret store, never in code or a chat.",
  newTokenLabel: "Your new token “{name}”",
  secretUseBefore: "Use it as ",
  secretUseMiddle: " next to ",
  secretUseAfter: ", or run ",
  tokenListLabel: "Your tokens",
  noTokens: "You have no tokens yet.",
  unnamedToken: "Unnamed token",
  statusRevoked: "Revoked",
  statusExpired: "Expired",
  statusActive: "Active",
  access: "Access",
  created: "Created",
  expires: "Expires",
  lastUsed: "Last used",
  commandLineLogin: " · command line login",
  revoke: "Revoke",
  revokeLabel: "Revoke token {name}",
  never: "never",
  unknown: "unknown",
  createTitle: "Create a token",
  name: "Name",
  nameHint: "For example “laptop” or “nightly build”.",
  whatMayItDo: "What may it do?",
  validFor: "Valid for",
  days: { one: "{n} day", other: "{n} days" },
  usedBy: "Used by",
  createButton: "Create token",
  presets: {
    "read-only": { label: "Read only", description: "See courses, learners, progress and reports. Change nothing." },
    author: { label: "Author", description: "Create and edit courses, use the AI Course Builder and Living Course, read reports." },
    ci: { label: "Automation", description: "Edit courses and run the builder from a script or a CI job." },
    learner: { label: "Learning", description: "Your own learning only: profile, progress, quizzes, notes." },
    admin: { label: "Everything my account can do", description: "Full access as you. Use for a trusted tool only." },
  },
  kinds: { cli: "Command line (ulams)", agent: "AI agent", ci: "Automation or CI", integration: "Another application" },
  failures: {
    unauthenticated: "Your session has expired. Sign in again.",
    forbidden: "This sign-in cannot manage tokens.",
    invalid: "The token could not be created with these settings.",
    throttled: "Too many attempts. Wait a minute and try again.",
    unavailable: "The server could not be reached. Try again in a moment.",
  },
  errNameRequired: "Give the token a name so you can recognise it later.",
  errNameLong: "The name can have at most 100 characters.",
  errNameChars: "The name cannot contain < or >.",
  errAccessRequired: "Choose what the token may do.",
  scopesEverything: "Everything your account can do",
  scopesChange: "Change: {list}",
  scopesRead: "Read: {list}",
  scopesNone: "No scopes",
};

const pl: AccountStrings = {
  signIn: "Zaloguj się",
  myLearning: "Moja nauka",
  email: "Adres e-mail",
  password: "Hasło",
  wrongCredentials: "Nieprawidłowy adres e-mail lub hasło.",
  loginFailed: "Nie udało się zalogować. Spróbuj ponownie.",
  otherWays: "Inne sposoby logowania",
  continueWith: "Kontynuuj przez {provider}",
  continueDemo: "Kontynuuj jako uczeń demo",
  homeFallback: "Strona główna",
  socialErrors: {
    provider_denied: "Logowanie zostało anulowane.",
    provider_failed: "Dostawca logowania nie przyjął żądania. Spróbuj ponownie.",
    link_required: "Konto z tym adresem e-mail już istnieje. Najpierw zaloguj się hasłem.",
    signup_disabled: "Ta akademia nie tworzy kont przez to logowanie. Zapytaj administratora.",
    email_unverified: "Potwierdź adres e-mail linkiem, który wysłaliśmy, a potem zaloguj się ponownie.",
    account_unavailable: "To konto jest niedostępne.",
    email_required: "Dostawca nie udostępnił adresu e-mail. Zaloguj się adresem e-mail i hasłem.",
  },
  socialGeneric: "Nie udało się zalogować. Spróbuj ponownie.",

  pageTitle: "Moja nauka — {name}",
  learner: "Uczeń",
  logOut: "Wyloguj się",
  myCourses: "Moje kursy",
  coursesFailed: "Nie udało się teraz wczytać Twoich kursów.",
  tryAgain: "Spróbuj ponownie",
  noCourses: "Nie masz jeszcze kursów.",
  browseAcademy: "Przeglądaj akademię",
  progressIn: "Postęp w kursie {title}",
  progressLine: "{percent}% · {done}/{total} tematów",
  start: "Zacznij",
  review: "Powtórz",
  continue: "Kontynuuj",
  personalisationLink: "Personalizacja i prywatność",
  personalisationText: ": co ta akademia wie o tym, jak się uczysz, i jakie masz możliwości wyboru.",

  tokensTitle: "Moje tokeny",
  tokensLeadBefore: "Tokeny pozwalają wierszowi poleceń ",
  tokensLeadAfter:
    ", skryptom i agentom AI działać w Twoim imieniu. Token nigdy nie może więcej niż Twoje konto, a Ty wybierasz, o ile mniej. Skopiuj nowy token jednorazowo, przechowuj go bezpiecznie i unieważnij, gdy przestanie być potrzebny.",
  tokenRevoked: "Token unieważniony. Przestaje działać natychmiast.",
  tokenRevokeFailed: "Nie udało się unieważnić tokenu. Spróbuj ponownie.",
  copyNowTitle: "Skopiuj nowy token teraz",
  copyNowText: "Pokazujemy go tylko raz. Przechowuj go w menedżerze haseł lub magazynie sekretów, nigdy w kodzie ani na czacie.",
  newTokenLabel: "Twój nowy token „{name}”",
  secretUseBefore: "Użyj go jako ",
  secretUseMiddle: " razem z ",
  secretUseAfter: " albo uruchom ",
  tokenListLabel: "Twoje tokeny",
  noTokens: "Nie masz jeszcze tokenów.",
  unnamedToken: "Token bez nazwy",
  statusRevoked: "Unieważniony",
  statusExpired: "Wygasł",
  statusActive: "Aktywny",
  access: "Dostęp",
  created: "Utworzono",
  expires: "Wygasa",
  lastUsed: "Ostatnio użyto",
  commandLineLogin: " · logowanie z wiersza poleceń",
  revoke: "Unieważnij",
  revokeLabel: "Unieważnij token {name}",
  never: "nigdy",
  unknown: "nieznana",
  createTitle: "Utwórz token",
  name: "Nazwa",
  nameHint: "Na przykład „laptop” albo „nocna kompilacja”.",
  whatMayItDo: "Co może robić?",
  validFor: "Ważny przez",
  days: { one: "{n} dzień", few: "{n} dni", many: "{n} dni", other: "{n} dnia" },
  usedBy: "Używany przez",
  createButton: "Utwórz token",
  presets: {
    "read-only": { label: "Tylko odczyt", description: "Przeglądanie kursów, uczniów, postępów i raportów. Bez żadnych zmian." },
    author: { label: "Autor", description: "Tworzenie i edycja kursów, AI Course Builder i Living Course, odczyt raportów." },
    ci: { label: "Automatyzacja", description: "Edycja kursów i uruchamianie kreatora ze skryptu lub zadania CI." },
    learner: { label: "Nauka", description: "Tylko Twoja własna nauka: profil, postępy, quizy, notatki." },
    admin: { label: "Wszystko, co może moje konto", description: "Pełny dostęp jako Ty. Używaj tylko dla zaufanego narzędzia." },
  },
  kinds: { cli: "Wiersz poleceń (ulams)", agent: "Agent AI", ci: "Automatyzacja lub CI", integration: "Inna aplikacja" },
  failures: {
    unauthenticated: "Twoja sesja wygasła. Zaloguj się ponownie.",
    forbidden: "To logowanie nie pozwala zarządzać tokenami.",
    invalid: "Nie udało się utworzyć tokenu z tymi ustawieniami.",
    throttled: "Zbyt wiele prób. Odczekaj minutę i spróbuj ponownie.",
    unavailable: "Nie udało się połączyć z serwerem. Spróbuj za chwilę.",
  },
  errNameRequired: "Nadaj tokenowi nazwę, aby później go rozpoznać.",
  errNameLong: "Nazwa może mieć najwyżej 100 znaków.",
  errNameChars: "Nazwa nie może zawierać znaków < ani >.",
  errAccessRequired: "Wybierz, co token może robić.",
  scopesEverything: "Wszystko, co może Twoje konto",
  scopesChange: "Zmiany: {list}",
  scopesRead: "Odczyt: {list}",
  scopesNone: "Brak zakresów",
};

const zh: AccountStrings = {
  signIn: "登录",
  myLearning: "我的学习",
  email: "电子邮箱",
  password: "密码",
  wrongCredentials: "邮箱或密码错误。",
  loginFailed: "登录失败，请重试。",
  otherWays: "其他登录方式",
  continueWith: "使用 {provider} 继续",
  continueDemo: "以演示学员身份继续",
  homeFallback: "首页",
  socialErrors: {
    provider_denied: "登录已取消。",
    provider_failed: "登录服务商未接受该请求，请重试。",
    link_required: "已存在使用此邮箱的账号。请先用密码登录。",
    signup_disabled: "此学院不通过该登录方式创建账号。请联系管理员。",
    email_unverified: "请点击我们发送的链接确认你的邮箱，然后重新登录。",
    account_unavailable: "此账号不可用。",
    email_required: "服务商未提供邮箱地址。请改用邮箱和密码登录。",
  },
  socialGeneric: "登录失败，请重试。",

  pageTitle: "我的学习 — {name}",
  learner: "学员",
  logOut: "退出登录",
  myCourses: "我的课程",
  coursesFailed: "暂时无法加载你的课程。",
  tryAgain: "重试",
  noCourses: "还没有课程。",
  browseAcademy: "浏览学院",
  progressIn: "{title} 的进度",
  progressLine: "{percent}% · {done}/{total} 个主题",
  start: "开始",
  review: "回顾",
  continue: "继续",
  personalisationLink: "个性化与隐私",
  personalisationText: "：本学院了解你哪些学习方式，以及你可以做的选择。",

  tokensTitle: "我的令牌",
  tokensLeadBefore: "令牌让 ",
  tokensLeadAfter: " 命令行、脚本和 AI 智能体以你的身份操作。令牌的权限永远不会超过你的账号，你可以选择缩小多少。新令牌只需复制一次，请妥善保管，不再需要时请撤销。",
  tokenRevoked: "令牌已撤销，立即失效。",
  tokenRevokeFailed: "无法撤销令牌，请重试。",
  copyNowTitle: "请立即复制你的新令牌",
  copyNowText: "它只显示一次。请保存在密码管理器或密钥库中，切勿放进代码或聊天记录。",
  newTokenLabel: "你的新令牌“{name}”",
  secretUseBefore: "将它用作 ",
  secretUseMiddle: "，并与 ",
  secretUseAfter: " 配合使用，或运行 ",
  tokenListLabel: "你的令牌",
  noTokens: "你还没有令牌。",
  unnamedToken: "未命名令牌",
  statusRevoked: "已撤销",
  statusExpired: "已过期",
  statusActive: "有效",
  access: "权限",
  created: "创建时间",
  expires: "过期时间",
  lastUsed: "最近使用",
  commandLineLogin: " · 命令行登录",
  revoke: "撤销",
  revokeLabel: "撤销令牌 {name}",
  never: "从未",
  unknown: "未知",
  createTitle: "创建令牌",
  name: "名称",
  nameHint: "例如“笔记本电脑”或“夜间构建”。",
  whatMayItDo: "它可以做什么？",
  validFor: "有效期",
  days: { other: "{n} 天" },
  usedBy: "使用方",
  createButton: "创建令牌",
  presets: {
    "read-only": { label: "只读", description: "查看课程、学员、进度和报告，不做任何更改。" },
    author: { label: "作者", description: "创建和编辑课程，使用 AI 课程构建器和 Living Course，查看报告。" },
    ci: { label: "自动化", description: "通过脚本或 CI 任务编辑课程并运行构建器。" },
    learner: { label: "学习", description: "仅限你自己的学习：个人资料、进度、测验、笔记。" },
    admin: { label: "我的账号能做的一切", description: "以你的身份拥有完全访问权限。仅用于可信的工具。" },
  },
  kinds: { cli: "命令行（ulams）", agent: "AI 智能体", ci: "自动化或 CI", integration: "其他应用" },
  failures: {
    unauthenticated: "你的会话已过期，请重新登录。",
    forbidden: "此登录无法管理令牌。",
    invalid: "无法使用这些设置创建令牌。",
    throttled: "尝试次数过多，请等待一分钟后重试。",
    unavailable: "无法连接到服务器，请稍后重试。",
  },
  errNameRequired: "请为令牌命名，以便日后识别。",
  errNameLong: "名称最多 100 个字符。",
  errNameChars: "名称不能包含 < 或 >。",
  errAccessRequired: "请选择令牌可以做什么。",
  scopesEverything: "你的账号能做的一切",
  scopesChange: "更改：{list}",
  scopesRead: "读取：{list}",
  scopesNone: "无权限范围",
};

const BY_LOCALE: Record<AccountLocale, AccountStrings> = { en, pl, zh };

export const accountStrings = (lang: string | null | undefined): AccountStrings => BY_LOCALE[trainingLocale(lang)];
