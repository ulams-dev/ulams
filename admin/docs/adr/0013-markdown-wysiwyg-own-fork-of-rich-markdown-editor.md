# 0013. Markdown WYSIWYG: rich-markdown-editor, then an own fork `@escolalms/markdown-editor`

- Status: Accepted (retroactive)
- Date: 2024-01-04

## Context and Problem Statement

Course descriptions, lessons and text topics are stored by the API as Markdown. Admin authors need a WYSIWYG editor that reads and writes Markdown. Outline's `rich-markdown-editor` (ProseMirror + styled-components) fits, but the team needed features it lacked (text/heading alignment, image sizes). In the vendored clone the last Outline commit is from 2020-08-31 and the last traverse-fork commit from 2022-12-22 (Inferred: neither upstream was a practical place to land these changes).

## Considered Options

- `rich-markdown-editor` from npm (June 2021 – April 2023).
- `traverse-markdown-editor`, a community fork of it (April 2023 – January 2024).
- An own fork, `EscolaLMS/markdown-editor`, published as `@escolalms/markdown-editor` (chosen).

## Decision Outcome

`src/components/WysiwygMarkdown` wraps the editor. In December 2023 the team forked the traverse-markdown-editor repository (which itself tracks Outline's), added alignment and image-size extensions (Jira TOS-493/TOS-494, WELLMS-426), added Storybook stories and an npm publish workflow, and released `12.0.6`; the admin switched to it the same day.

Inferred: the extensions were made in a published package, rather than patched inside the admin, so that the editor could be versioned and reused independently.

### Consequences

- Good: Markdown stays the storage format; the team controls editor features.
- Bad: the fork carries an old stack (React `^16 || ^17` peer range, TypeScript 3.9, styled-components 5, CircleCI config) and must be maintained by the team; it is being vendored into the monorepo.

## Evidence

- `1bb676d6` 2021-06-09 "course edit form (#15)" — `admin/package.json` (`rich-markdown-editor ^11.10.0`).
- `0a5363d1` 2023-04-14 "Feature/md editor (#772)" — `traverse-markdown-editor ^11.7.45`, `admin/src/components/WysiwygMarkdown/index.tsx`.
- `1b4d2d96` 2024-01-04 "feat: switch md editor lib" — `@escolalms/markdown-editor ^12.0.6`.
- markdown-editor repo: `efb6d63` 2018-03-27 "first commit" (Outline); `36e739f` 2020-08-31 "10.6.6-0" (last Outline commit); `b29b000` 2020-05-10 "Name traverse-markdown-editor"; `10e720a` 2023-12-12 "feat: add text and heading align options"; `76681a5` 2023-12-13 "feat: add image sizes"; `ae50488` 2024-01-03 "feat: publish to npm workflow"; `b747a1d` 2024-01-04 "12.0.6"; `dca5071` 2024-01-22 merge of PR #4 "fix/WELLMS-426/image-size".
