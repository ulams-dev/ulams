/**
 * Builder thread (/studio/new and /studio/s/:id): upload drop zone, the conversation with A2UI
 * surfaces (interview, outline review, generation progress, apply) and the brief/source panel.
 */
import { ApiError, isApplied, type BuilderState } from "@ulams/sdk";
import { h, usd } from "@ulams/ui/builder/dom.ts";
import { isEditable, renderBriefEditor } from "@ulams/ui/builder/brief-editor.ts";
import type { A2uiActionOut } from "@ulams/ui/builder/components.ts";
import { announce, connectionStatus, settleApplied, showCitation, studioClient, updateTopBar } from "./common.ts";
import { Timeline } from "./timeline.ts";

const MAX_BYTES = 20 * 1024 * 1024;

export function mountThread(root: HTMLElement): void {
  const cb = studioClient();
  let sessionId = root.dataset.session || null;
  const thread = root.querySelector<HTMLElement>("[data-thread]")!;
  const drop = root.querySelector<HTMLElement>("[data-drop]")!;
  const fileInput = root.querySelector<HTMLInputElement>("[data-file]")!;
  const dropError = root.querySelector<HTMLElement>("[data-drop-error]")!;
  const composer = root.querySelector<HTMLFormElement>("[data-composer]")!;
  const textarea = composer.querySelector("textarea")!;
  const panel = root.querySelector<HTMLElement>("[data-panel]")!;
  const nextLinks = root.querySelector<HTMLElement>("[data-next]")!;
  let abort: AbortController | null = null;

  const dispatch = async (action: A2uiActionOut) => {
    if (!sessionId) return;
    try {
      const result = await cb.runs.action(sessionId, action);
      if (!result.accepted && result.message) announce(result.message);
    } catch (error) {
      announce(error instanceof ApiError ? error.message : "The action could not be sent.");
    }
  };

  const timeline = new Timeline({
    container: thread,
    dispatch,
    onCitation: (id, label, trigger) => void showCitation(id, label, trigger),
    onState: (state) => render(state),
    onCustom: (name, value) => {
      if (name !== "applied") return;
      renderNext(timeline.state);
      const versionId = typeof value.versionId === "string" ? value.versionId : undefined;
      void settleApplied(cb, sessionId ?? "", versionId, () => timeline.state).then((state) => {
        if (state) timeline.adopt(state);
      });
    },
  });

  function editButton(key: string, label: string): HTMLElement {
    const button = h("button", { type: "button", class: "cb-btn cb-btn-ghost st-brief-edit-btn", "aria-label": `Edit ${label.toLowerCase()}` }, "Edit");
    button.addEventListener("click", () => {
      panel.dataset.editing = key;
      render(timeline.state);
      panel.querySelector<HTMLElement>(".st-brief-edit input, .st-brief-edit select, .st-brief-edit button")?.focus();
    });
    return button;
  }

  function render(state: BuilderState): void {
    updateTopBar(state);
    const hasReady = state.sources?.some((s) => s.status === "ready" || s.status === "processing" || s.status === "uploaded");
    drop.hidden = Boolean(hasReady);
    if (!state.aiEnabled) {
      dropError.textContent = "AI features are disabled on this installation. Ask your admin to configure them.";
    }
    const brief = state.briefRows ?? [];
    const decided = (state.brief?.decidedBy ?? {}) as Record<string, string>;
    const editing = panel.dataset.editing ?? "";
    const stopEditing = () => {
      delete panel.dataset.editing;
      render(timeline.state);
    };
    const save = async (patch: Record<string, unknown>) => {
      if (!sessionId) return;
      try {
        const result = await cb.brief.update(sessionId, patch as Parameters<typeof cb.brief.update>[1]);
        announce(result.stale ? "Brief saved. The outline and lessons still follow the old brief." : "Brief saved.");
        delete panel.dataset.editing;
        const fresh = await cb.sessions.get(sessionId);
        timeline.adopt(fresh);
      } catch (error) {
        announce(error instanceof ApiError ? error.message.replace(/^API \d+: /, "") : "The brief could not be saved.");
      }
    };
    const editor = (key: string) =>
      renderBriefEditor(key as Parameters<typeof renderBriefEditor>[0], (state.brief ?? {}) as Record<string, unknown>, (patch) => void save(patch), stopEditing);
    panel.replaceChildren(
      h("section", { class: "cb-card st-brief", "aria-labelledby": "st-brief-title" },
        h("h2", { id: "st-brief-title", class: "cb-h3" }, "Course brief"),
        brief.length
          ? h("dl", {}, brief.flatMap((row) => [
              h("dt", {}, row.label),
              editing === row.key
                ? h("dd", { class: "st-brief-edit" }, editor(row.key))
                : h("dd", {}, row.value, decided[row.key === "duration" ? "totalMinutes" : row.key] === "default" ? h("span", { class: "cb-tag" }, "decided for you") : null,
                    isEditable(row.key) ? editButton(row.key, row.label) : null),
            ]))
          : h("p", { class: "cb-muted" }, "The interview fills this in. You can change the brief at any time."),
        state.budgetReached ? h("p", { class: "cb-error", role: "alert" }, "Budget reached: ask an admin to raise the AI limit.") : null),
      h("section", { class: "cb-card st-sources", "aria-labelledby": "st-src-title" },
        h("h2", { id: "st-src-title", class: "cb-h3" }, "Sources"),
        state.sources?.length
          ? h("ul", {}, state.sources.map((s) => h("li", {}, h("span", { class: "cb-mono" }, s.name), " ", h("span", { class: "cb-muted" }, `${s.status}${s.fragments ? ` · ${s.fragments} fragments` : ""}`))))
          : h("p", { class: "cb-muted" }, "We split your source into sections with stable ids, so every generated element can point back to the passage it came from.")),
      h("section", { class: "cb-card st-cost-card", "aria-labelledby": "st-cost-title" },
        h("h2", { id: "st-cost-title", class: "cb-h3" }, "AI cost"),
        h("p", { class: "cb-mono" }, `${usd(state.cost?.usedMicroUsd)} of ${usd(state.cost?.budgetMicroUsd)}`),
        h("progress", { max: state.cost?.budgetMicroUsd ?? 1, value: Math.min(state.cost?.usedMicroUsd ?? 0, state.cost?.budgetMicroUsd ?? 1), "aria-label": "AI budget used" }),
        state.profiles ? h("p", { class: "cb-muted cb-small" }, `${state.profiles.default} for course content, ${state.profiles.light} for light steps. Costs are tracked per course.`) : null),
      h("section", { class: "st-guarantee" },
        h("h2", { class: "cb-h3" }, "Your source is data, not instructions"),
        h("p", { class: "cb-small cb-muted" }, "Uploaded content never changes what the assistant does. Nothing reaches your academy until you approve it."))
    );
    renderNext(state);
  }

  function renderNext(state: BuilderState): void {
    if (!sessionId) return;
    const status = state.session?.status;
    const links: HTMLElement[] = [];
    if (status && ["apply_review", "applying", "applied"].includes(status)) {
      links.push(h("a", { class: "cb-btn", href: `/studio/s/${sessionId}/workspace` }, "Open the workspace"));
    }
    if (isApplied(state.session)) links.push(h("a", { class: "cb-btn cb-btn-primary", href: `/studio/s/${sessionId}/done` }, "See your course"));
    nextLinks.replaceChildren(...links);
  }

  function connect(): void {
    if (!sessionId) return;
    abort?.abort();
    abort = new AbortController();
    void cb.events(sessionId, { onEvent: (event) => timeline.handle(event), onStatus: (status, detail) => connectionStatus(status, detail) }, { signal: abort.signal });
  }

  async function upload(file: File): Promise<void> {
    dropError.textContent = "";
    if (file.size > MAX_BYTES) {
      dropError.textContent = "The file is larger than 20 MB.";
      return;
    }
    if (!/\.(md|markdown|txt|pdf|docx)$/i.test(file.name)) {
      dropError.textContent = "Upload a Markdown, PDF or DOCX file.";
      return;
    }
    drop.setAttribute("aria-busy", "true");
    try {
      if (!sessionId) {
        const state = await cb.sessions.create();
        sessionId = state.session.id;
        root.dataset.session = sessionId;
        // the server's own greeting replaces the placeholder
        thread.querySelector("[data-placeholder]")?.remove();
        history.replaceState(null, "", `/studio/s/${sessionId}`);
        connect();
      }
      await cb.sources.upload(sessionId, file, file.name);
      announce(`Uploaded ${file.name}. Reading it now.`);
    } catch (error) {
      dropError.textContent = error instanceof ApiError ? error.message.replace(/^API \d+: /, "") : "The upload failed. Try again.";
    } finally {
      drop.removeAttribute("aria-busy");
    }
  }

  fileInput.addEventListener("change", () => {
    const file = fileInput.files?.[0];
    if (file) void upload(file);
    fileInput.value = "";
  });
  drop.addEventListener("dragover", (event) => {
    event.preventDefault();
    drop.classList.add("st-drop-over");
  });
  drop.addEventListener("dragleave", () => drop.classList.remove("st-drop-over"));
  drop.addEventListener("drop", (event) => {
    event.preventDefault();
    drop.classList.remove("st-drop-over");
    const file = event.dataTransfer?.files?.[0];
    if (file) void upload(file);
  });

  composer.addEventListener("submit", async (event) => {
    event.preventDefault();
    const text = textarea.value.trim();
    if (!text || !sessionId) return;
    textarea.value = "";
    try {
      await cb.runs.message(sessionId, text);
    } catch (error) {
      announce(error instanceof ApiError ? error.message : "The message could not be sent.");
      textarea.value = text;
    }
  });
  textarea.addEventListener("keydown", (event) => {
    if (event.key === "Enter" && !event.shiftKey) {
      event.preventDefault();
      composer.requestSubmit();
    }
  });

  connect();
}

const root = document.querySelector<HTMLElement>("[data-studio-thread]");
if (root) mountThread(root);
