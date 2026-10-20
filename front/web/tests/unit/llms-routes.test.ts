import { beforeEach, describe, expect, it, vi } from "vitest";
import type { Course } from "@ulams/sdk";
import { FIXTURES } from "./fixtures.ts";

const coffee = FIXTURES.coffee.course as unknown as Course;
const data = vi.hoisted(() => ({ getCourses: vi.fn(), getCourse: vi.fn(), getSettings: vi.fn() }));
vi.mock("../../src/lib/data.ts", () => data);

import { GET as llmsTxt } from "../../src/pages/llms.txt.ts";
import { GET as courseMd } from "../../src/pages/llms/courses/[id].md.ts";

const tenant = { slug: "coffee", apiUrl: "http://coffee.localhost", adminUrl: "http://coffee.admin.localhost" };
const request = new Request("https://coffee.example.org/llms.txt", { headers: { host: "coffee.example.org" } });
const url = new URL(request.url);
const call = (handler: unknown, locals: Record<string, unknown>, params: Record<string, string> = {}) =>
  (handler as (c: unknown) => Promise<Response>)({ request, url, locals, params });

describe("/llms.txt", () => {
  beforeEach(() => {
    data.getSettings.mockResolvedValue({ global: { companyName: "The Coffee Atlas" }, currencies: { default: "PLN" } });
    data.getCourses.mockResolvedValue([coffee]);
  });

  it("serves the academy index as plain text", async () => {
    const res = await call(llmsTxt, { tenant, platform: false });
    const body = await res.text();

    expect(res.status).toBe(200);
    expect(res.headers.get("content-type")).toContain("text/plain");
    expect(body).toContain("# The Coffee Atlas");
    expect(body).toContain(`/llms/courses/${coffee.id}.md`);
  });

  it("still answers when the API is down", async () => {
    data.getSettings.mockRejectedValue(new Error("down"));
    data.getCourses.mockRejectedValue(new Error("down"));

    const body = await (await call(llmsTxt, { tenant, platform: false })).text();

    expect(body).toContain("# coffee");
    expect(body).toContain("no public courses");
  });

  it("answers for the platform host and 404s for an unknown host", async () => {
    expect(await (await call(llmsTxt, { tenant: null, platform: true })).text()).toContain("# ulams");
    expect((await call(llmsTxt, { tenant: null, platform: false })).status).toBe(404);
  });
});

describe("/llms/courses/<id>.md", () => {
  beforeEach(() => {
    data.getSettings.mockResolvedValue({ global: { companyName: "The Coffee Atlas" } });
    data.getCourse.mockResolvedValue(coffee);
  });

  it("serves a public course as Markdown", async () => {
    const res = await call(courseMd, { tenant }, { id: String(coffee.id) });

    expect(res.status).toBe(200);
    expect(res.headers.get("content-type")).toContain("text/markdown");
    expect((await res.text()).startsWith(`# ${coffee.title}`)).toBe(true);
  });

  it("is 404 for bad ids, other ids, missing courses and hosts without an academy", async () => {
    expect((await call(courseMd, { tenant }, { id: "abc" })).status).toBe(404);
    expect((await call(courseMd, { tenant }, { id: "0" })).status).toBe(404);
    expect((await call(courseMd, { tenant }, { id: String(coffee.id + 1) })).status).toBe(404);
    expect((await call(courseMd, { tenant: null }, { id: String(coffee.id) })).status).toBe(404);
    data.getCourse.mockRejectedValue(new Error("404"));
    expect((await call(courseMd, { tenant }, { id: String(coffee.id) })).status).toBe(404);
  });
});
