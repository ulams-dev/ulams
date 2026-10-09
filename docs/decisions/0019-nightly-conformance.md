# 0019. Conformance against real LMSs and builders in an opt-in nightly workflow

- Status: Accepted (2026-10-09)
- Date: 2026-10-09

## Context and problem statement

LTI and Adapt are tested in PHPUnit against in-test fakes. Fakes encode our reading of the specs;
only real counterparts show interoperability problems (the Moodle run found two: Moodle's key type
setting and the completion event order, ADR 0018). Real counterparts are heavy (Moodle is a 1.1 GB
image and installs in minutes; the Adapt framework build needs its own image) or hosted and
interactive (saLTIre).

## Decision

A separate workflow `.github/workflows/nightly-conformance.yml`, never part of `ci.yml`:

- runs on a schedule only when the repository variable `NIGHTLY_CONFORMANCE` is `true`, or by hand
  with a choice of suites;
- **Adapt**: builds `api/adapt-builder`, builds a fixture course and imports it through the SCORM
  upload (`AdaptBuilderRoundTripTest`, skipped without `ADAPT_BUILDER_E2E_URL`);
- **Moodle 5.0 in Docker** (`.github/conformance/compose.yml`), both LTI directions in Chromium with
  grade passback checked in the other system's gradebook; fixtures are set up with Moodle's own PHP
  APIs and ulams models (`.github/conformance/lti/`);
- **saLTIre**: an operator-driven job that exposes a throwaway ulams through a Cloudflare quick
  tunnel, prints the tool settings and waits for launches in the audit log.

## Consequences

- Failures do not block merges; someone has to watch the nightly runs.
- Moodle's image is the frozen `bitnamilegacy` build; moving to another image changes only the
  compose file and the paths in `moodle-setup.php`.
- saLTIre remains semi-manual; Canvas and other platforms can be added as more compose services.
