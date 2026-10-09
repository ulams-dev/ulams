# cmi5

## Learner launch

Students have `cmi5_read` and launch an AU with `GET /api/cmi5/player/{auId}` (`?format=json` returns
the launch URL). The AU is served from the tenant content origin; the URL holds a one-time launch
token that `POST /api/cmi5/fetch` exchanges for an LRS-only session token (see `packages/lrs` and
`docs/decisions/0046-cmi5-content-origin-and-launch-token.md`). Deleting a package needs the new
`cmi5_delete` permission (admins). `php artisan cmi5:move-to-bucket` copies packages from the old
`local` disk to `CMI5_DISK` (default: `SCORM_DISK`).
