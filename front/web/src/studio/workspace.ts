/**
 * Workspace (/studio/s/:id/workspace): course tree, live preview of the selected element,
 * element-scoped chat with diff approve/reject, undo/redo and version history.
 */
import { ApiError, type Blueprint, type BlueprintQuestion, type BuilderState, type BlueprintVersion } from "@ulams/sdk";
import { renderSurface } from "@ulams/ui/builder/renderer.ts";
import type { A2uiActionOut } from "@ulams/ui/builder/components.ts";
import { h } from "@ulams/ui/builder/dom.ts";
import { announce, connectionStatus, showCitation, studioClient, updateTopBar } from "./common.ts";
import { Timeline } from "./timeline.ts";

type Selection = { id: string; label: string; type: "lesson" | "question" | "block" | "module" | "course" };

export function mountWorkspace(root: HTMLElement): void {
  const cb = studioClient();
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
    onCustom: (name) => {
      if (name === "applied") void loadVersion(timeline.state.session.currentVersionId, true);
    },
  });

  async function onState(state: BuilderState): Promise<void> {
    updateTopBar(state);
    undo.disabled = !state.canUndo;
    redo.disabled = !state.canRedo;
    const status = state.session?.status;
    applyBar.replaceChildren(
      ...(status === "apply_review" ? [h("a", { class: "cb-btn", href: `/studio/s/${sessionId}` }, "Review the apply in the builder")] : []),
      ...(status === "applied" ? [h("a", { class: "cb-btn cb-btn-primary", href: `/studio/s/${sessionId}/done` }, "Course overview")] : [])
    );
    if (state.session?.currentVersionId && state.session.currentVersionId !== loadedVersionId) await loadVersion(state.session.currentVersionId);
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
    if (selection && !findLabel(version.document, selection.id)) selection = null;
    renderPreview();
    void renderHistory();
  }

  function findLabel(doc: Blueprint, id: string): boolean {
    return JSON.stringify(doc).includes(`"id":"${id}"`);
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
              return item({ id: l.id, label, type: "lesson" }, `${mi + 1}.${li + 1} ${l.title}`,
                h("ul", {},
                  l.flags.length ? h("li", { class: "cb-tag" }, `${l.flags.length} flagged`) : null,
                  questions.map((q, qi) => item({ id: q.id, label: `${label} › Q${qi + 1}`, type: "question" }, `Q${qi + 1} · ${q.stem.slice(0, 60)}`))));
            })))),
        doc.finalTest ? h("li", {}, h("p", { class: "st-tree-module" }, "Final test"),
          h("ul", {}, doc.finalTest.questions.map((q, qi) => item({ id: q.id, label: `Final test › Q${qi + 1}`, type: "question" }, `Q${qi + 1} · ${q.stem.slice(0, 60)}`)))) : null)
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

  void cb.events(sessionId, { onEvent: (event) => timeline.handle(event), onStatus: connectionStatus });
}

const root = document.querySelector<HTMLElement>("[data-studio-workspace]");
if (root) mountWorkspace(root);
