#!/bin/sh

# Based on https://github.com/dockersamples/laravel-docker-examples
# (c) 2024 Sergei Shitikov
# License: https://github.com/dockersamples/laravel-docker-examples/blob/main/LICENSE

set -e

# Check if $UID and $GID are set, else fallback to default (1000:1000)
USER_ID=${UID:-1000}
GROUP_ID=${GID:-1000}

# Fix file ownership and permissions using the passed UID and GID
echo "Fixing file permissions with UID=${USER_ID} and GID=${GROUP_ID}..."
chown -R ${USER_ID}:${GROUP_ID} /var/www || echo "Some files could not be changed"

# The image bakes a vendor/ directory, but the bind mount of the project source
# shadows it. Install dependencies against the mounted tree if they are missing
# (e.g. a fresh checkout) so the app can boot.
if [ ! -f /var/www/vendor/autoload.php ]; then
    echo "vendor/ missing on the mounted volume — running composer install..."
    composer install --no-interaction --prefer-dist --no-progress
fi

# Run database migrations
php artisan migrate --force

# Seed the DB records for the LDAP demo logins (20-demo.ldif) with verified
# emails. Idempotent (keyed on username), so it is safe on every start.
php artisan db:seed --class=DemoUsersSeeder --force

# Clear configurations to avoid caching issues in development
echo "Clearing configurations..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Run the default command (e.g., php-fpm or bash)
exec "$@"