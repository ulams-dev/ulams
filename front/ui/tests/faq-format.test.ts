import { describe, expect, it } from "vitest";
import { codeSpans, formatFaqAnswer } from "../src/lib/faq-format";

describe("formatFaqAnswer", () => {
  it("keeps short answers as one paragraph", () => {
    expect(formatFaqAnswer("Yes. It is free.")).toEqual({ lead: "Yes. It is free.", points: [] });
  });

  it("turns later sentences into points and keeps abbreviations inside a sentence", () => {
    const answer = formatFaqAnswer("Moving data needs a plan. Other data, e.g. purchases, needs a check. Import does not recreate history.");
    expect(answer.lead).toBe("Moving data needs a plan.");
    expect(answer.points.map((p) => p.text)).toEqual(["Other data, e.g. purchases, needs a check.", "Import does not recreate history."]);
  });

  it("joins a very short opening with the next sentence", () => {
    const answer = formatFaqAnswer("Yes. ULAMS supports tenants. Each has a database. Operators create them.");
    expect(answer.lead).toBe("Yes. ULAMS supports tenants.");
    expect(answer.points).toHaveLength(2);
  });

  it("renders a labelled catalogue as labelled points without a lead", () => {
    const answer = formatFaqAnswer("Courses: lessons and video. Sales: cart and coupons. Users: roles and groups. Scope depends on the version.");
    expect(answer.lead).toBe("");
    expect(answer.points[0]).toEqual({ label: "Courses", text: "lessons and video." });
    expect(answer.points[3]).toEqual({ text: "Scope depends on the version." });
  });

  it("splits Chinese sentences", () => {
    const answer = formatFaqAnswer("平台代码以 MIT 许可证开源。自托管时需要承担费用。AI 生成由模型提供商计费。");
    expect(answer.lead).toBe("平台代码以 MIT 许可证开源。");
    expect(answer.points).toHaveLength(2);
  });
});

describe("codeSpans", () => {
  it("marks CLI commands as code", () => {
    expect(codeSpans("Run ulams stack down. Only stack down --purge --yes removes data.")).toEqual([
      { code: false, text: "Run " },
      { code: true, text: "ulams stack down" },
      { code: false, text: ". Only " },
      { code: true, text: "stack down --purge --yes" },
      { code: false, text: " removes data." },
    ]);
  });
});
