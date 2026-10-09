/**
 * Sources page (/studio/s/:id/sources): one card per source with its sync state, a form to upload a
 * new version, the revision timeline and the fragment changes of the selected revision. The
 * browser talks to the studio BFF; every part renders from what the API returns.
 */
import type { LivingSource, RevisionChanges, SourceRevision } from "@ulams/sdk";
import { renderSurface, type FlatComponent } from "@ulams/ui/builder/renderer.ts";
import type { A2uiActionOut } from "@ulams/ui/builder/components.ts";
import { h, uid } from "@ulams/ui/builder/dom.ts";
import { announce, livingClient, message } from "./common.ts";
import { connectionPanel } from "./connection.ts";
import { learnerSwitches } from "./learners.ts";
import { ACCEPTED_FILES, cardProps, changeProps, cosmeticToggleLabel, emptyChangesText, splitChanges, timelineProps, uploadMessage } from "./living.ts";

export function mountSources(root: HTMLElement): void {
  const lc = livingClient();
  const sessionId = root.dataset.session!;
  const list = root.querySelector<HTMLElement>("[data-sources]")!;

  const surface = (name: string, props: Record<string, unknown>, onAction: (a: A2uiActionOut) => void): HTMLElement =>
    renderSurface([{ id: "root", component: name, ...props } as FlatComponent], { surfaceId: `sources-${name}`, dispatch: onAction });

  function block(source: LivingSource): HTMLElement {
    let revisions: SourceRevision[] = [];
    let selectedId: string | null = null;
    let showCosmetic = false;
    let changes: RevisionChanges | null = null;

    const card = h("div", { "data-card": "" });
    const fileId = uid("file");
    const status = h("p", { class: "cb-muted st-upload-status", role: "status" });
    const error = h("p", { class: "cb-error", role: "alert" });
    const fileInput = h("input", { type: "file", id: fileId, accept: ACCEPTED_FILES, "data-file": "" }) as HTMLInputElement;
    const drop = h("form", { class: "st-drop st-drop-inline cb-card", "aria-labelledby": `${fileId}-title`, "data-drop": "" },
      h("h2", { id: `${fileId}-title`, class: "st-drop-title" }, "Upload a new version"),
      h("p", { class: "cb-muted" }, "Drop the updated Markdown, PDF or DOCX file here. We compare it with the revision before it."),
      h("label", { class: "cb-btn cb-btn-primary st-file", for: fileId }, "Choose a file", fileInput),
      status, error);
    const timeline = h("div", { "data-timeline": "" });
    const changesTitle = h("h2", { class: "cb-h3", id: `${fileId}-changes` }, "What changed");
    const toggle = h("button", { type: "button", class: "cb-btn cb-btn-small", hidden: true, "aria-pressed": "false" }) as HTMLButtonElement;
    const changesBody = h("div", { class: "st-changes", role: "region", "data-changes": "", "aria-labelledby": `${fileId}-changes` });
    const panel = connectionPanel({
      lc, source,
      refresh: () => refreshSource(),
      reloadRevisions: () => load(),
    });
    const el = h("section", { class: "st-source", "data-source-block": source.id, "aria-label": source.title ?? source.name },
      card, panel?.el ?? null, source.connection ? learnerSwitches(lc, source.connection) : null, drop, h("h2", { class: "cb-h3 st-sub" }, "Revisions"), timeline,
      h("div", { class: "st-changes-head" }, changesTitle, toggle), changesBody);

    const onAction = (a: A2uiActionOut) => {
      if (a.name === "upload_version") fileInput.focus();
      else if (a.name === "check_now") void panel?.check();
      else if (a.name === "select_revision") void select(String(a.context.revisionId));
    };

    function renderCard(): void {
      card.replaceChildren(surface("SourceConnectionCard", cardProps(source), onAction));
    }

    function renderTimeline(): void {
      if (revisions.length === 0) {
        timeline.replaceChildren(h("p", { class: "cb-muted" }, "No revisions yet."));
        return;
      }
      timeline.replaceChildren(surface("RevisionTimeline", timelineProps(source.id, revisions, selectedId), onAction));
    }

    function renderChanges(): void {
      const rev = revisions.find((r) => r.id === selectedId);
      changesTitle.textContent = rev ? `What changed in revision ${rev.number}` : "What changed";
      if (!changes) return;
      const { shown, cosmetic } = splitChanges(changes.changes);
      const rows = showCosmetic ? changes.changes : shown;
      toggle.hidden = cosmetic.length === 0;
      toggle.textContent = cosmeticToggleLabel(cosmetic.length, showCosmetic);
      toggle.setAttribute("aria-pressed", String(showCosmetic));
      if (rows.length === 0) {
        const none = changes.from === null
          ? emptyChangesText(source.connection?.connector)
          : cosmetic.length > 0
            ? "Only cosmetic edits (spacing, punctuation) were found in this revision."
            : "This revision has no content changes compared with the one before it.";
        changesBody.replaceChildren(h("p", { class: "cb-muted st-empty" }, none));
        return;
      }
      changesBody.replaceChildren(
        h("ul", { class: "st-change-list" }, rows.map((row) => h("li", {}, surface("FragmentChange", changeProps(row), () => undefined))))
      );
    }

    async function select(revisionId: string): Promise<void> {
      selectedId = revisionId;
      renderTimeline();
      changesBody.replaceChildren(h("p", { class: "cb-muted", role: "status" }, "Loading the changes…"));
      toggle.hidden = true;
      try {
        changes = await lc.revisions.changes(revisionId);
        showCosmetic = false;
        renderChanges();
      } catch (e) {
        changes = null;
        const retry = h("button", { type: "button", class: "cb-btn cb-btn-small" }, "Try again");
        retry.addEventListener("click", () => void select(revisionId));
        changesBody.replaceChildren(h("p", { class: "cb-error", role: "alert" }, message(e, "The changes could not be loaded.")), retry);
      }
    }

    async function load(preferId?: string): Promise<void> {
      timeline.replaceChildren(h("p", { class: "cb-muted", role: "status" }, "Loading revisions…"));
      try {
        revisions = await lc.revisions.list(source.id);
      } catch (e) {
        const retry = h("button", { type: "button", class: "cb-btn cb-btn-small" }, "Try again");
        retry.addEventListener("click", () => void load(preferId));
        timeline.replaceChildren(h("p", { class: "cb-error", role: "alert" }, message(e, "The revisions could not be loaded.")), retry);
        return;
      }
      if (revisions.length === 0) {
        renderTimeline();
        changesBody.replaceChildren(h("p", { class: "cb-muted st-empty" }, emptyChangesText(source.connection?.connector)));
        return;
      }
      await select(preferId ?? revisions[0]!.id);
    }

    async function refreshSource(): Promise<void> {
      try {
        const fresh = (await lc.sources.list(sessionId)).find((s) => s.id === source.id);
        if (fresh) Object.assign(source, fresh);
      } catch {
        /* the card keeps its last state */
      }
      renderCard();
      panel?.render();
    }

    async function upload(file: File): Promise<void> {
      error.textContent = "";
      status.textContent = `Uploading ${file.name}…`;
      drop.setAttribute("aria-busy", "true");
      fileInput.disabled = true;
      try {
        const result = await lc.revisions.upload(source.id, file, file.name);
        const text = uploadMessage(result);
        status.textContent = text;
        announce(text);
        await refreshSource();
        await load(result.revision.id);
      } catch (e) {
        status.textContent = "";
        error.textContent = message(e, "The upload failed. Try again.");
      } finally {
        drop.removeAttribute("aria-busy");
        fileInput.disabled = false;
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
      const file = (event as DragEvent).dataTransfer?.files?.[0];
      if (file) void upload(file);
    });
    drop.addEventListener("submit", (event) => event.preventDefault());
    toggle.addEventListener("click", () => {
      showCosmetic = !showCosmetic;
      renderChanges();
      announce(showCosmetic ? "Cosmetic changes shown." : "Cosmetic changes hidden.");
    });

    renderCard();
    if (source.status !== "ready") drop.hidden = true;
    void load();
    return el;
  }

  async function start(): Promise<void> {
    list.replaceChildren(h("p", { class: "cb-muted", role: "status" }, "Loading your sources…"));
    try {
      const sources = await lc.sources.list(sessionId);
      if (sources.length === 0) {
        list.replaceChildren(h("p", { class: "cb-muted st-empty" }, "This course has no sources yet. Add one in the Builder."));
        return;
      }
      list.replaceChildren(...sources.map(block));
    } catch (e) {
      const retry = h("button", { type: "button", class: "cb-btn cb-btn-small" }, "Try again");
      retry.addEventListener("click", () => void start());
      list.replaceChildren(h("p", { class: "cb-error", role: "alert" }, message(e, "The sources could not be loaded.")), retry);
    }
  }

  void start();
}

const root = document.querySelector<HTMLElement>("[data-studio-sources]");
if (root) mountSources(root);
