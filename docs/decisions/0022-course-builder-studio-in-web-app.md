# 0022. Course Builder studio in the reference web app, with its own author session

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

The Course Builder needs a streaming chat UI that renders A2UI surfaces from the `@ulams/ui`
catalogue (ADR 0008, 0011). The admin is an umi/Ant Design SPA with heavy bundles and its own
component language. The builder also needs an author (tutor or admin) session, while the reference
web app so far only had learner sessions (demo student or password login).

## Decision

- The studio lives in `front/web` under `/studio` (sessions, start/upload, conversation, workspace,
  success, landing preview). The admin only links to it: a "Build with AI" menu entry and a card on
  the course list (`REACT_APP_STUDIO_URL`, else derived from the admin host).
- The author's Passport token is kept in a separate httpOnly cookie `ulams_author`
  (SameSite=Lax). `/studio/login` signs in with e-mail and password; on demo tenants the studio signs
  in as the demo admin (`POST /api/demo/login {role: "admin"}`).
- The browser calls a BFF at `/studio/api/*` that forwards an allow-list of
  `/api/admin/course-builder` calls (JSON, multipart uploads and the AG-UI event stream, piped as it
  arrives) with the author's token. Cross-site writes are refused by the existing Origin check.
- Studio islands are vanilla TypeScript modules (no framework), rendering surfaces with the
  `@ulams/ui` builder renderer.

## Consequences

- Good: one catalogue implementation for builder, learner pages and later A2UI over MCP; the preview
  of the landing is the real renderer.
- Good: the browser never holds a Passport token.
- Bad: the web app now needs a second session type and a sign-in page; the admin and the studio
  have separate sessions (sign in twice outside demo mode).
- Bad: the studio's address must be configured for the admin link when hosts do not follow the
  `<slug>.admin.<domain>` / `<slug>.app.<domain>` pattern.
