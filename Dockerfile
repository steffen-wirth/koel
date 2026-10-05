# Builds Koel entirely from THIS source tree (no koel release tarball, no koel image).

# --- PHP dependencies (production only)
FROM composer:2 AS vendor
WORKDIR /app
COPY . .
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-scripts --no-interaction --ignore-platform-reqs

# --- Front-end assets
FROM node:22-bookworm AS assets
WORKDIR /app
RUN corepack enable
COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile --ignore-scripts
COPY . .
RUN pnpm run build

# --- Runtime (php + apache + ffmpeg, same layout as the official image)
FROM php:8.4-apache
WORKDIR /var/www/html

RUN apt-get update \
  && apt-get install --yes --no-install-recommends \
    cron libapache2-mod-xsendfile libzip-dev zip ffmpeg flac locales curl \
    libpng-dev libjpeg62-turbo-dev libpq-dev libwebp-dev libavif-dev libicu-dev nano \
  && docker-php-ext-configure gd --with-jpeg --with-webp --with-avif \
  && docker-php-ext-install bcmath exif gd intl pdo pdo_mysql pdo_pgsql pgsql zip \
  && a2enmod rewrite \
  && apt-get clean && rm -rf /var/lib/apt/lists/* \
  && echo "en_US.UTF-8 UTF-8" > /etc/locale.gen && /usr/sbin/locale-gen \
  && mkdir /music && chown www-data:www-data /music

# BPM and key detection (scripts/analyze-audio.py). Kept outside storage/ so volumes don't hide it.
RUN apt-get update \
  && apt-get install --yes --no-install-recommends python3 python3-venv \
  && python3 -m venv /opt/analysis-venv \
  && /opt/analysis-venv/bin/pip install --no-cache-dir essentia \
  && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/koel.ini /usr/local/etc/php/conf.d/koel.ini
COPY docker/koel-entrypoint docker/koel-init /usr/local/bin/

COPY --from=vendor --chown=www-data:www-data /app/ /var/www/html/
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build

# koel-init exits when there is no .env; real environment variables still take precedence over it
RUN cp .env.example .env \
  && cp public/.htaccess.example public/.htaccess \
  && ln -sfn ../storage/app/public public/storage \
  && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    storage/search-indexes storage/app/public/images \
  && chown -R www-data:www-data /var/www/html

RUN groupadd -g 1000 media && usermod -aG media www-data
USER www-data
RUN php artisan package:discover --ansi \
  && php artisan route:cache \
  && php artisan event:cache \
  && php artisan view:cache
USER root

ENV FFMPEG_PATH=/usr/bin/ffmpeg \
    AUDIO_ANALYSIS_PYTHON=/opt/analysis-venv/bin/python \
    MEDIA_PATH=/music \
    STREAMING_METHOD=x-sendfile \
    LANG=en_US.UTF-8 \
    LANGUAGE=en_US:en \
    LC_ALL=en_US.UTF-8

VOLUME ["/music", "/var/www/html/storage/app/public/images", "/var/www/html/storage/search-indexes"]
EXPOSE 80
HEALTHCHECK --start-period=30s --interval=5m --timeout=5s CMD curl -f http://localhost/sw.js || exit 1
ENTRYPOINT ["koel-entrypoint"]
CMD ["apache2-foreground"]
