# syntax=docker/dockerfile:1
# web: ulams reference frontend (Astro SSR, @astrojs/node standalone server).
#
# Build from the REPOSITORY ROOT (one root yarn.lock, Yarn workspaces):
#   docker build -f front/web/Dockerfile -t ulams/web:dev .
#   docker run -p 4321:4321 -e ULAMS_TENANT_HOSTS='{slug}.ulams.app=>https://{slug}.api.ulams.app' ulams/web:dev
#
# The build context is filtered by front/web/Dockerfile.dockerignore (allow-list).
# Only front/web and the workspaces it uses (front/sdk, front/ui) are present, so yarn
# installs just their dependency tree and the frozen root lockfile still applies.
# All ULAMS_* settings are read from the environment when the server starts (see
# front/web/.env.example); nothing tenant-specific is baked into the image.

ARG NODE_IMAGE=node:22-alpine

# ---- manifests: root manifest + lockfile + the three workspace manifests ------
FROM ${NODE_IMAGE} AS manifests
ENV HUSKY=0
WORKDIR /repo
COPY package.json yarn.lock ./
COPY front/sdk/package.json front/sdk/package.json
COPY front/ui/package.json front/ui/package.json
COPY front/web/package.json front/web/package.json

# ---- deps: full install for the build -------------------------------------
FROM manifests AS deps
RUN yarn install --frozen-lockfile --ignore-scripts --non-interactive --network-timeout 600000 \
    && yarn cache clean

# ---- build -----------------------------------------------------------------
# astro build downloads the Google fonts it self-hosts (experimental.fonts), so it needs network.
FROM deps AS build
COPY front/src/lib/tenant/resolveApiUrl.ts front/src/lib/tenant/resolveApiUrl.ts
COPY front/sdk front/sdk
COPY front/ui front/ui
COPY front/web front/web
RUN yarn --cwd front/web build

# ---- prod-deps: runtime node_modules only (sharp ships per-platform binaries) --
FROM manifests AS prod-deps
RUN yarn install --frozen-lockfile --production --ignore-scripts --non-interactive --network-timeout 600000 \
    && yarn cache clean \
    && mkdir -p front/web/node_modules

# ---- runtime -----------------------------------------------------------------
FROM ${NODE_IMAGE} AS runtime
ENV NODE_ENV=production \
    HOST=0.0.0.0 \
    PORT=4321
WORKDIR /app/front/web
COPY --from=prod-deps --chown=node:node /repo/node_modules /app/node_modules
COPY --from=prod-deps --chown=node:node /repo/front/web/node_modules ./node_modules
# node_modules/@ulams/{sdk,ui} are symlinks to these workspace folders
COPY --from=build --chown=node:node /repo/front/sdk /app/front/sdk
COPY --from=build --chown=node:node /repo/front/ui /app/front/ui
COPY --from=build --chown=node:node /repo/front/web/dist ./dist
COPY --chown=node:node front/web/package.json ./
USER node
EXPOSE 4321
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD node -e "fetch('http://127.0.0.1:'+(process.env.PORT||4321)+'/healthz').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"
CMD ["node", "dist/server/entry.mjs"]
