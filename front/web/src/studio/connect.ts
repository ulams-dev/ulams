/**
 * "Connect a repository" and "Add web pages" on /studio/new: two forms that create a builder
 * session, ask the API to connect the source (it checks the settings, reads revision 1 and starts
 * the interview) and continue into the normal builder at /studio/s/{id}. When the API refuses the
 * source the session is removed again and the API's message is shown as it is.
 */
import type { ConnectResult } from "@ulams/sdk";
import { h, uid } from "@ulams/ui/builder/dom.ts";
import { announce, livingClient, message, studioClient } from "./common.ts";
import { copyField, secretReveal, webhookHints } from "./connection.ts";
import {
  ACCOUNT_SOURCES, HOSTS, MAX_URLS, SCHEDULES, accountFormProblem, accountPayload, gitFormProblem, gitPayload, urlFormProblem, urlPayload,
  type AccountForm, type GitForm, type UrlForm,
} from "./connect-model.ts";

export const CHECKING_REPOSITORY = "Checking the repository and reading its files. This can take up to a minute.";
export const CHECKING_ACCOUNT = "Checking the file and reading its text. This can take up to a minute.";
export const CHECKING_PAGES = "Checking the pages and reading their text. This can take up to a minute.";

export interface ConnectDeps {
  navigate?: (url: string) => void;
}

/** The form values wait here while the browser is at Google or Notion; no token is ever kept. */
const PENDING = "ulams.studio.oauth-pending";

function remember(value: { sessionId: string; values: AccountForm } | null): void {
  try {
    if (value) window.sessionStorage.setItem(PENDING, JSON.stringify(value));
    else window.sessionStorage.removeItem(PENDING);
  } catch {
    // storage can be blocked; the sign-in then asks for the details again
  }
}

function recall(): { sessionId: string; values: AccountForm } | null {
  try {
    const raw = window.sessionStorage.getItem(PENDING);
    const value = raw ? (JSON.parse(raw) as { sessionId?: unknown; values?: Partial<AccountForm> }) : null;
    return value && typeof value.sessionId === "string" && value.values && typeof value.values.source === "string" ? (value as { sessionId: string; values: AccountForm }) : null;
  } catch {
    return null;
  }
}

function field(label: string, control: HTMLElement, hint?: string): HTMLElement {
  const id = control.id;
  return h("div", { class: "st-field" },
    h("label", { for: id, class: "cb-label" }, label),
    control,
    hint ? h("p", { id: `${id}-hint`, class: "cb-muted cb-small" }, hint) : null);
}

function control<K extends "input" | "select" | "textarea">(tag: K, name: string, attrs: Record<string, string | boolean | undefined> = {}, hint = false): HTMLElementTagNameMap[K] {
  const id = uid(`cn-${name}`);
  return h(tag, { id, name, class: tag === "input" ? "cb-input" : tag === "select" ? "cb-select" : "cb-textarea", "aria-describedby": hint ? `${id}-hint` : undefined, ...attrs }) as HTMLElementTagNameMap[K];
}

function scheduleSelect(): HTMLSelectElement {
  const select = control("select", "schedule", {}, true);
  select.append(...SCHEDULES.map((s) => h("option", { value: s.value }, s.label)));
  select.value = "daily";
  return select;
}

export function mountConnect(root: HTMLElement, deps: ConnectDeps = {}): void {
  const cb = studioClient();
  const lc = livingClient();
  const navigate = deps.navigate ?? ((url: string) => window.location.assign(url));
  let busy = false;

  /* ------------------------------------------------------------ repository */
  const host = control("select", "host");
  host.append(...HOSTS.map((o) => h("option", { value: o.value }, o.label)));
  const baseUrl = control("input", "base_url", { type: "url", placeholder: "https://git.example.com", autocomplete: "off" }, true);
  const repository = control("input", "repository", { type: "text", placeholder: "ulams-dev/docs", autocomplete: "off", spellcheck: "false", required: true }, true);
  const branch = control("input", "branch", { type: "text", placeholder: "main", autocomplete: "off", spellcheck: "false" }, true);
  const paths = control("textarea", "paths", { rows: "3", placeholder: "docs/**/*.md", spellcheck: "false" }, true);
  const token = control("input", "token", { type: "password", autocomplete: "off", spellcheck: "false" }, true);
  const gitSchedule = scheduleSelect();
  const gitStatus = h("p", { class: "cb-muted", role: "status", "data-status": "" });
  const gitError = h("p", { class: "cb-error", role: "alert", "data-error": "" });
  const gitSubmit = h("button", { type: "submit", class: "cb-btn cb-btn-primary" }, "Connect the repository") as HTMLButtonElement;
  const gitForm = h("form", { class: "st-connect-form cb-card", "aria-labelledby": "st-connect-git-title", "data-git-form": "", novalidate: true },
    h("h2", { id: "st-connect-git-title", class: "st-drop-title" }, "Connect a repository"),
    h("p", { class: "cb-muted" }, "Read Markdown from GitHub, GitLab, Gitea, Forgejo or another Git server. The course follows the repository: new commits become reviewable updates."),
    field("Where does it live?", host),
    field("Server address", baseUrl, "Only for Gitea, Forgejo and a self-hosted GitLab, for example https://git.example.com."),
    field("Repository", repository, "Write it as owner/name, for example ulams-dev/docs. For another server, the full https address, for example https://git.example.com/team/docs.git (an admin must have turned this on)."),
    field("Branch", branch, "Leave empty for the default branch."),
    field("Folders and files to read", paths, "One pattern per line, for example docs/**/*.md. Leave empty to read every Markdown file."),
    field("Access token (optional)", token, "Public repositories work without one; GitHub then allows only 60 requests an hour. The token is stored for checks and never shown again."),
    field("Check for changes", gitSchedule, "A webhook, set up after you connect, finds pushes at once."),
    h("div", { class: "cb-actions" }, gitSubmit), gitStatus, gitError);

  const syncServerField = () => {
    const wrap = baseUrl.closest(".st-field") as HTMLElement;
    wrap.hidden = host.value === "github" || host.value === "other";
    baseUrl.required = host.value === "gitea";
  };
  host.addEventListener("change", syncServerField);
  syncServerField();

  /* ------------------------------------------------------------ web pages */
  const urls = control("textarea", "urls", { rows: "4", placeholder: "https://docs.example.com/guide\nhttps://docs.example.com/faq", spellcheck: "false", required: true }, true);
  const selector = control("input", "selector", { type: "text", placeholder: "main", autocomplete: "off", spellcheck: "false" }, true);
  const urlSchedule = scheduleSelect();
  const urlStatus = h("p", { class: "cb-muted", role: "status", "data-status": "" });
  const urlError = h("p", { class: "cb-error", role: "alert", "data-error": "" });
  const urlSubmit = h("button", { type: "submit", class: "cb-btn cb-btn-primary" }, "Add the pages") as HTMLButtonElement;
  const urlForm = h("form", { class: "st-connect-form cb-card", "aria-labelledby": "st-connect-url-title", "data-url-form": "", novalidate: true },
    h("h2", { id: "st-connect-url-title", class: "st-drop-title" }, "Add web pages"),
    h("p", { class: "cb-muted" }, "Read the text of public pages and keep the course in step when they change."),
    field("Page addresses", urls, `One address per line, up to ${MAX_URLS}, all on the same site and starting with https://.`),
    field("Main content selector (optional)", selector, "A CSS selector for the part of the page to read, for example main or article. Leave empty to read the main content."),
    field("Check for changes", urlSchedule, "Pages are checked on this schedule; there is no webhook for web pages."),
    h("div", { class: "cb-actions" }, urlSubmit), urlStatus, urlError);

  /* ------------------------------------------------------------ Google Drive and Notion */
  const accountSource = control("select", "account_source");
  accountSource.append(...ACCOUNT_SOURCES.map((o) => h("option", { value: o.value }, o.label)));
  const accountAddress = control("input", "account_address", { type: "text", autocomplete: "off", spellcheck: "false", required: true }, true);
  const accountChildren = control("input", "account_children", { type: "checkbox" });
  const accountSchedule = scheduleSelect();
  const accountStatus = h("p", { class: "cb-muted", role: "status", "data-status": "" });
  const accountError = h("p", { class: "cb-error", role: "alert", "data-error": "" });
  const accountSubmit = h("button", { type: "submit", class: "cb-btn cb-btn-primary" }, "Sign in and connect") as HTMLButtonElement;
  const childrenField = field("Also read the pages under it", accountChildren, "Up to three levels and 100 pages.");
  const addressField = field("Address", accountAddress, ACCOUNT_SOURCES[0]!.hint);
  const accountForm = h("form", { class: "st-connect-form cb-card", "aria-labelledby": "st-connect-account-title", "data-account-form": "", novalidate: true, hidden: true },
    h("h2", { id: "st-connect-account-title", class: "st-drop-title" }, "Connect Google Drive or Notion"),
    h("p", { class: "cb-muted" }, "Sign in once with read-only access. The course follows the file or page: edits become reviewable updates."),
    field("What to connect", accountSource),
    addressField,
    childrenField,
    field("Check for changes", accountSchedule, "These sources have no webhook: they are checked on this schedule."),
    h("div", { class: "cb-actions" }, accountSubmit), accountStatus, accountError);
  const syncAccountFields = () => {
    const spec = ACCOUNT_SOURCES.find((s) => s.value === accountSource.value) ?? ACCOUNT_SOURCES[0]!;
    childrenField.hidden = spec.value !== "notion";
    const hint = addressField.querySelector(".cb-small");
    if (hint) hint.textContent = spec.hint;
  };
  accountSource.addEventListener("change", syncAccountFields);
  syncAccountFields();

  const done = h("section", { class: "st-connected cb-card", "aria-labelledby": "st-connected-title", "data-connected": "", hidden: true });
  const forms = h("div", { class: "st-connect-forms" }, gitForm, urlForm, accountForm);
  root.replaceChildren(
    h("h2", { class: "cb-h3", id: "st-connect-title" }, "Or connect a source that changes"),
    h("p", { class: "cb-muted" }, "Instead of a file, follow a repository or some web pages. We read them now, start the interview, and look for changes on a schedule."),
    forms, done);
  root.setAttribute("aria-labelledby", "st-connect-title");

  function setBusy(on: boolean, kind: "git" | "url" | "account", text: string): void {
    busy = on;
    for (const el of root.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | HTMLButtonElement>("input, select, textarea, button")) el.disabled = on;
    for (const form of [gitForm, urlForm, accountForm]) form.toggleAttribute("aria-busy", on);
    (kind === "git" ? gitStatus : kind === "url" ? urlStatus : accountStatus).textContent = on ? text : "";
    if (on) announce(text);
  }

  async function run(kind: "git" | "url" | "account", payload: Parameters<typeof lc.sources.connect>[1], checking: string, errorEl: HTMLElement, existingSession?: string): Promise<void> {
    let sessionId: string | null = existingSession ?? null;
    setBusy(true, kind, checking);
    try {
      sessionId ??= (await cb.sessions.create()).session.id;
      const result = await lc.sources.connect(sessionId, payload);
      setBusy(false, kind, "");
      connected(sessionId, result);
    } catch (e) {
      setBusy(false, kind, "");
      if (sessionId) await cb.sessions.delete(sessionId).catch(() => undefined);
      errorEl.textContent = message(e, "The source could not be connected. Try again.");
      announce(errorEl.textContent);
      // the refusal is read first, then the author goes back to the field
      errorEl.scrollIntoView?.({ block: "nearest" });
    }
  }

  function connected(sessionId: string, result: ConnectResult): void {
    const target = `/studio/s/${sessionId}`;
    if (!result.webhookSecret) {
      announce("Connected. Starting the interview.");
      navigate(target);
      return;
    }
    // the secret exists only now: the author copies it before moving on
    forms.hidden = true;
    done.hidden = false;
    const link = h("a", { class: "cb-btn cb-btn-primary", href: target, "data-continue": "" }, "Continue to the interview");
    const parts: Array<Node | null> = [
      h("h2", { id: "st-connected-title", class: "cb-h3", tabindex: "-1" }, "Connected"),
      h("p", {}, `${result.source.title ?? result.source.name} is connected and its first revision is read. The interview starts on the next page.`),
      h("h3", { class: "cb-h3" }, "Optional: tell us when someone pushes"),
      h("p", { class: "cb-muted" }, "Add a webhook on your host so changes are found at once. Without it we still check on your schedule."),
      result.connection.webhookUrl ? copyField("Webhook URL", result.connection.webhookUrl, { "data-webhook-url": "" }) : null,
      secretReveal(result.webhookSecret),
      webhookHints(result.connection.connector === "git-cli" ? "other" : result.connection.config.host),
      h("div", { class: "cb-actions" }, link),
    ];
    done.replaceChildren(...parts.filter((n): n is Node => n !== null));
    announce("Connected. Copy the webhook secret now: it is shown only once.");
    done.querySelector<HTMLElement>("#st-connected-title")?.focus();
  }

  gitForm.addEventListener("submit", (event) => {
    event.preventDefault();
    if (busy) return;
    gitError.textContent = "";
    const values: GitForm = {
      host: host.value, baseUrl: baseUrl.value, repository: repository.value, branch: branch.value, paths: paths.value, token: token.value, schedule: gitSchedule.value,
    };
    const problem = gitFormProblem(values);
    if (problem) {
      gitError.textContent = problem;
      (host.value === "gitea" && /server/i.test(problem) ? baseUrl : repository).focus();
      return;
    }
    void run("git", gitPayload(values), CHECKING_REPOSITORY, gitError);
  });

  accountForm.addEventListener("submit", (event) => {
    event.preventDefault();
    if (busy) return;
    accountError.textContent = "";
    const values: AccountForm = { source: accountSource.value, address: accountAddress.value, children: accountChildren.checked, schedule: accountSchedule.value };
    const problem = accountFormProblem(values);
    if (problem) {
      accountError.textContent = problem;
      accountAddress.focus();
      return;
    }
    const provider = (ACCOUNT_SOURCES.find((s) => s.value === values.source) ?? ACCOUNT_SOURCES[0]!).provider;
    void (async () => {
      let sessionId: string | null = null;
      setBusy(true, "account", "Opening the sign-in…");
      try {
        sessionId = (await cb.sessions.create()).session.id;
        const { url } = await lc.oauth.start(provider, sessionId);
        remember({ sessionId, values });
        navigate(url);
      } catch (e) {
        setBusy(false, "account", "");
        if (sessionId) await cb.sessions.delete(sessionId).catch(() => undefined);
        accountError.textContent = message(e, "The sign-in could not be started. Try again.");
        announce(accountError.textContent);
      }
    })();
  });

  urlForm.addEventListener("submit", (event) => {
    event.preventDefault();
    if (busy) return;
    urlError.textContent = "";
    const values: UrlForm = { urls: urls.value, selector: selector.value, schedule: urlSchedule.value };
    const problem = urlFormProblem(values);
    if (problem) {
      urlError.textContent = problem;
      urls.focus();
      return;
    }
    void run("url", urlPayload(values), CHECKING_PAGES, urlError);
  });

  /* ------------------------------------------------------------ offer the account sources, and finish a sign-in */
  void lc.connectors.list().then((list) => {
    const offered = list.filter((c) => c.key === "google-drive" || c.key === "notion");
    if (offered.length === 0) return;
    for (const option of [...accountSource.options]) if (!offered.some((c) => c.key === option.value)) option.remove();
    accountForm.hidden = false;
    syncAccountFields();
    const notReady = offered.filter((c) => c.oauth && !c.oauth.configured).map((c) => c.label);
    if (notReady.length === offered.length) {
      accountSubmit.disabled = true;
      accountStatus.textContent = `Signing in with ${notReady.join(" or ")} is not set up on this installation. Ask an admin to add the OAuth client.`;
    }
  }).catch(() => undefined);

  const back = new URLSearchParams(window.location.search);
  const pending = recall();
  if (pending && (back.has("grant") || back.has("oauth_error"))) {
    accountForm.hidden = false;
    remember(null);
    window.history.replaceState(null, "", window.location.pathname);
    const grant = back.get("grant");
    if (grant) {
      accountSource.value = pending.values.source;
      void run("account", accountPayload(pending.values, grant), CHECKING_ACCOUNT, accountError, pending.sessionId);
    } else {
      void cb.sessions.delete(pending.sessionId).catch(() => undefined);
      accountError.textContent = back.get("oauth_error") || "The sign-in did not finish. Try again.";
      announce(accountError.textContent);
    }
  }
}

const root = document.querySelector<HTMLElement>("[data-studio-connect]");
if (root) mountConnect(root);
