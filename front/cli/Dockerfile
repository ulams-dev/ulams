# The ulams CLI as an image (plan 8.1): `docker run --rm -v "$PWD:/work" ghcr.io/ulams-dev/ulams-cli course validate`.
#
# The bundle is built outside, so the image needs no monorepo install:
#   corepack yarn workspace ulams build
#   docker build -f front/cli/Dockerfile -t ulams-cli front/cli
# `git` is installed because course commands run inside a repository (the commit of a push, `course sources-connect`).
FROM node:22-alpine

RUN apk add --no-cache git \
 && adduser -D -u 10001 ulams \
 && mkdir /work && chown ulams /work

WORKDIR /opt/ulams
# production dependencies only (the dev dependencies are skipped; the SDK is bundled into dist)
COPY package.json LICENSE ./
# devDependencies are removed first: npm would try to resolve the private @ulams/sdk
RUN node -e "const f='package.json',p=JSON.parse(require('fs').readFileSync(f));delete p.devDependencies;require('fs').writeFileSync(f,JSON.stringify(p))" \
 && npm install --omit=dev --ignore-scripts --no-audit --no-fund --no-package-lock \
 && npm cache clean --force
COPY dist ./dist

ARG VERSION=dev
ARG REVISION=unknown
LABEL org.opencontainers.image.title="ulams-cli" \
      org.opencontainers.image.description="Command line and MCP server for ulams instances, and the runtime of the ulams course GitHub Action" \
      org.opencontainers.image.source="https://github.com/ulams-dev/ulams" \
      org.opencontainers.image.licenses="MIT" \
      org.opencontainers.image.version="${VERSION}" \
      org.opencontainers.image.revision="${REVISION}"

USER ulams
WORKDIR /work
ENV ULAMS_OUTPUT_COLOR=0
ENTRYPOINT ["node", "/opt/ulams/dist/ulams.mjs"]
CMD ["--help"]
