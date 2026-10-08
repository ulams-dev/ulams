# syntax=docker/dockerfile:1
# api-pdf: PDF renderer (pdfme) for Ulams certificates and PDF templates.
#
# Build from the REPOSITORY ROOT (one root yarn.lock, Yarn workspaces):
#   docker build -f api/pdf/Dockerfile -t ulams/pdf:dev .
# compose (from api/): build: { context: .., dockerfile: api/pdf/Dockerfile }
#
# The build context is filtered by api/pdf/Dockerfile.dockerignore (allow-list).
# Only the api/pdf workspace is present in the image, so yarn installs just its
# dependency tree and the frozen root lockfile still applies.

ARG NODE_IMAGE=node:22-alpine

# ---- manifests: root manifest + lockfile + this workspace's manifest ----------
FROM ${NODE_IMAGE} AS manifests
ENV HUSKY=0
WORKDIR /repo
COPY package.json yarn.lock ./
COPY api/pdf/package.json api/pdf/package.json

# ---- deps: full install for the build -------------------------------------
FROM manifests AS deps
RUN yarn install --frozen-lockfile --ignore-scripts --non-interactive --network-timeout 600000 \
    && yarn cache clean

# ---- build -----------------------------------------------------------------
FROM deps AS build
COPY api/pdf/tsconfig.json api/pdf/tsconfig.build.json api/pdf/
COPY api/pdf/src api/pdf/src
RUN yarn --cwd api/pdf build

# ---- prod-deps: runtime node_modules only -----------------------------------
FROM manifests AS prod-deps
RUN yarn install --frozen-lockfile --production --ignore-scripts --non-interactive --network-timeout 600000 \
    && yarn cache clean \
    && mkdir -p api/pdf/node_modules

# ---- runtime -----------------------------------------------------------------
FROM ${NODE_IMAGE} AS runtime
ENV NODE_ENV=production \
    PORT=3000 \
    PDF_FONTS_DIR=/app/api/pdf/fonts
WORKDIR /app/api/pdf
COPY --from=prod-deps --chown=node:node /repo/node_modules /app/node_modules
COPY --from=prod-deps --chown=node:node /repo/api/pdf/node_modules ./node_modules
COPY --from=build --chown=node:node /repo/api/pdf/dist ./dist
COPY --chown=node:node api/pdf/fonts ./fonts
COPY --chown=node:node api/pdf/package.json api/pdf/LICENSE ./
USER node
EXPOSE 3000
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD node -e "fetch('http://127.0.0.1:'+(process.env.PORT||3000)+'/health').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"
CMD ["node", "dist/index.js"]
