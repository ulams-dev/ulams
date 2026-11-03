/**
 * View model of the studio Style page: turns the style profile API's answer into table rows, sentences and the diff in the
 * reader's language, and builds the values an edit sends back. No DOM here, so every case is unit-tested (ADR 0196, SG-2).
 */
import { fill, STYLE_STRINGS, type StyleLocale, type StyleStrings } from "./style-i18n.ts";

export interface Cited {
  citations?: string[];
}
export interface StyleValues {
  schemaVersion?: number;
  theme?: Cited & { preset?: string; accent?: string; secondary?: string; fonts?: string[]; logoFileId?: string };
  voice?: Cited & { person?: string; formality?: number; sentenceLength?: number };
  readingLevel?: Array<Cited & { language: string; level: string }>;
  terminology?: Array<Cited & { use: string; avoid?: string[] }>;
  banned?: { blocking?: boolean; phrases?: Array<Cited & { phrase: string }> };
  components?: Cited & { preferred?: string[]; disallowed?: string[] };
  imagery?: Cited & { altTextRequired?: boolean; decorativeAllowed?: boolean; noTextInImages?: boolean };
  examples?: Array<Cited & { text: string }>;
}
export interface StyleProfile {
  id: string;
  name: string;
  scope: { type: string; id: string | null };
  status: string;
  sourceId: string | null;
  profile: StyleValues;
  warnings: string[];
  createdBy: number | null;
  approvedBy: number | null;
  approvedAt: string | null;
  createdAt: string | null;
  /** Only in the detail answer. */
  current?: { id: string; name: string; profile: StyleValues } | null;
  diff?: StyleChange[];
  fragments?: Record<string, { label: string; preview: string }>;
}
export interface StyleChange {
  path: string;
  change: string;
  before: unknown;
  after: unknown;
  citations: string[];
}

export interface StyleFormatter {
  t: StyleStrings;
  date: (iso: string | null) => string;
}

export function styleFormatterFor(locale: StyleLocale): StyleFormatter {
  const t = STYLE_STRINGS[locale];
  const date = new Intl.DateTimeFormat(t.htmlLang, { dateStyle: "medium", timeStyle: "short" });
  return {
    t,
    date: (iso) => {
      const d = new Date(iso ?? "");
      return iso && !Number.isNaN(d.getTime()) ? date.format(d) : "–";
    },
  };
}

export interface ProfileRow {
  id: string;
  name: string;
  status: string;
  statusKey: string;
  scope: string;
  created: string;
}

export function profileRows(f: StyleFormatter, profiles: StyleProfile[]): ProfileRow[] {
  return profiles.map((p) => ({
    id: p.id,
    name: p.name,
    status: f.t.statuses[p.status] ?? p.status,
    statusKey: p.status,
    scope: f.t.scopes[p.scope.type] ?? p.scope.type,
    created: f.date(p.createdAt),
  }));
}

/** A value in the reader's words: booleans as yes or no, lists joined, anything else as text. */
export function valueText(f: StyleFormatter, value: unknown): string {
  if (typeof value === "boolean") return value ? f.t.yes : f.t.no;
  if (Array.isArray(value)) return value.map((v) => valueText(f, v)).join(", ");
  if (value === null || value === undefined) return "–";
  return String(value);
}

export interface RemoveRef {
  part: "terminology" | "banned" | "readingLevel" | "examples";
  index: number;
}
export interface ValueItem {
  /** What is shown: "Label: value". */
  text: string;
  /** Machine-readable id of the value, for tests and the passage regions. */
  key: string;
  citations: Array<{ id: string; label: string }>;
  /** A value the author wrote (no citation). */
  own: boolean;
  /** How to remove it, for list items. */
  remove?: RemoveRef;
  /** The short name used in "Remove {item}". */
  name: string;
}
export interface ValueSection {
  key: string;
  label: string;
  items: ValueItem[];
}

const label = (profile: StyleProfile, id: string): { id: string; label: string } => ({ id, label: profile.fragments?.[id]?.label ?? id });

/** The values of a profile, grouped by part, each with the passages it cites. Parts without values are left out. */
export function valueSections(f: StyleFormatter, profile: StyleProfile): ValueSection[] {
  const t = f.t;
  const v = profile.profile;
  const out: ValueSection[] = [];
  const cites = (c?: string[]) => (c ?? []).map((id) => label(profile, id));
  const item = (key: string, text: string, citations: string[] | undefined, name: string, remove?: RemoveRef): ValueItem => ({
    key,
    text,
    citations: cites(citations),
    own: (citations ?? []).length === 0,
    name,
    ...(remove ? { remove } : {}),
  });
  const section = (key: string, items: ValueItem[]) => {
    if (items.length) out.push({ key, label: t.parts[key] ?? key, items });
  };
  const field = (name: string) => t.fields[name] ?? name;

  if (v.theme) {
    const items: ValueItem[] = [];
    for (const k of ["preset", "accent", "secondary", "logoFileId"] as const) {
      if (v.theme[k]) items.push(item(`theme.${k}`, `${field(k)}: ${v.theme[k]}`, v.theme.citations, field(k)));
    }
    if (v.theme.fonts?.length) items.push(item("theme.fonts", `${field("fonts")}: ${v.theme.fonts.join(", ")}`, v.theme.citations, field("fonts")));
    section("theme", items);
  }
  if (v.voice) {
    const items: ValueItem[] = [];
    if (v.voice.person) items.push(item("voice.person", `${field("person")}: ${t.persons[v.voice.person] ?? v.voice.person}`, v.voice.citations, field("person")));
    if (v.voice.formality) items.push(item("voice.formality", `${field("formality")}: ${v.voice.formality}`, v.voice.citations, field("formality")));
    if (v.voice.sentenceLength) items.push(item("voice.sentenceLength", `${field("sentenceLength")}: ${v.voice.sentenceLength}`, v.voice.citations, field("sentenceLength")));
    section("voice", items);
  }
  section("readingLevel", (v.readingLevel ?? []).map((r, index) => item(`readingLevel.${r.language}`, `${r.language}: ${r.level}`, r.citations, r.language, { part: "readingLevel", index })));
  section("terminology", (v.terminology ?? []).map((r, index) => item(`terminology.${r.use}`, r.avoid?.length ? `${r.use} (${fill(t.insteadOf, { terms: r.avoid.join(", ") })})` : r.use, r.citations, r.use, { part: "terminology", index })));
  if (v.banned) {
    const items = (v.banned.phrases ?? []).map((r, index) => item(`banned.phrase.${r.phrase}`, r.phrase, r.citations, r.phrase, { part: "banned" as const, index }));
    if (items.length) items.unshift(item("banned.blocking", `${field("blocking")}: ${valueText(f, v.banned.blocking === true)}`, [], field("blocking")));
    section("banned", items);
  }
  if (v.components) {
    const items: ValueItem[] = [];
    if (v.components.preferred?.length) items.push(item("components.preferred", `${field("preferred")}: ${v.components.preferred.join(", ")}`, v.components.citations, field("preferred")));
    if (v.components.disallowed?.length) items.push(item("components.disallowed", `${field("disallowed")}: ${v.components.disallowed.join(", ")}`, v.components.citations, field("disallowed")));
    section("components", items);
  }
  if (v.imagery) {
    const items: ValueItem[] = [];
    for (const k of ["altTextRequired", "decorativeAllowed", "noTextInImages"] as const) {
      if (typeof v.imagery[k] === "boolean") items.push(item(`imagery.${k}`, `${field(k)}: ${valueText(f, v.imagery[k])}`, v.imagery.citations, field(k)));
    }
    section("imagery", items);
  }
  section("examples", (v.examples ?? []).map((r, index) => item(`examples.${index + 1}`, r.text, r.citations, r.text.slice(0, 40), { part: "examples", index })));

  // a blocking switch without phrases shows nothing: it is only meaningful next to them
  return out;
}

export interface ChangeRow {
  key: string;
  kind: string;
  kindLabel: string;
  symbol: string;
  label: string;
  before: string | null;
  after: string | null;
  citations: Array<{ id: string; label: string }>;
}

/** "terminology.learner" as "Terminology: learner", "voice.formality" as "Voice: Formality (1 to 5)". */
export function changeLabel(f: StyleFormatter, path: string): string {
  const [part = "", ...rest] = path.split(".");
  const partLabel = f.t.parts[part] ?? part;
  if (part === "banned") return rest[0] === "blocking" ? `${partLabel}: ${f.t.fields.blocking}` : `${partLabel}: ${rest.slice(1).join(".")}`;
  if (part === "terminology" || part === "readingLevel" || part === "examples") return `${partLabel}: ${rest.join(".")}`;
  return `${partLabel}: ${f.t.fields[rest.join(".")] ?? rest.join(".")}`;
}

export function changeRows(f: StyleFormatter, profile: StyleProfile): ChangeRow[] {
  return (profile.diff ?? []).map((c) => ({
    key: c.path,
    kind: c.change,
    kindLabel: f.t.changeKinds[c.change] ?? c.change,
    symbol: f.t.changeSymbols[c.change] ?? "",
    label: changeLabel(f, c.path),
    before: c.change === "added" ? null : valueText(f, c.before),
    after: c.change === "removed" ? null : valueText(f, c.after),
    citations: c.citations.map((id) => label(profile, id)),
  }));
}

/** The address of the admin app's theme screen with the profile in the link, or null when the admin address is not known. */
export function themeLinkFor(adminUrl: string, profileId: string): string | null {
  if (!adminUrl) return null;
  try {
    const url = new URL("/configuration/settings/theme_settings", adminUrl);
    url.searchParams.set("styleProfile", profileId);
    return url.toString();
  } catch {
    return null;
  }
}

export const hasTheme = (profile: StyleProfile): boolean => Boolean(profile.profile.theme && (profile.profile.theme.preset || profile.profile.theme.accent));

/** The profile without one list item (the edit sends the rest back). */
export function withoutItem(values: StyleValues, ref: RemoveRef): StyleValues {
  const next: StyleValues = structuredClone(values);
  if (ref.part === "banned") {
    if (next.banned?.phrases) next.banned.phrases = next.banned.phrases.filter((_, i) => i !== ref.index);
    if (next.banned && (next.banned.phrases ?? []).length === 0) delete next.banned;
    return next;
  }
  const list = next[ref.part] as unknown[] | undefined;
  if (list) {
    const rest = list.filter((_, i) => i !== ref.index);
    if (rest.length) (next[ref.part] as unknown[]) = rest;
    else delete next[ref.part];
  }
  return next;
}

export interface VoiceEdit {
  person: string;
  formality: string;
  sentenceLength: string;
  blocking: boolean;
}

/** The profile with the voice fields and the blocking switch from the edit form; empty fields remove the rule. */
export function withEdits(values: StyleValues, edit: VoiceEdit): StyleValues {
  const next: StyleValues = structuredClone(values);
  const voice: NonNullable<StyleValues["voice"]> = { citations: next.voice?.citations ?? [] };
  if (edit.person) voice.person = edit.person;
  const formality = Number.parseInt(edit.formality, 10);
  if (Number.isInteger(formality)) voice.formality = formality;
  const length = Number.parseInt(edit.sentenceLength, 10);
  if (Number.isInteger(length)) voice.sentenceLength = length;
  if (voice.person || voice.formality !== undefined || voice.sentenceLength !== undefined) next.voice = voice;
  else delete next.voice;
  if (next.banned) next.banned.blocking = edit.blocking;
  return next;
}

export interface Finding {
  code: string;
  rule: string;
  severity: string;
  elementId?: string;
  message: string;
  term?: string;
  quote?: string;
  citations: Array<{ id: string; label: string }>;
}
export interface CheckReport {
  profile: { id: string; name: string; status: string };
  course: { id: number; title: string | null };
  blocking: Finding[];
  warnings: Finding[];
  hints: Finding[];
  counts: { blocking: number; warnings: number; hints: number };
  blocksPublishing: boolean;
}

export interface CheckGroup {
  key: "blocking" | "warnings" | "hints";
  title: string;
  findings: Finding[];
}

/** The groups of a check report that have findings, blocking first; empty when there is nothing to report. */
export function checkGroups(f: StyleFormatter, report: CheckReport): CheckGroup[] {
  return [
    { key: "blocking" as const, title: f.t.checkBlockingTitle, findings: report.blocking },
    { key: "warnings" as const, title: f.t.checkWarningsTitle, findings: report.warnings },
    { key: "hints" as const, title: f.t.checkHintsTitle, findings: report.hints },
  ].filter((g) => g.findings.length > 0);
}
