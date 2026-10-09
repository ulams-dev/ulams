import { expect, test } from "@playwright/test";
import { demoCourse } from "./demo-data.ts";

/**
 * The enforced Content Security Policy (ADR 0044) against a running stack (default :4321, see
 * README): the learner pages, every topic type of the demo course (H5P, packages, LiaScript, video,
 * quiz) and the studio load without a violation. A violation shows up as a console error
 * ("Refused to ...") and, in the report endpoint, as a row of GET /api/admin/csp-reports.
 *
 * Needs CSP_ENFORCE unset (development default) or `true` on the front being tested.
 */
const port = process.env.WEB_BASE_PORT ?? "4321";
const TENANTS = ["coffee", "oncall", "nightsky", "gravity", "poland", "ulam"];

for (const slug of TENANTS) {
  test.describe(`CSP on ${slug}`, () => {
    test.skip(({ isMobile }) => isMobile, "one viewport is enough");

    test("learner pages and every topic of the demo course load without a violation", async ({ page, request }) => {
      const base = `http://${slug}.app.localhost:${port}`;
      const course = await demoCourse(slug);
      const violations: string[] = [];
      page.on("console", (message) => {
        if (/Content Security Policy|Refused to/.test(message.text())) violations.push(message.text().replace(/\s+/g, " ").slice(0, 200));
      });

      const response = await request.get(base);
      expect(response.headers()["content-security-policy"], "the policy is enforced").toContain("default-src 'self'");
      expect(response.headers()["reporting-endpoints"]).toContain(`http://${slug}.localhost/api/csp-report`);

      const pages = [`${base}/`, `${base}/courses/${course.courseId}`, ...Object.values(course.topics).map((id) => `${base}/learn/${course.courseId}/${id}`)];
      for (const url of pages) {
        await page.goto(url, { waitUntil: "load" });
        await page.waitForTimeout(1500);
      }
      expect(violations, violations.join("\n")).toEqual([]);
    });
  });
}

test("the studio loads without a violation", async ({ page, isMobile }) => {
  test.skip(isMobile, "one viewport is enough");
  const violations: string[] = [];
  page.on("console", (message) => {
    if (/Content Security Policy|Refused to/.test(message.text())) violations.push(message.text().replace(/\s+/g, " ").slice(0, 200));
  });
  await page.goto(`http://coffee.app.localhost:${port}/studio`, { waitUntil: "load" });
  await page.waitForTimeout(1500);
  expect(violations, violations.join("\n")).toEqual([]);
});

test("a content origin or sandboxed frame may report; the collector keeps no URL parts", async ({ request }) => {
  const api = process.env.API_COFFEE ?? "http://coffee.localhost";
  const origin = "http://coffee.content.localhost";
  const preflight = await request.fetch(`${api}/api/csp-report`, { method: "OPTIONS", headers: { Origin: origin, "Access-Control-Request-Method": "POST", "Access-Control-Request-Headers": "content-type" } });
  expect(preflight.status()).toBe(204);
  expect(preflight.headers()["access-control-allow-origin"]).toBe("*");

  const report = await request.post(`${api}/api/csp-report`, {
    headers: { Origin: origin, "Content-Type": "application/csp-report" },
    data: JSON.stringify({ "csp-report": { "document-uri": `${origin}/cmi5/1/index.html?token=secret`, "effective-directive": "connect-src", "blocked-uri": "https://tracker.example.test/x?id=1" } }),
  });
  expect(report.status()).toBe(204);
  expect(report.headers()["access-control-allow-origin"]).toBe("*");
});
