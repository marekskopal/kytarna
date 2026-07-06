FROM node:24.18.0-alpine AS build
WORKDIR /app

RUN npm install -g pnpm@10
ENV PNPM_STRICT_DEP_BUILDS=false

COPY ./package.json ./pnpm-lock.yaml* ./.npmrc ./
RUN pnpm config set strict-dep-builds false && pnpm install --config.strict-dep-builds=false

COPY ./src ./src
COPY ./angular.json ./tsconfig.json ./tsconfig.app.json ./
RUN pnpm run build-prod

FROM nginx:1.30.3-alpine AS runtime
COPY --from=build /app/dist/kytarna/browser /usr/share/nginx/html
COPY ./conf/default.conf /etc/nginx/conf.d/default.conf
