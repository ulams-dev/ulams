import { expect, test } from "@playwright/test";
import { demoCourse } from "./demo-data.ts";

/**
 * H5P learner state through the BFF (ADR 0045), against a running stack (default :4321, see
 * README). The frame has no API token: the `/h5p` proxy adds the session token server-side for the
 * player's own calls. So the play model carries no `_token`, and state saved through the proxy is
 * there after a reload.
 */
const port = process.env.WEB_BASE_PORT ?? "4321";
const base = `http://coffee.app.localhost:${port}`;

test.describe("H5P state through the BFF", () => {
  test.skip(({ isMobile }) => isMobile, "one viewport is enough");

  test("the play model has no token in its URLs and saved state survives a reload", async ({ page }) => {
    const course = await demoCourse("coffee");
    const topicId = course.topics.H5P;
    test.skip(!topicId, "the demo course has no H5P topic");

    await page.goto(`${base}/learn/${course.courseId}/${topicId}`);
    const frame = page.locator("ulams-h5p iframe");
    await expect(frame).toBeVisible();
    const src = (await frame.getAttribute("src")) ?? "";
    const contentId = /\/h5p\/embed\/play\/(\d+)/.exec(src)?.[1];
    expect(contentId, `frame src ${src}`).toBeTruthy();

    // the frame is told token: null, so the page never holds a bearer token
    expect(await page.evaluate(() => document.cookie)).not.toMatch(/ulams_session/);

    const playUrl = `${base}/h5p/contents/${contentId}/play`;
    const model = await page.request.get(playUrl);
    expect(model.status()).toBe(200);
    const text = await model.text();
    expect(text, "model URLs must not carry the learner's token").not.toContain("_token=");
    expect(JSON.parse(text).data.integration.user?.id ?? "").not.toBe("anonymous");

    // state goes through the proxy as the learner and is there on the next load
    const stateUrl = `${base}/h5p/contentUserData/${contentId}/state/0`;
    const marker = JSON.stringify({ answered: Date.now() });
    // a browser sends Origin on its own POSTs; the front refuses writes without a same-site one
    const saved = await page.request.post(stateUrl, { form: { data: marker, preload: "1", invalidate: "1" }, headers: { Origin: base } });
    expect(saved.status()).toBe(200);

    await page.reload();
    const again = await page.request.get(stateUrl);
    expect(again.status()).toBe(200);
    expect(JSON.stringify(await again.json())).toContain(JSON.parse(marker).answered.toString());
  });

  test("a call that is not the learner's own player call is not made as the learner", async ({ page }) => {
    await page.goto(base);
    // the library list is an admin route: no session is added, so the service answers as anonymous
    const response = await page.request.get(`${base}/h5p/libraries`);
    expect([401, 403]).toContain(response.status());
  });
});
