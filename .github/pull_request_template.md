## What and why

<!-- What changes, why, and how to demo it. Link the issue, plan (docs/plans/phase-N.md) and ADR. -->

Closes #

## Checklist

- [ ] The title follows Conventional Commits (`feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`); the branch is `phase-N/short-description`.
- [ ] One concern per commit, tests in the same commit; existing tests and linters are green.
- [ ] The documentation site (`front/docs-site`) is updated in this branch and `corepack yarn workspace @ulams/docs coverage` passes (or: no user-visible or developer-visible change).
- [ ] Decisions made in this change have an ADR in `docs/decisions` (status Proposed until the product owner accepts it), or no decision was made.
- [ ] New API endpoints have policies, Swagger annotations and a tenant isolation test (or: no new endpoints).
- [ ] Learner-facing UI meets WCAG 2.2 AA (or: no learner-facing UI changed).
- [ ] H5P and SCORM behaviour is unchanged.
- [ ] New dependencies are justified (licence, maintenance, size, self-hosting impact); no GPL or AGPL code outside `api/h5p`.
- [ ] `docs/ROADMAP-TODO.md` is updated if a roadmap item is done or advanced.
- [ ] No AI attribution in commits, the PR description, branch names or files.
