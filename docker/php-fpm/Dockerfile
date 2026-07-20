# Based on https://github.com/dockersamples/laravel-docker-examples
# (c) 2024 Sergei Shitikov
# License: https://github.com/dockersamples/laravel-docker-examples/blob/main/LICENSE

# PHP 8.4: composer.lock resolves to symfony ^8.1 which requires php >= 8.4.1.
FROM php:8.4-fpm AS builder

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    libonig-dev \
    libssl-dev \
    libzip-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libicu-dev \
    libldap2-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype-dev \
    libavif-dev \
    libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-avif --with-webp \
    && docker-php-ext-install -j$(nproc) \
    gd \
    ldap \
    pdo_mysql \
    zip \
    && apt-get autoremove -y && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

WORKDIR /var/www

COPY . /var/www

# Composer, pulled from the official image rather than curled (Debian's composer
# package lags upstream). Dependencies are NOT baked into this dev image: the
# project is always bind-mounted (docker-compose.dev.yaml), and the entrypoint
# runs `composer install` if the mounted vendor/ is missing. That avoids needing
# the Flux Pro (livewire/flux-pro) credentials at image-build time; provide them
# at runtime via COMPOSER_AUTH in the environment when a fresh install is needed.
COPY --from=docker.io/composer:2 /usr/bin/composer /usr/local/bin/composer

USER root

# Add ARGs for syncing permissions
ARG UID=1000
ARG GID=1000

# Create a new user with the specified UID and GID, reusing an existing group if GID exists
RUN if getent group ${GID}; then \
      group_name=$(getent group ${GID} | cut -d: -f1); \
      useradd -m -u ${UID} -g ${GID} -s /bin/bash www; \
    else \
      groupadd -g ${GID} www && \
      useradd -m -u ${UID} -g www -s /bin/bash www; \
      group_name=www; \
    fi

# Dynamically update php-fpm to use the new user and group
RUN sed -i "s/user = www-data/user = www/g" /usr/local/etc/php-fpm.d/www.conf && \
    sed -i "s/group = www-data/group = $group_name/g" /usr/local/etc/php-fpm.d/www.conf

# Set the working directory
WORKDIR /var/www

# Copy the entrypoint script
COPY ./docker/php-fpm/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Raise upload/memory limits above PHP's stingy defaults - realm branding
# uploads (background photos especially) routinely exceed the 2M/8M/128M
# defaults.
COPY ./docker/php-fpm/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Switch back to the non-privileged user to run the application
USER www-data

# Change the default command to run the entrypoint script
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]
