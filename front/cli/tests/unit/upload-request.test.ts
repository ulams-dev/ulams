import { describe, expect, it } from "vitest";
import { buildForm } from "../../src/registry/http-run.ts";
import { buildRequest } from "../../src/registry/request.ts";
import type { FsPort, RequestSpec } from "../../src/registry/types.ts";

const fs = { readFile: async (path: string) => new TextEncoder().encode(`bytes of ${path}`) } as unknown as FsPort;

describe("requests that carry files", () => {
  const create: RequestSpec = {
    method: "POST", path: "/api/admin/courses", pathParams: [], queryParams: [], bodyMode: "json",
    bodyFields: ["title", "image"], files: { image: "image" },
  };

  it("keeps a JSON body when no file is given", () => {
    const req = buildRequest(create, { title: "T" });
    expect(req.body).toEqual({ title: "T" });
    expect(req.form).toBeUndefined();
  });

  it("switches a JSON body to multipart as soon as a file is given", async () => {
    const req = buildRequest(create, { title: "T", image: "./logo.png" });
    expect(req.body).toBeUndefined();
    expect(req.form).toEqual({ title: "T" });
    const form = await buildForm(fs, req, { title: "T", image: "./logo.png" });
    expect(form.get("title")).toBe("T");
    const image = form.get("image") as File;
    expect(image.name).toBe("logo.png");
    expect(await image.text()).toBe("bytes of ./logo.png");
  });

  it("sends a list field such as file[] once per file under that name", async () => {
    const upload: RequestSpec = {
      method: "POST", path: "/api/admin/file/upload", pathParams: [], queryParams: [], bodyMode: "multipart",
      bodyFields: ["file[]", "target"], files: { "file[]": "file[]" },
    };
    const input = { "file[]": ["a.pdf", "b.pdf"], target: "e2e" };
    const form = await buildForm(fs, buildRequest(upload, input), input);
    expect(form.getAll("file[]").map((f) => (f as File).name)).toEqual(["a.pdf", "b.pdf"]);
    expect(form.get("target")).toBe("e2e");
  });
});
