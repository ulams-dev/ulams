# Conformance runs (LTI with Moodle and saLTIre, Adapt builds)

Notes for the docs site (contributors). Decision: ADR 0019.

`.github/workflows/nightly-conformance.yml` tests ulams against real software. It never blocks a
merge.

- **Nightly**: set the repository variable `NIGHTLY_CONFORMANCE=true`.
- **By hand**: *Actions → Nightly conformance → Run workflow*, choose the suites.

| Suite | What it proves | Time |
|---|---|---|
| Adapt | The `adapt-builder` image builds a fixture course; the API imports the zip as an Adapt SCORM package | ~10 min (image build) |
| Moodle | Moodle 5.0 launches a ulams course and receives the grade; ulams launches a course published by Moodle and receives Moodle's grade (Chromium, real HTTP both ways) | ~15 min |
| saLTIre | saLTIre's hosted test platform launches ulams; needs a person at the browser (below) | until launched, max 30 min |

## Running the Moodle round trips locally

```
docker compose -f .github/conformance/compose.yml --profile lti up -d   # first start installs Moodle
# the API must serve http://ulams.test:18000 (see the workflow's environment), then:
NODE_PATH=<dir with playwright> sh .github/conformance/lti/run-moodle.sh
```

`ULAMS_EXEC="docker exec <container>"` runs the ulams fixture commands in a container instead of on
the host. Moodle admin: `admin` / `Admin-1234!`, student `student` / `Student-1234!` (fixtures only).

## saLTIre

1. On https://saltire.lti.app open the Platform page and note the issuer, client ID, deployment ID
   and the authentication, access token and keyset URLs.
2. Run the workflow with *saltire* on and those values as JSON in *saltire_platform*.
3. The run summary shows the tool URLs (a temporary `trycloudflare.com` address): enter them in
   saLTIre with the custom parameter shown, and launch. The job passes on the first successful
   launch and fails on a rejected one.
