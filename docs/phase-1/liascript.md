# LiaScript courses

Notes for the docs site. Code and API: `api/packages/liascript/README.md`; decision: ADR 0016.

## For authors

- **Courses → LiaScript** in the admin lists the LiaScript documents. Create one from Markdown, from a
  `.md` file, or from a `.zip` with a `README.md` (or one other `.md` file at the root) and its
  images, audio and other files. Relative links in the Markdown (`![diagram](img/x.png)`) must match
  paths in the zip.
- **Every save is a new version.** Nothing is overwritten: the version list shows who changed what,
  "Show" compares a version with the current one (added and removed lines), and "Restore" adds a new
  version with the old text. Files from a zip stay available to later versions that only change the
  text.
- **Preview** shows the text as you type it (refreshed when you stop typing for a moment), with the
  files of the current version, exactly as learners will see it. The preview records no progress and
  is not saved; drafts disappear after an hour.
- **Remote `import:` macros do not work**: courses run on an isolated origin without access to other
  sites. The editor warns about them; copy the macros into the course instead.
- To use a document in a course, add a lesson topic of type **LiaScript** and pick the document.
  Learners always get the **current** version; saving a new version updates every course that uses
  it. A document used by a topic cannot be deleted.

## For learners

The course opens inside the lesson. The topic is complete when the learner reaches the last section
(one section per heading) or when a quiz in the course reports `completed`/`passed`.

## Course export and import

Exporting a course includes the current text and files of each LiaScript topic. Importing (or
cloning) the course creates a **new** document from them, so the copy can be edited independently;
the version history stays with the original document.

## For operators

- The LiaScript player is downloaded when the API image is built (pinned version and checksum). In a
  development container with the `api/` folder mounted, run it once:
  `docker compose exec api sh packages/liascript/bin/fetch-player.sh`.
- Without a content origin (`CONTENT_ORIGIN`) or the player, launches and previews answer 503 with an
  explanation.
- `LIASCRIPT_PREVIEW_TTL` (seconds, default 3600) and `LIASCRIPT_PREVIEW_KEEP` (default 4) control
  how long preview drafts live.
