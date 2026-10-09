import { describe, expect, it } from "vitest";
import type { Course, Tenant, Topic } from "@ulams/sdk";
import { topicKind } from "@ulams/sdk";
import { completionMode, formatOf, layoutNodes, topicDoc } from "../../src/lib/page-docs.ts";

const tenant = { slug: "coffee", apiUrl: "http://coffee.localhost" } as Tenant;
const course = { id: 7, language: "en" } as Course;

const timeline = { component: "Timeline", props: { items: [{ label: "1555", title: "The first coffeehouses open in Istanbul" }, { label: "1650", title: "Oxford follows" }] } };
const cards = { component: "FlipCards", props: { cards: [{ front: "Why grind finer?", back: "More surface dissolves more." }] } };
const practice = {
  component: "PracticeActivity",
  props: {
    intro: "Dial in a pour-over.",
    toolbox: [{ label: "Brew ratio chart" }],
    challenges: [{ id: "c1", level: 1, prompt: "The cup is sour. What first?", workedSolution: "Grind finer." }],
  },
};

const layoutTopic = (topicable: Record<string, unknown> | null, over: Partial<Topic> = {}) =>
  ({ id: 42, title: "Layout lesson", topicable_type: "Ulams\\TopicTypeLayout\\Models\\LayoutTopic", topicable, ...over }) as unknown as Topic;
const input = (topic: Topic) => ({ tenant, theme: "coffee" as const, course, lesson: undefined, topic, access: true, nextHref: "/next" });

describe("Layout topics in the lesson player", () => {
  it("is a layout topic shown with the interactive format", () => {
    const topic = layoutTopic({ document: [timeline], markdown_fallback: "x" });
    expect(topicKind(topic.topicable_type)).toBe("layout");
    expect(formatOf(topic)).toBe("interactive");
  });

  it("renders the stored nodes as catalogue components, in order", () => {
    const topic = layoutTopic({ document: [timeline, cards, { ...practice, id: "practice-1" }], markdown_fallback: "Fallback" });
    const doc = topicDoc(input(topic));
    expect(doc.children?.map((c) => c.component)).toEqual(["Timeline", "FlipCards", "PracticeActivity"]);
    expect(doc.children?.[2]?.id).toBe("practice-1");
  });

  it("adds the topic description under the layout", () => {
    const doc = topicDoc(input(layoutTopic({ document: [timeline], markdown_fallback: "Fallback" }, { description: "A note." })));
    expect(doc.children?.map((c) => c.component)).toEqual(["Timeline", "Prose"]);
  });

  it("falls back to the Markdown as Prose when the document cannot be rendered", () => {
    const bad: unknown[] = [
      null,
      "text",
      [],
      [{ component: "Hero", props: { title: "Not approved" } }],
      [{ component: "Timeline", props: { items: "nope" } }],
      [timeline, { component: "Callout" }],
      [{ props: {} }],
      [{ component: "LiaScriptLesson", props: { src: "javascript:alert(1)", title: "x" } }],
    ];
    for (const document of bad) {
      const doc = topicDoc(input(layoutTopic({ document, markdown_fallback: "## Fallback text" })));
      expect(doc.children, JSON.stringify(document)).toEqual([{ component: "Prose", props: { markdown: "## Fallback text" } }]);
    }
  });

  it("shows an empty Prose, not a crash, when neither the document nor the fallback is there", () => {
    const doc = topicDoc(input(layoutTopic({})));
    expect(doc.children).toEqual([{ component: "Prose", props: { markdown: "" } }]);
  });

  it("is completed by viewing, or by the first practice attempt when there is a practice activity", () => {
    expect(completionMode(layoutTopic({ document: [timeline, cards], markdown_fallback: "x" }))).toBe("view");
    expect(completionMode(layoutTopic({ document: [timeline, practice], markdown_fallback: "x" }))).toBe("external");
    // a document that falls back to Prose has nothing to attempt
    expect(completionMode(layoutTopic({ document: [practice, { component: "Hero" }], markdown_fallback: "x" }))).toBe("view");
  });

  it("accepts only the approved components", () => {
    expect(layoutNodes({ document: [timeline] })).toHaveLength(1);
    expect(layoutNodes({ document: [{ component: "Stack", props: {}, children: [] }] })).toBeNull();
  });
});
