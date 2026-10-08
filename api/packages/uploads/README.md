# Uploads

## What does it do

One upload guard for every path that accepts third-party files (SCORM, cmi5, course import, and
later LiaScript, Adapt and course-builder sources):

- **`UploadGuard`** checks a file against the policy of its kind (`config('ulams_uploads.policies')`):
  size, extension, MIME type sniffed from the content (`finfo`, never the client header), virus scan
  and, for archives, the zip inspector. The `SafeUpload` validation rule wraps it:
  `'zip' => ['required', 'file', new SafeUpload('scorm')]`.
- **`ZipInspector`** rejects zip-slip paths (`..`), absolute paths, drive letters, backslashes,
  control characters, symlink entries, duplicate normalised names, too many entries, too large
  files and suspicious compression ratios (zip bombs).
- **`SafeExtractor`** extracts entry by entry under a normalised path to any Laravel disk (or a
  local directory). It never calls `ZipArchive::extractTo`, counts the real bytes while copying
  (central-directory sizes can lie) and removes what it wrote when it fails.
- **`VirusScannerContract`** with `NullScanner` (default) and `ClamdScanner` (clamd `INSTREAM`
  over TCP). Start ClamAV with `docker compose --profile av up -d clamav` and set
  `UPLOADS_SCANNER=clamd`. ClamAV is GPL-2.0 and runs as a separate process.
- **Active content on the bucket origin**: the `s3` driver is replaced by
  `ActiveContentSafeS3Adapter`, which sets `Content-Type` from the extension and stores SVG, HTML,
  XML and files of unknown type with `Content-Disposition: attachment`, so opening their URL
  downloads them instead of running their scripts. `<img src>` ignores the header. Package
  prefixes (`scorm/`, `cmi5/`, …) are left alone; they are served from the per-tenant content origin
  with its own CSP.

Error reasons (`UploadRejected::$reason`): `invalid_archive`, `zip_slip`, `absolute_path`,
`invalid_name`, `symlink`, `duplicate_entry`, `too_many_entries`, `zip_bomb`, `too_large`,
`wrong_type`, `infected`, `scan_failed`, `storage_error`.

## Configuration

See [src/config.php](src/config.php) and the uploads section of
[docs/enviromental-variables.md](../../docs/enviromental-variables.md).

## Tests

```bash
vendor/bin/phpunit --testsuite uploads
```

Fixtures (zip-slip, absolute path, symlink, entry count, zip bomb, wrong MIME, EICAR via a fake
clamd) are built at test time by the `ZipFixtures` trait, which other packages' tests reuse.
