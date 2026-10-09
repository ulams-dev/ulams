# syntax=docker/dockerfile:1
# adapt-builder: builds Adapt course JSON into a SCORM 1.2 package (ADR 0013). GPL-3.0-or-later.
#
#   docker build -t ulams/adapt-builder:dev api/adapt-builder
#
# The Adapt framework, its core plugins and their sources are in the image under
# /opt/adapt/framework (corresponding source, see README "Licence and source offer"); the
# installed versions are listed in /opt/adapt/framework/ulams-adapt-versions.json and the labels.

ARG NODE_IMAGE=node:22-alpine
# adapt_framework release (2026-04-13) and adapt-cli used to install its plugins
ARG ADAPT_FRAMEWORK_TAG=v5.56.2
ARG ADAPT_CLI_VERSION=3.4.0

# ---- framework: clone, build dependencies, plugins (needs network) ----------
FROM ${NODE_IMAGE} AS framework
ARG ADAPT_FRAMEWORK_TAG
ARG ADAPT_CLI_VERSION
RUN apk add --no-cache git
COPY scripts/install-framework.sh /install-framework.sh
RUN sh /install-framework.sh /opt/adapt/framework "$ADAPT_FRAMEWORK_TAG" "$ADAPT_CLI_VERSION" \
    && npm cache clean --force

# ---- runtime -----------------------------------------------------------------
FROM ${NODE_IMAGE} AS runtime
ARG ADAPT_FRAMEWORK_TAG
ARG ADAPT_CLI_VERSION
LABEL org.opencontainers.image.title="ulams adapt-builder" \
      org.opencontainers.image.licenses="GPL-3.0-or-later" \
      org.opencontainers.image.source="https://github.com/ulams-dev/ulams/tree/main/api/adapt-builder" \
      dev.ulams.adapt-framework="https://github.com/adaptlearning/adapt_framework/tree/${ADAPT_FRAMEWORK_TAG}" \
      dev.ulams.adapt-cli="adapt-cli@${ADAPT_CLI_VERSION}"
ENV NODE_ENV=production \
    PORT=8080 \
    ADAPT_FRAMEWORK_DIR=/opt/adapt/framework \
    ADAPT_WORK_DIR=/tmp
WORKDIR /app
COPY --from=framework /opt/adapt/framework /opt/adapt/framework
COPY package.json LICENSE README.md ./
COPY src ./src
USER node
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD node -e "fetch('http://127.0.0.1:'+(process.env.PORT||8080)+'/health').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"
CMD ["node", "src/index.mjs"]
