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
  HOSTS, MAX_URLS, SCHEDULES, gitFormProblem, gitPayload, urlFormProblem, urlPayload,
  type GitForm, type UrlForm,
} from "./connect-model.ts";

export const CHECKING_REPOSITORY = "Checking the repository and reading its files. This can take up to a minute.";
export const CHECKING_PAGES = "Checking the pages and reading their text. This can take up to a minute.";

export interface ConnectDeps {
  navigate?: (url: string) => void;
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
    h("p", { class: "cb-muted" }, "Read Markdown from GitHub, GitLab, Gitea or Forgejo. The course follows the repository: new commits become reviewable updates."),
    field("Where does it live?", host),
    field("Server address", baseUrl, "Only for Gitea, Forgejo and a self-hosted GitLab, for example https://git.example.com."),
    field("Repository", repository, "Write it as owner/name, for example ulams-dev/docs."),
    field("Branch", branch, "Leave empty for the default branch."),
    field("Folders and files to read", paths, "One pattern per line, for example docs/**/*.md. Leave empty to read every Markdown file."),
    field("Access token (optional)", token, "Public repositories work without one; GitHub then allows only 60 requests an hour. The token is stored for checks and never shown again."),
    field("Check for changes", gitSchedule, "A webhook, set up after you connect, finds pushes at once."),
    h("div", { class: "cb-actions" }, gitSubmit), gitStatus, gitError);

  const syncServerField = () => {
    const wrap = baseUrl.closest(".st-field") as HTMLElement;
    wrap.hidden = host.value === "github";
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

  const done = h("section", { class: "st-connected cb-card", "aria-labelledby": "st-connected-title", "data-connected": "", hidden: true });
  const forms = h("div", { class: "st-connect-forms" }, gitForm, urlForm);
  root.replaceChildren(
    h("h2", { class: "cb-h3", id: "st-connect-title" }, "Or connect a source that changes"),
    h("p", { class: "cb-muted" }, "Instead of a file, follow a repository or some web pages. We read them now, start the interview, and look for changes on a schedule."),
    forms, done);
  root.setAttribute("aria-labelledby", "st-connect-title");

  function setBusy(on: boolean, kind: "git" | "url", text: string): void {
    busy = on;
    for (const el of root.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | HTMLButtonElement>("input, select, textarea, button")) el.disabled = on;
    for (const form of [gitForm, urlForm]) form.toggleAttribute("aria-busy", on);
    (kind === "git" ? gitStatus : urlStatus).textContent = on ? text : "";
    if (on) announce(text);
  }

  async function run(kind: "git" | "url", payload: Parameters<typeof lc.sources.connect>[1], checking: string, errorEl: HTMLElement): Promise<void> {
    let sessionId: string | null = null;
    setBusy(true, kind, checking);
    try {
      sessionId = (await cb.sessions.create()).session.id;
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
      webhookHints(result.connection.config.host),
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
}

const root = document.querySelector<HTMLElement>("[data-studio-connect]");
if (root) mountConnect(root);
