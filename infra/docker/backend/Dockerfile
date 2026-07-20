# syntax=docker/dockerfile:1.8

ARG PHP_VERSION=8.5.8

FROM php:${PHP_VERSION}-fpm-trixie AS php-base

ARG REDIS_EXTENSION_VERSION=6.3.0

RUN apt-get update \
    && apt-get install --no-install-recommends --yes \
        curl \
        libfreetype6 \
        libfreetype6-dev \
        libicu-dev \
        libicu76 \
        libjpeg62-turbo \
        libjpeg62-turbo-dev \
        libpng16-16t64 \
        libpng-dev \
        libpq-dev \
        libpq5 \
        libzip-dev \
        libzip5 \
        poppler-utils \
        procps \
        qpdf \
        tesseract-ocr \
        tesseract-ocr-eng \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath gd intl pcntl pdo_pgsql zip \
    && pecl install "redis-${REDIS_EXTENSION_VERSION}" \
    && docker-php-ext-enable redis \
    && apt-get purge --auto-remove --yes \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libpq-dev \
        libzip-dev \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2.10.2 /usr/bin/composer /usr/local/bin/composer
COPY infra/docker/backend/php.ini /usr/local/etc/php/conf.d/99-keepproof.ini

RUN groupadd --gid 1000 app \
    && useradd --uid 1000 --gid app --create-home --shell /bin/bash app

FROM php-base AS development-dependencies

WORKDIR /workspace/apps/backend
COPY apps/backend/composer.json apps/backend/composer.lock ./
COPY packages/deadline-engine /workspace/packages/deadline-engine
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts

FROM php-base AS development

WORKDIR /workspace/apps/backend
COPY --from=development-dependencies --chown=app:app /workspace/apps/backend/vendor ./vendor
COPY --chown=app:app apps/backend ./
COPY --chown=app:app packages /workspace/packages
RUN mkdir -p /workspace/packages/deadline-engine/vendor \
    && chown -R app:app /workspace/packages/deadline-engine \
    && composer dump-autoload --optimize --no-interaction
USER app

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

FROM php-base AS production-dependencies

WORKDIR /build/apps/backend
COPY apps/backend/composer.json apps/backend/composer.lock ./
COPY packages/deadline-engine /build/packages/deadline-engine
RUN composer install \
    --classmap-authoritative \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts

FROM php-base AS production

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

WORKDIR /var/www/backend
COPY --from=production-dependencies --chown=app:app /build/apps/backend/vendor ./vendor
COPY --chown=app:app apps/backend ./
COPY --chown=app:app packages /var/www/packages
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R app:app storage bootstrap/cache

USER app
EXPOSE 9000
CMD ["php-fpm", "--nodaemonize"]
