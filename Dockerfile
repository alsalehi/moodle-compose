FROM php:8.4.26-apache

# Install required system dependencies for Moodle PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    libonig-dev \
    libsodium-dev \
    git \
    unzip \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Configure & install PHP extensions required by Moodle
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        mysqli \
        pdo_mysql \
        gd \
        intl \
        zip \
        opcache \
        soap \
        bcmath \
        sodium \
        exif

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Enable Apache mod_rewrite & mod_headers
RUN a2enmod rewrite headers

# Prepare moodledata directory with standard permissions
RUN mkdir -p /var/moodledata && chown -R www-data:www-data /var/moodledata

WORKDIR /var/www/html

# Download Moodle 5.2.4 source release
RUN curl -sSL https://download.moodle.org/download.php/direct/moodle/moodle-5.2.4.tgz | tar -xz --strip-components=1 \
    && chown -R www-data:www-data /var/www/html

# Copy custom config and PHP settings
COPY --chown=www-data:www-data config.php /var/www/html/config.php
COPY php.ini /usr/local/etc/php/conf.d/moodle.ini

EXPOSE 80
