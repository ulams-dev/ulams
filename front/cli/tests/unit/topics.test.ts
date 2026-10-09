import { describe, expect, it } from "vitest";
import { runCli, type Handler } from "../helpers.ts";

const env = { ULAMS_URL: "http://coffee.localhost", ULAMS_TOKEN: "tok-123456" };
const CLS = "Ulams\\TopicTypes\\Models\\TopicContent\\";

function lmsRoutes(extra: Record<string, Handler> = {}): Record<string, Handler> {
  return {
    "GET /api/admin/lessons/12": { body: { success: true, data: { id: 12, course_id: 4 } } },
    "GET /api/admin/courses/4": { body: { success: true, data: { id: 4, lessons: [{ id: 12, topics: [{ order: 1 }, { order: 2 }] }] } } },
    "POST /api/admin/topics": (req) => ({ body: { success: true, data: { id: 50, topicable: { id: 7 }, ...(req.body ?? {}) } } }),
    ...extra,
  };
}
const base = ["--lesson", "12", "--title", "T", "--json"];

describe("topic commands", () => {
  it("richtext converts Markdown and appends after the last topic", async () => {
    const r = await runCli(["topics", "create-richtext", ...base, "--markdown", "@a.md"], { env, routes: lmsRoutes(), files: { "a.md": "# Hi\n\nSome **bold** text.\n\n- one\n- two\n" } });
    expect(r.code).toBe(0);
    const body = r.requests.find((q) => q.method === "POST")?.body as Record<string, unknown>;
    expect(body).toMatchObject({ lesson_id: 12, title: "T", order: 3, topicable_type: `${CLS}RichText` });
    expect(body.value).toBe("<h1>Hi</h1>\n<p>Some <strong>bold</strong> text.</p>\n<ul><li>one</li><li>two</li></ul>");
  });

  it("richtext needs exactly one of --markdown and --html", async () => {
    expect((await runCli(["topics", "create-richtext", ...base], { env, routes: lmsRoutes() })).code).toBe(2);
    expect((await runCli(["topics", "create-richtext", ...base, "--html", "<p>x</p>", "--markdown", "x"], { env, routes: lmsRoutes() })).code).toBe(2);
  });

  it("oembed and youtube create an OEmbed topic with the URL", async () => {
    const a = await runCli(["topics", "create-oembed", ...base, "--url", "https://vimeo.com/1", "--order", "9"], { env, routes: lmsRoutes() });
    expect(a.requests.at(-1)?.body).toMatchObject({ topicable_type: `${CLS}OEmbed`, value: "https://vimeo.com/1", order: 9 });
    const b = await runCli(["topics", "create-video", ...base, "--youtube-url", "https://youtu.be/x"], { env, routes: lmsRoutes() });
    expect(b.requests.at(-1)?.body).toMatchObject({ topicable_type: `${CLS}OEmbed`, value: "https://youtu.be/x" });
  });

  it("file topics upload multipart and pick the type from the extension", async () => {
    for (const [file, type] of [["a.pdf", "PDF"], ["a.png", "Image"], ["a.mp3", "Audio"]] as const) {
      const r = await runCli(["topics", "create-file", ...base, "--file", file], { env, routes: lmsRoutes(), files: { [file]: "bytes" } });
      expect(r.code).toBe(0);
      const form = r.requests.find((q) => q.method === "POST")?.form as FormData;
      expect(form.get("topicable_type")).toBe(`${CLS}${type}`);
      expect((form.get("value") as File).name).toBe(file);
    }
    expect((await runCli(["topics", "create-file", ...base, "--file", "a.exe"], { env, routes: lmsRoutes(), files: { "a.exe": "x" } })).code).toBe(2);
  });

  it("video uploads, waits for processing and --no-wait returns the operation handle", async () => {
    const routes = lmsRoutes({ "GET /api/admin/video/states": { body: { success: true, data: [{ topic_id: 50, state: "finished" }] } } });
    const waited = await runCli(["topics", "create-video", ...base, "--file", "v.mp4"], { env, routes, files: { "v.mp4": "x" } });
    expect(waited.json()).toMatchObject({ data: { processing: { handle: "video:50", status: "succeeded" } } });
    const nowait = await runCli(["topics", "create-video", ...base, "--file", "v.mp4", "--no-wait"], { env, routes, files: { "v.mp4": "x" } });
    expect(nowait.json()).toMatchObject({ meta: { operation: "video:50" } });
    expect(nowait.requests.some((q) => q.path.endsWith("/video/states"))).toBe(false);
  });

  it("scorm uploads the package, picks the only SCO and creates the topic", async () => {
    const routes = lmsRoutes({ "POST /api/admin/scorm/upload": { body: { success: true, data: { scormData: { scos: [{ id: 31, title: "Golf" }] } } } } });
    const r = await runCli(["topics", "create-scorm", ...base, "--package", "p.zip"], { env, routes, files: { "p.zip": "zip" } });
    expect(r.code).toBe(0);
    const upload = r.requests.find((q) => q.path === "/api/admin/scorm/upload")?.form as FormData;
    expect((upload.get("zip") as File).name).toBe("p.zip");
    expect(r.requests.find((q) => q.path === "/api/admin/topics")?.body).toMatchObject({ topicable_type: `${CLS}ScormSco`, value: 31 });
  });

  it("scorm with several SCOs exits 2 with the choices, --sco resolves it", async () => {
    const routes = lmsRoutes({ "POST /api/admin/scorm/upload": { body: { success: true, data: { scormData: { scos: [{ id: 1, title: "A" }, { id: 2, title: "B" }] } } } } });
    const r = await runCli(["topics", "create-scorm", ...base, "--package", "p.zip"], { env, routes, files: { "p.zip": "zip" } });
    expect(r.code).toBe(2);
    expect((r.json().error as { details: { choices: unknown[] } }).details.choices).toHaveLength(2);
    const ok = await runCli(["topics", "create-scorm", ...base, "--package", "p.zip", "--sco", "2"], { env, routes, files: { "p.zip": "zip" } });
    expect(ok.requests.find((q) => q.path === "/api/admin/topics")?.body).toMatchObject({ value: 2 });
  });

  it("cmi5, h5p and liascript upload first and create the topic with the new id", async () => {
    const cmi5 = await runCli(["topics", "create-cmi5", ...base, "--package", "p.zip"], {
      env,
      routes: lmsRoutes({ "POST /api/admin/cmi5": { body: { success: true, data: { id: 3, au: [{ id: 8, title: "AU" }] } } } }),
      files: { "p.zip": "x" },
    });
    expect(cmi5.requests.at(-1)?.body).toMatchObject({ topicable_type: `${CLS}Cmi5Au`, value: 8 });
    const h5p = await runCli(["topics", "create-h5p", ...base, "--package", "q.h5p"], {
      env,
      routes: lmsRoutes({ "POST /h5p/contents/upload": { body: { contentId: "14", metadata: {} } } }),
      files: { "q.h5p": "x" },
    });
    expect((h5p.requests.find((q) => q.path === "/h5p/contents/upload")?.form as FormData).get("h5p_file")).toBeTruthy();
    expect(h5p.requests.at(-1)?.body).toMatchObject({ topicable_type: `${CLS}H5P`, value: 14 });
    const lia = await runCli(["topics", "create-liascript", ...base, "--markdown", "@l.md"], {
      env,
      routes: lmsRoutes({ "POST /api/admin/liascript": (req) => ({ body: { success: true, data: { id: 21, ...(req.body as object) } } }) }),
      files: { "l.md": "# Lia" },
    });
    expect(lia.requests.find((q) => q.path === "/api/admin/liascript")?.body).toMatchObject({ markdown: "# Lia", title: "T" });
    expect(lia.requests.at(-1)?.body).toMatchObject({ topicable_type: "Ulams\\LiaScript\\Models\\LiaScriptTopic", value: 21 });
  });

  it("interactive uploads the package first, then creates the topic with its range, rule and display", async () => {
    const routes = lmsRoutes({ "POST /api/admin/interactive": () => ({ body: { success: true, data: { id: 31, title: "Gravity" } } }) });
    const r = await runCli(
      ["topics", "create-interactive", ...base, "--file", "gravity.zip", "--start-step", "too-slow", "--end-step", "too-slow", "--display", "background", "--text", "@intro.md"],
      { env, routes, files: { "gravity.zip": "zip-bytes", "intro.md": "Drag the slider." } }
    );
    expect(r.code).toBe(0);
    const upload = r.requests.find((q) => q.path === "/api/admin/interactive");
    expect((upload?.form as FormData).get("file")).toBeTruthy();
    expect((upload?.form as FormData).get("accept_network")).toBeNull();
    expect(r.requests.at(-1)?.body).toMatchObject({
      topicable_type: "Ulams\\Interactive\\Models\\InteractiveTopic",
      value: 31,
      start_step: "too-slow",
      end_step: "too-slow",
      completion_rule: "on_range_end",
      display: "background",
      text: "Drag the slider.",
    });
    expect(JSON.parse(r.stdout).data.package).toMatchObject({ id: 31 });
  });

  it("interactive on an uploaded package pins a version, or follows the latest, and checks its inputs", async () => {
    const pinned = await runCli(["topics", "create-interactive", ...base, "--package", "3", "--version", "2", "--completion", "on_score", "--pass-score", "80"], { env, routes: lmsRoutes() });
    expect(pinned.requests.some((q) => q.path === "/api/admin/interactive")).toBe(false);
    expect(pinned.requests.at(-1)?.body).toMatchObject({ value: 3, version: 2, completion_rule: "on_score", pass_score: 80, display: "inline" });
    const follow = await runCli(["topics", "create-interactive", ...base, "--package", "3", "--version", "2", "--follow-latest"], { env, routes: lmsRoutes() });
    expect(follow.requests.at(-1)?.body).toMatchObject({ follow_latest: 1 });
    expect(follow.requests.at(-1)?.body).not.toHaveProperty("version");

    expect((await runCli(["topics", "create-interactive", ...base], { env, routes: lmsRoutes() })).code).toBe(2);
    expect((await runCli(["topics", "create-interactive", ...base, "--package", "3", "--file", "a.zip"], { env, routes: lmsRoutes(), files: { "a.zip": "x" } })).code).toBe(2);
    expect((await runCli(["topics", "create-interactive", ...base, "--package", "3", "--completion", "on_score"], { env, routes: lmsRoutes() })).code).toBe(2);
  });

  it("interactive confirms the manifest's network origins only when asked to", async () => {
    const routes = lmsRoutes({ "POST /api/admin/interactive": () => ({ body: { success: true, data: { id: 31 } } }) });
    const r = await runCli(["topics", "create-interactive", ...base, "--file", "g.zip", "--accept-network"], { env, routes, files: { "g.zip": "zip" } });
    expect(((r.requests.find((q) => q.path === "/api/admin/interactive")?.form) as FormData).get("accept_network")).toBe("1");
  });

  it("quiz creates the topic then one GIFT question per entry", async () => {
    const routes = lmsRoutes({ "POST /api/admin/gift-questions": (req) => ({ body: { success: true, data: { id: 1, ...(req.body as object) } } }) });
    const r = await runCli(["topics", "create-quiz", ...base, "--input", "@q.yaml"], {
      env,
      routes,
      files: { "q.yaml": "lesson: 12\ntitle: Q\nquestions:\n  - prompt: What is 2+2?\n    options:\n      - { text: '4', correct: true }\n      - { text: '5' }\n    score: 2\n  - gift: '::raw:: Q {T}'\n" },
    });
    expect(r.code).toBe(0);
    const qs = r.requests.filter((q) => q.path === "/api/admin/gift-questions").map((q) => q.body);
    expect(qs).toEqual([
      { topic_gift_quiz_id: 7, value: "::Q1:: What is 2+2? {=4 ~5}", score: 2, order: 1 },
      { topic_gift_quiz_id: 7, value: "::raw:: Q {T}", score: 1, order: 2 },
    ]);
    expect(r.requests.find((q) => q.path === "/api/admin/topics")?.body).toMatchObject({ topicable_type: "Ulams\\TopicTypeGift\\Models\\GiftQuiz" });
  });

  it("generic create passes the class and extra fields through", async () => {
    const r = await runCli(["topics", "create", ...base, "--topicable", "Ulams\\TopicTypeProject\\Models\\Project", "--value", "Build X", "--set", "weight=5", "--order", "1"], { env, routes: lmsRoutes() });
    expect(r.requests.at(-1)?.body).toMatchObject({ topicable_type: "Ulams\\TopicTypeProject\\Models\\Project", value: "Build X", weight: 5, order: 1 });
  });

  it("--dry-run on a topic command sends nothing", async () => {
    const r = await runCli(["topics", "create-richtext", ...base, "--html", "<p>x</p>", "--dry-run"], { env, routes: lmsRoutes() });
    expect(r.requests).toHaveLength(0);
    expect(r.json()).toMatchObject({ data: { dryRun: true } });
  });
});
