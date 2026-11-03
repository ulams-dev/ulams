/**
 * Course Insights page (/studio/courses/:id/insights): progress and completion, quiz performance, who needs
 * support, the sections that confuse people, personal help and nudges, and, for people allowed to see
 * individuals, the learners who struggle with their activity. Everything comes from the analytics API through
 * the studio BFF; groups below the minimum size never arrive (the server withholds them). Rates are numbers
 * in text with a decorative bar, states are announced, and nothing animates under reduced motion.
 */
import {
  ApiError,
  createInsightsAnalyticsClient,
  createInsightsModuleClient,
  INSIGHTS_PERIODS,
  type CourseElements,
  type CourseInsightsFull,
  type ImproveResult,
  type InsightsAnalyticsClient,
  type InsightsPeriod,
  type StrugglingLearner,
} from "@ulams/sdk";
import { dataTable, evidenceCard, figureList, rateBar } from "@ulams/ui/builder/insights-view.ts";
import { h, uid } from "@ulams/ui/builder/dom.ts";
import { announce } from "./common.ts";
import { fill, type InsightsLocale } from "./insights-i18n.ts";
import { costText, evidenceCardOptions, improveErrorText, improveStrings, proposalStateText, reviewHref } from "./improve-model.ts";
import {
  elementRows,
  formatterFor,
  helpFigures,
  learnerRows,
  periodLabel,
  progressFigures,
  quizFigures,
  supportFigures,
  timelineRows,
  type Formatter,
} from "./insights-model.ts";

export const insightsClient = () => createInsightsAnalyticsClient({ baseUrl: "/studio/api", prefix: "/learner-insights" });
const moduleClient = () => createInsightsModuleClient({ baseUrl: "/studio/api", prefix: "/learner-insights" });

const isForbidden = (e: unknown): boolean => e instanceof ApiError && e.status === 403;

export function mountInsights(root: HTMLElement): void {
  const courseId = Number(root.dataset.course);
  const locale = (root.dataset.locale ?? "en") as InsightsLocale;
  const adminUrl = root.dataset.adminUrl ?? "";
  const f: Formatter = formatterFor(locale);
  const t = f.t;
  const im = improveStrings(locale);
  const api: InsightsAnalyticsClient = insightsClient();

  let period: InsightsPeriod = 30;
  let requestId = 0;
  let improveRequest = 0;

  const selectId = uid("ins-period");
  const select = h("select", { id: selectId, class: "cb-select", name: "period" },
    INSIGHTS_PERIODS.map((p) => h("option", { value: String(p), selected: p === period }, periodLabel(t, p)))) as HTMLSelectElement;
  const controls = h("div", { class: "st-ins-controls" },
    h("div", { class: "st-field" }, h("label", { for: selectId }, t.periodLabel), select));
  const body = h("div", { class: "st-ins-body", "aria-live": "polite" });
  root.replaceChildren(controls, body);

  select.addEventListener("change", () => {
    period = Number(select.value) as InsightsPeriod;
    void load(true);
  });

  const section = (title: string, ...children: Array<Node | null>): HTMLElement => {
    const id = uid("ins-h");
    return h("section", { class: "cb-card st-ins-section", "aria-labelledby": id }, h("h2", { id, class: "cb-h3" }, title), children);
  };
  const note = (text: string): HTMLElement => h("p", { class: "cb-muted" }, text);
  const status = (text: string, role: "status" | "alert" = "status"): HTMLElement => h("p", { class: role === "alert" ? "cb-error" : "cb-muted", role }, text);
  const figures = (data: Array<{ label: string; value: string; note?: string }>, label: string): HTMLElement => figureList(data, label);

  const privacy = (n: number): HTMLElement => {
    const id = uid("ins-h");
    return h("aside", { class: "cb-card st-ins-privacy", "aria-labelledby": id }, h("h2", { id, class: "cb-h3" }, t.privacyTitle), h("p", { class: "cb-muted" }, fill(t.privacyText, { n })));
  };

  function showLoading(): void {
    body.setAttribute("aria-busy", "true");
    if (!body.firstChild) body.replaceChildren(h("div", { class: "cb-skeleton", "aria-hidden": "true" }, h("span"), h("span"), h("span")), status(t.loading));
  }

  function showError(text: string, retry = true): void {
    body.setAttribute("aria-busy", "false");
    const again = h("button", { type: "button", class: "cb-btn cb-btn-small" }, t.retry);
    again.addEventListener("click", () => void load(true));
    body.replaceChildren(h("div", { class: "cb-card" }, status(text, "alert"), retry ? again : null));
  }

  function showModuleOff(): void {
    body.setAttribute("aria-busy", "false");
    body.replaceChildren(h("div", { class: "cb-card", "data-state": "module-off" }, note(t.moduleOff), adminUrl ? h("a", { class: "cb-btn cb-btn-small", href: adminUrl }, t.moduleOffLink) : null));
  }

  function elementsSection(data: CourseElements): HTMLElement {
    const rows = elementRows(f, data);
    if (rows.length === 0) return section(t.elementsTitle, note(t.elementsIntro), note(t.elementsEmpty));
    const panel = h("div", { class: "st-improve", id: uid("ins-improve"), "data-improve": "" });
    const table = dataTable({
      caption: t.elementsCaption,
      scrollLabel: t.elementsScroll,
      columns: [
        { label: t.colSection },
        { label: t.colActive, numeric: true },
        { label: t.colStruggle },
        { label: t.colHelped },
        { label: t.colRecovered },
        { label: t.colReasons },
        { label: im.colAction },
      ],
      rows: rows.map((r) => {
        if (r.withheld) return { id: r.key, withheld: true, cells: [r.label, h("span", {}, fill(t.withheldRow, { n: data.minGroupSize })), "", "", "", "", ""] };
        let action: Node | string = "";
        if (r.improvable) {
          const button = h("button", { type: "button", class: "cb-btn cb-btn-small", "data-improve-button": "", "aria-controls": panel.id, "aria-expanded": "false" }, im.button, h("span", { class: "cb-sr" }, ` – ${r.label}`));
          button.addEventListener("click", () => void openImprove(panel, r.key, r.label, data.minGroupSize, button));
          action = button;
        }
        return { id: r.key, cells: [r.label, r.active!, rateBar(r.struggle!.rate, r.struggle!.text), rateBar(r.helped!.rate, r.helped!.text), rateBar(r.recovered!.rate, r.recovered!.text), r.reasons!, action] };
      }),
    });
    return section(t.elementsTitle, note(t.elementsIntro), table, panel);
  }

  /** "Propose an improvement": the estimate and the evidence first, the proposal only after a confirmation. */
  async function openImprove(panel: HTMLElement, key: string, label: string, minGroup: number, trigger: HTMLElement): Promise<void> {
    const mine = ++improveRequest;
    const id = uid("ins-h");
    const heading = h("h3", { id, class: "cb-h3", tabindex: "-1" }, fill(im.panelTitle, { label }));
    const content = h("div", { class: "st-improve-body", lang: im.htmlLang !== t.htmlLang ? im.htmlLang : undefined }, status(im.checking));
    document.querySelectorAll("[data-studio-insights] [data-improve-button]").forEach((b) => b.setAttribute("aria-expanded", "false"));
    trigger.setAttribute("aria-expanded", "true");
    panel.replaceChildren(heading, content);
    panel.setAttribute("aria-busy", "true");
    heading.focus();

    const close = (): void => {
      improveRequest++;
      panel.replaceChildren();
      trigger.setAttribute("aria-expanded", "false");
      trigger.focus();
    };
    const closeButton = (text: string): HTMLElement => {
      const button = h("button", { type: "button", class: "cb-btn cb-btn-small" }, text);
      button.addEventListener("click", close);
      return button;
    };
    const failure = (error: unknown): HTMLElement => {
      const text = improveErrorText(im, error, minGroup);
      announce(text);
      return h("div", { class: "st-improve-result" }, status(text, "alert"), h("div", { class: "cb-actions" }, closeButton(im.close)));
    };
    const finished = (result: ImproveResult): HTMLElement => {
      if (result.state === "estimate") throw new Error("unexpected estimate");
      const existing = result.state === "kept_existing";
      const proposal = result.proposal;
      const text = existing ? fill(im.existing, { number: proposal.number }) : `${fill(im.created, { number: proposal.number, status: proposalStateText(im, proposal.status) })}`;
      announce(text);
      const message = h("p", { class: "cb-muted", role: "status", tabindex: "-1", "data-improve-result": "" }, text);
      queueMicrotask(() => message.focus());
      return h("div", { class: "st-improve-result" }, message,
        h("div", { class: "cb-actions" }, h("a", { class: "cb-btn cb-btn-primary cb-btn-small", href: reviewHref(proposal.sessionId, proposal.id, locale) }, existing ? im.existingReview : im.createdReview), closeButton(im.close)));
    };

    try {
      const first = await api.improve(courseId, key, { period });
      if (mine !== improveRequest) return;
      if (first.state !== "estimate") {
        content.replaceChildren(finished(first));
        return;
      }
      const confirm = h("button", { type: "button", class: "cb-btn cb-btn-primary cb-btn-small" }, im.confirm);
      const cancel = closeButton(im.cancel);
      const result = h("div", { "data-improve-result-slot": "" });
      confirm.addEventListener("click", async () => {
        confirm.setAttribute("disabled", "");
        cancel.setAttribute("disabled", "");
        panel.setAttribute("aria-busy", "true");
        result.replaceChildren(status(im.creating));
        try {
          const done = await api.improve(courseId, key, { confirmEstimate: true, period });
          if (mine !== improveRequest) return;
          content.replaceChildren(finished(done));
        } catch (error) {
          if (mine !== improveRequest) return;
          confirm.removeAttribute("disabled");
          cancel.removeAttribute("disabled");
          result.replaceChildren(status(improveErrorText(im, error, minGroup), "alert"));
          announce(improveErrorText(im, error, minGroup));
        } finally {
          panel.setAttribute("aria-busy", "false");
        }
      });
      content.replaceChildren(
        evidenceCard(evidenceCardOptions(f, im, first.evidence, { sent: true, level: 4 })),
        h("p", {}, fill(im.estimate, { cost: costText(first.estimateUsd) })),
        h("div", { class: "cb-actions" }, confirm, cancel),
        result
      );
    } catch (error) {
      if (mine !== improveRequest) return;
      content.replaceChildren(failure(error));
    } finally {
      panel.setAttribute("aria-busy", "false");
    }
  }

  /** The list of learners and the activity panel; both are individual data. */
  function individualsSection(learners: StrugglingLearner[]): HTMLElement {
    if (learners.length === 0) return section(t.individualsTitle, note(t.individualsIntro), note(t.individualsEmpty));
    const panel = h("div", { class: "st-ins-activity", id: uid("ins-activity"), "data-activity": "" });
    const rows = learnerRows(f, learners);
    const table = dataTable({
      caption: t.individualsCaption,
      scrollLabel: t.individualsScroll,
      columns: [{ label: t.colLearner }, { label: t.colStatus }, { label: t.colWhy }, { label: t.colSince }, { label: t.colActivity }],
      rows: rows.map((r) => {
        const button = h("button", { type: "button", class: "cb-btn cb-btn-small", "aria-controls": panel.id, "aria-expanded": "false" }, t.showActivity, h("span", { class: "cb-sr" }, ` – ${r.name}`));
        button.addEventListener("click", () => void showActivity(panel, r.userId, r.name, button));
        return { id: String(r.userId), cells: [r.name, r.status, r.reasons, r.since, button] };
      }),
    });
    return section(t.individualsTitle, note(t.individualsIntro), table, panel);
  }

  async function showActivity(panel: HTMLElement, userId: number, name: string, trigger: HTMLElement): Promise<void> {
    const id = uid("ins-h");
    const heading = h("h3", { id, class: "cb-h3", tabindex: "-1" }, fill(t.activityTitle, { name }));
    // the heading stays in place while the content under it changes, so the focus is not lost
    const content = h("div", {}, status(t.activityLoading));
    document.querySelectorAll("[data-studio-insights] button[aria-expanded]").forEach((b) => b.setAttribute("aria-expanded", "false"));
    trigger.setAttribute("aria-expanded", "true");
    panel.replaceChildren(heading, content);
    panel.setAttribute("aria-busy", "true");
    heading.focus();
    try {
      const rows = timelineRows(f, await api.timeline(userId, courseId));
      content.replaceChildren(
        rows.length === 0
          ? note(t.activityEmpty)
          : dataTable({
              caption: t.activityCaption,
              scrollLabel: t.activityScroll,
              columns: [{ label: t.colWhen }, { label: t.colWhat }, { label: t.colWhere }],
              rows: rows.map((r) => ({ id: r.key, cells: [r.when, r.what, r.where] })),
            })
      );
    } catch (e) {
      content.replaceChildren(status(isForbidden(e) ? t.activityForbidden : t.activityError, "alert"));
    } finally {
      panel.setAttribute("aria-busy", "false");
    }
  }

  async function individuals(mine: number): Promise<void> {
    let node: HTMLElement;
    try {
      node = individualsSection(await api.learners(courseId));
    } catch (e) {
      node = isForbidden(e) ? section(t.individualsTitle, note(t.individualsForbidden)) : section(t.individualsTitle, status(t.error, "alert"));
    }
    if (mine === requestId) body.append(node);
  }

  function showData(summary: CourseInsightsFull, elements: CourseElements): void {
    body.setAttribute("aria-busy", "false");
    body.replaceChildren(
      privacy(summary.minGroupSize),
      section(t.progressTitle, note(t.progressIntro), figures(progressFigures(f, summary), t.progressTitle)),
      section(t.quizTitle, summary.quiz.attempts === 0 ? note(t.quizEmpty) : figures(quizFigures(f, summary), t.quizTitle)),
      section(t.supportTitle, note(t.supportIntro), figures(supportFigures(f, summary), t.supportTitle)),
      elementsSection(elements),
      section(t.helpTitle, note(t.helpIntro), figures(helpFigures(f, summary), t.helpTitle))
    );
  }

  async function load(announceIt = false): Promise<void> {
    const mine = ++requestId;
    showLoading();
    const [off, summary, elements] = await Promise.allSettled([moduleClient().enabled(), api.summary(courseId, period), api.elements(courseId, period)]);
    if (mine !== requestId) return;

    if (off.status === "fulfilled" && !off.value) return showModuleOff();
    if (summary.status === "rejected") return showError(isForbidden(summary.reason) ? t.forbidden : t.error, !isForbidden(summary.reason));
    if (elements.status === "rejected") return showError(isForbidden(elements.reason) ? t.forbidden : t.error, !isForbidden(elements.reason));

    if (summary.value.smallGroup) {
      body.setAttribute("aria-busy", "false");
      body.replaceChildren(privacy(summary.value.minGroupSize), h("div", { class: "cb-card", "data-state": "small-group" }, note(fill(t.smallGroup, { enrolled: summary.value.enrolled, n: summary.value.minGroupSize }))));
    } else {
      showData(summary.value, elements.value);
      void individuals(mine);
    }
    if (announceIt) announce(fill(t.statusAnnounce, { period: periodLabel(t, period) }));
  }

  void load();
}

const root = typeof document === "undefined" ? null : document.querySelector<HTMLElement>("[data-studio-insights]");
if (root) mountInsights(root);
