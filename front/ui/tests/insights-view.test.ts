// @vitest-environment jsdom
import axe from "axe-core";
import { describe, expect, it } from "vitest";
import { dataTable, figureList, rateBar } from "../src/builder/insights-view.ts";

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
