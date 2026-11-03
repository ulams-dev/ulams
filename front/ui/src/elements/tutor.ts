import { createTutorClient, type TutorClient } from "@ulams/sdk";
import { tutorStrings } from "../lib/tutor-strings.ts";

/**
 * <ulams-tutor course-id="5" topic-id="12"></ulams-tutor>
 *
 * The course tutor: a launcher button that opens a panel where the learner asks questions and gets
 * answers from the course only, each with citations. Inside the reference frontend it calls the
 * page's own `/bff` (the session lives in an httpOnly cookie). On another site set `api-url` to
 * the tenant API and `token` to the learner's API token.
 *
 *   course-id     the course the tutor answers from (required)
 *   topic-id      the lesson the learner has open; its content is added to the search
 *   api-url       tenant API origin, for example https://acme.ulams.app
 *   token         learner token; never put an admin token on a public page
 *   learn-href    lesson template for the citations, default `/learn/{course}/{topic}`
 *   lang          interface language (en, pl, zh); default the page language
 *
 * The launcher is small and always in the same place on every lesson. It shows only when the tutor
 * is on for the tenant and the learner may attend the course. The panel script (markdown renderer,
 * conversation list) loads the first time the launcher is used.
 */
const STYLE = `
:host([hidden]) { display: none; }
:host { display: inline-block; font: inherit; color: var(--ulams-color-text, #1b1b1f); }
* { box-sizing: border-box; }
button.launcher { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 0 16px; border: 1px solid var(--ulams-color-border, #d0d0d8); border-radius: 999px; background: var(--ulams-color-surface, #fff); color: inherit; font: inherit; font-weight: 600; cursor: pointer; }
button.launcher:hover { border-color: var(--ulams-color-primary, #1d4ed8); }
button.launcher:focus-visible { outline: 3px solid var(--ulams-color-primary, #1d4ed8); outline-offset: 2px; }
button.launcher[aria-busy="true"] { cursor: progress; }
@media (max-width: 520px) {
  button.launcher { padding: 0; width: 44px; justify-content: center; }
  .label { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }
}
svg { width: 18px; height: 18px; flex: none; }
`;

const ICON = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z"/></svg>`;

export interface TutorContext {
  launcher: HTMLButtonElement;
  client: TutorClient;
  courseId: number;
  topicId: number | null;
  learnHref: string;
  lang: string;
}

class UlamsTutor extends HTMLElement {
  private root!: ShadowRoot;
  private panel: { open(): void; destroy(): void } | null = null;

  connectedCallback(): void {
    this.root = this.shadowRoot ?? this.attachShadow({ mode: "open" });
    this.hidden = true;
    void this.start();
  }

  disconnectedCallback(): void {
    this.panel?.destroy();
    this.panel = null;
  }

  private get language(): string {
    return this.getAttribute("lang") ?? document.documentElement.lang ?? "en";
  }

  private get client(): TutorClient {
    const baseUrl = this.getAttribute("api-url");
    return createTutorClient(baseUrl ? { baseUrl, token: this.getAttribute("token"), timeoutMs: 20_000 } : { baseUrl: "/bff", timeoutMs: 20_000 });
  }

  private async start(): Promise<void> {
    const courseId = Number(this.getAttribute("course-id"));
    if (!Number.isInteger(courseId) || courseId < 1) return;
    const client = this.client;
    try {
      const status = await client.status(courseId);
      if (!status.enabled || !status.aiEnabled) return;
    } catch {
      return; // off, not allowed or unreachable: no tutor on this page, and nothing broken
    }
    const text = tutorStrings(this.language).launcher;
    const launcher = document.createElement("button");
    launcher.type = "button";
    launcher.className = "launcher";
    launcher.setAttribute("aria-haspopup", "dialog");
    launcher.innerHTML = ICON;
    const label = document.createElement("span");
    label.className = "label";
    label.textContent = text;
    launcher.append(label);
    launcher.addEventListener("click", () => void this.openPanel(launcher, client, courseId));
    const style = document.createElement("style");
    style.textContent = STYLE;
    this.root.replaceChildren(style, launcher);
    this.hidden = false;
  }

  private async openPanel(launcher: HTMLButtonElement, client: TutorClient, courseId: number): Promise<void> {
    if (!this.panel) {
      launcher.setAttribute("aria-busy", "true");
      try {
        const { TutorPanel } = await import("./tutor-panel.ts");
        const topic = Number(this.getAttribute("topic-id"));
        this.panel = new TutorPanel({
          launcher,
          client,
          courseId,
          topicId: Number.isInteger(topic) && topic > 0 ? topic : null,
          learnHref: this.getAttribute("learn-href") ?? "/learn/{course}/{topic}",
          lang: this.language,
        });
      } finally {
        launcher.removeAttribute("aria-busy");
      }
    }
    this.panel.open();
  }
}

if (!customElements.get("ulams-tutor")) customElements.define("ulams-tutor", UlamsTutor);
