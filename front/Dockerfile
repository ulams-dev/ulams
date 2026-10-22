# Build from the repository root (one root yarn.lock, Yarn workspaces):
#   docker build -f front/Dockerfile -t foo . && docker run -p 8080:8080 -it foo
ARG NODE_VERSION=22.12.0
# The static build is the same for every platform: run it natively, only the server stage is per-arch.
FROM --platform=$BUILDPLATFORM node:${NODE_VERSION}-bookworm AS base
ARG APP_VERSION="xxx"
ENV HUSKY=0
WORKDIR /home/node/app
# workspace manifests first (all of them, so the frozen root lockfile matches) for layer caching
COPY package.json yarn.lock ./
COPY api/package.json api/package.json
COPY admin/package.json admin/package.json
COPY front/package.json front/package.json
# scripts skipped: admin's `max setup` postinstall needs admin sources, husky needs .git
RUN n=1; until yarn install --frozen-lockfile --ignore-scripts --network-timeout 1000000000; do [ "$n" -ge 3 ] && exit 1; n=$((n+1)); echo "retrying yarn install ($n/3)" >&2; sleep 5; done
COPY front/ front/
RUN cd front && n=1; until yarn build; do [ "$n" -ge 3 ] && exit 1; n=$((n+1)); echo "retrying ($n/3): yarn build" >&2; sleep 5; done && find dist -name '*.map' -delete
RUN echo $APP_VERSION >> front/dist/version.html


# Non-root static server (uid 101, port 8080). The tag is pinned by digest (Dependabot's docker ecosystem bumps tag and
# digest together, weekly); bump both together by hand otherwise. The upstream image lags Alpine security fixes, so the
# stage also runs `apk upgrade` to pick up patched packages (curl, openssl, expat, ...) at every build.
FROM nginxinc/nginx-unprivileged:1.31-alpine@sha256:b5047b9b76845eac124358373c6852377c52e4b05834a47647e955dd91cb8f1e AS httpd
# Settings are read at start from the environment (names starting with VITE_APP_) and written to
# /runtime-config.json, which index.html loads before the app boots (ADR 0056). Examples:
#   VITE_APP_API_URL, VITE_APP_TENANT_API_HOST_PATTERN, VITE_APP_SENTRYDSN, VITE_APP_FIREBASE_*,
#   VITE_APP_ULAMS_DOCS_URL (ULAMS_DOCS_URL): base URL of the documentation
USER root
RUN apk upgrade --no-cache
COPY front/nginx.conf /etc/nginx/conf.d/default.conf
COPY front/entrypoint.sh /docker-entrypoint.d/40-runtime-config.sh
COPY --from=base /home/node/app/front/dist /usr/share/nginx/html
RUN chmod 755 /docker-entrypoint.d/40-runtime-config.sh \
    && touch /usr/share/nginx/html/runtime-config.json \
    && chown 101:101 /usr/share/nginx/html/runtime-config.json
USER 101
EXPOSE 8080
