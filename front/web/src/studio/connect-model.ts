/**
 * Pure helpers of the connection forms and the connection panel: form values to the API's connect
 * payload, plain-language labels and the setup hints for each source host. Nothing here talks to
 * the network or the DOM.
 */
import type { ConnectInput, GitHost, SourceConnection } from "@ulams/sdk";

export type Schedule = "hourly" | "daily" | "weekly" | "manual";

export const SCHEDULES: Array<{ value: Schedule; label: string }> = [
  { value: "hourly", label: "Every hour" },
  { value: "daily", label: "Every day" },
  { value: "weekly", label: "Every week" },
  { value: "manual", label: "Only when I check" },
];

export const scheduleLabel = (schedule: string | null | undefined): string => SCHEDULES.find((s) => s.value === schedule)?.label ?? "Not scheduled";

export const HOSTS: Array<{ value: GitHost; label: string }> = [
  { value: "github", label: "GitHub" },
  { value: "gitlab", label: "GitLab" },
  { value: "gitea", label: "Gitea or Forgejo" },
];

export const MAX_URLS = 20;

/** One value per line or separated by commas; blanks and repeats are dropped. */
export function splitList(text: string): string[] {
  const seen = new Set<string>();
  for (const part of text.split(/[\n,]+/)) {
    const value = part.trim();
    if (value) seen.add(value);
  }
  return [...seen];
}

export interface GitForm {
  host: string;
  baseUrl: string;
  repository: string;
  branch: string;
  paths: string;
  token: string;
  schedule: string;
}

export interface UrlForm {
  urls: string;
  selector: string;
  schedule: string;
}

const REPOSITORY = /^[A-Za-z0-9_.-]+(\/[A-Za-z0-9_.-]+)+$/;

const asSchedule = (value: string): Schedule => (SCHEDULES.some((s) => s.value === value) ? (value as Schedule) : "daily");

/** Accepts a pasted repository address and returns `owner/name`. */
export function normaliseRepository(text: string): string {
  const value = text.trim().replace(/\.git$/i, "").replace(/\/+$/, "");
  const match = value.match(/^https?:\/\/[^/]+\/(.+)$/i);
  return match ? match[1]! : value;
}

/** The first problem of the form in plain words, or null. The API checks everything again. */
export function gitFormProblem(form: GitForm): string | null {
  if (!HOSTS.some((h) => h.value === form.host)) return "Choose where the repository lives.";
  if (!REPOSITORY.test(normaliseRepository(form.repository))) return "Write the repository as owner/name, for example ulams-dev/docs.";
  if (form.host === "gitea" && !/^https?:\/\/\S+$/i.test(form.baseUrl.trim())) return "Gitea and Forgejo need the address of your server, for example https://git.example.com.";
  if (form.baseUrl.trim() !== "" && !/^https?:\/\/\S+$/i.test(form.baseUrl.trim())) return "The server address must start with https://.";
  return null;
}

export function gitPayload(form: GitForm): ConnectInput {
  const config: Record<string, unknown> = { host: form.host, repository: normaliseRepository(form.repository) };
  if (form.host !== "github" && form.baseUrl.trim()) config.base_url = form.baseUrl.trim().replace(/\/+$/, "");
  if (form.branch.trim()) config.branch = form.branch.trim();
  const paths = splitList(form.paths);
  if (paths.length) config.paths = paths;
  const token = form.token.trim();
  return { connector: "git", config, ...(token ? { secrets: { token } } : {}), schedule: asSchedule(form.schedule) };
}

export function urlFormProblem(form: UrlForm): string | null {
  const urls = splitList(form.urls);
  if (urls.length === 0) return "Add at least one page address.";
  if (urls.length > MAX_URLS) return `Add at most ${MAX_URLS} pages.`;
  const bad = urls.find((u) => !/^https:\/\/\S+$/i.test(u));
  if (bad) return `${bad} is not an https address. Use the full address, starting with https://.`;
  const hosts = new Set(urls.map((u) => { try { return new URL(u).host.toLowerCase(); } catch { return u; } }));
  if (hosts.size > 1) return "All pages must be on the same site.";
  return null;
}

export function urlPayload(form: UrlForm): ConnectInput {
  const config: Record<string, unknown> = { urls: splitList(form.urls) };
  if (form.selector.trim()) config.selector = form.selector.trim();
  return { connector: "url", config, schedule: asSchedule(form.schedule) };
}

/* ------------------------------------------------------------------ connection panel */

export type ConnectionState = "active" | "paused" | "error";

/** `error` wins over the stored status: a source that failed its last check says so even while it is still scheduled. */
export function connectionState(c: Pick<SourceConnection, "status" | "lastError">): ConnectionState {
  if (c.status === "error" || c.lastError) return "error";
  return c.status === "paused" ? "paused" : "active";
}

export const STATE_TEXT: Record<ConnectionState, string> = { active: "Active", paused: "Paused", error: "Error" };

export function hostLabel(host: unknown): string {
  return HOSTS.find((h) => h.value === host)?.label ?? String(host ?? "");
}

export interface ConnectionFact {
  label: string;
  value: string;
}

/** What the connection reads from, in words: host and repository and branch, or the pages. */
export function connectionFacts(c: SourceConnection): ConnectionFact[] {
  const cfg = c.config ?? {};
  if (c.connector === "git") {
    const facts: ConnectionFact[] = [
      { label: "Host", value: hostLabel(cfg.host) + (typeof cfg.base_url === "string" && cfg.base_url ? ` (${cfg.base_url})` : "") },
      { label: "Repository", value: String(cfg.repository ?? "") },
      { label: "Branch", value: String(cfg.branch ?? "default branch") },
    ];
    const paths = Array.isArray(cfg.paths) ? cfg.paths.map(String) : [];
    if (paths.length) facts.push({ label: "Paths", value: paths.join(", ") });
    return facts;
  }
  if (c.connector === "url") {
    const urls = Array.isArray(cfg.urls) ? cfg.urls.map(String) : [];
    const facts: ConnectionFact[] = [{ label: urls.length === 1 ? "Page" : "Pages", value: urls.length > 1 ? `${urls.length} pages on ${hostOf(urls[0])}` : (urls[0] ?? "") }];
    if (typeof cfg.selector === "string" && cfg.selector) facts.push({ label: "Content selector", value: cfg.selector });
    return facts;
  }
  return [];
}

function hostOf(url: string | undefined): string {
  try {
    return new URL(url ?? "").host;
  } catch {
    return url ?? "";
  }
}

/** Pages of a URL source, for a list under the facts. */
export function connectionUrls(c: SourceConnection): string[] {
  const urls = (c.config ?? {}).urls;
  return c.connector === "url" && Array.isArray(urls) ? urls.map(String) : [];
}

export const hasToken = (c: Pick<SourceConnection, "secretsSet">): boolean => (c.secretsSet ?? []).includes("token");

export interface HostHint {
  host: GitHost;
  title: string;
  steps: string[];
}

/** Setup steps on the source host; the same three values everywhere: URL, JSON, secret, push events. */
export const WEBHOOK_HINTS: HostHint[] = [
  {
    host: "github",
    title: "GitHub",
    steps: [
      "Open the repository, then Settings, Webhooks, Add webhook.",
      "Paste the webhook URL into Payload URL and set Content type to application/json.",
      "Paste the secret into Secret.",
      "Choose Just the push event and save.",
    ],
  },
  {
    host: "gitlab",
    title: "GitLab",
    steps: [
      "Open the project, then Settings, Webhooks, Add new webhook.",
      "Paste the webhook URL into URL and the secret into Secret token.",
      "Tick Push events for your branch and save.",
    ],
  },
  {
    host: "gitea",
    title: "Gitea or Forgejo",
    steps: [
      "Open the repository, then Settings, Webhooks, Add webhook, Gitea (or Forgejo).",
      "Paste the webhook URL into Target URL and set Content type to application/json.",
      "Paste the secret into Secret and choose Push events only.",
    ],
  },
];

export const hintFor = (host: unknown): HostHint => WEBHOOK_HINTS.find((h) => h.host === host) ?? WEBHOOK_HINTS[0]!;

export const SECRET_WARNING = "Copy this secret now. It is shown only once; if you lose it, rotate it to get a new one.";

/** The queued state of "Check now", in words. */
export const CHECK_QUEUED = "Check queued. We are looking for new changes; this page refreshes when it finishes.";
export const CHECK_DONE = "The check finished.";
export const CHECK_PENDING_LONG = "The check is still running. It will show up here when it finishes.";
