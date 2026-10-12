import { describe, expect, it } from "vitest";
import { landingDocs } from "../../src/lib/docs.ts";

type Node = { component: string; props?: Record<string, unknown> };

const doc = landingDocs.platform!;
const main = doc.children!.find((node) => node.component === "Main")!;
const sections = main.children as Node[];
const featureList = sections.find((node) => node.component === "FeatureList")!;

describe("concise platform feature list", () => {
  it("summarises customer academies without a separate animated brand story", () => {
    const items = featureList.props!.items as Array<{ title: string; text: string }>;
    expect(items).toHaveLength(3);
    expect(items.find((item) => item.title === "A site per customer")?.text).toContain("own subdomain");
    expect(sections.some((section) => section.component === "WhiteLabelStory")).toBe(false);
  });

  it("keeps demos and developer workflow on Overview, with comparison linked separately", () => {
    const order = sections.map((section) => section.component);
    expect(order.indexOf("Showcase")).toBeLessThan(order.indexOf("WorkflowShowcase"));
    expect(order).not.toContain("ComparisonTable");
    expect((doc.children!.find((node) => node.component === "SiteHeader")!.props!.links as Array<{ href: string }>)[1]!.href).toBe("/comparison/");
  });
});
