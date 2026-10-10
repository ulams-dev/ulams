/**
 * The `ulams-ix` v1 protocol (ADR 0087): types, constants and runtime guards shared by the lesson
 * page (host) and the interactive package. Zero dependencies. The JSON Schemas in `schema/v1` are the
 * contract; the guards below enforce the same shape by hand so that a 3 KB bundle can validate.
 */

export const PROTOCOL = 1;
/** Messages larger than this (JSON length) are dropped. */
export const MAX_MESSAGE_BYTES = 16 * 1024;
export const READY_TIMEOUT_MS = 10_000;

export type Display = "inline" | "background" | "fullscreen";
export type Chrome = "full" | "minimal" | "none";

export interface Capabilities {
  steps?: boolean;
  reducedMotion?: boolean;
  score?: boolean;
  background?: boolean;
  locales?: string[];
}

export interface InitPayload {
  locale: string;
  theme: Record<string, string>;
  reducedMotion: boolean;
  display: Display;
  chrome: Chrome;
  startStep?: string;
  range?: { from?: string; to?: string };
  readOnly?: boolean;
  /**
   * A decorative, self-running view for a landing hero: no controls and no text, nothing focusable, slow
   * motion, and the package follows `goToStep` from the page, which cycles the manifest's `showcase.steps`.
   */
  showcase?: boolean;
}

export type ParentMessage =
  | ({ type: "init" } & InitPayload)
  | { type: "goToStep"; step: string }
  | { type: "setTheme"; theme: Record<string, string> }
  | { type: "setLocale"; locale: string }
  | { type: "pause" }
  | { type: "resume" };

export type PackageMessage =
  | { type: "ready"; protocol: number; steps: string[]; capabilities: Capabilities }
  | { type: "resize"; height: number }
  | { type: "stepChanged"; step: string }
  | { type: "progress"; value: number }
  | { type: "complete" }
  | { type: "score"; raw: number; max: number; passed?: boolean }
  | { type: "event"; verb: string; object: string; result?: { response?: string; success?: boolean; score?: number } }
  | { type: "error"; code: string; message?: string };

export type Message = ParentMessage | PackageMessage;
export type Envelope<M extends Message = Message> = { "ulams-ix": 1; nonce: string } & M;

export const PARENT_TYPES = ["init", "goToStep", "setTheme", "setLocale", "pause", "resume"] as const;
export const PACKAGE_TYPES = ["ready", "resize", "stepChanged", "progress", "complete", "score", "event", "error"] as const;

const ID = /^[a-z0-9][a-z0-9-]{0,63}$/;
const CODE = /^[a-z0-9][a-z0-9-]{0,47}$/;
const IRI = /^[a-z][a-z0-9+.-]*:\S+$/i;

const isObj = (v: unknown): v is Record<string, unknown> => typeof v === "object" && v !== null && !Array.isArray(v);
const isStr = (v: unknown, max = 256): v is string => typeof v === "string" && v.length > 0 && v.length <= max;
const isNum = (v: unknown): v is number => typeof v === "number" && Number.isFinite(v);
const isTokens = (v: unknown): v is Record<string, string> =>
  isObj(v) && Object.keys(v).length <= 64 && Object.entries(v).every(([k, x]) => /^--[a-z0-9-]{1,64}$/.test(k) && typeof x === "string" && x.length <= 200);
const optStep = (v: unknown) => v === undefined || (typeof v === "string" && ID.test(v));

/** True when the JSON size of a value is within the 16 KB limit (and it can be serialised at all). */
export function withinSize(data: unknown): boolean {
  try {
    const json = JSON.stringify(data);
    return typeof json === "string" && json.length <= MAX_MESSAGE_BYTES;
  } catch {
    return false;
  }
}

/** Validates the type-specific payload of a parent-to-package message. */
export function isParentMessage(m: Record<string, unknown>): boolean {
  switch (m.type) {
    case "init": {
      const r = m.range;
      return (
        isStr(m.locale, 16) &&
        isTokens(m.theme) &&
        typeof m.reducedMotion === "boolean" &&
        (m.display === "inline" || m.display === "background" || m.display === "fullscreen") &&
        (m.chrome === "full" || m.chrome === "minimal" || m.chrome === "none") &&
        optStep(m.startStep) &&
        (r === undefined || (isObj(r) && optStep(r.from) && optStep(r.to))) &&
        (m.readOnly === undefined || typeof m.readOnly === "boolean") &&
        (m.showcase === undefined || typeof m.showcase === "boolean")
      );
    }
    case "goToStep":
      return typeof m.step === "string" && ID.test(m.step);
    case "setTheme":
      return isTokens(m.theme);
    case "setLocale":
      return isStr(m.locale, 16);
    case "pause":
    case "resume":
      return true;
    default:
      return false;
  }
}

/** Validates the type-specific payload of a package-to-parent message. */
export function isPackageMessage(m: Record<string, unknown>): boolean {
  switch (m.type) {
    case "ready": {
      const c = m.capabilities;
      return (
        m.protocol === PROTOCOL &&
        Array.isArray(m.steps) &&
        m.steps.length <= 200 &&
        m.steps.every((s) => typeof s === "string" && ID.test(s)) &&
        isObj(c) &&
        ["steps", "reducedMotion", "score", "background"].every((k) => c[k] === undefined || typeof c[k] === "boolean") &&
        (c.locales === undefined || (Array.isArray(c.locales) && c.locales.length <= 20 && c.locales.every((l) => isStr(l, 16))))
      );
    }
    case "resize":
      return isNum(m.height) && m.height >= 0 && m.height <= 100_000;
    case "stepChanged":
      return typeof m.step === "string" && ID.test(m.step);
    case "progress":
      return isNum(m.value) && m.value >= 0 && m.value <= 1;
    case "complete":
      return true;
    case "score":
      return isNum(m.raw) && isNum(m.max) && m.max > 0 && m.raw >= 0 && (m.passed === undefined || typeof m.passed === "boolean");
    case "event": {
      const r = m.result;
      return (
        typeof m.verb === "string" &&
        m.verb.length <= 200 &&
        IRI.test(m.verb) &&
        typeof m.object === "string" &&
        ID.test(m.object) &&
        (r === undefined ||
          (isObj(r) &&
            (r.response === undefined || (typeof r.response === "string" && r.response.length <= 500)) &&
            (r.success === undefined || typeof r.success === "boolean") &&
            (r.score === undefined || isNum(r.score))))
      );
    }
    case "error":
      return typeof m.code === "string" && CODE.test(m.code) && (m.message === undefined || (typeof m.message === "string" && m.message.length <= 500));
    default:
      return false;
  }
}

/**
 * Parses an incoming message for one direction. Returns the envelope, or null when the data is not a
 * v1 message, is too large, has the wrong nonce, an unknown type or an invalid payload.
 */
export function parseMessage(data: unknown, direction: "toPackage", nonce: string | null): Envelope<ParentMessage> | null;
export function parseMessage(data: unknown, direction: "toParent", nonce: string | null): Envelope<PackageMessage> | null;
export function parseMessage(data: unknown, direction: "toPackage" | "toParent", nonce: string | null): Envelope | null {
  if (!isObj(data) || data["ulams-ix"] !== PROTOCOL || typeof data.type !== "string") return null;
  if (typeof data.nonce !== "string" || data.nonce.length < 8 || data.nonce.length > 128) return null;
  // The package learns the nonce from `init`; until then only `init` itself is considered.
  if (nonce !== null && data.nonce !== nonce) return null;
  if (!withinSize(data)) return null;
  const ok = direction === "toPackage" ? isParentMessage(data) : isPackageMessage(data);
  return ok ? (data as unknown as Envelope) : null;
}
