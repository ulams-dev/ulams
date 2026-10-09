# adapt-builder

Build worker for Adapt Learning Path B ([ADR 0013](../../docs/decisions/0013-adapt-build-worker.md)):
takes an Adapt course as JSON and returns a SCORM 1.2 package built by the Adapt framework with
`adapt-contrib-spoor`. The API (`api/packages/adapt`, MIT) stores and validates the sources and
imports the zip through the normal SCORM upload (Path A).

**Licence: GPL-3.0-or-later** (`LICENSE`). The image bundles `adapt_framework` and its core
plugins (GPL-3.0). Like `api/h5p` it is a separate program reached over HTTP; no ulams code links
to it (LICENSING rules 1–3).

Off by default: the API needs `ADAPT_SOURCE_ENABLED=true`, and the compose service is in the
profile `adapt`.

## API

| Method & path | Answer |
| --- | --- |
| `GET /health` | `{ok, busy, queued}` |
| `POST /build` | header `X-Internal-Token`, body `{"id": "<source>-v<version>", "source": {course, config, contentObjects, articles, blocks, components}}` → `200 application/zip`, or `{"error": "…"}` with 400 (bad body), 401 (token), 413 (body over `ADAPT_MAX_SOURCE_KB`), 415, 422 (the framework build failed; the message carries the build's error lines), 503 (queue full), 504 (timeout) |

One build runs at a time; up to `ADAPT_MAX_QUEUED` requests wait. Each build:

1. copies the framework (without `node_modules`, linked read-only) into a fresh directory under
   `ADAPT_WORK_DIR`;
2. writes the course to `src/course` (`config.json`, `<language>/*.json`), fills the `_type`s the
   framework needs and forces `_spoor._isEnabled`;
3. runs `grunt build` with a minimal environment and kills the process group after
   `ADAPT_BUILD_TIMEOUT_MS`; the framework's `check-json`, `check-plugins` and `schema-defaults`
   tasks validate the JSON against the installed plugins;
4. zips `build/` (must contain `imsmanifest.xml`) and deletes the directory. Nothing is kept.

The container has no route to the internet in compose (internal network `adapt_build`), a read-only
root filesystem with `/tmp` as tmpfs, and CPU, memory and PID limits.

## Configuration

| Variable | Default | Notes |
| --- | --- | --- |
| `ADAPT_BUILDER_TOKEN` | – | required; the API sends it as `X-Internal-Token` (`ADAPT_BUILDER_TOKEN` there too) |
| `PORT` | `8080` | |
| `ADAPT_FRAMEWORK_DIR` | `/opt/adapt/framework` | installed framework with plugins and `node_modules` |
| `ADAPT_WORK_DIR` | OS temp dir | build workspaces |
| `ADAPT_BUILD_TIMEOUT_MS` | `240000` | per build |
| `ADAPT_MAX_SOURCE_KB` | `8192` | request body limit |
| `ADAPT_MAX_OUTPUT_MB` | `512` | build output limit |
| `ADAPT_MAX_QUEUED` | `4` | waiting builds before 503 |

## Image

```
docker build -t ulams/adapt-builder:dev api/adapt-builder
# or, from api/: docker compose --profile adapt up -d adapt-builder
```

Build arguments: `ADAPT_FRAMEWORK_TAG` (default `v5.56.2`), `ADAPT_CLI_VERSION` (default `3.4.0`).
`scripts/install-framework.sh` clones the framework at the tag, installs its runtime dependencies
and the plugins of its `adapt.json` (the latest versions compatible with that framework) and
records the installed versions in `/opt/adapt/framework/ulams-adapt-versions.json`. The image is
about 600 MB; a build of a small course takes 20–60 s of one CPU.

## Licence and source offer

The image contains the complete corresponding source: this directory (`/app`) and the framework
and plugin sources as installed (`/opt/adapt/framework`, uncompiled, with the versions in
`ulams-adapt-versions.json` and the image labels). Anyone who receives the image or a hosted
build service can get the same source from the ulams repository (`api/adapt-builder`) and the
upstream repositories at the recorded versions.

## Development

No npm dependencies (Node 22.2+ built-ins only); it is not a Yarn workspace.

```
node --test api/adapt-builder/test/
```

The tests use a fake framework (a stand-in `grunt` that writes a build); the real build runs in
the nightly conformance workflow (`.github/workflows/nightly-conformance.yml`).
