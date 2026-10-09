import { describe, expect, it } from "vitest";
import { sanitizeHtml } from "../src/lib/html.ts";

describe("sanitizeHtml (API rich text)", () => {
  it("keeps formatting tags without attributes", () => {
    expect(sanitizeHtml('<p class="x" onclick="y">Hi <strong>you</strong></p><ol><li>a</li></ol>')).toBe("<p>Hi <strong>you</strong></p><ol><li>a</li></ol>");
  });
  it("drops scripts, styles, iframes and unknown tags", () => {
    const out = sanitizeHtml('<script>alert(1)</script><img src=x onerror=alert(1)><a href="javascript:x">l</a><style>*{}</style>');
    expect(out).not.toMatch(/<script|<img|<a |onerror|<style/);
    expect(out).toContain("l");
  });
  it("escapes broken markup instead of letting it through", () => {
    const out = sanitizeHtml("<<b>img src=x onerror=alert(1)>");
    expect(out).toBe("&lt;<b>img src=x onerror=alert(1)&gt;");
  });
  it("keeps entities and escapes bare ampersands", () => {
    expect(sanitizeHtml("Q&amp;A & more")).toBe("Q&amp;A &amp; more");
  });
});
