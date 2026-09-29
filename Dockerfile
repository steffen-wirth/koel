# Builds Koel from THIS source tree (not a release tarball).
# The runtime layer reuses the official image (php + apache + ffmpeg + extensions + entrypoint),
# so its version must be compatible with the checked-out source.
ARG BASE_IMAGE=phanan/koel:9.15.0

# --- PHP dependencies (production only)
FROM composer:2 AS vendor
WORKDIR /app
COPY . .
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-scripts --no-interaction --ignore-platform-reqs

# --- Front-end assets
FROM node:22-slim AS assets
WORKDIR /app
RUN apt-get update && apt-get install -y --no-install-recommends ca-certificates \
  && rm -rf /var/lib/apt/lists/* \
  && corepack enable
COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile --ignore-scripts
COPY . .
RUN pnpm run build

# --- Runtime
FROM ${BASE_IMAGE}
USER root
WORKDIR /var/www/html

# Replace the release code with this source, keeping the volumes' mount points under storage/
RUN find /var/www/html -mindepth 1 -maxdepth 1 ! -name storage -exec rm -rf {} +
COPY --from=vendor --chown=www-data:www-data /app/ /var/www/html/
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build

# koel-init exits when there is no .env; real environment variables still take precedence over it
RUN cp .env.example .env \
  && ln -sfn ../storage/app/public public/storage \
  && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
  && chown -R www-data:www-data /var/www/html

USER www-data
RUN php artisan package:discover --ansi \
  && php artisan route:cache \
  && php artisan event:cache \
  && php artisan view:cache

USER root
