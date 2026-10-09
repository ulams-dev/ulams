/**
 * Accessible implementations of the builder catalogue (./catalogue.ts). Each takes validated
 * props and a context and returns an element. Interactions are sent back as A2UI actions through
 * `ctx.dispatch` (the studio posts them as AG-UI runs). Every control is a native button, input or
 * select; state is conveyed by text and icons, never colour alone.
 */
import { h, miniMarkdown, sr, uid, usd } from "./dom.ts";
import { wordDiff } from "./word-diff.ts";

export interface A2uiActionOut {
  name: string;
  surfaceId: string;
  sourceComponentId: string;
  context: Record<string, unknown>;
}

export interface BuilderContext {
  surfaceId: string;
  dispatch: (action: A2uiActionOut) => void;
  /** Opens the source passage behind a citation chip. */
  onCitation?: (fragmentId: string, label: string, trigger: HTMLElement) => void;
  /** Scopes the chat to an element (lesson, block, question). */
  onSelect?: (elementId: string, label: string) => void;
  onRestore?: (versionId: string) => void;
}

type Props = Record<string, any>; // eslint-disable-line @typescript-eslint/no-explicit-any
export type Renderer = (props: Props, ctx: BuilderContext, id: string, children: HTMLElement[]) => HTMLElement;

const act = (ctx: BuilderContext, id: string, name: string, context: Record<string, unknown> = {}) =>
  ctx.dispatch({ name, surfaceId: ctx.surfaceId, sourceComponentId: id, context });

const icon = (name: "check" | "plus" | "minus" | "tilde" | "alert" | "spark" | "dot" | "clock"): SVGElement => {
  const paths: Record<string, string> = {
    check: "M4 10.5l4 4 8-9",
    plus: "M10 4v12M4 10h12",
    minus: "M4 10h12",
    tilde: "M3 11c2-3 4-3 7 0s5 3 7 0",
    alert: "M10 3l8 14H2L10 3zM10 8v4M10 14.5v.5",
    spark: "M10 2v5M10 13v5M2 10h5M13 10h5",
    dot: "M10 7a3 3 0 110 6 3 3 0 010-6z",
    clock: "M10 3a7 7 0 110 14 7 7 0 010-14zM10 6v4l3 2",
  };
  const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
  svg.setAttribute("viewBox", "0 0 20 20");
  svg.setAttribute("aria-hidden", "true");
  svg.setAttribute("class", `cb-icon cb-icon-${name}`);
  const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
  path.setAttribute("d", paths[name]!);
  svg.append(path);
  return svg;
};

function citations(list: Props[] | undefined, ctx: BuilderContext): HTMLElement | null {
  if (!list || list.length === 0) return null;
  return h("ul", { class: "cb-cites", "aria-label": "Sources" }, list.map((c) => h("li", {}, citationChip(c, ctx))));
}

function citationChip(p: Props, ctx: BuilderContext): HTMLElement {
  const button = h("button", { type: "button", class: "cb-cite", title: `Source ${p.fragmentId}` }, sr("Source: "), String(p.label));
  button.addEventListener("click", () => ctx.onCitation?.(String(p.fragmentId), String(p.label), button));
  return button;
}

const changeBadge = (change: string | undefined): HTMLElement | null => {
  if (!change || change === "unchanged") return null;
  const label = change === "added" ? "Added" : change === "removed" ? "Removed" : "Changed";
  return h("span", { class: `cb-badge cb-badge-${change}` }, icon(change === "added" ? "plus" : change === "removed" ? "minus" : "tilde"), label);
};

/* ------------------------------------------------------------------ interview */

function questionShell(p: Props, id: string, body: HTMLElement[], ctx: BuilderContext, answer: () => unknown): HTMLElement {
  const status = String(p.status);
  const headingId = uid("q");
  const step = p.step && p.total ? h("span", { class: "cb-step" }, `${p.step} of ${p.total}`) : null;
  if (status === "answered") {
    return h(
      "section",
      { class: "cb-card cb-question cb-question-answered", "aria-labelledby": headingId, "data-key": p.questionKey },
      h("div", { class: "cb-question-row" }, step, h("h3", { id: headingId, class: "cb-question-title" }, String(p.label)),
        h("span", { class: "cb-answer" }, icon("check"), sr("Answered: "), answerLabel(p)),
        p.decidedBy === "default" ? h("span", { class: "cb-tag" }, "decided for you") : null)
    );
  }
  if (status === "upcoming") {
    return h("section", { class: "cb-card cb-question cb-question-upcoming", "aria-labelledby": headingId, "data-key": p.questionKey },
      step, h("h3", { id: headingId, class: "cb-question-title" }, String(p.label)), h("p", { class: "cb-muted" }, "Upcoming"));
  }
  const continueButton = h("button", { type: "submit", class: "cb-btn cb-btn-primary" }, "Continue");
  const decide = h("button", { type: "button", class: "cb-btn cb-btn-ghost" }, icon("spark"), "Decide for me");
  decide.addEventListener("click", () => act(ctx, id, "decide_for_me", { key: p.questionKey }));
  const form = h("form", { class: "cb-card cb-question cb-question-open", "aria-labelledby": headingId, "data-key": p.questionKey },
    h("div", { class: "cb-question-head" }, step, h("h3", { id: headingId, class: "cb-question-title cb-serif" }, String(p.label))),
    p.why ? h("p", { class: "cb-why" }, h("strong", {}, "Why I ask: "), String(p.why)) : null,
    ...body,
    h("div", { class: "cb-actions" }, continueButton, decide));
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    const value = answer();
    if (value === undefined || value === "" || (Array.isArray(value) && value.length === 0)) {
      act(ctx, id, "decide_for_me", { key: p.questionKey });
      return;
    }
    act(ctx, id, "answer", { key: p.questionKey, value });
  });
  return form;
}

function answerLabel(p: Props): string {
  const options: Props[] = p.options ?? [];
  const label = (v: unknown) => options.find((o) => o.value === v)?.label ?? String(v);
  if (p.value && typeof p.value === "object" && !Array.isArray(p.value) && "totalMinutes" in p.value) {
    return `${p.value.totalMinutes} min · ${p.value.lessonMinutes}-min lessons`;
  }
  if (Array.isArray(p.value)) return p.value.map(label).join(", ") || "None";
  return p.value !== undefined ? label(p.value) : "";
}

const ChoiceChips: Renderer = (p, ctx, id) => {
  const selected = new Set<string>((p.value ?? p.defaultValue ?? []).map(String));
  const group = h("div", { class: "cb-chips", role: "group", "aria-label": String(p.label) });
  const custom = p.allowCustom ? h("input", { type: "text", class: "cb-input", "aria-label": "Or type your own answer", placeholder: "Or type your own answer", maxlength: 120 }) : null;
  for (const option of p.options as Props[]) {
    const button = h("button", { type: "button", class: "cb-chip", "aria-pressed": selected.has(option.value) ? "true" : "false", "data-value": option.value }, String(option.label));
    button.addEventListener("click", () => {
      if (!p.multiple) {
        selected.clear();
        group.querySelectorAll("button").forEach((b) => b.setAttribute("aria-pressed", "false"));
      }
      if (selected.has(option.value)) selected.delete(option.value);
      else selected.add(option.value);
      button.setAttribute("aria-pressed", selected.has(option.value) ? "true" : "false");
    });
    group.append(button);
  }
  return questionShell(p, id, [group, ...(custom ? [custom] : [])], ctx, () => {
    const typed = custom?.value.trim();
    if (typed) return p.multiple ? [typed] : typed;
    const values = [...selected];
    return p.multiple ? values : values[0];
  });
};

const radioGroup = (name: string, legend: string, options: Props[], checked: unknown): HTMLFieldSetElement =>
  h("fieldset", { class: "cb-radios" }, h("legend", {}, legend),
    options.map((o) => {
      const inputId = uid(name);
      return h("span", { class: "cb-radio" },
        h("input", { type: "radio", id: inputId, name, value: String(o.value), checked: String(o.value) === String(checked) }),
        h("label", { for: inputId }, String(o.label)));
    }));

const SingleChoice: Renderer = (p, ctx, id) => {
  const name = uid("single");
  const fieldset = radioGroup(name, String(p.label), p.options, p.value ?? p.defaultValue);
  fieldset.classList.add("cb-radios-cards");
  return questionShell(p, id, [fieldset], ctx, () => (fieldset.querySelector<HTMLInputElement>("input:checked")?.value ?? undefined));
};

const DurationSlider: Renderer = (p, ctx, id) => {
  const current = p.value ?? p.defaultValue;
  const minutes = (m: number) => (m >= 60 && m % 60 === 0 ? `${m / 60} h` : `${m} min`);
  const total = radioGroup(uid("total"), "Total duration", (p.totalOptions as number[]).map((m) => ({ value: m, label: minutes(m) })), current?.totalMinutes);
  const lesson = radioGroup(uid("lesson"), "Lesson length", (p.lessonOptions as number[]).map((m) => ({ value: m, label: `${m} min` })), current?.lessonMinutes);
  total.classList.add("cb-radios-cards");
  return questionShell(p, id, [total, lesson], ctx, () => ({
    totalMinutes: Number(total.querySelector<HTMLInputElement>("input:checked")?.value ?? current?.totalMinutes),
    lessonMinutes: Number(lesson.querySelector<HTMLInputElement>("input:checked")?.value ?? current?.lessonMinutes),
  }));
};

const LanguagePicker: Renderer = (p, ctx, id) => {
  const selectId = uid("lang");
  const select = h("select", { id: selectId, class: "cb-select" },
    (p.options as Props[]).map((o) => h("option", { value: o.value, selected: o.value === (p.value ?? p.defaultValue) }, String(o.label))));
  return questionShell(p, id, [h("label", { for: selectId, class: "cb-label" }, "Language"), select], ctx, () => select.value);
};

const DecideForMe: Renderer = (p, ctx, id) => {
  const button = h("button", { type: "button", class: "cb-btn cb-btn-ghost cb-decide", disabled: !p.open }, icon("spark"), String(p.label));
  button.addEventListener("click", () => act(ctx, id, "decide_for_me", {}));
  return h("div", { class: "cb-decide-row" }, button);
};

/* ------------------------------------------------------------------ sources and outline */

const SourceCard: Renderer = (p, ctx) => {
  const status = String(p.status);
  const statusText = { uploaded: "Uploaded", processing: "Reading", ready: "Ready to cite", failed: "Could not read" }[status] ?? status;
  const meta = [p.sizeBytes ? `${(p.sizeBytes / 1024).toFixed(0)} KB` : null, p.pages ? `${p.pages} pages` : null, p.fragmentCount ? `${p.fragmentCount} fragments` : null, p.tokens ? `${Math.round(p.tokens / 1000)}k tokens` : null].filter(Boolean).join(" · ");
  return h("section", { class: `cb-card cb-source cb-status-${status}`, "aria-label": `Source ${p.name}` },
    h("div", { class: "cb-source-head" },
      h("span", { class: "cb-mono cb-file" }, String(p.name)),
      h("span", { class: `cb-pill cb-pill-${status}` }, icon(status === "ready" ? "check" : status === "failed" ? "alert" : "clock"), statusText)),
    meta ? h("p", { class: "cb-muted cb-mono" }, meta) : null,
    p.error ? h("p", { class: "cb-error", role: "alert" }, String(p.error)) : null,
    status === "processing" ? h("div", { class: "cb-skeleton-lines", "aria-hidden": "true" }, h("span", {}), h("span", {}), h("span", {})) : null,
    p.sections?.length
      ? h("details", { class: "cb-sections", open: status === "ready" && p.sections.length <= 12 },
          h("summary", {}, `Sections (${p.sections.length})`),
          h("ul", {}, (p.sections as Props[]).map((s) => h("li", { style: `--depth:${s.level}` }, citationChip({ fragmentId: s.fragmentId, label: s.label }, ctx)))))
      : null);
};

const CitationChip: Renderer = (p, ctx) => citationChip(p, ctx);

const OutlineDiff: Renderer = (p, ctx, id) => {
  const proposed = p.status === "proposed";
  const edits = new Map<string, string>();
  const objective = (o: Props): HTMLElement => {
    const li = h("li", { class: `cb-objective cb-change-${o.change ?? "unchanged"}` });
    const text = h("span", { class: "cb-objective-text" }, String(o.text));
    li.append(icon("dot"), text);
    if (o.before && o.change === "changed") li.append(h("span", { class: "cb-before" }, sr("before: "), wordDiff(String(o.before), String(o.text))));
    const badge = changeBadge(o.change);
    if (badge && o.change !== "added") li.append(badge);
    if (proposed && p.editable) {
      const edit = h("button", { type: "button", class: "cb-link", "aria-label": `Edit objective: ${o.text}` }, "Edit");
      edit.addEventListener("click", () => {
        const input = h("input", { type: "text", class: "cb-input", value: edits.get(o.id) ?? String(o.text), maxlength: 400, "aria-label": "Objective" });
        const save = h("button", { type: "button", class: "cb-btn cb-btn-small" }, "Save");
        const form = h("span", { class: "cb-inline-edit" }, input, save);
        save.addEventListener("click", () => {
          const value = input.value.trim();
          if (value) {
            edits.set(o.id, value);
            text.textContent = value;
            li.classList.add("cb-edited");
          }
          form.replaceWith(edit);
          edit.focus();
          editedNote.textContent = `${edits.size} objective${edits.size === 1 ? "" : "s"} edited`;
        });
        edit.replaceWith(form);
        input.focus();
      });
      li.append(edit);
    }
    li.append(citations(o.citations, ctx) ?? "");
    return li;
  };
  const editedNote = h("p", { class: "cb-muted", "aria-live": "polite" });
  const summary = p.summary;
  const header = h("header", { class: "cb-outline-head" },
    h("h3", { class: "cb-serif cb-h2" }, "Proposed outline", h("span", { class: "cb-version cb-mono" }, `v${p.number ?? ""}`)),
    h("p", { class: "cb-muted" }, `${summary.modules} modules · ${summary.lessons} lessons · ${summary.minutes} min${p.targetMinutes ? ` (brief: ${p.targetMinutes})` : ""} · ${summary.objectives} learning objectives`),
    hasChanges(p) ? h("ul", { class: "cb-legend", "aria-label": "Legend" }, ["added", "changed", "removed"].map((k) => h("li", {}, changeBadge(k)))) : null);
  const course = h("section", { class: "cb-outline-course" },
    h("h4", { class: "cb-serif" }, String(p.course.title)),
    p.course.subtitle ? h("p", { class: "cb-muted" }, String(p.course.subtitle)) : null,
    p.course.objectives?.length ? h("div", {}, h("p", { class: "cb-eyebrow" }, "Course outcomes"), h("ul", { class: "cb-objectives" }, (p.course.objectives as Props[]).map(objective))) : null);
  const modules = h("ol", { class: "cb-modules" }, (p.modules as Props[]).map((m, mi) =>
    h("li", { class: `cb-module cb-change-${m.change ?? "unchanged"}` },
      h("div", { class: "cb-module-head" }, h("span", { class: "cb-eyebrow cb-mono" }, `Module ${String(mi + 1).padStart(2, "0")}`),
        h("h4", { class: "cb-serif" }, String(m.title)), changeBadge(m.change)),
      m.summary ? h("p", { class: "cb-muted" }, String(m.summary)) : null,
      h("ol", { class: "cb-lessons" }, (m.lessons as Props[]).map((l, li) =>
        h("li", { class: `cb-lesson cb-change-${l.change ?? "unchanged"}` },
          h("div", { class: "cb-lesson-head" },
            h("h5", { class: "cb-serif" }, `${mi + 1}.${li + 1} `, l.before ? wordDiff(String(l.before), String(l.title)) : String(l.title)),
            h("span", { class: "cb-mono cb-muted" }, `${l.minutes} min`), changeBadge(l.change)),
          l.summary ? h("p", { class: "cb-muted" }, String(l.summary)) : null,
          h("ul", { class: "cb-objectives", "aria-label": "Learning objectives" }, (l.objectives as Props[]).map(objective)),
          citations(l.citations, ctx)))))));
  const removed = p.removed?.length
    ? h("ul", { class: "cb-removed", "aria-label": "Removed" }, (p.removed as Props[]).map((r) => h("li", {}, changeBadge("removed"), h("del", {}, String(r.title)))))
    : null;

  const footer = h("footer", { class: "cb-review" });
  if (proposed) {
    const commentId = uid("comment");
    const comment = h("textarea", { id: commentId, class: "cb-textarea", rows: 3, maxlength: 2000, placeholder: "e.g. Fewer modules, more on grind size" });
    const approve = h("button", { type: "button", class: "cb-btn cb-btn-primary" }, icon("spark"), "Approve outline & generate");
    const reject = h("button", { type: "button", class: "cb-btn" }, "Request changes");
    approve.addEventListener("click", () => act(ctx, id, "approve_outline", { versionId: p.versionId, edits: [...edits].map(([objectiveId, text]) => ({ objectiveId, text })) }));
    reject.addEventListener("click", () => act(ctx, id, "reject_outline", { versionId: p.versionId, comment: comment.value.trim() }));
    footer.append(
      h("label", { for: commentId, class: "cb-label" }, "Comment for a revision (optional)"), comment, editedNote,
      h("div", { class: "cb-actions" }, approve, reject),
      h("p", { class: "cb-muted cb-small" }, "Nothing is written to your academy until you approve the generated course."));
  } else {
    footer.append(h("p", { class: `cb-decision cb-decision-${p.status}` }, icon(p.status === "approved" ? "check" : "minus"),
      p.status === "approved" ? "Outline approved" : p.status === "rejected" ? "Changes requested" : "Replaced by a newer proposal"));
  }
  return h("section", { class: "cb-card cb-outline", "aria-label": "Proposed outline" }, header, p.comment ? h("p", { class: "cb-note" }, `Changed after your comment: “${p.comment}”`) : null, course, modules, removed, footer);
};

/** True when an outline proposal differs from a previous one (first proposals have no marks). */
function hasChanges(p: Props): boolean {
  if (p.removed?.length) return true;
  const changed = (x: Props) => x.change && x.change !== "unchanged";
  return (p.modules as Props[]).some((m) => changed(m) || (m.lessons as Props[]).some((l) => changed(l) || (l.objectives as Props[]).some(changed)));
}

/* ------------------------------------------------------------------ generation */

const CostMeter: Renderer = (p) => {
  const used = Number(p.usedMicroUsd ?? 0);
  const budget = Math.max(1, Number(p.budgetMicroUsd ?? 1));
  return h("div", { class: "cb-cost" },
    h("span", { class: "cb-mono" }, `${usd(used)} of ${usd(budget)}`, p.label ? ` · ${p.label}` : ""),
    h("progress", { max: budget, value: Math.min(used, budget), "aria-label": "AI budget used" }),
    p.tokens ? h("span", { class: "cb-muted cb-mono" }, `${Math.round(Number(p.tokens) / 1000)}k tokens`) : null);
};

const GenerationProgress: Renderer = (p, ctx, id) => {
  const lessons = p.lessons as Props[];
  const done = lessons.filter((l) => l.status === "done" || l.status === "flagged").length;
  const statusLabel: Record<string, string> = { pending: "Waiting", running: "Writing", done: "Ready", failed: "Failed", flagged: "Ready, check flags" };
  const cost = p.cost;
  const cached = cost.inputTokens + cost.cacheReadTokens > 0 ? Math.round((100 * cost.cacheReadTokens) / (cost.inputTokens + cost.cacheReadTokens)) : 0;
  return h("section", { class: "cb-card cb-progress", "aria-label": "Generation progress" },
    h("ol", { class: "cb-stages" }, (p.stages as Props[]).map((s) =>
      h("li", { class: `cb-stage cb-stage-${s.status}`, "aria-current": s.status === "running" ? "step" : null },
        icon(s.status === "done" ? "check" : s.status === "failed" ? "alert" : s.status === "running" ? "spark" : "dot"),
        h("span", {}, String(s.label)), sr(` (${s.status})`)))),
    h("div", { class: "cb-progress-head" },
      h("h3", { class: "cb-serif cb-h2" }, p.status === "finished" ? "Lessons ready" : "Lessons in progress"),
      h("p", { class: "cb-mono" }, `${done} of ${lessons.length} ready`),
      p.status === "needs_attention" ? h("p", { class: "cb-error" }, icon("alert"), "A step needs your attention") : null),
    h("ul", { class: "cb-lesson-rows" }, lessons.map((l) => {
      const retry = l.status === "failed" && l.stepId ? h("button", { type: "button", class: "cb-btn cb-btn-small" }, "Retry") : null;
      retry?.addEventListener("click", () => act(ctx, id, "retry_step", { stepId: l.stepId }));
      return h("li", { class: `cb-lesson-row cb-row-${l.status}` },
        h("span", { class: "cb-row-icon" }, icon(l.status === "done" ? "check" : l.status === "failed" || l.status === "flagged" ? "alert" : l.status === "running" ? "spark" : "dot")),
        h("span", { class: "cb-row-title cb-serif" }, String(l.title)),
        h("span", { class: "cb-row-status" }, statusLabel[l.status] ?? l.status),
        l.citations ? h("span", { class: "cb-cite cb-cite-static" }, `${l.citations} citations`) : null,
        l.questions ? h("span", { class: "cb-muted" }, `Quiz: ${l.questions} questions`) : null,
        l.costMicroUsd ? h("span", { class: "cb-mono cb-muted" }, usd(l.costMicroUsd)) : null,
        l.error ? h("p", { class: "cb-error cb-row-error" }, String(l.error)) : null,
        retry);
    })),
    h("div", { class: "cb-telemetry" }, CostMeter({ usedMicroUsd: cost.usedMicroUsd, budgetMicroUsd: cost.budgetMicroUsd }, ctx, `${id}-cost`, []),
      h("span", { class: "cb-mono cb-muted" }, `${cost.inputTokens ?? 0} in · ${cost.outputTokens ?? 0} out · ${cached}% cached`)));
};

/* ------------------------------------------------------------------ previews */

const LessonPreviewCard: Renderer = (p, ctx) => {
  const body = h("div", { class: "cb-prose" });
  const blocks = (p.blocks as Props[]).map((b) => {
    const block = h("div", { class: `cb-block cb-block-${b.kind}`, "data-block": b.id });
    const content = h("div", {});
    content.innerHTML = miniMarkdown(String(b.markdown));
    block.append(content, citations(b.citations, ctx) ?? "");
    return block;
  });
  body.append(...blocks);
  const edit = h("button", { type: "button", class: "cb-btn cb-btn-small" }, "Edit in chat");
  edit.addEventListener("click", () => ctx.onSelect?.(String(p.lessonId), String(p.title)));
  return h("article", { class: "cb-card cb-lesson-preview", "aria-label": `Lesson ${p.title}` },
    h("header", {}, h("h3", { class: "cb-serif cb-h2" }, String(p.title)), p.minutes ? h("p", { class: "cb-mono cb-muted" }, `${p.minutes} min`) : null, edit),
    p.flags?.length ? h("ul", { class: "cb-flags", "aria-label": "Grounding flags" }, (p.flags as string[]).map((f) => h("li", {}, icon("alert"), f))) : null,
    body);
};

const QuizQuestionCard: Renderer = (p, ctx) => {
  const edit = h("button", { type: "button", class: "cb-btn cb-btn-small" }, "Edit in chat");
  edit.addEventListener("click", () => ctx.onSelect?.(String(p.questionId), String(p.stem)));
  return h("article", { class: "cb-card cb-question-card", "aria-label": "Quiz question" },
    h("p", { class: "cb-eyebrow" }, { single: "Single choice", multiple: "Multiple answers", truefalse: "True or false", short: "Short answer" }[String(p.type)] ?? String(p.type)),
    h("h3", { class: "cb-question-stem" }, String(p.stem)),
    h("ol", { class: "cb-options", type: "A" }, (p.options as Props[]).map((o) =>
      h("li", { class: o.correct ? "cb-option cb-option-correct" : "cb-option" }, String(o.text),
        o.correct ? h("span", { class: "cb-correct" }, icon("check"), "Correct answer") : null))),
    h("p", { class: "cb-explanation" }, h("strong", {}, "Explanation: "), String(p.explanation)),
    citations(p.citations, ctx), edit);
};

const DiffView: Renderer = (p, ctx, id) => {
  const proposed = p.status === "proposed";
  const rows = h("ul", { class: "cb-changes" }, (p.changes as Props[]).map((c) =>
    h("li", { class: `cb-change cb-change-${c.kind}` },
      h("p", { class: "cb-change-label" }, changeBadge(c.kind), String(c.label)),
      c.kind === "changed"
        ? h("p", { class: "cb-change-text" }, wordDiff(String(c.before ?? ""), String(c.after ?? "")))
        : h("p", { class: "cb-change-text" }, c.kind === "added" ? h("ins", {}, sr("added: "), String(c.after ?? "")) : h("del", {}, sr("removed: "), String(c.before ?? ""))))));
  const actions = h("div", { class: "cb-actions" });
  if (proposed) {
    const approve = h("button", { type: "button", class: "cb-btn cb-btn-primary" }, icon("check"), "Approve");
    const reject = h("button", { type: "button", class: "cb-btn" }, "Reject");
    approve.addEventListener("click", () => act(ctx, id, "approve_patch", { versionId: p.versionId }));
    reject.addEventListener("click", () => act(ctx, id, "reject_patch", { versionId: p.versionId }));
    actions.append(approve, reject);
  } else {
    actions.append(h("p", { class: `cb-decision cb-decision-${p.status}` }, icon(p.status === "approved" ? "check" : "minus"),
      p.status === "approved" ? "Approved and applied" : p.status === "rejected" ? "Rejected; nothing changed" : "Replaced by a newer proposal"));
  }
  return h("section", { class: "cb-card cb-diff", "aria-label": `Proposed change to ${p.elementLabel}` },
    h("header", {}, h("h3", { class: "cb-h3" }, `Proposed change to ${p.elementLabel}`), p.reason ? h("p", { class: "cb-muted" }, `You asked: “${p.reason}”`) : null),
    p.changes.length ? rows : h("p", { class: "cb-muted" }, "No changes: the element stays as it is."),
    citations(p.citations, ctx), actions);
};

const ApplySummary: Renderer = (p, ctx, id) => {
  const kinds: Array<[string, string]> = [["courses", "Courses"], ["lessons", "Lessons"], ["topics", "Topics"], ["questions", "Quiz questions"], ["pages", "Pages"]];
  const table = h("table", { class: "cb-table" },
    h("caption", { class: "cb-sr" }, "What applying changes in your academy"),
    h("thead", {}, h("tr", {}, h("th", { scope: "col" }, "Item"), h("th", { scope: "col" }, "Create"), h("th", { scope: "col" }, "Update"), h("th", { scope: "col" }, "Delete"))),
    h("tbody", {}, kinds.filter(([k]) => p.create[k] + p.update[k] + p.delete[k] > 0).map(([k, label]) =>
      h("tr", {}, h("th", { scope: "row" }, label), h("td", {}, String(p.create[k])), h("td", {}, String(p.update[k])), h("td", {}, String(p.delete[k]))))));
  const actions = h("div", { class: "cb-actions" });
  if (p.status === "proposed") {
    const approve = h("button", { type: "button", class: "cb-btn cb-btn-primary" }, icon("check"), p.firstApply ? "Apply to my academy" : "Apply changes");
    approve.addEventListener("click", () => act(ctx, id, "approve_apply", { versionId: p.versionId }));
    actions.append(approve, h("p", { class: "cb-muted cb-small" }, "The course is created unpublished. Publishing is a separate step."));
  } else {
    actions.append(h("p", { class: `cb-decision cb-decision-${p.status}`, role: p.status === "failed" ? "alert" : null },
      icon(p.status === "applied" ? "check" : p.status === "failed" ? "alert" : "clock"),
      { applying: "Applying…", applied: "Applied to your academy", failed: "The apply failed" }[String(p.status)] ?? String(p.status)));
  }
  return h("section", { class: "cb-card cb-apply", "aria-label": "Apply summary" },
    h("h3", { class: "cb-serif cb-h2" }, p.firstApply ? "Create the course in your academy" : "Apply the changes"),
    table,
    p.warnings?.length ? h("div", { class: "cb-warnings" }, h("p", { class: "cb-eyebrow" }, `${p.warnings.length} warnings`), h("ul", {}, (p.warnings as string[]).map((w) => h("li", {}, icon("alert"), w)))) : null,
    actions);
};

const VersionList: Renderer = (p, ctx) =>
  h("ol", { class: "cb-versions", reversed: true, "aria-label": "Version history" }, [...(p.versions as Props[])].reverse().map((v) => {
    const restore = v.id !== p.currentVersionId && v.status === "approved" && v.kind !== "outline" ? h("button", { type: "button", class: "cb-link" }, `Restore v${v.number}`) : null;
    restore?.addEventListener("click", () => ctx.onRestore?.(String(v.id)));
    return h("li", { class: v.id === p.currentVersionId ? "cb-version-current" : "", "aria-current": v.id === p.currentVersionId ? "true" : null },
      h("span", { class: "cb-mono" }, `v${v.number}`), " ", h("span", {}, v.reason ? String(v.reason) : v.kind === "update" ? "Source update" : String(v.kind)),
      v.kind === "update" && !String(v.reason ?? "").startsWith("Source update") ? h("span", { class: "cb-tag" }, "Source update") : null,
      h("span", { class: "cb-muted" }, ` · ${v.origin === "ai" ? "AI" : v.origin === "author" ? "You" : "Restore"} · ${v.status}`),
      v.id === p.currentVersionId ? h("span", { class: "cb-tag" }, "current") : null, restore);
  }));

/* ------------------------------------------------------------------ living course */

const when = (iso: unknown): HTMLElement | null => {
  if (typeof iso !== "string" || iso === "") return null;
  const date = new Date(iso);
  const text = Number.isNaN(date.getTime()) ? iso : date.toLocaleString("en-GB", { dateStyle: "medium", timeStyle: "short" });
  return h("time", { datetime: iso }, text);
};

const CONNECTOR_LABEL: Record<string, string> = { upload: "Upload", git: "Git repository", url: "Web page" };
const SYNC_STATE: Record<string, { label: string; icon: "check" | "plus" | "clock" | "alert" | "minus" }> = {
  up_to_date: { label: "Up to date", icon: "check" },
  new_version: { label: "New version available", icon: "plus" },
  processing: { label: "Processing", icon: "clock" },
  failed: { label: "Check failed", icon: "alert" },
  paused: { label: "Paused", icon: "minus" },
};

const SourceConnectionCard: Renderer = (p, ctx, id) => {
  const state = SYNC_STATE[String(p.state)] ?? SYNC_STATE.up_to_date!;
  const headingId = uid("src");
  const status = h("p", { class: "cb-sync-status", role: "status" }, h("span", { class: `cb-sync-pill cb-sync-${p.state}` }, icon(state.icon), state.label));
  const facts = h("dl", { class: "cb-facts" },
    h("div", {}, h("dt", {}, "In your course"), h("dd", {}, p.syncedRevision ? `Revision ${p.syncedRevision}` : "Not synced yet")),
    h("div", {}, h("dt", {}, "Latest revision"), h("dd", {}, p.latestRevision ? `Revision ${p.latestRevision}` : "None")),
    h("div", {}, h("dt", {}, "Last checked"), h("dd", {}, when(p.lastCheckedAt) ?? "Never")),
    p.schedule ? h("div", {}, h("dt", {}, "Checks"), h("dd", {}, String(p.schedule))) : null);
  const actions = h("div", { class: "cb-actions" });
  if (p.canCheck) {
    const check = h("button", { type: "button", class: "cb-btn cb-btn-small" }, "Check now");
    check.addEventListener("click", () => act(ctx, id, "check_now", { sourceId: p.sourceId }));
    actions.append(check);
  }
  if (p.canUpload !== false) {
    const upload = h("button", { type: "button", class: "cb-btn cb-btn-small cb-btn-primary" }, "Upload a new version");
    upload.addEventListener("click", () => act(ctx, id, "upload_version", { sourceId: p.sourceId }));
    actions.append(upload);
  }
  return h("section", { class: "cb-card cb-source-conn", "aria-labelledby": headingId, "data-source": p.sourceId },
    h("div", { class: "cb-source-conn-head" },
      h("div", {},
        h("p", { class: "cb-eyebrow" }, `${CONNECTOR_LABEL[String(p.connector)] ?? String(p.connector)}${p.kind ? ` · ${String(p.kind).toUpperCase()}` : ""}`),
        h("h3", { id: headingId, class: "cb-serif cb-h2" }, String(p.name))),
      status),
    facts,
    p.error ? h("p", { class: "cb-error", role: "alert" }, icon("alert"), String(p.error)) : null,
    actions.childElementCount ? actions : null);
};

const ORIGIN_LABEL: Record<string, string> = { initial: "First import", upload: "Uploaded", git: "Git", url: "Web page" };
const TRIGGER_LABEL: Record<string, string> = { initial: "first import", manual: "by you", scheduled: "scheduled check", webhook: "webhook" };
const REVISION_STATUS_LABEL: Record<string, { label: string; icon: "check" | "plus" | "clock" | "alert" | "minus" | "dot" }> = {
  fetched: { label: "Fetched", icon: "clock" },
  ingested: { label: "Imported", icon: "check" },
  unchanged: { label: "No changes", icon: "minus" },
  no_impact: { label: "No impact on the course", icon: "minus" },
  failed: { label: "Failed", icon: "alert" },
};

/** "3 changed, 1 removed, 2 added, 1 moved"; kinds with a zero count are left out. */
export function countsText(counts: Props | null | undefined): string {
  if (!counts) return "";
  return (["changed", "removed", "added", "moved"] as const)
    .filter((k) => Number(counts[k]) > 0)
    .map((k) => `${counts[k]} ${k}`)
    .join(", ");
}

const RevisionTimeline: Renderer = (p, ctx, id) => {
  const items = (p.revisions as Props[]).map((r) => {
    const selected = r.id === p.selectedRevisionId;
    const status = REVISION_STATUS_LABEL[String(r.status)] ?? { label: String(r.status), icon: "dot" as const };
    const title = `Revision ${r.number}`;
    const hint = sr(selected ? " (changes shown)" : ": show changes");
    let control: HTMLElement;
    if (r.href) {
      control = h("a", { class: "cb-rev-link", href: String(r.href), "aria-current": selected ? "true" : null }, title, hint);
    } else {
      control = h("button", { type: "button", class: "cb-rev-link", "aria-pressed": selected ? "true" : "false" }, title, hint);
      control.addEventListener("click", () => act(ctx, id, "select_revision", { revisionId: r.id, sourceId: p.sourceId }));
    }
    const counts = countsText(r.counts);
    return h("li", { class: `cb-rev${selected ? " cb-rev-selected" : ""}`, "data-revision": r.id },
      h("div", { class: "cb-rev-head" }, control,
        r.synced ? h("span", { class: "cb-tag cb-tag-course" }, icon("check"), "In your course") : null,
        r.latest ? h("span", { class: "cb-tag" }, "Latest") : null),
      h("p", { class: "cb-muted cb-small cb-rev-meta" },
        ORIGIN_LABEL[String(r.origin)] ?? (r.origin ? String(r.origin) : "Revision"),
        r.trigger ? ` · ${TRIGGER_LABEL[String(r.trigger)] ?? String(r.trigger)}` : "",
        r.detectedAt ? " · " : "", when(r.detectedAt)),
      h("p", { class: "cb-rev-status" }, icon(status.icon), status.label, counts ? h("span", { class: "cb-rev-counts" }, ` · ${counts}`) : null),
      r.error ? h("p", { class: "cb-error" }, icon("alert"), String(r.error)) : null);
  });
  return h("section", { class: "cb-card cb-timeline", "aria-label": "Revision history" },
    h("ol", { class: "cb-revs", "aria-label": "Revisions, newest first" }, items));
};

const SIGNAL_TEXT: Record<string, string> = {
  number: "a number changed",
  code: "code changed",
  identifier: "a name or identifier changed",
  modality: "a must, should or may changed",
  large: "a large part was rewritten",
};
const KIND_BADGE: Record<string, { label: string; icon: "plus" | "minus" | "tilde" | "dot" }> = {
  changed: { label: "Changed", icon: "tilde" },
  moved: { label: "Moved", icon: "dot" },
  removed: { label: "Removed", icon: "minus" },
  added: { label: "Added", icon: "plus" },
};
const MAGNITUDE_TEXT: Record<string, string> = { trivial: "Cosmetic edit", minor: "Minor change", substantive: "Substantive change" };

/** Word-level runs from the API (`=`, `-`, `+`) with visible and hidden markers. */
function runsDiff(runs: string[][]): HTMLElement {
  const out = h("p", { class: "cb-worddiff cb-frag-diff" });
  for (const [op, text] of runs) {
    if (op === "+") out.append(h("ins", {}, sr("added: "), h("span", { "aria-hidden": "true", class: "cb-mark" }, "+"), text ?? ""));
    else if (op === "-") out.append(h("del", {}, sr("removed: "), h("span", { "aria-hidden": "true", class: "cb-mark" }, "−"), text ?? ""));
    else out.append(text ?? "");
  }
  return out;
}

const FragmentChange: Renderer = (p) => {
  const badge = KIND_BADGE[String(p.kind)] ?? KIND_BADGE.changed!;
  const oldF = p.old as Props | undefined;
  const newF = p.new as Props | undefined;
  const label = String((newF ?? oldF)?.label ?? p.section ?? "Source fragment");
  const headingId = uid("chg");
  const signals = ((p.signals as string[] | undefined) ?? []).map((x) => SIGNAL_TEXT[x] ?? x);
  const meta = [MAGNITUDE_TEXT[String(p.magnitude)] ?? String(p.magnitude), ...signals];
  const body: HTMLElement[] = [];
  if (p.wordDiff && (p.wordDiff as string[][]).length) {
    body.push(runsDiff(p.wordDiff as string[][]));
  } else {
    if (oldF?.text) body.push(h("p", { class: "cb-frag-old" }, h("span", { class: "cb-mark", "aria-hidden": "true" }, "− "), sr("Before: "), String(oldF.text)));
    if (newF?.text) body.push(h("p", { class: "cb-frag-new" }, h("span", { class: "cb-mark", "aria-hidden": "true" }, "+ "), sr("After: "), String(newF.text)));
  }
  return h("article", { class: `cb-card cb-frag cb-frag-${p.kind}`, "aria-labelledby": headingId, "data-change": p.changeId },
    h("div", { class: "cb-frag-head" },
      h("h4", { id: headingId, class: "cb-serif cb-h3" }, label),
      h("span", { class: `cb-badge cb-kind-${p.kind}` }, icon(badge.icon), badge.label)),
    p.section && p.section !== label ? h("p", { class: "cb-muted cb-small" }, `In ${String(p.section)}`) : null,
    h("p", { class: "cb-muted cb-small cb-frag-meta" }, meta.join(" · "), typeof p.similarity === "number" ? ` · ${Math.round(Number(p.similarity) * 100)}% similar` : ""),
    ...body,
    p.kind === "moved" && body.length === 0 ? h("p", { class: "cb-muted" }, "The text is the same; it moved to a different place.") : null);
};

/* ------------------------------------------------------------------ update proposals */

const ITEM_KIND: Record<string, { label: string; icon: "tilde" | "check" | "minus" | "alert" | "plus" | "dot" }> = {
  update: { label: "Update", icon: "tilde" },
  citation_remap: { label: "Citation update", icon: "dot" },
  remove: { label: "Removal", icon: "minus" },
  no_change: { label: "No change needed", icon: "check" },
  manual: { label: "Update by hand", icon: "alert" },
  uncovered: { label: "New in the source", icon: "plus" },
};
const ITEM_STATUS: Record<string, { label: string; icon: "check" | "minus" | "clock" | "alert" }> = {
  pending: { label: "Not decided yet", icon: "clock" },
  accepted: { label: "Accepted", icon: "check" },
  rejected: { label: "Rejected", icon: "minus" },
  conflict: { label: "Conflict", icon: "alert" },
  stale: { label: "Edited after the analysis", icon: "alert" },
};
/** Button words per kind: what "accept" means differs when nothing is written to the course. */
const ACCEPT_LABEL: Record<string, string> = { no_change: "Agree, no change", manual: "Mark as handled", uncovered: "Acknowledge" };
const REJECT_LABEL: Record<string, string> = { no_change: "Disagree", manual: "Keep as it is", uncovered: "Skip", remove: "Keep the element", update: "Reject" };
const KIND_NOTE: Record<string, string> = {
  citation_remap: "Only the citations change: the source text this element relies on moved or was renumbered. The wording stays the same.",
  remove: "The source no longer covers this. Accepting removes the element from the course.",
  no_change: "AI found no change needed. Nothing is written to the course unless you ask for changes.",
  manual: "This needs an update by hand: it has not been analysed. Open it in the workspace and edit it there.",
  uncovered: "A new section in the source that no lesson covers yet. Add a lesson for it in the workspace.",
};

function fieldDiff(f: Props): HTMLElement {
  const before = typeof f.before === "string" ? f.before : "";
  const after = typeof f.after === "string" ? f.after : "";
  let kind: "added" | "removed" | "changed" = "changed";
  let body: HTMLElement;
  if (f.before === undefined && f.after !== undefined) {
    kind = "added";
    body = h("ins", {}, sr("added: "), after);
  } else if (f.after === undefined && f.before !== undefined) {
    kind = "removed";
    body = h("del", {}, sr("removed: "), before);
  } else if (before === after) {
    return h("li", { class: "cb-change cb-change-unchanged" }, h("p", { class: "cb-change-label" }, String(f.label)), h("p", { class: "cb-change-text" }, before));
  } else {
    body = wordDiff(before, after);
  }
  return h("li", { class: `cb-change cb-change-${kind}`, "data-field": f.path },
    h("p", { class: "cb-change-label" }, changeBadge(kind), String(f.label)),
    h("p", { class: "cb-change-text" }, body));
}

const UpdateItem: Renderer = (p, ctx, id) => {
  const headingId = uid("upd");
  const kind = String(p.kind);
  const kindInfo = ITEM_KIND[kind] ?? ITEM_KIND.update!;
  const max = Number(p.maxRegenerations ?? 3);
  const used = Number(p.regenerations ?? 0);
  const canDecide = p.canDecide !== false;
  const canRegenerate = p.canRegenerate !== false && ["update", "no_change", "remove", "manual"].includes(kind);
  let status = String(p.status);

  const live = h("p", { class: "cb-sr", role: "status", "aria-live": "polite" });
  const statusEl = h("p", { class: "cb-item-status" });
  const accept = h("button", { type: "button", class: "cb-btn cb-btn-small", "data-decision": "accepted", "data-focus-target": "" }, icon("check"), ACCEPT_LABEL[kind] ?? "Accept");
  const reject = h("button", { type: "button", class: "cb-btn cb-btn-small", "data-decision": "rejected" }, REJECT_LABEL[kind] ?? "Reject");
  const reset = h("button", { type: "button", class: "cb-link", "data-decision": "pending" }, "Undo my decision");
  const note = h("p", { class: "cb-item-note", role: "alert" });

  function paint(): void {
    const info = ITEM_STATUS[status] ?? ITEM_STATUS.pending!;
    statusEl.replaceChildren(icon(info.icon), h("span", {}, sr("Decision: "), info.label));
    statusEl.className = `cb-item-status cb-item-status-${status}`;
    accept.setAttribute("aria-pressed", String(status === "accepted"));
    reject.setAttribute("aria-pressed", String(status === "rejected"));
    accept.disabled = status === "conflict" || status === "stale";
    reset.hidden = status !== "accepted" && status !== "rejected";
    root.dataset.status = status;
    note.textContent = status === "conflict" ? "You edited this element after the analysis. Ask for a new version of it, or reject it."
      : status === "stale" ? "You edited this element after the analysis. Ask for a new version that builds on your edit, or reject this one."
      : "";
    note.hidden = note.textContent === "";
  }

  /** Moves focus to the next undecided item so a keyboard user keeps going. */
  function focusNext(): void {
    const items = [...document.querySelectorAll<HTMLElement>("[data-update-item]")];
    const index = items.indexOf(root);
    const next = items.slice(index + 1).concat(items.slice(0, Math.max(index, 0))).find((el) => el.dataset.status === "pending");
    const target = next?.querySelector<HTMLElement>("[data-focus-target]:not([disabled])");
    if (target) target.focus();
  }

  const decide = (decision: "accepted" | "rejected" | "pending") => {
    const was = status;
    if (decision === "accepted" && (status === "conflict" || status === "stale")) return;
    status = decision;
    paint();
    const words = { accepted: "Accepted", rejected: "Rejected", pending: "Decision removed" }[decision];
    live.textContent = "";
    window.setTimeout(() => (live.textContent = `${words}: ${String(p.label)}.`), 30);
    act(ctx, id, "decide_item", { itemId: p.itemId, decision: { accepted: "accept", rejected: "reject", pending: "reset" }[decision], previous: was });
    if (decision !== "pending") focusNext();
  };
  accept.addEventListener("click", () => decide(status === "accepted" ? "pending" : "accepted"));
  reject.addEventListener("click", () => decide(status === "rejected" ? "pending" : "rejected"));
  reset.addEventListener("click", () => {
    decide("pending");
    accept.focus();
  });

  const controls = h("div", { class: "cb-item-controls" });
  if (canDecide) controls.append(h("div", { role: "group", "aria-label": `Decision for ${String(p.label)}`, class: "cb-item-decision" }, accept, reject, reset));
  else controls.append(h("p", { class: "cb-muted cb-small" }, status === "pending" ? "Decisions open when the analysis is finished." : "This proposal is settled."));

  if (canDecide && canRegenerate) {
    const exhausted = used >= max;
    const textId = uid("ask");
    const toggle = h("button", { type: "button", class: "cb-btn cb-btn-small cb-btn-ghost", "aria-expanded": "false", "aria-controls": textId, disabled: exhausted },
      status === "stale" || status === "conflict" ? "Regenerate against your edit" : "Ask for changes");
    const area = h("textarea", { id: `${textId}-comment`, rows: 3, maxlength: 1000, placeholder: "e.g. Keep the second example" }) as HTMLTextAreaElement;
    const send = h("button", { type: "submit", class: "cb-btn cb-btn-small cb-btn-primary" }, "Ask for a new version");
    const form = h("form", { id: textId, class: "cb-item-ask", hidden: true },
      h("label", { for: `${textId}-comment` }, "What should change?"), area,
      h("div", { class: "cb-actions" }, send));
    const open = (value: boolean) => {
      form.hidden = !value;
      toggle.setAttribute("aria-expanded", String(value));
      if (value) area.focus();
      else toggle.focus();
    };
    toggle.addEventListener("click", () => open(form.hidden));
    area.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        event.stopPropagation();
        open(false);
      }
    });
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      send.disabled = true;
      root.setAttribute("aria-busy", "true");
      live.textContent = "";
      window.setTimeout(() => (live.textContent = `Asking for a new version of ${String(p.label)}…`), 30);
      act(ctx, id, "regenerate_item", { itemId: p.itemId, comment: area.value.trim() });
    });
    controls.append(
      toggle,
      h("span", { class: "cb-muted cb-small cb-item-count" }, exhausted ? `You asked ${used} times. Edit it by hand in the workspace.` : used > 0 ? `Asked for changes ${used} of ${max} times` : ""),
      form
    );
  }

  const fields = (p.fields as Props[] | undefined) ?? [];
  const body: Array<HTMLElement | null> = [];
  if (KIND_NOTE[kind]) body.push(h("p", { class: "cb-item-kind-note" }, KIND_NOTE[kind]!));
  if (p.answerChanged) body.push(h("p", { class: "cb-item-warn cb-item-warn-answer", role: "note" }, icon("alert"), h("strong", {}, "Answer changed. "), "The correct answer is different now. Learners' earlier scores stay on record."));
  else if (p.answerCheck) body.push(h("p", { class: "cb-item-warn cb-item-warn-answer", role: "note" }, icon("alert"), h("strong", {}, "Answer may be wrong. "), "The source changed where this answer comes from. Check the marked answer."));
  for (const flag of (p.flags as string[] | undefined) ?? []) {
    body.push(h("p", { class: "cb-item-warn", role: "note" }, icon("alert"), h("strong", {}, flag.startsWith("Possibly unsupported") ? "Possibly unsupported. " : "Check. "), flag.replace(/^Possibly unsupported:\s*/, "")));
  }
  if (p.reason) body.push(h("p", { class: "cb-item-reason" }, h("strong", {}, "Why: "), String(p.reason)));
  if (fields.length && kind !== "citation_remap") body.push(h("ul", { class: "cb-changes cb-item-fields" }, fields.map(fieldDiff)));
  else if (kind === "update" && fields.length === 0) body.push(h("p", { class: "cb-muted" }, "No visible text change; only the sources of the element are updated."));
  const signals = ((p.signals as string[] | undefined) ?? []).map((x) => SIGNAL_TEXT[x] ?? x);
  if (signals.length) body.push(h("p", { class: "cb-muted cb-small" }, `Source change: ${signals.join(", ")}`));
  if (p.sources) body.push(h("p", { class: "cb-muted cb-small" }, String(p.sources)));

  const root = h("article", { class: `cb-card cb-item cb-item-${kind}`, "aria-labelledby": headingId, "data-update-item": p.itemId, "data-element": p.elementId },
    h("header", { class: "cb-item-head" },
      h("h4", { id: headingId, class: "cb-serif cb-h3" }, String(p.label)),
      h("span", { class: `cb-badge cb-item-kind cb-item-kind-${kind}` }, icon(kindInfo.icon), kindInfo.label),
      p.severity === "major" ? h("span", { class: "cb-tag" }, "Changes the meaning") : null),
    statusEl, note, ...body,
    citations(p.citations, ctx),
    p.href && (kind === "manual" || kind === "uncovered" || used >= max) ? h("a", { class: "cb-link", href: String(p.href) }, "Open in the workspace") : null,
    controls, live);
  paint();
  return root;
};

const COST_LINE = (label: string, micro: unknown): HTMLElement | null =>
  micro === undefined ? null : h("div", {}, h("dt", {}, label), h("dd", {}, usd(micro)));

const ImpactSummary: Renderer = (p) => {
  const headingId = uid("imp");
  const stat = (label: string, value: number, warn = false): HTMLElement =>
    h("div", { class: warn && value > 0 ? "cb-impact-warn" : "" }, h("dt", {}, label), h("dd", {}, warn && value > 0 ? icon("alert") : null, String(value)));
  const sentence = `${p.elements} ${p.elements === 1 ? "element" : "elements"} may need an update${p.lessons ? ` across ${p.lessons} ${p.lessons === 1 ? "lesson" : "lessons"}` : ""}${p.answerChecks > 0 ? `, and ${p.answerChecks} quiz ${p.answerChecks === 1 ? "answer" : "answers"} may now be wrong` : ""}.`;
  return h("section", { class: "cb-card cb-impact", "aria-labelledby": headingId },
    h("h3", { id: headingId, class: "cb-serif cb-h3" }, "Impact of this update"),
    h("p", { class: "cb-impact-sentence" }, sentence),
    h("dl", { class: "cb-facts" },
      stat("Elements to review", p.elements),
      stat("Answers to check", p.answerChecks, true),
      stat("Uncovered source sections", p.uncovered),
      p.remaps !== undefined ? stat("Citations updated automatically", p.remaps) : null,
      p.major !== undefined ? stat("Changes to the meaning", p.major) : null,
      COST_LINE("Estimated cost", p.estimatedCostMicroUsd),
      COST_LINE("Cost so far", p.costMicroUsd)),
    p.learnerImpact ? h("p", { class: "cb-impact-learners" }, h("strong", {}, "Learner impact: "), String(p.learnerImpact)) : null);
};

const StalenessBadge: Renderer = (p) => {
  const state = String(p.state);
  const days = p.days as number | undefined;
  const text = state === "stale" ? (days === undefined ? "Stale" : days === 0 ? "Stale · today" : `Stale · ${days} ${days === 1 ? "day" : "days"}`) : state === "dismissed" ? "Updates dismissed" : "In sync";
  const glyph = icon(state === "in_sync" ? "check" : state === "stale" ? "clock" : "minus");
  const pending = p.pendingElements ? sr(` · ${p.pendingElements} ${p.pendingElements === 1 ? "element" : "elements"} waiting for an update`) : null;
  return p.href
    ? h("a", { class: `cb-fresh cb-fresh-${state}`, href: String(p.href) }, glyph, text, pending)
    : h("span", { class: `cb-fresh cb-fresh-${state}` }, glyph, text, pending);
};

const ACTOR_TYPE_LABEL: Record<string, string> = { user: "Person", system: "System", agent: "Agent" };

const AuditTable: Renderer = (p) => {
  const entries = (p.entries ?? []) as Props[];
  const caption = String(p.caption ?? "Audit trail, newest first");
  if (entries.length === 0) return h("p", { class: "cb-muted cb-audit-empty" }, String(p.emptyText ?? "No entries match."));
  const tableId = uid("audit");
  const rows = entries.flatMap((entry) => {
    const detailId = `${tableId}-${entry.id}`;
    const details = (entry.details ?? []) as Props[];
    const toggle = h("button", { type: "button", class: "cb-btn cb-btn-small", "aria-expanded": "false", "aria-controls": detailId },
      "Details", sr(` for entry ${entry.id}: ${entry.actionLabel}`)) as HTMLButtonElement;
    const detail = h("tr", { id: detailId, class: "cb-audit-detail", hidden: true },
      h("td", { colspan: "5" },
        h("dl", { class: "cb-audit-facts" },
          details.flatMap((d) => [h("dt", {}, String(d.label)), h("dd", { class: d.mono ? "cb-mono" : "" }, String(d.value))]))));
    toggle.addEventListener("click", () => {
      const open = toggle.getAttribute("aria-expanded") !== "true";
      toggle.setAttribute("aria-expanded", String(open));
      detail.hidden = !open;
    });
    const actorType = String(entry.actorType ?? "user");
    return [
      h("tr", { class: "cb-audit-row", "data-entry": String(entry.id) },
        h("td", {}, when(entry.at) ?? "-"),
        h("td", {}, h("span", { class: "cb-audit-action" }, String(entry.actionLabel)), h("span", { class: "cb-muted cb-small cb-mono cb-audit-code" }, String(entry.action))),
        h("td", {}, String(entry.actor), " ", h("span", { class: "cb-tag" }, ACTOR_TYPE_LABEL[actorType] ?? actorType)),
        h("td", {}, entry.summary ? String(entry.summary) : ""),
        h("td", {}, toggle)),
      detail,
    ];
  });
  return h("div", { class: "cb-audit-scroll", role: "region", "aria-label": `${caption} (scrolls sideways on small screens)`, tabindex: "0" },
    h("table", { class: "cb-audit" },
      h("caption", { class: "cb-sr" }, caption),
      h("thead", {}, h("tr", {},
        h("th", { scope: "col" }, "When"),
        h("th", { scope: "col" }, "Action"),
        h("th", { scope: "col" }, "Who"),
        h("th", { scope: "col" }, "What happened"),
        h("th", { scope: "col" }, h("span", { class: "cb-sr" }, "Details")))),
      h("tbody", {}, rows)));
};

const Column: Renderer = (p, _ctx, _id, children) => h("div", { class: `cb-column cb-gap-${p.gap ?? "md"}` }, children);
const Text: Renderer = (p) => h("p", { class: `cb-text cb-text-${p.variant ?? "body"}` }, String(p.text));

export const builderComponents: Record<string, Renderer> = {
  Column,
  Text,
  ChoiceChips,
  SingleChoice,
  DurationSlider,
  LanguagePicker,
  DecideForMe,
  SourceCard,
  CitationChip,
  OutlineDiff,
  GenerationProgress,
  LessonPreviewCard,
  QuizQuestionCard,
  DiffView,
  ApplySummary,
  VersionList,
  SourceConnectionCard,
  RevisionTimeline,
  FragmentChange,
  UpdateItem,
  ImpactSummary,
  StalenessBadge,
  CostMeter,
  AuditTable,
};
