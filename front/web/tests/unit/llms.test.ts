import { describe, expect, it } from "vitest";
import type { Course } from "@ulams/sdk";
import { academyLlmsTxt, academyName, courseMarkdown, formatPrice, platformLlmsTxt, toMarkdown } from "../../src/lib/llms.ts";
import { FIXTURES } from "./fixtures.ts";

const academy = { name: "The Coffee Atlas", origin: "https://coffee.example.org", currency: "PLN" };
const coffee = FIXTURES.coffee.course as unknown as Course;

describe("academy llms.txt", () => {
  it("lists the public courses with links to their Markdown pages", () => {
    const txt = academyLlmsTxt({ ...academy, courses: [coffee] });

    expect(txt.startsWith("# The Coffee Atlas\n")).toBe(true);
    expect(txt).toContain("## Courses");
    expect(txt).toContain(`[${coffee.title}](https://coffee.example.org/llms/courses/${coffee.id}.md)`);
    expect(txt).toContain("89.00 PLN");
    expect(txt).toContain("## Optional");
  });

  it("is still a valid file for an academy without courses", () => {
    const txt = academyLlmsTxt({ ...academy, courses: [] });

    expect(txt).toContain("This academy has no public courses yet.");
    expect(txt).not.toContain("](https://coffee.example.org/llms/courses/");
  });

  it("names a free course as free and falls back to the slug without a company name", () => {
    expect(academyLlmsTxt({ ...academy, courses: [{ id: 3, title: "Intro", product: null }] })).toContain("(free)");
    expect(academyName(null, "coffee")).toBe("coffee");
    expect(academyName({ global: { companyName: " Atlas " } }, "coffee")).toBe("Atlas");
  });

  it("describes the platform host separately", () => {
    expect(platformLlmsTxt("https://ulams.app")).toContain("Each academy serves its own /llms.txt");
  });
});

describe("course Markdown", () => {
  const md = courseMarkdown(coffee, academy);

  it("starts with the title, the lead and the facts", () => {
    expect(md.startsWith(`# ${coffee.title}\n`)).toBe(true);
    expect(md).toContain("- Page: https://coffee.example.org/courses/" + coffee.id);
    expect(md).toContain("- Price: 89.00 PLN");
    expect(md).toContain("- Teachers: Inés Duarte, Tomasz Wierzba");
  });

  it("carries the description and one syllabus entry per lesson", () => {
    expect(md).toContain("## About");
    expect(md).toContain("## What you will learn");
    expect(md).toContain("## Syllabus");
    for (const lesson of coffee.lessons ?? []) expect(md).toContain(`### ${lesson.title}`);
  });

  it("states each topic's format and length", () => {
    expect(md).toMatch(/^- .+ \((reading|video|audio|quiz|interactive)[^)]*\)$/m);
  });

  it("never leaves HTML or a template marker behind", () => {
    expect(md).not.toMatch(/<[a-z]/i);
    expect(md).not.toContain("undefined");
    expect(md).not.toContain("null");
  });
});

describe("text helpers", () => {
  it("converts the HTML a rich text editor writes and leaves Markdown alone", () => {
    // headings move one level down: the page title is the only H1 and the description sits under "About"
    expect(toMarkdown("<h2>Goals</h2><p>Learn <strong>fast</strong> &amp; well.</p><ul><li>One</li><li>Two</li></ul><a href=\"https://x.test\">more</a>")).toBe(
      "### Goals\n\nLearn **fast** & well.\n\n- One\n- Two\n\n[more](https://x.test)"
    );
    expect(toMarkdown("**bold** text\n\n- a\n- b")).toBe("**bold** text\n\n- a\n- b");
    expect(toMarkdown(null)).toBe("");
  });

  it("formats minor units", () => {
    expect(formatPrice(8900, "PLN")).toBe("89.00 PLN");
    expect(formatPrice(5)).toBe("0.05");
    expect(formatPrice(null)).toBe("");
  });
});
