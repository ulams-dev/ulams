import { describe, expect, it } from "vitest";
import {
  connectionFacts, connectionState, gitFormProblem, gitPayload, hasToken, hintFor, normaliseRepository, scheduleLabel, splitList, urlFormProblem, urlPayload,
  type GitForm,
} from "../../src/studio/connect-model.ts";

const git = (extra: Partial<GitForm> = {}): GitForm => ({ host: "github", baseUrl: "", repository: "ulams-dev/docs", branch: "", paths: "", token: "", schedule: "daily", ...extra });

describe("connect form model", () => {
  it("splits lists by line or comma and drops blanks and repeats", () => {
    expect(splitList(" docs/**/*.md\n\n README.md, docs/**/*.md ")).toEqual(["docs/**/*.md", "README.md"]);
    expect(splitList("")).toEqual([]);
  });

  it("accepts a pasted repository address", () => {
    expect(normaliseRepository("https://github.com/ulams-dev/docs.git")).toBe("ulams-dev/docs");
    expect(normaliseRepository(" ulams-dev/docs/ ")).toBe("ulams-dev/docs");
    expect(normaliseRepository("https://gitlab.example.com/group/sub/name")).toBe("group/sub/name");
  });

  it("builds the Git payload without empty parts", () => {
    expect(gitPayload(git())).toEqual({ connector: "git", config: { host: "github", repository: "ulams-dev/docs" }, schedule: "daily" });
    const full = gitPayload(git({ host: "gitea", baseUrl: "https://git.example.com/", branch: " docs ", paths: "docs/**/*.md\nREADME.md", token: " tok ", schedule: "weekly" }));
    expect(full).toEqual({
      connector: "git",
      config: { host: "gitea", base_url: "https://git.example.com", repository: "ulams-dev/docs", branch: "docs", paths: ["docs/**/*.md", "README.md"] },
      secrets: { token: "tok" },
      schedule: "weekly",
    });
  });

  it("does not send a server address for GitHub and falls back to a daily schedule", () => {
    const payload = gitPayload(git({ baseUrl: "https://example.com", schedule: "yearly" }));
    expect(payload.config).not.toHaveProperty("base_url");
    expect(payload.schedule).toBe("daily");
  });

  it("explains what is wrong with the Git form in plain words", () => {
    expect(gitFormProblem(git())).toBeNull();
    expect(gitFormProblem(git({ repository: "docs" }))).toMatch(/owner\/name/);
    expect(gitFormProblem(git({ host: "gitea" }))).toMatch(/address of your server/);
    expect(gitFormProblem(git({ host: "gitea", baseUrl: "https://git.example.com" }))).toBeNull();
    expect(gitFormProblem(git({ host: "gitlab", baseUrl: "git.example.com" }))).toMatch(/https/);
    expect(gitFormProblem(git({ host: "svn" }))).toMatch(/Choose/);
  });

  it("validates the page list", () => {
    expect(urlFormProblem({ urls: "", selector: "", schedule: "daily" })).toMatch(/at least one/);
    expect(urlFormProblem({ urls: "http://docs.example.com/a", selector: "", schedule: "daily" })).toMatch(/not an https address/);
    expect(urlFormProblem({ urls: "https://a.example.com/x\nhttps://b.example.com/y", selector: "", schedule: "daily" })).toMatch(/same site/);
    expect(urlFormProblem({ urls: Array.from({ length: 21 }, (_, i) => `https://a.example.com/${i}`).join("\n"), selector: "", schedule: "daily" })).toMatch(/at most 20/);
    expect(urlFormProblem({ urls: "https://a.example.com/x\nhttps://a.example.com/y", selector: "", schedule: "daily" })).toBeNull();
  });

  it("builds the page payload", () => {
    expect(urlPayload({ urls: "https://a.example.com/x\nhttps://a.example.com/y", selector: " main ", schedule: "manual" })).toEqual({
      connector: "url", config: { urls: ["https://a.example.com/x", "https://a.example.com/y"], selector: "main" }, schedule: "manual",
    });
    expect(urlPayload({ urls: "https://a.example.com/x", selector: "", schedule: "daily" }).config).toEqual({ urls: ["https://a.example.com/x"] });
  });
});

describe("connection panel model", () => {
  const base = { id: "c", connector: "git", schedule: "daily", status: "active", autoAnalyse: true, settings: {}, config: {}, syncedRevision: null, latestRevision: null, lastCheckedAt: null, nextCheckAt: null, lastChangeAt: null, failureCount: 0, lastError: null, secretsSet: [] };

  it("reads the state from status and last error", () => {
    expect(connectionState({ status: "active", lastError: null })).toBe("active");
    expect(connectionState({ status: "paused", lastError: null })).toBe("paused");
    expect(connectionState({ status: "active", lastError: "Not found" })).toBe("error");
    expect(connectionState({ status: "error", lastError: null })).toBe("error");
  });

  it("describes a repository and a set of pages", () => {
    const repo = connectionFacts({ ...base, config: { host: "gitea", base_url: "https://git.example.com", repository: "a/b", branch: "main", paths: ["docs/**/*.md"] } });
    expect(repo.map((f) => `${f.label}: ${f.value}`)).toEqual([
      "Host: Gitea or Forgejo (https://git.example.com)", "Repository: a/b", "Branch: main", "Paths: docs/**/*.md",
    ]);
    const pages = connectionFacts({ ...base, connector: "url", config: { urls: ["https://a.example.com/x", "https://a.example.com/y"], selector: "main" } });
    expect(pages).toEqual([{ label: "Pages", value: "2 pages on a.example.com" }, { label: "Content selector", value: "main" }]);
    expect(connectionFacts({ ...base, connector: "upload" })).toEqual([]);
  });

  it("labels schedules, tokens and hints", () => {
    expect(scheduleLabel("weekly")).toBe("Every week");
    expect(scheduleLabel(null)).toBe("Not scheduled");
    expect(hasToken({ secretsSet: ["token"] })).toBe(true);
    expect(hasToken({ secretsSet: [] })).toBe(false);
    expect(hintFor("gitlab").steps.join(" ")).toMatch(/Secret token/);
    expect(hintFor("github").steps.join(" ")).toMatch(/application\/json/);
    expect(hintFor("unknown").host).toBe("github");
  });
});
