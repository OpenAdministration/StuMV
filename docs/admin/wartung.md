# Wartung & Updates

## Automatische Aufgaben

Der [Cronjob](./installation#_10-cronjob-einrichten) ruft jede Minute den Laravel-Scheduler auf. Dieser startet täglich:

| Uhrzeit | Befehl | Aufgabe |
| --- | --- | --- |
| 00:01 | `ldap:sync-roles` | Rollenmitgliedschaften aus der Datenbank ins LDAP übertragen – inklusive Mitgliedschaften, deren Start- oder Enddatum erreicht ist |
| 00:15 | `ldap:sync-groups` | Rechtegruppen ins LDAP übertragen |
| 00:30 | `mailman:sync-lists` | Mailinglisten an die Rechtegruppen angleichen (nur mit Mailman) |
| 03:00 | `app:mark-inactive-accounts` | inaktive Konten benachrichtigen und vormerken (nur wenn `ACCOUNT_INACTIVE_AFTER_DAYS` gesetzt ist) |
| 03:30 | `app:delete-marked-accounts` | vorgemerkte Konten nach Ablauf der Frist löschen |
| 04:00 | `model:prune` | alte Einträge des Aktivitätsprotokolls löschen (nur wenn eine Frist gesetzt ist) |

Ob der Scheduler läuft, zeigt:

```bash
sudo -u www-data php artisan schedule:list
```

Alle Befehle lassen sich auch von Hand ausführen, z. B. für eine einzelne Studierendenschaft:

```bash
sudo -u www-data php artisan ldap:sync-roles stura
sudo -u www-data php artisan mailman:sync-lists stura
```

`app:mark-inactive-accounts` und `app:delete-marked-accounts` kennen `--dry-run`, um vorab zu sehen, welche Konten betroffen wären.

## Updates

```bash
cd /var/www/stumv
sudo -u www-data php artisan down

sudo -u www-data git pull
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data npm ci
sudo -u www-data npm run build
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

systemctl reload php8.4-fpm
sudo -u www-data php artisan up
```

::: warning Vor dem Update
- Lies die Änderungen seit der letzten Version, insbesondere Hinweise zu neuen `.env`-Variablen (vergleiche mit `.env.example`).
- Erstelle eine [Datensicherung](#datensicherung).
:::

## Datensicherung

Eine vollständige Sicherung besteht aus vier Teilen:

| Was | Wie |
| --- | --- |
| Datenbank | `mariadb-dump --single-transaction stumv > stumv.sql` (PostgreSQL: `sudo -u postgres pg_dump stumv > stumv.sql`) |
| LDAP-Daten | `slapcat -n 1 > ldap-data.ldif` |
| LDAP-Konfiguration | `slapcat -n 0 > ldap-config.ldif` |
| Dateien | `/var/www/stumv/.env`, `/var/www/stumv/storage/oauth-*.key`, `/var/www/stumv/storage/app/` (hochgeladene Bilder) |

Beispiel für ein nächtliches Sicherungsskript:

```bash
#!/bin/sh
# /usr/local/sbin/stumv-backup
set -eu
DIR=/var/backups/stumv/$(date +%F)
mkdir -p "$DIR"
mariadb-dump --single-transaction stumv | gzip > "$DIR/stumv.sql.gz"
slapcat -n 1 | gzip > "$DIR/ldap-data.ldif.gz"
slapcat -n 0 | gzip > "$DIR/ldap-config.ldif.gz"
tar -czf "$DIR/files.tar.gz" -C /var/www/stumv .env storage/oauth-private.key storage/oauth-public.key storage/app
chmod -R go-rwx "$DIR"
find /var/backups/stumv -mindepth 1 -maxdepth 1 -mtime +30 -exec rm -rf {} +
```

```bash
chmod 700 /usr/local/sbin/stumv-backup
echo '30 2 * * * root /usr/local/sbin/stumv-backup' > /etc/cron.d/stumv-backup
```

::: danger Sicherungen enthalten personenbezogene Daten
Die Sicherungen enthalten Passwort-Hashes, Anschriften und Telefonnummern sowie den `APP_KEY`. Lagere sie verschlüsselt und nur so lange wie nötig.
:::

### Wiederherstellen

```bash
# Datenbank
gunzip -c stumv.sql.gz | mariadb stumv

# LDAP (slapd gestoppt, leeres Datenverzeichnis)
systemctl stop slapd
gunzip -c ldap-data.ldif.gz | sudo -u openldap slapadd -n 1
systemctl start slapd
```

Datenbank und LDAP sollten aus **derselben** Sicherung stammen, da die Mitgliedschaften in der Datenbank auf Konten im LDAP verweisen.

## Nützliche Befehle

| Befehl | Zweck |
| --- | --- |
| `community:new <kürzel>` | Studierendenschaft anlegen |
| `community:domain:new <kürzel> <domain>` | Domain hinzufügen |
| `app:import-ldap-users [kürzel] [--verified]` | Datenbankeinträge für LDAP-Konten anlegen, die sich noch nie angemeldet haben |
| `app:provision-database-users [kürzel] [--dry-run]` | fehlende oder falsch verknüpfte Datenbankeinträge für LDAP-Konten reparieren |
| `app:move-user-to-realm <uid> <von> <nach>` | LDAP-Eintrag eines Kontos in eine andere Studierendenschaft verschieben |
| `app:copy-user <uid> <von> <nach> [--dry-run]` | Konto als unabhängige Kopie in eine weitere Studierendenschaft übernehmen; überschreibt einen vorhandenen Eintrag am Ziel |
| `app:delete-orphaned-profile-pictures [--dry-run]` | Profilbilder (Datenbankeintrag und Datei) ohne zugehöriges LDAP-Konto löschen |
| `directory-api:client <kürzel> <name>` | [API-Client](/user/administration/api-clients) auf der Kommandozeile registrieren |

Alle Befehle laufen als `www-data` im Installationsverzeichnis: `sudo -u www-data php artisan …`. Mit `--help` zeigt jeder Befehl seine Optionen.
