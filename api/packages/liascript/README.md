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

## Rendering (not yet)

Learners do not see LiaScript topics yet. The plan (docs/plans/phase-1.md, 5.2, option b′) is to
package the source as SCORM with the vendored LiaScript SCORM build and play it through the existing
SCORM runtime on the content origin. The spike result is recorded in the plan.

## Tests

```bash
vendor/bin/phpunit --testsuite liascript
```
