/**
 * Course Compare page (/studio/courses/:id/compare?from=&to=): what changed between two versions or releases of a course,
 * from the semantic diff of the compare API through the studio BFF. A summary and what learners would notice come first,
 * then the changed elements as a tree (arrow keys, one tab stop) with a detail panel: word-level text changes inline or
 * side by side, the citations behind them, quiz answers and the progress consequence. Added, removed, changed and moved are
 * always words with a symbol, never colour alone; selecting an element moves focus to its detail; nothing animates under
 * reduced motion. The same screen compares a variant with its base: the pair picker lists every branch of the session.
 */
import {
  ApiError,
  createCourseVersionsClient,
  type ChangeField,
  type CompareChangeKind,
  type CourseCompare,
  type CourseVersions,
  type CourseVersionsClient,
  type ElementChange,
  type QuizChange,
} from "@ulams/sdk";
import { h, sr, uid } from "@ulams/ui/builder/dom.ts";
import { wordDiff, wordDiffSides, type WordDiffLabels } from "@ulams/ui/builder/word-diff.ts";
import { announce } from "./common.ts";
import { fill, type CompareLocale } from "./compare-i18n.ts";
import {
  breadcrumb,
  buildTree,
  citationRows,
  compareFormatterFor,
  comparePath,
  elementName,
  elementTitle,
  fieldName,
  fieldsToShow,
  fieldValue,
  impactFigures,
  isEmpty,
  isTextField,
  KIND_SYMBOL,
  kindsOf,
  kindsPresent,
  quizFor,
  quizHeadline,
  sideLine,
  summaryFigures,
  typesPresent,
  variantPair,
  versionOptionLabel,
  visibleIds,
  walk,
  type CompareFormatter,
  type TreeNode,
} from "./compare-model.ts";

export const compareClient = (): CourseVersionsClient => createCourseVersionsClient({ baseUrl: "/studio/api", prefix: "/course-versions" });

type View = "inline" | "side";

export function mountCompare(root: HTMLElement, client: CourseVersionsClient = compareClient()): void {
  const courseId = Number(root.dataset.course);
  const locale = (root.dataset.locale ?? "en") as CompareLocale;
  const from = root.dataset.from ?? "";
  const to = root.dataset.to ?? "";
  const f = compareFormatterFor(locale);
  const t = f.t;
  const diffLabels: WordDiffLabels = { added: t.srAdded, removed: t.srRemoved };

  const pickHost = h("div", { class: "st-cmp-pick-host" });
  const resultHost = h("div", { class: "st-cmp-result", "aria-live": "polite" });
  root.replaceChildren(pickHost, resultHost);

  const status = (text: string, role: "status" | "alert" = "status"): HTMLElement => h("p", { class: role === "alert" ? "cb-error" : "cb-muted", role }, text);
  const section = (title: string, ...children: Array<Node | null>): HTMLElement => {
    const id = uid("cmp-h");
    return h("section", { class: "cb-card st-cmp-section", "aria-labelledby": id }, h("h2", { id, class: "cb-h3" }, title), children);
  };

  function message(host: HTMLElement, text: string, kind: "status" | "alert", state: string, retry?: () => void): void {
    host.setAttribute("aria-busy", "false");
    const again = retry ? h("button", { type: "button", class: "cb-btn cb-btn-small", onclick: retry }, t.retry) : null;
    host.replaceChildren(h("div", { class: "cb-card", "data-state": state }, status(text, kind), again));
  }

  /** A failed request as the sentence the author needs. */
  function failure(error: unknown, retry: () => void, during: "versions" | "compare"): void {
    if (error instanceof ApiError && error.status === 404) return message(resultHost, during === "compare" ? t.notFound : t.notBuilt, "status", during === "compare" ? "not-found" : "not-built");
    if (error instanceof ApiError && error.status === 403) return message(resultHost, t.forbidden, "alert", "forbidden");
    message(resultHost, t.error, "alert", "error", retry);
  }

  // ---- the pair picker -------------------------------------------------------------------------------------------

  function showPicker(data: CourseVersions): void {
    const fromId = h("select", { id: uid("cmp-from"), class: "cb-select", name: "from" }) as HTMLSelectElement;
    const toId = h("select", { id: uid("cmp-to"), class: "cb-select", name: "to" }) as HTMLSelectElement;
    const valueOf = (wanted: string): string => data.versions.find((v) => v.id === wanted || v.releaseId === wanted)?.id ?? wanted;
    const options = (select: HTMLSelectElement, wanted: string): void => {
      const chosen = valueOf(wanted);
      const known = data.versions.some((v) => v.id === chosen);
      for (const v of data.versions) select.append(h("option", { value: v.id }, versionOptionLabel(f, v, data.variants)));
      if (wanted && !known) select.append(h("option", { value: wanted }, fill(t.otherId, { id: wanted.slice(0, 10) })));
      if (wanted) select.value = chosen;
    };
    options(fromId, from);
    options(toId, to);
    if (!from && !to) {
      // nothing asked for yet: suggest the latest release against the one before it
      const own = data.versions.filter((v) => v.variantId === data.variantId && v.releaseId !== null);
      if (own[0]) toId.value = own[0].id;
      if (own[1]) fromId.value = own[1].id;
    }
    const lang = h("input", { type: "hidden", name: "lang", value: locale });
    const swap = h("a", { class: "cb-btn", href: from && to ? comparePath(courseId, to, from, locale) : "#", "aria-disabled": from && to ? null : "true" }, t.swap);
    if (!(from && to)) swap.addEventListener("click", (e) => e.preventDefault());
    const pickId = uid("cmp-pick");
    const form = h("form", { class: "st-cmp-form", method: "get", action: `/studio/courses/${courseId}/compare` },
      h("div", { class: "st-field" }, h("label", { for: fromId.id }, t.fromLabel), fromId),
      h("div", { class: "st-field" }, h("label", { for: toId.id }, t.toLabel), toId),
      lang,
      h("div", { class: "st-cmp-actions" }, h("button", { type: "submit", class: "cb-btn cb-btn-primary" }, t.compareButton), swap));

    const variants = data.variants.filter((v) => v.status !== "retired");
    let variantForm: HTMLElement | null = null;
    if (variants.length) {
      // also a plain GET form: choosing a variant fills `from` (the base's latest) and `to` (the variant's latest)
      const select = h("select", { id: uid("cmp-variant"), class: "cb-select" }, variants.map((v) => h("option", { value: v.id }, v.label))) as HTMLSelectElement;
      const fromInput = h("input", { type: "hidden", name: "from", value: "" }) as HTMLInputElement;
      const toInput = h("input", { type: "hidden", name: "to", value: "" }) as HTMLInputElement;
      const note = h("p", { class: "cb-error", role: "alert", hidden: true }, t.variantNoVersions) as HTMLElement;
      const fillPair = (): boolean => {
        const pair = variantPair(data.versions, select.value);
        fromInput.value = pair?.from ?? "";
        toInput.value = pair?.to ?? "";
        return pair !== null;
      };
      fillPair();
      select.addEventListener("change", () => {
        note.hidden = true;
        fillPair();
      });
      variantForm = h("form", { class: "st-cmp-form st-cmp-variant", method: "get", action: `/studio/courses/${courseId}/compare`, "data-variant-form": "" },
        h("div", { class: "st-field" }, h("label", { for: select.id }, t.variantLabel), select), fromInput, toInput, h("input", { type: "hidden", name: "lang", value: locale }),
        h("div", { class: "st-cmp-actions" }, h("button", { type: "submit", class: "cb-btn" }, t.variantButton)), note);
      variantForm.addEventListener("submit", (event) => {
        if (!fillPair()) {
          event.preventDefault();
          note.hidden = false;
        }
      });
    }
    pickHost.replaceChildren(h("section", { class: "cb-card st-cmp-section", "aria-labelledby": pickId },
      h("h2", { id: pickId, class: "cb-h3" }, t.pickTitle), form, variantForm,
      h("a", { class: "st-cmp-back", href: `/studio/courses/${courseId}/versions?lang=${locale}` }, t.backToVersions)));
  }

  // ---- the comparison ----------------------------------------------------------------------------------------------

  function showCompare(data: CourseCompare): void {
    resultHost.setAttribute("aria-busy", "false");
    const sides = h("p", { class: "st-cmp-sides" },
      h("span", {}, h("strong", {}, `${t.sideFrom}: `), sideLine(f, data.from)), " ", h("span", { "aria-hidden": "true" }, "→"), " ",
      h("span", {}, h("strong", {}, `${t.sideTo}: `), sideLine(f, data.to)));
    if (data.from.versionId === data.to.versionId) return void resultHost.replaceChildren(sides, status(t.sameVersion));
    if (isEmpty(data)) {
      resultHost.replaceChildren(sides, h("div", { class: "cb-card", "data-state": "no-change" }, status(t.nothingChanged)));
      announce(fill(t.announceLoaded, { n: f.number(0) }));
      return;
    }

    const figures = summaryFigures(f, data.summary);
    const summary = section(t.summaryTitle,
      data.summary.first ? h("p", { class: "cb-muted" }, t.firstRelease) : null,
      h("dl", { class: "cb-figures", "aria-label": t.summaryTitle },
        figures.map((x) => h("div", { class: "cb-figure" }, h("dt", {}, x.label), h("dd", {}, x.value)))));
    const impact = section(t.impactTitle, h("p", { class: "cb-muted" }, t.impactIntro),
      h("dl", { class: "cb-figures", "aria-label": t.impactTitle },
        impactFigures(f, data.summary).map((x) => h("div", { class: "cb-figure" }, h("dt", {}, x.label), h("dd", {}, x.value)))));

    const tree = buildTree(data.changes);
    const byId = new Map<string, TreeNode>();
    walk(tree, (n) => byId.set(n.id, n));
    const total = data.changes.length;
    const filters = { kinds: new Set<CompareChangeKind>(), types: new Set<string>() } as { kinds: Set<CompareChangeKind>; types: Set<string> };
    let view: View = "inline";
    let selectedId: string | null = null;
    let currentId: string | null = null;
    const collapsed = new Set<string>();

    const treeHost = h("div", { class: "st-cmp-tree-host" });
    const detail = h("section", { class: "st-cmp-detail cb-card", "aria-labelledby": "cmp-detail-heading", "data-detail": "" });
    const filterHost = h("div", { class: "st-cmp-filters" });
    const viewHost = h("div", { class: "st-cmp-view", role: "group", "aria-label": t.viewLabel });

    const badge = (kind: CompareChangeKind): HTMLElement =>
      h("span", { class: `cb-badge cb-badge-${kind}`, "data-kind": kind }, h("span", { "aria-hidden": "true" }, KIND_SYMBOL[kind]), ` ${t.kinds[kind]}`);

    // quiz impact: one button per changed question, jumping to it in the tree
    const quizSection = section(t.quizTitle,
      data.quiz.length === 0
        ? h("p", { class: "cb-muted" }, t.quizNone)
        : h("ul", { class: "st-cmp-quiz-list" }, data.quiz.map((q) => {
            const node = byId.get(q.questionId);
            const label = `${quizName(f, q)}: ${quizHeadline(t, q)}`;
            return h("li", {}, node ? h("button", { type: "button", class: "cb-btn cb-btn-small", "data-quiz-jump": q.questionId, onclick: () => select(q.questionId, true) }, label) : label);
          })));

    // ---- filters and view ----
    function chip(label: string, pressed: boolean, on: () => void, attrs: Record<string, string> = {}): HTMLElement {
      return h("button", { type: "button", class: "cb-chip", "aria-pressed": pressed ? "true" : "false", onclick: on, ...attrs }, label);
    }
    function renderFilters(): void {
      const kindCounts = new Map<CompareChangeKind, number>();
      for (const c of data.changes) for (const k of kindsOf(c)) kindCounts.set(k, (kindCounts.get(k) ?? 0) + 1);
      const typeCounts = new Map<string, number>();
      for (const c of data.changes) typeCounts.set(c.type, (typeCounts.get(c.type) ?? 0) + 1);
      const kindId = uid("cmp-kinds");
      const typeId = uid("cmp-types");
      const active = filters.kinds.size > 0 || filters.types.size > 0;
      const clearButton = active ? h("button", { type: "button", class: "cb-btn cb-btn-small", "data-filter-clear": "", onclick: clearFilters }, t.clearFilters) : null;
      filterHost.replaceChildren(
        h("div", { class: "st-cmp-group", role: "group", "aria-labelledby": kindId },
          h("span", { id: kindId, class: "st-cmp-group-label" }, t.kindGroup),
          h("div", { class: "cb-chips" }, kindsPresent(data.changes).map((k) =>
            chip(`${KIND_SYMBOL[k]} ${t.kinds[k]} (${f.number(kindCounts.get(k) ?? 0)})`, filters.kinds.has(k), () => toggle(filters.kinds, k, `[data-filter-kind="${k}"]`), { "data-filter-kind": k })))),
        h("div", { class: "st-cmp-group", role: "group", "aria-labelledby": typeId },
          h("span", { id: typeId, class: "st-cmp-group-label" }, t.typeGroup),
          h("div", { class: "cb-chips" }, typesPresent(data.changes).map((type) =>
            chip(`${typeNoun(type)} (${f.number(typeCounts.get(type) ?? 0)})`, filters.types.has(type), () => toggle(filters.types, type, `[data-filter-type="${type}"]`), { "data-filter-type": type })))),
        ...(clearButton ? [clearButton] : []));
    }
    const typeNoun = (type: string): string => t.types[type] ?? type;
    function toggle<T>(set: Set<T>, value: T, again: string): void {
      if (set.has(value)) set.delete(value);
      else set.add(value);
      refresh(true, again);
    }
    function clearFilters(): void {
      filters.kinds.clear();
      filters.types.clear();
      refresh(true, "[data-filter-kind], [data-filter-type]");
    }
    function renderView(): void {
      viewHost.replaceChildren(
        h("span", { class: "st-cmp-group-label" }, t.viewLabel),
        h("div", { class: "cb-chips" },
          chip(t.viewInline, view === "inline", () => setView("inline"), { "data-view": "inline" }),
          chip(t.viewSide, view === "side", () => setView("side"), { "data-view": "side" })));
    }
    function setView(next: View): void {
      view = next;
      renderView();
      viewHost.querySelector<HTMLElement>(`[data-view="${next}"]`)?.focus();
      if (selectedId) renderDetail(byId.get(selectedId)!);
    }

    // ---- the tree ----
    function renderTree(): void {
      const { ids } = visibleIds(tree, filters);
      const list = h("ul", { class: "st-cmp-tree", role: "tree", "aria-label": t.treeLabel });
      if (ids.size === 0) {
        treeHost.replaceChildren(h("p", { class: "cb-muted", role: "status", "data-state": "no-match" }, t.noMatch));
        return;
      }
      const visibleRoots = tree.filter((n) => ids.has(n.id));
      const first = visibleRoots[0]?.id ?? null;
      if (!currentId || !ids.has(currentId)) currentId = selectedId && ids.has(selectedId) ? selectedId : first;
      const item = (n: TreeNode): HTMLElement => {
        const kids = n.children.filter((c) => ids.has(c.id));
        const open = !collapsed.has(n.id);
        const rowId = uid("cmp-row");
        const name = n.change ? elementTitle(t, n.change) : elementTitle(t, n);
        const badges = n.change ? kindsOf(n.change).map(badge) : [];
        const row = h("div", { class: `st-cmp-row${n.change ? "" : " st-cmp-context"}`, id: rowId, "data-row": n.id },
          kids.length ? h("span", { class: "st-cmp-chevron", "aria-hidden": "true", onclick: (e: Event) => { e.stopPropagation(); setOpen(n.id, !open); } }, open ? "▾" : "▸") : h("span", { class: "st-cmp-chevron", "aria-hidden": "true" }, "·"),
          h("span", { class: "st-cmp-name" }, name),
          badges.length ? h("span", { class: "st-cmp-badges" }, badges) : null);
        const li = h("li", {
          role: "treeitem", "aria-labelledby": rowId, "aria-level": n.depth, "aria-selected": n.id === selectedId ? "true" : "false",
          "aria-expanded": kids.length ? (open ? "true" : "false") : null, tabindex: n.id === currentId ? "0" : "-1", "data-node": n.id,
        }, row, kids.length ? h("ul", { role: "group", hidden: !open }, kids.map(item)) : null);
        row.addEventListener("click", () => select(n.id, true));
        return li;
      };
      list.append(...visibleRoots.map(item));
      list.addEventListener("keydown", onTreeKey);
      treeHost.replaceChildren(list);
    }
    function treeItems(): HTMLElement[] {
      return [...treeHost.querySelectorAll<HTMLElement>('[role="treeitem"]')].filter((el) => !el.parentElement?.closest("[hidden]"));
    }
    function focusItem(el: HTMLElement | undefined): void {
      if (!el) return;
      for (const other of treeHost.querySelectorAll<HTMLElement>('[role="treeitem"]')) other.tabIndex = other === el ? 0 : -1;
      currentId = el.dataset.node ?? null;
      el.focus();
    }
    function setOpen(id: string, open: boolean): void {
      if (open) collapsed.delete(id);
      else collapsed.add(id);
      const keep = id;
      renderTree();
      focusItem(treeHost.querySelector<HTMLElement>(`[data-node="${keep}"]`) ?? undefined);
    }
    function onTreeKey(event: KeyboardEvent): void {
      const target = (event.target as HTMLElement).closest<HTMLElement>('[role="treeitem"]');
      if (!target || event.altKey || event.ctrlKey || event.metaKey) return;
      const items = treeItems();
      const index = items.indexOf(target);
      const id = target.dataset.node!;
      const expandable = target.hasAttribute("aria-expanded");
      const open = target.getAttribute("aria-expanded") === "true";
      let handled = true;
      switch (event.key) {
        case "ArrowDown": focusItem(items[index + 1]); break;
        case "ArrowUp": focusItem(items[index - 1]); break;
        case "Home": focusItem(items[0]); break;
        case "End": focusItem(items[items.length - 1]); break;
        case "ArrowRight":
          if (expandable && !open) setOpen(id, true);
          else if (expandable) focusItem(items[index + 1]);
          break;
        case "ArrowLeft":
          if (expandable && open) setOpen(id, false);
          else {
            const parent = target.parentElement?.closest<HTMLElement>('[role="treeitem"]');
            if (parent) focusItem(parent);
          }
          break;
        case "Enter":
        case " ":
          select(id, true);
          break;
        default: handled = false;
      }
      if (handled) event.preventDefault();
    }

    // ---- the detail panel ----
    const text = (value: unknown): string => (value === null || value === undefined ? "" : String(value));

    function fieldBlock(change: ElementChange, field: ChangeField): HTMLElement {
      const title = fieldName(t, field);
      const id = uid("cmp-field");
      const body: Array<Node | null> = [];
      if (field.truncated && field.lineDiff) {
        const lines = (label: string, list: string[], mark: string): Node | null =>
          list.length ? h("div", {}, h("p", { class: "cb-eyebrow" }, label), h("ul", { class: "st-cmp-lines" }, list.map((l) => h("li", { class: `st-cmp-line st-cmp-line-${mark === "+" ? "added" : "removed"}` }, h("span", { "aria-hidden": "true" }, `${mark} `), sr(mark === "+" ? t.srAdded : t.srRemoved), l)))) : null;
        body.push(h("p", { class: "cb-muted cb-small" }, t.longField), lines(t.linesRemoved, field.lineDiff.removed, "−"), lines(t.linesAdded, field.lineDiff.added, "+"));
        if (field.lineDiff.omitted > 0) body.push(h("p", { class: "cb-muted cb-small" }, fill(t.linesOmitted, { n: f.number(field.lineDiff.omitted) })));
      } else if (change.kind === "added") {
        body.push(h("p", { class: "st-cmp-text" }, fieldValue(t, field.field, field.after)));
      } else if (change.kind === "removed") {
        body.push(h("p", { class: "st-cmp-text" }, fieldValue(t, field.field, field.before)));
      } else if (isTextField(field.field)) {
        const before = text(field.before);
        const after = text(field.after);
        if (view === "inline") body.push(h("p", { class: "st-cmp-text cb-change-text" }, wordDiff(before, after, diffLabels)));
        else {
          const sides = wordDiffSides(before, after, diffLabels);
          body.push(h("div", { class: "st-cmp-sides-grid" },
            h("div", { class: "st-cmp-side", role: "group", "aria-label": t.before }, h("p", { class: "cb-eyebrow" }, t.before), h("p", { class: "st-cmp-text" }, sides.before)),
            h("div", { class: "st-cmp-side", role: "group", "aria-label": t.after }, h("p", { class: "cb-eyebrow" }, t.after), h("p", { class: "st-cmp-text" }, sides.after))));
        }
      } else {
        body.push(h("dl", { class: "st-cmp-values" },
          h("div", {}, h("dt", {}, t.before), h("dd", {}, h("del", {}, sr(t.srRemoved), fieldValue(t, field.field, field.before)))),
          h("div", {}, h("dt", {}, t.after), h("dd", {}, h("ins", {}, sr(t.srAdded), fieldValue(t, field.field, field.after))))));
      }
      return h("div", { class: "st-cmp-field", role: "group", "aria-labelledby": id, "data-field": field.field }, h("h4", { id, class: "st-cmp-field-title" }, title), body);
    }

    function passage(label: string, p: { label: string; text: string; sourceRevision: { id: string; number: number | null } | null } | null): HTMLElement {
      return h("figure", { class: "st-cmp-passage" },
        h("figcaption", {}, h("strong", {}, label), p ? ` · ${p.label}${p.sourceRevision?.number != null ? ` · ${fill(t.revision, { n: f.number(p.sourceRevision.number) })}` : ""}` : null),
        h("blockquote", {}, p && p.text ? p.text : t.passageMissing));
    }

    function citationsPanel(change: ElementChange): HTMLElement | null {
      const rows = citationRows(change, data.fragments);
      if (!rows.length) return null;
      const id = uid("cmp-cit");
      return h("div", { class: "st-cmp-panel", role: "group", "aria-labelledby": id, "data-panel": "citations" }, h("h4", { id, class: "st-cmp-field-title" }, t.citationsPanel),
        h("ul", { class: "st-cmp-citations" }, rows.map((row) =>
          h("li", { "data-citation": row.kind },
            h("p", { class: "st-cmp-cit-kind" }, h("span", { "aria-hidden": "true" }, row.kind === "added" ? "+ " : row.kind === "removed" ? "− " : "↻ "), row.kind === "added" ? t.citAdded : row.kind === "removed" ? t.citRemoved : t.citRepointed),
            row.kind === "repointed"
              ? h("div", { class: "st-cmp-sides-grid" }, passage(t.passageBefore, row.before), passage(t.passageAfter, row.after))
              : passage(row.kind === "added" ? t.passageAfter : t.passageBefore, row.kind === "added" ? row.after : row.before)))));
    }

    function optionList(label: string, options: Array<{ text: string; correct: boolean }>): Node | null {
      return options.length
        ? h("div", {}, h("p", { class: "cb-eyebrow" }, label), h("ul", { class: "st-cmp-options" }, options.map((o) => h("li", {}, `${o.text} (${o.correct ? t.correctMark : t.wrongMark})`))))
        : null;
    }

    function quizPanel(q: QuizChange): HTMLElement {
      const id = uid("cmp-quiz");
      const body: Array<Node | null> = [];
      if (q.stem) body.push(h("div", {}, h("p", { class: "cb-eyebrow" }, t.quizStem), h("p", { class: "st-cmp-text cb-change-text" }, wordDiff(q.stem.before, q.stem.after, diffLabels))));
      if (q.type) body.push(h("p", {}, `${t.quizType}: `, h("del", {}, sr(t.srRemoved), text(q.type.before)), " → ", h("ins", {}, sr(t.srAdded), text(q.type.after))));
      body.push(optionList(t.optionsAdded, q.options.added), optionList(t.optionsRemoved, q.options.removed));
      if (q.options.reworded.length) {
        body.push(h("div", {}, h("p", { class: "cb-eyebrow" }, t.optionsReworded), h("ul", { class: "st-cmp-options" }, q.options.reworded.map((o) => h("li", { class: "cb-change-text" }, wordDiff(o.before, o.after, diffLabels))))));
      }
      if (q.answerKeyChanged) {
        body.push(
          h("dl", { class: "st-cmp-values" },
            h("div", {}, h("dt", {}, t.correctBefore), h("dd", {}, q.correctBefore.join("; ") || t.empty)),
            h("div", {}, h("dt", {}, t.correctAfter), h("dd", {}, q.correctAfter.join("; ") || t.empty))),
          h("p", { class: "cb-note st-cmp-consequence", role: "note" }, t.quizConsequence));
      } else if (q.options.keyChanged.length) {
        body.push(h("p", {}, t.keyChanged));
      }
      if (q.explanation) body.push(h("p", { class: "cb-muted" }, t.explanationChanged));
      return h("div", { class: "st-cmp-panel", role: "group", "aria-labelledby": id, "data-panel": "quiz" }, h("h4", { id, class: "st-cmp-field-title" }, t.quizPanel), body);
    }

    function parentName(label: string | null): string {
      if (!label) return t.topLevel;
      return elementName(t, { type: /^Module/.test(label) ? "module" : /^Lesson/.test(label) ? "lesson" : /^Final test/.test(label) ? "quiz" : null, label });
    }

    function renderDetail(n: TreeNode): void {
      const change = n.change;
      const name = elementTitle(t, change ?? n);
      const trail = breadcrumb(t, n);
      const sections: Array<Node | null> = [];
      if (!change) {
        sections.push(h("p", { class: "cb-muted" }, t.contextNote));
      } else {
        if (change.moved) sections.push(h("p", { class: "st-cmp-move" }, h("span", { "aria-hidden": "true" }, `${KIND_SYMBOL.moved} `), fill(t.movedFromTo, { from: parentName(change.moved.from.label), to: parentName(change.moved.to.label) })));
        if (change.reordered) sections.push(h("p", { class: "st-cmp-move" }, h("span", { "aria-hidden": "true" }, `${KIND_SYMBOL.reordered} `), fill(t.reorderedLine, { from: f.number(change.reordered.from), to: f.number(change.reordered.to) })));
        if (change.kind === "added") sections.push(h("p", { class: "cb-eyebrow" }, t.addedContent));
        if (change.kind === "removed") sections.push(h("p", { class: "cb-eyebrow" }, t.removedContent));
        sections.push(...fieldsToShow(change).map((field) => fieldBlock(change, field)));
        sections.push(citationsPanel(change));
      }
      const q = quizFor(data.quiz, n.id);
      if (q) sections.push(quizPanel(q));
      const present = (nodes: Array<Node | null>): Node[] => nodes.filter((n): n is Node => n !== null);
      detail.replaceChildren(
        h("h3", { id: "cmp-detail-heading", class: "cb-h3 st-cmp-detail-title", tabindex: "-1" }, name, " ", change ? h("span", { class: "st-cmp-badges" }, kindsOf(change).map(badge)) : null),
        ...(trail ? [h("p", { class: "cb-muted cb-small" }, trail)] : []),
        ...present(sections),
        h("button", { type: "button", class: "cb-btn cb-btn-small st-cmp-back-btn", "data-back": "", onclick: () => focusItem(treeHost.querySelector<HTMLElement>(`[data-node="${n.id}"]`) ?? treeItems()[0]) }, t.back));
    }

    function select(id: string, focusPanel: boolean): void {
      const n = byId.get(id);
      if (!n) return;
      // open the parents so the element is visible in the tree
      for (let p = n.parent; p !== null; p = p.parent) collapsed.delete(p.id);
      selectedId = id;
      currentId = id;
      renderTree();
      renderDetail(n);
      if (focusPanel) {
        detail.querySelector<HTMLElement>("#cmp-detail-heading")?.focus();
      }
    }

    function refresh(announceCount: boolean, refocus: string | null = null): void {
      const { shown } = visibleIds(tree, filters);
      renderFilters();
      renderTree();
      if (selectedId && !visibleIds(tree, filters).ids.has(selectedId)) {
        selectedId = null;
        detail.replaceChildren(h("h3", { id: "cmp-detail-heading", class: "cb-h3", tabindex: "-1" }, t.detailTitle), h("p", { class: "cb-muted" }, t.detailEmpty));
      }
      if (announceCount) announce(fill(t.announceFiltered, { shown: f.number(shown), total: f.number(total) }));
      if (refocus) filterHost.querySelector<HTMLElement>(refocus)?.focus();
      count.textContent = fill(t.showing, { shown: f.number(shown), total: f.number(total) });
    }

    const count = h("p", { class: "cb-muted st-cmp-count", "data-count": "" });
    detail.replaceChildren(h("h3", { id: "cmp-detail-heading", class: "cb-h3", tabindex: "-1" }, t.detailTitle), h("p", { class: "cb-muted" }, t.detailEmpty));
    renderView();
    const changes = section(t.changesTitle,
      h("div", { class: "st-cmp-toolbar", role: "group", "aria-label": t.filtersLabel }, filterHost, viewHost),
      count,
      h("div", { class: "st-cmp-panes" }, treeHost, detail));
    refresh(false);
    resultHost.replaceChildren(sides, summary, impact, quizSection, changes);
    announce(fill(t.announceLoaded, { n: f.number(total) }));
  }

  // ---- loading ---------------------------------------------------------------------------------------------------

  async function loadCompare(): Promise<void> {
    resultHost.setAttribute("aria-busy", "true");
    resultHost.replaceChildren(h("div", { class: "cb-skeleton", "aria-hidden": "true" }, h("span", {}), h("span", {}), h("span", {})), status(t.loading));
    try {
      showCompare(await client.compare(courseId, from, to));
    } catch (error) {
      failure(error, () => void loadCompare(), "compare");
    }
  }

  async function load(): Promise<void> {
    pickHost.replaceChildren(status(t.loading));
    try {
      const data = await client.versions(courseId, { branch: "all", perPage: 200 });
      if (data.total === 0) {
        pickHost.replaceChildren();
        return message(resultHost, t.noVersions, "status", "empty");
      }
      showPicker(data);
      if (from && to) await loadCompare();
      else resultHost.replaceChildren(status(t.pickBoth));
    } catch (error) {
      pickHost.replaceChildren();
      failure(error, () => void load(), "versions");
    }
  }

  void load();
}

/** `Lesson 2.1: Question 1` for a quiz change (its API label is English: "Lesson 2.1 › Q1"). */
function quizName(f: CompareFormatter, q: QuizChange): string {
  const [lesson, question] = q.label.split(" › ");
  const t = f.t;
  const a = /^Final test/.test(lesson ?? "") ? t.finalTest : elementName(t, { type: "lesson", label: lesson ?? "" });
  return question ? `${a} › ${elementName(t, { type: "question", label: question })}` : a;
}

const root = typeof document === "undefined" ? null : document.querySelector<HTMLElement>("[data-studio-compare]");
if (root) mountCompare(root);
