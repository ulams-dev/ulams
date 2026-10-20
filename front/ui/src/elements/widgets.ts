import { createClient, type Course, type CourseProgressSummary, type UlamsClient } from "@ulams/sdk";
import { bff } from "./bff.ts";

/**
 * Embeddable learner widgets: framework-free custom elements that work on any page, with or
 * without the reference frontend.
 *
 *   <ulams-catalogue limit="6"></ulams-catalogue>
 *   <ulams-my-courses></ulams-my-courses>
 *   <ulams-continue></ulams-continue>
 *
 * Data comes from `@ulams/sdk`. Inside the reference frontend the elements call the page's own
 * `/bff` (the session lives in an httpOnly cookie). On another site set `api-url` to the tenant API
 * and, for the learner widgets, `token` to the learner's API token. Attributes shared by all:
 *
 *   api-url       tenant API origin, for example https://acme.ulams.app
 *   token         learner token (my courses, continue); never put an admin token on a public page
 *   course-href   course page template, default `/courses/{course}`
 *   learn-href    lesson template, default `/learn/{course}/{topic}`
 *
 * The content renders in a shadow root so host-page CSS cannot break it; theme variables
 * (`--ulams-color-*`) pass through. Text is set with textContent only.
 */

const STYLE = `
:host { display: block; color: var(--ulams-color-text, #1b1b1f); font: inherit; }
* { box-sizing: border-box; }
h2, h3, p { margin: 0; }
ul { display: grid; gap: 12px; margin: 0; padding: 0; list-style: none; }
.card { display: grid; gap: 8px; padding: 16px; border: 1px solid var(--ulams-color-border, #d0d0d8); border-radius: var(--ulams-radius-card, 8px); background: var(--ulams-color-surface, #fff); }
.card h3 { font-size: 1.05rem; line-height: 1.3; }
.muted { color: var(--ulams-color-muted, #595965); font-size: 0.9rem; }
.title { font-size: 1.15rem; margin-bottom: 12px; }
a { color: var(--ulams-color-primary-on-light, #1d4ed8); text-underline-offset: 3px; }
a.button { display: inline-flex; align-items: center; justify-self: start; min-height: 44px; padding: 0 16px; border-radius: 8px; background: var(--ulams-color-primary, #1d4ed8); color: var(--ulams-color-on-primary, #fff); font-weight: 700; text-decoration: none; }
a:focus-visible { outline: 3px solid var(--ulams-color-primary, #1d4ed8); outline-offset: 2px; }
.bar { height: 8px; border-radius: 4px; background: linear-gradient(90deg, var(--ulams-color-positive, #1a7f4b) var(--p), var(--ulams-color-border, #d0d0d8) var(--p)); }
`;

const h = (tag: string, attrs: Record<string, string> = {}, ...kids: Array<Node | string>): HTMLElement => {
  const el = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs)) el.setAttribute(k, v);
  for (const kid of kids) el.append(kid);
  return el;
};

/** Fills `{course}` and `{topic}` in an href template; only relative and http(s) templates are accepted. */
export function widgetHref(template: string, ids: { course: number; topic?: number }): string {
  const ok = /^\/(?!\/)/.test(template) || /^https?:\/\//i.test(template);
  const safe = ok ? template : "#";
  return safe.replace("{course}", String(ids.course)).replace("{topic}", String(ids.topic ?? ""));
}

/** Completion and the first open topic of one course, from the progress rows of `/courses/progress`. */
export function summaryStanding(entry: Pick<CourseProgressSummary, "progress">): { percent: number; done: number; total: number; nextTopic?: number } {
  const rows = entry.progress ?? [];
  const done = rows.filter((r) => r.status === 1).length;
  return { percent: rows.length ? Math.round((done / rows.length) * 100) : 0, done, total: rows.length, nextTopic: rows.find((r) => r.status !== 1)?.topic_id };
}

abstract class Widget extends HTMLElement {
  protected root!: ShadowRoot;
  private seq = 0;

  connectedCallback(): void {
    this.root = this.shadowRoot ?? this.attachShadow({ mode: "open" });
    void this.reload();
  }

  static get observedAttributes(): string[] {
    return ["api-url", "token", "limit", "course-href", "learn-href"];
  }

  attributeChangedCallback(): void {
    if (this.isConnected && this.root) void this.reload();
  }

  protected get client(): UlamsClient {
    const baseUrl = this.getAttribute("api-url");
    return baseUrl ? createClient({ baseUrl, token: this.getAttribute("token"), timeoutMs: 15_000 }) : bff;
  }
  protected courseHref = (course: number) => widgetHref(this.getAttribute("course-href") ?? "/courses/{course}", { course });
  protected learnHref = (course: number, topic?: number) =>
    topic ? widgetHref(this.getAttribute("learn-href") ?? "/learn/{course}/{topic}", { course, topic }) : widgetHref(this.getAttribute("course-href") ?? "/courses/{course}", { course });

  protected abstract heading: string;
  protected abstract load(): Promise<Node>;

  /** Re-fetches and renders; a newer call wins over an older one still in flight. */
  async reload(): Promise<void> {
    const mine = ++this.seq;
    this.paint(h("p", { role: "status", class: "muted" }, "Loading…"));
    try {
      const body = await this.load();
      if (mine === this.seq) this.paint(body);
    } catch {
      if (mine !== this.seq) return;
      const retry = h("a", { href: "#", class: "retry" }, "Try again");
      retry.addEventListener("click", (event) => {
        event.preventDefault();
        void this.reload();
      });
      this.paint(h("p", { role: "alert" }, "This could not be loaded right now. ", retry));
    }
  }

  private paint(body: Node): void {
    const section = h("section", { "aria-label": this.heading }, body);
    this.root.replaceChildren(h("style", {}, STYLE), section);
  }

  protected list(items: HTMLElement[], label: string): HTMLElement {
    return h("ul", { role: "list", "aria-label": label }, ...items.map((li) => h("li", {}, li)));
  }

  protected percentBar(percent: number, label: string): HTMLElement {
    return h("div", { class: "bar", role: "progressbar", "aria-label": label, "aria-valuemin": "0", "aria-valuemax": "100", "aria-valuenow": String(percent), style: `--p:${percent}%` });
  }
}

class UlamsCatalogue extends Widget {
  protected heading = "Courses";
  protected async load(): Promise<Node> {
    const limit = Math.min(24, Math.max(1, Number(this.getAttribute("limit")) || 6));
    const { data } = await this.client.courses.list({ per_page: limit });
    if (data.length === 0) return h("p", { class: "muted" }, "No courses are published yet.");
    return this.list(data.map((course: Course) => this.card(course)), "Courses");
  }
  private card(course: Course): HTMLElement {
    const meta = [course.level, course.duration].filter(Boolean).join(" · ");
    return h(
      "article",
      { class: "card" },
      h("h3", {}, h("a", { href: this.courseHref(course.id) }, course.title)),
      ...(course.summary ? [h("p", { class: "muted" }, course.summary)] : []),
      ...(meta ? [h("p", { class: "muted" }, meta)] : [])
    );
  }
}

class UlamsMyCourses extends Widget {
  protected heading = "My courses";
  protected async load(): Promise<Node> {
    const rows = await this.client.progress.all();
    if (rows.length === 0) return h("p", { class: "muted" }, "No courses yet.");
    return this.list(
      rows.map((entry) => {
        const s = summaryStanding(entry);
        const verb = s.percent === 0 ? "Start" : s.percent === 100 ? "Review" : "Continue";
        return h(
          "article",
          { class: "card" },
          h("h3", {}, h("a", { href: this.courseHref(entry.course.id) }, entry.course.title)),
          this.percentBar(s.percent, `Progress in ${entry.course.title}`),
          h("p", { class: "muted" }, `${s.percent}% · ${s.done}/${s.total} topics`),
          h("a", { class: "button", href: this.learnHref(entry.course.id, s.nextTopic), "aria-label": `${verb}: ${entry.course.title}` }, verb)
        );
      }),
      "My courses"
    );
  }
}

class UlamsContinue extends Widget {
  protected heading = "Continue learning";
  protected async load(): Promise<Node> {
    const rows = await this.client.progress.all();
    const withStanding = rows.map((entry) => ({ entry, s: summaryStanding(entry) }));
    const target = withStanding.find((r) => r.s.percent > 0 && r.s.percent < 100) ?? withStanding.find((r) => r.s.percent < 100);
    if (!target) {
      return h("p", { class: "muted" }, rows.length === 0 ? "No courses yet." : "You have finished all your courses.");
    }
    const { entry, s } = target;
    return h(
      "div",
      { class: "card" },
      h("p", { class: "muted" }, s.percent === 0 ? "Start your course" : "Pick up where you left off"),
      h("h3", {}, entry.course.title),
      this.percentBar(s.percent, `Progress in ${entry.course.title}`),
      h("p", { class: "muted" }, `${s.percent}% · ${s.done}/${s.total} topics`),
      h("a", { class: "button", href: this.learnHref(entry.course.id, s.nextTopic) }, s.percent === 0 ? "Start learning" : "Continue learning")
    );
  }
}

for (const [name, ctor] of [
  ["ulams-catalogue", UlamsCatalogue],
  ["ulams-my-courses", UlamsMyCourses],
  ["ulams-continue", UlamsContinue],
] as const) {
  if (!customElements.get(name)) customElements.define(name, ctor);
}
