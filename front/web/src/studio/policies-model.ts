/**
 * View model of the studio Policies pages: turns the policy API's answers into rows and sentences in the reader's language.
 * No DOM here, so every case is unit-tested. Counts are "label: number"; every state is written out (no meaning by colour).
 */
import type { DashboardPerson, PolicyDetail, PolicyVersionRow, PolicySummary, VersionReview } from "@ulams/sdk";
import { fill, POLICIES_STRINGS, type PoliciesLocale, type PoliciesStrings } from "./policies-i18n.ts";

export interface PoliciesFormatter {
  t: PoliciesStrings;
  number: (n: number) => string;
  percent: (rate: number | null) => string;
  date: (iso: string | null) => string;
  cost: (microUsd: number) => string;
}

export function policiesFormatterFor(locale: PoliciesLocale): PoliciesFormatter {
  const t = POLICIES_STRINGS[locale];
  const number = new Intl.NumberFormat(t.htmlLang);
  const percent = new Intl.NumberFormat(t.htmlLang, { style: "percent", maximumFractionDigits: 0 });
  const date = new Intl.DateTimeFormat(t.htmlLang, { dateStyle: "medium" });
  const usd = new Intl.NumberFormat("en-US", { style: "currency", currency: "USD", maximumFractionDigits: 2 });
  return {
    t,
    number: (n) => number.format(n),
    percent: (rate) => (rate === null ? t.none : percent.format(rate)),
    date: (iso) => {
      const d = iso ? new Date(iso) : null;
      return d === null || Number.isNaN(d.getTime()) ? t.none : date.format(d);
    },
    cost: (micro) => usd.format(micro / 1_000_000),
  };
}

export interface PolicyRow {
  id: string;
  title: string;
  current: string;
  review: string;
  departments: string;
  openLabel: string;
}

export function policyRows(f: PoliciesFormatter, policies: PolicySummary[]): PolicyRow[] {
  const t = f.t.list;
  return policies.map((p) => ({
    id: p.id,
    title: p.title,
    current: p.currentVersion === null ? t.notApproved : fill(t.version, { n: p.currentVersion }),
    review: p.pendingReview === null ? f.t.none : fill(t.version, { n: p.pendingReview }),
    departments: f.number(p.departments),
    openLabel: fill(t.open, { title: p.title }),
  }));
}

/** The stage in the author's words; an unknown stage keeps its key so nothing is silently dropped. */
export const stageText = (t: PoliciesStrings, stage: string): string => t.detail.stages[stage] ?? stage;

export interface PersonRow {
  key: number;
  name: string;
  department: string;
  status: string;
  due: string;
  courseVersion: string;
  policyVersion: string;
  exam: boolean;
  certificate: { text: string; verifyPath: string } | null;
}

export function personRows(f: PoliciesFormatter, people: DashboardPerson[]): PersonRow[] {
  const t = f.t.detail;
  return people.map((p) => ({
    key: p.assignmentId,
    name: p.user?.name || p.user?.email || f.t.none,
    department: p.department ?? f.t.none,
    status: t.statuses[p.status] ?? p.status,
    due: f.date(p.dueAt),
    courseVersion: p.courseVersion === null ? f.t.none : fill(f.t.list.version, { n: p.courseVersion }),
    policyVersion: p.policyVersion === null ? f.t.none : fill(f.t.list.version, { n: p.policyVersion }),
    exam: p.exam,
    certificate: p.certificate ? { text: t.certStatuses[p.certificate.status] ?? p.certificate.status, verifyPath: p.certificate.verifyPath } : null,
  }));
}

export interface VersionRowModel {
  number: number;
  label: string;
  state: string;
  change: string;
  summary: string;
  reviewable: boolean;
  reviewLabel: string;
}

export function versionRows(f: PoliciesFormatter, versions: PolicyVersionRow[]): VersionRowModel[] {
  const t = f.t.detail;
  return [...versions].reverse().map((v) => ({
    number: v.number,
    label: fill(f.t.list.version, { n: v.number }),
    state: t.versionStates[v.status] ?? v.status,
    change: t.classes[v.changeClass] ?? v.changeClass,
    summary: v.summary ?? f.t.none,
    reviewable: v.status === "in_review" || v.status === "applying" || (v.changeClass !== "initial" && (v.status === "approved" || v.status === "rejected" || v.status === "superseded")),
    reviewLabel: fill(t.review, { n: v.number }),
  }));
}

/** The first version can be approved from the policy page while it is a draft. */
export const canApproveFirst = (policy: PolicyDetail): boolean => policy.currentVersion === null && policy.versions.some((v) => v.changeClass === "initial" && v.status === "draft");

/** A change can be uploaded when a version is in force and none waits for a decision. */
export const canUploadChange = (policy: PolicyDetail): boolean => policy.currentVersion !== null && !policy.versions.some((v) => v.status === "in_review" || v.status === "applying");

/** Departments that have not been asked to generate yet. */
export const canGenerate = (policy: PolicyDetail): boolean => policy.variants.some((v) => v.stage === "not_started");

/** Whether choosing `chosen` lowers the class below the suggestion, which needs a reason. */
export const lowersClass = (suggested: string | undefined, chosen: string): boolean => suggested === "major" && chosen === "minor";

export const isOpenVersion = (v: VersionReview): boolean => v.status === "in_review" || v.status === "applying";
