/**
 * Course variants for the learner (CV-5, ADR 0192): reading the picker data and the learner's own state in the
 * family, choosing or switching a variant, the hint to switch, the placement check, and the view model that turns a
 * state into what the course page shows. The API decides who may open what; this module never guesses access.
 * Every read fails soft (a course page without variants is the normal case) and every write answers a result the
 * page can explain instead of throwing.
 */
import type { Tenant } from "@ulams/sdk";
import { trainingLocale } from "@ulams/ui/training-strings";
import { fill, hintReasonText, isVariantLevel, plural, variantStrings, type HintReason } from "@ulams/ui/variant-strings";
import { tenantFetch } from "./api-transport.ts";
import { publicData } from "./data.ts";

// ----- the API's shapes (api/packages/course-variants, VariantController and PlacementController)

export interface VariantItem {
  courseId: number;
  title: string;
  label: string;
  level: string | null;
  role: string | null;
  isDefault: boolean;
  description: string | null;
  duration: string | null;
}

export interface VariantFamily {
  groupId: number;
  defaultCourseId: number;
  variants: VariantItem[];
}

export interface VariantReason {
  key: string;
  params: Record<string, unknown>;
}

export interface VariantCurrent {
  courseId: number;
  source: string;
  reason: VariantReason | null;
  byOrganisation: boolean;
  decidedAt: string | null;
}

export interface PlacementAttempt {
  id: string;
  scores: Record<string, { correct: number; total: number }>;
  suggestedCourseId: number | null;
  reason: VariantReason | null;
  createdAt: string | null;
}

export interface VariantHint {
  id: number;
  direction: "easier" | "harder";
  courseId: number;
  suggestedCourseId: number;
  reasons: HintReason[];
}

export type LockedReason = "not_enrolled" | "locked" | null;

export interface VariantState extends VariantFamily {
  current: VariantCurrent | null;
  effectiveCourseId: number;
  enrolled: boolean;
  canChange: boolean;
  lockedReason: LockedReason;
  placement: { enabled: boolean; available: boolean; lastAttempt: PlacementAttempt | null };
  hint: VariantHint | null;
}

export interface PlacementQuestion {
  id: string;
  type: string;
  stem: string;
  options: Array<{ id: string; text: string }>;
}

// ----- defensive parsing of what the API answers

const isObject = (value: unknown): value is Record<string, unknown> => typeof value === "object" && value !== null && !Array.isArray(value);
const int = (value: unknown): number | null => (typeof value === "number" && Number.isInteger(value) && value > 0 ? value : null);
const text = (value: unknown): string | null => (typeof value === "string" && value.trim() !== "" ? value.trim() : null);

function parseItem(raw: unknown): VariantItem | null {
  if (!isObject(raw)) return null;
  const courseId = int(raw.courseId);
  const title = text(raw.title);
  if (courseId === null || title === null) return null;
  return {
    courseId,
    title,
    label: text(raw.label) ?? title,
    level: text(raw.level),
    role: text(raw.role),
    isDefault: raw.isDefault === true,
    description: text(raw.description),
    duration: text(raw.duration),
  };
}

function parseItems(raw: unknown): VariantItem[] {
  return Array.isArray(raw) ? raw.map(parseItem).filter((item): item is VariantItem => item !== null) : [];
}

function parseReason(raw: unknown): VariantReason | null {
  if (!isObject(raw)) return null;
  const key = text(raw.key);
  return key === null ? null : { key, params: isObject(raw.params) ? raw.params : {} };
}

export function parseAttempt(raw: unknown): PlacementAttempt | null {
  if (!isObject(raw)) return null;
  const id = text(raw.id);
  if (id === null) return null;
  const scores: PlacementAttempt["scores"] = {};
  if (isObject(raw.scores)) {
    for (const [level, row] of Object.entries(raw.scores)) {
      if (isObject(row) && Number.isFinite(Number(row.correct)) && Number.isFinite(Number(row.total))) scores[level] = { correct: Number(row.correct), total: Number(row.total) };
    }
  }
  return { id, scores, suggestedCourseId: int(raw.suggestedCourseId), reason: parseReason(raw.reason), createdAt: text(raw.createdAt) };
}

function parseHint(raw: unknown): VariantHint | null {
  if (!isObject(raw)) return null;
  const id = int(raw.id);
  const suggestedCourseId = int(raw.suggestedCourseId);
  const courseId = int(raw.courseId);
  if (id === null || suggestedCourseId === null || courseId === null) return null;
  const reasons = Array.isArray(raw.reasons)
    ? raw.reasons.filter(isObject).map((r) => ({ messageKey: text(r.messageKey) ?? undefined, params: isObject(r.params) ? r.params : undefined, message: text(r.message) ?? undefined }))
    : [];
  return { id, direction: raw.direction === "harder" ? "harder" : "easier", courseId, suggestedCourseId, reasons };
}

export function parseFamily(raw: unknown): VariantFamily | null {
  if (!isObject(raw)) return null;
  const groupId = int(raw.groupId);
  const defaultCourseId = int(raw.defaultCourseId);
  const variants = parseItems(raw.variants);
  return groupId === null || defaultCourseId === null || variants.length === 0 ? null : { groupId, defaultCourseId, variants };
}

export function parseState(raw: unknown): VariantState | null {
  const family = parseFamily(raw);
  if (!family || !isObject(raw)) return null;
  const effectiveCourseId = int(raw.effectiveCourseId) ?? family.defaultCourseId;
  const cur = isObject(raw.current) ? raw.current : null;
  const currentId = cur ? int(cur.courseId) : null;
  const placement = isObject(raw.placement) ? raw.placement : {};
  const locked = raw.lockedReason === "locked" || raw.lockedReason === "not_enrolled" ? raw.lockedReason : null;
  return {
    ...family,
    current: cur && currentId !== null ? { courseId: currentId, source: text(cur.source) ?? "self", reason: parseReason(cur.reason), byOrganisation: cur.byOrganisation === true, decidedAt: text(cur.decidedAt) } : null,
    effectiveCourseId,
    enrolled: raw.enrolled === true,
    canChange: raw.canChange === true,
    lockedReason: locked,
    placement: { enabled: placement.enabled === true, available: placement.available === true, lastAttempt: parseAttempt(placement.lastAttempt) },
    hint: parseHint(raw.hint),
  };
}

export function parseQuestions(raw: unknown): PlacementQuestion[] {
  const list = isObject(raw) && Array.isArray(raw.questions) ? raw.questions : [];
  return list.flatMap((q): PlacementQuestion[] => {
    if (!isObject(q)) return [];
    const id = text(typeof q.id === "number" ? String(q.id) : q.id);
    const stem = text(q.stem);
    const options = Array.isArray(q.options)
      ? q.options.flatMap((o) => (isObject(o) && text(typeof o.id === "number" ? String(o.id) : o.id) && text(o.text) ? [{ id: text(typeof o.id === "number" ? String(o.id) : o.id)!, text: text(o.text)! }] : []))
      : [];
    return id && stem && options.length >= 2 ? [{ id, type: text(q.type) ?? "single", stem, options }] : [];
  });
}

/** A question that accepts more than one answer (checkboxes); every other type is a single choice (radios). */
export const isMultiple = (question: PlacementQuestion): boolean => /^multiple/i.test(question.type);

// ----- calls to the tenant API

interface Reply {
  status: number;
  data: unknown;
}

async function call(tenant: Tenant, token: string | null, method: string, path: string, body?: unknown, timeoutMs = 6_000): Promise<Reply> {
  try {
    const response = await tenantFetch(tenant)(`${tenant.apiUrl}${path}`, {
      method,
      headers: { Accept: "application/json", "Content-Type": "application/json", ...(token ? { Authorization: `Bearer ${token}` } : {}) },
      ...(body === undefined ? {} : { body: JSON.stringify(body) }),
      signal: AbortSignal.timeout(timeoutMs),
    });
    const json = (await response.json().catch(() => null)) as { data?: unknown } | null;
    return { status: response.status, data: json?.data ?? null };
  } catch {
    return { status: 0, data: null };
  }
}

/** The picker's public data for any course of a family, or null when it has no variants. Cached briefly. */
export async function getVariantFamily(tenant: Tenant, courseId: number): Promise<VariantFamily | null> {
  return publicData(
    tenant,
    `variants:${courseId}`,
    async () => {
      const reply = await call(tenant, null, "GET", `/api/courses/${courseId}/variants`, undefined, 3_000);
      if (reply.status === 0 || reply.status >= 500) throw new Error("variants unavailable");
      return reply.status === 200 ? parseFamily(reply.data) : null;
    },
    30_000
  ).catch(() => null);
}

/** The learner's own state in the family. Never cached; null for a course without variants, an older API or an error. */
export async function getVariantState(tenant: Tenant, token: string, courseId: number): Promise<VariantState | null> {
  const reply = await call(tenant, token, "GET", `/api/courses/${courseId}/variant`, undefined, 3_000);
  return reply.status === 200 ? parseState(reply.data) : null;
}

export type ChooseInput =
  | { kind: "switch"; courseId: number }
  | { kind: "placement"; courseId: number; attemptId: string }
  | { kind: "hint-switch"; courseId: number; hintId: number }
  | { kind: "hint-dismiss"; hintId: number };

/** Reads the picker, hint and placement-result forms (plain posts). Anything malformed is refused. */
export function parseChooseForm(form: FormData): ChooseInput | null {
  const number = (name: string): number | null => {
    const raw = String(form.get(name) ?? "");
    return /^\d{1,10}$/.test(raw) && Number(raw) > 0 ? Number(raw) : null;
  };
  const action = String(form.get("action") ?? "");
  const courseId = number("course_id");
  const hintId = number("hint_id");
  const attemptId = String(form.get("attempt_id") ?? "");
  if (action === "switch" && courseId) return { kind: "switch", courseId };
  if (action === "placement" && courseId && /^[0-9A-Za-z]{26}$/.test(attemptId)) return { kind: "placement", courseId, attemptId };
  if (action === "hint-switch" && courseId && hintId) return { kind: "hint-switch", courseId, hintId };
  if (action === "hint-dismiss" && hintId) return { kind: "hint-dismiss", hintId };
  return null;
}

/** What a write came to: the page turns it into the flash message. */
export type ChooseOutcome =
  | { kind: "switched"; courseId: number; carried: number }
  | { kind: "unchanged" }
  | { kind: "dismissed" }
  | { kind: "locked" }
  | { kind: "no-access" }
  | { kind: "failed" };

export async function applyChoice(tenant: Tenant, token: string, courseId: number, input: ChooseInput): Promise<ChooseOutcome> {
  if (input.kind === "hint-dismiss") {
    const reply = await call(tenant, token, "POST", `/api/courses/${courseId}/variant/hints/${input.hintId}/dismiss`);
    return reply.status === 200 ? { kind: "dismissed" } : { kind: "failed" };
  }
  const body =
    input.kind === "switch"
      ? { course_id: input.courseId, source: "self" }
      : input.kind === "placement"
        ? { course_id: input.courseId, source: "placement", attempt_id: input.attemptId }
        : { course_id: input.courseId, source: "hint", hint_id: input.hintId };
  const reply = await call(tenant, token, "PUT", `/api/courses/${courseId}/variant`, body);
  if (reply.status === 200 && isObject(reply.data)) {
    if (reply.data.changed === false) return { kind: "unchanged" };
    const carried = Number(reply.data.carried);
    return { kind: "switched", courseId: input.courseId, carried: Number.isFinite(carried) && carried > 0 ? Math.floor(carried) : 0 };
  }
  if (reply.status === 403) {
    // the answer does not say which refusal it is: the state does
    const state = await getVariantState(tenant, token, courseId);
    return state?.lockedReason === "locked" ? { kind: "locked" } : { kind: "no-access" };
  }
  return { kind: "failed" };
}

export async function getPlacementQuestions(tenant: Tenant, token: string, courseId: number): Promise<PlacementQuestion[]> {
  const reply = await call(tenant, token, "GET", `/api/courses/${courseId}/placement`, undefined, 4_000);
  return reply.status === 200 ? parseQuestions(reply.data) : [];
}

/** The answers of the placement form: `q-<question id>` fields holding option ids (a checkbox group posts several). */
export function parsePlacementForm(form: FormData): Record<string, string[]> {
  const answers: Record<string, string[]> = {};
  for (const [name, value] of form.entries()) {
    const match = /^q-([0-9A-Za-z_-]{1,40})$/.exec(name);
    if (!match || typeof value !== "string" || value.length > 40) continue;
    (answers[match[1]!] ??= []).push(value);
  }
  return answers;
}

export async function scorePlacement(tenant: Tenant, token: string, courseId: number, answers: Record<string, string[]>): Promise<PlacementAttempt | null> {
  const reply = await call(tenant, token, "POST", `/api/courses/${courseId}/placement/attempts`, { answers }, 8_000);
  return reply.status === 201 || reply.status === 200 ? parseAttempt(reply.data) : null;
}

// ----- the flash message after a post (a query parameter, like the lesson view)

export type Flash =
  | { kind: "switched"; carried: number }
  | { kind: "unchanged" | "dismissed" | "locked" | "no-access" | "failed" };

const FLASH_KINDS = ["unchanged", "dismissed", "locked", "no-access", "failed"] as const;

export function flashQuery(outcome: ChooseOutcome): string {
  return outcome.kind === "switched" ? `variant=switched&carried=${outcome.carried}` : `variant=${outcome.kind}`;
}

export function readFlash(params: URLSearchParams): Flash | null {
  const kind = params.get("variant");
  if (kind === "switched") {
    const carried = Number(params.get("carried"));
    return { kind: "switched", carried: Number.isInteger(carried) && carried > 0 && carried < 100_000 ? carried : 0 };
  }
  return (FLASH_KINDS as readonly string[]).includes(kind ?? "") ? ({ kind } as Flash) : null;
}

/**
 * The status line after a post. "Switched" names the version the learner is in now (`effectiveCourseId` of the fresh
 * state) and says how many completed lessons carried over.
 */
export function flashMessage(flash: Flash, lang: string, state: Pick<VariantState, "variants" | "effectiveCourseId"> | null): { tone: "ok" | "error"; text: string } {
  const t = variantStrings(lang);
  switch (flash.kind) {
    case "switched": {
      const label = state?.variants.find((v) => v.courseId === state.effectiveCourseId)?.label;
      const carried = flash.carried > 0 ? plural(trainingLocale(lang), t.statusCarried, flash.carried) : t.statusCarriedNone;
      return { tone: "ok", text: `${label ? `${fill(t.statusSwitched, { title: label })} ` : ""}${carried}` };
    }
    case "unchanged":
      return { tone: "ok", text: t.statusUnchanged };
    case "dismissed":
      return { tone: "ok", text: t.statusDismissed };
    case "locked":
      return { tone: "error", text: t.statusLocked };
    case "no-access":
      return { tone: "error", text: t.statusNoAccess };
    default:
      return { tone: "error", text: t.statusFailed };
  }
}

// ----- the view model of the picker (pure: state in, what to show out)

export type PickerMode = "visitor" | "choose" | "locked";

export interface PickerItemView extends VariantItem {
  /** The learner's version now (only for an enrolled learner). */
  current: boolean;
  /** The radio that starts checked. */
  selected: boolean;
  /** Where the variant's own page is; null for the page being shown. */
  href: string | null;
}

export interface HintView {
  id: number;
  direction: "easier" | "harder";
  suggested: VariantItem;
  reasons: string[];
}

export interface PickerView {
  mode: PickerMode;
  items: PickerItemView[];
  /** The learner has chosen or been assigned a version and may not change it. */
  assignedByOrganisation: boolean;
  ruleName: string | null;
  /** Who decided the current version, for the one-line reason; null when the learner never chose or an administrator did. */
  reason: "self" | "placement" | "hint-easier" | "hint-harder" | null;
  placementHref: string | null;
  hint: HintView | null;
  /** The version the page is about is not the one the learner is in. */
  elsewhere: boolean;
}

/**
 * What the course page shows for a family. `state` is the signed-in learner's state, or null for a visitor (or
 * when the call failed); `family` is the public picker data and is the fallback list. Returns null when there is
 * nothing to pick from (a family needs at least two versions).
 */
export function pickerView(family: VariantFamily | null, state: VariantState | null, pageCourseId: number, lang: string): PickerView | null {
  const source: VariantFamily | null = state ?? family;
  if (!source || source.variants.length < 2) return null;
  const learner = state !== null && state.enrolled;
  const mode: PickerMode = !learner ? "visitor" : state.canChange ? "choose" : "locked";
  const selectedId = learner ? state.effectiveCourseId : pageCourseId;
  const items = source.variants.map<PickerItemView>((item) => ({
    ...item,
    current: learner && item.courseId === state.effectiveCourseId,
    selected: item.courseId === selectedId,
    href: item.courseId === pageCourseId ? null : `/courses/${item.courseId}`,
  }));
  const current = learner ? state.current : null;
  const byOrganisation = current?.byOrganisation === true;
  const ruleName = byOrganisation && current?.reason?.key === "rule" ? text(current.reason.params.name) : null;
  let reason: PickerView["reason"] = null;
  if (current && !byOrganisation) {
    const r = current.reason;
    if (current.source === "placement" || r?.key === "placement") reason = "placement";
    else if (current.source === "hint" || r?.key === "hint") reason = r?.params.direction === "harder" ? "hint-harder" : "hint-easier";
    else if (current.source === "self") reason = "self";
  }
  const placementHref = learner && state.placement.available ? `/courses/${pageCourseId}/placement` : null;
  return {
    mode,
    items,
    assignedByOrganisation: byOrganisation,
    ruleName,
    reason,
    placementHref,
    hint: learner && state.canChange ? hintView(state, lang) : null,
    elsewhere: learner && state.effectiveCourseId !== pageCourseId,
  };
}

function hintView(state: VariantState, lang: string): HintView | null {
  const hint = state.hint;
  if (!hint) return null;
  const suggested = state.variants.find((v) => v.courseId === hint.suggestedCourseId);
  if (!suggested || suggested.courseId === state.effectiveCourseId) return null;
  const reasons = hint.reasons.map((r) => hintReasonText(lang, r)).filter((r): r is string => r !== null);
  return { id: hint.id, direction: hint.direction, suggested, reasons };
}

// ----- the placement page

export type PlacementResult =
  | { kind: "suggestion"; attempt: PlacementAttempt; suggested: VariantItem; alreadyUsing: boolean; level: string | null; correct: number; total: number }
  | { kind: "none"; attempt: PlacementAttempt };

/** What the result of an attempt shows: the suggested variant with the evidence of the API's reason, or no suggestion. */
export function placementResult(state: VariantState, attempt: PlacementAttempt): PlacementResult {
  const suggested = attempt.suggestedCourseId === null ? undefined : state.variants.find((v) => v.courseId === attempt.suggestedCourseId);
  if (!suggested) return { kind: "none", attempt };
  const params = attempt.reason?.params ?? {};
  const basis = text(params.basisLevel) ?? text(params.level);
  const correct = Number(params.correct);
  const total = Number(params.total);
  return {
    kind: "suggestion",
    attempt,
    suggested,
    alreadyUsing: suggested.courseId === state.effectiveCourseId,
    level: basis,
    correct: Number.isFinite(correct) ? correct : 0,
    total: Number.isFinite(total) ? total : 0,
  };
}

/** The scores per level in the order beginner, intermediate, expert (levels the API names otherwise follow). */
export function orderedScores(attempt: PlacementAttempt): Array<{ level: string; correct: number; total: number }> {
  const rank = (level: string) => (isVariantLevel(level) ? ["beginner", "intermediate", "expert"].indexOf(level) : 99);
  return Object.entries(attempt.scores)
    .map(([level, row]) => ({ level, ...row }))
    .sort((a, b) => rank(a.level) - rank(b.level) || a.level.localeCompare(b.level));
}
