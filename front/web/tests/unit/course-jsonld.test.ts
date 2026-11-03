import { describe, expect, it } from "vitest";
import { courseDescription, courseJsonLd, workload } from "../../src/lib/course-jsonld.ts";
import { courseModel } from "../../src/lib/view-model.ts";
import type { Course } from "@ulams/sdk";

const base = { id: 7, title: "Brewing", summary: "Brew better coffee at home.", language: "en", lessons: [] } as unknown as Course;
const model = (extra: Record<string, unknown> = {}, currency = "EUR") => courseModel({ ...base, ...extra } as Course, currency);
const input = (course = model()) => ({ course, tenantName: "The Coffee Atlas", origin: "https://coffee.example", language: "en" });

describe("Course JSON-LD", () => {
  it("has the shape of a schema.org Course with the tenant as provider", () => {
    const ld = courseJsonLd(input());
    expect(ld).toMatchObject({
      "@context": "https://schema.org",
      "@type": "Course",
      "@id": "https://coffee.example/courses/7#course",
      name: "Brewing",
      description: "Brew better coffee at home.",
      url: "https://coffee.example/courses/7",
      inLanguage: "en",
      provider: { "@type": "Organization", name: "The Coffee Atlas", url: "https://coffee.example/" },
    });
  });

  it("says the course is online and self-paced", () => {
    const instance = courseJsonLd(input()).hasCourseInstance as Record<string, unknown>;
    expect(instance["@type"]).toBe("CourseInstance");
    expect(instance.courseMode).toEqual(["online", "self-paced"]);
    expect(instance.eventAttendanceMode).toBe("https://schema.org/OnlineEventAttendanceMode");
  });

  it("has no offer for a course without a price, and none for a price of zero", () => {
    expect(courseJsonLd(input())).not.toHaveProperty("offers");
    expect(courseJsonLd(input(model({ product: { id: 1, name: "x", price: 0 } })))).not.toHaveProperty("offers");
    expect(JSON.stringify(courseJsonLd(input()))).not.toMatch(/price/i);
  });

  it("has an offer in major units with the currency when the course has a price", () => {
    const ld = courseJsonLd(input(model({ product: { id: 1, name: "x", price: 8900 } })));
    expect(ld.offers).toEqual({ "@type": "Offer", price: "89.00", priceCurrency: "EUR", category: "Paid", availability: "https://schema.org/InStock", url: "https://coffee.example/courses/7" });
    const pln = courseJsonLd(input(model({ product: { id: 1, name: "x", price: 12345 } }, "pln")));
    expect((pln.offers as Record<string, string>).price).toBe("123.45");
    expect((pln.offers as Record<string, string>).priceCurrency).toBe("PLN");
  });

  it("derives the workload from the hours, else from the lesson minutes, else leaves it out", () => {
    expect(workload({ hours: 9, minutes: 0 })).toBe("PT9H");
    expect(workload({ hours: 1.5, minutes: 0 })).toBe("PT1H30M");
    expect(workload({ hours: 0.5, minutes: 0 })).toBe("PT30M");
    expect(workload({ minutes: 45 })).toBe("PT45M");
    expect(workload({ minutes: 0 })).toBeUndefined();
    expect(courseJsonLd(input()).hasCourseInstance).not.toHaveProperty("courseWorkload");
    expect((courseJsonLd(input(model({ hours_to_complete: 3 }))).hasCourseInstance as Record<string, string>).courseWorkload).toBe("PT3H");
  });

  it("always has a description: the summary, else the start of the description without markdown, else the title", () => {
    expect(courseDescription({ title: "T", summary: "S" })).toBe("S");
    expect(courseDescription({ title: "T", description: "## Heading\n\nSome **bold** text with a [link](https://x.test) and ![img](a.png)." })).toBe("Heading Some bold text with a link and .");
    expect(courseDescription({ title: "T" })).toBe("T");
    const long = courseDescription({ title: "T", description: "word ".repeat(200) });
    expect(long.length).toBeLessThanOrEqual(300);
    expect(long.endsWith("…")).toBe(true);
  });

  it("uses an absolute image URL and the level only when the course has them", () => {
    expect(courseJsonLd(input())).not.toHaveProperty("image");
    const ld = courseJsonLd(input(model({ image_url: "/storage/course/1.png", level: "Beginner" })));
    expect(ld.image).toBe("https://coffee.example/storage/course/1.png");
    expect(ld.educationalLevel).toBe("Beginner");
    expect(courseJsonLd(input(model({ image_url: "http://storage.localhost/c.png" }))).image).toBe("http://storage.localhost/c.png");
  });

  it("takes the language the page content is in", () => {
    expect(courseJsonLd({ ...input(), language: "pl" }).inLanguage).toBe("pl");
  });

  it("survives a JSON round trip with hostile text, and a script-closing sequence is escaped the way the layout does", () => {
    const hostile = model({ title: "</script><script>alert(1)</script>", summary: "a < b & \"c\"" });
    const ld = courseJsonLd({ ...input(hostile), tenantName: "</script>" });
    const serialized = JSON.stringify([ld]).replace(/</g, "\\u003c");
    expect(serialized).not.toContain("<");
    expect(JSON.parse(serialized)[0].name).toBe("</script><script>alert(1)</script>");
  });

  it("trims a trailing slash of the origin", () => {
    expect(courseJsonLd({ ...input(), origin: "https://coffee.example/" }).url).toBe("https://coffee.example/courses/7");
  });
});
