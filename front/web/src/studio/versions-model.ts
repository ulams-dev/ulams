/**
 * View model of the studio Versions page: turns the releases API's answer into table rows and sentences in the reader's
 * language. No DOM here, so every case is unit-tested. A release says what changed in counts ("label: number"); the
 * full compare screen arrives with the compare milestone (ADR 0192, CV-3).
 */
import type { CourseRelease, CourseReleases, ReleaseSummary } from "@ulams/sdk";
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
  if (s.citationsChanged) phrases.push(fill(t.citationsChanged, { n: f.number(s.citationsChanged) }));
  if (s.answerKeysChanged) phrases.push(fill(t.answerKeysChanged, { n: f.number(s.answerKeysChanged) }));
  return phrases.length ? phrases : [t.noChange];
}

export interface ReleaseRow {
  id: string;
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
