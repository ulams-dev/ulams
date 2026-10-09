# Notice for users of the upstream EscolaLMS packages

Status: **draft, not sent.** The product owner sends it (GitHub issue "owner-action: send the upstream
security notice"). Nothing here has been published; before sending, re-check each finding against the
upstream default branch and confirm the upstream state of the linked issues.

Date of the findings: 2026-10-08 (see [upstream security reports](../reports/upstream-security-reports.md)
for the filing record and [Phase 0.1 audit](../reports/phase-0-audit.md) for the context). Each finding
was checked against the default branch of the upstream repository on that date. We did not test every
tagged release: treat every release of these packages as affected until its maintainers say otherwise.
Commit links point to the ulams repository (`https://github.com/ulams-dev/ulams/commit/<hash>`).

## Who this is for

Anyone running a Wellms / EscolaLMS installation (the `escolalms/*` Composer packages or the
`EscolaLMS/API` template) that exposes the API to the internet.

## Findings and fixes

| Package (upstream) | Issue summary | Severity | Fix in ulams |
|---|---|---|---|
| `escolalms/payments` ([#55](https://github.com/EscolaLMS/payments/issues/55)) | The public payment callback does not verify the provider: a request can mark a Stripe payment as paid; the Przelewy24 signature is not checked; RevenueCat is on by default without receipt verification; the Free driver settles non-zero amounts; the client can override the currency | critical | [`c300a9d9`](https://github.com/ulams-dev/ulams/commit/c300a9d9994f170ef2ebf35b9ab4816331f9787a), [`994566a1`](https://github.com/ulams-dev/ulams/commit/994566a10a8c822ecb416c412720e9d24335cf7b) |
| `escolalms/cart` ([#151](https://github.com/EscolaLMS/Cart/issues/151)) | Client-controlled payment parameters (driver, currency, `has_trial`); `purchaseProduct` skips purchasable and limit checks | medium | [`e9085a82`](https://github.com/ulams-dev/ulams/commit/e9085a82e729f7d59196c9e26b1c7744bfb6f1a5) |
| `escolalms/lrs` ([#18](https://github.com/EscolaLMS/LRS/issues/18)) | `AccessTokenGuard` reads JWT claims without verifying the signature | high | [`ef97b33f`](https://github.com/ulams-dev/ulams/commit/ef97b33fd50375dd88b4684587de05e1eaf70747) (first-party xAPI store with verified tokens) |
| `escolalms/consultations` ([#132](https://github.com/EscolaLMS/Consultations/issues/132)), `escolalms/webinar` ([#84](https://github.com/EscolaLMS/Webinar/issues/84)) | Unauthenticated webcam screenshot endpoints (`save-screen`, `signed-screen-urls`) | high | [`a6ba0030`](https://github.com/ulams-dev/ulams/commit/a6ba0030e1a4a7c029f133953da9b73b2bf8956a) (capture and endpoints removed) |
| `escolalms/course-access` ([#18](https://github.com/EscolaLMS/Course-Access/issues/18)) | Ungrouped `orWhereDate` in `getUserCourseIds` returns other users' courses | high | [`ce5fd045`](https://github.com/ulams-dev/ulams/commit/ce5fd04570189d5b6b5c4ec4dd5bdc3ea0d51471) |
| `escolalms/topic-type-gift` ([#49](https://github.com/EscolaLMS/Topic-Type-GIFT/issues/49)), `escolalms/tasks` ([#19](https://github.com/EscolaLMS/Tasks/issues/19)), `escolalms/stationary-events` ([#27](https://github.com/EscolaLMS/Stationary-Events/issues/27)) | Ungrouped `orWhere` / `orWhereHas` conditions let a user see another user's attempts, tasks or events, or skip filters | medium | [`ce5fd045`](https://github.com/ulams-dev/ulams/commit/ce5fd04570189d5b6b5c4ec4dd5bdc3ea0d51471) |
| `escolalms/jitsi` ([#18](https://github.com/EscolaLMS/Jitsi/issues/18)) | The recording webhook is unauthenticated and downloads a request-supplied URL (SSRF, arbitrary file write) | high | [`af4f97d6`](https://github.com/ulams-dev/ulams/commit/af4f97d6899eeab45b6928a16afe0fb9c2e1350d) |
| `EscolaLMS/API` (private advisory `GHSA-mpjf-c7hh-pv78`) | Unauthenticated `POST api/test-websockets` broadcasts to any channel, including private ones | medium | [`ffd2fc16`](https://github.com/ulams-dev/ulams/commit/ffd2fc163053a7fc03e8fe06618bae96ceee34a7) |
| `escolalms/tags` | Admin tag routes without `auth:api` and permission | medium | [`8f9dd4df`](https://github.com/ulams-dev/ulams/commit/8f9dd4df231aea0246a4beeff4295e69e1e5527b) |

Details (reproduction steps) are deliberately not in public issues; they are offered privately.

## Recommended actions

1. Do not expose `ANY /api/payments-gateways/callback/{payment}` and the Jitsi recording webhook
   publicly until the package is patched. Put the Stripe webhook behind signature verification and set
   the signing secret.
2. Remove or block the webcam screenshot routes (`save-screen`, `signed-screen-urls`) in Consultations and Webinar.
3. Remove `POST api/test-websockets` from the API template.
4. Wrap `api/admin/tags` routes in `auth:api` with a permission check.
5. Review every `orWhere` next to a user filter in custom queries; group them in a closure.
6. Disable the RevenueCat driver unless a server-side receipt verifier is configured.
7. Rotate any Stripe key that was ever committed to the `.env.example` files of the template.

## Contact

The reporter account is `qunabu`. Replies go to the maintainers named in the issues.
