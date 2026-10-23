/**
 * Style page (/studio/style): upload a style guide, review the proposed style profile (values with the passages they
 * cite, what was dropped, the changes against the approved profile), edit it, and have an admin approve it (ADR 0196, SG-2).
 * Theme values are never applied here: the page links to the admin theme screen, which shows them as a diff to accept.
 * Every state is text (no meaning by colour alone), results are announced, and nothing animates under reduced motion.
 */
import { h, uid, usd } from "@ulams/ui/builder/dom.ts";
import { dataTable } from "@ulams/ui/builder/insights-view.ts";
import { announce } from "./common.ts";
import { fill, type StyleLocale } from "./style-i18n.ts";
import {
  changeRows,
  checkGroups,
  type CheckReport,
  hasTheme,
  profileRows,
  styleFormatterFor,
  themeLinkFor,
  valueSections,
  withEdits,
  withoutItem,
  type StyleProfile,
  type StyleValues,
  type ValueItem,
} from "./style-model.ts";

const BASE = "/studio/api/style-profiles";
const LIVING = "/studio/api/living-course";

class StyleError extends Error {
  constructor(
    readonly status: number,
    message: string
  ) {
    super(message);
  }
}

async function call<T>(method: string, path: string, body?: unknown, base: string = BASE): Promise<T> {
  const form = body instanceof FormData;
  let response: Response;
  try {
    response = await fetch(`${base}${path}`, {
      method,
      headers: { Accept: "application/json", ...(body !== undefined && !form ? { "Content-Type": "application/json" } : {}) },
      ...(body !== undefined ? { body: form ? (body as FormData) : JSON.stringify(body) } : {}),
    });
  } catch {
    throw new StyleError(0, "");
  }
  const text = await response.text();
  let json: { data?: T; message?: string } = {};
  try {
    json = JSON.parse(text);
  } catch {
    // not JSON: keep the empty object
  }
  if (!response.ok) throw new StyleError(response.status, json.message ?? "");
  return json.data as T;
}

interface RestylePreview {
  state: string;
  elements?: number;
  findings?: number;
  skipped?: number;
  estimateMicroUsd?: number;
  proposal?: { id: string };
}

export function mountStyle(root: HTMLElement): void {
  const locale = (root.dataset.locale ?? "en") as StyleLocale;
  const adminUrl = root.dataset.adminUrl ?? "";
  const f = styleFormatterFor(locale);
  const t = f.t;

  let profiles: StyleProfile[] = [];
  let selected: StyleProfile | null = null;
  let requestId = 0;

  const status = h("p", { class: "cb-muted", role: "status", "data-status": "" });
  const alertBox = h("p", { class: "cb-error", role: "alert", "data-alert": "", hidden: true });
  const uploadBox = h("div", { class: "st-style-upload" });
  const listBox = h("div", { class: "st-style-list", "aria-live": "polite" });
  const detailBox = h("div", { class: "st-style-detail", "data-detail": "" });
  root.replaceChildren(uploadBox, status, alertBox, listBox, detailBox);

  const say = (text: string) => {
    alertBox.hidden = true;
    alertBox.textContent = "";
    status.textContent = text;
    announce(text);
  };
  const fail = (error: unknown, fallback: string) => {
    status.textContent = "";
    alertBox.hidden = false;
    alertBox.textContent = error instanceof StyleError && error.status === 403 ? t.forbidden : error instanceof StyleError && error.message ? error.message : fallback;
  };

  // ---- the upload form -------------------------------------------------------------------------------------------

  function renderUpload(): void {
    const fileId = uid("st-style-file");
    const nameId = uid("st-style-name");
    const hintId = uid("st-style-hint");
    const file = h("input", { type: "file", id: fileId, name: "file", accept: ".md,.markdown,.txt,.pdf,.docx", "aria-describedby": hintId, required: true }) as HTMLInputElement;
    const name = h("input", { type: "text", id: nameId, name: "name", maxlength: "255", autocomplete: "off" }) as HTMLInputElement;
    const submit = h("button", { type: "submit", class: "cb-btn cb-btn-primary" }, t.readButton) as HTMLButtonElement;
    const form = h("form", { class: "st-style-form", "data-upload": "", novalidate: true },
      h("h2", { class: "cb-h3" }, t.uploadTitle),
      h("p", { class: "cb-muted", id: hintId }, t.uploadHint),
      h("p", {}, h("label", { for: fileId }, t.fileLabel), h("br"), file),
      h("p", {}, h("label", { for: nameId }, t.nameLabel), h("br"), name),
      h("p", {}, submit));
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      const chosen = file.files?.[0];
      if (!chosen) return fail(new StyleError(0, t.needFile), t.needFile);
      const body = new FormData();
      body.append("file", chosen, chosen.name);
      if (name.value.trim()) body.append("name", name.value.trim());
      submit.disabled = true;
      say(t.reading);
      call<StyleProfile>("POST", "", body)
        .then(async (created) => {
          form.reset();
          await loadList();
          show(created);
          say(t.readDone);
        })
        .catch((error) => fail(error, t.error))
        .finally(() => (submit.disabled = false));
    });
    uploadBox.replaceChildren(form);
  }

  // ---- the list --------------------------------------------------------------------------------------------------

  function renderList(): void {
    const heading = h("h2", { class: "cb-h3" }, t.listTitle);
    if (profiles.length === 0) {
      listBox.replaceChildren(heading, h("p", { class: "cb-muted", "data-empty": "" }, t.listEmpty));
      return;
    }
    const rows = profileRows(f, profiles);
    const table = dataTable({
      caption: t.caption,
      scrollLabel: t.scrollLabel,
      columns: [{ label: t.colName }, { label: t.colStatus }, { label: t.colScope }, { label: t.colCreated }, { label: t.colAction }],
      rows: rows.map((r) => ({
        id: r.id,
        cells: [
          r.name,
          r.status,
          r.scope,
          r.created,
          h("button", { type: "button", class: "cb-btn cb-btn-small", "data-review": r.id, "aria-label": fill(t.reviewLabel, { name: r.name }), "aria-pressed": selected?.id === r.id ? "true" : "false" }, t.review),
        ],
      })),
    });
    table.addEventListener("click", (event) => {
      const button = (event.target as HTMLElement).closest<HTMLButtonElement>("button[data-review]");
      if (button) void open(button.dataset.review ?? "");
    });
    listBox.replaceChildren(heading, table);
  }

  async function loadList(): Promise<void> {
    const mine = ++requestId;
    try {
      const data = await call<StyleProfile[]>("GET", "");
      if (mine !== requestId) return;
      profiles = data;
      status.textContent = "";
      renderList();
    } catch (error) {
      if (mine !== requestId) return;
      const again = h("button", { type: "button", class: "cb-btn cb-btn-small" }, t.retry);
      again.addEventListener("click", () => void loadList());
      listBox.replaceChildren(h("div", { class: "cb-card", "data-state": "error" }, h("p", { class: "cb-error", role: "alert" }, error instanceof StyleError && error.status === 403 ? t.forbidden : t.error), again));
    }
  }

  async function open(id: string): Promise<void> {
    detailBox.replaceChildren(h("p", { class: "cb-muted", role: "status" }, t.loadingDetail));
    try {
      show(await call<StyleProfile>("GET", `/${id}`));
    } catch (error) {
      detailBox.replaceChildren();
      fail(error, t.error);
    }
  }

  // ---- the detail ------------------------------------------------------------------------------------------------

  function citationButtons(item: { citations: Array<{ id: string; label: string }>; own: boolean }, region: HTMLElement, from: StyleProfile | null = selected): HTMLElement {
    if (item.own) return h("span", { class: "cb-muted st-style-own" }, t.ownValue);
    return h("span", { class: "st-style-cites" },
      h("span", { class: "cb-muted" }, `${t.citedBy} `),
      item.citations.map((c) => {
        const passageId = uid("st-style-passage");
        const passage = h("blockquote", { class: "st-style-passage", id: passageId, hidden: true }, from?.fragments?.[c.id]?.preview ?? t.noPassage);
        const button = h("button", { type: "button", class: "cb-btn cb-btn-small st-style-cite", "aria-expanded": "false", "aria-controls": passageId, "aria-label": fill(t.showPassage, { label: c.label }), "data-cite": c.id }, c.label) as HTMLButtonElement;
        button.addEventListener("click", () => {
          const open = passage.hidden;
          passage.hidden = !open;
          button.setAttribute("aria-expanded", String(open));
          button.setAttribute("aria-label", fill(open ? t.hidePassage : t.showPassage, { label: c.label }));
        });
        region.append(passage);
        return button;
      }));
  }

  function valueItem(p: StyleProfile, item: ValueItem, editable: boolean): HTMLElement {
    const passages = h("div", { class: "st-style-passages" });
    const row = h("li", { class: "st-style-value", "data-value": item.key }, h("span", { class: "st-style-text" }, item.text), " ", citationButtons(item, passages));
    if (editable && item.remove) {
      const remove = h("button", { type: "button", class: "cb-btn cb-btn-small", "data-remove": item.key, "aria-label": fill(t.removeItem, { item: item.name }) }, "×");
      remove.addEventListener("click", () => {
        draft = withoutItem(draft ?? p.profile, item.remove!);
        say(t.removed);
        row.remove();
      });
      row.append(" ", remove);
    }
    row.append(passages);
    return row;
  }

  /** The values being edited (removals and edits not yet saved); null when nothing is pending. */
  let draft: StyleValues | null = null;

  function show(p: StyleProfile): void {
    selected = p;
    draft = null;
    renderList();
    const editable = p.status === "suggested";
    const headingId = uid("st-style-h");
    const heading = h("h2", { id: headingId, class: "cb-h3", tabindex: "-1" }, fill(t.detailTitle, { name: p.name }));
    const parts: Array<Node | null> = [heading];

    parts.push(h("p", { "data-status-line": "" }, t.statuses[p.status] ?? p.status, p.approvedAt ? ` ${fill(t.approvedLine, { date: f.date(p.approvedAt) })}` : ""));
    if (p.status === "approved") parts.push(h("p", { class: "cb-muted" }, t.approvedReadOnly));
    if (p.status === "superseded") parts.push(h("p", { class: "cb-muted" }, t.supersededReadOnly));

    if (p.warnings.length) {
      parts.push(h("section", { class: "st-style-warnings", "aria-labelledby": `${headingId}-w`, "data-warnings": "" },
        h("h3", { id: `${headingId}-w`, class: "cb-h3" }, t.warningsTitle), h("p", { class: "cb-muted" }, t.warningsIntro), h("ul", {}, p.warnings.map((w) => h("li", {}, w)))));
    }

    // changes against the approved profile: a word and a symbol, never colour alone
    if (p.status !== "approved") {
      const changes = changeRows(f, p);
      parts.push(h("section", { class: "st-style-changes", "aria-labelledby": `${headingId}-c`, "data-changes": "" },
        h("h3", { id: `${headingId}-c`, class: "cb-h3" }, t.changesTitle),
        h("p", { class: "cb-muted" }, p.current ? t.changesIntro : t.noCurrent),
        changes.length === 0
          ? h("p", { class: "cb-muted" }, t.changesNone)
          : h("ul", { class: "st-style-diff" }, changes.map((c) => {
              const region = h("div", { class: "st-style-passages" });
              return h("li", { class: "st-style-change", "data-change": c.key, "data-kind": c.kind },
                h("span", { class: "st-style-kind" }, h("span", { "aria-hidden": "true" }, c.symbol), " ", c.kindLabel), ": ", h("strong", {}, c.label),
                c.before !== null ? h("span", {}, ` (${t.changeFrom}: ${c.before})`) : null,
                c.after !== null ? h("span", {}, ` ${t.changeTo}: ${c.after}`) : null,
                " ", c.citations.length ? citationButtons({ citations: c.citations, own: false }, region) : null, region);
            }))));
    }

    // the values with their citations
    const sections = valueSections(f, p);
    parts.push(h("section", { class: "st-style-values", "aria-labelledby": `${headingId}-v`, "data-values": "" },
      h("h3", { id: `${headingId}-v`, class: "cb-h3" }, t.valuesTitle),
      sections.length === 0
        ? h("p", { class: "cb-muted" }, t.valuesEmpty)
        : sections.map((s) => h("div", { class: "st-style-part", "data-part": s.key }, h("h4", { class: "cb-h3" }, s.label), h("ul", {}, s.items.map((i) => valueItem(p, i, editable)))))));

    // the theme part goes to the theme screen as a diff, never straight to the academy
    if (hasTheme(p)) {
      const link = themeLinkFor(adminUrl, p.id);
      parts.push(h("section", { class: "st-style-theme", "aria-labelledby": `${headingId}-t`, "data-theme": "" },
        h("h3", { id: `${headingId}-t`, class: "cb-h3" }, t.themeTitle), h("p", {}, t.themeText),
        link ? h("a", { class: "cb-btn", href: link }, t.themeLink) : h("p", { class: "cb-muted" }, t.themeNoAdmin)));
    }

    parts.push(checkSection(p, headingId));
    if (p.status === "approved") parts.push(restyleSection(p, headingId));
    if (editable) parts.push(editForm(p));

    const actions = h("div", { class: "st-style-actions" });
    if (editable) actions.append(approveButton(p), deleteControl(p));
    else if (p.status === "approved") actions.append(reviseButton(p));
    else actions.append(deleteControl(p));
    parts.push(actions);
    if (editable) parts.push(h("p", { class: "cb-muted" }, t.approveHint));

    detailBox.replaceChildren(...(parts.filter(Boolean) as Node[]));
    heading.focus();
  }

  /** The style check of one course against this profile: read-only, so it works for a suggestion too. */
  function checkSection(p: StyleProfile, headingId: string): HTMLElement {
    const courseId = uid("st-style-course");
    const input = h("input", { type: "number", id: courseId, min: "1", step: "1", inputmode: "numeric", "aria-describedby": `${courseId}-h` }) as HTMLInputElement;
    const button = h("button", { type: "submit", class: "cb-btn" }, t.checkButton) as HTMLButtonElement;
    const result = h("div", { class: "st-style-check-result", "data-check-result": "", "aria-live": "polite" });
    const form = h("form", { class: "st-style-check", "data-check": "", novalidate: true },
      h("p", { class: "cb-muted" }, t.checkIntro),
      h("p", {}, h("label", { for: courseId }, t.checkCourseLabel), h("br"), input, h("br"), h("span", { id: `${courseId}-h`, class: "cb-muted" }, t.checkCourseHint)),
      h("p", {}, button), result);
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      const course = Number.parseInt(input.value, 10);
      if (!Number.isInteger(course) || course < 1) {
        result.replaceChildren(h("p", { class: "cb-error", role: "alert" }, t.checkNeedCourse));
        return;
      }
      button.disabled = true;
      result.replaceChildren(h("p", { class: "cb-muted", role: "status" }, t.checking));
      call<CheckReport>("GET", `/${p.id}/check?course=${course}`)
        .then((report) => {
          renderReport(result, p, report);
          const summary = fill(t.checkSummary, { blocking: report.counts.blocking, warnings: report.counts.warnings, hints: report.counts.hints });
          announce(summary);
        })
        .catch((error) => {
          const text = error instanceof StyleError && error.status === 404 ? t.checkNotFound : error instanceof StyleError && error.status === 403 ? t.forbidden : error instanceof StyleError && error.message ? error.message : t.error;
          result.replaceChildren(h("p", { class: "cb-error", role: "alert" }, text));
        })
        .finally(() => (button.disabled = false));
    });
    return h("section", { class: "st-style-checkbox", "aria-labelledby": `${headingId}-k` }, h("h3", { id: `${headingId}-k`, class: "cb-h3" }, t.checkTitle), form);
  }

  function renderReport(box: HTMLElement, p: StyleProfile, report: CheckReport): void {
    const groups = checkGroups(f, report);
    const summary = fill(t.checkSummary, { blocking: report.counts.blocking, warnings: report.counts.warnings, hints: report.counts.hints });
    const nodes: Node[] = [h("p", { "data-check-summary": "" }, summary)];
    if (groups.length === 0) nodes.push(h("p", { class: "cb-muted" }, t.checkClean));
    if (report.blocksPublishing) nodes.push(h("p", { class: "cb-error", "data-blocks": "" }, t.checkBlocksPublishing));
    nodes.push(
      ...groups.map((g) =>
        h("div", { class: "st-style-findings", "data-findings": g.key },
          h("h4", { class: "cb-h3" }, `${g.title}: ${g.findings.length}`),
          h("ul", {}, g.findings.map((finding) => {
            const passages = h("div", { class: "st-style-passages" });
            const own = { citations: finding.citations, own: finding.citations.length === 0 };
            return h("li", { class: "st-style-finding", "data-code": finding.code }, h("span", {}, finding.message), finding.quote ? h("q", { class: "cb-muted" }, ` ${finding.quote}`) : null, " ",
              finding.citations.length ? citationButtons(own, passages, p) : null, passages);
          })))));
    box.replaceChildren(...nodes);
  }

  /** A restyle of a course for this approved profile (ADR 0196): preview what it would rewrite, then create the proposal. Never automatic. */
  function restyleSection(p: StyleProfile, headingId: string): HTMLElement {
    const courseId = uid("st-style-restyle-course");
    const input = h("input", { type: "number", id: courseId, min: "1", step: "1", inputmode: "numeric" }) as HTMLInputElement;
    const button = h("button", { type: "submit", class: "cb-btn" }, t.restyleButton) as HTMLButtonElement;
    const result = h("div", { class: "st-style-restyle-result", "data-restyle-result": "", "aria-live": "polite" });
    const form = h("form", { class: "st-style-restyle", "data-restyle": "", novalidate: true },
      h("p", { class: "cb-muted" }, t.restyleIntro),
      h("p", {}, h("label", { for: courseId }, t.checkCourseLabel), h("br"), input),
      h("p", {}, button), result);
    const proposalLink = (sessionId: string, proposalId: string) => h("a", { class: "cb-btn cb-btn-primary", href: `/studio/s/${sessionId}/updates/${proposalId}?lang=${locale}`, "data-restyle-link": "" }, t.restyleOpen);
    const problem = (error: unknown) => result.replaceChildren(h("p", { class: "cb-error", role: "alert" }, error instanceof StyleError && error.status === 404 ? t.checkNotFound : error instanceof StyleError && error.status === 403 ? t.forbidden : error instanceof StyleError && error.message ? error.message : t.error));
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      const course = Number.parseInt(input.value, 10);
      if (!Number.isInteger(course) || course < 1) {
        result.replaceChildren(h("p", { class: "cb-error", role: "alert" }, t.checkNeedCourse));
        return;
      }
      button.disabled = true;
      result.replaceChildren(h("p", { class: "cb-muted", role: "status" }, t.restyling));
      call<CheckReport>("GET", `/${p.id}/check?course=${course}`)
        .then(async (report) => {
          const sessionId = (report.course as { sessionId?: string }).sessionId ?? "";
          const preview = await call<RestylePreview>("POST", `/sessions/${sessionId}/style-proposals`, { styleProfileId: p.id }, LIVING);
          if (preview.state === "kept_existing" && preview.proposal) {
            result.replaceChildren(h("p", {}, t.restyleKept), proposalLink(sessionId, preview.proposal.id));
            return;
          }
          const create = h("button", { type: "button", class: "cb-btn cb-btn-primary", "data-restyle-create": "" }, t.restyleCreate) as HTMLButtonElement;
          create.addEventListener("click", () => {
            create.disabled = true;
            result.querySelector("[data-restyle-status]")?.replaceChildren(t.restyleCreating);
            call<RestylePreview>("POST", `/sessions/${sessionId}/style-proposals`, { styleProfileId: p.id, confirmEstimate: true }, LIVING)
              .then((created) => {
                const done: Node[] = [h("p", { role: "status", "data-restyle-done": "" }, t.restyleCreated)];
                if (created.proposal) done.push(proposalLink(sessionId, created.proposal.id));
                result.replaceChildren(...done);
                announce(t.restyleCreated);
              })
              .catch((error) => {
                create.disabled = false;
                problem(error);
              });
          });
          const nodes: Node[] = [h("p", { "data-restyle-preview": "" }, fill(t.restylePreview, { elements: preview.elements ?? 0, findings: preview.findings ?? 0, cost: usd(preview.estimateMicroUsd ?? 0) }))];
          if ((preview.skipped ?? 0) > 0) nodes.push(h("p", { class: "cb-muted" }, fill(t.restyleSkipped, { skipped: preview.skipped ?? 0 })));
          nodes.push(h("p", { class: "cb-muted", role: "status", "data-restyle-status": "" }), create);
          result.replaceChildren(...nodes);
          announce(fill(t.restylePreview, { elements: preview.elements ?? 0, findings: preview.findings ?? 0, cost: usd(preview.estimateMicroUsd ?? 0) }));
        })
        .catch(problem)
        .finally(() => (button.disabled = false));
    });
    return h("section", { class: "st-style-restylebox", "aria-labelledby": `${headingId}-r` }, h("h3", { id: `${headingId}-r`, class: "cb-h3" }, t.restyleTitle), form);
  }

  function editForm(p: StyleProfile): HTMLElement {
    const v = p.profile;
    const nameId = uid("st-style-n");
    const personId = uid("st-style-p");
    const formId = uid("st-style-f");
    const lenId = uid("st-style-l");
    const blockId = uid("st-style-b");
    const jsonId = uid("st-style-j");
    const name = h("input", { type: "text", id: nameId, value: p.name, maxlength: "255", autocomplete: "off" }) as HTMLInputElement;
    const person = h("select", { id: personId }, h("option", { value: "" }, t.personNone), ["you", "we", "neutral"].map((k) => h("option", { value: k }, t.persons[k]))) as HTMLSelectElement;
    person.value = v.voice?.person ?? "";
    const formality = h("input", { type: "number", id: formId, min: "1", max: "5", step: "1", "aria-describedby": `${formId}-h`, value: v.voice?.formality ?? "" }) as HTMLInputElement;
    const length = h("input", { type: "number", id: lenId, min: "5", max: "60", step: "1", "aria-describedby": `${lenId}-h`, value: v.voice?.sentenceLength ?? "" }) as HTMLInputElement;
    const blocking = h("input", { type: "checkbox", id: blockId, "aria-describedby": `${blockId}-h` }) as HTMLInputElement;
    blocking.checked = v.banned?.blocking === true;
    const json = h("textarea", { id: jsonId, rows: "10", spellcheck: "false", "aria-describedby": `${jsonId}-h`, class: "st-style-json" }) as HTMLTextAreaElement;
    json.value = JSON.stringify(v, null, 2);
    const save = h("button", { type: "submit", class: "cb-btn cb-btn-primary" }, t.save) as HTMLButtonElement;

    const form = h("form", { class: "st-style-edit", "data-edit": "", novalidate: true },
      h("h3", { class: "cb-h3" }, t.editTitle), h("p", { class: "cb-muted" }, t.editIntro),
      h("p", {}, h("label", { for: nameId }, t.nameField), h("br"), name),
      h("p", {}, h("label", { for: personId }, t.personField), h("br"), person),
      h("p", {}, h("label", { for: formId }, t.formalityField), h("br"), formality, h("br"), h("span", { id: `${formId}-h`, class: "cb-muted" }, t.formalityHint)),
      h("p", {}, h("label", { for: lenId }, t.sentenceField), h("br"), length, h("br"), h("span", { id: `${lenId}-h`, class: "cb-muted" }, t.sentenceHint)),
      v.banned ? h("p", {}, blocking, " ", h("label", { for: blockId }, t.blockingField), h("br"), h("span", { id: `${blockId}-h`, class: "cb-muted" }, t.blockingHint)) : null,
      h("details", { class: "st-style-advanced" }, h("summary", {}, t.jsonSummary), h("p", {}, h("label", { for: jsonId }, t.jsonLabel), h("br"), json, h("br"), h("span", { id: `${jsonId}-h`, class: "cb-muted" }, t.jsonHint))),
      h("p", {}, save));
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      const advanced = form.querySelector("details")?.open === true;
      let values: StyleValues;
      if (advanced) {
        try {
          values = JSON.parse(json.value) as StyleValues;
        } catch {
          return fail(new StyleError(0, t.jsonInvalid), t.jsonInvalid);
        }
      } else {
        values = withEdits(draft ?? v, { person: person.value, formality: formality.value, sentenceLength: length.value, blocking: blocking.checked });
      }
      save.disabled = true;
      say(t.saving);
      call<StyleProfile>("PATCH", `/${p.id}`, { name: name.value.trim() || p.name, profile: values })
        .then(async (updated) => {
          await loadList();
          show(updated);
          say(t.saved);
        })
        .catch((error) => fail(error, t.error))
        .finally(() => (save.disabled = false));
    });
    return form;
  }

  function approveButton(p: StyleProfile): HTMLElement {
    const button = h("button", { type: "button", class: "cb-btn cb-btn-primary", "data-approve": "" }, t.approve) as HTMLButtonElement;
    button.addEventListener("click", () => {
      button.disabled = true;
      say(t.approving);
      call<StyleProfile>("POST", `/${p.id}/approve`)
        .then(async (approved) => {
          await loadList();
          show(approved);
          say(t.approvedDone);
        })
        .catch((error) => fail(error, t.error))
        .finally(() => (button.disabled = false));
    });
    return button;
  }

  function reviseButton(p: StyleProfile): HTMLElement {
    const button = h("button", { type: "button", class: "cb-btn", "data-revise": "" }, t.revise) as HTMLButtonElement;
    button.addEventListener("click", () => {
      button.disabled = true;
      call<StyleProfile>("POST", "", { from: p.id, name: p.name })
        .then(async (revision) => {
          await loadList();
          show(revision);
          say(t.revised);
        })
        .catch((error) => fail(error, t.error))
        .finally(() => (button.disabled = false));
    });
    return button;
  }

  /** Delete takes two steps (a button, then a confirmation) so one slip never removes a profile. */
  function deleteControl(p: StyleProfile): HTMLElement {
    const box = h("span", { class: "st-style-delete" });
    const first = h("button", { type: "button", class: "cb-btn", "data-delete": "" }, t.deleteButton) as HTMLButtonElement;
    first.addEventListener("click", () => {
      const yes = h("button", { type: "button", class: "cb-btn", "data-delete-confirm": "" }, t.deleteConfirm) as HTMLButtonElement;
      const no = h("button", { type: "button", class: "cb-btn" }, t.deleteCancel);
      no.addEventListener("click", () => {
        box.replaceChildren(first);
        first.focus();
      });
      yes.addEventListener("click", () => {
        yes.disabled = true;
        call<unknown>("DELETE", `/${p.id}`)
          .then(async () => {
            selected = null;
            detailBox.replaceChildren();
            await loadList();
            say(t.deleted);
          })
          .catch((error) => {
            yes.disabled = false;
            fail(error, t.error);
          });
      });
      box.replaceChildren(yes, " ", no);
      yes.focus();
    });
    box.append(first);
    return box;
  }

  renderUpload();
  status.textContent = t.loading;
  void loadList();
}

for (const root of document.querySelectorAll<HTMLElement>("[data-studio-style]")) mountStyle(root);
