# Installation auf Debian

Diese Anleitung installiert StuMV mit nginx, PHP-FPM, MariaDB und OpenLDAP auf **einem** Debian-13-Server. Alle Befehle werden als `root` ausgeführt, sofern nicht anders angegeben.

In den Beispielen steht

- `stumv.example.org` für den Hostnamen der Installation,
- `dc=stumv,dc=de` für die Basis des LDAP-Verzeichnisses,
- `/var/www/stumv` für das Installationsverzeichnis.

Ersetze die Werte durch eure eigenen.

## 1. Pakete installieren

```bash
apt update
apt install -y nginx mariadb-server git unzip curl cron \
  php8.4-fpm php8.4-cli php8.4-gd php8.4-intl php8.4-ldap php8.4-mysql \
  php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip \
  composer nodejs npm
```

::: info Node.js
Node.js wird nur zum Bauen der Frontend-Assets gebraucht. Debian 13 liefert Node.js 20.19, das die Mindestanforderung von Vite 7 erfüllt. Alternativ kannst du die Assets auf einem anderen Rechner bauen und nur `public/build` kopieren.
:::

OpenLDAP wird im nächsten Kapitel [eingerichtet](./openldap) – erledige das, bevor du mit Schritt 5 weitermachst.

## 2. PHP anpassen

Logos, Hintergrund- und Profilbilder können mehrere Megabyte groß sein. Erhöhe die Limits für PHP-FPM:

```ini
; /etc/php/8.4/fpm/conf.d/99-stumv.ini
upload_max_filesize = 12M
post_max_size = 15M
memory_limit = 256M
```

```bash
systemctl restart php8.4-fpm
```

## 3. Datenbank anlegen

```bash
mariadb <<'SQL'
CREATE DATABASE stumv CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'stumv'@'localhost' IDENTIFIED BY 'EIN-SICHERES-PASSWORT';
GRANT ALL PRIVILEGES ON stumv.* TO 'stumv'@'localhost';
SQL
```

## 4. OpenLDAP einrichten

Siehe [OpenLDAP einrichten](./openldap).

## 5. StuMV herunterladen

```bash
git clone https://github.com/OpenAdministration/StuMV /var/www/stumv
chown -R www-data:www-data /var/www/stumv
```

Alle weiteren Befehle in diesem Abschnitt laufen als `www-data` im Installationsverzeichnis:

```bash
cd /var/www/stumv
sudo -u www-data -s
```

## 6. Abhängigkeiten installieren

StuMV verwendet die kostenpflichtige UI-Bibliothek Flux Pro. Hinterlege deine Zugangsdaten für deren Composer-Repository (E-Mail-Adresse des Flux-Kontos und Lizenzschlüssel):

```bash
composer config http-basic.composer.fluxui.dev "deine@adresse.de" "DEIN-LIZENZSCHLÜSSEL"
```

Dann die Abhängigkeiten installieren und die Assets bauen:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

## 7. Konfigurieren

```bash
cp .env.example .env
php artisan key:generate
```

Passe die `.env` an. Mindestens nötig sind:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://stumv.example.org
LOG_LEVEL=warning

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_DATABASE=stumv
DB_USERNAME=stumv
DB_PASSWORD=EIN-SICHERES-PASSWORT

LDAP_HOST=127.0.0.1
LDAP_PORT=389
LDAP_BASE_DN="dc=stumv,dc=de"
LDAP_USERNAME="uid=stumv,ou=Services,dc=stumv,dc=de"
LDAP_PASSWORD=PASSWORT-DES-SERVICE-KONTOS
LDAP_TLS_CACERT=/etc/ldap/tls/server.crt

SESSION_DRIVER=database
QUEUE_CONNECTION=sync

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.org
MAIL_PORT=587
MAIL_USERNAME=stumv@example.org
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="stumv@example.org"
```

Alle Optionen erklärt die [Konfigurationsreferenz](./konfiguration).

## 8. Datenbank und Schlüssel einrichten

```bash
php artisan migrate --force
php artisan storage:link
php artisan passport:keys
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`passport:keys` erzeugt `storage/oauth-private.key` und `storage/oauth-public.key`. Damit signiert StuMV die Tokens für OIDC-Clients.

::: danger Schlüssel sichern
Gehen die OAuth-Schlüssel verloren oder werden neu erzeugt, sind alle ausgestellten Tokens ungültig und angeschlossene Anwendungen müssen sich neu anmelden. Nimm sie in die [Datensicherung](./wartung#datensicherung) auf.
:::

Danach mit `exit` zurück zu `root`.

## 9. nginx einrichten

```nginx
# /etc/nginx/sites-available/stumv
server {
    listen 80;
    listen [::]:80;
    server_name stumv.example.org;

    root /var/www/stumv/public;
    index index.php;

    # passend zu post_max_size in PHP
    client_max_body_size 15m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
ln -s /etc/nginx/sites-available/stumv /etc/nginx/sites-enabled/
rm /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
```

::: warning `.well-known` nicht blockieren
StuMV stellt die OIDC-Discovery unter `/<kürzel>/.well-known/openid-configuration` bereit. Achte bei eigenen nginx-Regeln darauf, `.well-known` nicht zu sperren.
:::

### HTTPS

StuMV muss über HTTPS erreichbar sein – Logins, OIDC und Cookies setzen das voraus. Mit Let's Encrypt:

```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d stumv.example.org
```

## 10. Cronjob einrichten

Der Laravel-Scheduler übernimmt den nächtlichen LDAP- und Mailman-Abgleich, das Aufräumen inaktiver Konten und des Aktivitätsprotokolls. Er muss **jede Minute** aufgerufen werden:

```bash
cat > /etc/cron.d/stumv <<'EOF'
* * * * * www-data cd /var/www/stumv && php artisan schedule:run >> /dev/null 2>&1
EOF
```

::: tip Kein Queue-Worker nötig
Mit `QUEUE_CONNECTION=sync` werden E-Mails und Logout-Benachrichtigungen direkt im Request verschickt. Ein eigener Queue-Worker ist nicht nötig.
:::

## 11. Weiter

StuMV läuft jetzt, aber es gibt noch keine Konten. Lege als Nächstes den [ersten Superadmin](./ersteinrichtung) an.
