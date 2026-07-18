# Based on https://github.com/dockersamples/laravel-docker-examples
# (c) 2024 Sergei Shitikov
# License: https://github.com/dockersamples/laravel-docker-examples/blob/main/LICENSE

# Use the official PHP CLI image as the base.
# PHP 8.4: composer.lock resolves to symfony ^8.1 which requires php >= 8.4.1.
FROM php:8.4-cli

# Set environment variables for user and group ID
ARG UID=1000
ARG GID=1000
ARG NODE_VERSION=22.0.0

# Install system dependencies and build libraries. gd + zip are needed so
# `composer install` satisfies the project's platform requirements.
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
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
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    ldap \
    pdo_mysql \
    zip \
    && apt-get autoremove -y && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Composer, pulled from the official image rather than curled (Debian's composer
# package lags upstream).
COPY --from=docker.io/composer:2 /usr/bin/composer /usr/local/bin/composer

# If the group already exists, use it; otherwise, create the 'www' group
RUN if getent group ${GID}; then \
      useradd -m -u ${UID} -g ${GID} -s /bin/bash www; \
    else \
      groupadd -g ${GID} www && \
      useradd -m -u ${UID} -g www -s /bin/bash www; \
    fi && \
    usermod -aG sudo www && \
    echo 'www ALL=(ALL) NOPASSWD:ALL' >> /etc/sudoers

# Switch to the non-root user to install NVM and Node.js
USER www

# Install NVM (Node Version Manager) as the www user
RUN export NVM_DIR="$HOME/.nvm" && \
    curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash && \
    [ -s "$NVM_DIR/nvm.sh" ] && . "$NVM_DIR/nvm.sh" && \
    nvm install ${NODE_VERSION} && \
    nvm alias default ${NODE_VERSION} && \
    nvm use default

# Ensure NVM is available for all future shells
RUN echo 'export NVM_DIR="$HOME/.nvm"' >> /home/www/.bashrc && \
    echo '[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"' >> /home/www/.bashrc && \
    echo '[ -s "$NVM_DIR/bash_completion" ] && \. "$NVM_DIR/bash_completion"' >> /home/www/.bashrc

# Set the working directory
WORKDIR /var/www

# Override the entrypoint to avoid the default php entrypoint
ENTRYPOINT []

# Default command to keep the container running
CMD ["bash"]