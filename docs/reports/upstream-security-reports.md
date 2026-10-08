# Upstream security reports (EscolaLMS)

Date: 2026-10-08 · Source: [Phase 0.1 audit](phase-0-audit.md) and the security follow-ups in
[`docs/ROADMAP-TODO.md`](../ROADMAP-TODO.md) §0.1c · Reporter account: `qunabu`

Each finding was checked against the current default branch of the upstream repository before filing; all are
present upstream. Only `EscolaLMS/API` has private vulnerability reporting enabled, so it got a private advisory
report. The `qunabu` account has read-only access to the other repositories (no admin rights, private reporting
disabled), so they got public issues that name only the class of problem and the component, with details offered
privately and no reproduction steps.

| Repo | Kind | URL | Finding | Severity | ulams fix |
|---|---|---|---|---|---|
| EscolaLMS/API | advisory (private report, triage) | https://github.com/EscolaLMS/API/security/advisories/GHSA-mpjf-c7hh-pv78 | Unauthenticated `POST api/test-websockets` broadcasts to any channel, including private ones | medium | `ffd2fc16` |
| EscolaLMS/payments | issue | https://github.com/EscolaLMS/payments/issues/55 | Payment callbacks not verified with the provider: Stripe signature/status, Przelewy24 signature, RevenueCat on by default without receipt verification, Free driver settles non-zero amounts, client currency override | critical | `c300a9d9` |
| EscolaLMS/Cart | issue | https://github.com/EscolaLMS/Cart/issues/151 | Client-controlled payment parameters (driver, currency, `has_trial`); `purchaseProduct` skips purchasable/limit checks | medium | not fixed yet (TODO §0.1c medium follow-ups) |
| EscolaLMS/LRS | issue | https://github.com/EscolaLMS/LRS/issues/18 | `AccessTokenGuard` trusts JWT claims without signature verification | high | `ef97b33f` (first-party xAPI store) |
| EscolaLMS/Consultations | issue | https://github.com/EscolaLMS/Consultations/issues/132 | Unauthenticated webcam screenshot endpoints (`save-screen`, `signed-screen-urls`) | high | `a6ba0030` |
| EscolaLMS/Webinar | issue | https://github.com/EscolaLMS/Webinar/issues/84 | Unauthenticated `signed-screen-urls` endpoint | high | `a6ba0030` |
| EscolaLMS/Course-Access | issue | https://github.com/EscolaLMS/Course-Access/issues/18 | Ungrouped `orWhereDate` in `getUserCourseIds` returns other users' courses | high | `ce5fd045` |
| EscolaLMS/Topic-Type-GIFT | issue | https://github.com/EscolaLMS/Topic-Type-GIFT/issues/49 | Ungrouped `orWhere` in `QuizAttempt::scopeActive` returns another user's attempt | medium | `ce5fd045` |
| EscolaLMS/Tasks | issue | https://github.com/EscolaLMS/Tasks/issues/19 | Ungrouped `orWhere` in `RelatedIdsCriterion` returns other users' tasks | medium | `ce5fd045` |
| EscolaLMS/Stationary-Events | issue | https://github.com/EscolaLMS/Stationary-Events/issues/27 | Ungrouped `orWhereHas` in `StationaryEventRepository` skips filters for authored events | medium | `ce5fd045` |
| EscolaLMS/Jitsi | issue | https://github.com/EscolaLMS/Jitsi/issues/18 | Unauthenticated recording webhook downloads a request-supplied URL (SSRF, arbitrary write) | high | `af4f97d6` |

## Not filed

- Audit items specific to ulams (tenant video queue, missing CI) and the GPL licence findings: not security issues
  upstream.
- TODO §0.1c medium follow-ups that were noted but not verified in the audit (admin tag routes without
  `auth:api`, `POST api/images/img`, `POST api/cmi5/fetch`, vouchers admin search OR grouping, `getChildGroups`
  depth, `_ignition` in production, Stripe test key in the env examples): file after they are confirmed.
- `de4d6213` (JSON escaping in templates): a functional bug, not a security finding.

## Next steps for the EscolaLMS owners

- Enable private vulnerability reporting on the package repositories (or grant `qunabu` admin), then move the issue
  details into private advisories; the issues can be closed or edited once advisories exist.
- Triage GHSA-mpjf-c7hh-pv78 in `EscolaLMS/API`.
