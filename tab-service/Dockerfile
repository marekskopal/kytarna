FROM node:24-alpine

ENV NODE_ENV=production
RUN npm install -g pnpm@11

RUN mkdir -p /app && chown node:node /app
WORKDIR /app
COPY --chown=node:node package.json pnpm-lock.yaml ./
USER node
RUN pnpm install --prod --frozen-lockfile
COPY --chown=node:node src ./src

EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD wget -qO- "http://127.0.0.1:${TAB_SERVICE_PORT:-8080}/health" || exit 1

CMD ["node", "src/server.js"]
