/**
 * Interactive preview (/studio/s/:id/preview): the course as the learner sees it, in a same-origin
 * frame, next to the element chat. Every element of the frame carries its blueprint id; selecting one
 * (click, or Tab + Enter on its "Discuss" button) scopes the chat to it, shows the source passages it
 * cites, and a proposed change appears as a diff in the panel. Approving applies the new version and
 * the element is re-rendered in place. Same API and timeline as the workspace; nothing is tracked.
 */
import { ApiError, type BlueprintVersion, type BuilderState } from "@ulams/sdk";
import type { A2uiActionOut } from "@ulams/ui/builder/components.ts";
import { h } from "@ulams/ui/builder/dom.ts";
import { elementCitations, findElement, type FoundElement } from "../lib/blueprint-preview.ts";
import { announce, connectionStatus, showCitation, studioClient, updateTopBar } from "./common.ts";
import { elementKey, markedElements, planFramePatch } from "./frame-patch.ts";
import { Timeline } from "./timeline.ts";

interface Selection {
  id: string;
  part: string | null;
  label: string;
  found: FoundElement;
}

const HIGHLIGHT_MS = 2600;
const MAX_CHIPS = 12;

export function mountPreview(root: HTMLElement): void {
  const cb = studioClient();
  const sessionId = root.dataset.session!;
  const frame = root.querySelector<HTMLIFrameElement>("[data-frame]")!;
  const chat = root.querySelector<HTMLElement>("[data-chat]")!;
  const scope = root.querySelector<HTMLElement>("[data-scope]")!;
  const sources = root.querySelector<HTMLElement>("[data-sources]")!;
  const composer = root.querySelector<HTMLFormElement>("[data-composer]")!;
  const textarea = composer.querySelector("textarea")!;
  const undo = root.querySelector<HTMLButtonElement>("[data-undo]")!;
  const redo = root.querySelector<HTMLButtonElement>("[data-redo]")!;
  const toggle = root.querySelector<HTMLButtonElement>("[data-toggle-panel]")!;
  const panel = root.querySelector<HTMLElement>("[data-panel]")!;
  const toPreview = root.querySelector<HTMLButtonElement>("[data-to-preview]")!;
  const versionLabel = root.querySelector<HTMLElement>("[data-version]")!;
  const frameSrc = frame.dataset.src ?? frame.src;

  let version: BlueprintVersion | null = null;
  let loadedVersionId: string | null = null;
  let selection: Selection | null = null;

  const dispatch = async (action: A2uiActionOut) => {
    try {
      const result = await cb.runs.action(sessionId, action);
      if (!result.accepted && result.message) announce(result.message);
    } catch (error) {
      announce(error instanceof ApiError ? error.message : "The action could not be sent.");
    }
  };

  const timeline = new Timeline({
    container: chat,
    dispatch,
    kinds: ["patch", "apply"],
    startAfterKind: "apply",
    onCitation: (id, label, trigger) => void showCitation(id, label, trigger),
    onState: (state) => void onState(state),
    onCustom: (name) => {
      if (name === "applied") void loadVersion(timeline.state.session.currentVersionId, true);
    },
  });

  // ---- panel -----------------------------------------------------------------------------------

  const setPanel = (open: boolean) => {
    panel.hidden = !open;
    root.dataset.panel = open ? "open" : "closed";
    toggle.setAttribute("aria-expanded", String(open));
    toggle.textContent = open ? "Hide chat" : "Discuss with the assistant";
  };
  toggle.addEventListener("click", () => setPanel(panel.hidden));

  async function onState(state: BuilderState): Promise<void> {
    updateTopBar(state);
    undo.disabled = !state.canUndo;
    redo.disabled = !state.canRedo;
    if (state.session?.currentVersionId && state.session.currentVersionId !== loadedVersionId) await loadVersion(state.session.currentVersionId);
  }

  function renderScope(): void {
    if (!selection) {
      scope.replaceChildren(h("span", { class: "cb-muted" }, "Select an element in the preview: hover it and press Discuss, or focus Discuss with Tab and press Enter."));
      sources.replaceChildren();
      return;
    }
    const clear = h("button", { type: "button", class: "cb-link", "aria-label": "Clear the selection" }, "×");
    clear.addEventListener("click", () => select(null));
    scope.replaceChildren(h("span", { class: "st-scope-chip" }, `Discussing: ${selection.label}`), clear);
    renderSources();
  }

  function renderSources(): void {
    if (!selection || !version) return;
    const ids = elementCitations(selection.found.node);
    const shown = ids.slice(0, MAX_CHIPS);
    sources.replaceChildren(
      h("h3", { class: "cb-eyebrow" }, "Cited sources"),
      shown.length
        ? h("ul", { class: "st-pv-chips", role: "list" },
            shown.map((id) => {
              const label = (version!.fragments[id] ?? id).slice(0, 80);
              const chip = h("button", { type: "button", class: "cb-cite", "aria-haspopup": "dialog" }, label);
              chip.addEventListener("click", () => void showCitation(id, label, chip));
              return h("li", {}, chip);
            }),
            ids.length > shown.length ? h("li", { class: "cb-muted cb-small" }, `+${ids.length - shown.length} more in this element`) : null
          )
        : h("p", { class: "cb-muted cb-small" }, "This element cites no source passage.")
    );
  }

  // ---- selection ---------------------------------------------------------------------------------

  const frameDoc = (): Document | null => {
    try {
      return frame.contentDocument;
    } catch {
      return null;
    }
  };

  const wrapperFor = (sel: { id: string; part: string | null }, doc: Document): HTMLElement | null =>
    [...doc.querySelectorAll<HTMLElement>("[data-blueprint-id]")].find((el) => el.dataset.blueprintId === sel.id && (el.dataset.blueprintPart ?? null) === sel.part) ?? null;

  function markSelected(): void {
    const doc = frameDoc();
    if (!doc) return;
    doc.querySelectorAll<HTMLElement>("[data-selected]").forEach((el) => {
      el.removeAttribute("data-selected");
      el.querySelector(":scope > [data-discuss]")?.setAttribute("aria-pressed", "false");
    });
    const el = selection ? wrapperFor(selection, doc) : null;
    el?.setAttribute("data-selected", "true");
    el?.querySelector(":scope > [data-discuss]")?.setAttribute("aria-pressed", "true");
  }

  function select(next: { id: string; part: string | null; label: string } | null, options: { focus?: boolean } = {}): void {
    if (!next) {
      selection = null;
      markSelected();
      renderScope();
      return;
    }
    const found = version ? findElement(version.document, next.id) : null;
    if (!found) {
      announce("That element is not part of the current version.");
      return;
    }
    if (!found.editable) {
      announce(`${next.label} cannot be changed in chat.`);
      return;
    }
    selection = { ...next, found };
    markSelected();
    renderScope();
    setPanel(true);
    if (options.focus !== false) {
      textarea.focus();
      announce(`Discussing ${next.label}. Type a change request in the chat panel.`);
    }
  }

  function attach(): void {
    const doc = frameDoc();
    if (!doc) return;
    doc.addEventListener("click", (event) => {
      const target = event.target as Element | null;
      if (!target) return;
      const discuss = target.closest<HTMLElement>("[data-discuss]");
      const wrapper = (discuss ?? target).closest<HTMLElement>("[data-blueprint-id]");
      if (!wrapper) return;
      // links, form controls and other buttons keep their own behaviour
      if (!discuss && target.closest("a, button, input, select, textarea, summary, label")) return;
      event.preventDefault();
      select({ id: wrapper.dataset.blueprintId!, part: wrapper.dataset.blueprintPart ?? null, label: wrapper.dataset.label ?? "Element" });
    });
    markSelected();
  }
  frame.addEventListener("load", attach);
  if (frame.contentDocument?.readyState === "complete") attach();

  // ---- versions: re-render in place ------------------------------------------------------------------

  async function loadVersion(id: string | null, force = false): Promise<void> {
    if (!id || (!force && id === loadedVersionId)) return;
    const first = loadedVersionId === null;
    loadedVersionId = id;
    try {
      version = await cb.versions.get(id);
    } catch {
      announce("The course could not be loaded.");
      return;
    }
    versionLabel.textContent = `Version ${version.number}`;
    if (selection && !findElement(version.document, selection.id)) select(null);
    else if (selection) {
      selection = { ...selection, found: findElement(version.document, selection.id)! };
      renderSources();
    }
    if (!first) await refreshFrame();
  }

  async function refreshFrame(): Promise<void> {
    const doc = frameDoc();
    if (!doc) return;
    let html: string;
    try {
      const response = await fetch(frameSrc, { headers: { Accept: "text/html" }, credentials: "same-origin" });
      if (!response.ok) throw new Error(String(response.status));
      html = await response.text();
    } catch {
      frame.contentWindow?.location.reload();
      return;
    }
    const next = new DOMParser().parseFromString(html, "text/html");
    const current = markedElements(doc);
    const upcoming = markedElements(next);
    const plan = planFramePatch(current, upcoming);
    if (plan.structural) {
      frame.contentWindow?.location.reload();
      return;
    }
    const live = [...doc.querySelectorAll<HTMLElement>("[data-blueprint-id]")];
    const fresh = [...next.querySelectorAll<HTMLElement>("[data-blueprint-id]")];
    const keys = new Map<string, number>();
    const labels: string[] = [];
    live.forEach((el, i) => {
      const base = `${el.dataset.blueprintId}|${el.dataset.blueprintPart ?? ""}`;
      const n = keys.get(base) ?? 0;
      keys.set(base, n + 1);
      const key = elementKey(el.dataset.blueprintId!, el.dataset.blueprintPart, n);
      if (!plan.changed.includes(key)) return;
      el.innerHTML = fresh[i]!.innerHTML;
      el.dataset.label = fresh[i]!.dataset.label ?? el.dataset.label ?? "";
      el.classList.add("is-changed");
      labels.push(el.dataset.label ?? "");
      doc.defaultView?.setTimeout(() => el.classList.remove("is-changed"), HIGHLIGHT_MS);
    });
    // titles and the program tree sit outside the marked elements
    const tree = doc.querySelector(".u-player__tree nav");
    const treeNext = next.querySelector(".u-player__tree nav");
    if (tree && treeNext && tree.innerHTML !== treeNext.innerHTML) tree.innerHTML = treeNext.innerHTML;
    doc.title = next.title || doc.title;
    markSelected();
    if (labels.length) announce(`Preview updated: ${[...new Set(labels)].join(", ")}.`);
  }

  // ---- undo, redo, composer ------------------------------------------------------------------------

  const move = async (direction: "undo" | "redo") => {
    try {
      await cb.versions[direction](sessionId);
      announce(direction === "undo" ? "Undone." : "Redone.");
    } catch (error) {
      announce(error instanceof ApiError ? error.message.replace(/^API \d+: /, "") : "Not possible.");
    }
  };
  undo.addEventListener("click", () => void move("undo"));
  redo.addEventListener("click", () => void move("redo"));

  composer.addEventListener("submit", async (event) => {
    event.preventDefault();
    const text = textarea.value.trim();
    if (!text) return;
    if (!selection) {
      announce("Select an element in the preview first.");
      return;
    }
    textarea.value = "";
    try {
      await cb.runs.message(sessionId, text, selection.id);
    } catch (error) {
      textarea.value = text;
      announce(error instanceof ApiError ? error.message : "The message could not be sent.");
    }
  });
  textarea.addEventListener("keydown", (event) => {
    if (event.key === "Enter" && !event.shiftKey) {
      event.preventDefault();
      composer.requestSubmit();
    }
  });

  /** Focus goes back to the Discuss button of the selected element (or the frame). */
  const backToPreview = () => {
    const doc = frameDoc();
    const wrapper = doc && selection ? wrapperFor(selection, doc) : null;
    const target = wrapper?.querySelector<HTMLElement>(":scope > [data-discuss]");
    if (target) target.focus();
    else frame.focus();
  };
  toPreview.addEventListener("click", backToPreview);
  panel.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !(event.target as Element).closest("dialog")) {
      event.preventDefault();
      backToPreview();
    }
  });

  setPanel(true);
  renderScope();
  void cb.events(sessionId, { onEvent: (event) => timeline.handle(event), onStatus: connectionStatus });
}

const root = document.querySelector<HTMLElement>("[data-studio-preview]");
if (root) mountPreview(root);
