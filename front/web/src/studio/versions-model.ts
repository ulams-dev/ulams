/**
 * View model of the studio Versions page: turns the releases API's answer into table rows and sentences in the reader's
 * language. No DOM here, so every case is unit-tested. A release says what changed in counts ("label: number"); the
 * Compare page (compare-model.ts) shows the details of any two of them (ADR 0192, CV-3).
 */
import type { CourseRelease, CourseReleases, CourseVariantRef, CourseVersion, ReleaseSummary } from "@ulams/sdk";
import { fill, VERSIONS_STRINGS, type VersionsLocale, type VersionsStrings } from "./versions-i18n.ts";

export interface VersionsFormatter {
  t: VersionsStrings;
  number: (n: number) => string;
  dateTime: (iso: string) => string;
}

export function versionsFormatterFor(locale: VersionsLocale): VersionsFormatter {
  const t = VERSIONS_STRINGS[locale];
  const number = new Intl.NumberFormat(t.htmlLang);
  const dateTime = new Intl.DateTimeFormat(t.htmlLang, { dateStyle: "medium", timeStyle: "short" });
  return {
    t,
    number: (n) => number.format(n),
    dateTime: (iso) => {
      const d = new Date(iso);
      return Number.isNaN(d.getTime()) ? "–" : dateTime.format(d);
    },
  };
}

/** The trigger in the author's words; an unknown trigger keeps its key so nothing is silently dropped. */
export const triggerName = (t: VersionsStrings, trigger: string): string => t.triggers[trigger] ?? trigger;

/** What changed since the previous release, as short phrases (one per kind of change that is not zero). */
export function changePhrases(f: VersionsFormatter, s: ReleaseSummary): string[] {
  const t = f.t;
  if (s.first) return [fill(t.firstRelease, { n: f.number(s.lessons.added) })];
  const lessons = s.lessons.added + s.lessons.removed + s.lessons.changed;
  const others = s.counts.added + s.counts.removed + s.counts.changed - lessons;
  const phrases: string[] = [];
  if (s.lessons.added) phrases.push(fill(t.lessonsAdded, { n: f.number(s.lessons.added) }));
  if (s.lessons.removed) phrases.push(fill(t.lessonsRemoved, { n: f.number(s.lessons.removed) }));
  if (s.lessons.changed) phrases.push(fill(t.lessonsChanged, { n: f.number(s.lessons.changed) }));
  if (others > 0) phrases.push(fill(t.otherElements, { n: f.number(others) }));
  if (s.moved) phrases.push(fill(t.movedElements, { n: f.number(s.moved) }));
  if (s.citationsChanged) phrases.push(fill(t.citationsChanged, { n: f.number(s.citationsChanged) }));
  if (s.answerKeysChanged) phrases.push(fill(t.answerKeysChanged, { n: f.number(s.answerKeysChanged) }));
  return phrases.length ? phrases : [t.noChange];
}

export interface ReleaseRow {
  id: string;
  /** The release number as a number, for labels. */
  n: number;
  versionId: string;
  /** The blueprint version number, to order two picked versions. */
  versionNumber: number | null;
  previousReleaseId: string | null;
  number: string;
  released: string;
  startedBy: string;
  approvedBy: string;
  changes: string[];
  version: string;
}

export function releaseRows(f: VersionsFormatter, releases: CourseRelease[]): ReleaseRow[] {
  return releases.map((r) => ({
    id: r.id,
    n: r.number,
    versionId: r.versionId,
    versionNumber: r.versionNumber,
    previousReleaseId: r.previousReleaseId,
    number: `#${f.number(r.number)}`,
    released: f.dateTime(r.releasedAt),
    startedBy: triggerName(f.t, r.trigger),
    approvedBy: r.approvedBy?.name ?? f.t.nobody,
    changes: changePhrases(f, r.summary),
    version: r.versionNumber === null ? "–" : f.number(r.versionNumber),
  }));
}

export interface PagerState {
  page: number;
  last: number;
  total: number;
  hasNewer: boolean;
  hasOlder: boolean;
}

export function pagerFor(data: Pick<CourseReleases, "page" | "perPage" | "total">): PagerState {
  const last = Math.max(1, Math.ceil(data.total / Math.max(1, data.perPage)));
  return { page: data.page, last, total: data.total, hasNewer: data.page > 1, hasOlder: data.page < last };
}

export interface VersionRow {
  id: string;
  /** The version number as a number, to order two picked versions. */
  n: number;
  version: string;
  kind: string;
  state: string;
  created: string;
  /** The branch, or null when the course has no variants (the column is left out). */
  branch: string | null;
  released: string;
}

/** Every version of a course as table rows: what made it, its state, when, the branch and the release that made it live. */
export function versionRows(f: VersionsFormatter, versions: CourseVersion[], variants: CourseVariantRef[]): VersionRow[] {
  const t = f.t;
  return versions.map((v) => ({
    id: v.id,
    n: v.number,
    version: f.number(v.number),
    kind: t.kinds[v.kind] ?? v.kind,
    state: t.statuses[v.status] ?? v.status,
    created: f.dateTime(v.createdAt ?? ""),
    branch: variants.length === 0 ? null : v.variantId === null ? t.baseCourse : variants.find((x) => x.id === v.variantId)?.label ?? "–",
    released: v.releaseNumber === null ? t.neverReleased : fill(t.releaseNumber, { n: f.number(v.releaseNumber) }),
  }));
}
