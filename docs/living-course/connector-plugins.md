# Connector plugins

A source connector is the only code a plugin needs to feed Living Course (ADR 0032). It fetches the
files of a source; Living Course converts them to Markdown, splits them into fragments with stable
ids, compares them with the previous revision, finds the course elements that cite what changed and
prepares an update proposal. A connector never produces fragments, never calls a model and never
writes the database.

## The contract

`Ulams\LivingCourse\Connectors\SourceConnector`:

| Method | What it does |
|---|---|
| `key()`, `label()` | The stable key stored on the connection, and the name shown in the studio. |
| `configSchema()` | JSON Schema (draft 2020-12) of the non-secret settings; the studio renders the form from it. |
| `secretFields()` | Names of write-only secrets (tokens). They are encrypted at rest, never returned by the API, never logged, never sent to the model. |
| `validate($config, $secrets)` | A dry run at connect time: reachability, permissions, limits. Throw `ConnectorException` with a message that is safe to show. |
| `fetch($connection, $latest)` | Return a `FetchResult`: `unchanged` when the remote reference still matches `$latest->origin_ref`, otherwise a reference (commit SHA, ETag, hash) and the `FetchedFile`s (`path`, `bytes`, `kind` markdown, pdf, docx or html, optional `blob` id). |
| `supportsWebhooks()`, `verifyWebhook()` | Optional. Verify the signature with `hash_equals`, throw `InvalidSignature` when it does not match, return `null` for deliveries that do not concern the source. |

Rules that keep tenants safe:

- Every HTTP request goes through `Ulams\Core\Http\SafeHttp::client()` (https only, public addresses only,
  pinned resolution, size cap, redirects limited to the host). Never use a bare Guzzle client or `file_get_contents` on a URL.
- Anything you fetch is untrusted input. Do not interpret it, do not put it in log lines and do not
  copy commit messages, file names or headers into `FetchResult::$metadata` unless they are safe to show in the UI.
- Keep fetches cheap: return `unchanged` before downloading anything when you can.
- Respect the limits in `config('living_course.connector_limits')` (files, bytes per file, total bytes).

## Registering a connector

```php
public function boot(): void
{
    if (class_exists(SourceConnectorRegistry::class)) {
        $this->app->afterResolving(SourceConnectorRegistry::class, fn ($registry) => $registry->register(new MyConnector()));
    }
}
```

An installation enables connectors with `LIVING_COURSE_CONNECTORS` (default `upload,git,url`); add your key to the list.

## Worked example

`api/packages/example-plugin/src/Connectors/ExampleConnector.php` is a complete, network-free connector: its
pages are configured inline. `api/packages/living-course/tests/Feature/ConnectorPluginTest.php` registers it,
connects a session through `POST /api/admin/living-course/sessions/{session}/sources/connect`, changes the
pages and checks the source, which is the whole path from a plugin to an update proposal.

## Designed, not built: Google Drive and Notion

Both need an OAuth app per installation (client id and secret, a redirect URI on the tenant host), token
refresh and per-file permissions. As connector plugins each would take the settings `{fileId}` or
`{pageId, includeChildren}` and the secret `{refresh_token}`; `fetch()` would export Google Docs as DOCX
(`files.export`) and read Notion pages as blocks converted to Markdown, with `modifiedTime` or
`last_edited_time` as the reference; webhooks (Drive push channels, Notion webhooks) come after polling.
