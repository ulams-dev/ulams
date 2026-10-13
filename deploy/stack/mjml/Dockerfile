FROM node:22-alpine
WORKDIR /app
COPY deploy/stack/mjml/package.json deploy/stack/mjml/package-lock.json ./
RUN npm ci --omit=dev --ignore-scripts && npm cache clean --force
COPY deploy/stack/mjml/server.mjs ./
USER node
EXPOSE 15500
HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=5 CMD node -e "fetch('http://127.0.0.1:15500/healthz').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"
CMD ["node", "server.mjs"]
