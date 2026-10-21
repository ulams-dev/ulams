/**
 * Interface strings of "my training, certificates and skills" (account pages, the finish page and the
 * `<ulams-certificate-badge>` widget) in English, Polish and Simplified Chinese. Course titles, skill
 * names and every other API text are never translated here. Plural forms go through `Intl.PluralRules`
 * (Polish has `one`, `few`, `many`).
 */
export type TrainingLocale = "en" | "pl" | "zh";

export type Plural = Partial<Record<Intl.LDMLPluralRule, string>> & { other: string };

export interface TrainingStrings {
  // account summary
  accountTrainingLink: string;
  accountCertificatesLink: string;
  accountSkillsLink: string;
  accountTeamLink: string;
  /** `{n}` is replaced. */
  summaryDue: Plural;
  summaryOverdue: Plural;
  summaryCertificates: Plural;
  summarySkills: Plural;
  summaryNothingDue: string;
  // training page
  trainingTitle: string;
  trainingLead: string;
  requiredHeading: string;
  requiredEmpty: string;
  statusAssigned: string;
  statusDueSoon: string;
  /** `{n}` is replaced. */
  statusOverdue: Plural;
  statusCompleted: string;
  statusCancelled: string;
  /** `{date}` is replaced. */
  dueOn: string;
  noDueDate: string;
  /** `{date}` is replaced. */
  completedOn: string;
  start: string;
  cont: string;
  review: string;
  viewCertificate: string;
  pathsHeading: string;
  pathsEmpty: string;
  stepDone: string;
  stepAvailable: string;
  stepLocked: string;
  /** `{courses}` is replaced. */
  unlocksAfter: string;
  pathCompleted: string;
  /** `{done}` and `{total}` are replaced. */
  pathProgress: string;
  loadFailed: string;
  retry: string;
  // certificates
  certificatesHeading: string;
  certificatesEmpty: string;
  issued: string;
  validUntil: string;
  doesNotExpire: string;
  courseVersion: string;
  certValid: string;
  /** `{n}` is replaced. */
  certExpiring: Plural;
  certExpired: string;
  /** `{date}` is replaced. */
  certRevoked: string;
  download: string;
  /** `{title}` is replaced. */
  downloadLabel: string;
  verify: string;
  copyBadge: string;
  badgeCopied: string;
  badgeCopyFailed: string;
  badgeCodeLabel: string;
  // skills
  skillsTitle: string;
  skillsLead: string;
  skillsEmpty: string;
  skillsBrowse: string;
  /** `{date}` is replaced. */
  earnedOn: string;
  /** `{date}` is replaced. */
  lapsesOn: string;
  doesNotLapse: string;
  provenBy: string;
  skillsOther: string;
  // team
  teamTitle: string;
  teamLead: string;
  teamEmpty: string;
  filterLabel: string;
  filterAll: string;
  filterOverdue: string;
  filterApply: string;
  learner: string;
  course: string;
  status: string;
  due: string;
  teamOverdueFirst: string;
  // finish page
  finishCertificate: string;
  finishIssuedTo: string;
  finishPreview: string;
  finishPreviewNote: string;
  finishNotIssued: string;
  breadcrumbHome: string;
  // widget
  badgeLoading: string;
  badgeFailed: string;
  badgeUnknown: string;
  badgeMissingId: string;
  badgeHolder: string;
  badgeCourse: string;
  badgeIssued: string;
  badgeExpires: string;
  badgeRevokedOn: string;
  badgeVerifyLink: string;
  /** `{status}` and `{course}` are replaced. */
  badgeLabel: string;
}

const en: TrainingStrings = {
  accountTrainingLink: "My training",
  accountCertificatesLink: "Certificates",
  accountSkillsLink: "Skills",
  accountTeamLink: "My team",
  summaryDue: { one: "{n} training due this month", other: "{n} trainings due this month" },
  summaryOverdue: { one: "{n} training overdue", other: "{n} trainings overdue" },
  summaryCertificates: { one: "{n} certificate", other: "{n} certificates" },
  summarySkills: { one: "{n} skill", other: "{n} skills" },
  summaryNothingDue: "No required training is due.",
  trainingTitle: "My training",
  trainingLead: "Courses you were asked to complete, your learning paths and the certificates you earned.",
  requiredHeading: "Required training",
  requiredEmpty: "No required training. Courses you are asked to complete appear here.",
  statusAssigned: "Assigned",
  statusDueSoon: "Due soon",
  statusOverdue: { one: "Overdue by {n} day", other: "Overdue by {n} days" },
  statusCompleted: "Completed",
  statusCancelled: "Cancelled",
  dueOn: "Due {date}",
  noDueDate: "No due date",
  completedOn: "Completed {date}",
  start: "Start",
  cont: "Continue",
  review: "Review",
  viewCertificate: "View certificate",
  pathsHeading: "Learning paths",
  pathsEmpty: "You are not on a learning path. Paths you are enrolled in appear here.",
  stepDone: "Done",
  stepAvailable: "Available",
  stepLocked: "Locked",
  unlocksAfter: "Unlocks after: {courses}",
  pathCompleted: "Path completed",
  pathProgress: "{done} of {total} steps done",
  loadFailed: "This could not be loaded right now.",
  retry: "Try again",
  certificatesHeading: "Certificates",
  certificatesEmpty: "No certificates yet. You receive one when you finish a course that issues certificates.",
  issued: "Issued",
  validUntil: "Valid until",
  doesNotExpire: "Does not expire",
  courseVersion: "Course version",
  certValid: "Valid",
  certExpiring: { one: "Expiring in {n} day", other: "Expiring in {n} days" },
  certExpired: "Expired",
  certRevoked: "Revoked on {date}",
  download: "Download PDF",
  downloadLabel: "Download the PDF of {title}",
  verify: "Verify",
  copyBadge: "Copy badge code",
  badgeCopied: "Badge code copied.",
  badgeCopyFailed: "Could not copy. Select the code below and copy it.",
  badgeCodeLabel: "Badge code to paste on your own page",
  skillsTitle: "My skills",
  skillsLead: "Skills confirmed by the courses you have completed. A skill lapses when the certificate that proves it does.",
  skillsEmpty: "No skills yet. Complete a course to earn its skills.",
  skillsBrowse: "Browse the courses",
  earnedOn: "Earned {date}",
  lapsesOn: "Lapses {date}",
  doesNotLapse: "Does not lapse",
  provenBy: "Proven by",
  skillsOther: "Other skills",
  teamTitle: "My team",
  teamLead: "Required training of the people who report to you. Overdue training comes first.",
  teamEmpty: "No training matches. People who report to you appear here once they have required training.",
  filterLabel: "Show",
  filterAll: "All",
  filterOverdue: "Overdue",
  filterApply: "Apply",
  learner: "Learner",
  course: "Course",
  status: "Status",
  due: "Due",
  teamOverdueFirst: "Sorted with overdue training first.",
  finishCertificate: "Your certificate",
  finishIssuedTo: "Issued to",
  finishPreview: "Certificate preview",
  finishPreviewNote: "Preview: finish every topic to receive it",
  finishNotIssued: "If this course issues a certificate, it appears under My training as soon as it is ready.",
  breadcrumbHome: "My learning",
  badgeLoading: "Checking the certificate…",
  badgeFailed: "The certificate could not be checked right now.",
  badgeUnknown: "This certificate id is not known.",
  badgeMissingId: "Add a certificate-id to show a certificate.",
  badgeHolder: "Holder",
  badgeCourse: "Course",
  badgeIssued: "Issued",
  badgeExpires: "Valid until",
  badgeRevokedOn: "Revoked on",
  badgeVerifyLink: "Verify this certificate",
  badgeLabel: "Certificate: {status}, {course}",
};

const pl: TrainingStrings = {
  accountTrainingLink: "Moje szkolenia",
  accountCertificatesLink: "Certyfikaty",
  accountSkillsLink: "Umiejętności",
  accountTeamLink: "Mój zespół",
  summaryDue: { one: "{n} szkolenie do ukończenia w tym miesiącu", few: "{n} szkolenia do ukończenia w tym miesiącu", many: "{n} szkoleń do ukończenia w tym miesiącu", other: "{n} szkolenia do ukończenia w tym miesiącu" },
  summaryOverdue: { one: "{n} szkolenie po terminie", few: "{n} szkolenia po terminie", many: "{n} szkoleń po terminie", other: "{n} szkolenia po terminie" },
  summaryCertificates: { one: "{n} certyfikat", few: "{n} certyfikaty", many: "{n} certyfikatów", other: "{n} certyfikatu" },
  summarySkills: { one: "{n} umiejętność", few: "{n} umiejętności", many: "{n} umiejętności", other: "{n} umiejętności" },
  summaryNothingDue: "Żadne obowiązkowe szkolenie nie czeka na ukończenie.",
  trainingTitle: "Moje szkolenia",
  trainingLead: "Kursy, które masz ukończyć, Twoje ścieżki nauki i zdobyte certyfikaty.",
  requiredHeading: "Szkolenia obowiązkowe",
  requiredEmpty: "Brak szkoleń obowiązkowych. Kursy, które masz ukończyć, pojawią się tutaj.",
  statusAssigned: "Przypisane",
  statusDueSoon: "Termin wkrótce",
  statusOverdue: { one: "Po terminie o {n} dzień", few: "Po terminie o {n} dni", many: "Po terminie o {n} dni", other: "Po terminie o {n} dnia" },
  statusCompleted: "Ukończone",
  statusCancelled: "Anulowane",
  dueOn: "Termin: {date}",
  noDueDate: "Bez terminu",
  completedOn: "Ukończono {date}",
  start: "Rozpocznij",
  cont: "Kontynuuj",
  review: "Powtórz",
  viewCertificate: "Zobacz certyfikat",
  pathsHeading: "Ścieżki nauki",
  pathsEmpty: "Nie jesteś na żadnej ścieżce nauki. Ścieżki, do których jesteś zapisany, pojawią się tutaj.",
  stepDone: "Ukończony",
  stepAvailable: "Dostępny",
  stepLocked: "Zablokowany",
  unlocksAfter: "Odblokuje się po: {courses}",
  pathCompleted: "Ścieżka ukończona",
  pathProgress: "Ukończone kroki: {done} z {total}",
  loadFailed: "Nie udało się teraz tego wczytać.",
  retry: "Spróbuj ponownie",
  certificatesHeading: "Certyfikaty",
  certificatesEmpty: "Nie masz jeszcze certyfikatów. Otrzymasz go po ukończeniu kursu, który go wydaje.",
  issued: "Wydany",
  validUntil: "Ważny do",
  doesNotExpire: "Bez daty ważności",
  courseVersion: "Wersja kursu",
  certValid: "Ważny",
  certExpiring: { one: "Wygasa za {n} dzień", few: "Wygasa za {n} dni", many: "Wygasa za {n} dni", other: "Wygasa za {n} dnia" },
  certExpired: "Wygasł",
  certRevoked: "Unieważniony {date}",
  download: "Pobierz PDF",
  downloadLabel: "Pobierz PDF: {title}",
  verify: "Zweryfikuj",
  copyBadge: "Skopiuj kod plakietki",
  badgeCopied: "Kod plakietki skopiowany.",
  badgeCopyFailed: "Nie udało się skopiować. Zaznacz kod poniżej i skopiuj go.",
  badgeCodeLabel: "Kod plakietki do wklejenia na własnej stronie",
  skillsTitle: "Moje umiejętności",
  skillsLead: "Umiejętności potwierdzone ukończonymi kursami. Umiejętność wygasa razem z certyfikatem, który ją potwierdza.",
  skillsEmpty: "Brak umiejętności. Ukończ kurs, aby je zdobyć.",
  skillsBrowse: "Przeglądaj kursy",
  earnedOn: "Zdobyta {date}",
  lapsesOn: "Wygasa {date}",
  doesNotLapse: "Nie wygasa",
  provenBy: "Potwierdzają ją",
  skillsOther: "Pozostałe umiejętności",
  teamTitle: "Mój zespół",
  teamLead: "Szkolenia obowiązkowe osób, które Ci podlegają. Zaległe są na początku.",
  teamEmpty: "Brak pasujących szkoleń. Osoby, które Ci podlegają, pojawią się tutaj, gdy będą miały szkolenia obowiązkowe.",
  filterLabel: "Pokaż",
  filterAll: "Wszystkie",
  filterOverdue: "Po terminie",
  filterApply: "Zastosuj",
  learner: "Uczestnik",
  course: "Kurs",
  status: "Status",
  due: "Termin",
  teamOverdueFirst: "Zaległe szkolenia są na początku.",
  finishCertificate: "Twój certyfikat",
  finishIssuedTo: "Wydany dla",
  finishPreview: "Podgląd certyfikatu",
  finishPreviewNote: "Podgląd: ukończ wszystkie tematy, aby go otrzymać",
  finishNotIssued: "Jeśli ten kurs wydaje certyfikat, pojawi się w sekcji Moje szkolenia, gdy tylko będzie gotowy.",
  breadcrumbHome: "Moja nauka",
  badgeLoading: "Sprawdzanie certyfikatu…",
  badgeFailed: "Nie udało się teraz sprawdzić certyfikatu.",
  badgeUnknown: "Nie znamy tego numeru certyfikatu.",
  badgeMissingId: "Dodaj certificate-id, aby pokazać certyfikat.",
  badgeHolder: "Posiadacz",
  badgeCourse: "Kurs",
  badgeIssued: "Wydany",
  badgeExpires: "Ważny do",
  badgeRevokedOn: "Unieważniony",
  badgeVerifyLink: "Zweryfikuj ten certyfikat",
  badgeLabel: "Certyfikat: {status}, {course}",
};

const zh: TrainingStrings = {
  accountTrainingLink: "我的培训",
  accountCertificatesLink: "证书",
  accountSkillsLink: "技能",
  accountTeamLink: "我的团队",
  summaryDue: { other: "本月有 {n} 项培训到期" },
  summaryOverdue: { other: "{n} 项培训已逾期" },
  summaryCertificates: { other: "{n} 份证书" },
  summarySkills: { other: "{n} 项技能" },
  summaryNothingDue: "没有到期的必修培训。",
  trainingTitle: "我的培训",
  trainingLead: "要求你完成的课程、你的学习路径和已获得的证书。",
  requiredHeading: "必修培训",
  requiredEmpty: "没有必修培训。要求你完成的课程会显示在这里。",
  statusAssigned: "已分配",
  statusDueSoon: "即将到期",
  statusOverdue: { other: "已逾期 {n} 天" },
  statusCompleted: "已完成",
  statusCancelled: "已取消",
  dueOn: "截止日期：{date}",
  noDueDate: "无截止日期",
  completedOn: "完成于 {date}",
  start: "开始",
  cont: "继续",
  review: "复习",
  viewCertificate: "查看证书",
  pathsHeading: "学习路径",
  pathsEmpty: "你没有加入任何学习路径。已加入的路径会显示在这里。",
  stepDone: "已完成",
  stepAvailable: "可学习",
  stepLocked: "未解锁",
  unlocksAfter: "完成以下课程后解锁：{courses}",
  pathCompleted: "路径已完成",
  pathProgress: "已完成 {done}/{total} 步",
  loadFailed: "暂时无法加载。",
  retry: "重试",
  certificatesHeading: "证书",
  certificatesEmpty: "还没有证书。完成会颁发证书的课程后即可获得。",
  issued: "颁发日期",
  validUntil: "有效期至",
  doesNotExpire: "长期有效",
  courseVersion: "课程版本",
  certValid: "有效",
  certExpiring: { other: "{n} 天后到期" },
  certExpired: "已过期",
  certRevoked: "已于 {date} 撤销",
  download: "下载 PDF",
  downloadLabel: "下载 PDF：{title}",
  verify: "验证",
  copyBadge: "复制徽章代码",
  badgeCopied: "徽章代码已复制。",
  badgeCopyFailed: "无法复制。请选中下方代码后手动复制。",
  badgeCodeLabel: "粘贴到你自己页面的徽章代码",
  skillsTitle: "我的技能",
  skillsLead: "由你完成的课程确认的技能。技能会随证明它的证书一同失效。",
  skillsEmpty: "还没有技能。完成课程即可获得相应技能。",
  skillsBrowse: "浏览课程",
  earnedOn: "获得于 {date}",
  lapsesOn: "{date} 失效",
  doesNotLapse: "长期有效",
  provenBy: "证明课程",
  skillsOther: "其他技能",
  teamTitle: "我的团队",
  teamLead: "向你汇报的人员的必修培训，逾期的排在前面。",
  teamEmpty: "没有符合条件的培训。向你汇报的人员有必修培训后会显示在这里。",
  filterLabel: "显示",
  filterAll: "全部",
  filterOverdue: "已逾期",
  filterApply: "应用",
  learner: "学员",
  course: "课程",
  status: "状态",
  due: "截止日期",
  teamOverdueFirst: "逾期培训排在最前。",
  finishCertificate: "你的证书",
  finishIssuedTo: "颁发给",
  finishPreview: "证书预览",
  finishPreviewNote: "预览：完成所有主题后即可获得",
  finishNotIssued: "如果此课程颁发证书，证书准备好后会立即出现在“我的培训”中。",
  breadcrumbHome: "我的学习",
  badgeLoading: "正在核验证书…",
  badgeFailed: "暂时无法核验证书。",
  badgeUnknown: "无此证书编号。",
  badgeMissingId: "请添加 certificate-id 以显示证书。",
  badgeHolder: "持有人",
  badgeCourse: "课程",
  badgeIssued: "颁发日期",
  badgeExpires: "有效期至",
  badgeRevokedOn: "撤销日期",
  badgeVerifyLink: "验证此证书",
  badgeLabel: "证书：{status}，{course}",
};

const BY_LOCALE: Record<TrainingLocale, TrainingStrings> = { en, pl, zh };

/** `pl` or `zh` when the language tag starts with that code, else `en`. */
export function trainingLocale(lang: string | null | undefined): TrainingLocale {
  const code = (lang ?? "").toLowerCase().split(/[-_]/)[0] ?? "";
  return code === "pl" || code === "zh" ? code : "en";
}

export const trainingStrings = (lang: string | null | undefined): TrainingStrings => BY_LOCALE[trainingLocale(lang)];

/**
 * The language of an account page from the browser's `Accept-Language` (first supported entry wins),
 * `en` when none matches. Used until a tenant default language exists.
 */
export function acceptedLocale(header: string | null | undefined): TrainingLocale {
  for (const part of (header ?? "").split(",")) {
    const code = trainingLocale(part.split(";")[0]?.trim());
    const tag = (part.split(";")[0] ?? "").trim().toLowerCase();
    if (code !== "en" || tag.startsWith("en")) return code;
  }
  return "en";
}

export const fill = (template: string, values: Record<string, string | number>): string =>
  template.replace(/\{(\w+)\}/g, (_, key: string) => String(values[key] ?? ""));

/** Picks the plural form for `n` in the locale and fills `{n}`. */
export function plural(locale: TrainingLocale, forms: Plural, n: number): string {
  const rule = new Intl.PluralRules(locale).select(n);
  return fill(forms[rule] ?? forms.other, { n });
}

/** A date in the locale, always in UTC so server and browser agree. */
export function formatDate(locale: TrainingLocale, iso: string | null | undefined): string {
  if (!iso) return "";
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return "";
  return new Intl.DateTimeFormat(locale === "zh" ? "zh-CN" : locale === "pl" ? "pl-PL" : "en-GB", { day: "numeric", month: "long", year: "numeric", timeZone: "UTC" }).format(date);
}
