/**
 * Production page (/studio/courses/:id/production): how this course was produced, from the authoring metric of the
 * tenant's own recorded runs and model calls: the time of every stage, the cost, the counts and what became of the AI's
 * elements. Read through the studio BFF (/studio/api/authoring-metrics/…); the author of the course may read it, so may
 * an administrator. Every figure is text (the bar of the element split is decorative and described), states are
 * announced, and nothing is compared with any other course. The files are made here from the loaded figures, with the
 * columns of the admin export.
 */
import { dataTable, figureList } from "@ulams/ui/builder/insights-view.ts";
import { h, uid } from "@ulams/ui/builder/dom.ts";
import { announce } from "./common.ts";
import { fill, type ProductionLocale } from "./production-i18n.ts";
import { barText, costFigures, countFigures, elementClasses, productionFormatterFor, stageRows, toCsv, toJson, totals, type CourseMetricData } from "./production-model.ts";

type Fetcher = (url: string, init?: RequestInit) => Promise<Response>;

export function mountProduction(root: HTMLElement, fetcher: Fetcher = (url, init) => fetch(url, init), save: (name: string, type: string, text: string) => void = download): void {
  const courseId = Number(root.dataset.course);
  const locale = (root.dataset.locale ?? "en") as ProductionLocale;
  const f = productionFormatterFor(locale);
  const t = f.t;
  let requestId = 0;

  const body = h("div", { class: "st-prod-body", "aria-live": "polite" });
  root.replaceChildren(body);
  const status = (text: string, role: "status" | "alert" = "status"): HTMLElement => h("p", { class: role === "alert" ? "cb-error" : "cb-muted", role }, text);

  function showLoading(): void {
    body.setAttribute("aria-busy", "true");
    body.replaceChildren(h("div", { class: "cb-skeleton", "aria-hidden": "true" }, h("span"), h("span"), h("span")), status(t.loading));
  }

  function showMessage(text: string, kind: "status" | "alert", state: string, retry: boolean): void {
    body.setAttribute("aria-busy", "false");
    const again = h("button", { type: "button", class: "cb-btn cb-btn-small" }, t.retry);
    again.addEventListener("click", () => void load());
    body.replaceChildren(h("div", { class: "cb-card", "data-state": state }, status(text, kind), retry ? again : null));
    announce(text);
  }

  const card = (title: string, ...children: Array<Node | null>): HTMLElement => {
    const id = uid("prod-h");
    return h("section", { class: "cb-card st-prod-section", "aria-labelledby": id }, h("h2", { id, class: "cb-h3" }, title), ...children);
  };

  function timeSection(m: CourseMetricData): HTMLElement {
    const stages = m.stages ?? [];
    return card(
      t.timeTitle,
      figureList(totals(f, m).map((x) => ({ label: x.label, value: x.value, note: x.note })), t.totalsTitle),
      stages.length === 0 ? null : dataTable({ caption: t.timeCaption, scrollLabel: t.timeScroll, columns: [{ label: t.colStage }, { label: t.colRun }, { label: t.colSeconds, numeric: true }, { label: t.colQuality }], rows: stageRows(f, stages) }),
      h("p", { class: "cb-muted cb-small" }, t.qualityNote),
    );
  }

  function elementsSection(m: CourseMetricData): HTMLElement {
    const rows = elementClasses(f, m);
    if (m.elements.ai_elements === 0) return card(t.elementsTitle, h("p", { class: "cb-muted" }, t.noElements));
    const bar = h("div", { class: "st-prod-bar", role: "img", "aria-label": barText(f, rows) }, rows.filter((r) => r.count > 0).map((r) => h("span", { class: `st-prod-seg st-prod-seg-${r.key}`, style: `flex-grow:${r.count}` })));
    const table = dataTable({
      caption: t.elementsCaption,
      scrollLabel: t.elementsScroll,
      columns: [{ label: t.colClass }, { label: t.colCount, numeric: true }, { label: t.colShare, numeric: true }],
      rows: [
        ...rows.map((r) => ({ id: r.key, cells: [r.label, f.number(r.count), f.percent(r.share)] })),
        { id: "author", cells: [t.classAuthor, f.number(m.elements.author_written), "–"] },
      ],
    });
    return card(t.elementsTitle, h("p", { class: "cb-muted" }, t.elementsIntro), bar, table, m.elements.unknown > 0 ? h("p", { class: "cb-muted cb-small" }, t.classUnknownNote) : null);
  }

  function exportSection(m: CourseMetricData): HTMLElement {
    const file = (extension: "csv" | "json", label: string): HTMLElement => {
      const button = h("button", { type: "button", class: "cb-btn cb-btn-small", "data-export": extension }, label);
      button.addEventListener("click", () => {
        const name = `authoring-metrics-course-${m.course_id}.${extension}`;
        save(name, extension === "csv" ? "text/csv" : "application/json", extension === "csv" ? toCsv(m) : toJson(m));
        announce(fill(t.exported, { name }));
      });
      return button;
    };

    return card(t.exportTitle, h("div", { class: "st-prod-actions" }, file("csv", t.exportCsv), file("json", t.exportJson)), h("p", { class: "cb-muted cb-small" }, `${fill(t.methodVersion, { n: m.method_version })}. ${fill(t.computed, { date: f.date(m.computed_at) })}`));
  }

  function render(m: CourseMetricData): void {
    body.setAttribute("aria-busy", "false");
    body.replaceChildren(
      h("p", { class: "cb-muted" }, t.ownFigures),
      timeSection(m),
      card(t.costTitle, figureList(costFigures(f, m).map((x) => ({ label: x.label, value: x.value, note: x.note })), t.costTitle), h("p", { class: "cb-muted cb-small" }, t.costNote)),
      card(t.countsTitle, figureList(countFigures(f, m).map((x) => ({ label: x.label, value: x.value })), t.countsTitle)),
      elementsSection(m),
      exportSection(m),
    );
  }

  async function load(): Promise<void> {
    const mine = ++requestId;
    showLoading();
    try {
      const response = await fetcher(`/studio/api/authoring-metrics/courses/${courseId}`, { headers: { Accept: "application/json" } });
      if (mine !== requestId) return;
      if (response.status === 404) return showMessage(t.notBuilt, "status", "not-built", false);
      if (response.status === 403) return showMessage(t.forbidden, "alert", "forbidden", false);
      if (!response.ok) return showMessage(t.error, "alert", "error", true);
      const data = ((await response.json()) as { data?: CourseMetricData }).data;
      if (!data) return showMessage(t.error, "alert", "error", true);
      render(data);
    } catch {
      if (mine !== requestId) return;
      showMessage(t.error, "alert", "error", true);
    }
  }

  void load();
}

function download(name: string, type: string, text: string): void {
  const url = URL.createObjectURL(new Blob([text], { type }));
  const link = h("a", { href: url, download: name });
  document.body.append(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

const root = typeof document === "undefined" ? null : document.querySelector<HTMLElement>("[data-studio-production]");
if (root) mountProduction(root);
