# Screenshots for the user manual

`capture.mjs` logs in as the demo accounts and saves the screenshots to
`docs/public/screenshots/`. Run it again whenever the UI changes.

It needs the OpenLDAP from the dev stack (`127.0.0.1:13389`) and a database with
the demo content. Use a throwaway database so your dev data stays out of the
pictures:

```bash
podman run --rm -d --name stumv-docsdb -p 127.0.0.1:23306:3306 \
  -e MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=yes -e MARIADB_DATABASE=stumv_docs \
  -e MARIADB_USER=stumv -e MARIADB_PASSWORD=local_stumv_password \
  docker.io/library/mariadb:11

export APP_ENV=local APP_DEBUG=false DEBUGBAR_ENABLED=false APP_URL=http://127.0.0.1:8123
export APP_KEY=base64:$(openssl rand -base64 32)
export DB_CONNECTION=mariadb DB_HOST=127.0.0.1 DB_PORT=23306 DB_DATABASE=stumv_docs \
  DB_USERNAME=stumv DB_PASSWORD=local_stumv_password
export LDAP_HOST=127.0.0.1 LDAP_PORT=13389 LDAP_TLS_VERIFY=false \
  LDAP_USERNAME="uid=stumv,ou=Services,dc=stumv,dc=de" LDAP_PASSWORD=stumv-not-production
export SESSION_DRIVER=database MAIL_MAILER=log MAILMAN_API_URL=

php artisan migrate --force
php artisan db:seed --class=DemoUsersSeeder --force
php artisan db:seed --class=DemoDocsSeeder --force
php artisan app:import-ldap-users admin --verified
# writes the demo memberships to LDAP, so the sync status shows "Aktuell"
php artisan ldap:sync-roles demo && php artisan ldap:sync-groups demo

npm run build
php artisan serve --host=127.0.0.1 --port=8123 --no-reload
```

Then, in a second terminal:

```bash
npx playwright install chromium   # once
npm run docs:screenshots          # all of them
npm run docs:screenshots -- roles role-members   # just these
```

The superadmin screenshot logs in as `admin` / `admin-not-production` from
`10-sample.ldif`. If that account is gone from your LDAP, point the script at
another account in the `admin` realm with `SUPERADMIN_UID` and
`SUPERADMIN_PASSWORD`.

Afterwards: `podman stop stumv-docsdb`.
