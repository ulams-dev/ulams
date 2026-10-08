# syntax=docker/dockerfile:1
# api-h5p: H5P microservice (Lumi h5p-nodejs-library) for Ulams.

ARG NODE_IMAGE=node:22-alpine
# Commits pinned by Lumi (h5p-nodejs-library scripts/install.sh, v10.0.x)
ARG H5P_CORE_REF=2aeb0b83fa603e331381b3a6b8bf42c3773ba140
ARG H5P_EDITOR_REF=ab2daa18bd61b19e7f8729e22eec88f3b637a868

# ---- deps: full install for the build -------------------------------------
FROM ${NODE_IMAGE} AS deps
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

# ---- build -----------------------------------------------------------------
FROM deps AS build
COPY tsconfig.json tsconfig.build.json ./
COPY src ./src
RUN npm run build

# ---- prod-deps: runtime node_modules only -----------------------------------
FROM ${NODE_IMAGE} AS prod-deps
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --omit=dev --no-audit --no-fund && npm cache clean --force

# ---- core: H5P core + editor client files at pinned commits -----------------
FROM alpine:3.22 AS core
ARG H5P_CORE_REF
ARG H5P_EDITOR_REF
RUN apk add --no-cache curl unzip
COPY scripts/download-core.sh /download-core.sh
RUN sh /download-core.sh "$H5P_CORE_REF" "$H5P_EDITOR_REF" /out

# ---- runtime -----------------------------------------------------------------
FROM ${NODE_IMAGE} AS runtime
ENV NODE_ENV=production \
    PORT=8080 \
    H5P_CORE_PATH=/app/h5p/core \
    H5P_EDITOR_PATH=/app/h5p/editor \
    H5P_LIBRARIES_PATH=/data/libraries \
    H5P_TMP_PATH=/tmp/h5p \
    JWT_PUBLIC_KEY_PATH=/keys/oauth-public.key
WORKDIR /app
COPY --from=prod-deps --chown=node:node /app/node_modules ./node_modules
COPY --from=build --chown=node:node /app/dist ./dist
COPY --from=core --chown=node:node /out/core ./h5p/core
COPY --from=core --chown=node:node /out/editor ./h5p/editor
COPY --chown=node:node package.json ./
RUN mkdir -p /data/libraries /tmp/h5p && chown -R node:node /data /tmp/h5p
USER node
VOLUME ["/data/libraries"]
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD node -e "fetch('http://127.0.0.1:'+(process.env.PORT||8080)+'/h5p/health').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"
CMD ["node", "dist/index.js"]
