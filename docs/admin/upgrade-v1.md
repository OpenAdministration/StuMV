# Upgrade von v1 auf v2

Diese Anleitung aktualisiert eine bestehende StuMV-Installation von **v1** (Branch [`main`](https://github.com/OpenAdministration/StuMV)) auf **v2** (Branch [`new-architecture`](https://github.com/OpenAdministration/StuMV/tree/new-architecture)). Das Upgrade verändert sowohl die Datenbank als auch die Struktur des LDAP-Verzeichnisses und **lässt sich nicht rückgängig machen** – außer durch Einspielen der Sicherung.

::: danger Erst auf einer Kopie testen
Spiele das Upgrade zuerst auf einer Kopie der Produktivdaten durch (Datenbank-Dump und `slapcat` in einer Test-VM). Die Ausgaben der `--dry-run`-Läufe zeigen dir dort, was sich ändern wird.
:::

## Was sich ändert

| Bereich | v1 | v2 |
| --- | --- | --- |
| **Konten** | ein gemeinsamer Zweig `ou=People,dc=…` für alle | ein eigener Zweig `ou=People` je Studierendenschaft. Wer in mehreren Studierendenschaften Mitglied war, bekommt **je Studierendenschaft ein eigenes, unabhängiges Konto**. |
| **Mitgliedschaft in der Studierendenschaft** | Gruppe `cn=members` je Studierendenschaft | ergibt sich aus dem Ort des Kontos; die Gruppe `cn=members` entfällt |
| **Superadmins** | Gruppe `cn=super-admins,dc=…` | Konten in der eigenen Studierendenschaft `admin`, Anmeldung unter `/admin/login` |
| **Rechtegruppen** | Rollen sind als `uniqueMember` der LDAP-Gruppe eingetragen | Zuordnung Gruppe ↔ Rolle liegt in der Datenbank; im LDAP stehen nur noch die Personen |
| **Anmeldung** | eine Login-Seite | Login je Studierendenschaft unter `/<kürzel>/login` |
| **OAuth/OIDC** | globale Endpunkte unter `/oauth/…` | Endpunkte je Studierendenschaft unter `/<kürzel>/oauth/…`, Discovery unter `/<kürzel>/.well-known/openid-configuration`. Jeder Client gehört zu genau einer Studierendenschaft. |
| **LDAP-Verbindung** | `LDAP_SSL`/`LDAP_TLS` wählbar | immer StartTLS |
| **Software** | PHP 8.2, Laravel 10, Livewire 3 | PHP 8.4, Laravel 13, Livewire 4, Flux Pro, OpenLDAP 2.6 |

Neu hinzu kommen u. a. Identity Provider, Einladungen, Mailman-Anbindung, Erscheinungsbild, Tätigkeits-Bescheinigungen, Aktivitätsprotokoll und das Löschen inaktiver Konten – siehe [Benutzerhandbuch](/user/einfuehrung/was-ist-stumv).

## Ablauf im Überblick

1. [Vorbereiten und sichern](#_1-vorbereiten-und-sichern)
2. [Wartungsmodus und Cronjob anhalten](#_2-wartungsmodus-und-cronjob-anhalten)
3. [Systemvoraussetzungen aktualisieren](#_3-systemvoraussetzungen-aktualisieren)
4. [OpenLDAP anpassen](#_4-openldap-anpassen)
5. [Code aktualisieren](#_5-code-aktualisieren)
6. [`.env` anpassen](#_6-env-anpassen)
7. [Datenbank migrieren](#_7-datenbank-migrieren)
8. [Gruppen-Rollen-Zuordnungen übernehmen](#_8-gruppen-rollen-zuordnungen-ubernehmen)
9. [Konten auf die Studierendenschaften aufteilen](#_9-konten-auf-die-studierendenschaften-aufteilen)
10. [Datenbank-Konten verknüpfen](#_10-datenbank-konten-verknupfen)
11. [OAuth-Clients zuordnen](#_11-oauth-clients-zuordnen)
12. [LDAP synchronisieren und prüfen](#_12-ldap-synchronisieren-und-prufen)
13. [Wieder in Betrieb nehmen](#_13-wieder-in-betrieb-nehmen)

::: warning Reihenfolge einhalten
Schritt 8 muss **vor** jedem Lauf von `ldap:sync-groups` erfolgen. Der Befehl ersetzt die Mitglieder jeder Rechtegruppe durch die Personen aus der Datenbank – die in v1 im LDAP eingetragenen Rollen wären danach verloren. Deshalb wird in Schritt 2 auch der Cronjob angehalten.
:::

Alle `artisan`-Befehle laufen als `www-data` im Installationsverzeichnis:

```bash
cd /var/www/stumv
sudo -u www-data php artisan …
```

## 1. Vorbereiten und sichern

**Informiere vorab** alle Beteiligten:

- Betreiber:innen angeschlossener Anwendungen (OIDC/OAuth): Die Endpunkte ändern sich, die Anwendungen müssen in Schritt 11 neu konfiguriert werden.
- Mitglieder: Die Login-Adresse ändert sich auf `/<kürzel>/login`. Wer in mehreren Studierendenschaften Mitglied ist, hat danach getrennte Konten.
- Superadmins: Anmeldung künftig unter `/admin/login`.

**Erstelle eine vollständige Sicherung:**

```bash
mkdir -p /var/backups/stumv-v1
cd /var/backups/stumv-v1
mariadb-dump --single-transaction stumv > stumv.sql
slapcat -n 0 > ldap-config.ldif
slapcat -n 1 > ldap-data.ldif
tar -czf files.tar.gz -C /var/www/stumv .env storage
```

Notiere dir außerdem den aktuellen Commit: `git -C /var/www/stumv rev-parse HEAD`.

## 2. Wartungsmodus und Cronjob anhalten

```bash
sudo -u www-data php artisan down
mv /etc/cron.d/stumv /root/stumv.cron.disabled   # Pfad ggf. anpassen
```

Prüfe mit `crontab -l -u www-data`, dass auch dort kein `schedule:run` mehr eingetragen ist.

## 3. Systemvoraussetzungen aktualisieren

| Komponente | Anforderung v2 |
| --- | --- |
| PHP | **8.4** mit zusätzlich `intl` und `zip` |
| OpenLDAP | **2.6** (für `olcLastBind`) |
| Flux Pro | Lizenzschlüssel |
| Node.js | zum Bauen der Assets (Vite 7) |

Auf Debian 13 (Trixie) sind PHP 8.4 und OpenLDAP 2.6 enthalten. Läuft die Installation noch auf einem älteren Debian, ist jetzt der Zeitpunkt für ein Distributions-Upgrade:

```bash
apt install -y php8.4-fpm php8.4-cli php8.4-gd php8.4-intl php8.4-ldap php8.4-mysql \
  php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip
```

Passe den nginx-Socket auf `php8.4-fpm.sock` an und übernimm die [PHP-Limits](./installation#_2-php-anpassen) und `client_max_body_size 15m` aus der Installationsanleitung.

::: info OpenLDAP 2.5 → 2.6
Debian aktualisiert slapd beim Distributions-Upgrade selbst und exportiert/importiert die Datenbank dabei. Prüfe danach mit `slapd -VV`, dass Version 2.6 läuft.
:::

## 4. OpenLDAP anpassen

v2 benötigt zusätzliche Zugriffsregeln für die Konten-Zweige je Studierendenschaft sowie einige Einstellungen, die in v1 noch nicht nötig waren.

### Module und Overlays

Prüfe, welche Module geladen und welche Overlays konfiguriert sind:

```bash
ldapsearch -Y EXTERNAL -H ldapi:/// -b cn=config -LLL \
  '(|(objectClass=olcModuleList)(objectClass=olcOverlayConfig))' olcModuleLoad olcOverlay
```

Benötigt werden die Module `argon2`, `dynlist` und `ppolicy` sowie die Overlays `dynlist` und `ppolicy` auf der Hauptdatenbank. Fehlt etwas, ergänze es wie in [OpenLDAP einrichten](./openldap#_4-konfiguration-anpassen) beschrieben. Ist das `ppolicy`-Overlay neu, lege auch den Eintrag `cn=default,ou=Policies,…` aus der [Grundstruktur](./openldap#_5-grundstruktur-anlegen) an.

### Zugriffsregeln und letzte Anmeldung

Spiele den Teil von `stumv-config.ldif` aus [OpenLDAP einrichten](./openldap#_4-konfiguration-anpassen) ein, der die Hauptdatenbank betrifft (`olcLastBind`, `olcLastBindPrecision`, `olcAccess`). Neu gegenüber v1 sind vor allem:

- **Regeln `{6}`–`{8}`**: Lese-, Schreib- und Passwortrechte für `ou=People,ou=<kürzel>,ou=Communities,…`. Ohne sie können sich Personen nach Schritt 9 nicht mehr anmelden oder ihr Passwort ändern.
- **Regel `{10}`**: Schreibrecht auf die Kinder der Basis. Ohne sie kann Schritt 9 die alte Gruppe `cn=super-admins` nicht löschen.

::: warning Eigene Regeln übernehmen
Hast du in v1 zusätzliche Zugriffsregeln (z. B. für weitere Service-Konten) eingerichtet, füge sie in die neue Liste ein, bevor du sie mit `replace: olcAccess` einspielst.
:::

### StartTLS

v2 verbindet sich immer per StartTLS. Ist auf slapd noch kein Zertifikat konfiguriert, richte es wie unter [TLS-Zertifikat erzeugen](./openldap#_3-tls-zertifikat-erzeugen) ein und teste die Verbindung:

```bash
LDAPTLS_CACERT=/etc/ldap/tls/server.crt \
  ldapwhoami -x -ZZ -H ldap://127.0.0.1 -D "<LDAP_USERNAME aus der .env>" -W
```

## 5. Code aktualisieren

```bash
cd /var/www/stumv
sudo -u www-data git fetch origin
sudo -u www-data git checkout new-architecture
```

Flux-Pro-Zugangsdaten hinterlegen, Abhängigkeiten installieren, Assets bauen:

```bash
sudo -u www-data composer config http-basic.composer.fluxui.dev "deine@adresse.de" "DEIN-LIZENZSCHLÜSSEL"
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data npm ci
sudo -u www-data npm run build
```

## 6. `.env` anpassen

Vergleiche deine `.env` mit der neuen `.env.example`. Die wichtigsten Änderungen:

| Variable | Änderung |
| --- | --- |
| `ABOUT_URL` | entfällt → `IMPRINT_URL` |
| `LDAP_SSL`, `LDAP_TLS` | entfallen – es wird immer StartTLS verwendet. `LDAP_PORT` daher `389`. |
| `LDAP_TLS_VERIFY`, `LDAP_TLS_CACERT` | neu – siehe [Konfiguration](./konfiguration#ldap) |
| `DB_CONNECTION` | `mariadb` (oder weiterhin `mysql`) |
| `SESSION_DRIVER` | `database` empfohlen |
| `PRIVACY_URL`, `TERMS_URL`, `SOURCE_CODE_URL`, `OPERATOR_NAME`, `HELP_CONTACT_MAIL` | neu |
| `MAILMAN_*`, `ACCOUNT_*`, `ACTIVITY_LOG_*`, `CSP_ENABLED` | neu, optional |

Alle Variablen erklärt die [Konfigurationsreferenz](./konfiguration).

```bash
sudo -u www-data php artisan config:clear
```

## 7. Datenbank migrieren

```bash
sudo -u www-data php artisan migrate --force
```

Die Migrationen legen alle neuen Tabellen an, ergänzen die bestehenden um die Studierendenschaft (`realm`) und befüllen diese für alle Personen mit Rollenmitgliedschaften. Die Passport-Tabellen aus v1 werden weiterverwendet; ihre ganzzahligen Client-IDs werden in Zeichenketten umgewandelt, damit neue Clients UUIDs erhalten können. Bestehende Client-IDs bleiben gültig.

## 8. Gruppen-Rollen-Zuordnungen übernehmen

Übernimm die Zuordnungen von Rollen zu Rechtegruppen aus dem LDAP in die Datenbank. Prüfe zuerst die Ausgabe:

```bash
sudo -u www-data php artisan app:move-group-roles-from-ldap-to-database --dry-run
sudo -u www-data php artisan app:move-group-roles-from-ldap-to-database
```

Der Befehl übernimmt je Studierendenschaft alle Rollen (Einträge unter `ou=Committees`), die in einer ihrer Gruppen unter `ou=Groups` als Mitglied eingetragen sind. Er lässt sich gefahrlos mehrfach ausführen.

## 9. Konten auf die Studierendenschaften aufteilen

Dieser Schritt verschiebt alle Konten aus dem gemeinsamen Zweig `ou=People` in die Zweige der jeweiligen Studierendenschaften.

```bash
sudo -u www-data php artisan app:split-people-by-realm --dry-run
```

Die Ausgabe zeigt für jede Person, wohin ihr Konto verschoben oder kopiert wird. Dabei gilt:

- Grundlage ist die Mitgliedschaft in der Gruppe `cn=members` der Studierendenschaft bzw. in `cn=super-admins`.
- Wer in **mehreren** Studierendenschaften Mitglied ist, behält das Originalkonto in der ersten und bekommt in jeder weiteren eine **unabhängige Kopie** – mit demselben Anmeldenamen und Passwort, das sich danach aber getrennt ändern lässt. Superadmins werden immer zuerst in die Studierendenschaft `admin` verschoben.
- Die Einträge in `cn=admins` und `cn=moderators` werden auf die neuen Konten umgeschrieben; die Gruppen `cn=members` und `cn=super-admins` werden gelöscht.

::: warning Nicht zugeordnete Konten
Am Ende listet der Befehl unter *unassigned* alle Konten, die in keiner Studierendenschaft Mitglied sind. Sie bleiben im alten Zweig und **können sich in v2 nicht mehr anmelden**. Soll eine dieser Personen ihr Konto behalten, trage sie vor dem echten Lauf in die Gruppe `cn=members` der passenden Studierendenschaft ein.
:::

Wenn die Ausgabe stimmt:

```bash
sudo -u www-data php artisan app:split-people-by-realm
```

## 10. Datenbank-Konten verknüpfen

Schritt 9 verknüpft verschobene und kopierte Konten bereits mit ihren Datenbankeinträgen. Prüfe anschließend, ob noch Konten ohne oder mit falsch verknüpftem Datenbankeintrag übrig sind – etwa Personen, die sich in v1 nie angemeldet haben – und repariere sie:

```bash
sudo -u www-data php artisan app:provision-database-users --dry-run
sudo -u www-data php artisan app:provision-database-users
```

## 11. OAuth-Clients zuordnen

In v2 gehört jeder OAuth/OIDC-Client zu genau einer Studierendenschaft. Bestehende Clients aus v1 haben noch keine Zuordnung – sie **funktionieren erst wieder und erscheinen erst dann in der Oberfläche**, wenn sie zugeordnet sind:

```bash
mariadb stumv -e "SELECT id, name, redirect_uris, community_uid FROM oauth_clients WHERE revoked = 0;"
mariadb stumv -e "UPDATE oauth_clients SET community_uid = 'stura' WHERE id = '…';"
```

::: tip Spaltenname
Je nach Stand der v1-Datenbank heißt die Spalte für die Redirect-URIs `redirect` statt `redirect_uris`. Passe die Abfrage dann an.
:::

Stelle danach in jeder angeschlossenen Anwendung die neuen Adressen ein. Am einfachsten über die Discovery-URL:

```
https://stumv.example.org/<kürzel>/.well-known/openid-configuration
```

Client-ID und Secret bleiben unverändert. Anschließend lassen sich die Clients wie gewohnt unter [OIDC-Clients](/user/administration/oidc-clients) und [API-Clients](/user/administration/api-clients) verwalten.

Die OAuth-Schlüssel aus v1 (`storage/oauth-private.key`, `storage/oauth-public.key`) werden weiterverwendet. Fehlen sie, erzeuge sie mit `php artisan passport:keys`.

## 12. LDAP synchronisieren und prüfen

Übertrage die Rollen- und Gruppenmitgliedschaften jetzt mit der neuen Logik ins LDAP:

```bash
sudo -u www-data php artisan ldap:sync-roles
sudo -u www-data php artisan ldap:sync-groups
```

Prüfe stichprobenartig einige Rechtegruppen: Sie sollten jetzt die **Personen** mit den zugeordneten Rollen enthalten.

```bash
LDAPTLS_CACERT=/etc/ldap/tls/server.crt ldapsearch -x -ZZ -H ldap://127.0.0.1 \
  -D "<LDAP_USERNAME>" -W -b "ou=Groups,ou=<kürzel>,ou=Communities,dc=stumv,dc=de" uniqueMember
```

## 13. Wieder in Betrieb nehmen

```bash
sudo -u www-data php artisan storage:link
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
systemctl reload php8.4-fpm

mv /root/stumv.cron.disabled /etc/cron.d/stumv
sudo -u www-data php artisan up
```

Prüfe, dass der Cronjob dem in der [Installationsanleitung](./installation#_10-cronjob-einrichten) entspricht (`schedule:run` jede Minute).

### Nach dem Upgrade testen

- [ ] Ein Superadmin kann sich unter `/admin/login` anmelden und die Studierendenschaften sehen.
- [ ] Ein Mitglied kann sich unter `/<kürzel>/login` anmelden und sein Passwort ändern.
- [ ] Moderator:innen und Administrator:innen haben ihre bisherigen Rechte.
- [ ] Unter **Rechte** sind bei jeder Gruppe die erwarteten Rollen zugeordnet.
- [ ] Eine angeschlossene Anwendung meldet sich per OIDC erfolgreich an.
- [ ] Das Menü **Synchronisieren** zeigt *Aktuell*.

## Zurück auf v1

Geht etwas schief, stelle den Stand aus Schritt 1 wieder her:

```bash
sudo -u www-data php artisan down
systemctl stop slapd
mv /etc/ldap/slapd.d /etc/ldap/slapd.d.v2 && mkdir /etc/ldap/slapd.d
mv /var/lib/ldap /var/lib/ldap.v2 && mkdir /var/lib/ldap
slapadd -n 0 -F /etc/ldap/slapd.d -l /var/backups/stumv-v1/ldap-config.ldif
slapadd -n 1 -F /etc/ldap/slapd.d -l /var/backups/stumv-v1/ldap-data.ldif
chown -R openldap:openldap /etc/ldap/slapd.d /var/lib/ldap
systemctl start slapd

mariadb -e "DROP DATABASE stumv; CREATE DATABASE stumv CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mariadb stumv < /var/backups/stumv-v1/stumv.sql

cd /var/www/stumv
sudo -u www-data git checkout <commit aus Schritt 1>
tar -xzf /var/backups/stumv-v1/files.tar.gz -C /var/www/stumv
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data npm ci && sudo -u www-data npm run build
```

Danach PHP-Version, nginx-Socket und Cronjob auf den v1-Stand zurücksetzen.
