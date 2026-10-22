/**
 * Course Versions page (/studio/courses/:id/versions): the releases of a course, newest first, with what started each,
 * who approved it and what changed since the previous one. Releases come from the course-versions API through the studio
 * BFF and exist for courses made with the Course Builder. Every state is text (no meaning by colour alone), states are
 * announced, and nothing animates under reduced motion. Compare any two versions arrives in a later milestone.
 */
import { ApiError, createCourseVersionsClient, type CourseReleases, type CourseVersionsClient } from "@ulams/sdk";
import { dataTable } from "@ulams/ui/builder/insights-view.ts";
import { h, uid } from "@ulams/ui/builder/dom.ts";
import { announce } from "./common.ts";
import { fill, type VersionsLocale } from "./versions-i18n.ts";
import { pagerFor, releaseRows, versionsFormatterFor } from "./versions-model.ts";

export const versionsClient = (): CourseVersionsClient => createCourseVersionsClient({ baseUrl: "/studio/api", prefix: "/course-versions" });

const PER_PAGE = 25;

export function mountVersions(root: HTMLElement, client: CourseVersionsClient = versionsClient()): void {
  const courseId = Number(root.dataset.course);
  const locale = (root.dataset.locale ?? "en") as VersionsLocale;
  const f = versionsFormatterFor(locale);
  const t = f.t;
  let page = 1;
  let requestId = 0;

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

  function showReleases(data: CourseReleases, moved: boolean): void {
    body.setAttribute("aria-busy", "false");
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
      columns: [{ label: t.colRelease }, { label: t.colReleased }, { label: t.colStartedBy }, { label: t.colApprovedBy }, { label: t.colChanges }, { label: t.colVersion, numeric: true }],
      rows: rows.map((r) => ({
        id: r.id,
        cells: [r.number, r.released, r.startedBy, r.approvedBy, h("ul", { class: "st-ver-changes" }, r.changes.map((c) => h("li", {}, c))), r.version],
      })),
    });
    const summary = fill(t.pageStatus, { page: pager.page, last: pager.last, total: pager.total });
    body.replaceChildren(
      h("section", { class: "cb-card st-ver-section", "aria-labelledby": id }, heading, table,
        h("nav", { class: "st-ver-pager", "aria-label": t.pagerLabel }, newer, h("span", { class: "cb-muted", "data-page-status": "" }, summary), older))
    );
    if (moved) {
      announce(fill(t.announcePage, { page: pager.page, last: pager.last }));
      heading.focus();
    }
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
      showReleases(data, moved);
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
