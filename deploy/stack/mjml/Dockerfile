FROM node:22-alpine
WORKDIR /app
COPY deploy/stack/mjml/package.json deploy/stack/mjml/package-lock.json ./
RUN n=1; until npm ci --omit=dev --ignore-scripts; do [ "$n" -ge 3 ] && exit 1; n=$((n+1)); echo "retrying ($n/3): npm ci --omit=dev --ignore-scripts" >&2; sleep 5; done && npm cache clean --force
COPY deploy/stack/mjml/server.mjs ./
# The runtime never runs a package manager: drop npm (and yarn) with the dependencies bundled in the base image,
# whose advisories (brace-expansion, picomatch, ...) the base image does not fix yet. Nothing here needs them.
RUN rm -rf /usr/local/lib/node_modules/npm /usr/local/bin/npm /usr/local/bin/npx /opt/yarn-v* /usr/local/bin/yarn /usr/local/bin/yarnpkg
USER node
EXPOSE 15500
HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=5 CMD node -e "fetch('http://127.0.0.1:15500/healthz').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"
CMD ["node", "server.mjs"]
