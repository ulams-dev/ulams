# ulams PHP SDK

PHP client for the ulams API, generated from `front/cli/spec/openapi.json` with openapi-generator, plus a small
hand-written wrapper. Guide: `front/docs-site/src/content/docs/developers/openapi-and-sdk.mdx` ("PHP SDK").

```php
$ulams = Ulams\Sdk\Client::make('https://academy.example.com', getenv('ULAMS_TOKEN'));
$api = $ulams->api(Ulams\Sdk\Api\AdminKnowledgeApi::class);
$exports = $api->knowledgeExportsList();
```

| Path | |
|---|---|
| `lib/` | Generated API classes (`Api/`) and models (`Model/`). Never edit by hand. |
| `src/` | `Client` (base URL, bearer token), `RequestIds` (Idempotency-Key, X-Request-Id), `Pagination` |
| `tests/` | Tests against canned answers; `composer install && composer test` |
| `generate.sh` | Regenerates `lib/` (Docker, pinned generator image). CI fails when `lib/` differs. |

Regenerate after any API change: `yarn workspace ulams sync-spec` (with a fresh `api-docs.json`), then `sdk-php/generate.sh`.
Requires PHP 8.1+. The generated code targets PHP 7.4+ syntax and raises deprecation notices on PHP 8.4
(implicit nullable parameters), which come from the generator templates, not from this package.
