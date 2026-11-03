# Security policy

## Reporting a vulnerability

**Do not report security vulnerabilities in public issues, discussions or pull requests.**

Report them privately through GitHub's private vulnerability reporting:

1. Open the [ulams-dev/ulams repository](https://github.com/ulams-dev/ulams).
2. Go to the **Security** tab and choose **Report a vulnerability**
   (direct link: <https://github.com/ulams-dev/ulams/security/advisories/new>).
3. Fill in the form. Only you and the maintainers can see the report.

If the **Report a vulnerability** button is missing, private reporting has not been enabled yet;
open a public issue that asks for a private contact **without any details of the vulnerability**,
and a maintainer will reach out.

## What to include

- The affected component: API (`api/`, and the package in `api/packages` if you know it), admin,
  learner front, reference frontend (`front/web`), H5P service (`api/h5p`), PDF service
  (`api/pdf`), a container image, or the documentation site.
- The version: commit SHA, or the image name and tag (for example `ghcr.io/ulams-dev/api:sha-abc1234`).
- The kind of issue (for example cross-tenant data access, authentication or authorization
  bypass, injection, prompt injection through uploaded content, SSRF, XSS) and its impact.
- Step-by-step reproduction, a proof of concept if you have one, and the configuration needed
  (single tenant or multi-tenant, relevant environment variables, never real secrets).
- Whether the issue is already public or known to others, and how you would like to be credited.

## Supported versions

The images, the CLI and (from the Helm chart on) the chart share one version, `vX.Y.Z`
(semantic versioning). Each release is published as image tags `X.Y.Z` and `X.Y`, and two channels
move with releases:

- `stable`: the newest release.
- `lts`: the newest patch of a long-term support line. One minor line a year is declared LTS (from
  1.0 on), listed in `.github/release-channels.json`, and gets security fixes for 12 months.

`latest` and `sha-<short>` keep meaning the `main` branch.

| Version | Supported |
|---|---|
| The newest release (`stable`) | Yes |
| LTS lines, for 12 months from their first release (`lts`) | Security fixes |
| `main` and the `latest` images | Yes |
| Older releases and image tags | No: upgrade to `stable` or the current `lts` |

No release has been cut yet (the first one needs the owner's approval); until then only `main` and the
`latest` images are supported.

## Patch policy

Container images are scanned with Trivy on every pull request, on every publish and every night
(`.github/workflows/ci.yml`, `publish.yml`, `nightly-security.yml`). Only vulnerabilities that have a fix
available are gated.

| Finding | Target |
|---|---|
| CRITICAL in a published image | fixed image published within 2 days |
| HIGH in a published image | within 14 days |
| Lower severity, or no fix available yet | with the next regular release; tracked, not gated |

The clock starts when the nightly scan opens its `security` issue or when we learn of the problem from an
advisory, whichever is first. A fix is a rebuilt image (the publish workflow pulls fresh base layers), a
dependency update, or a documented exception in `.trivyignore.yaml` with a reason and an expiry date.
Published images are signed with cosign (keyless, GitHub OIDC) and carry an SBOM; how to verify them is in
the hardening guide ([operators documentation](front/docs-site/src/content/docs/operators/hardening.mdx)). A published image that
fails the critical gate is not signed.

## Disclosure process

1. We aim to acknowledge the report within 5 working days and then confirm whether we can
   reproduce it.
2. We assess the severity and agree a fix timeline with you, keeping you updated in the private
   advisory.
3. The fix is developed in a private fork linked to the advisory and merged into `main`; new
   images are published.
4. We publish a GitHub security advisory (with a CVE when appropriate) once the fix is available,
   and credit you unless you prefer to stay anonymous.

We ask you to give us a reasonable time to fix the issue before disclosing it publicly (90 days
by default, or sooner once a fix is released), and not to access or modify data that is not yours,
degrade the service for others or test against deployments you do not own.

## Scope notes

- `api/h5p` bundles third-party H5P core and editor code; vulnerabilities in H5P itself are
  best reported upstream as well. We will still patch or pin our copy.
- Infrastructure defaults in the development stack (SeaweedFS with the admin key from the compose file, MailHog, Adminer) are for local
  development and are not meant to be exposed in production.

`api/SECURITY.md` points to this file; this policy covers the whole repository.
