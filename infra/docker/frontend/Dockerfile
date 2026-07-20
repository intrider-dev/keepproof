# syntax=docker/dockerfile:1.8

ARG NODE_VERSION=24.18.0

FROM node:${NODE_VERSION}-alpine3.23 AS dependencies

WORKDIR /workspace/apps/frontend
COPY apps/frontend/package.json apps/frontend/package-lock.json ./
RUN npm ci --ignore-scripts

FROM dependencies AS development

COPY --chown=node:node apps/frontend ./
USER node
EXPOSE 5173
CMD ["npm", "run", "dev", "--", "--host=0.0.0.0", "--port=5173"]

FROM dependencies AS build

COPY apps/frontend ./
RUN npm run build
