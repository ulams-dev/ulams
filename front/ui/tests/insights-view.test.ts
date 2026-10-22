// @vitest-environment jsdom
import axe from "axe-core";
import { describe, expect, it } from "vitest";
import { dataTable, evidenceCard, figureList, rateBar, type EvidenceCardOptions } from "../src/builder/insights-view.ts";

const violations = async (root: HTMLElement) =>
  (await axe.run(root, { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } })).violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.html).join(" | ")}`);

describe("rate bar", () => {
  it("writes the rate as text and draws a decorative bar", () => {
    const el = rateBar(0.4132, "41%");
    expect(el.querySelector(".cb-rate-text")!.textContent).toBe("41%");
    const bar = el.querySelector<HTMLElement>(".cb-rate-bar")!;
    expect(bar.getAttribute("aria-hidden")).toBe("true");
    expect(bar.querySelector<HTMLElement>(".cb-rate-fill")!.style.width).toBe("41%");
  });

  it("draws no bar when there is no figure, and the text says why", () => {
    const el = rateBar(null, "Not enough data");
    expect(el.textContent).toBe("Not enough data");
    expect(el.querySelector(".cb-rate-bar")).toBeNull();
    expect(el.dataset.rate).toBe("none");
  });

  it("clamps rates outside 0 to 1", () => {
    expect(rateBar(1.7, "100%").querySelector<HTMLElement>(".cb-rate-fill")!.style.width).toBe("100%");
    expect(rateBar(-1, "0%").querySelector<HTMLElement>(".cb-rate-fill")!.style.width).toBe("0%");
  });
});

describe("figure list", () => {
  it("is a description list with the label as term and the value as description", () => {
    const el = figureList([{ label: "Enrolled", value: "48" }, { label: "Recovered", value: "Not enough data", note: "Shown once 10 cases are closed." }], "Progress");
    expect(el.tagName).toBe("DL");
    expect([...el.querySelectorAll("dt")].map((d) => d.textContent)).toEqual(["Enrolled", "Recovered"]);
    expect(el.querySelectorAll("dd")[1]!.textContent).toContain("Shown once 10 cases are closed.");
  });
});

describe("data table", () => {
  const options = {
    caption: "Sections ranked by struggle rate",
    scrollLabel: "Sections (scrolls sideways on small screens)",
    columns: [{ label: "Section" }, { label: "Learners", numeric: true }, { label: "Struggled" }],
    rows: [
      { id: "a", cells: ["2.3 Question 3", "29", rateBar(0.41, "41%")] },
      { id: "b", withheld: true, cells: ["4.3 Rosetta", "Hidden: fewer than 10 learners", ""] },
    ],
  };

  it("has a caption, column headers, a row header and a keyboard reachable scroll region", () => {
    const el = dataTable(options);
    expect(el.getAttribute("role")).toBe("region");
    expect(el.getAttribute("aria-label")).toBe(options.scrollLabel);
    expect(el.getAttribute("tabindex")).toBe("0");
    expect(el.querySelector("caption")!.textContent).toBe(options.caption);
    expect([...el.querySelectorAll("thead th")].every((th) => th.getAttribute("scope") === "col")).toBe(true);
    expect(el.querySelector("tbody tr th")!.getAttribute("scope")).toBe("row");
    expect(el.querySelector("tbody tr th")!.textContent).toBe("2.3 Question 3");
  });

  it("marks a withheld row with words, not only styling", () => {
    const el = dataTable(options);
    const row = el.querySelector<HTMLElement>('[data-row="b"]')!;
    expect(row.className).toContain("cb-row-withheld");
    expect(row.textContent).toContain("Hidden: fewer than 10 learners");
  });

  it("passes axe", async () => {
    document.body.innerHTML = "";
    const host = document.createElement("main");
    host.append(figureList([{ label: "Enrolled", value: "48" }], "Progress"), dataTable(options));
    document.body.append(host);
    expect(await violations(host)).toEqual([]);
  });
});

describe("evidence card", () => {
  const options = (over: Partial<EvidenceCardOptions> = {}): EvidenceCardOptions => ({
    title: "Why this was proposed",
    intro: "Results of 42 learners in the last 30 days, as numbers.",
    figuresLabel: "Learner evidence in numbers",
    figures: [{ label: "Struggled here", value: "38%", rate: 0.38 }, { label: "Recovered after help", value: "Not enough data", rate: null }, { label: "Tutor questions declined", value: "3" }],
    reasonsTitle: "Most common reasons",
    reasons: ["repeated failed attempts (16)"],
    noReasons: "No single reason stands out.",
    wrong: { title: "Wrong answers learners chose", intro: "Share of learners.", table: { caption: "Wrong answers", scrollLabel: "Wrong answers (scrolls sideways)", columns: [{ label: "Answer" }, { label: "Share", numeric: true }], rows: [{ id: "o2", cells: ["1:17 <b>", "38%"] }] } },
    sent: { title: "What the AI receives", text: "The section and the numbers above." },
    ...over,
  });

  it("is a labelled section with the title, numbers as text and decorative bars", () => {
    const card = evidenceCard(options());
    document.body.replaceChildren(card);

    expect(card.tagName).toBe("SECTION");
    expect(card.getAttribute("aria-labelledby")).toBe(card.querySelector("h2")!.id);
    expect(card.querySelector("h2")!.textContent).toBe("Why this was proposed");
    expect([...card.querySelectorAll("dt")].map((d) => d.textContent)).toEqual(["Struggled here", "Recovered after help", "Tutor questions declined"]);
    expect(card.querySelector("dl")!.getAttribute("aria-label")).toBe("Learner evidence in numbers");
    expect(card.querySelectorAll(".cb-rate")).toHaveLength(2);
    expect(card.querySelectorAll(".cb-rate-bar")).toHaveLength(1);
    expect(card.querySelectorAll("dd")[2]!.querySelector(".cb-rate")).toBeNull();
    expect([...card.querySelectorAll("li")].map((li) => li.textContent)).toEqual(["repeated failed attempts (16)"]);
    expect(card.textContent).toContain("What the AI receives");
  });

  it("puts text in as text and keeps the headings in order at any level", () => {
    const card = evidenceCard(options({ level: 4, lang: "pl" }));
    expect(card.querySelector("table")!.textContent).toContain("1:17 <b>");
    expect(card.querySelector("table b")).toBeNull();
    expect([...card.querySelectorAll("h2, h3, h4, h5")].map((h) => h.tagName)).toEqual(["H4", "H5", "H5", "H5"]);
    expect(card.getAttribute("lang")).toBe("pl");
  });

  it("says when no reason stands out and leaves out what it was not given", () => {
    const card = evidenceCard(options({ reasons: [], wrong: null, sent: null }));
    expect(card.querySelector("ul")).toBeNull();
    expect(card.textContent).toContain("No single reason stands out.");
    expect(card.querySelector("table")).toBeNull();
    expect(card.textContent).not.toContain("What the AI receives");
  });

  it("passes axe", async () => {
    const card = evidenceCard(options());
    document.body.replaceChildren(h1(), card);
    expect(await violations(document.body)).toEqual([]);
  });
});

function h1(): HTMLElement {
  const el = document.createElement("main");
  const heading = document.createElement("h1");
  heading.textContent = "Course insights";
  el.append(heading);
  return el;
}
