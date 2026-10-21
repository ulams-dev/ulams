# 0160. Course-as-code sync through Blueprint v3 import versions

- Status: Accepted
- Date: 2026-10-21
- Plan: `docs/plans/phase-7-course-as-code.md` (sections 4, 6, 7); amends ADR 0079 (Proposed); owner question #78

## Context and problem statement

ADR 0079 planned course-as-code on a "Blueprint v2". Since then the v2 schema shipped for lesson content types
(LiaScript, H5P, interactive), every course-builder route sits behind the AI gate, courses made by hand have no
blueprint, and admin edits after an apply are only detected as drift. Course-as-code must work with AI off, keep
"every AI change is a reviewable diff", and accept hand-written content that has no source to cite.

## Decision

- The course-as-code additions are **Blueprint v3**: per-element `origin` (`ai` default, `author`), zero
  citations allowed only for `origin: author`, content types `scorm`, `cmi5`, `video`, `file`, `embed` with asset
  references, optional `key` slugs. v1 and v2 documents upgrade losslessly.
- Any element the AI creates or changes is `origin: ai` and must cite; `ai → author` happens only in a human
  import and is shown in the diff.
- A push creates a **proposed** version of the new kind `import` (origin `author`). Approval needs a human
  (`cli` or session token); `agent` tokens and MCP clients never approve; `ci` tokens approve only when the
  tenant setting `course_as_code.ci_may_approve` is on.
- Course-as-code endpoints live in `api/admin/course-as-code/*` without `EnsureAiEnabled`.
- Hand-made courses are **adopted** into a session with an entity map that makes the first apply a no-op; admin
  drift can be absorbed into a proposed `author` version.
- Sync is an element-level three-way merge against a committed hash base (`.ulams/base.json`); one conflict per
  element, written as a file, never overwritten silently.

## Consequences

- Good: Git, studio and Living Course edits converge through one version history and one diff model; works
  without AI; hand-written courses are possible without fake citations.
- Bad: a v3 schema, five content types and a reverse mapper (adoption, drift) to maintain; uncited author blocks
  are invisible to Living Course impact analysis.
