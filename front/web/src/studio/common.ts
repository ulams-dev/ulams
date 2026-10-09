/**
 * Shared browser code of the studio islands: the BFF client, the screen-reader announcer, the
 * top-bar status/cost pills and the citation popover (the source passage behind a chip).
 */
import { createCourseBuilderClient, type BuilderState } from "@ulams/sdk";
import { STATUS_LABEL } from "./labels.ts";
import { h, usd } from "@ulams/ui/builder/dom.ts";

export const studioClient = () => createCourseBuilderClient({ baseUrl: "/studio/api", prefix: "", timeoutMs: 120_000 });

export function announce(text: string): void {
  const region = document.querySelector<HTMLElement>("[data-announcer]");
  if (!region) return;
  region.textContent = "";
  // a new text node so the same message is announced twice in a row
  window.setTimeout(() => (region.textContent = text), 50);
}


export function updateTopBar(state: Partial<BuilderState>): void {
  const pill = document.querySelector<HTMLElement>("[data-status-pill]");
  if (pill && state.session) pill.textContent = STATUS_LABEL[state.session.status] ?? state.session.status;
  const crumb = document.querySelector<HTMLElement>("[data-crumb]");
  if (crumb && state.session?.title) crumb.textContent = state.session.title;
  const cost = document.querySelector<HTMLElement>("[data-cost-pill]");
  if (cost && state.cost) {
    cost.hidden = false;
    const tokens = state.cost.inputTokens + state.cost.outputTokens + state.cost.cacheReadTokens;
    cost.textContent = `${usd(state.cost.usedMicroUsd)} · ${Math.round(tokens / 1000)}k tokens`;
  }
}

export function connectionStatus(status: string, detail?: string): void {
  const el = document.querySelector<HTMLElement>("[data-connection]");
  if (!el) return;
  el.textContent = status === "reconnecting" ? "Reconnecting…" : status === "error" ? `Connection refused (${detail ?? ""}). Reload the page.` : "";
}

let dialog: HTMLDialogElement | null = null;

/** Opens the source passage behind a citation in a modal dialog (focus returns to the chip). */
export async function showCitation(fragmentId: string, label: string, trigger: HTMLElement): Promise<void> {
  dialog?.remove();
  const body = h("div", { class: "st-cite-body" }, h("p", { class: "cb-muted" }, "Loading the source passage…"));
  const close = h("button", { type: "button", class: "cb-btn cb-btn-small" }, "Close");
  dialog = h("dialog", { class: "st-cite-dialog", "aria-labelledby": "st-cite-title" },
    h("h2", { id: "st-cite-title", class: "cb-h3" }, `Source ${label}`), body, close) as HTMLDialogElement;
  close.addEventListener("click", () => dialog?.close());
  dialog.addEventListener("close", () => {
    dialog?.remove();
    trigger.focus();
  });
  document.body.append(dialog);
  dialog.showModal();
  try {
    const fragment = await studioClient().sources.fragment(fragmentId);
    body.replaceChildren(
      h("p", { class: "cb-eyebrow cb-mono" }, `${fragment.source.name}${fragment.pageStart ? ` · page ${fragment.pageStart}` : ""} · ${fragment.id}`),
      h("blockquote", { class: "st-cite-text" }, fragment.text)
    );
  } catch {
    body.replaceChildren(h("p", { class: "cb-error" }, "The passage could not be loaded."));
  }
}
