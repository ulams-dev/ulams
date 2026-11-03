/**
 * Course Versions page (/studio/courses/:id/versions): the releases of a course, newest first, with what started each,
 * who approved it and what changed since the previous one. Releases come from the course-versions API through the studio
 * BFF and exist for courses made with the Course Builder. Every release has a link to compare it with the previous one,
 * and any two releases (or, with "show all versions", any two versions, a variant's too) can be ticked and compared on the
 * Compare page. Every state is text (no meaning by colour alone), states are announced, and nothing animates under
 * reduced motion.
 */
import { ApiError, createCourseVersionsClient, type CourseReleases, type CourseVersions, type CourseVersionsClient } from "@ulams/sdk";
import { dataTable } from "@ulams/ui/builder/insights-view.ts";
import { h, uid } from "@ulams/ui/builder/dom.ts";
import { announce } from "./common.ts";
import { comparePath, orderPair } from "./compare-model.ts";
import { fill, type VersionsLocale } from "./versions-i18n.ts";
import { pagerFor, releaseRows, versionRows, versionsFormatterFor } from "./versions-model.ts";

export const versionsClient = (): CourseVersionsClient => createCourseVersionsClient({ baseUrl: "/studio/api", prefix: "/course-versions" });

const PER_PAGE = 25;
const ALL_PER_PAGE = 200;

/** A version ticked for comparison: its id (the compare API takes version or release ids) and its number, to order the pair. */
interface Picked {
  id: string;
  number: number;
}

export function mountVersions(root: HTMLElement, client: CourseVersionsClient = versionsClient()): void {
  const courseId = Number(root.dataset.course);
  const locale = (root.dataset.locale ?? "en") as VersionsLocale;
  const f = versionsFormatterFor(locale);
  const t = f.t;
  let page = 1;
  let requestId = 0;
  let showAll = false;
  let allVersions: CourseVersions | null = null;
  let allFailed = false;
  let lastReleases: CourseReleases | null = null;
  const picks = new Map<string, Picked>();

  const body = h("div", { class: "st-ver-body", "aria-live": "polite" });
  root.replaceChildren(body);

  const status = (text: string, role: "status" | "alert" = "status"): HTMLElement => h("p", { class: role === "alert" ? "cb-error" : "cb-muted", role }, text);

  function showLoading(): void {
    body.setAttribute("aria-busy", "true");
    if (!body.firstChild) body.replaceChildren(h("div", { class: "cb-skeleton", "aria-hidden": "true" }, h("span"), h("span"), h("span")), status(t.loading));
  }

  function showMessage(text: string, kind: "status" | "alert", state: string, retry: boolean): void {
    body.setAttribute("aria-busy", "false");
    const again = h("button", { type: "button", class: "cb-btn cb-btn-small" }, t.retry);
    again.addEventListener("click", () => void load());
    body.replaceChildren(h("div", { class: "cb-card", "data-state": state }, status(text, kind), retry ? again : null));
  }

  // ---- ticking two versions to compare ----------------------------------------------------------------------------

  /** A checkbox for one version; the third is refused until one of the two is cleared. */
  function pickBox(id: string, number: number | null, label: string): HTMLElement {
    const box = h("input", { type: "checkbox", class: "st-ver-pick", name: "pick", value: id, "aria-label": label, "data-pick": id, "data-nonumber": number === null ? "1" : null }) as HTMLInputElement;
    box.checked = picks.has(id);
    box.disabled = number === null || (!box.checked && picks.size >= 2);
    box.addEventListener("change", () => {
      if (number === null) return;
      if (box.checked) picks.set(id, { id, number });
      else picks.delete(id);
      syncPicks();
      announce(fill(t.selectedStatus, { n: picks.size }));
    });
    return box;
  }

  /** Keeps every checkbox, the count and the compare button in step with the selection (the same version can be in both tables). */
  function syncPicks(): void {
    for (const box of root.querySelectorAll<HTMLInputElement>("input[data-pick]")) {
      box.checked = picks.has(box.dataset.pick ?? "");
      box.disabled = !box.checked && (picks.size >= 2 || box.dataset.nonumber === "1");
    }
    const count = root.querySelector<HTMLElement>("[data-pick-count]");
    if (count) count.textContent = picks.size >= 2 ? `${fill(t.selectedStatus, { n: picks.size })}. ${t.limitReached}` : fill(t.selectedStatus, { n: picks.size });
    const go = root.querySelector<HTMLButtonElement>("[data-compare-selected]");
    if (go) go.disabled = picks.size !== 2;
    if (picks.size === 2) {
      const [older, newer] = orderPair(...([...picks.values()] as [Picked, Picked]));
      root.querySelector<HTMLInputElement>('[data-compare-form] input[name="from"]')!.value = older.id;
      root.querySelector<HTMLInputElement>('[data-compare-form] input[name="to"]')!.value = newer.id;
    }
    const clear = root.querySelector<HTMLButtonElement>("[data-clear-picks]");
    if (clear) clear.disabled = picks.size === 0;
  }

  function pickBar(): HTMLElement {
    const go = h("button", { type: "submit", class: "cb-btn cb-btn-primary cb-btn-small", "data-compare-selected": "" }, t.compareSelected) as HTMLButtonElement;
    go.disabled = true;
    const clear = h("button", { type: "button", class: "cb-btn cb-btn-small", "data-clear-picks": "" }, t.clearSelection) as HTMLButtonElement;
    clear.disabled = true;
    clear.addEventListener("click", () => {
      picks.clear();
      syncPicks();
      announce(fill(t.selectedStatus, { n: 0 }));
    });
    // a plain GET form: the two ticked versions travel as the `from` (older) and `to` (newer) of the compare page
    return h("form", { class: "st-ver-picks", method: "get", action: `/studio/courses/${courseId}/compare`, "data-compare-form": "" },
      h("p", { class: "cb-muted st-ver-hint" }, t.selectHint),
      h("input", { type: "hidden", name: "from", value: "" }), h("input", { type: "hidden", name: "to", value: "" }), h("input", { type: "hidden", name: "lang", value: locale }),
      h("div", { class: "st-ver-pick-actions" }, go, clear, h("span", { class: "cb-muted", "data-pick-count": "" }, fill(t.selectedStatus, { n: picks.size }))));
  }

  // ---- the two tables --------------------------------------------------------------------------------------------

  function releasesSection(data: CourseReleases, moved: boolean): HTMLElement {
    const id = uid("ver-h");
    const heading = h("h2", { id, class: "cb-h3", tabindex: "-1" }, t.releasesTitle);
    const pager = pagerFor(data);
    const newer = h("button", { type: "button", class: "cb-btn cb-btn-small", "data-pager": "newer" }, t.previous) as HTMLButtonElement;
    const older = h("button", { type: "button", class: "cb-btn cb-btn-small", "data-pager": "older" }, t.next) as HTMLButtonElement;
    newer.disabled = !pager.hasNewer;
    older.disabled = !pager.hasOlder;
    newer.addEventListener("click", () => void go(page - 1));
    older.addEventListener("click", () => void go(page + 1));
    const rows = releaseRows(f, data.releases);
    const table = dataTable({
      caption: t.caption,
      scrollLabel: t.scrollLabel,
      columns: [{ label: t.colRelease }, { label: t.colSelect }, { label: t.colReleased }, { label: t.colStartedBy }, { label: t.colApprovedBy }, { label: t.colChanges }, { label: t.colVersion, numeric: true }, { label: t.colActions }],
      rows: rows.map((r) => ({
        id: r.id,
        cells: [
          r.number,
          pickBox(r.versionId, r.versionNumber, fill(t.selectRelease, { n: r.n })),
          r.released,
          r.startedBy,
          r.approvedBy,
          h("ul", { class: "st-ver-changes" }, r.changes.map((c) => h("li", {}, c))),
          r.version,
          r.previousReleaseId
            ? h("a", { class: "cb-btn cb-btn-small st-ver-link", href: comparePath(courseId, r.previousReleaseId, r.id, locale), "aria-label": fill(t.compareWithPreviousLabel, { n: r.n }) }, t.compareWithPrevious)
            : t.firstReleaseAction,
        ],
      })),
    });
    const summary = fill(t.pageStatus, { page: pager.page, last: pager.last, total: pager.total });
    const section = h("section", { class: "cb-card st-ver-section", "aria-labelledby": id }, heading, table,
      h("nav", { class: "st-ver-pager", "aria-label": t.pagerLabel }, newer, h("span", { class: "cb-muted", "data-page-status": "" }, summary), older));
    if (moved) {
      announce(fill(t.announcePage, { page: pager.page, last: pager.last }));
      queueMicrotask(() => heading.focus());
    }
    return section;
  }

  function allSection(): HTMLElement | null {
    if (!showAll) return null;
    const id = uid("ver-all");
    const heading = h("h2", { id, class: "cb-h3" }, t.allVersionsTitle);
    if (allFailed) return h("section", { class: "cb-card st-ver-section", "aria-labelledby": id, "data-state": "all-error" }, heading, status(t.errorAll, "alert"));
    if (!allVersions) return h("section", { class: "cb-card st-ver-section", "aria-labelledby": id }, heading, status(t.loadingAll));
    const rows = versionRows(f, allVersions.versions, allVersions.variants);
    const branches = allVersions.variants.length > 0;
    const table = dataTable({
      caption: t.allCaption,
      scrollLabel: t.allScrollLabel,
      columns: [{ label: t.colVersionNo, numeric: true }, { label: t.colSelect }, { label: t.colKind }, { label: t.colState }, { label: t.colCreated }, ...(branches ? [{ label: t.colBranch }] : []), { label: t.colReleasedAs }],
      rows: rows.map((r) => ({
        id: r.id,
        cells: [r.version, pickBox(r.id, r.n, fill(t.selectVersion, { n: r.n })), r.kind, r.state, r.created, ...(branches ? [r.branch ?? "–"] : []), r.released],
      })),
    });
    return h("section", { class: "cb-card st-ver-section", "aria-labelledby": id, "data-all-versions": "" }, heading, table);
  }

  function renderPage(moved = false): void {
    if (!lastReleases) return;
    body.setAttribute("aria-busy", "false");
    const toggleId = uid("ver-all-toggle");
    const toggle = h("input", { type: "checkbox", id: toggleId, "data-show-all": "" }) as HTMLInputElement;
    toggle.checked = showAll;
    toggle.addEventListener("change", () => void setShowAll(toggle.checked));
    const controls = h("div", { class: "st-ver-controls" },
      h("label", { class: "st-ver-toggle", for: toggleId }, toggle, h("span", {}, t.showAll)),
      h("a", { class: "cb-btn cb-btn-small st-ver-link", href: `/studio/courses/${courseId}/compare?lang=${locale}` }, t.openCompare));
    const all = allSection();
    body.replaceChildren(controls, pickBar(), releasesSection(lastReleases, moved), ...(all ? [all] : []));
    syncPicks();
  }

  async function setShowAll(on: boolean): Promise<void> {
    showAll = on;
    allFailed = false;
    renderPage();
    root.querySelector<HTMLElement>("[data-show-all]")?.focus();
    if (!on || allVersions) return;
    try {
      allVersions = await client.versions(courseId, { branch: "all", perPage: ALL_PER_PAGE });
    } catch {
      allFailed = true;
    }
    if (!showAll) return;
    const focused = document.activeElement?.hasAttribute("data-show-all") ?? false;
    renderPage();
    if (focused) root.querySelector<HTMLElement>("[data-show-all]")?.focus();
    announce(allFailed ? t.errorAll : t.allVersionsTitle);
  }

  async function go(next: number): Promise<void> {
    page = next;
    await load(true);
  }

  async function load(moved = false): Promise<void> {
    const mine = ++requestId;
    showLoading();
    try {
      const data = await client.releases(courseId, { page, perPage: PER_PAGE });
      if (mine !== requestId) return;
      if (data.total === 0) return showMessage(t.empty, "status", "empty", false);
      lastReleases = data;
      renderPage(moved);
    } catch (error) {
      if (mine !== requestId) return;
      if (error instanceof ApiError && error.status === 404) return showMessage(t.notBuilt, "status", "not-built", false);
      if (error instanceof ApiError && error.status === 403) return showMessage(t.forbidden, "alert", "forbidden", false);
      showMessage(t.error, "alert", "error", true);
    }
  }

  void load();
}

const root = typeof document === "undefined" ? null : document.querySelector<HTMLElement>("[data-studio-versions]");
if (root) mountVersions(root);
