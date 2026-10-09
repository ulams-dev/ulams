/**
 * Update proposal review (/studio/s/:id/updates/:proposal): the source changes on the left, the
 * proposed changes per lesson in the middle, a sticky bar to apply what was accepted. Every part
 * renders from what the API returns; decisions are saved one by one, and nothing changes in the
 * course until the author applies.
 */
import { apiErrorInfo, type AgUiEvent, type ProposalDetail, type ProposalItem, type RevisionChanges } from "@ulams/sdk";
import { renderSurface, type FlatComponent } from "@ulams/ui/builder/renderer.ts";
import type { A2uiActionOut } from "@ulams/ui/builder/components.ts";
import { h, uid } from "@ulams/ui/builder/dom.ts";
import { announce, livingClient, message, showCitation, studioClient } from "./common.ts";
import { changeProps, cosmeticToggleLabel, countsSentence, splitChanges } from "./living.ts";
import {
  analyseLabel,
  applyLabel,
  applyModel,
  applySummary,
  canDecide,
  conflictsText,
  groupProgress,
  impactProps,
  itemProps,
  needsAttention,
  progressOf,
  progressText,
  PROPOSAL_STATUS_LABEL,
  rejectAllText,
  stepRows,
  usdText,
  withItem,
  type Progress,
} from "./updates.ts";

export interface ReviewOptions {
  /** Poll interval while the analysis or the apply runs and the event stream is not open. */
  pollMs?: number;
}

export function mountReview(root: HTMLElement, options: ReviewOptions = {}): void {
  const lc = livingClient();
  const cb = studioClient();
  const sessionId = root.dataset.session!;
  const proposalId = root.dataset.proposal!;
  const pollMs = options.pollMs ?? 3000;

  const header = h("div", { "data-header": "" });
  const notices = h("div", { class: "st-notices", "data-notices": "" });
  const statePanel = h("div", { "data-state": "" });
  const changesCol = h("details", { class: "st-review-changes cb-card", "data-source-changes": "", open: true });
  const itemsCol = h("div", { class: "st-review-items", "data-items": "" });
  const applyBar = h("div", { class: "st-apply-bar", "data-apply-bar": "", role: "region", "aria-label": "Apply the accepted changes", hidden: true });
  root.replaceChildren(header, notices, statePanel, h("div", { class: "st-review-body" }, changesCol, itemsCol), applyBar);

  let detail: ProposalDetail | null = null;
  let aiEnabled = true;
  let live: Progress | null = null;
  let sseOpen = false;
  let changes: RevisionChanges | null = null;
  let changesFor: string | null = null;
  let showCosmetic = false;
  let itemsKey = "";
  let pollTimer: number | undefined;
  let busy = false;
  const itemHosts = new Map<string, HTMLElement>();
  const abort = new AbortController();

  const surface = (name: string, props: Record<string, unknown>, onAction: (a: A2uiActionOut) => void = () => undefined): HTMLElement =>
    renderSurface([{ id: "root", component: name, ...props } as FlatComponent], {
      surfaceId: `review-${name}`,
      dispatch: onAction,
      onCitation: (id, label, trigger) => void showCitation(id, label, trigger),
    });

  const notify = (text: string, kind: "error" | "info" = "info"): void => {
    notices.replaceChildren(h("p", { class: kind === "error" ? "cb-error" : "cb-muted", role: kind === "error" ? "alert" : "status" }, text));
    announce(text);
  };
  const clearNotice = (): void => notices.replaceChildren();

  const retryButton = (label: string, run: () => void): HTMLElement => {
    const button = h("button", { type: "button", class: "cb-btn cb-btn-small" }, label);
    button.addEventListener("click", run);
    return button;
  };

  /** Inline confirmation: a labelled group with the consequence in words, never a blind click. */
  function confirmPanel(title: string, text: string, confirmLabel: string, onConfirm: () => void, onCancel: () => void): HTMLElement {
    const titleId = uid("confirm");
    const yes = h("button", { type: "button", class: "cb-btn cb-btn-primary cb-btn-small" }, confirmLabel);
    const no = h("button", { type: "button", class: "cb-btn cb-btn-small" }, "Cancel");
    const panel = h("div", { class: "st-confirm cb-card", role: "group", "aria-labelledby": titleId, tabindex: "-1", "data-confirm": "" },
      h("h3", { id: titleId, class: "cb-h3" }, title), h("p", {}, text), h("div", { class: "cb-actions" }, yes, no));
    yes.addEventListener("click", onConfirm);
    no.addEventListener("click", onCancel);
    panel.addEventListener("keydown", (event) => {
      if (event.key === "Escape") onCancel();
    });
    window.setTimeout(() => panel.focus(), 0);
    return panel;
  }

  /* ---------------------------------------------------------------- loading */

  async function load(): Promise<void> {
    try {
      const next = await lc.proposals.get(proposalId);
      detail = next;
      live = next.status === "analysing" ? live : null;
    } catch (error) {
      if (!detail) {
        root.replaceChildren(h("p", { class: "cb-error", role: "alert" }, message(error, "The update could not be loaded.")), retryButton("Try again", () => void start()));
        return;
      }
      notify(message(error, "The update could not be refreshed."), "error");
      return;
    }
    render();
    void loadChanges();
    schedule();
  }

  async function start(): Promise<void> {
    root.replaceChildren(h("p", { class: "cb-muted", role: "status" }, "Loading the update…"));
    try {
      aiEnabled = (await cb.sessions.get(sessionId)).aiEnabled !== false;
    } catch {
      aiEnabled = true;
    }
    root.replaceChildren(header, notices, statePanel, h("div", { class: "st-review-body" }, changesCol, itemsCol), applyBar);
    await load();
    void cb.events(sessionId, { onEvent: onEvent, onStatus: (s) => (sseOpen = s === "open") }, { signal: abort.signal });
    window.addEventListener("pagehide", () => abort.abort(), { once: true });
  }

  function schedule(): void {
    window.clearTimeout(pollTimer);
    if (!detail || (detail.status !== "analysing" && detail.status !== "applying") || abort.signal.aborted) return;
    pollTimer = window.setTimeout(() => void load(), sseOpen ? Math.max(pollMs, 15_000) : pollMs);
  }

  function onEvent(event: AgUiEvent): void {
    if (String(event.type) !== "CUSTOM") return;
    const value = ((event as { value?: Record<string, unknown> }).value ?? {}) as Record<string, unknown>;
    if (value.proposalId !== proposalId) return;
    const name = String((event as { name?: unknown }).name);
    if (name === "update_analysis") {
      live = { total: Number(value.total ?? 0), done: Number(value.done ?? 0), failed: Number(value.failed ?? 0), costMicroUsd: Number(value.costMicroUsd ?? 0) };
      renderState();
      if (live.total > 0 && live.done + live.failed >= live.total) void load();
    } else if (name === "update_proposal" || name === "update_applied") {
      void load();
    }
  }

  async function loadChanges(): Promise<void> {
    const p = detail;
    if (!p?.toRevision || !p.fromRevision || changesFor === p.toRevision.id) return;
    changesFor = p.toRevision.id;
    changesCol.replaceChildren(h("summary", {}, "Source changes"), h("p", { class: "cb-muted", role: "status" }, "Loading the source changes…"));
    try {
      changes = await lc.revisions.changes(p.toRevision.id, p.fromRevision.id);
      renderChanges();
    } catch (error) {
      changesFor = null;
      changesCol.replaceChildren(h("summary", {}, "Source changes"), h("p", { class: "cb-error", role: "alert" }, message(error, "The source changes could not be loaded.")), retryButton("Try again", () => void loadChanges()));
    }
  }

  /* ---------------------------------------------------------------- rendering */

  function render(): void {
    if (!detail) return;
    renderHeader();
    renderState();
    renderItemsIfChanged();
    renderApplyBar();
    renderChanges();
  }

  function renderHeader(): void {
    const p = detail!;
    const from = p.fromRevision?.number;
    const to = p.toRevision?.number;
    const source = countsSentence(p.counts?.source);
    const d = p.decisions;
    header.replaceChildren(
      h("div", { class: "st-review-head" },
        h("h2", { class: "cb-serif cb-h2" }, from && to ? `Source update: revision ${from} → ${to}` : `Source update #${p.number}`),
        h("span", { class: "st-status", "data-proposal-status": p.status }, PROPOSAL_STATUS_LABEL[p.status] ?? p.status)),
      h("p", { class: "cb-muted" },
        source ? `What changed in the source: ${source}.` : "The source changed.",
        ` Proposal ${p.number}`,
        p.status === "ready" || p.status === "applied" ? ` · ${d.accepted ?? 0} accepted · ${d.rejected ?? 0} rejected · ${d.pending ?? 0} undecided` : ""),
      surface("ImpactSummary", impactProps(p)),
      // EXTENSION POINT (learner impact and learner note, a later milestone): the panel renders here.
      h("div", { "data-extension": "learner-impact" })
    );
  }

  function renderState(): void {
    const p = detail;
    if (!p) return;
    const nodes: HTMLElement[] = [];
    const newer = p.counts?.newerRevision;
    if (newer && !["applied", "rejected", "superseded", "no_impact"].includes(p.status)) {
      nodes.push(h("p", { class: "st-banner cb-card", role: "note" }, h("strong", {}, `A newer source revision exists (revision ${newer}). `),
        "Decisions you made here are not carried over. ", h("a", { href: `/studio/s/${sessionId}/updates` }, "See all updates"), " to open the proposal for the newer revision."));
    }
    if (p.error && (p.status === "ready" || p.status === "failed")) {
      nodes.push(h("p", { class: "cb-error", role: "alert" }, p.status === "ready" ? `The last apply did not finish: ${p.error}` : p.error));
    }
    switch (p.status) {
      case "analysing":
        nodes.push(analysingPanel(p));
        break;
      case "awaiting_analysis":
        nodes.push(awaitingPanel(p));
        break;
      case "budget_blocked":
        nodes.push(h("section", { class: "cb-card st-state" },
          h("h2", { class: "cb-h3" }, "The AI budget is used up"),
          h("p", {}, "The analysis stopped before it could propose changes. You can update the listed elements by hand in the workspace, or try the analysis again after the budget resets."),
          h("div", { class: "cb-actions" }, h("a", { class: "cb-btn cb-btn-small", href: `/studio/s/${sessionId}/workspace` }, "Update by hand in the workspace"),
            ...(aiEnabled ? [retryButton("Try the analysis again", () => void analyse(false))] : []))));
        break;
      case "failed":
        nodes.push(h("section", { class: "cb-card st-state" },
          h("h2", { class: "cb-h3" }, "The analysis did not finish"),
          h("p", {}, p.error ?? "Something went wrong while the changes were analysed."),
          h("div", { class: "cb-actions" }, ...(aiEnabled ? [retryButton("Run the analysis again", () => void analyse(false))] : []), h("a", { class: "cb-btn cb-btn-small", href: `/studio/s/${sessionId}/workspace` }, "Update by hand in the workspace"))));
        break;
      case "applying":
        nodes.push(h("section", { class: "cb-card st-state", "aria-busy": "true" },
          h("h2", { class: "cb-h3" }, "Applying the accepted changes"),
          h("progress", { "aria-label": "Applying the accepted changes" }),
          h("p", { class: "cb-muted", role: "status" }, "The course is being updated. You can leave this page; nothing is lost.")));
        break;
      case "applied":
        nodes.push(h("section", { class: "cb-card st-state st-done", "data-done": "" },
          h("h2", { class: "cb-h3" }, "The update is applied"),
          h("p", {}, `Your course now reflects source revision ${p.toRevision?.number ?? ""}. Learners keep their progress.`.replace("revision .", "the new revision.")),
          h("div", { class: "cb-actions" }, h("a", { class: "cb-btn cb-btn-primary", href: `/studio/s/${sessionId}/workspace` }, "Back to the workspace"), h("a", { class: "cb-btn", href: `/studio/s/${sessionId}/updates` }, "All updates"))));
        break;
      case "no_impact":
        nodes.push(h("section", { class: "cb-card st-state" }, h("h2", { class: "cb-h3" }, "This change does not affect your course"), h("p", {}, "No lesson, question or objective relies on the passages that changed.")));
        break;
      case "rejected":
        nodes.push(h("section", { class: "cb-card st-state" }, h("h2", { class: "cb-h3" }, "You kept the earlier version"), h("p", {}, "Your course was not changed, and the source revision is marked as reviewed.")));
        break;
      case "superseded":
        nodes.push(h("section", { class: "cb-card st-state" }, h("h2", { class: "cb-h3" }, "A newer proposal replaced this one"), h("p", {}, h("a", { href: `/studio/s/${sessionId}/updates` }, "Open the list of updates"), " to review it.")));
        break;
      default:
        break;
    }
    const failed = p.status === "ready" || p.status === "failed" ? needsAttention(p) : [];
    if (failed.length) nodes.push(failedGroupsPanel(failed));
    statePanel.replaceChildren(...nodes);
  }

  function analysingPanel(p: ProposalDetail): HTMLElement {
    const progress = progressOf(p, live);
    const rows = stepRows(p);
    const bar = h("progress", { max: Math.max(1, progress.total), value: Math.min(progress.done + progress.failed, Math.max(1, progress.total)), "aria-label": "Analysis progress" });
    return h("section", { class: "cb-card st-state", "aria-busy": "true", "data-analysing": "" },
      h("h2", { class: "cb-h3" }, "Analysing the changes"),
      bar,
      h("p", { class: "cb-muted", role: "status", "data-progress-text": "" }, progressText(progress)),
      rows.length
        ? h("ul", { class: "st-steps", "aria-label": "Progress per lesson" }, rows.map((r) =>
            h("li", { "data-step": r.groupKey, class: `st-step st-step-${r.status}` },
              h("span", { "aria-hidden": "true" }, r.status === "done" ? "✓" : r.status === "failed" ? "!" : r.status === "running" ? "…" : "·"), " ", r.label,
              h("span", { class: "cb-muted" }, ` · ${r.status === "done" ? "done" : r.status === "failed" ? "failed" : r.status === "running" ? "analysing" : "waiting"}`))))
        : null,
      h("p", { class: "cb-muted cb-small" }, "You can leave this page; the analysis continues."));
  }

  function awaitingPanel(p: ProposalDetail): HTMLElement {
    if (!aiEnabled) {
      return h("section", { class: "cb-card st-state" }, h("h2", { class: "cb-h3" }, "AI analysis is off"),
        h("p", {}, "AI features are disabled on this installation, so no changes are proposed. The elements below are affected by the source change: open each one in the workspace and update it by hand, then mark it as handled."));
    }
    const slot = h("div", { "data-confirm-slot": "" });
    const label = analyseLabel(p.estimatedCostMicroUsd);
    const start_ = h("button", { type: "button", class: "cb-btn cb-btn-primary", "data-analyse": "" }, label);
    start_.addEventListener("click", () => {
      start_.disabled = true;
      slot.replaceChildren(confirmPanel("Start the analysis?",
        `The AI will read the changed sections and propose edits${p.estimatedCostMicroUsd ? `, for about ${usdText(p.estimatedCostMicroUsd)}` : ""}. Nothing in your course changes until you approve it.`,
        "Start the analysis", () => void analyse(true), () => {
          slot.replaceChildren();
          start_.disabled = false;
          start_.focus();
        }));
    });
    return h("section", { class: "cb-card st-state", "data-awaiting": "" },
      h("h2", { class: "cb-h3" }, "Ready to be analysed"),
      h("p", {}, "The estimate is above the limit for automatic analysis, so it waits for you. The changed elements are listed below."),
      h("div", { class: "cb-actions" }, start_), slot);
  }

  function failedGroupsPanel(rows: ReturnType<typeof needsAttention>): HTMLElement {
    return h("section", { class: "cb-card st-state st-failed", "data-failed-groups": "", role: "group", "aria-label": "Groups that failed" },
      h("h2", { class: "cb-h3" }, `The analysis failed for ${rows.length} ${rows.length === 1 ? "group" : "groups"}`),
      h("ul", { class: "st-steps" }, rows.map((r) => {
        const retry = h("button", { type: "button", class: "cb-btn cb-btn-small", "data-retry": r.groupKey },
          r.id && detail?.runId ? "Retry this group" : "Run the analysis again");
        retry.addEventListener("click", () => void retryGroup(r, retry as HTMLButtonElement));
        return h("li", { class: "st-step st-step-failed" }, h("strong", {}, r.label), r.error ? h("span", { class: "cb-muted" }, ` · ${r.error}`) : null, " ", aiEnabled ? retry : null);
      })),
      h("p", { class: "cb-muted cb-small" }, "The other groups are ready to review. Failed elements stay unchanged until you retry or update them by hand."));
  }

  function renderItemsIfChanged(): void {
    const p = detail!;
    const key = `${p.status}|${aiEnabled}|${p.items.map((i) => `${i.id}:${i.status}:${i.kind}:${i.regenerations}:${i.after ? 1 : 0}`).join(",")}`;
    if (key === itemsKey) return;
    itemsKey = key;
    renderItems();
  }

  function renderItems(): void {
    const p = detail!;
    itemHosts.clear();
    if (p.groups.length === 0) {
      itemsCol.replaceChildren(h("p", { class: "cb-muted st-empty" }, p.status === "analysing" ? "Proposed changes appear here as each lesson is analysed." : "There are no elements to review."));
      return;
    }
    const decidable = canDecide(p.status, aiEnabled);
    const regenerate = aiEnabled && p.status === "ready";
    const toolbar = p.status === "ready" ? bulkToolbar() : null;
    itemsCol.replaceChildren(
      ...(toolbar ? [toolbar] : []),
      ...p.groups.map((g) => {
        const headingId = uid("group");
        return h("section", { class: "st-group", "aria-labelledby": headingId, "data-group": g.key },
          h("div", { class: "st-group-head" }, h("h3", { id: headingId, class: "cb-serif cb-h3" }, g.label), h("span", { class: "cb-muted cb-small", "data-group-progress": g.key }, groupProgress(g))),
          h("ul", { class: "st-items" }, g.items.map((item) => {
            const host = h("li", { "data-item-host": item.id });
            itemHosts.set(item.id, host);
            host.replaceChildren(itemSurface(item, decidable, regenerate));
            return host;
          })));
      })
    );
  }

  const itemSurface = (item: ProposalItem, decidable: boolean, regenerate: boolean): HTMLElement =>
    surface("UpdateItem", itemProps(item, { sessionId, canDecide: decidable, canRegenerate: regenerate }), (action) => void onItemAction(action));

  function bulkToolbar(): HTMLElement {
    const slot = h("div", { "data-reject-slot": "" });
    const all = h("button", { type: "button", class: "cb-btn cb-btn-small", "data-accept-all": "" }, "Accept all");
    const reject = h("button", { type: "button", class: "cb-btn cb-btn-small", "data-reject-all": "" }, "Reject all");
    all.addEventListener("click", () => void acceptAll());
    reject.addEventListener("click", () => {
      reject.disabled = true;
      slot.replaceChildren(confirmPanel("Reject the whole update?", rejectAllText(detail?.toRevision?.number), "Reject and keep my course", () => void rejectAll(), () => {
        slot.replaceChildren();
        reject.disabled = false;
        reject.focus();
      }));
    });
    return h("div", { class: "st-bulk" },
      h("div", { class: "cb-actions" }, all, reject),
      h("p", { class: "cb-muted cb-small" }, "Accept all accepts every undecided update, removal and no-change item. Reject all keeps your course as it is and acknowledges this source revision."),
      slot);
  }

  function renderApplyBar(): void {
    const p = detail!;
    if (p.status !== "ready") {
      applyBar.hidden = true;
      applyBar.replaceChildren();
      return;
    }
    const model = applyModel(p);
    applyBar.hidden = false;
    const button = h("button", { type: "button", class: "cb-btn cb-btn-primary", "data-apply": "", disabled: !model.canApply || busy }, applyLabel(model.applicable));
    button.addEventListener("click", () => void apply(false));
    applyBar.replaceChildren(
      h("p", { class: "st-apply-text", "data-apply-summary": "" }, applySummary(model)),
      ...(model.conflicts > 0 ? [h("p", { class: "cb-error" }, conflictsText(model.conflicts))] : []),
      button,
      h("div", { "data-apply-slot": "" })
    );
  }

  function renderChanges(): void {
    if (!changes) return;
    const { shown, cosmetic } = splitChanges(changes.changes);
    const rows = showCosmetic ? changes.changes : shown;
    const toggle = h("button", { type: "button", class: "cb-btn cb-btn-small", "aria-pressed": String(showCosmetic), hidden: cosmetic.length === 0 }, cosmeticToggleLabel(cosmetic.length, showCosmetic));
    toggle.addEventListener("click", () => {
      showCosmetic = !showCosmetic;
      renderChanges();
      announce(showCosmetic ? "Cosmetic changes shown." : "Cosmetic changes hidden.");
    });
    changesCol.replaceChildren(
      h("summary", {}, `Source changes (${shown.length})`),
      toggle,
      rows.length
        ? h("ul", { class: "st-change-list" }, rows.map((row) => h("li", { id: `change-${row.id}` }, surface("FragmentChange", changeProps(row)))))
        : h("p", { class: "cb-muted st-empty" }, cosmetic.length ? "Only cosmetic edits (spacing, punctuation) were found." : "No changes to show.")
    );
  }

  /* ---------------------------------------------------------------- actions */

  function replaceItem(item: ProposalItem, summary?: ProposalDetail): void {
    if (!detail) return;
    detail = withItem(detail, item, summary);
    itemsKey = "";
  }

  async function onItemAction(action: A2uiActionOut): Promise<void> {
    if (!detail) return;
    const itemId = String(action.context.itemId);
    const item = detail.items.find((i) => i.id === itemId);
    if (!item) return;
    if (action.name === "decide_item") {
      const decision = String(action.context.decision) as "accept" | "reject" | "reset";
      try {
        const call = decision === "accept" ? lc.proposals.accept : decision === "reject" ? lc.proposals.reject : lc.proposals.reset;
        const result = await call(proposalId, itemId);
        detail = withItem(detail, result.item, { ...detail, ...result.proposal });
        itemsKey = keyNow();
        clearNotice();
        renderHeader();
        renderApplyBar();
        updateGroupProgress();
      } catch (error) {
        notify(message(error, "The decision could not be saved."), "error");
        host(itemId)?.replaceChildren(itemSurface(item, true, aiEnabled && detail.status === "ready"));
      }
    } else if (action.name === "regenerate_item") {
      const comment = String(action.context.comment ?? "");
      try {
        const result = await lc.proposals.regenerate(proposalId, itemId, comment);
        replaceItem(result.item, { ...detail, ...result.proposal });
        itemsKey = keyNow();
        host(itemId)?.replaceChildren(itemSurface(result.item, true, true));
        renderHeader();
        renderApplyBar();
        updateGroupProgress();
        const next = host(itemId)?.querySelector<HTMLElement>("[data-focus-target]");
        next?.focus();
        announce(`A new version of ${result.item.label} is ready. Review it and decide.`);
      } catch (error) {
        const info = apiErrorInfo(error);
        const text = info.code === "ai_disabled" ? "AI is disabled on this installation. Edit the element by hand in the workspace." : message(error, "A new version could not be made.");
        notify(text, "error");
        host(itemId)?.replaceChildren(itemSurface(item, true, info.code === "ai_disabled" ? false : true));
      }
    }
  }

  const host = (itemId: string): HTMLElement | undefined => itemHosts.get(itemId);
  const keyNow = (): string => {
    const p = detail!;
    return `${p.status}|${aiEnabled}|${p.items.map((i) => `${i.id}:${i.status}:${i.kind}:${i.regenerations}:${i.after ? 1 : 0}`).join(",")}`;
  };

  function updateGroupProgress(): void {
    for (const g of detail!.groups) {
      const el = [...itemsCol.querySelectorAll("[data-group-progress]")].find((e) => e.getAttribute("data-group-progress") === g.key);
      if (el) el.textContent = groupProgress(g);
    }
  }

  async function analyse(confirm: boolean): Promise<void> {
    clearNotice();
    try {
      await lc.proposals.analyse(proposalId, confirm);
      announce("The analysis started.");
      live = null;
      await load();
    } catch (error) {
      const info = apiErrorInfo(error);
      if (info.code === "confirm_estimate") {
        const estimate = Number(info.data?.estimateMicroUsd ?? 0);
        if (detail) detail = { ...detail, estimatedCostMicroUsd: estimate || detail.estimatedCostMicroUsd };
        notify(`The estimate is ${estimate ? `about ${usdText(estimate)}` : "above the automatic limit"}. Choose Analyse to confirm it.`);
        itemsKey = "";
        renderState();
      } else if (info.code === "ai_disabled") {
        aiEnabled = false;
        notify("AI is disabled on this installation. Update the listed elements by hand in the workspace.", "error");
        itemsKey = "";
        render();
      } else if (info.code === "budget_blocked") {
        notify(message(error, "The AI budget is used up. Update the elements by hand, or try again after the budget resets."), "error");
        await load();
      } else {
        notify(message(error, "The analysis could not be started."), "error");
      }
    }
  }

  async function retryGroup(row: ReturnType<typeof needsAttention>[number], button: HTMLButtonElement): Promise<void> {
    button.disabled = true;
    button.setAttribute("aria-busy", "true");
    try {
      if (row.id && detail?.runId) {
        await cb.runs.retryStep(detail.runId, row.id);
        announce(`Retrying ${row.label}.`);
        live = null;
        await load();
      } else {
        await analyse(false);
      }
    } catch (error) {
      notify(message(error, "The group could not be retried."), "error");
      button.disabled = false;
      button.removeAttribute("aria-busy");
    }
  }

  async function acceptAll(): Promise<void> {
    try {
      const result = await lc.proposals.acceptAll(proposalId);
      announce(result.accepted === 0 ? "Nothing was left to accept." : `${result.accepted} ${result.accepted === 1 ? "item" : "items"} accepted.`);
      await load();
    } catch (error) {
      notify(message(error, "The items could not be accepted."), "error");
    }
  }

  async function rejectAll(): Promise<void> {
    try {
      await lc.proposals.rejectAll(proposalId);
      announce("The update was rejected. Your course was not changed.");
      await load();
    } catch (error) {
      notify(message(error, "The update could not be rejected."), "error");
    }
  }

  async function apply(overwrite: boolean): Promise<void> {
    if (!detail || busy) return;
    clearNotice();
    applyBar.querySelector("[data-apply-slot]")?.replaceChildren();
    setBusy(true);
    try {
      await lc.proposals.apply(proposalId, overwrite);
      announce("Applying the accepted changes.");
      detail = { ...detail, status: "applying" };
      itemsKey = "";
      render();
      schedule();
    } catch (error) {
      const info = apiErrorInfo(error);
      if (info.code === "conflicts") {
        const rows = ((info.data?.conflicts as ProposalItem[] | undefined) ?? []);
        for (const row of rows) replaceItem({ ...row, status: "conflict" });
        notify(conflictsText(rows.length || 1), "error");
        itemsKey = "";
        render();
        announce(conflictsText(rows.length || 1));
      } else if (info.code === "admin_edits") {
        const drift = ((info.data?.drift as string[] | undefined) ?? []).slice(0, 8);
        const slot = applyBar.querySelector<HTMLElement>("[data-apply-slot]");
        slot?.replaceChildren(confirmPanel("Overwrite edits made in the admin?",
          `${drift.length ? `${drift.join(", ")} ${drift.length === 1 ? "was" : "were"}` : "Some elements were"} edited in the admin after the last apply. Applying this update overwrites those edits.`,
          "Overwrite and apply", () => void apply(true), () => {
            slot.replaceChildren();
            applyBar.querySelector<HTMLElement>("[data-apply]")?.focus();
          }));
      } else {
        notify(message(error, "The changes could not be applied."), "error");
      }
    } finally {
      setBusy(false);
    }
  }

  /** Disables Apply while the request runs, without redrawing the bar (a confirmation may be open in it). */
  function setBusy(value: boolean): void {
    busy = value;
    const apply_ = applyBar.querySelector<HTMLButtonElement>("[data-apply]");
    if (apply_) {
      apply_.disabled = value || !detail || !applyModel(detail).canApply;
      if (value) apply_.setAttribute("aria-busy", "true");
      else apply_.removeAttribute("aria-busy");
    }
  }

  void start();
}

const root = document.querySelector<HTMLElement>("[data-studio-review]");
if (root) mountReview(root);
