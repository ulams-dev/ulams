/**
 * Workspace (/studio/s/:id/workspace): course tree, live preview of the selected element,
 * element-scoped chat with diff approve/reject, undo/redo and version history.
 */
import { ApiError, isApplied, type Blueprint, type BlueprintQuestion, type BuilderState, type BlueprintVersion, type StaleElement, type StalenessSummary } from "@ulams/sdk";
import { renderSurface } from "@ulams/ui/builder/renderer.ts";
import type { A2uiActionOut } from "@ulams/ui/builder/components.ts";
import { h } from "@ulams/ui/builder/dom.ts";
import { announce, connectionStatus, livingClient, settleApplied, showCitation, studioClient, updateTopBar } from "./common.ts";
import { bannerModel, lessonMarker, markerFor, reviewHref, staleMap, staleNote, type Marker } from "./staleness.ts";
import { Timeline } from "./timeline.ts";

type Selection = { id: string; label: string; type: "lesson" | "question" | "block" | "module" | "course" };

export function mountWorkspace(root: HTMLElement): void {
  const cb = studioClient();
  const lc = livingClient();
  const sessionId = root.dataset.session!;
  const tree = root.querySelector<HTMLElement>("[data-tree]")!;
  const preview = root.querySelector<HTMLElement>("[data-preview]")!;
  const chat = root.querySelector<HTMLElement>("[data-chat]")!;
  const scope = root.querySelector<HTMLElement>("[data-scope]")!;
  const composer = root.querySelector<HTMLFormElement>("[data-composer]")!;
  const textarea = composer.querySelector("textarea")!;
  const undo = root.querySelector<HTMLButtonElement>("[data-undo]")!;
  const redo = root.querySelector<HTMLButtonElement>("[data-redo]")!;
  const history = root.querySelector<HTMLElement>("[data-history]")!;
  const applyBar = root.querySelector<HTMLElement>("[data-apply]")!;
  const banner = root.querySelector<HTMLElement>("[data-stale-banner]");
  let stale = new Map<string, StaleElement>();
  let freshness: StalenessSummary | null = null;
  let version: BlueprintVersion | null = null;
  let selection: Selection | null = null;
  let loadedVersionId: string | null = null;

  const cite = (ids: string[]) => ids.map((fragmentId) => ({ fragmentId, label: (version?.fragments[fragmentId] ?? fragmentId).slice(0, 120) }));
  const onCitation = (id: string, label: string, trigger: HTMLElement) => void showCitation(id, label, trigger);

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
    onCitation,
    onState: (state) => void onState(state),
    onCustom: (name, value) => {
      if (name === "update_proposal" || name === "update_applied" || name === "update_analysis") void loadStaleness();
      if (name !== "applied") return;
      // the event carries the applied version; the stream's state may not have caught up yet
      const versionId = typeof value.versionId === "string" ? value.versionId : undefined;
      void loadVersion(versionId ?? timeline.state.session?.currentVersionId ?? null, true);
      void settleApplied(cb, sessionId, versionId, () => timeline.state).then((state) => {
        if (state) timeline.adopt(state);
      });
    },
  });

  async function onState(state: BuilderState): Promise<void> {
    updateTopBar(state);
    undo.disabled = !state.canUndo;
    redo.disabled = !state.canRedo;
    const status = state.session?.status;
    applyBar.replaceChildren(
      ...(status === "apply_review" ? [h("a", { class: "cb-btn", href: `/studio/s/${sessionId}` }, "Review the apply in the builder")] : []),
      // "applied" only once the applied version is the current one; until then the re-apply is running
      ...(isApplied(state.session) ? [h("a", { class: "cb-btn cb-btn-primary", href: `/studio/s/${sessionId}/done` }, "Course overview")] : []),
      ...(status === "applying" || (status === "applied" && !isApplied(state.session)) ? [h("p", { class: "cb-muted", role: "status" }, "Applying your change to the course…")] : [])
    );
    if (state.session?.currentVersionId && state.session.currentVersionId !== loadedVersionId) await loadVersion(state.session.currentVersionId);
  }

  const markerEl = (marker: Marker & { count?: number }): HTMLElement =>
    h("span", { class: `st-marker st-marker-${marker.kind}`, "data-marker": marker.kind },
      h("span", { class: "st-marker-glyph", "aria-hidden": "true" }, marker.kind === "answer" ? "?" : marker.kind === "removed" ? "×" : "↻"),
      marker.text, marker.count && marker.count > 1 ? ` (${marker.count})` : "");

  function renderBanner(): void {
    if (!banner) return;
    const model = bannerModel(freshness);
    if (!model) {
      banner.hidden = true;
      banner.replaceChildren();
      return;
    }
    banner.hidden = false;
    banner.className = `st-banner st-banner-${model.state} cb-card`;
    banner.replaceChildren(
      h("p", { class: "st-banner-title" }, h("span", { class: "st-marker-glyph", "aria-hidden": "true" }, model.state === "stale" ? "↻" : "–"), model.title),
      ...(model.detail ? [h("p", { class: "cb-muted cb-small" }, model.detail)] : []),
      h("a", { class: "cb-btn cb-btn-small", href: reviewHref(sessionId, freshness) }, freshness?.openProposalId ? "Review the update" : "Open updates")
    );
  }

  async function loadStaleness(): Promise<void> {
    try {
      const result = await lc.staleness.get(sessionId);
      stale = staleMap(result.elements);
      freshness = result.summary;
    } catch {
      return; // the course still works without the markers
    }
    renderBanner();
    if (version) {
      renderTree(version.document);
      renderPreview();
    }
  }

  async function loadVersion(id: string | null, force = false): Promise<void> {
    if (!id || (!force && id === loadedVersionId)) return;
    loadedVersionId = id;
    try {
      version = await cb.versions.get(id);
    } catch {
      preview.replaceChildren(h("p", { class: "cb-error" }, "The course could not be loaded."));
      return;
    }
    renderTree(version.document);
    void loadStaleness();
    if (selection && !findLabel(version.document, selection.id)) selection = null;
    renderPreview();
    void renderHistory();
  }

  function findLabel(doc: Blueprint, id: string): boolean {
    return JSON.stringify(doc).includes(`"id":"${id}"`);
  }

  function questionMarker(id: string): HTMLElement | null {
    const m = markerFor(stale.get(id));
    return m ? h("span", { class: "st-tree-marker" }, markerEl(m)) : null;
  }

  /** Highlights stale blocks and questions in the preview with "based on §3.2, changed N days ago". */
  function decoratePreview(): void {
    const labels = version?.fragments ?? {};
    const mark = (host: Element, element: StaleElement) => {
      const marker = markerFor(element);
      if (!marker) return;
      host.classList.add("st-stale", `st-stale-${marker.kind}`);
      host.prepend(h("p", { class: "st-stale-note", "data-stale-note": element.elementId }, markerEl(marker), " ", staleNote(element, labels)));
    };
    for (const block of preview.querySelectorAll("[data-block]")) {
      const element = stale.get(block.getAttribute("data-block") ?? "");
      if (element) mark(block, element);
    }
    const card = preview.querySelector<HTMLElement>(".cb-question-card");
    if (card && selection?.type === "question") {
      const element = stale.get(selection.id);
      if (element) mark(card, element);
    }
  }

  function renderTree(doc: Blueprint): void {
    const item = (sel: Selection, text: string, extra?: HTMLElement | null) => {
      const button = h("button", { type: "button", class: "st-tree-item", "aria-current": selection?.id === sel.id ? "true" : null, "data-element": sel.id }, text);
      button.addEventListener("click", () => select(sel));
      return h("li", {}, button, extra ?? null);
    };
    tree.replaceChildren(
      h("ul", { class: "st-tree" },
        item({ id: doc.course.id, label: "Course", type: "course" }, doc.course.title),
        doc.modules.map((m, mi) =>
          h("li", {},
            h("p", { class: "st-tree-module" }, `Module ${mi + 1} · ${m.title}`),
            h("ul", {}, m.lessons.map((l, li) => {
              const label = `Lesson ${mi + 1}.${li + 1}`;
              const questions = l.quiz?.questions ?? [];
              const lm = lessonMarker(l, stale);
              return item({ id: l.id, label, type: "lesson" }, `${mi + 1}.${li + 1} ${l.title}`,
                h("ul", {},
                  lm ? h("li", {}, markerEl(lm)) : null,
                  l.flags.length ? h("li", { class: "cb-tag" }, `${l.flags.length} flagged`) : null,
                  questions.map((q, qi) => item({ id: q.id, label: `${label} › Q${qi + 1}`, type: "question" }, `Q${qi + 1} · ${q.stem.slice(0, 60)}`, questionMarker(q.id)))));
            })))),
        doc.finalTest ? h("li", {}, h("p", { class: "st-tree-module" }, "Final test"),
          h("ul", {}, doc.finalTest.questions.map((q, qi) => item({ id: q.id, label: `Final test › Q${qi + 1}`, type: "question" }, `Q${qi + 1} · ${q.stem.slice(0, 60)}`, questionMarker(q.id))))) : null)
    );
  }

  function select(sel: Selection): void {
    selection = sel;
    tree.querySelectorAll("[data-element]").forEach((b) => b.setAttribute("aria-current", b.getAttribute("data-element") === sel.id ? "true" : "false"));
    scope.replaceChildren(h("span", { class: "st-scope-chip" }, `Editing: ${sel.label}`), (() => {
      const clear = h("button", { type: "button", class: "cb-link", "aria-label": "Clear the selection" }, "×");
      clear.addEventListener("click", () => {
        selection = null;
        scope.replaceChildren(h("span", { class: "cb-muted" }, "Select an element in the tree to edit it in chat."));
      });
      return clear;
    })());
    renderPreview();
    textarea.focus();
  }

  function renderPreview(): void {
    if (!version) return;
    const doc = version.document;
    const lessons = doc.modules.flatMap((m) => m.lessons);
    let node: Record<string, unknown> | null = null;
    const question = (q: BlueprintQuestion) => ({ id: "root", component: "QuizQuestionCard", questionId: q.id, type: q.type, stem: q.stem, options: q.options, explanation: q.explanation, citations: cite(q.citations) });
    const lessonCard = (l: (typeof lessons)[number]) => ({
      id: "root", component: "LessonPreviewCard", lessonId: l.id, title: l.title, minutes: l.minutes, flags: l.flags,
      blocks: l.blocks.map((b) => ({ id: b.id, kind: b.kind, markdown: b.markdown, citations: cite(b.citations) })),
    });
    if (selection?.type === "question") {
      const q = [...lessons.flatMap((l) => l.quiz?.questions ?? []), ...(doc.finalTest?.questions ?? [])].find((x) => x.id === selection!.id);
      if (q) node = question(q);
    } else if (selection?.type === "lesson") {
      const l = lessons.find((x) => x.id === selection!.id);
      if (l) node = lessonCard(l);
    }
    if (!node && lessons[0] && lessons[0].blocks.length) node = lessonCard(lessons[0]);
    preview.replaceChildren(
      node
        ? renderSurface([node as never], { surfaceId: "preview", dispatch: () => undefined, onCitation, onSelect: (id, label) => select({ id, label: label.slice(0, 80), type: node!.component === "QuizQuestionCard" ? "question" : "lesson" }) })
        : h("p", { class: "cb-muted" }, "Lessons appear here once they are generated.")
    );
    decoratePreview();
  }

  async function renderHistory(): Promise<void> {
    try {
      const list = await cb.versions.list(sessionId);
      history.replaceChildren(renderSurface([{ id: "root", component: "VersionList", currentVersionId: list.currentVersionId ?? "", versions: list.versions.map((v) => ({ ...v, reason: v.reason ?? undefined, createdAt: v.createdAt })) } as never], {
        surfaceId: "versions",
        dispatch: () => undefined,
        onRestore: async (id) => {
          try {
            await cb.versions.restore(id);
            announce("Version restored.");
          } catch (error) {
            announce(error instanceof ApiError ? error.message : "Restore failed.");
          }
        },
      }));
    } catch {
      history.replaceChildren(h("p", { class: "cb-muted" }, "History unavailable."));
    }
  }

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
      announce("Select an element in the course tree first.");
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

  void loadStaleness();
  void cb.events(sessionId, { onEvent: (event) => timeline.handle(event), onStatus: connectionStatus });
}

const root = document.querySelector<HTMLElement>("[data-studio-workspace]");
if (root) mountWorkspace(root);
