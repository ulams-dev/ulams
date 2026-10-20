import { describe, expect, it } from "vitest";
import {
  accountFormProblem, accountPayload, connectionFacts, connectionState, gitFormProblem, gitPayload, hasToken, hintFor, normaliseRepository, scheduleLabel, splitList, urlFormProblem, urlPayload,
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

  it("builds the payload of another server, read with git, from its full https address", () => {
    const other = git({ host: "other", repository: " https://git.example.com/team/docs.git ", baseUrl: "https://ignored.example", branch: "docs", paths: "docs/**/*.md", token: "tok" });
    expect(gitFormProblem(other)).toBeNull();
    expect(gitPayload(other)).toEqual({ connector: "git-cli", config: { url: "https://git.example.com/team/docs.git", branch: "docs", paths: ["docs/**/*.md"] }, secrets: { token: "tok" }, schedule: "daily" });
    expect(gitFormProblem(git({ host: "other", repository: "team/docs" }))).toMatch(/starting with https/);
    expect(gitFormProblem(git({ host: "other", repository: "http://git.example.com/a.git" }))).toMatch(/starting with https/);
    expect(gitFormProblem(git({ host: "other", repository: "https://user@git.example.com/a.git" }))).toMatch(/starting with https/);
    expect(connectionFacts({ connector: "git-cli", config: { url: "https://git.example.com/team/docs.git", branch: "docs" } } as never)).toEqual([
      { label: "Server", value: "git.example.com" }, { label: "Repository", value: "team/docs" }, { label: "Branch", value: "docs" },
    ]);
    expect(hintFor("other").title).toBe("your Git server");
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

  it("checks and builds the Google Drive and Notion forms", () => {
    const drive = { source: "google-drive", address: " https://docs.google.com/document/d/1AbCdEfGhIjKlMnOp/edit ", children: false, schedule: "weekly" };
    expect(accountFormProblem(drive)).toBeNull();
    expect(accountPayload(drive, "g1")).toEqual({ connector: "google-drive", config: { fileId: "https://docs.google.com/document/d/1AbCdEfGhIjKlMnOp/edit" }, secrets: { grant: "g1" }, schedule: "weekly" });
    expect(accountFormProblem({ ...drive, address: "1AbCdEfGhIjKlMnOp" })).toBeNull();
    expect(accountFormProblem({ ...drive, address: "https://evil.example/d/1AbCdEfGhIjKlMnOp" })).toMatch(/Google Docs/);
    const notion = { source: "notion", address: "https://www.notion.so/Coffee-handbook-0123456789abcdef0123456789abcdef", children: true, schedule: "nonsense" };
    expect(accountFormProblem(notion)).toBeNull();
    expect(accountPayload(notion)).toEqual({ connector: "notion", config: { pageId: notion.address, includeChildren: true }, schedule: "daily" });
    expect(accountFormProblem({ ...notion, address: "docs" })).toMatch(/Notion page/);
    expect(accountFormProblem({ ...notion, source: "dropbox" })).toMatch(/Choose/);
    expect(connectionFacts({ connector: "notion", config: { pageId: "abc", includeChildren: true } } as never)).toEqual([{ label: "Notion page", value: "abc and the pages under it" }]);
    expect(connectionFacts({ connector: "google-drive", config: { fileId: "1Ab" } } as never)).toEqual([{ label: "Google Drive", value: "1Ab" }]);
  });
});
