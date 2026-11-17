# Local test and demo image. Production is deployed without Docker (see README, "Deploy").
FROM php:8.4-cli-bookworm AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql intl zip bcmath pcntl opcache redis \
    && apt-get update && apt-get install -y --no-install-recommends unzip postgresql-client \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/app/php.ini /usr/local/etc/php/conf.d/app.ini

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --optimize

# The theme is compiled from Filament's own styles, so the build needs vendor/.
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY --from=base /app /app
RUN npm ci && npm run build

FROM base
COPY --from=assets /app/public/build /app/public/build
RUN useradd --create-home app && chown -R app:app /app
USER app
EXPOSE 8000
ENTRYPOINT ["/app/docker/app/entrypoint.sh"]
