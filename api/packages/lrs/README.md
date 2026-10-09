# Learning Record Stores

A small, first-party xAPI learning record store for cmi5 content, plus the cmi5 launch endpoints
used by the front app and the admin statement list.

## Install

1. get package from composer `composer require ulams/lrs`
2. run the migrations (`php artisan migrate`); they create the store tables only when missing
3. run the seeder `php artisan db:seed --class="Ulams\Lrs\Database\Seeders\LrsSeeder"` to create the
   store owner, client and xAPI access
4. make sure that Response Headers are not overwritten by any layer, the xAPI endpoints respond with

```
x-experience-api-version: 1.0.3
```

## xAPI endpoint

`{APP_URL}/trax/api/{access uuid}/xapi/std` (returned as `endpoint` by `GET /api/cmi5/courses/{id}`).
The URL and the table names (`trax_*`) are kept from the earlier TRAX-based implementation, so
existing launch links and stored records keep working.

### Authentication

`Authorization` must be one of:

- `Basic <token>` or `Bearer <token>` with the learner's Passport access token (what cmi5 content
  receives from the fetch URL). The RS256 signature is verified with the Passport public key
  (`passport.public_key` or `storage/oauth-public.key`), `exp`/`nbf` are checked, and the token must
  exist in `oauth_access_tokens`, not revoked and not expired.
- `Basic base64(username:password)` with the access's own credentials (`trax_basic_http`; bcrypt
  hashes, legacy plain values are compared in constant time).

The access must be active and its client active. Failures answer `401`. Every request except
`about` needs an `X-Experience-API-Version: 1.0.x` header (`400` otherwise).

### Supported resources

| Resource | Methods | Notes |
|---|---|---|
| `statements` | `POST`, `PUT`, `GET` | single statement or batch; `statementId` / `voidedStatementId`; filters `agent`, `verb`, `activity`, `registration`, `since`, `until`, `limit` (default 100, max 500), `ascending`; paging through `more` |
| `activities/state` | `GET`, `PUT`, `POST`, `DELETE` | `activityId`, `agent`, `stateId`, `registration`; `GET`/`DELETE` without `stateId` list/delete all; `since` |
| `activities/profile` | `GET`, `PUT`, `POST`, `DELETE` | `activityId`, `profileId`; `GET` without `profileId` lists ids |
| `agents/profile` | `GET`, `PUT`, `POST`, `DELETE` | `agent`, `profileId`; `GET` without `profileId` lists ids |
| `about` | `GET` | no authentication |

- Statements are validated structurally (required properties, one agent identifier, IRIs, UUIDs,
  ISO 8601 timestamps and durations, score ranges, unknown properties). The store sets `stored`,
  `authority`, `version` (`1.0.0`) and `timestamp` when missing. Re-sending a statement with the same id
  and content is accepted; different content answers `409`. Voiding statements mark their target voided.
- Documents: `POST` merges JSON objects; `PUT` replaces. `GET` returns an `ETag`; `If-Match` and
  `If-None-Match: *` are honoured (`412`).
- Statements and documents are isolated per store owner.

### Not supported

- Statement attachments (multipart requests answer `400`) and binary (non UTF-8) documents.
- The `activities` and `agents` resources (`GET /activities`, `GET /agents`), the alternate request
  syntax (`?method=`), `related_agents` / `related_activities`, and statement `format` other than the
  stored ("exact") form; `X-Experience-API-Consistent-Through` is always "now".
- Signed statements and full validation of interaction definitions, language codes and extensions.

## Testing

1. Download [cmi5-demo](https://github.com/xapijs/cmi5-demo) and run it with static file server - [`npm run serve`](https://www.npmjs.com/package/serve) or [`php -S localhost:8000`](https://www.php.net/manual/en/features.commandline.webserver.php) is good enough
2. Generate [fetch params](http://aicc.github.io/CMI-5_Spec_Current/flows/lms-flow.html) for a course id calling `/api/cmi5/courses/{id}` endpoint
3. Start course from point 1 with generated params, use `url` object example below

```bash
http://localhost:3000/?endpoint=https%3A%2F%2Fapi-stage.ulams.app%2Ftrax%2Fapi%2Faf743842-8870-445e-9ca9-f4dcbde65efe%2Fxapi%2Fstd&fetch=https%3A%2F%2Fapi-stage.ulams.app%2Fapi%2Fcmi5%2Ffetch%3Ftoken%3DeyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI5NGExY2RiYi1iZTRiLTRlMjktOTRhZi1mYzk5MjI1YTQ2NmMiLCJqdGkiOiI0ZTliNGE0OTAwZWEwYmEyOWM5ODIwNmVkYzg2YWU0MDQ4M2JmZmNiMGNlYTc2OTU5YjkwZTM1ODk0ZTU2Njk2Mzc4MDA1ZWYyOGMwMmRhZSIsImlhdCI6MTY0MzA0NTEyOS4zMjgxNzgsIm5iZiI6MTY0MzA0NTEyOS4zMjgxODYsImV4cCI6MTY3NDU4MTEyOS4zMjA5MjcsInN1YiI6IjIiLCJzY29wZXMiOltdfQ.hQr_XUoEByCvgFH8S94JLmccqxlg-Zh6dPxEflWD3ABKQQcnSum10IEMrjE9_O0HMHArdwbbi8ebJv0f1XrHEgx2nkw8O5cWIbT27OBnaR86gA3yshg0g5BuM693WvWqH_kc2fK9uF9148b0vcvFsCKX3vru6gLv0NT3WhMKIt7vMSyZrBhD2i1WtgyrpiVz81Tua1f2c7Pcxbir8jijr71Y2H-ZszytxglWvXYtGzCVyY0JiiZV50-did8PhCCTGPKlg3wIYdeVTFRozbTRe-9bF660QhavJr6WMi_ymvnL8hK-BqQWEHTbVdCDXYKMM9WkodqAAk6CWcTRXzPgQT4UTvOPu_rxNMTKU-hA6xaZqGjo5esGId2FMJXxtzMp8MRR2oLxjta6fTmmlgtBXMy1s4thIDlbWIZPSLVx95m85vos2R2TxMc_hKq5FoLp_j78TsJc_zXbxphToVDKybwCAvZC0nreyV3dseNd3urtdDtPmXJnDoasSoQw38GVbj4VlxQ1gq8J9DDtOPmJ3St9j4lMDEXpjZ5WKKKnrmdmxUQi-ti1V4oZ1phARh-KeAIIwfHAR5IdCUVmj6wVvErOUMZwgo9QsvmdoxLVFEe2uwmD9W01crpEKboZ9qtG2cmIDB4PzgrUM6lIwCTtquRPlKMHX-l8PRW3hW7P9Us&actor=%7B%22mbox%22%3A%22mailto%3Aadmin%40ulams.com%22%2C%22objectType%22%3A%22Agent%22%2C%22name%22%3A%22Admin+A%22%7D&registration=cfddab74-b3af-4262-ba18-21b0c8f8273c&activityId=https%3A%2F%2Fapi-stage.ulams.app%2Fxapi%2Factivities%2Fcourse%2F37%2Ftopic%2F671
```
