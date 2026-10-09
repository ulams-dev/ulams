# syntax=docker/dockerfile:1
# api-h5p: H5P microservice (Lumi h5p-nodejs-library) for Ulams.
#
# Build from the REPOSITORY ROOT (one root yarn.lock, Yarn workspaces):
#   docker build -f api/h5p/Dockerfile -t ulams/h5p:dev .
# compose (from api/): build: { context: .., dockerfile: api/h5p/Dockerfile }
#
# The build context is filtered by api/h5p/Dockerfile.dockerignore (allow-list).
# Only the api/h5p workspace is present in the image, so yarn hoists its
# dependencies to /repo/node_modules (a few `nohoist` ones stay in
# api/h5p/node_modules); the runtime stage keeps that layout under /app.

ARG NODE_IMAGE=node:22-alpine
# Commits pinned by Lumi (h5p-nodejs-library scripts/install.sh, v10.0.x)
ARG H5P_CORE_REF=2aeb0b83fa603e331381b3a6b8bf42c3773ba140
ARG H5P_EDITOR_REF=ab2daa18bd61b19e7f8729e22eec88f3b637a868

# ---- manifests: root manifest + lockfile + this workspace's manifest ----------
# Only api/h5p is present as a workspace; yarn installs just its dependency tree
# (plus the tiny root devDependencies) and the frozen lockfile still applies.
FROM ${NODE_IMAGE} AS manifests
ENV HUSKY=0
WORKDIR /repo
COPY package.json yarn.lock ./
COPY api/h5p/package.json api/h5p/package.json

# ---- deps: full install for the build -------------------------------------
FROM manifests AS deps
RUN yarn install --frozen-lockfile --ignore-scripts --non-interactive --network-timeout 600000 \
    && yarn cache clean

# ---- build -----------------------------------------------------------------
FROM deps AS build
COPY api/h5p/tsconfig.json api/h5p/tsconfig.build.json api/h5p/
COPY api/h5p/scripts/build-embed.mjs api/h5p/scripts/build-embed.mjs
COPY api/h5p/embed-client api/h5p/embed-client
COPY api/h5p/src api/h5p/src
# tsc (server) + esbuild (embed pages: Lumi web components, bundled here only)
RUN yarn --cwd api/h5p build

# ---- prod-deps: runtime node_modules only -----------------------------------
FROM manifests AS prod-deps
RUN yarn install --frozen-lockfile --production --ignore-scripts --non-interactive --network-timeout 600000 \
    && yarn cache clean \
    && mkdir -p api/h5p/node_modules

# ---- core: H5P core + editor client files at pinned commits -----------------
FROM alpine:3.24 AS core
ARG H5P_CORE_REF
ARG H5P_EDITOR_REF
RUN apk add --no-cache curl unzip
COPY api/h5p/scripts/download-core.sh /download-core.sh
RUN sh /download-core.sh "$H5P_CORE_REF" "$H5P_EDITOR_REF" /out

# ---- runtime -----------------------------------------------------------------
FROM ${NODE_IMAGE} AS runtime
ARG H5P_CORE_REF
ARG H5P_EDITOR_REF
# GPL-3.0-or-later: the image carries the licence text, and its labels name the pinned H5P core
# and editor commits that are part of the corresponding source (see LICENSING.md).
LABEL org.opencontainers.image.licenses="GPL-3.0-or-later" \
      dev.ulams.h5p.core-ref="https://github.com/h5p/h5p-php-library/tree/${H5P_CORE_REF}" \
      dev.ulams.h5p.editor-ref="https://github.com/h5p/h5p-editor-php-library/tree/${H5P_EDITOR_REF}"
ENV NODE_ENV=production \
    PORT=8080 \
    H5P_CORE_PATH=/app/h5p/core \
    H5P_EDITOR_PATH=/app/h5p/editor \
    H5P_LIBRARIES_PATH=/data/libraries \
    H5P_TMP_PATH=/tmp/h5p \
    JWT_PUBLIC_KEY_PATH=/keys/oauth-public.key
WORKDIR /app/api/h5p
COPY --from=prod-deps --chown=node:node /repo/node_modules /app/node_modules
COPY --from=prod-deps --chown=node:node /repo/api/h5p/node_modules ./node_modules
COPY --from=build --chown=node:node /repo/api/h5p/dist ./dist
COPY --from=core --chown=node:node /out/core /app/h5p/core
COPY --from=core --chown=node:node /out/editor /app/h5p/editor
COPY --chown=node:node api/h5p/package.json api/h5p/LICENSE ./
RUN mkdir -p /data/libraries /tmp/h5p && chown -R node:node /data /tmp/h5p
USER node
VOLUME ["/data/libraries"]
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD node -e "fetch('http://127.0.0.1:'+(process.env.PORT||8080)+'/h5p/health').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"
CMD ["node", "dist/index.js"]
