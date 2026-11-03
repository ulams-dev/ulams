# Plan: PHP critical RCE advisory (2026-10-21)

Status: proposed, waiting for the owner's decisions in section 7.
Source: [php.net announcement 2026-10-21-5](https://www.php.net/archive/2026.php#2026-10-21-5), "Security advisory:
Critical remote code execution vulnerabilities, update now". Everything in section 1 is what that page says; nothing
here has been confirmed against the PHP source or the GitHub advisories.

## 1. The advisory

- Fixed in **PHP 8.5.12, 8.4.27, 8.3.36 and 8.2.35**. PHP 8.1 and older get no update from the PHP project (minimal
  source backports for 7.4, 8.0 and 8.1 are linked from the page).
- Two critical, unauthenticated remote code execution bugs:
  - **CVE-2026-109618** (GHSA-w8hw-w9fx-j79w), "RCE via OOM corruption": out-of-memory handling can interrupt PHP while
    internal request data is partly initialised, and request cleanup then treats it as valid.
  - **CVE-2026-109619** (GHSA-6wmf-4rv5-p3rp), "RCE via stale global state": request-owned internal state survives
    after its memory was freed, and a later request on the same worker can use it before it is reset.
- Affected setups: PHP-FPM, Apache mod_php, php-cgi, FrankenPHP, LiteSpeed (highest risk: mod_php). No extension is named.
- The page's interim measures (it says only updating is complete):
  - CVE-2026-109618: `memory_limit=-1` set where the application cannot override it (FPM `php_admin_value`), and
    `post_max_size` of 8M (no uploads) or 32M (uploads), with `upload_max_filesize` below it. Do not use `RLIMIT_AS` or
    `RLIMIT_DATA`.
  - CVE-2026-109619: `session.upload_progress.enabled=0` in php.ini, an FPM pool or server config that applies before
    request parsing (not `ini_set()`, not FPM `.user.ini`), then restart every worker.
  - PHP 8.5 only: `fatal_error_backtraces=0`.

## 2. What we run

| Place | PHP | Exposed to the internet? | Who patches it |
|---|---|---|---|
| Image `ghcr.io/ulams-dev/php:8.4` (`api/docker/php`, `FROM php:8.4-fpm-alpine`) and the `api` image built from it | 8.4 (official image) | not published yet; used by `ulams stack up`, Compose, the VPS plan | us: rebuild once the official image has 8.4.27 |
| Staging APIs on MyDevil (`staging-api`, `coffee-staging-api`, `{gravity,poland,ulam,oncall,nightsky}-staging-api`) | `php84` from the hosting provider, FPM behind their nginx | **yes** (Cloudflare in front) | the provider; we cannot patch it |
| GitHub Actions (setup-php 8.4, 8.3 for one job, `docs.yml`, nightly) | latest on the runner | no (short-lived, no inbound requests) | automatic, nothing to do |
| Local dev containers on this machine (`api-api`) | whatever image was built | no | rebuild on the next stack refresh |
| `composer.json` requirement | `^8.4` | n/a | no change needed |

Our own settings against the advisory (`api/docker/conf/php/*.ini`): `memory_limit` 1G or 2G, `post_max_size` and
`upload_max_filesize` up to 2G (large course packages), and `session.upload_progress.enabled` is not set, so it is
the PHP default (on). Nothing in the repo uses upload progress (no `session.upload_progress`, no
`UPLOAD_PROGRESS` field). The 32M `post_max_size` stop-gap would break large uploads, which the product needs.

**Checked on 2026-10-21:** `docker run php:8.4-fpm-alpine php -v` still prints **PHP 8.4.26** (built 2026-09-24). The
official image is not patched yet, so rebuilding our images now would not fix anything.

## 3. Goal and scope

Stop running a vulnerable PHP anywhere a request from outside can reach it, prove it with a check that fails if we
regress, and make the next PHP release visible to us without anybody having to read php.net.

Out of scope: moving off MyDevil (issue #160), PHP 8.5 (we are on 8.4), the vendor's own release timing.

## 4. Plan

### Phase A, today: reduce exposure while the fix is not available

1. **Disable upload progress everywhere we control the config** (no product use, so no cost): add
   `session.upload_progress.enabled = 0` to every ini under `api/docker/conf/php/` and, to be sure it applies before
   request parsing, `php_admin_value[session.upload_progress.enabled] = 0` in
   `api/docker/php/conf/php-fpm.d/00-ulams-base.conf`. Test: a PHPUnit/CLI check that `ini_get` is `0` in the built
   image (see phase B). This goes into the image that phase B builds, so it needs no separate deploy.
2. **Staging on MyDevil (the real exposure).** We cannot set ini values the advisory accepts (`.user.ini` is explicitly
   not enough) and cannot update PHP. Options, owner decides in section 7: (a) ask MyDevil support today for the patched
   `php84` and an ETA, (b) meanwhile take the staging APIs offline at Cloudflare (pause the proxied hostnames or an
   always-block rule) and restore them after the provider confirms 8.4.27, (c) leave them up and accept the risk
   (staging holds demo data only, but it also holds the staging `.env` with database and application secrets, and an
   AI key if #178 is answered yes, so a code execution there is not harmless).
3. **Decide the `post_max_size` and `memory_limit` stop-gap** only for hosts that stay exposed (section 7, Q2).
   Recommended default: not applied to our images, because they are not exposed and the real fix is hours or days away.

### Phase B: the fix, as soon as `php:8.4-fpm-alpine` is 8.4.27 or higher

1. **Gate on the version, so we never ship a vulnerable build.** In `api/docker/php/install.sh` (or the Dockerfile) fail
   the build when `PHP_VERSION` is below 8.4.27: `php -r 'exit(version_compare(PHP_VERSION, "8.4.27", ">=") ? 0 : 1);'`.
   Added in the same PR as the first rebuild (SEC2), when the official image is already patched, so it never turns
   the publish workflow red; afterwards it stops a stale base from being shipped again.
2. **Rebuild and publish** the PHP base image and the images built on it. In `.github/workflows/publish.yml` set
   `pull: true` for the php build so it takes the new base, keep the layer cache otherwise. Tag `8.4` (moving) and
   `8.4.27`-style immutable tags (add the full version as a tag from `php -v`).
3. **Verify** before anything is deployed: `docker run --rm ghcr.io/ulams-dev/php:8.4 php -v`, the `ini_get` check for
   upload progress, `php-fpm -tt` loads the pool config, and the full API suites in CI on the new image (`Image smoke`
   plus the PHPUnit shards run on the runner PHP, so also run one shard inside the new image).
4. **Roll out:** local dev stack (rebuild `api` when no agent is using the shared stack; the shared-stack rule says
   never recreate `api-*` while others work), the VPS plan and `ulams stack up` pick the new tag on the next pull. No
   production tenant exists yet, so no customer rollout.
5. **Staging on MyDevil:** when the provider confirms, check with `deploy/mydevil/bin/check-host.sh` (extend it to print
   and compare `PHP_VERSION`, failing below 8.4.27), then lift the Cloudflare block from phase A.2.

### Phase C: so the next one is not a surprise

1. **PHP freshness workflow** (new `.github/workflows/php-freshness.yml`, daily): read the supported branches from
   `https://www.php.net/releases/index.php?json&version=8.4`, run `docker run php:8.4-fpm-alpine php -v` and our latest
   published image, and open a GitHub issue (label `security`) when the official image or ours is behind. Cheap, and
   it would have told us today's date before php.net's front page did.
2. **Dependabot** already has a `docker` ecosystem entry: confirm it covers `api/docker/php` and `api/Dockerfile*`
   (the `FROM php:8.4-fpm-alpine` line), and that it is not ignoring patch versions of `php`.
3. **Docs:** a short "PHP security updates" section in the operators docs (image tags, how to check the running version,
   the interim ini values and why we do not use them by default), and a line in the security policy.

## 5. Tests and verification

- Image build fails on a PHP below 8.4.27 (negative test by building against `--build-arg` of an old tag once).
- `ini_get('session.upload_progress.enabled') === '0'` inside the built image, through FPM (a tiny `phpinfo`-free
  probe script run by the Image smoke job, not exposed over HTTP).
- API PHPUnit shards still green (unchanged code), one shard inside the new image.
- The freshness workflow tested with `workflow_dispatch` against a deliberately old image tag.

## 6. Risks

- The official image may take days; MyDevil may take longer. Mitigation: phase A.2 decision, and the version gate so
  nothing stale ships by accident.
- `pull: true` plus the GHA cache could still reuse a stale base layer: the version gate catches that.
- Interim `memory_limit=-1` removes our own memory ceiling: only as a stop-gap on exposed hosts, with a note to revert.
- We rely on the advisory's wording for what is affected; nobody here has reproduced either bug.

## 7. Decisions for the owner (recommended default first)

- **Q1, staging APIs on MyDevil until the provider patches:** take them offline at Cloudflare **(recommended)**, or leave
  them up, or move staging behind an access policy. Needs your Cloudflare access; I will not touch Cloudflare without
  your go-ahead.
- **Q2, stop-gap ini values (`memory_limit=-1`, `post_max_size` 32M) on exposed hosts:** not applied to our images,
  applied only if we keep a host exposed **(recommended)**.
- **Q3, contact MyDevil support now** with the advisory link and ask for the patched `php84` and an ETA (an owner action:
  it is your account) **(recommended: yes, issue opened)**.
- **Q4, freshness workflow:** add it **(recommended)**.
- **Q5, publish immutable full-version tags (`8.4.27`) next to `8.4`:** yes **(recommended)**.

## 8. Milestones

1. **SEC1 (today, small PR):** upload progress off in all ini and the FPM pool, `pull: true`, the full-version tag and
   the Image smoke probe. No version gate yet: a gate merged today would turn every publish run red (and send a
   failure e-mail) until the official image is patched.
2. **SEC2 (after the official image is 8.4.27):** add the version gate from phase B.1 in the same PR as the first
   rebuild, publish, verify, roll out locally.
3. **SEC3:** `check-host.sh` version check and the staging recovery steps.
4. **SEC4:** freshness workflow, Dependabot check, docs.
