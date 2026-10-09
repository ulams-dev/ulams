import { bff, whenActive } from "./bff.ts";

/**
 * <ulams-progress course-id topic-id complete="manual|view|media|h5p|quiz" status="0|1|2">
 *   …contains a button[data-complete]…
 * </ulams-progress>
 *
 * Pings the topic while the page is visible (time on task), marks it complete on the
 * button, on `ulams:complete` events from players, or (complete="view") when the reader
 * reaches the end of the content. Updates the program tree and the course progress bar.
 */
class UlamsProgress extends HTMLElement {
  private timer: number | undefined;
  private done = false;

  connectedCallback(): void {
    const courseId = Number(this.getAttribute("course-id"));
    const topicId = Number(this.getAttribute("topic-id"));
    if (!courseId || !topicId) return;
    this.done = this.getAttribute("status") === "1";
    this.render();

    whenActive(() => {
      const ping = () => {
        if (document.visibilityState === "visible") void bff.progress.ping(topicId).catch(() => undefined);
      };
      ping();
      this.timer = window.setInterval(ping, 60_000);
    });

    this.querySelector<HTMLButtonElement>("button[data-complete]")?.addEventListener("click", () => this.complete(courseId, topicId));
    document.addEventListener("ulams:complete", this.onComplete);

    if (this.getAttribute("complete") === "view" && !this.done) {
      const sentinel = document.querySelector("[data-end-of-content]");
      const since = Date.now();
      if (sentinel) {
        const io = new IntersectionObserver((entries) => {
          if (entries.some((e) => e.isIntersecting) && Date.now() - since > 4000) {
            io.disconnect();
            void this.complete(courseId, topicId);
          }
        });
        whenActive(() => window.setTimeout(() => io.observe(sentinel), 4000));
      }
    }
  }

  disconnectedCallback(): void {
    window.clearInterval(this.timer);
    document.removeEventListener("ulams:complete", this.onComplete);
  }

  private onComplete = () => {
    void this.complete(Number(this.getAttribute("course-id")), Number(this.getAttribute("topic-id")));
  };

  private async complete(courseId: number, topicId: number): Promise<void> {
    if (this.done) return;
    this.done = true;
    this.render();
    try {
      const progress = await bff.progress.complete(courseId, topicId);
      const all = Array.isArray(progress) ? progress : [];
      const finished = all.filter((p) => p.status === 1).length;
      if (all.length) this.updateBar(Math.round((finished / all.length) * 100));
      document.querySelectorAll(`[data-topic-id="${topicId}"]`).forEach((el) => el.setAttribute("data-status", "done"));
      this.announce("Topic marked as complete.");
    } catch {
      this.done = false;
      this.render();
      this.announce("Could not save your progress. Try again.");
    }
  }

  private updateBar(percent: number): void {
    document.querySelectorAll<HTMLElement>("[data-course-progress]").forEach((el) => {
      el.style.setProperty("--progress", `${percent}%`);
      const label = el.querySelector("[data-progress-label]");
      if (label) label.textContent = `${percent}%`;
      el.setAttribute("aria-valuenow", String(percent));
    });
  }

  private render(): void {
    const button = this.querySelector<HTMLButtonElement>("button[data-complete]");
    if (!button) return;
    button.setAttribute("aria-pressed", String(this.done));
    const label = button.querySelector("[data-label]");
    if (label) label.textContent = this.done ? (button.dataset.doneLabel ?? "Completed") : (button.dataset.todoLabel ?? "Mark as complete");
  }

  private announce(text: string): void {
    const live = this.querySelector("[data-live]");
    if (live) live.textContent = text;
  }
}

if (!customElements.get("ulams-progress")) customElements.define("ulams-progress", UlamsProgress);
