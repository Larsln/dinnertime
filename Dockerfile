FROM dunglas/frankenphp:1-php8.5

RUN install-php-extensions pdo_pgsql pcntl intl zip opcache

RUN apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg python3 \
    && rm -rf /var/lib/apt/lists/*

ADD --chmod=755 https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp /usr/local/bin/yt-dlp

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
