# Build from the repository root (one root yarn.lock; admin also compiles front/src/lib):
#   docker build -f admin/Dockerfile -t abc . && docker run -p 8080:8080 --env REACT_APP_TENANT_API_HOST_PATTERN="{slug}.admin.ulams.app=>https://{slug}.api.ulams.app" abc
ARG NODE_VERSION=22.12.0
# The static build is the same for every platform: run it natively, only the server stage is per-arch.
FROM --platform=$BUILDPLATFORM node:${NODE_VERSION}-bookworm AS base
ENV HUSKY=0
WORKDIR /home/node/app
# workspace manifests first (all of them, so the frozen root lockfile matches) for layer caching
COPY package.json yarn.lock ./
COPY api/package.json api/package.json
COPY admin/package.json admin/package.json
COPY front/package.json front/package.json
# scripts skipped: `max setup` runs as part of `max build`, husky needs .git
RUN yarn install --frozen-lockfile --ignore-scripts --network-timeout 1000000000
COPY front/src/lib/ front/src/lib/
COPY admin/ admin/
RUN cd admin && yarn build && find dist -name '*.map' -delete

# Non-root static server (uid 101, port 8080). The tag is pinned by digest; bump both together.
FROM nginxinc/nginx-unprivileged:1.29-alpine@sha256:0c79d56aee561a1d81c63f00eee5fb5fe29279560cdc55e91425133104c7fbe6 AS httpd
# Settings are read at start from the environment (names starting with REACT_APP_) and written to
# /runtime-config.json, which the app loads before it boots (ADR 0056). Examples:
#   REACT_APP_API_URL            fixed API URL (single tenant); empty: derived from the host
#   REACT_APP_TENANT_API_HOST_PATTERN, REACT_APP_STUDIO_URL, REACT_APP_SENTRYDSN, REACT_APP_YBUG
ARG ADMIN_VERSION="dev-main"
USER root
COPY admin/nginx.conf /etc/nginx/conf.d/default.conf
COPY admin/entrypoint.sh /docker-entrypoint.d/40-runtime-config.sh
COPY --from=base /home/node/app/admin/dist /usr/share/nginx/html
RUN echo "${ADMIN_VERSION}" > /usr/share/nginx/html/version \
    && chmod 755 /docker-entrypoint.d/40-runtime-config.sh \
    && touch /usr/share/nginx/html/runtime-config.json \
    && chown 101:101 /usr/share/nginx/html/runtime-config.json
USER 101
EXPOSE 8080
