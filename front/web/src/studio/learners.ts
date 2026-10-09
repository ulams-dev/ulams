/**
 * What an update means for learners (ADR 0033): the impact panel of the review screen with the
 * editable learner note, and the two per-source switches for learner notices. The API creates the
 * notices after an apply; nothing here touches a learner's progress.
 */
import type { LearnerImpact, LivingCourseClient, ProposalDetail, SourceConnection } from "@ulams/sdk";
import { renderSurface, type FlatComponent } from "@ulams/ui/builder/renderer.ts";
import { h, uid } from "@ulams/ui/builder/dom.ts";
import { announce, message } from "./common.ts";

export const NOTE_MAX = 500;

export interface LearnerSettings {
  /** "Updated since you completed it", retired and new lessons. The re-attempt notice is always sent. */
  notify: boolean;
  /** "The source of this lesson changed; an update is under review". */
  showPending: boolean;
}

/** The API's defaults when a setting was never saved: notices on, the under-review marker off. */
export function learnerSettings(connection: Pick<SourceConnection, "settings"> | null | undefined): LearnerSettings {
  const settings = connection?.settings ?? {};
  return {
    notify: settings.notify_learners_of_updates === undefined ? true : settings.notify_learners_of_updates !== false,
    showPending: settings.show_pending_to_learners === true,
  };
}

/** Props of the LearnerImpact component. */
export function impactProps(impact: LearnerImpact, settings: LearnerSettings, applied: boolean): Record<string, unknown> {
  const n = (v: number | undefined): number => Math.max(0, Math.trunc(Number(v ?? 0)));
  return {
    applied,
    noticesOff: !settings.notify,
    topicUpdated: n(impact.learners.topic_updated),
    questionReattempt: n(impact.learners.question_reattempt),
    topicRetired: n(impact.learners.topic_retired),
    courseExtended: n(impact.learners.course_extended),
  };
}

/** The note can be changed until the proposal is applied or settled. */
export function noteEditable(status: ProposalDetail["status"]): boolean {
  return status === "ready" || status === "awaiting_analysis" || status === "budget_blocked";
}

export const noteCounter = (length: number): string => `${length} of ${NOTE_MAX} characters`;

/** The note is plain text: line breaks and runs of spaces are kept as typed, nothing is interpreted. */
export const cleanNote = (text: string): string => text.replace(/\r\n?/g, "\n").trim().slice(0, NOTE_MAX);

/* ------------------------------------------------------------------ the two switches */

/**
 * Two checkboxes for one source connection. A change is saved at once; a failure puts the box back
 * and says why. `onChange` receives the saved settings.
 */
export function learnerSwitches(lc: LivingCourseClient, connection: SourceConnection, onChange?: (settings: LearnerSettings) => void): HTMLElement {
  let current = learnerSettings(connection);
  const legendId = uid("lsw");
  const status = h("p", { class: "cb-muted cb-small", role: "status", "data-switch-status": "" });
  const error = h("p", { class: "cb-error", role: "alert", "data-switch-error": "" });

  const row = (key: "notify_learners_of_updates" | "show_pending_to_learners", field: keyof LearnerSettings, label: string, hint: string): HTMLElement => {
    const id = uid("lsw-c");
    const input = h("input", { type: "checkbox", id, "aria-describedby": `${id}-hint`, "data-switch": key }) as HTMLInputElement;
    input.checked = current[field];
    input.addEventListener("change", () => {
      const wanted = input.checked;
      error.textContent = "";
      status.textContent = "";
      input.disabled = true;
      lc.connections
        .update(connection.id, { settings: { [key]: wanted } })
        .then((saved) => {
          connection.settings = saved.settings ?? { ...connection.settings, [key]: wanted };
          current = learnerSettings(connection);
          status.textContent = "Saved.";
          announce(`${label}: ${wanted ? "on" : "off"}. Saved.`);
          onChange?.(current);
        })
        .catch((e) => {
          input.checked = !wanted;
          error.textContent = message(e, "The setting could not be saved.");
        })
        .finally(() => {
          input.disabled = false;
        });
    });
    return h("div", { class: "st-switch" }, input, h("div", {}, h("label", { for: id }, label), h("p", { id: `${id}-hint`, class: "cb-muted cb-small" }, hint)));
  };

  return h("fieldset", { class: "st-switches cb-card", "aria-labelledby": legendId, "data-learner-switches": "" },
    h("legend", { id: legendId, class: "cb-h3" }, "Learner notices for this source"),
    row("notify_learners_of_updates", "notify", "Tell learners when a lesson they completed is updated",
      "They see “Updated since you completed it”, “New since you finished” and retired lessons. The notice for a corrected quiz question and the extra attempt are always sent."),
    row("show_pending_to_learners", "showPending", "Show that an update is under review",
      "A lesson whose source changed days ago shows “The source of this lesson changed on …; an update is under review”. Off by default."),
    status, error);
}

/* ------------------------------------------------------------------ the review panel */

export interface LearnerPanel {
  el: HTMLElement;
  /** Re-renders from the proposal and the connection's settings; keeps what the author is typing. */
  update(detail: ProposalDetail, settings: LearnerSettings): void;
  /** Saves a changed note. False when it could not be saved (the reason is shown); true when saved or nothing changed. */
  saveIfDirty(): Promise<boolean>;
}

export function createLearnerPanel(lc: LivingCourseClient, proposalId: string): LearnerPanel {
  const headingId = uid("lp");
  const noteId = uid("lp-note");
  const impactHost = h("div", { "data-impact": "" });
  const area = h("textarea", { id: noteId, class: "cb-textarea st-note", rows: "3", maxlength: String(NOTE_MAX), "aria-describedby": `${noteId}-hint ${noteId}-count`, "data-note": "" }) as HTMLTextAreaElement;
  const counter = h("p", { id: `${noteId}-count`, class: "cb-muted cb-small" });
  const status = h("p", { class: "cb-muted cb-small", role: "status", "data-note-status": "" });
  const error = h("p", { class: "cb-error", role: "alert", "data-note-error": "" });
  const save = h("button", { type: "submit", class: "cb-btn cb-btn-small" }, "Save the note") as HTMLButtonElement;
  const useSuggested = h("button", { type: "button", class: "cb-btn cb-btn-small" }, "Use the suggested note") as HTMLButtonElement;
  const noteBlock = h("form", { class: "st-note-form", "data-note-form": "" },
    h("label", { for: noteId, class: "st-note-label" }, "Note for learners: what changed"),
    h("p", { id: `${noteId}-hint`, class: "cb-muted cb-small" },
      "Plain text, shown to learners under “Updated since you completed it”. We suggest the reasons of the major changes; change it if learners need something else."),
    area, counter, h("div", { class: "cb-actions" }, save, useSuggested), status, error);
  const readOnlyNote = h("p", { class: "st-note-readonly", "data-note-readonly": "" });
  const noteHost = h("div", {}, noteBlock, readOnlyNote);
  const el = h("section", { class: "st-learners cb-card", "aria-labelledby": headingId, "data-learner-impact": "" },
    h("h3", { id: headingId, class: "cb-serif cb-h3" }, "Learners"), impactHost, noteHost);

  let detail: ProposalDetail | null = null;
  let baseline = "";
  let busy = false;

  const dirty = (): boolean => cleanNote(area.value) !== baseline;
  const showCounter = (): void => {
    counter.textContent = noteCounter(area.value.length);
  };
  const setBusy = (value: boolean): void => {
    busy = value;
    area.readOnly = value;
    save.disabled = value;
    useSuggested.disabled = value;
    noteBlock.setAttribute("aria-busy", String(value));
  };

  async function persist(note: string): Promise<boolean> {
    if (!detail) return true;
    error.textContent = "";
    status.textContent = "";
    setBusy(true);
    try {
      const saved = await lc.proposals.setLearnerNote(proposalId, note);
      detail = { ...detail, learnerNote: saved.learnerNote, learnerImpact: detail.learnerImpact ? { ...detail.learnerImpact, note: saved.effectiveNote } : detail.learnerImpact };
      baseline = cleanNote(saved.effectiveNote);
      area.value = saved.effectiveNote;
      showCounter();
      status.textContent = note === "" ? "The suggested note is back." : "Note saved.";
      announce(status.textContent);
      return true;
    } catch (e) {
      error.textContent = message(e, "The note could not be saved.");
      return false;
    } finally {
      setBusy(false);
    }
  }

  noteBlock.addEventListener("submit", (event) => {
    event.preventDefault();
    if (!dirty()) {
      status.textContent = "Nothing to save.";
      return;
    }
    void persist(cleanNote(area.value));
  });
  useSuggested.addEventListener("click", () => void persist(""));
  area.addEventListener("input", () => {
    showCounter();
    status.textContent = "";
  });

  return {
    el,
    update(next, settings) {
      detail = next;
      const impact = next.learnerImpact;
      el.hidden = !impact;
      if (!impact) return;
      const applied = next.status === "applied";
      impactHost.replaceChildren(
        renderSurface([{ id: "root", component: "LearnerImpact", ...impactProps(impact, settings, applied) } as FlatComponent], { surfaceId: "review-LearnerImpact", dispatch: () => undefined })
      );
      // the note matters when some learner will see it
      const reach = settings.notify && impact.learners.topic_updated > 0;
      const editable = reach && noteEditable(next.status);
      noteBlock.hidden = !editable;
      readOnlyNote.hidden = editable || !reach || !impact.note;
      readOnlyNote.textContent = reach && impact.note ? `Note shown to learners: ${impact.note}` : "";
      // never overwrite what the author is typing
      if (!dirty() || !editable) {
        baseline = cleanNote(impact.note);
        area.value = impact.note;
        showCounter();
      }
    },
    async saveIfDirty() {
      if (busy) return false;
      if (noteBlock.hidden || !dirty()) return true;
      return persist(cleanNote(area.value));
    },
  };
}
