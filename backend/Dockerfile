FROM composer:2.10.1 AS composer
FROM mlocati/php-extension-installer:2.11.12 AS php-extension-installer
FROM dunglas/frankenphp:1.12.4-php8.5.7-alpine AS php

COPY --from=php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
    curl \
    pdo_mysql \
    opcache \
    mbstring \
    pcov \
    sockets \
    zip \
    igbinary \
    memcached

# curl/tzdata/supervisor for the runtime; supercronic runs the in-container cron
# (see docker/cron.d/kytarna).
RUN apk add --no-cache curl tzdata supervisor supercronic

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

COPY ./bin /app/bin
COPY ./migrations /app/migrations
COPY ./src /app/src
COPY ./translations /app/translations
COPY ./Caddyfile /etc/frankenphp/Caddyfile
COPY ./docker/php-uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY ./docker/supervisord.conf /etc/supervisord.conf
COPY ./docker/cron.d /etc/cron.d
RUN chmod 0600 /etc/cron.d/*
COPY ./composer.json ./composer.lock* /app/

WORKDIR /app/

RUN rm -rf /app/vendor \
    && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-progress --classmap-authoritative -d /app/ \
    && composer clear-cache

ENTRYPOINT ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
