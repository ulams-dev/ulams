/**
 * Policies pages of the studio (/studio/policies, ADR 0140): the list, creating a policy, the policy page (departments and
 * who is trained, the people, versions, uploading a change) and the review of one version (what changed, how each department's
 * course is updated, the change class, what approving will do, approve or reject). One island, one view per page
 * (`data-policies-view`). Every state is text, states are announced, tables sit in focusable scroll regions and nothing
 * animates under reduced motion. The owner decides: nothing here approves itself.
 */
import { ApiError, createPoliciesClient, type AcademyState, type PoliciesClient, type PolicyDashboard, type PolicyDetail, type PolicyVariantRow, type VersionReview } from "@ulams/sdk";
import { dataTable } from "@ulams/ui/builder/insights-view.ts";
import { h, uid } from "@ulams/ui/builder/dom.ts";
import { announce } from "./common.ts";
import { fill, type PoliciesLocale } from "./policies-i18n.ts";
import { canApproveFirst, canGenerate, canUploadChange, isOpenVersion, lowersClass, personRows, policiesFormatterFor, policyRows, stageText, versionRows } from "./policies-model.ts";

export const policiesClient = (): PoliciesClient => createPoliciesClient({ baseUrl: "/studio/api", prefix: "/policies", timeoutMs: 120_000 });

const PER_PAGE = 25;

const apiMessage = (error: unknown): string | null => (error instanceof ApiError && error.status !== 0 ? error.message.replace(/^API \d+( on [^:]*)?: /, "") : null);

export function mountPolicies(root: HTMLElement, client: PoliciesClient = policiesClient()): void {
  const view = root.dataset.policiesView ?? "list";
  const locale = (root.dataset.locale ?? "en") as PoliciesLocale;
  const f = policiesFormatterFor(locale);
  const t = f.t;
  const policyId = root.dataset.policy ?? "";
  const versionNumber = Number(root.dataset.version ?? 0);
  const suffix = locale === "en" ? "" : `?lang=${locale}`;
  let requestId = 0;

  const body = h("div", { class: "st-pol-body", "aria-live": "polite" });
  root.replaceChildren(body);

  const status = (text: string, role: "status" | "alert" = "status"): HTMLElement => h("p", { class: role === "alert" ? "cb-error" : "cb-muted", role }, text);
  const heading = (text: string, id = uid("pol-h")): HTMLElement => h("h2", { id, class: "cb-h3", tabindex: "-1" }, text);

  function showLoading(): void {
    body.setAttribute("aria-busy", "true");
    if (!body.firstChild) body.replaceChildren(h("div", { class: "cb-skeleton", "aria-hidden": "true" }, h("span"), h("span"), h("span")), status(t.loading));
  }

  function showMessage(text: string, kind: "status" | "alert", state: string, retry: boolean, again: () => void = () => void load()): void {
    body.setAttribute("aria-busy", "false");
    const button = h("button", { type: "button", class: "cb-btn cb-btn-small" }, t.retry);
    button.addEventListener("click", again);
    body.replaceChildren(h("div", { class: "cb-card", "data-state": state }, status(text, kind), retry ? button : null));
  }

  function failure(error: unknown, again: () => void = () => void load()): void {
    if (error instanceof ApiError && error.status === 404) return showMessage(t.notFound, "alert", "not-found", false);
    if (error instanceof ApiError && error.status === 403) return showMessage(t.forbidden, "alert", "forbidden", false);
    showMessage(t.error, "alert", "error", true, again);
  }

  /** A form's result line: success or the API's own message (never a bare "error"). */
  function resultLine(): HTMLElement {
    return h("p", { class: "cb-muted st-pol-result", role: "status", "data-result": "" });
  }
  const say = (line: HTMLElement, text: string, kind: "status" | "alert" = "status"): void => {
    line.textContent = text;
    line.className = kind === "alert" ? "cb-error st-pol-result" : "cb-muted st-pol-result";
    line.setAttribute("role", kind);
    announce(text);
  };

  const field = (label: string, control: HTMLElement, hint?: string): HTMLElement => {
    const id = uid("pol-f");
    control.id = id;
    const hintId = hint ? `${id}-hint` : null;
    if (hintId) control.setAttribute("aria-describedby", hintId);
    return h("div", { class: "st-pol-field" }, h("label", { for: id }, label), control, hint ? h("p", { id: hintId, class: "cb-muted st-pol-hint" }, hint) : null);
  };

  const select = (options: Array<[string, string]>, value: string, name: string): HTMLSelectElement => {
    const el = h("select", { name }) as HTMLSelectElement;
    for (const [v, text] of options) el.append(h("option", { value: v }, text));
    el.value = value;
    return el;
  };

  // ---- list -------------------------------------------------------------------------------------------------------

  async function loadList(): Promise<void> {
    const mine = ++requestId;
    showLoading();
    try {
      const policies = await client.list();
      if (mine !== requestId) return;
      body.setAttribute("aria-busy", "false");
      if (policies.length === 0) return showMessage(t.list.empty, "status", "empty", false);
      const rows = policyRows(f, policies);
      body.replaceChildren(
        dataTable({
          caption: t.list.caption,
          scrollLabel: t.list.scrollLabel,
          columns: [{ label: t.list.colTitle }, { label: t.list.colCurrent }, { label: t.list.colReview }, { label: t.list.colDepartments, numeric: true }],
          rows: rows.map((r) => ({ id: r.id, cells: [h("a", { href: `/studio/policies/${r.id}${suffix}`, "aria-label": r.openLabel }, r.title), r.current, r.review, r.departments] })),
        }),
      );
    } catch (error) {
      if (mine === requestId) failure(error);
    }
  }

  // ---- create -----------------------------------------------------------------------------------------------------

  function mountCreate(): void {
    body.setAttribute("aria-busy", "false");
    const result = resultLine();
    const title = h("input", { type: "text", name: "title", required: true, maxlength: "255", autocomplete: "off" }) as HTMLInputElement;
    const file = h("input", { type: "file", name: "file", required: true, accept: ".md,.markdown,.txt,.pdf,.docx" }) as HTMLInputElement;
    const total = h("input", { type: "number", name: "totalMinutes", min: "5", max: "2400", value: "30" }) as HTMLInputElement;
    const lesson = h("input", { type: "number", name: "lessonMinutes", min: "2", max: "120", value: "10" }) as HTMLInputElement;
    const list = h("div", { class: "st-pol-departments", role: "group", "aria-labelledby": "pol-dept-title" });
    let count = 0;

    function addDepartment(): void {
      const n = ++count;
      const name = h("input", { type: "text", name: `department-${n}`, maxlength: "120", autocomplete: "off", "data-department": "label" }) as HTMLInputElement;
      const seniority = select(Object.entries(t.create.seniorityOptions), "mixed", `seniority-${n}`);
      seniority.setAttribute("data-department", "seniority");
      const experience = select(Object.entries(t.create.experienceOptions), "some", `experience-${n}`);
      experience.setAttribute("data-department", "priorExperience");
      const minutes = h("input", { type: "number", name: `minutes-${n}`, min: "5", max: "2400", "data-department": "totalMinutes" }) as HTMLInputElement;
      const remove = h("button", { type: "button", class: "cb-btn cb-btn-small", "aria-label": fill(t.create.remove, { n }) }, t.create.remove.replace(" {n}", "").replace("{n}", ""));
      const fieldset = h("fieldset", { class: "cb-card st-pol-department", "data-department-row": "" },
        h("legend", { class: "cb-h3" }, fill(t.create.department, { n })),
        field(t.create.name, name),
        field(t.create.seniority, seniority),
        field(t.create.experience, experience),
        field(t.create.minutes, minutes),
        remove);
      remove.addEventListener("click", () => {
        const rows = list.querySelectorAll("[data-department-row]");
        if (rows.length <= 1) return;
        fieldset.remove();
        announce(fill(t.create.remove, { n }));
        addButton.focus();
      });
      list.append(fieldset);
    }

    const addButton = h("button", { type: "button", class: "cb-btn cb-btn-small" }, t.create.add) as HTMLButtonElement;
    addButton.addEventListener("click", () => {
      addDepartment();
      list.querySelector<HTMLInputElement>("[data-department-row]:last-of-type input")?.focus();
    });
    addDepartment();

    const submit = h("button", { type: "submit", class: "cb-btn cb-btn-primary" }, t.create.submit) as HTMLButtonElement;
    const form = h("form", { class: "cb-card st-pol-form", novalidate: true },
      field(t.create.title, title),
      field(t.create.file, file, t.create.fileHint),
      h("div", { class: "st-pol-row" }, field(t.create.totalMinutes, total), field(t.create.lessonMinutes, lesson)),
      h("h2", { id: "pol-dept-title", class: "cb-h3" }, t.create.departments),
      h("p", { class: "cb-muted" }, t.create.departmentHint),
      list, addButton, submit, result);
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      const departments = [...list.querySelectorAll<HTMLElement>("[data-department-row]")].map((row) => {
        const value = (key: string) => (row.querySelector<HTMLInputElement | HTMLSelectElement>(`[data-department="${key}"]`)?.value ?? "").trim();
        return { label: value("label"), seniority: value("seniority") as never, priorExperience: value("priorExperience") as never, totalMinutes: value("totalMinutes") ? Number(value("totalMinutes")) : undefined };
      });
      const chosen = file.files?.[0];
      if (!title.value.trim()) return say(result, t.create.needTitle, "alert"), title.focus();
      if (!chosen) return say(result, t.create.needFile, "alert"), file.focus();
      if (departments.some((d) => !d.label)) return say(result, t.create.needDepartment, "alert"), list.querySelector<HTMLInputElement>('[data-department="label"]')?.focus();
      submit.disabled = true;
      say(result, t.create.creating);
      client.create({ title: title.value.trim(), file: chosen, brief: { totalMinutes: Number(total.value) || undefined, lessonMinutes: Number(lesson.value) || undefined }, departments }).then(
        (policy) => {
          say(result, t.create.created);
          window.location.assign(`/studio/policies/${policy.id}${suffix}`);
        },
        (error) => {
          submit.disabled = false;
          say(result, apiMessage(error) ?? t.error, "alert");
        },
      );
    });
    body.replaceChildren(form);
  }

  // ---- one policy -------------------------------------------------------------------------------------------------

  let policy: PolicyDetail | null = null;
  let dashboard: PolicyDashboard | null = null;
  let page = 1;
  let filterVariant = "";
  let filterStatus = "";

  async function loadPolicy(moved = false): Promise<void> {
    const mine = ++requestId;
    showLoading();
    try {
      const [detail, board] = await Promise.all([client.get(policyId), client.dashboard(policyId, { variant: filterVariant, status: filterStatus, page, perPage: PER_PAGE })]);
      if (mine !== requestId) return;
      policy = detail;
      dashboard = board;
      renderPolicy(moved);
    } catch (error) {
      if (mine === requestId) failure(error, () => void loadPolicy());
    }
  }

  function departmentsSection(p: PolicyDetail, board: PolicyDashboard): HTMLElement {
    const id = uid("pol-dep");
    const items = board.departments.map((d) => {
      const variant = p.variants.find((v) => v.id === d.id);
      return h("li", { class: "cb-card st-pol-dept", "data-department": d.department },
        h("h3", { class: "cb-h3" }, d.department),
        h("p", { class: "cb-muted" }, `${t.detail.stageLabel}: ${stageText(t, d.stage)}`),
        variant?.sessionId ? h("p", {}, h("a", { href: `/studio/s/${variant.sessionId}`, "aria-label": fill(t.detail.openSession, { department: d.department }) }, fill(t.detail.openSession, { department: d.department }))) : null,
        h("dl", { class: "st-pol-figures" },
          figure(t.detail.assigned, f.number(d.assigned)), figure(t.detail.completed, f.number(d.completed)),
          figure(t.detail.pending, f.number(d.pending)), figure(t.detail.overdue, f.number(d.overdue)), figure(t.detail.rate, f.percent(d.completionRate))),
        variant ? academyBlock(p, variant) : null);
    });
    return h("section", { "aria-labelledby": id }, h("h2", { id, class: "cb-h3" }, t.detail.tilesTitle), h("ul", { class: "st-pol-depts", role: "list" }, items));
  }

  // ---- a department's own academy (ADR 0141, amendment) -----------------------------------------------------------

  const IN_PROGRESS = ["queued", "provisioning", "transferring"];

  /** The academy of one department: ask for it (theme, accent), follow it, open it, retry or remove it. Refreshes itself while it is being made. */
  function academyBlock(p: PolicyDetail, variant: PolicyVariantRow): HTMLElement {
    const a = t.academy;
    const id = uid("pol-ac");
    let state: AcademyState | null = variant.academy ?? null;
    let confirming = false;
    let timer: number | undefined;
    const section = h("section", { class: "st-pol-academy", "aria-labelledby": id, "data-academy": variant.department });
    const result = resultLine();

    const statusText = (s: AcademyState): string => fill(a.state, { status: a.statuses[s.status] ?? s.status });
    const schedule = (): void => {
      window.clearTimeout(timer);
      if (state && IN_PROGRESS.includes(state.status) && section.isConnected !== false) timer = window.setTimeout(() => void refresh(), 4000);
    };
    async function refresh(): Promise<void> {
      try {
        const before = state?.status;
        state = (await client.academy(p.id, variant.id)).academy;
        if (state && state.status !== before) announce(fill(a.updated, { department: variant.department, status: a.statuses[state.status] ?? state.status }));
        render();
      } catch {
        schedule();
      }
    }

    function form(): HTMLElement[] {
      const theme = select([["", a.themeDefault], ...(p.academyThemes ?? []).map((x) => [x, x] as [string, string])], "", "academy-theme");
      const accent = h("input", { type: "text", name: "academy-accent", maxlength: "7", placeholder: "#C2552D", autocomplete: "off" }) as HTMLInputElement;
      const button = h("button", { type: "button", class: "cb-btn", "data-action": "create-academy" }, fill(a.create, { department: variant.department })) as HTMLButtonElement;
      button.addEventListener("click", () => {
        if (accent.value.trim() && !/^#[0-9a-fA-F]{6}$/.test(accent.value.trim())) return say(result, a.badAccent, "alert"), accent.focus();
        button.disabled = true;
        say(result, a.creating);
        client.createAcademy(p.id, variant.id, { theme: theme.value || undefined, accent: accent.value.trim() || undefined }).then(
          (answer) => {
            state = answer.academy;
            say(result, a.requested);
            render();
          },
          (error) => {
            button.disabled = false;
            say(result, apiMessage(error) ?? t.error, "alert");
          },
        );
      });
      return [field(a.theme, theme), field(a.accent, accent, a.accentHint), button];
    }

    function removal(s: AcademyState): HTMLElement {
      if (!confirming) {
        const open = h("button", { type: "button", class: "cb-btn cb-btn-small", "data-action": "remove-academy" }, a.remove) as HTMLButtonElement;
        open.addEventListener("click", () => {
          confirming = true;
          render();
          section.querySelector<HTMLElement>('[data-action="confirm-remove"]')?.focus();
        });
        return open;
      }
      const yes = h("button", { type: "button", class: "cb-btn cb-btn-small", "data-action": "confirm-remove" }, fill(a.confirm, { name: s.name })) as HTMLButtonElement;
      const no = h("button", { type: "button", class: "cb-btn cb-btn-small", "data-action": "keep-academy" }, a.cancel) as HTMLButtonElement;
      no.addEventListener("click", () => {
        confirming = false;
        render();
        section.querySelector<HTMLElement>('[data-action="remove-academy"]')?.focus();
      });
      yes.addEventListener("click", () => {
        yes.disabled = true;
        no.disabled = true;
        say(result, a.removing);
        client.removeAcademy(p.id, variant.id).then(
          () => {
            state = null;
            confirming = false;
            render();
            say(result, a.removed);
          },
          (error) => {
            yes.disabled = false;
            no.disabled = false;
            say(result, apiMessage(error) ?? t.error, "alert");
          },
        );
      });
      return h("div", { class: "st-pol-confirm", role: "group", "aria-label": a.remove }, h("p", { class: "cb-muted" }, a.confirmHint), yes, no);
    }

    function render(): void {
      const nodes: Array<HTMLElement | null> = [h("h4", { id, class: "cb-h3" }, a.title), h("p", { class: "cb-muted" }, a.intro)];
      if (state) {
        nodes.push(h("p", { "data-academy-state": state.status }, statusText(state)));
        if (state.status === "failed" && state.error) nodes.push(h("p", { class: "cb-error", role: "alert" }, fill(a.failed, { error: state.error })));
        if (state.status === "done") {
          nodes.push(h("ul", { class: "st-pol-links" },
            state.studioUrl ? h("li", {}, h("a", { href: state.studioUrl, "aria-label": `${a.open}: ${variant.department}` }, a.open)) : null,
            state.adminUrl ? h("li", {}, h("a", { href: state.adminUrl, "aria-label": `${a.openAdmin}: ${variant.department}` }, a.openAdmin)) : null,
            state.frontUrl ? h("li", {}, h("a", { href: state.frontUrl, "aria-label": `${a.openFront}: ${variant.department}` }, a.openFront)) : null));
          if (state.invited) nodes.push(h("p", { class: "cb-muted" }, a.invited));
        }
        if (p.academyAvailable && state.status === "failed") nodes.push(...form());
        if (p.academyAvailable && ["done", "failed"].includes(state.status)) nodes.push(removal(state));
      } else if (!p.academyAvailable) nodes.push(h("p", { class: "cb-muted" }, a.notAvailable));
      else if (!variant.sessionId) nodes.push(h("p", { class: "cb-muted" }, a.needsCourse));
      else nodes.push(...form());
      nodes.push(result);
      section.replaceChildren(...(nodes.filter(Boolean) as HTMLElement[]));
      schedule();
    }
    render();
    return section;
  }

  const figure = (label: string, value: string): HTMLElement => h("div", {}, h("dt", {}, label), h("dd", {}, value));

  function actionsSection(p: PolicyDetail): HTMLElement | null {
    const nodes: HTMLElement[] = [];
    const result = resultLine();
    if (canGenerate(p)) {
      const estimate = p.estimate ? f.cost(p.estimate.totalMicroUsd) : t.none;
      const button = h("button", { type: "button", class: "cb-btn cb-btn-primary", "data-action": "generate" }, t.detail.generate) as HTMLButtonElement;
      button.addEventListener("click", () => {
        button.disabled = true;
        say(result, t.detail.generating);
        client.generate(p.id).then(
          () => (say(result, t.detail.generated), void loadPolicy()),
          (error) => {
            button.disabled = false;
            say(result, apiMessage(error) ?? t.error, "alert");
          },
        );
      });
      nodes.push(h("div", {}, h("p", { class: "cb-muted" }, fill(t.detail.generateHint, { cost: estimate })), button));
    }
    if (canApproveFirst(p)) {
      const button = h("button", { type: "button", class: "cb-btn cb-btn-primary", "data-action": "approve-first" }, t.detail.approve) as HTMLButtonElement;
      const blockers = h("div", { "data-blockers": "" });
      button.addEventListener("click", () => {
        button.disabled = true;
        say(result, t.detail.approving);
        client.approve(p.id, 1).then(
          () => (say(result, t.detail.approved), void loadPolicy()),
          (error) => {
            button.disabled = false;
            const message = apiMessage(error) ?? t.error;
            const [first, ...rest] = message.split("\n");
            say(result, first ?? message, "alert");
            blockers.replaceChildren(...(rest.length ? [h("p", {}, t.detail.blockers), h("ul", {}, rest.map((line) => h("li", {}, line)))] : []));
          },
        );
      });
      nodes.push(h("div", {}, button, blockers));
    }
    if (nodes.length === 0) return null;
    return h("section", { class: "cb-card st-pol-actions", "aria-label": t.detail.departmentsTitle }, ...nodes, result);
  }

  function peopleSection(p: PolicyDetail, board: PolicyDashboard): HTMLElement {
    const id = uid("pol-people");
    const heading_ = heading(t.detail.peopleTitle, id);
    const departmentSelect = select([["", t.detail.all], ...p.variants.map((v) => [v.id, v.department] as [string, string])], filterVariant, "variant");
    const statusSelect = select([["", t.detail.all], ...Object.entries(t.detail.statuses).filter(([k]) => k !== "cancelled")], filterStatus, "status");
    const onFilter = (): void => {
      filterVariant = departmentSelect.value;
      filterStatus = statusSelect.value;
      page = 1;
      void loadPolicy(true);
    };
    departmentSelect.addEventListener("change", onFilter);
    statusSelect.addEventListener("change", onFilter);
    const filters = h("div", { class: "st-pol-filters" }, field(t.detail.filterDepartment, departmentSelect), field(t.detail.filterStatus, statusSelect),
      h("a", { class: "cb-btn cb-btn-small", href: client.exportPath(p.id, { variant: filterVariant || undefined, status: filterStatus || undefined }), download: "" }, t.detail.export));
    if (board.people.length === 0) return h("section", { "aria-labelledby": id }, heading_, filters, status(t.detail.noPeople));
    const rows = personRows(f, board.people);
    const table = dataTable({
      caption: t.detail.caption,
      scrollLabel: t.detail.scrollLabel,
      columns: [{ label: t.detail.colPerson }, { label: t.detail.colDepartment }, { label: t.detail.colStatus }, { label: t.detail.colDue }, { label: t.detail.colCourseVersion }, { label: t.detail.colPolicyVersion }, { label: t.detail.colCertificate }],
      rows: rows.map((r) => ({
        id: String(r.key),
        cells: [
          r.exam ? `${r.name} · ${t.detail.examTag}` : r.name,
          r.department, r.status, r.due, r.courseVersion, r.policyVersion,
          r.certificate ? h("span", {}, `${r.certificate.text} `, h("a", { href: r.certificate.verifyPath.replace("/api", ""), "aria-label": `${t.detail.verify}: ${r.name}` }, t.detail.verify)) : t.none,
        ],
      })),
    });
    const newer = h("button", { type: "button", class: "cb-btn cb-btn-small", "data-pager": "newer" }, t.detail.previous) as HTMLButtonElement;
    const older = h("button", { type: "button", class: "cb-btn cb-btn-small", "data-pager": "older" }, t.detail.next) as HTMLButtonElement;
    newer.disabled = board.currentPage <= 1;
    older.disabled = board.currentPage >= board.lastPage;
    newer.addEventListener("click", () => ((page = board.currentPage - 1), void loadPolicy(true)));
    older.addEventListener("click", () => ((page = board.currentPage + 1), void loadPolicy(true)));
    return h("section", { "aria-labelledby": id }, heading_, filters, table,
      h("nav", { class: "st-pol-pager", "aria-label": t.detail.caption }, newer, h("span", { class: "cb-muted", "data-page-status": "" }, fill(t.detail.pageStatus, { page: board.currentPage, last: board.lastPage, total: f.number(board.total) })), older));
  }

  function versionsSection(p: PolicyDetail): HTMLElement {
    const id = uid("pol-ver");
    const rows = versionRows(f, p.versions);
    const table = dataTable({
      caption: t.detail.versionsCaption,
      scrollLabel: t.detail.versionsScroll,
      columns: [{ label: t.detail.colVersion }, { label: t.detail.colState }, { label: t.detail.colClass }, { label: t.detail.colSummary }, { label: t.list.colReview }],
      rows: rows.map((r) => ({
        id: String(r.number),
        cells: [r.label, r.state, r.change, r.summary, r.reviewable ? h("a", { href: `/studio/policies/${p.id}/versions/${r.number}${suffix}`, "aria-label": r.reviewLabel }, r.reviewLabel) : t.none],
      })),
    });
    return h("section", { "aria-labelledby": id }, h("h2", { id, class: "cb-h3" }, t.detail.versionsTitle), table);
  }

  function uploadSection(p: PolicyDetail): HTMLElement | null {
    if (!canUploadChange(p)) return null;
    const result = resultLine();
    const file = h("input", { type: "file", name: "file", required: true, accept: ".md,.markdown,.txt,.pdf,.docx" }) as HTMLInputElement;
    const submit = h("button", { type: "submit", class: "cb-btn cb-btn-primary" }, t.detail.upload) as HTMLButtonElement;
    const form = h("form", { class: "cb-card st-pol-form", novalidate: true }, h("h2", { class: "cb-h3" }, t.detail.uploadTitle), h("p", { class: "cb-muted" }, t.detail.uploadHint), field(t.detail.uploadFile, file), submit, result);
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      const chosen = file.files?.[0];
      if (!chosen) return say(result, t.detail.needFile, "alert"), file.focus();
      submit.disabled = true;
      say(result, t.detail.uploading);
      client.submitVersion(p.id, chosen).then(
        (version) => {
          say(result, fill(t.detail.uploaded, { n: version.number }));
          window.location.assign(`/studio/policies/${p.id}/versions/${version.number}${suffix}`);
        },
        (error) => {
          submit.disabled = false;
          say(result, apiMessage(error) ?? t.error, "alert");
        },
      );
    });
    return form;
  }

  function renderPolicy(moved: boolean): void {
    if (!policy || !dashboard) return;
    body.setAttribute("aria-busy", "false");
    const p = policy;
    const peopleNode = peopleSection(p, dashboard);
    const summary = h("p", { class: "cb-muted" }, `${t.detail.inForce}: ${p.currentVersion === null ? t.detail.noneInForce : fill(t.list.version, { n: p.currentVersion })}. ${fill(t.detail.owner, { minor: p.minorDueDays, major: p.majorDueDays })}`);
    body.replaceChildren(summary, ...([actionsSection(p), departmentsSection(p, dashboard), peopleNode, versionsSection(p), uploadSection(p)].filter(Boolean) as HTMLElement[]));
    if (moved) {
      announce(fill(t.detail.announcePage, { page: dashboard.currentPage, last: dashboard.lastPage }));
      queueMicrotask(() => peopleNode.querySelector<HTMLElement>("h2")?.focus());
    }
  }

  // ---- one version ------------------------------------------------------------------------------------------------

  let chosenClass: "minor" | "major" | null = null;

  async function loadVersion(): Promise<void> {
    const mine = ++requestId;
    showLoading();
    try {
      const review = await client.version(policyId, versionNumber, chosenClass ?? undefined);
      if (mine !== requestId) return;
      renderVersion(review);
    } catch (error) {
      if (mine === requestId) failure(error, () => void loadVersion());
    }
  }

  function renderVersion(v: VersionReview): void {
    body.setAttribute("aria-busy", "false");
    const r = t.review;
    const nodes: HTMLElement[] = [h("p", { class: "cb-muted" }, `${r.state}: ${t.detail.versionStates[v.status] ?? v.status} · ${t.detail.classes[v.changeClass] ?? v.changeClass}`)];
    if (v.classReason) nodes.push(h("p", { class: "cb-muted" }, fill(r.classReason, { reason: v.classReason })));
    const sumId = uid("pol-sum");
    const sections = v.changes?.sections ?? [];
    nodes.push(h("section", { class: "cb-card", "aria-labelledby": sumId }, h("h2", { id: sumId, class: "cb-h3" }, r.summaryTitle), h("p", {}, v.summary ?? r.noSummary),
      sections.length ? dataTable({ caption: r.sectionsCaption, scrollLabel: r.sectionsScroll, columns: [{ label: r.colSection }, { label: r.colKind }], rows: sections.map((s, i) => ({ id: String(i), cells: [s.label, r.kinds[s.kind] ?? s.kind] })) }) : null));

    if (v.rollouts?.length) {
      const id = uid("pol-dep");
      nodes.push(h("section", { "aria-labelledby": id }, h("h2", { id, class: "cb-h3" }, r.departmentsTitle),
        dataTable({
          caption: r.deptCaption, scrollLabel: r.deptScroll,
          columns: [{ label: r.colDepartment }, { label: r.colRollout }, { label: r.colUndecided, numeric: true }, { label: r.colReview }],
          rows: v.rollouts.map((x) => ({
            id: x.id,
            cells: [x.department ?? t.none, x.error ? `${r.rollouts[x.status] ?? x.status}: ${x.error}` : (r.rollouts[x.status] ?? x.status), f.number(x.undecided),
              x.proposal ? h("a", { href: `/studio/s/${x.proposal.sessionId}/updates`, "aria-label": `${r.openUpdates}: ${x.department ?? ""}` }, r.openUpdates) : t.none],
          })),
        })));
    }
    if (isOpenVersion(v) && v.suggestion) nodes.push(decisionForm(v));
    else if (!isOpenVersion(v)) nodes.push(h("p", { class: "cb-muted", "data-state": "decided" }, fill(r.decided, { state: t.detail.versionStates[v.status] ?? v.status })));
    body.replaceChildren(...nodes);
  }

  function decisionForm(v: VersionReview): HTMLElement {
    const r = t.review;
    const suggested = v.suggestion!.class;
    chosenClass ??= v.status === "applying" && (v.changeClass === "minor" || v.changeClass === "major") ? v.changeClass : suggested;
    const result = resultLine();
    const radio = (value: "minor" | "major", label: string): HTMLElement => {
      const id = uid("pol-class");
      const input = h("input", { type: "radio", name: "change_class", value, id }) as HTMLInputElement;
      input.checked = chosenClass === value;
      input.addEventListener("change", () => {
        chosenClass = value;
        syncReason();
        void refreshPreflight();
      });
      return h("label", { class: "st-pol-radio", for: id }, input, h("span", {}, label));
    };
    const reason = h("textarea", { name: "reason", rows: "3", maxlength: "2000" }) as HTMLTextAreaElement;
    const reasonField = field(r.reason, reason, r.reasonHint);
    const syncReason = (): void => {
      const needed = lowersClass(suggested, chosenClass ?? suggested);
      reason.required = needed;
      reason.setAttribute("aria-required", String(needed));
    };
    syncReason();
    const overwrite = h("input", { type: "checkbox", name: "overwrite" }) as HTMLInputElement;
    const preflight = h("div", { "data-preflight": "" }, preflightTable(v));
    const approve = h("button", { type: "submit", class: "cb-btn cb-btn-primary", "data-action": "approve" }, fill(r.approve, { n: v.number })) as HTMLButtonElement;
    const blockers = h("div", { "data-blockers": "" }, blockerList(v.blockers));
    approve.disabled = false;

    async function refreshPreflight(): Promise<void> {
      try {
        const review = await client.version(policyId, versionNumber, chosenClass ?? undefined);
        preflight.replaceChildren(preflightTable(review));
        announce(r.preflightTitle);
      } catch {
        /* the table keeps its last answer */
      }
    }

    const form = h("form", { class: "cb-card st-pol-form", novalidate: true, "data-decision": "" },
      h("fieldset", { class: "st-pol-class" },
        h("legend", { class: "cb-h3" }, r.classTitle),
        h("p", { class: "cb-muted" }, fill(r.suggested, { class: t.detail.classes[suggested] ?? suggested })),
        v.suggestion!.reasons.length ? h("div", {}, h("p", { class: "cb-muted" }, r.reasonsTitle), h("ul", {}, v.suggestion!.reasons.map((x) => h("li", {}, x)))) : null,
        radio("minor", r.classMinor), radio("major", r.classMajor)),
      reasonField,
      h("label", { class: "st-pol-radio" }, overwrite, h("span", {}, r.overwrite)),
      h("section", { "aria-label": r.preflightTitle }, h("h2", { class: "cb-h3" }, r.preflightTitle), preflight),
      blockers, approve, result);
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      if (reason.required && !reason.value.trim()) return say(result, r.needReason, "alert"), reason.focus();
      approve.disabled = true;
      say(result, r.approving);
      client.approve(policyId, versionNumber, { change_class: chosenClass ?? undefined, reason: reason.value.trim() || undefined, overwrite: overwrite.checked }).then(
        () => (say(result, r.approved), void loadVersion()),
        (error) => {
          approve.disabled = false;
          const [first, ...rest] = (apiMessage(error) ?? t.error).split("\n");
          say(result, first ?? t.error, "alert");
          blockers.replaceChildren(blockerList(rest));
        },
      );
    });

    const rejectReason = h("textarea", { name: "reject-reason", rows: "3", maxlength: "2000", required: true }) as HTMLTextAreaElement;
    const rejectResult = resultLine();
    const rejectButton = h("button", { type: "submit", class: "cb-btn", "data-action": "reject" }, r.reject) as HTMLButtonElement;
    const rejectForm = h("form", { class: "cb-card st-pol-form", novalidate: true, "data-reject": "" }, h("h2", { class: "cb-h3" }, r.reject), field(r.rejectReason, rejectReason, r.rejectHint), rejectButton, rejectResult);
    rejectForm.addEventListener("submit", (event) => {
      event.preventDefault();
      if (!rejectReason.value.trim()) return say(rejectResult, r.needReason, "alert"), rejectReason.focus();
      rejectButton.disabled = true;
      say(rejectResult, r.rejecting);
      client.reject(policyId, versionNumber, rejectReason.value.trim()).then(
        () => (say(rejectResult, r.rejected), void loadVersion()),
        (error) => {
          rejectButton.disabled = false;
          say(rejectResult, apiMessage(error) ?? t.error, "alert");
        },
      );
    });
    return h("div", { class: "st-pol-decisions" }, form, v.status === "in_review" ? rejectForm : null);
  }

  function blockerList(lines: string[]): HTMLElement | string {
    const list = lines.map((l) => l.trim()).filter(Boolean);
    return list.length ? h("div", {}, h("p", {}, t.review.blockers), h("ul", {}, list.map((line) => h("li", {}, line)))) : "";
  }

  function preflightTable(v: VersionReview): HTMLElement {
    const r = t.review;
    const rows = v.preflight?.departments ?? [];
    return dataTable({
      caption: r.preflightCaption,
      scrollLabel: r.preflightScroll,
      columns: [{ label: r.colDepartment }, { label: r.colRevoke, numeric: true }, { label: r.colReassign, numeric: true }, { label: r.colAssigns }],
      rows: rows.map((d) => ({ id: d.id, cells: [d.department, f.number(d.certificatesToRevoke), f.number(d.peopleToReassign), d.affected ? (r.assigns[d.assigns] ?? d.assigns) : r.notAffected] })),
    });
  }

  async function load(): Promise<void> {
    if (view === "list") return loadList();
    if (view === "create") return mountCreate();
    if (view === "version") return loadVersion();
    return loadPolicy();
  }

  void load();
}

const root = typeof document === "undefined" ? null : document.querySelector<HTMLElement>("[data-policies-view]");
if (root) mountPolicies(root);
