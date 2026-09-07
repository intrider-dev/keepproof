# syntax=docker/dockerfile:1.8

ARG NODE_VERSION=24.18.0

FROM node:${NODE_VERSION}-alpine3.23 AS frontend-build
WORKDIR /build
COPY apps/frontend/package.json apps/frontend/package-lock.json ./
RUN npm ci --ignore-scripts
COPY apps/frontend ./
RUN npm run build

FROM nginx:1.31.5-alpine3.24 AS production
COPY infra/nginx/nginx.conf /etc/nginx/nginx.conf
COPY infra/nginx/production.conf /etc/nginx/conf.d/default.conf
COPY --from=frontend-build /build/dist /var/www/frontend
COPY apps/backend/public /var/www/backend/public
USER nginx
EXPOSE 8080
