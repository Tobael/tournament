FROM node:22-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY resources resources
COPY vite.config.js ./

FROM php:8.4-fpm-alpine AS php-base
RUN apk add --no-cache nginx curl sqlite-dev oniguruma-dev \
    && docker-php-ext-install pdo_sqlite mbstring opcache
WORKDIR /var/www/html

FROM php-base AS dependencies
RUN apk add --no-cache unzip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

FROM assets AS frontend
COPY --from=dependencies /var/www/html/vendor /app/vendor
RUN npm run build

FROM php-base AS production
COPY --from=dependencies --chown=www-data:www-data /var/www/html /var/www/html
COPY --from=frontend /app/public/build /var/www/html/public/build
COPY nginx.conf /etc/nginx/http.d/default.conf
COPY deploy/entrypoint.sh /usr/local/bin/tournament-entrypoint
RUN chmod +x /usr/local/bin/tournament-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache
ENTRYPOINT ["tournament-entrypoint"]
