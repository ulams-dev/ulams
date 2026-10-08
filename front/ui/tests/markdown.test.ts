import { describe, expect, it } from "vitest";
import { renderMarkdown, stripLeadingTitle } from "../src/lib/markdown.ts";

describe("renderMarkdown (untrusted course content)", () => {
  it("escapes raw HTML", () => {
    const html = renderMarkdown('Hi <script>alert(1)</script> <img src=x onerror="alert(1)">');
    expect(html).not.toContain("<script");
    expect(html).not.toContain("<img src=x");
    expect(html).toContain("&lt;script&gt;");
  });

  it("drops unsafe links and images, keeps safe ones", () => {
    const html = renderMarkdown("[a](javascript:alert(1)) [b](https://example.com) ![c](javascript:x)");
    expect(html).not.toContain("javascript:");
    expect(html).toContain('href="https://example.com"');
    expect(html).toContain('rel="noopener noreferrer"');
  });

  it("renders block and inline math as MathML", () => {
    const html = renderMarkdown("Ratio $x^2$ here.\n\n$$\nEY = \\frac{a}{b}\n$$\n");
    expect(html).toContain("<math");
    expect(html).toContain('class="u-math"');
    expect(html).toContain("<mfrac>");
  });

  it("wraps tables in a scrollable region with header scope", () => {
    const html = renderMarkdown("| a | b |\n|---|---|\n| 1 | 2 |");
    expect(html).toContain('role="region"');
    expect(html).toContain('<th scope="col">a</th>');
  });

  it("does not treat prices as math", () => {
    expect(renderMarkdown("Costs $5 and $10 today.")).not.toContain("<math");
  });

  it("strips a heading that repeats the page title", () => {
    expect(stripLeadingTitle("# Ratios & extraction\n\nBody", "Ratios & extraction")).toBe("\nBody");
    expect(stripLeadingTitle("# Other\n\nBody", "Ratios")).toBe("# Other\n\nBody");
  });
});
