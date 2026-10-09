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

ulams has no versioned releases yet. Security fixes land on the `main` branch and in the container
images published from it (`ghcr.io/ulams-dev/*`, tag `latest` and `sha-<short>` tags from `main`).
Run the latest images and update to new fixes. Older commits, branches and image tags are not
patched. When versioned releases start, this section will list the supported release lines.

| Version | Supported |
|---|---|
| `main` and the `latest` images | Yes |
| Anything older | No |

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
- Infrastructure defaults in the development stack (MinIO, Soketi, MailHog, Adminer) are for local
  development and are not meant to be exposed in production.

`api/SECURITY.md` points to this file; this policy covers the whole repository.
