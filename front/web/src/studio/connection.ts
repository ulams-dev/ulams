/**
 * The connection panel of a connected source on the Sources page: what it reads from, its state,
 * the schedule, pause and resume, "Check now", the write-only token and the webhook of a Git
 * source. The secrets are never shown after they were set: the token only says "Token set", and
 * a webhook secret appears once, right after it was created or rotated.
 */
import type { LivingCourseClient, LivingSource } from "@ulams/sdk";
import { h, uid } from "@ulams/ui/builder/dom.ts";
import { announce, message } from "./common.ts";
import {
  CHECK_DONE, CHECK_PENDING_LONG, CHECK_QUEUED, SCHEDULES, SECRET_WARNING, STATE_TEXT, connectionFacts, connectionState, connectionUrls, hasToken, hintFor,
  type ConnectionState,
} from "./connect-model.ts";

const ICON_PATH: Record<ConnectionState, string> = {
  active: "M4 10.5l4 4 8-9",
  paused: "M7 4v12M13 4v12",
  error: "M10 3l8 14H2L10 3zM10 8v4M10 14.5v.5",
};

function stateIcon(state: ConnectionState): SVGElement {
  const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
  svg.setAttribute("viewBox", "0 0 20 20");
  svg.setAttribute("aria-hidden", "true");
  svg.setAttribute("class", "cb-icon");
  const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
  path.setAttribute("d", ICON_PATH[state]);
  svg.append(path);
  return svg;
}

const when = (iso: string | null | undefined, empty: string): string => {
  if (!iso) return empty;
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? iso : date.toLocaleString("en-GB", { dateStyle: "medium", timeStyle: "short" });
};

/** A button that copies the value of a field; the field is selected as a fallback. */
export function copyButton(input: HTMLInputElement, label: string): HTMLButtonElement {
  const button = h("button", { type: "button", class: "cb-btn cb-btn-small", "data-copy": "" }, `Copy ${label}`) as HTMLButtonElement;
  button.addEventListener("click", async () => {
    input.select();
    try {
      await navigator.clipboard.writeText(input.value);
      announce(`${label} copied.`);
      button.textContent = "Copied";
    } catch {
      announce(`Press Ctrl+C or Cmd+C to copy the ${label}.`);
      button.textContent = "Press Ctrl+C to copy";
    }
    window.setTimeout(() => (button.textContent = `Copy ${label}`), 2500);
  });
  return button;
}

/** A read-only field with its copy button. */
export function copyField(label: string, value: string, extra: Record<string, string> = {}): HTMLElement {
  const id = uid("cf");
  const input = h("input", { type: "text", id, class: "cb-input st-mono-field", readonly: true, value, autocomplete: "off", spellcheck: "false", ...extra }) as HTMLInputElement;
  input.value = value;
  return h("div", { class: "st-copy" }, h("label", { for: id, class: "cb-label" }, label), h("div", { class: "st-copy-row" }, input, copyButton(input, label.toLowerCase())));
}

/** The webhook secret, with the warning that it is shown once. */
export function secretReveal(secret: string): HTMLElement {
  return h("div", { class: "st-secret", role: "group", "aria-label": "Webhook secret", "data-secret": "" },
    copyField("Webhook secret", secret),
    h("p", { class: "st-secret-warning", "data-secret-warning": "" }, SECRET_WARNING));
}

/** Setup steps on the source host. */
export function webhookHints(host: unknown): HTMLElement {
  const hint = hintFor(host);
  return h("details", { class: "st-hint", "data-hints": "" },
    h("summary", {}, `Set up the webhook on ${hint.title}`),
    h("ol", {}, hint.steps.map((s) => h("li", {}, s))),
    h("p", { class: "cb-muted cb-small" }, "Without a webhook we still check on your schedule. Use the same three values on any host: the URL, content type JSON, and the secret, for push events only."));
}

export interface PanelOptions {
  lc: LivingCourseClient;
  source: LivingSource;
  /** Reloads the source from the API and re-renders what depends on it (the card). */
  refresh(): Promise<void>;
  /** Reloads the revisions after a check found something new. */
  reloadRevisions(): Promise<void>;
  pollMs?: number;
  pollTries?: number;
}

export interface ConnectionPanel {
  el: HTMLElement;
  render(): void;
  check(): Promise<void>;
}

/** Null for an upload source: it has no connection settings to change. */
export function connectionPanel(options: PanelOptions): ConnectionPanel | null {
  const { lc, source } = options;
  if (!source.connection || source.connection.connector === "upload") return null;
  const pollMs = options.pollMs ?? 2000;
  const pollTries = options.pollTries ?? 15;
  const headingId = uid("conn");
  const el = h("section", { class: "st-conn cb-card", "aria-labelledby": headingId, "data-connection-panel": "" });
  const status = h("p", { class: "cb-muted cb-small", role: "status", "data-conn-status": "" });
  const error = h("p", { class: "cb-error", role: "alert", "data-conn-error": "" });
  /** Set only by "Rotate secret": shown until the page is left. */
  let secret: string | null = null;
  let rotating = false;
  let checking = false;

  const connection = () => source.connection!;

  async function save(patch: Parameters<LivingCourseClient["connections"]["update"]>[1], done: string): Promise<boolean> {
    error.textContent = "";
    status.textContent = "";
    try {
      await lc.connections.update(connection().id, patch);
      await options.refresh();
      status.textContent = done;
      announce(done);
      render();
      return true;
    } catch (e) {
      error.textContent = message(e, "The change could not be saved.");
      return false;
    }
  }

  async function check(): Promise<void> {
    if (checking) return;
    error.textContent = "";
    checking = true;
    status.textContent = CHECK_QUEUED;
    announce(CHECK_QUEUED);
    const before = connection().lastCheckedAt;
    const beforeLatest = connection().latestRevision?.id ?? null;
    const beforeError = connection().lastError;
    try {
      await lc.connections.check(connection().id);
    } catch (e) {
      checking = false;
      status.textContent = "";
      error.textContent = message(e, "The check could not be started.");
      return;
    }
    let finished = false;
    for (let i = 0; i < pollTries && !finished; i++) {
      await options.refresh();
      const c = connection();
      finished = c.lastCheckedAt !== before || (c.latestRevision?.id ?? null) !== beforeLatest || c.lastError !== beforeError;
      if (!finished && pollMs > 0) await new Promise((resolve) => window.setTimeout(resolve, pollMs));
    }
    checking = false;
    if (finished) {
      await options.reloadRevisions();
      const c = connection();
      const text = c.lastError ? "The check failed. The reason is shown above." : (c.latestRevision?.id ?? null) !== beforeLatest ? "The check found a new revision." : `${CHECK_DONE} Nothing new was found.`;
      status.textContent = text;
      announce(text);
    } else {
      status.textContent = CHECK_PENDING_LONG;
    }
    render();
  }

  function schedule(): HTMLElement {
    const id = uid("conn-sched");
    const select = h("select", { id, class: "cb-select", "data-schedule": "" }, SCHEDULES.map((s) => h("option", { value: s.value }, s.label))) as HTMLSelectElement;
    select.value = connection().schedule ?? "manual";
    select.addEventListener("change", () => {
      const previous = connection().schedule ?? "manual";
      select.disabled = true;
      void save({ schedule: select.value }, "Schedule saved.").then((ok) => {
        if (!ok) select.value = previous;
        select.disabled = false;
      });
    });
    return h("div", { class: "st-conn-field" }, h("label", { for: id, class: "cb-label" }, "Check for changes"), select);
  }

  function tokenBlock(): HTMLElement | null {
    if (connection().connector !== "git") return null;
    const id = uid("conn-token");
    const set = hasToken(connection());
    const input = h("input", { type: "password", id, class: "cb-input", autocomplete: "off", spellcheck: "false", "aria-describedby": `${id}-hint`, "data-token": "" }) as HTMLInputElement;
    const button = h("button", { type: "submit", class: "cb-btn cb-btn-small" }, set ? "Replace token" : "Add token") as HTMLButtonElement;
    const form = h("form", { class: "st-conn-field", "data-token-form": "" },
      h("p", { class: "cb-label st-token-state", "data-token-state": "" }, set ? "Access token: Token set" : "Access token: none"),
      h("label", { for: id, class: "cb-label" }, set ? "New token" : "Token"),
      input,
      h("p", { id: `${id}-hint`, class: "cb-muted cb-small" },
        set ? "The token is never shown again. Write a new one to replace it."
          : "Public repositories work without a token, but GitHub then allows only 60 requests an hour. A token with read access lifts that limit and opens private repositories."),
      h("div", { class: "cb-actions" }, button));
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      const value = input.value.trim();
      if (!value) {
        error.textContent = "Write the token first.";
        return;
      }
      button.disabled = true;
      void save({ secrets: { token: value } }, "Token saved.").then((ok) => {
        if (ok) input.value = "";
        button.disabled = false;
      });
    });
    return form;
  }

  function webhookBlock(): HTMLElement | null {
    const c = connection();
    if (c.connector !== "git" || !c.webhookUrl) return null;
    const url = copyField("Webhook URL", c.webhookUrl, { "data-webhook-url": "" });
    const rotate = h("button", { type: "button", class: "cb-btn cb-btn-small", "aria-expanded": String(rotating), "data-rotate": "" }, "Rotate secret") as HTMLButtonElement;
    rotate.addEventListener("click", () => {
      rotating = true;
      render();
      el.querySelector<HTMLButtonElement>("[data-rotate-confirm]")?.focus();
    });
    const confirm = rotating
      ? h("div", { class: "st-rotate", "data-rotate-box": "" },
          h("p", {}, "The current secret stops working at once. Update the webhook on your host with the new one afterwards."),
          h("div", { class: "cb-actions" },
            h("button", { type: "button", class: "cb-btn cb-btn-small cb-btn-primary", "data-rotate-confirm": "", onclick: () => void doRotate() }, "Rotate and show the new secret"),
            h("button", { type: "button", class: "cb-btn cb-btn-small", onclick: () => { rotating = false; render(); el.querySelector<HTMLButtonElement>("[data-rotate]")?.focus(); } }, "Cancel")))
      : null;
    return h("div", { class: "st-webhook", "data-webhook": "" },
      h("h4", { class: "cb-h3" }, "Webhook"),
      h("p", { class: "cb-muted" }, "Let your host tell us the moment someone pushes, so changes are found without waiting for the next scheduled check."),
      url,
      secret ? secretReveal(secret) : null,
      h("div", { class: "cb-actions" }, rotating ? null : rotate),
      confirm,
      webhookHints(c.config.host));
  }

  async function doRotate(): Promise<void> {
    error.textContent = "";
    try {
      const result = await lc.connections.rotateSecret(connection().id);
      secret = result.webhookSecret;
      rotating = false;
      status.textContent = "A new webhook secret was created. Copy it now.";
      announce("A new webhook secret was created. Copy it now.");
      render();
      el.querySelector<HTMLInputElement>("[data-secret] input")?.focus();
    } catch (e) {
      error.textContent = message(e, "The secret could not be rotated.");
    }
  }

  function render(): void {
    const c = connection();
    const state = connectionState(c);
    const facts = connectionFacts(c);
    const urls = connectionUrls(c);
    const pause = h("button", { type: "button", class: "cb-btn cb-btn-small", "data-pause": "" }, c.status === "paused" ? "Resume checking" : "Pause checking") as HTMLButtonElement;
    pause.addEventListener("click", () => {
      pause.disabled = true;
      void save({ status: c.status === "paused" ? "active" : "paused" }, c.status === "paused" ? "Checking resumed." : "Checking paused.").then(() => (pause.disabled = false));
    });
    const parts: Array<Node | null> = [
      h("h3", { id: headingId, class: "cb-h3" }, "Connection"),
      h("p", { class: "cb-sync-status" }, h("span", { class: `cb-sync-pill st-conn-${state}`, "data-conn-state": state }, stateIcon(state), `Connection ${STATE_TEXT[state].toLowerCase()}`)),
      c.lastError ? h("p", { class: "cb-error", "data-conn-last-error": "" }, `Last error: ${c.lastError}`) : null,
      h("dl", { class: "cb-facts" },
        facts.map((f) => h("div", {}, h("dt", {}, f.label), h("dd", { class: "st-wrap" }, f.value))),
        h("div", {}, h("dt", {}, "Last checked"), h("dd", { "data-last-checked": "" }, when(c.lastCheckedAt, "Never"))),
        h("div", {}, h("dt", {}, "Next check"), h("dd", { "data-next-check": "" }, c.status === "paused" ? "Paused" : when(c.nextCheckAt, c.schedule === "manual" ? "Only when you check" : "Not scheduled")))),
      urls.length > 1 ? h("ul", { class: "st-urls" }, urls.map((u) => h("li", {}, u))) : null,
      schedule(),
      h("div", { class: "cb-actions" }, pause),
      tokenBlock(),
      webhookBlock(),
      status, error,
    ];
    el.replaceChildren(...parts.filter((n): n is Node => n !== null));
  }

  render();
  return { el, render, check };
}
