# Build from the repository root (one root yarn.lock; admin also compiles front/src/lib):
#   docker build -f admin/Dockerfile -t abc . && docker run -p 80:80 --env API_URL="https://api-stage.ulams.app" abc
ARG NODE_VERSION=22.12.0
FROM node:${NODE_VERSION}-bookworm AS base
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
RUN cd admin && yarn build && cp dist/index.html dist/tpl.html

FROM php:apache AS httpd
ENV API_URL="http://localhost:1000"
ENV SENTRYDSN=""
ENV YBUG=""
ARG ADMIN_VERSION="dev-main"
COPY admin/entrypoint.sh /usr/local/bin/docker-php-entrypoint
COPY --from=base /home/node/app/admin/dist /var/www/html
COPY admin/config/php/index.php /var/www/html/index.php
RUN echo "${ADMIN_VERSION}" > /var/www/html/version
# Enable mod_rewrite and AllowOverride All
RUN a2enmod rewrite \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
