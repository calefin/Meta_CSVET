FROM node:22-bookworm-slim AS builder
WORKDIR /app

RUN npm install -g pnpm@10.4.1

COPY package.json pnpm-lock.yaml ./
COPY patches/ ./patches/
RUN pnpm install --frozen-lockfile

COPY . .
RUN pnpm build

# ──────────────────────────────────────────────
FROM node:22-bookworm-slim AS runner
WORKDIR /app

# Fontes para o sharp/librsvg desenhar texto nos cards de compartilhamento (/api/og/*).
# A imagem slim não traz nenhuma fonte — sem isto o texto sai em branco.
RUN apt-get update && apt-get install -y --no-install-recommends fonts-dejavu-core fontconfig \
    && rm -rf /var/lib/apt/lists/*

RUN npm install -g pnpm@10.4.1

COPY package.json pnpm-lock.yaml ./
COPY patches/ ./patches/
RUN pnpm install --frozen-lockfile --prod

COPY --from=builder /app/dist ./dist
COPY --from=builder /app/drizzle ./drizzle
COPY --from=builder /app/scripts ./scripts
COPY --from=builder /app/data ./data
COPY --from=builder /app/drizzle.config.ts ./drizzle.config.ts

ENV NODE_ENV=production
EXPOSE 3000

CMD ["node", "dist/index.js"]
