FROM node:22-alpine AS assets

WORKDIR /src

COPY web/package*.json ./
RUN npm ci

COPY web/index.html web/vite.config.js ./
COPY web/resources ./resources
RUN npm run build

FROM nginx:1.27-alpine

COPY --from=assets /src/dist /var/www/html/dist
COPY php/public /var/www/html/php/public
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
