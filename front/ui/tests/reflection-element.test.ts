import { afterEach, beforeAll, describe, expect, it, vi } from "vitest";
import { renderDoc, setupDom } from "./astro-render.ts";
import { registry } from "../src/registry.ts";
import { fallbackText, validateDocument, type UiNode } from "../src/render-core.ts";
import { validate } from "../src/schema.ts";

const ID = "01JBCDEFGHJKMNPQRSTVWXYZ01";
const reflection = (props: Record<string, unknown> = {}): UiNode => ({ component: "FeynmanReflection", props: { assignmentId: ID, ...props } });
const FEEDBACK = {
  correct: "You linked altitude to a slower ripening.",
  missing: "You did not say why cooler air matters.",
  followUp: "How would a lower farm change the cup?",
  citations: [
    { fragmentId: "frg_aaaaaaaaaaaa", label: "§2 Species and altitude" },
    { fragmentId: "frg_bbbbbbbbbbbb", label: "§2 Species and altitude" },
  ],
  remaining: 4,
};

setupDom();
const { default: axe } = await import("axe-core");
beforeAll(async () => {
  await import("../src/elements/reflection.ts");
});

const AXE = { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } };
const violations = async (root: HTMLElement) => (await axe.run(root, AXE)).violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.html).join(" | ")}`);
async function mount(doc: UiNode): Promise<HTMLElement> {
  document.body.innerHTML = `<main><h1 tabindex="-1">Brew ratios</h1>${await renderDoc(doc)}</main>`;
  return document.querySelector("main")!;
}
const window = (document.defaultView ?? globalThis) as unknown as Window & typeof globalThis;
const flush = async () => {
  for (let i = 0; i < 5; i++) await Promise.resolve();
  await new Promise((r) => setTimeout(r, 0));
};

describe("FeynmanReflection: schema and fallback", () => {
  it("takes an assignment id, an optional prompt and a length of at most 2 000", () => {
    expect(validate(registry.FeynmanReflection.props, { assignmentId: ID }).value).toMatchObject({ maxLength: 2000 });
    expect(validate(registry.FeynmanReflection.props, {}).valid).toBe(false);
    expect(validate(registry.FeynmanReflection.props, { assignmentId: ID, maxLength: 2001 }).valid).toBe(false);
    expect(validate(registry.FeynmanReflection.props, { assignmentId: ID, onclick: "x" }).valid).toBe(false);
    expect(validate(registry.FeynmanReflection.props, { assignmentId: ID, prompt: "x".repeat(121) }).valid).toBe(false);
  });

  it("is not a block the generator may store, and falls back to its prompt", () => {
    expect(fallbackText(reflection({ prompt: "Say it again" }))).toBe("Say it again");
    expect(fallbackText(reflection())).toBe("Explain it in your own words");
    expect(validateDocument(reflection())).toEqual([]);
  });
});

describe("FeynmanReflection renders accessibly", () => {
  it("has a labelled textarea with a counter and the privacy sentence before the button, and passes axe", async () => {
    // inside its card, so the heading levels (h2, h3) are the ones a lesson page has
    const root = await mount({ component: "SupportCard", props: { assignmentId: ID, status: "ready", summary: "s" }, children: [reflection()] });
    const text = root.querySelector<HTMLTextAreaElement>("textarea")!;
    expect(root.querySelector(`label[for="${text.id}"]`)?.textContent?.trim()).toBe("Explain it in your own words");
    expect(root.querySelector(".u-fr h3")?.textContent?.trim()).toBe("Explain it in your own words");
    for (const id of text.getAttribute("aria-describedby")!.split(" ")) expect(root.querySelector(`#${id}`)).not.toBeNull();
    expect(root.textContent).toContain("Your text is sent to the AI provider to give feedback. Don't include personal details.");
    expect(text.maxLength).toBe(2000);
    expect(root.querySelector("[data-count]")?.textContent).toBe("0 of 2000 characters");
    expect(root.querySelector("[data-count][aria-live]")).toBeNull();
    const order = [...root.querySelectorAll("[data-privacy], .u-fr__privacy, button[data-submit]")].map((e) => e.tagName);
    expect(order).toEqual(["P", "BUTTON"]);
    const found = await violations(root);
    expect(found, found.join("\n")).toEqual([]);
  });

  it("shows the form only with scripts and says what it needs otherwise", async () => {
    const root = await mount(reflection());
    expect(root.querySelector<HTMLElement>("[data-form]")!.hidden).toBe(false);
    expect(root.querySelector("[data-nojs]")?.hasAttribute("hidden")).toBe(true);
    expect(root.querySelector("[data-nojs]")?.textContent).toBe("Getting feedback needs JavaScript.");
  });

  it("follows the locale and renders nothing without an assignment", async () => {
    const root = await mount(reflection({ locale: "pl" }));
    expect(root.querySelector("button[data-submit]")?.textContent?.trim()).toBe("Uzyskaj informację zwrotną");
    expect(root.querySelector("[data-count]")?.textContent).toBe("0 z 2000 znaków");
    expect((await mount({ component: "FeynmanReflection", props: {} })).querySelector("ulams-reflection")).toBeNull();
  });
});

describe("<ulams-reflection>", () => {
  const calls: Array<{ url: string; method: string; body: string | undefined }> = [];
  const replies: Array<{ status: number; body: unknown }> = [];
  afterEach(() => {
    vi.unstubAllGlobals();
    calls.length = 0;
    replies.length = 0;
  });

  async function setup() {
    vi.stubGlobal(
      "fetch",
      vi.fn(async (url: string, init: RequestInit) => {
        calls.push({ url: String(url), method: String(init.method), body: init.body as string | undefined });
        const next = replies.shift() ?? { status: 200, body: { data: FEEDBACK } };
        return new Response(JSON.stringify(next.body), { status: next.status });
      })
    );
    const root = await mount(reflection());
    const q = <T extends HTMLElement>(s: string) => root.querySelector<T>(s)!;
    const type = (value: string) => {
      const text = q<HTMLTextAreaElement>("[data-text]");
      text.value = value;
      text.dispatchEvent(new window.Event("input", { bubbles: true }));
    };
    const submit = () => q<HTMLFormElement>("[data-form]").dispatchEvent(new window.Event("submit", { bubbles: true, cancelable: true }));
    return { root, q, type, submit };
  }

  it("counts characters as the learner types, without a live region", async () => {
    const { q, type } = await setup();
    type("Cooler air");
    expect(q("[data-count]").textContent).toBe("10 of 2000 characters");
  });

  it("asks for at least ten characters before it sends anything", async () => {
    const { q, type, submit } = await setup();
    type("short");
    submit();
    await flush();
    expect(calls).toEqual([]);
    expect(q("[data-status]").textContent).toBe("Write at least 10 characters first.");
    expect(document.activeElement).toBe(q("[data-text]"));
  });

  it("sends the text once through the BFF and shows what was right, what is missing, the follow-up and sources once each", async () => {
    const { q, type, submit } = await setup();
    type("  Cooler air slows ripening so the sugars build up.  ");
    submit();
    await flush();
    expect(calls).toEqual([{ url: `/bff/api/learner-insights/assignments/${ID}/reflection`, method: "POST", body: '{"text":"Cooler air slows ripening so the sugars build up."}' }]);
    expect(q("[data-result]").hidden).toBe(false);
    expect(q("[data-correct]").textContent).toBe(FEEDBACK.correct);
    expect(q("[data-missing]").textContent).toBe(FEEDBACK.missing);
    expect(q("[data-follow-up]").textContent).toBe(FEEDBACK.followUp);
    expect([...document.querySelectorAll("[data-source-list] li")].map((li) => li.textContent)).toEqual(["§2 Species and altitude"]);
    expect(q("[data-left]").textContent).toBe("4 left today");
    expect(q("[data-result] .u-ailabel").textContent).toContain("AI feedback, based on this course's sources");
    expect(document.activeElement).toBe(q("[data-result]"));
    // the text is not kept on the client after the answer
    expect(q<HTMLTextAreaElement>("[data-text]").value).toBe("");
    expect(q<HTMLFormElement>("[data-form]").hidden).toBe(true);
  });

  it("writes feedback as text, never as markup", async () => {
    replies.push({ status: 200, body: { data: { ...FEEDBACK, correct: "<img src=x onerror=alert(1)> **bold**", citations: [{ fragmentId: "f", label: "<b>x</b>" }] } } });
    const { root, type, submit } = await setup();
    type("An explanation of ten or more characters");
    submit();
    await flush();
    expect(root.querySelector("[data-correct] img")).toBeNull();
    expect(root.querySelector("[data-correct]")?.textContent).toContain("<img");
    expect(root.querySelector("[data-source-list] b")).toBeNull();
  });

  it("explain again empties the result, returns to the form and focuses the box", async () => {
    const { q, type, submit } = await setup();
    type("An explanation of ten or more characters");
    submit();
    await flush();
    q<HTMLButtonElement>("[data-again]").click();
    expect(q("[data-result]").hidden).toBe(true);
    expect(q<HTMLFormElement>("[data-form]").hidden).toBe(false);
    expect(document.activeElement).toBe(q("[data-text]"));
  });

  it("says the daily limit is reached in words, keeps the text and can be tried later", async () => {
    replies.push({ status: 429, body: { message: "Daily limit reached" } });
    const { q, type, submit } = await setup();
    type("An explanation of ten or more characters");
    submit();
    await flush();
    expect(q("[data-status]").textContent).toBe("You have used all of today's feedback requests. Your text is still here; try again tomorrow.");
    expect(q<HTMLTextAreaElement>("[data-text]").value).toBe("An explanation of ten or more characters");
    expect(q("[data-result]").hidden).toBe(true);
    expect(q<HTMLButtonElement>("[data-submit]").disabled).toBe(false);
  });

  it("says AI feedback is unavailable on a 503 and a generic failure on anything else", async () => {
    replies.push({ status: 503, body: { message: "AI is disabled" } }, { status: 500, body: { message: "boom" } });
    const { q, type, submit } = await setup();
    type("An explanation of ten or more characters");
    submit();
    await flush();
    expect(q("[data-status]").textContent).toBe("AI feedback is not available right now. Your text is still here; try again later.");
    submit();
    await flush();
    expect(q("[data-status]").textContent).toBe("We could not get feedback. Your text is still here; try again.");
    expect(q<HTMLTextAreaElement>("[data-text]").value).toBe("An explanation of ten or more characters");
  });

  it("does not send twice while a request is running", async () => {
    const { q, type, submit } = await setup();
    type("An explanation of ten or more characters");
    submit();
    submit();
    expect(q<HTMLButtonElement>("[data-submit]").disabled).toBe(true);
    expect(q("[data-submit]").getAttribute("aria-busy")).toBe("true");
    await flush();
    expect(calls).toHaveLength(1);
  });

  it("never touches browser storage", async () => {
    const set = vi.spyOn(window.Storage.prototype, "setItem");
    const { type, submit } = await setup();
    type("An explanation of ten or more characters");
    submit();
    await flush();
    expect(set).not.toHaveBeenCalled();
    set.mockRestore();
  });
});
