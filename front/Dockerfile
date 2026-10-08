# Build from the repository root (one root yarn.lock, Yarn workspaces):
#   docker build -f front/Dockerfile -t foo . && docker run -p 8080:80 -it foo
ARG NODE_VERSION=22.12.0
FROM node:${NODE_VERSION}-bookworm AS base
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
RUN cd front && yarn build && cp dist/index.html dist/tpl.html
RUN echo $APP_VERSION >> front/dist/version.html


FROM php:apache AS httpd
ENV API_URL="http://localhost:1000"
ENV SENTRYDSN=""
ENV YBUG_ID=""
ENV BASENAME=""
ENV ROUTING_TYPE="HashRouter"

COPY front/entrypoint.sh /usr/local/bin/httpd-foreground
ENTRYPOINT /usr/local/bin/httpd-foreground
COPY --from=base /home/node/app/front/dist /var/www/html
COPY front/config/php/index.php /var/www/html/index.php

RUN a2enmod rewrite
