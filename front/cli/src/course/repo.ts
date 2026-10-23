import { execFileSync } from "node:child_process";

/* Where a course folder lives on a Git host, for `ulams course init --sources-connector` and `course sources-connect`
 * (plan 9.2): the origin remote and the branch say which repository Living Course should watch for `sources/**`. */

export type GitHost = "github" | "gitlab" | "gitea";

export interface RepoRef {
  host: GitHost;
  /** API base for GitLab and Gitea/Forgejo; GitHub.com needs none. */
  baseUrl?: string;
  /** owner/name (GitLab: group/subgroup/name). */
  repository: string;
  branch: string;
}

/** `https://github.com/o/r.git`, `git@github.com:o/r.git` and `ssh://git@host:2222/o/r` as host name and repository path. */
export function splitRemote(remote: string): { hostname: string; path: string } | null {
  const url = remote.trim();
  let m = /^[a-z][a-z0-9+.-]*:\/\/(?:[^@/]+@)?([^/:]+)(?::\d+)?\/(.+?)(?:\.git)?\/?$/i.exec(url);
  if (!m) m = /^(?:[^@/]+@)?([^/:]+):(?!\/\/)(.+?)(?:\.git)?\/?$/i.exec(url);
  if (!m?.[1] || !m[2]) return null;
  const path = m[2].replace(/^\/+/, "");
  return /^[A-Za-z0-9_.-]+(\/[A-Za-z0-9_.-]+)+$/.test(path) ? { hostname: m[1].toLowerCase(), path } : null;
}

/** github.com and gitlab.com are known; any other host needs `host` ("gitlab" or "gitea", Forgejo included) from the caller. */
export function repoFromRemote(remote: string, branch: string, host?: GitHost): RepoRef | null {
  const parts = splitRemote(remote);
  if (!parts) return null;
  const kind: GitHost | null = host ?? (parts.hostname === "github.com" ? "github" : parts.hostname === "gitlab.com" ? "gitlab" : null);
  if (kind === null) return null;
  if (kind === "github") return { host: kind, ...(parts.hostname === "github.com" ? {} : { baseUrl: `https://${parts.hostname}/api/v3` }), repository: parts.path, branch };
  if (kind === "gitlab") return { host: kind, ...(parts.hostname === "gitlab.com" ? {} : { baseUrl: `https://${parts.hostname}/api/v4` }), repository: parts.path, branch };
  return { host: kind, baseUrl: `https://${parts.hostname}`, repository: parts.path, branch };
}

export type GitRunner = (args: string[], cwd: string) => string | null;

const run: GitRunner = (args, cwd) => {
  try {
    return execFileSync("git", args, { cwd, encoding: "utf8", stdio: ["ignore", "pipe", "ignore"], timeout: 5000 }).trim();
  } catch {
    return null;
  }
};

/** The origin remote and the default branch of the repository around `dir` (the remote's HEAD, else the checked-out branch, else main). */
export function gitOrigin(dir: string, git: GitRunner = run): { remote: string; branch: string } | null {
  const remote = git(["remote", "get-url", "origin"], dir);
  if (!remote) return null;
  const head = git(["symbolic-ref", "--short", "refs/remotes/origin/HEAD"], dir)?.replace(/^origin\//, "");
  const current = git(["rev-parse", "--abbrev-ref", "HEAD"], dir);
  return { remote, branch: head || (current && current !== "HEAD" ? current : "main") };
}

/** The top level of the repository around `dir`, or null outside one. */
export function gitTopLevel(dir: string, git: GitRunner = run): string | null {
  return git(["rev-parse", "--show-toplevel"], dir) || null;
}
