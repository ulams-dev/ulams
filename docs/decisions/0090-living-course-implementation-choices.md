# 0090. Living Course: choices made during implementation

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

ADRs 0030 to 0034 fixed the architecture of Living Course. Building it surfaced a few choices the plan
left open or got slightly wrong. They are small, but each changes behaviour an operator or an author can
see, so they are recorded here.

## Decision

- **Path filters use our own glob matcher** (`PathFilter`), not Symfony's `Glob`: Git repositories are
  matched by tree path, not by files on disk, and `**` semantics must be the same on every host.
  MDX/JSX is stripped from `.mdx` sources before fragmenting.
- **The URL connector stores the converted Markdown as the raw revision**, so a revision can be
  re-fragmented later without fetching the page again.
- **Alignment is lenient where it is safe**: an unmatched pair with enough token similarity, or the
  only fragment under a heading on both sides, is a change of that fragment, not an add plus a remove.
  This avoids proposing to delete and regenerate a paragraph that was merely reworded.
- **A GIFT question keeps its score when it is updated**, and a drift check on an admin-edited question
  only fires when the question text itself was edited, not its feedback or order.
- **`living-course:eval` builds its course on the fake driver and only the analysis calls are live**
  (with `--live`); recorded answers are replayed in CI by `CassetteReplayTest`. A full live run costs
  about USD 0.2 for the three fixtures.
- **Plan commits 10 and 11 shipped as one commit, as did the progress-rules pair (23 and 24)**: the
  impact analyser and proposal service share their tables, and the policies and notices share their
  test.
- **The living-course tests run in their own CI shard** (`living`): the suite takes several minutes and
  would otherwise dominate the `learning` shard.
- **A suggested lesson is a proposal item, not a new run**: an uncovered section turns into a
  `new_lesson` item (one `new_lesson` call, cited only to that section, grounding-checked) that the
  author accepts or rejects like any other change; the blueprint applier adds it. A proposal that was
  settled as `no_impact` because only a section was added is reopened when a lesson is suggested.
- **Generic Git (`git-cli`) reads any https Git server with the `git` binary**, which the plan (decision
  6) had postponed. It is opt-in (`LIVING_COURSE_CONNECTORS`) because the image must ship git: the
  address passes the SSRF checks and is pinned for git, redirects and protocols other than https are
  off, the clone is shallow, single-branch, no checkout, with no hooks, submodules or LFS, runs with an
  empty environment in a temporary directory with a size and time cap, only regular files are read by
  blob id, the token travels in an environment header and git's own messages are never shown.
- **Google Drive and Notion are connectors with an account sign-in** (`OAuthConnector`), built into the package
  and opt-in like `git-cli`, not separate plugin packages: the OAuth app belongs to the installation, the
  browser only ever holds a one-time server-side grant (encrypted, ten minutes, bound to the user), and the
  refresh token (Google) or access token (Notion) lives encrypted in the connection. Google Docs are exported as
  Markdown instead of DOCX (the plan's choice), which keeps headings and lists without a converter.

## Consequences

- Good: behaviour of filters and diffs is predictable and tested without a repository on disk.
- Good: CI exercises the real recorded model answers at no cost.
- Bad: our own glob matcher is one more piece of code to maintain; it covers `*`, `**`, `?` and
  negation only.
- Bad: lenient pairing can occasionally merge two unrelated edits under one heading; the author sees
  the old and new text side by side and can reject the item.
