import {
  createCertificateClient,
  createComplianceClient,
  type Assignment,
  type AssignmentStatus,
  type Certificate,
  type PathEnrollment,
  type Skill,
  type Tenant,
} from "@ulams/sdk";
import { fill, formatDate, plural, trainingStrings, type TrainingLocale } from "@ulams/ui/training-strings";
import { tenantFetch } from "./api-transport.ts";
import { ownOrigins } from "./bff.ts";

/**
 * "My training, certificates and skills": the learner's required training, learning paths, certificates and
 * skills, and a manager's read-only view of their reports (plan phase-5-learner-ui, section 3.7). The API
 * scopes every call to the caller; this module loads with the learner's own token, never throws (a missing
 * piece is `null`, so the page shows an error for that block only) and turns the rows into words and links.
 */
const DAY = 86_400_000;
/** An open assignment due within this many days is "due soon". */
export const DUE_SOON_DAYS = 14;
/** A certificate that lapses within this many days says so. */
export const EXPIRING_DAYS = 30;

const options = (tenant: Tenant, token: string) => ({ baseUrl: tenant.apiUrl, fetch: tenantFetch(tenant), token, timeoutMs: 8_000 });
const settle = <T>(promise: Promise<T>): Promise<T | null> => promise.catch(() => null);

export interface TrainingData {
  assignments: Assignment[] | null;
  paths: PathEnrollment[] | null;
  certificates: Certificate[] | null;
  skills: Skill[] | null;
}

export async function loadTraining(tenant: Tenant, token: string): Promise<TrainingData> {
  const compliance = createComplianceClient(options(tenant, token));
  const certificates = createCertificateClient(options(tenant, token));
  const [assignments, paths, certs, skills] = await Promise.all([
    settle(compliance.myAssignments()),
    settle(compliance.myPaths()),
    settle(certificates.list()),
    settle(compliance.mySkills()),
  ]);
  return { assignments, paths, certificates: certs ? certs.items : null, skills };
}

export const loadSkills = (tenant: Tenant, token: string): Promise<Skill[] | null> => settle(createComplianceClient(options(tenant, token)).mySkills());

/** The reports' assignments of a manager; `[]` for everyone else, `null` when the API failed. */
export async function loadTeam(tenant: Tenant, token: string, status?: AssignmentStatus): Promise<Assignment[] | null> {
  const page = await settle(createComplianceClient(options(tenant, token)).teamAssignments({ status, per_page: 200 }));
  return page ? page.items : null;
}

/** Issued certificate of one course: the newest that is not revoked, else the newest. `null` when none or on failure. */
export async function loadCourseCertificate(tenant: Tenant, token: string, courseId: number): Promise<Certificate | null> {
  const list = await settle(createCertificateClient(options(tenant, token)).list());
  const mine = (list?.items ?? []).filter((c) => isCourseCertificate(c, courseId));
  return mine.find((c) => c.status === "valid") ?? mine[0] ?? null;
}

/** The API shows the class name today and accepts the alias (ADR 0043), so both spellings of "course" match. */
export const isCourseCertificate = (c: Pick<Certificate, "assignable_type" | "assignable_id">, courseId: number): boolean =>
  Number(c.assignable_id) === courseId && /(^|\\|\.)course$/i.test(c.assignable_type ?? "");

/** Completion percent of a course from its progress rows (0 when there are none). */
export const completion = (entry: { progress?: Array<{ status: number }> | null }): number => {
  const rows = entry.progress ?? [];
  return rows.length ? Math.round((rows.filter((r) => r.status === 1).length / rows.length) * 100) : 0;
};

const days = (ms: number) => Math.floor(ms / DAY);

export type TrainingTone = "ok" | "warn" | "bad" | "muted";

export interface AssignmentRow {
  id: number;
  courseId: number | null;
  title: string;
  who: string | null;
  state: "overdue" | "dueSoon" | "assigned" | "completed" | "cancelled";
  tone: TrainingTone;
  statusText: string;
  dueText: string;
  completedText: string;
  action: { label: string; href: string } | null;
  certificate: { href: string; label: string } | null;
}

const RANK: Record<AssignmentRow["state"], number> = { overdue: 0, dueSoon: 1, assigned: 2, completed: 3, cancelled: 4 };

/** One assignment in words. `percent` maps a course to the learner's completion, to tell Start from Continue. */
export function assignmentRow(a: Assignment, locale: TrainingLocale, now: Date, percent: Map<number, number> = new Map()): AssignmentRow {
  const t = trainingStrings(locale);
  const due = a.due_at ? new Date(a.due_at) : null;
  const validDue = due && !Number.isNaN(due.getTime()) ? due : null;
  const courseId = a.course?.id ?? null;
  const open = a.status === "assigned" || a.status === "overdue";
  let state: AssignmentRow["state"];
  if (a.status === "completed" || a.status === "cancelled") state = a.status;
  else if (a.status === "overdue") state = "overdue";
  else state = validDue && validDue.getTime() - now.getTime() <= DUE_SOON_DAYS * DAY ? "dueSoon" : "assigned";

  const statusText = {
    overdue: plural(locale, t.statusOverdue, Math.max(1, days(validDue ? now.getTime() - validDue.getTime() : 0))),
    dueSoon: t.statusDueSoon,
    assigned: t.statusAssigned,
    completed: t.statusCompleted,
    cancelled: t.statusCancelled,
  }[state];

  const done = percent.get(courseId ?? -1) ?? 0;
  const label = done >= 100 ? t.review : done > 0 ? t.cont : t.start;
  const certificateId = a.certificate?.certificate_id ?? null;
  return {
    id: a.id,
    courseId,
    title: a.course?.title ?? "",
    who: a.user ? (a.user.name ?? a.user.email ?? `#${a.user.id}`) : null,
    state,
    tone: state === "overdue" ? "bad" : state === "dueSoon" ? "warn" : state === "completed" ? "ok" : "muted",
    statusText,
    dueText: validDue ? fill(t.dueOn, { date: formatDate(locale, a.due_at) }) : t.noDueDate,
    completedText: a.completed_at ? fill(t.completedOn, { date: formatDate(locale, a.completed_at) }) : "",
    action: open && courseId !== null ? { label, href: `/learn/${courseId}` } : null,
    certificate: a.status === "completed" && certificateId ? { href: `/certificates/verify/${encodeURIComponent(certificateId)}`, label: t.viewCertificate } : null,
  };
}

/** Overdue first (the most overdue at the top), then due soon, assigned by due date, completed, cancelled. */
export function sortAssignments(rows: AssignmentRow[], dueOf: Map<number, string | null>): AssignmentRow[] {
  const time = (r: AssignmentRow) => {
    const iso = dueOf.get(r.id);
    return iso ? new Date(iso).getTime() : Number.MAX_SAFE_INTEGER;
  };
  return [...rows].sort((a, b) => RANK[a.state] - RANK[b.state] || time(a) - time(b) || a.id - b.id);
}

export function assignmentRows(list: Assignment[], locale: TrainingLocale, now: Date, percent?: Map<number, number>): AssignmentRow[] {
  return sortAssignments(
    list.map((a) => assignmentRow(a, locale, now, percent)),
    new Map(list.map((a) => [a.id, a.due_at])),
  );
}

export interface CertificateRow {
  id: number;
  title: string;
  issued: string;
  validUntil: string;
  version: string | null;
  state: "valid" | "expiring" | "expired" | "revoked";
  tone: TrainingTone;
  statusText: string;
  downloadHref: string;
  verifyHref: string | null;
  badgeCode: string | null;
}

/** Copyable snippet of `<ulams-certificate-badge>`; `webOrigin` serves the script and the verification page. */
export function badgeSnippet(webOrigin: string, apiUrl: string, certificateId: string): string {
  const q = (s: string) => s.replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;");
  const origin = webOrigin.replace(/\/+$/, "");
  return [
    `<script type="module" src="${q(origin)}/embed/ulams-certificate-badge.js"></script>`,
    `<ulams-certificate-badge certificate-id="${q(certificateId)}" api-url="${q(apiUrl.replace(/\/+$/, ""))}" verify-href="${q(origin)}/certificates/verify/{id}"></ulams-certificate-badge>`,
  ].join("\n");
}

export function certificateRow(c: Certificate, locale: TrainingLocale, now: Date, site: { webOrigin: string; apiUrl: string }): CertificateRow {
  const t = trainingStrings(locale);
  const expires = c.expires_at ? new Date(c.expires_at) : null;
  const left = expires && !Number.isNaN(expires.getTime()) ? Math.ceil((expires.getTime() - now.getTime()) / DAY) : null;
  const state: CertificateRow["state"] = c.status === "revoked" ? "revoked" : c.status === "expired" ? "expired" : left !== null && left <= EXPIRING_DAYS ? "expiring" : "valid";
  const statusText = {
    valid: t.certValid,
    expiring: plural(locale, t.certExpiring, Math.max(0, left ?? 0)),
    expired: t.certExpired,
    revoked: fill(t.certRevoked, { date: formatDate(locale, c.revoked_at) }).trim(),
  }[state];
  const id = c.certificate_id;
  return {
    id: c.id,
    title: c.title ?? "",
    issued: formatDate(locale, c.created_at),
    validUntil: c.expires_at ? formatDate(locale, c.expires_at) : t.doesNotExpire,
    version: c.course_version === null || c.course_version === undefined || c.course_version === "" ? null : String(c.course_version),
    state,
    tone: state === "valid" ? "ok" : state === "expiring" ? "warn" : "bad",
    statusText,
    downloadHref: `/account/certificates/${c.id}.pdf`,
    verifyHref: id ? `/certificates/verify/${encodeURIComponent(id)}` : null,
    // a revoked certificate would only show "revoked" on someone's page: no snippet for it
    badgeCode: id && state !== "revoked" ? badgeSnippet(site.webOrigin, site.apiUrl, id) : null,
  };
}

export interface PathStepRow {
  courseId: number;
  title: string;
  status: "done" | "available" | "locked";
  statusText: string;
  unlocks: string | null;
  href: string | null;
}
export interface PathRow {
  id: number;
  title: string;
  completed: boolean;
  progress: string;
  steps: PathStepRow[];
}

export function pathRows(paths: PathEnrollment[], locale: TrainingLocale): PathRow[] {
  const t = trainingStrings(locale);
  return paths.map((p) => {
    const names = new Map(p.steps.map((s) => [s.course_id, s.title ?? `#${s.course_id}`]));
    const done = p.steps.filter((s) => s.status === "done").length;
    return {
      id: p.path_id,
      title: p.title ?? "",
      completed: p.completed_at !== null || (p.steps.length > 0 && done === p.steps.length),
      progress: fill(t.pathProgress, { done, total: p.steps.length }),
      steps: p.steps.map((s) => ({
        courseId: s.course_id,
        title: s.title ?? `#${s.course_id}`,
        status: s.status,
        statusText: s.status === "done" ? t.stepDone : s.status === "available" ? t.stepAvailable : t.stepLocked,
        unlocks: s.status === "locked" && s.waiting_for.length ? fill(t.unlocksAfter, { courses: s.waiting_for.map((id) => names.get(id) ?? `#${id}`).join(", ") }) : null,
        href: s.status === "locked" ? null : `/learn/${s.course_id}`,
      })),
    };
  });
}

export interface SkillGroup {
  area: string | null;
  skills: Array<{ id: number; name: string; code: string | null; earned: string; lapses: string; lapsing: boolean; courses: Array<{ id: number; title: string }> }>;
}

/** Skills grouped by area (alphabetical, "other" last), each group's skills by name. */
export function skillGroups(skills: Skill[], locale: TrainingLocale): SkillGroup[] {
  const t = trainingStrings(locale);
  const groups = new Map<string | null, SkillGroup>();
  for (const s of skills) {
    const key = s.area ?? null;
    const group = groups.get(key) ?? { area: key, skills: [] };
    group.skills.push({
      id: s.competency_id,
      name: s.name,
      code: s.code,
      earned: s.earned_at ? fill(t.earnedOn, { date: formatDate(locale, s.earned_at) }) : "",
      lapses: s.expires_at ? fill(t.lapsesOn, { date: formatDate(locale, s.expires_at) }) : t.doesNotLapse,
      lapsing: s.expires_at !== null,
      courses: s.courses.map((c) => ({ id: c.course_id, title: c.title ?? `#${c.course_id}` })),
    });
    groups.set(key, group);
  }
  const collator = new Intl.Collator(locale);
  return [...groups.values()]
    .map((g) => ({ ...g, skills: g.skills.sort((a, b) => collator.compare(a.name, b.name)) }))
    .sort((a, b) => (a.area === null ? 1 : b.area === null ? -1 : collator.compare(a.area, b.area)));
}

export interface AccountSummary {
  dueThisMonth: number;
  overdue: number;
  certificates: number | null;
  skills: number | null;
}

/** Counts for the links on /account: open trainings due before the end of this month (UTC), overdue ones, certificates, skills. */
export function accountSummary(data: TrainingData, now: Date): AccountSummary {
  const endOfMonth = Date.UTC(now.getUTCFullYear(), now.getUTCMonth() + 1, 1);
  const open = (data.assignments ?? []).filter((a) => a.status === "assigned" || a.status === "overdue");
  return {
    dueThisMonth: open.filter((a) => a.status === "assigned" && a.due_at && new Date(a.due_at).getTime() < endOfMonth).length,
    overdue: open.filter((a) => a.status === "overdue").length,
    certificates: data.certificates ? data.certificates.filter((c) => c.status !== "revoked").length : null,
    skills: data.skills ? data.skills.length : null,
  };
}

export const TEAM_FILTERS: Array<"all" | AssignmentStatus> = ["all", "overdue", "assigned", "completed", "cancelled"];

export function teamFilter(value: string | null): AssignmentStatus | undefined {
  return TEAM_FILTERS.includes(value as AssignmentStatus) && value !== "all" ? (value as AssignmentStatus) : undefined;
}

/** This site's public origin as the browser sees it (the proxy's forwarded host first); `fallback` when the host is unusable. */
export function siteOrigin(headers: Headers, secure: boolean, fallback: string): string {
  return ownOrigins(headers.get("x-forwarded-host") ?? headers.get("host") ?? "", secure)[0] ?? fallback;
}
