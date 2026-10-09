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
RUN yarn install --frozen-lockfile --ignore-scripts --network-timeout 1000000000
COPY front/ front/
RUN cd front && yarn build && find dist -name '*.map' -delete
RUN echo $APP_VERSION >> front/dist/version.html


# Non-root static server (uid 101, port 8080). The tag is pinned by digest; bump both together.
FROM nginxinc/nginx-unprivileged:1.29-alpine@sha256:0c79d56aee561a1d81c63f00eee5fb5fe29279560cdc55e91425133104c7fbe6 AS httpd
# Settings are read at start from the environment (names starting with VITE_APP_) and written to
# /runtime-config.json, which index.html loads before the app boots (ADR 0056). Examples:
#   VITE_APP_API_URL, VITE_APP_TENANT_API_HOST_PATTERN, VITE_APP_SENTRYDSN, VITE_APP_FIREBASE_*
USER root
COPY front/nginx.conf /etc/nginx/conf.d/default.conf
COPY front/entrypoint.sh /docker-entrypoint.d/40-runtime-config.sh
COPY --from=base /home/node/app/front/dist /usr/share/nginx/html
RUN chmod 755 /docker-entrypoint.d/40-runtime-config.sh \
    && touch /usr/share/nginx/html/runtime-config.json \
    && chown 101:101 /usr/share/nginx/html/runtime-config.json
USER 101
EXPOSE 8080
