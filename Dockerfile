# syntax=docker/dockerfile:1.7
# Versioned Moodle source and PHP-FPM runtime, with a separate Nginx target.
# Official Moodle 5.2.4 source archive is verified by the published SHA-256.

ARG PHP_BASE_IMAGE=php:8.4.26-fpm-alpine
ARG NGINX_BASE_IMAGE=nginx:1.28-alpine
ARG ALPINE_BASE_IMAGE=alpine:3.24
ARG MOODLE_VERSION=5.2.4
ARG MOODLE_SHA256=8569b63f1e97416ecb67ec75767dd20675892c7a62a5e90d1647366fddf873cf
ARG REDIS_EXTENSION_VERSION=6.2.0

FROM ${ALPINE_BASE_IMAGE} AS moodle-source
ARG MOODLE_VERSION
ARG MOODLE_SHA256

RUN set -eux; \
    apk add --no-cache ca-certificates curl tar; \
    curl --fail --location --retry 5 --retry-delay 2 \
      "https://download.moodle.org/download.php/direct/stable502/moodle-${MOODLE_VERSION}.tgz" \
      --output /tmp/moodle.tgz; \
    echo "${MOODLE_SHA256}  /tmp/moodle.tgz" | sha256sum -c -; \
    mkdir -p /var/www/html; \
    tar -xzf /tmp/moodle.tgz --strip-components=1 -C /var/www/html; \
    rm -f /tmp/moodle.tgz

# Store third-party/site plugins at their Moodle-relative paths under moodle-plugins/.
COPY moodle-plugins/ /tmp/moodle-plugins/
RUN set -eux; \
    cp -a /tmp/moodle-plugins/. /var/www/html/; \
    rm -rf /tmp/moodle-plugins; \
    rm -f /var/www/html/.gitkeep; \
    chown -R root:root /var/www/html

FROM ${PHP_BASE_IMAGE} AS php-fpm
ARG REDIS_EXTENSION_VERSION

# Install development libraries for extensions compiled below. The official
# PHP 8.4 image may ship opcache/sodium modules without enabling their INI
# entries, so enable them explicitly in a later step.
RUN set -eux; \
    apk add --no-cache --virtual .build-deps \
      $PHPIZE_DEPS \
      freetype-dev \
      icu-dev \
      libjpeg-turbo-dev \
      libpng-dev \
      libxml2-dev \
      libzip-dev; \
    apk add --no-cache \
      freetype \
      icu-libs \
      libjpeg-turbo \
      libpng \
      libxml2 \
      libzip

# Keep the install list to extensions that need compiling on this PHP base.
# Splitting extension installation from PECL makes failures easier to diagnose.
RUN set -eux; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
      exif gd intl mysqli pdo_mysql soap zip

# PhpRedis 6.2.0 supports PHP 8.4. Pin it instead of relying on the PECL latest
# alias (6.3.0 metadata was intermittently unavailable from PECL infrastructure).
RUN set -eux; \
    pecl channel-update pecl.php.net; \
    pecl install "redis-${REDIS_EXTENSION_VERSION}"; \
    docker-php-ext-enable redis; \
    rm -rf /tmp/pear ~/.pearrc

# Enable OPcache and sodium only if they are not already loaded. PHP reports
# OPcache's extension name as "Zend OPcache" (not "opcache").
RUN set -eux; \
    if ! php -r 'exit(extension_loaded("Zend OPcache") ? 0 : 1);'; then docker-php-ext-enable opcache; fi; \
    if ! php -r 'exit(extension_loaded("sodium") ? 0 : 1);'; then docker-php-ext-enable sodium; fi

RUN set -eux; \
    apk del .build-deps; \
    php -r '$required = ["curl", "exif", "gd", "intl", "mbstring", "mysqli", "opcache", "pdo_mysql", "redis", "soap", "sodium", "zip"]; foreach ($required as $ext) { $loaded = ($ext === "opcache") ? extension_loaded("Zend OPcache") : extension_loaded($ext); if (!$loaded) { fwrite(STDERR, "Missing required PHP extension: {$ext}\n"); exit(1); } }'

COPY --from=moodle-source /var/www/html/ /var/www/html/
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-moodle.ini
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/moodle-entrypoint.sh /usr/local/bin/moodle-entrypoint

RUN set -eux; \
    chmod 0755 /usr/local/bin/moodle-entrypoint; \
    mkdir -p /var/moodledata /var/www/moodle-config /var/www/current-moodle-code; \
    chown www-data:www-data /var/moodledata

WORKDIR /var/www/html
EXPOSE 9000
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
  CMD php-fpm -t >/dev/null 2>&1 || exit 1
ENTRYPOINT ["moodle-entrypoint"]
CMD ["php-fpm", "-F"]

FROM ${NGINX_BASE_IMAGE} AS nginx
COPY --from=moodle-source /var/www/html/public/ /var/www/html/public/
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD nginx -t >/dev/null 2>&1 || exit 1
