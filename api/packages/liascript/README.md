# LiaScript

Course sources in [LiaScript](https://liascript.github.io) Markdown, stored as **versioned Markdown
plus assets** (spec 1.1). The Markdown is the source of truth, so the AI phases can read and diff it.

- Every change, upload or restore adds an immutable version (`liascript_versions`); nothing is
  overwritten.
- Assets from a `.zip` upload live on the tenant disk under `liascript/<document>/v<version>/`
  and are carried over by later text-only versions. Uploads go through the upload guard
  (`packages/uploads`, kind `liascript`): zip-slip, symlinks, zip bombs, size and type.
- Remote `import:` macros are reported as warnings: they would load code from another site and are
  blocked by the content-origin CSP.

## API (permission `liascript_manage`, admins and tutors)

| Method | Path | |
|---|---|---|
| GET | `/api/admin/liascript` | list |
| POST | `/api/admin/liascript` | create from `markdown`, or `file` (`.md` or `.zip` with `README.md` + assets) |
| GET / PUT / DELETE | `/api/admin/liascript/{id}` | show, rename, delete with all versions and assets |
| GET | `/api/admin/liascript/{id}/source?version=` | Markdown (`text/markdown`), current version by default |
| GET / POST | `/api/admin/liascript/{id}/versions` | list versions, add a version (`markdown` or `file`, `change_note`) |
| POST | `/api/admin/liascript/{id}/versions/{version}/restore` | restore (adds a new version) |
| POST | `/api/admin/liascript/{id}/preview` | live preview of unsaved `markdown`: `{url, sections, warnings}` |

**Live preview.** The admin editor sends the unsaved text when typing pauses. The API writes it as
`liascript/<doc>/v<current>/preview-<128-bit random>.md`, next to the current version so its
relative asset links resolve, and returns the player URL with `#preview=1&course=…`: the player
runs without a token and reports no progress. Drafts older than `LIASCRIPT_PREVIEW_TTL` (1 h) and
all but the newest `LIASCRIPT_PREVIEW_KEEP` (4) per version are deleted on every preview; nothing is
saved as a version.

## Topic type and playback

The topic type `Ulams\LiaScript\Models\LiaScriptTopic` (`value` = document id) plays the document's
current version on the tenant content origin (api/docs/content-origin.md):

1. `POST /api/liascript/launches/{topic}` (learner, course access checked) publishes the player and the
   version to the LiaScript disk (`liascript/_player/...`, `liascript/<doc>/v<n>/README.md` with its
   assets copied into the version folder) and returns
   `<content origin>/liascript/_player/index.html#api=…&topic=…&token=…&course=…&sections=…`.
2. Our page (`resources/player/index.html`, `player.js`) runs the LiaScript SCORM 1.2 build in a
   same-origin iframe and provides `window.API`; slide position and status go to
   `POST /api/liascript/progress/{topic}` with a topic-scoped token (`X-Ulams-Tracking-Token`, HMAC
   with the tenant `APP_KEY`, 4 h).
3. The topic is complete when the learner reaches the last section (one section per heading outside
   code blocks) or LiaScript reports `completed`/`passed`.

The LiaScript build (`@liascript/exporter` 3.4.2--2.1.0, `dist/assets/scorm1.2` + `common`,
BSD-3-Clause, about 12 MB) is **not in git**: `bin/fetch-player.sh` downloads it at a pinned version
and SHA-256 into `resources/player/build/`. The Dockerfiles run it at image build time; in development
(the api folder is bind-mounted) `init.sh` runs it on container start when the build is missing; run it by hand
with `make liascript-player` (from `api/`).
Without the build or a content origin, launches answer 503 with an explanation.

## Course export and import

A course export (`packages/courses-import-export`) carries the course text: each LiaScript topic
writes its document's current version to `topic/<topic id>/liascript/` in the archive
(`README.md` plus assets), and `content.json` names that folder (`liascript_folder`,
`liascript_title`, `liascript_version`; no document id, which means nothing in another tenant).
On import, `LiaScriptTopicImportStrategy` repacks the folder and creates a **new document** through
the normal upload (upload guard, safe extraction, Markdown checks); the topic points to it. Only the
current version travels: the version history stays with the source course. Course cloning uses the
same path, so a clone gets its own copy of the text.

## Tests

```bash
vendor/bin/phpunit --testsuite liascript
```
