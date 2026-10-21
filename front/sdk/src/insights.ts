/**
 * Learner Insights, learner side (`/api/learner-insights`): the "Extra help for you" items of a lesson with
 * their feedback and hide actions, the Feynman reflection, the lesson-view preferences, and the learner's own
 * status, settings, export and erasure. Every call acts on the caller only. REST calls return the unwrapped
 * `data`. In the browser `baseUrl` is the learner BFF (`/bff`), which adds the session token.
 */
import { ApiError, type ClientOptions } from "./client.ts";

export interface InsightsReason {
  rule: string;
  message: string;
}

export interface InsightsCourse {
  courseId: number;
  title: string | null;
  status: "on_track" | "struggling" | "at_risk";
  reasons: InsightsReason[];
  since: string | null;
  elements: Array<{ elementKey: string; status: string; reasons: InsightsReason[] }>;
}

export interface InsightsSettings {
  enabled: boolean;
  help: boolean;
  nudges: boolean;
  consent: string | null;
}

export interface InsightsMe {
  enabled: boolean;
  settings: InsightsSettings;
  requiresConsent: boolean;
  courses: InsightsCourse[];
  retention: { signalsDays: number; historyDays: number };
}

export interface SettingsChange {
  enabled: boolean;
  help: boolean;
  nudges: boolean;
  consent?: boolean;
  eraseExisting?: boolean;
}

/** One stored block of a remediation: a catalogue component with its props, and the fragments it cites. */
export interface SupportBlock {
  component: string;
  props: Record<string, unknown>;
  citations?: string[];
}

export interface SupportCitation {
  fragmentId: string;
  /** The section the fragment comes from, as plain text (fragments have no learner URL). */
  label: string;
}

export type SupportStatus = "generating" | "ready";
export type SupportFeedback = "helpful" | "not_helpful";

export interface SupportItem {
  /** 26-character ULID of the learner's assignment. */
  assignmentId: string;
  kind: "explanation" | "worked_example" | "practice" | "elaboration";
  status: SupportStatus;
  /** Why this help is shown, in plain text. */
  reasons: string[];
  why: string | null;
  feedback: SupportFeedback | null;
  /** Null until the remediation is ready. */
  document: { summary: string; blocks: SupportBlock[] } | null;
  citations: SupportCitation[];
  aiLabel?: string;
}

export interface Support {
  /** False when the module is off for the tenant, the learner opted out or help is switched off. */
  enabled: boolean;
  items: SupportItem[];
}

export interface ReflectionFeedback {
  correct: string;
  missing: string;
  followUp: string;
  citations: SupportCitation[];
  /** Reflections left today. */
  remaining: number;
}

export interface Presentation {
  density: "comfortable" | "compact";
  chunking: "whole" | "steps";
  navigation: "linear" | "overview";
  hints: 0 | 1 | 2 | 3;
}

export interface PresentationState {
  /** The tenant derives suggestions from the learner's progress. */
  adaptive: boolean;
  /** What the learner sees: the derived suggestion with the learner's own choices on top. */
  profile: Presentation;
  derived: Presentation;
  overrides: Partial<Presentation>;
  /** Plain-text reasons for the suggestion. */
  why: string[];
}

/** A change to the learner's choices; `null` removes one and falls back to the suggestion. */
export type PresentationChange = { [K in keyof Presentation]?: Presentation[K] | null };

const PREFIX = "/api/learner-insights";

export type InsightsClient = ReturnType<typeof createInsightsClient>;

export function createInsightsClient(options: ClientOptions) {
  const base = options.baseUrl.replace(/\/+$/, "") + PREFIX;
  const doFetch = options.fetch ?? ((input: RequestInfo | URL, init?: RequestInit) => fetch(input, init));

  async function send(method: "GET" | "POST" | "PUT" | "DELETE", path: string, body?: unknown): Promise<string> {
    const headers: Record<string, string> = {
      Accept: "application/json",
      "Current-timezone": options.timezone ?? "UTC",
      ...options.headers,
    };
    if (body !== undefined) headers["Content-Type"] = "application/json";
    if (options.token) headers.Authorization = `Bearer ${options.token}`;
    let response: Response;
    try {
      response = await doFetch(`${base}${path}`, {
        method,
        headers,
        body: body === undefined ? undefined : JSON.stringify(body),
        signal: AbortSignal.timeout(options.timeoutMs ?? 15_000),
      });
    } catch (error) {
      throw new ApiError(0, path, null, `API unreachable on ${path}: ${(error as Error).message}`);
    }
    const text = await response.text();
    if (!response.ok) {
      let json: { message?: string } | null;
      try {
        json = text ? JSON.parse(text) : null;
      } catch {
        json = { message: text.slice(0, 200) };
      }
      throw new ApiError(response.status, path, json, json?.message ?? `API ${response.status} on ${path}`);
    }
    return text;
  }

  async function call<T>(method: "GET" | "POST" | "PUT" | "DELETE", path: string, body?: unknown): Promise<T | null> {
    const text = await send(method, path, body);
    try {
      return (text ? (JSON.parse(text) as { data?: T }).data : null) ?? null;
    } catch {
      return null;
    }
  }

  return {
    base,
    /** The caller's extra-help items for a lesson, oldest first (hidden items are not returned). */
    support: async (courseId: number, topicId: number): Promise<Support> =>
      (await call<Support>("GET", `/courses/${courseId}/topics/${topicId}/support`)) ?? { enabled: false, items: [] },
    /** "This helped" / "Not helpful". */
    feedback: async (assignmentId: string, helpful: boolean): Promise<{ feedback: SupportFeedback }> =>
      (await call<{ feedback: SupportFeedback }>("POST", `/assignments/${assignmentId}/feedback`, { helpful })) ?? { feedback: helpful ? "helpful" : "not_helpful" },
    /** Hides one item for the learner; it does not come back. */
    hide: async (assignmentId: string): Promise<{ hidden: boolean }> => (await call<{ hidden: boolean }>("POST", `/assignments/${assignmentId}/hide`)) ?? { hidden: true },
    /** Feedback on what the learner wrote in their own words; the text is never stored. 429 = daily limit, 503 = AI off or failed. */
    reflection: async (assignmentId: string, text: string): Promise<ReflectionFeedback> => {
      const data = await call<ReflectionFeedback>("POST", `/assignments/${assignmentId}/reflection`, { text });
      if (!data) throw new ApiError(502, `/assignments/${assignmentId}/reflection`, null, "The feedback came back empty.");
      return data;
    },
    presentation: async (courseId: number): Promise<PresentationState | null> => call<PresentationState>("GET", `/courses/${courseId}/presentation`),
    savePresentation: async (courseId: number, change: PresentationChange): Promise<PresentationState | null> => call<PresentationState>("PUT", `/courses/${courseId}/presentation`, change),
    me: async (): Promise<InsightsMe | null> => call<InsightsMe>("GET", "/me"),
    saveSettings: async (change: SettingsChange): Promise<InsightsSettings | null> => call<InsightsSettings>("PUT", "/me/settings", change),
    /** The JSON file of everything stored about the learner, as the API sends it. */
    exportData: async (): Promise<string> => send("GET", "/me/export"),
    /** Deletes everything stored about the learner. */
    erase: async (): Promise<{ deleted: unknown }> => (await call<{ deleted: unknown }>("DELETE", "/me")) ?? { deleted: true },
  };
}
