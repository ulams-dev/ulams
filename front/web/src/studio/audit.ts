/**
 * Audit trail page (/studio/s/:id/audit): who decided what and when, tied to the source revision
 * and the course versions. A filter form, the table (AuditTable), paging, the state of the hash
 * chain, and CSV and JSON exports as plain links to the studio BFF. The trail is read-only here.
 */
import type { AuditFilters, AuditPage } from "@ulams/sdk";
import { renderSurface, type FlatComponent } from "@ulams/ui/builder/renderer.ts";
import { h, uid } from "@ulams/ui/builder/dom.ts";
import { announce, livingClient, message } from "./common.ts";
import {
  ACTION_GROUPS,
  ACTOR_TYPES,
  chainText,
  EMPTY_TEXT,
  FILTERED_EMPTY_TEXT,
  filtersFrom,
  PER_PAGE,
  pageCount,
  rangeError,
  rangeText,
  tableProps,
  type AuditFormValues,
} from "./audit-model.ts";

export function mountAudit(root: HTMLElement): void {
  const lc = livingClient();
  const sessionId = root.dataset.session!;

  let form: AuditFormValues = { group: "", actorType: "", from: "", to: "" };
  let filters: AuditFilters = {};
  let page = 1;
  let requestId = 0;

  const chain = h("p", { class: "st-chain cb-card", role: "status", "data-chain": "" }, "Checking the chain…");
  const verifyAgain = h("button", { type: "button", class: "cb-btn cb-btn-small" }, "Check the chain again") as HTMLButtonElement;
  const csv = h("a", { class: "cb-btn cb-btn-small", href: "#", download: "", "data-export": "csv" }, "Export CSV") as HTMLAnchorElement;
  const json = h("a", { class: "cb-btn cb-btn-small", href: "#", download: "", "data-export": "json" }, "Export JSON") as HTMLAnchorElement;
  const formError = h("p", { class: "cb-error", role: "alert", "data-form-error": "" });
  const tableHost = h("div", { "data-table": "", "aria-live": "polite" });
  const range = h("p", { class: "cb-muted", role: "status", "data-range": "" });
  const previous = h("button", { type: "button", class: "cb-btn cb-btn-small" }, "Previous page") as HTMLButtonElement;
  const next = h("button", { type: "button", class: "cb-btn cb-btn-small" }, "Next page") as HTMLButtonElement;
  const pager = h("nav", { class: "st-pager", "aria-label": "Audit pages" }, previous, range, next);

  const select = (label: string, name: keyof AuditFormValues, options: Array<{ value: string; label: string }>): HTMLElement => {
    const id = uid("audit-f");
    const el = h("select", { id, class: "cb-select", name }, options.map((o) => h("option", { value: o.value }, o.label))) as HTMLSelectElement;
    el.addEventListener("change", () => (form = { ...form, [name]: el.value }));
    return h("div", { class: "st-field" }, h("label", { for: id }, label), el);
  };
  const date = (label: string, name: "from" | "to"): HTMLElement => {
    const id = uid("audit-f");
    const el = h("input", { id, class: "cb-input", type: "date", name }) as HTMLInputElement;
    el.addEventListener("change", () => (form = { ...form, [name]: el.value }));
    return h("div", { class: "st-field" }, h("label", { for: id }, label), el);
  };
  const apply = h("button", { type: "submit", class: "cb-btn cb-btn-primary" }, "Apply filters");
  const reset = h("button", { type: "reset", class: "cb-btn" }, "Clear filters");
  const filterForm = h("form", { class: "st-filters cb-card", "aria-label": "Filter the audit trail", "data-filters": "" },
    select("Action", "group", ACTION_GROUPS),
    select("Who", "actorType", ACTOR_TYPES),
    date("From", "from"),
    date("To", "to"),
    h("div", { class: "st-filter-actions" }, apply, reset),
    formError);

  root.replaceChildren(
    chain,
    h("div", { class: "st-toolbar" }, verifyAgain, h("span", { class: "st-spacer" }), csv, json),
    filterForm,
    tableHost,
    pager
  );

  const surface = (props: Record<string, unknown>): HTMLElement =>
    renderSurface([{ id: "root", component: "AuditTable", ...props } as FlatComponent], { surfaceId: "audit-table", dispatch: () => undefined });

  const filtered = (): boolean => Object.values(filters).some(Boolean);

  function exportLinks(): void {
    csv.href = lc.audit.exportUrl(sessionId, "csv", filters);
    json.href = lc.audit.exportUrl(sessionId, "json", filters);
  }

  async function verify(): Promise<void> {
    chain.textContent = "Checking the chain…";
    chain.dataset.state = "checking";
    try {
      const verdict = await lc.audit.verify(sessionId);
      chain.dataset.state = verdict.ok ? "ok" : "broken";
      chain.textContent = chainText(verdict);
      if (!verdict.ok) chain.setAttribute("role", "alert");
      else chain.setAttribute("role", "status");
    } catch (e) {
      chain.dataset.state = "error";
      chain.textContent = message(e, "The chain could not be checked.");
    }
  }

  function showPage(data: AuditPage): void {
    const total = data.total;
    const pages = pageCount(total, data.perPage || PER_PAGE);
    page = data.page;
    if (data.entries.length === 0) {
      tableHost.replaceChildren(h("p", { class: "cb-muted st-empty" }, filtered() ? FILTERED_EMPTY_TEXT : EMPTY_TEXT));
      pager.hidden = total === 0;
      range.textContent = "";
      return;
    }
    tableHost.replaceChildren(surface(tableProps(data.entries, `Audit trail, page ${page} of ${pages}, newest first`)));
    range.textContent = `${rangeText(page, data.perPage, total)} Page ${page} of ${pages}.`;
    previous.disabled = page <= 1;
    next.disabled = page >= pages;
    pager.hidden = false;
  }

  async function load(target: number, announceIt = false): Promise<void> {
    const mine = ++requestId;
    tableHost.setAttribute("aria-busy", "true");
    if (!tableHost.firstChild) tableHost.replaceChildren(h("p", { class: "cb-muted", role: "status" }, "Loading the audit trail…"));
    try {
      const data = await lc.audit.list(sessionId, { ...filters, page: target, perPage: PER_PAGE });
      if (mine !== requestId) return;
      showPage(data);
      if (announceIt) announce(`${rangeText(data.page, data.perPage, data.total)} Page ${data.page}.`);
    } catch (e) {
      if (mine !== requestId) return;
      const retry = h("button", { type: "button", class: "cb-btn cb-btn-small" }, "Try again");
      retry.addEventListener("click", () => void load(target));
      tableHost.replaceChildren(h("p", { class: "cb-error", role: "alert" }, message(e, "The audit trail could not be loaded.")), retry);
      pager.hidden = true;
    } finally {
      if (mine === requestId) tableHost.removeAttribute("aria-busy");
    }
  }

  filterForm.addEventListener("submit", (event) => {
    event.preventDefault();
    const problem = rangeError(form);
    formError.textContent = problem ?? "";
    if (problem) return;
    filters = filtersFrom(form);
    exportLinks();
    void load(1, true);
  });
  filterForm.addEventListener("reset", () => {
    form = { group: "", actorType: "", from: "", to: "" };
    filters = {};
    formError.textContent = "";
    exportLinks();
    void load(1, true);
  });
  previous.addEventListener("click", () => void load(page - 1, true));
  next.addEventListener("click", () => void load(page + 1, true));
  verifyAgain.addEventListener("click", () => void verify());

  pager.hidden = true;
  exportLinks();
  void verify();
  void load(1);
}

const root = document.querySelector<HTMLElement>("[data-studio-audit]");
if (root) mountAudit(root);
