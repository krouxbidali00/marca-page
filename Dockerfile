#syntax=docker/dockerfile:1

FROM dunglas/frankenphp:1-php8.4 AS frankenphp_upstream

# --- base stage ------------------------------------------------------------
FROM frankenphp_upstream AS frankenphp_base

WORKDIR /app

# persistent / runtime deps
RUN apt-get update && apt-get install -y --no-install-recommends \
    acl file gettext git curl \
    && rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    install-php-extensions \
      @composer \
      apcu \
      intl \
      opcache \
      pdo_pgsql \
      zip

# Node.js (for asset tooling)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --link frankenphp/conf.d/10-app.ini $PHP_INI_DIR/conf.d/
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link frankenphp/Caddyfile /etc/frankenphp/Caddyfile

ENTRYPOINT ["docker-entrypoint"]

HEALTHCHECK --start-period=60s CMD curl -f http://localhost:2019/metrics || exit 1
CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile" ]

# --- dev stage -------------------------------------------------------------
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev XDEBUG_MODE=off
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY --link frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/conf.d/
RUN set -eux; install-php-extensions xdebug
CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--watch" ]

# --- prod stage ------------------------------------------------------------
FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod COMPOSER_ALLOW_SUPERUSER=1
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link composer.* symfony.* ./
RUN set -eux; composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress
COPY --link . ./
RUN rm -Rf frankenphp/
RUN set -eux; \
    mkdir -p var/cache var/log; \
    composer dump-autoload --classmap-authoritative --no-dev; \
    composer dump-env prod; \
    composer run-script --no-dev post-install-cmd; \
    chmod +x bin/console; \
    php bin/console sass:build; \
    php bin/console asset-map:compile; \
    sync;
