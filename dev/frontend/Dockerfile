FROM node:22.13.1-alpine AS build

WORKDIR /app
COPY project/package.json project/yarn.lock ./
RUN yarn install --frozen-lockfile
COPY \
  ./project/.env \
  ./project/index.html \
  ./project/tsconfig.json \
  ./project/tsconfig.app.json \
  ./project/tsconfig.node.json \
  ./project/vite.config.ts \
  ./
COPY ./project/src ./src

RUN yarn build --mode docker_debug

FROM httpd:2.4.58-alpine

COPY ./conf/httpd.conf /usr/local/apache2/conf/httpd.conf
COPY ./project/public/ /usr/local/apache2/htdocs/
COPY --from=build /app/dist/ /usr/local/apache2/htdocs/

EXPOSE 80
