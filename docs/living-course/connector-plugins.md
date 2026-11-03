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

## Built on the contract: Google Drive and Notion

`Connectors\Google\GoogleDriveConnector` and `Connectors\Notion\NotionConnector` follow plan 6.6 and show what a
connector with an account sign-in looks like. They implement `OAuthConnector` (a `oauthProvider()` on top of the
contract), so `Oauth\OAuthService` can sign the author in and hand the connect request a one-time `grant` instead
of a token: the settings are `{fileId}` or `{pageId, includeChildren}`, the secrets `{refresh_token}` or `{token}`.

- `fetch()` asks for a fresh access token (Google) and compares the remote versions first: a Drive file or folder by
  `version` and `modifiedTime`, a Notion page by `last_edited_time`, so an unchanged source costs one metadata
  request. Google Docs are exported as Markdown (`text/markdown`; the plan said DOCX, the Markdown export keeps
  headings and lists without a converter), text and Markdown files are downloaded, Word and PDF files go through the
  shared converters. Notion blocks are converted by `BlocksToMarkdown` (text only: images, files and embeds are
  dropped, only http(s) links survive).
- Both talk through `Remote\RemoteApi`, a thin wrapper over `SafeHttp` that maps statuses to our own messages and never
  repeats what the remote said.
- Webhooks (Drive push channels, Notion webhooks) are not used; the poll schedule finds changes.
- Both are off until listed in `LIVING_COURSE_CONNECTORS`; each needs the installation's OAuth client
  (`living_course.oauth.google|notion`). Tests use a scripted vendor (`tests/Support/FakeVendor.php`), no network.
