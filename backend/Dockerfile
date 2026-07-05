FROM composer:2.10.1 AS composer
FROM mlocati/php-extension-installer:2.11.12 AS php-extension-installer
# Thread-safe (ZTS) v8js build, matching FrankenPHP's embedded ZTS PHP. Provides
# ext-v8js for the low-code scripting sandbox (U-80); see php-v8js-docker. Pinned
# to a digest for reproducible, tamper-evident builds (tag kept for readability).
FROM marekskopal/php-v8js:8.5-zts-alpine@sha256:94d6b7d98ab6a379769d24f8e97936fa16eb38f596ddbbd21a922cf1840cb3a0 AS v8js
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
# (see docker/cron.d/kytario); nodejs-libs provides the libnode that v8js.so links
# against (V8 is embedded in Node, no separate libv8 in Alpine).
RUN apk add --no-cache curl tzdata supervisor supercronic nodejs-libs

# Unprivileged user for the V8 script sandbox. The worker runs attacker-authored
# (admin-authored) JS in-process, so a V8 memory-safety escape must NOT land as
# root in the same container that holds the DB creds and AUTHORIZATION_TOKEN_KEY.
# It only needs read access to /app (which is root-owned but world-readable) plus
# outbound network to RabbitMQ/DB — no write access to the codebase.
RUN adduser -S -D -H -u 10001 -s /sbin/nologin kytario-script

# Copy the prebuilt ZTS v8js extension. It is loaded only by the script-worker
# process (via `php -d extension=v8js.so` in supervisord.conf), not by FrankenPHP
# or the main AMQP consumer, so the heavyweight V8 runtime stays out of the web
# tier. The extension-dir suffix is the Zend module API stamp; identical across
# all PHP 8.5.x, so the 8.5-zts image's .so loads into this 8.5.6 runtime.
COPY --from=v8js /usr/local/lib/php/extensions/no-debug-zts-20250925/v8js.so \
     /usr/local/lib/php/extensions/no-debug-zts-20250925/v8js.so

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
