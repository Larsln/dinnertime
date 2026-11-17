FROM dunglas/frankenphp:1-php8.5 AS base

RUN install-php-extensions pdo_pgsql pcntl intl zip opcache

RUN apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg python3 \
    && rm -rf /var/lib/apt/lists/*

ADD --chmod=755 https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp /usr/local/bin/yt-dlp

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app


FROM base AS dev


FROM node:24-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY vite.config.js tsconfig.json ./
COPY resources resources
RUN npm run build


FROM base AS production

ENV APP_ENV=production \
    APP_DEBUG=false \
    SERVER_NAME=:80

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY . .
COPY --from=assets /app/public/build public/build

RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/entrypoint

VOLUME /app/storage/app

ENTRYPOINT ["entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
