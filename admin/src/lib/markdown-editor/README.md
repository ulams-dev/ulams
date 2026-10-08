# markdown-editor (vendored)

ProseMirror-based rich Markdown editor (fork of outline/rich-markdown-editor). Used only by
`admin/src/components/WysiwygMarkdown`.

- Upstream: https://github.com/EscolaLMS/markdown-editor (npm `@escolalms/markdown-editor`)
- Version: 12.0.6 = commit `b747a1da184a23561c5bf5c38146ccbc777f7c26` (not upstream HEAD)
- Copied: `src/` minus storybook stories and the jest test/snapshot.
- Import as `@lms/markdown-editor`; alias in `admin/config/config.ts` + `admin/tsconfig.json`.
- Runtime deps moved to `admin/package.json` (prosemirror-*, @benrbray/prosemirror-math, markdown-it*,
  katex 0.13, outline-icons, refractor, react-portal, …). Upstream pinned `prosemirror-transform@1.2.5`
  but never imported it; the single hoisted prosemirror stack is used now. Runs on styled-components 6
  (what admin already resolved), upstream targeted 5.
- Typecheck: separate TS project (`tsconfig.json` here, upstream non-strict settings) referenced from
  `admin/tsconfig.json`; `yarn typecheck` in admin runs `tsc -b` on it first and admin consumes the
  emitted declarations (`admin/node_modules/.cache/lms-markdown-editor`).
- Local changes vs b747a1d (type fixes for current deps, no behaviour change except where noted):
  `markdown-it/lib/token` is ESM-only in markdown-it 14 → type-only import + `state.Token` at runtime;
  prosemirror-view `Decoration` no longer generic; `DecorationSet.find(undefined, …)`; `ComponentView.dom`
  is never nulled; Link handlers typed for mouse+touch; `withTheme` exports typed for styled-components 6;
  `onSave` signature typo.
- License: BSD-3-Clause (`LICENSE`).
