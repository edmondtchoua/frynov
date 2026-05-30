# ─────────────────────────────────────────────
#  Frontend Vue 3 — Multi-stage Dockerfile
# ─────────────────────────────────────────────

# ── Stage 1 : Développement ──────────────────
FROM node:20-alpine AS development

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY . .

EXPOSE 5173

CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]


# ── Stage 2 : Build production ───────────────
FROM node:20-alpine AS builder

WORKDIR /app

COPY package*.json ./
RUN npm ci --production=false

COPY . .

ARG VITE_API_URL
ARG VITE_APP_ENV=production
ENV VITE_API_URL=$VITE_API_URL
ENV VITE_APP_ENV=$VITE_APP_ENV

RUN npm run build


# ── Stage 3 : Production (Nginx) ─────────────
FROM nginx:1.25-alpine AS production

# Supprimer la config nginx par défaut
RUN rm /etc/nginx/conf.d/default.conf

# Config nginx pour Vue Router (SPA)
COPY infra/nginx/conf.d/spa.conf /etc/nginx/conf.d/default.conf

# Build Vue
COPY --from=builder /app/dist /usr/share/nginx/html

# Backend API (PHP-FPM)
COPY infra/nginx/conf.d/api.conf /etc/nginx/conf.d/api.conf

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
