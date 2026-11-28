# Konfiguration

StuMV wird über die Datei `.env` im Installationsverzeichnis konfiguriert. Eine kommentierte Vorlage liegt als `.env.example` bei.

::: warning Nach jeder Änderung Cache neu bauen
In Produktion ist die Konfiguration gecacht. Änderungen an der `.env` werden erst wirksam nach:

```bash
sudo -u www-data php artisan config:cache
```
:::

## Anwendung

| Variable | Bedeutung | Beispiel |
| --- | --- | --- |
| `APP_NAME` | Name in Seitenleiste, Titel und E-Mails | `StuMV` |
| `APP_ENV` | in Produktion immer `production` | `production` |
| `APP_KEY` | Verschlüsselungsschlüssel, wird mit `php artisan key:generate` erzeugt. **Nicht ändern**, sonst sind verschlüsselte Daten (z. B. Secrets der Identity Provider) unlesbar. | `base64:…` |
| `APP_DEBUG` | Fehlerdetails im Browser anzeigen. In Produktion **immer** `false`. | `false` |
| `APP_URL` | öffentliche Adresse mit `https://`, ohne abschließenden Schrägstrich | `https://stumv.example.org` |
| `LOG_LEVEL` | Mindeststufe für Einträge in `storage/logs/laravel.log` | `warning` |

## Betreiberangaben

| Variable | Bedeutung |
| --- | --- |
| `OPERATOR_NAME` | Name der Betreiber:innen, wird in Hilfetexten genannt (z. B. „Neue Administrator:innen können … durch *Betreiber* hinzugefügt werden“) |
| `HELP_CONTACT_MAIL` | Kontaktadresse für Hilfeanfragen, verlinkt auf den Seiten zu Administrator:innen und Studierendenschaften |
| `IMPRINT_URL` | Link zum Impressum in der Fußzeile |
| `PRIVACY_URL` | Link zur Datenschutzerklärung |
| `TERMS_URL` | Link zu den Nutzungsbedingungen |
| `SOURCE_CODE_URL` | Link zum Quellcode |

Leere oder auskommentierte Links werden in der Fußzeile nicht angezeigt.

## Datenbank

| Variable | Bedeutung |
| --- | --- |
| `DB_CONNECTION` | `mariadb`, `mysql` oder `pgsql` |
| `DB_HOST`, `DB_PORT` | Adresse des Datenbankservers |
| `DB_DATABASE` | Name der Datenbank |
| `DB_USERNAME`, `DB_PASSWORD` | Zugangsdaten |

::: info PostgreSQL
Statt MariaDB kann auch PostgreSQL verwendet werden: `DB_CONNECTION=pgsql`, `DB_PORT=5432`, Paket `php8.4-pgsql`. Die automatisierten Tests laufen gegen beide Datenbanken.
:::

## LDAP

| Variable | Bedeutung | Standard |
| --- | --- | --- |
| `LDAP_HOST` | Adresse des OpenLDAP-Servers | `127.0.0.1` |
| `LDAP_PORT` | Port. StuMV verwendet immer StartTLS, daher `389` und nicht `636`. | `389` |
| `LDAP_BASE_DN` | Basis des Verzeichnisses | – |
| `LDAP_USERNAME` | DN des [Service-Kontos](./openldap#_5-grundstruktur-anlegen) | – |
| `LDAP_PASSWORD` | Passwort des Service-Kontos | – |
| `LDAP_TIMEOUT` | Verbindungs-Timeout in Sekunden | `5` |
| `LDAP_TLS_VERIFY` | Zertifikat des LDAP-Servers prüfen. Nur zum Testen auf `false` setzen. | `true` |
| `LDAP_TLS_CACERT` | CA-Datei für das Zertifikat. Leer: System-Zertifikatsspeicher. | – |
| `LDAP_LOGGING` | LDAP-Operationen protokollieren | `true` |
| `LDAP_CACHE` | LDAP-Abfragen cachen | `false` |

## Sitzungen, Cache, Queue

| Variable | Empfehlung | Hinweis |
| --- | --- | --- |
| `SESSION_DRIVER` | `database` | Nötig, damit Mitglieder unter *Profil → Sitzungen* ihre angemeldeten Geräte sehen und abmelden können. |
| `SESSION_LIFETIME` | `120` | Minuten bis zum automatischen Abmelden bei Inaktivität |
| `CACHE_DRIVER` | `file` | `redis` oder `memcached` sind möglich, aber nicht nötig. |
| `QUEUE_CONNECTION` | `sync` | E-Mails werden direkt verschickt; kein Queue-Worker nötig. |

## E-Mail

StuMV verschickt E-Mails für Registrierung, Passwort-Reset, Einladungen, Bestätigung weiterer Adressen und die Benachrichtigung inaktiver Konten.

| Variable | Bedeutung |
| --- | --- |
| `MAIL_MAILER` | `smtp` (oder `sendmail`, `log` zum Testen) |
| `MAIL_HOST`, `MAIL_PORT` | SMTP-Server, z. B. Port `587` |
| `MAIL_USERNAME`, `MAIL_PASSWORD` | SMTP-Zugangsdaten |
| `MAIL_ENCRYPTION` | `tls`, oder leer bei automatischem STARTTLS |
| `MAIL_FROM_ADDRESS` | Absenderadresse |
| `MAIL_FROM_NAME` | Absendername, Standard: `APP_NAME` |

::: tip Zustellbarkeit
Richte für die Absenderdomain SPF, DKIM und DMARC ein. Sonst landen Bestätigungs-E-Mails gerade bei Hochschul-Mailservern schnell im Spam.
:::

## Mailman 3 (optional)

Für die [Mailinglisten-Synchronisation](/user/administration/mailinglisten). Ohne `MAILMAN_API_URL` ist die Funktion abgeschaltet.

| Variable | Bedeutung | Standard |
| --- | --- | --- |
| `MAILMAN_API_URL` | Wurzel der REST-API von Mailman Core (nicht Postorius/HyperKitty), z. B. `https://lists.example.org:8001/3.1` | – |
| `MAILMAN_API_USER` | REST-Benutzer | `restadmin` |
| `MAILMAN_API_KEY` | REST-Passwort | – |
| `MAILMAN_API_VERIFY_TLS` | `true`, `false` oder Pfad zu einem CA-Bundle | `true` |
| `MAILMAN_WEB_URL` | Optional: Wurzel von mailman-web (ohne `/postorius`). Für Server, deren REST-API nur lesbar ist – Änderungen laufen dann über Postorius als Listen-Owner. | – |
| `MAILMAN_WEB_USER`, `MAILMAN_WEB_PASSWORD` | Zugangsdaten für Postorius | – |
| `MAILMAN_WEB_VERIFY_TLS` | wie oben | `true` |
| `MAILMAN_LIST_CREATION` | Administrator:innen dürfen neue Listen direkt aus StuMV anlegen (braucht eine beschreibbare REST-API) | `false` |
| `MAILMAN_LIST_OWNER` | Adresse, die Owner jeder neu angelegten Liste wird – z. B. die von `MAILMAN_WEB_USER` | – |

Welche Studierendenschaft unter welcher Mailman-Domain Listen anlegen darf, legen Superadmins in der Oberfläche fest (*Einstellungen → Mailinglisten-Domains*).

## Hochschul-LDAP (optional)

Für die Hilfsmittel [Nutzer:innen-Import aus dem Uni-LDAP](/user/moderation/hilfsmittel#nutzer-innen-import-aus-dem-uni-ldap) und [Uni-Account-Abgleich](/user/moderation/hilfsmittel#uni-account-abgleich). StuMV liest dort nur.

| Variable | Bedeutung | Standard |
| --- | --- | --- |
| `UNI_LDAP_HOST`, `UNI_LDAP_PORT` | Verzeichnisserver der Hochschule (StartTLS) | `127.0.0.1`, `389` |
| `UNI_LDAP_BASE_DN` | Suchbasis | – |
| `UNI_LDAP_USERNAME`, `UNI_LDAP_PASSWORD` | Lesekonto | – |
| `UNI_LDAP_TIMEOUT` | Timeout in Sekunden | `5` |
| `UNI_LDAP_BATCH_SIZE` | Anzahl gleichzeitig abgefragter Konten beim Abgleich | `10` |

## Inaktive Konten und Aufbewahrung

| Variable | Bedeutung | Standard |
| --- | --- | --- |
| `ACCOUNT_INACTIVE_AFTER_DAYS` | Nach so vielen Tagen ohne Anmeldung wird die Person per E-Mail gefragt, ob sie das Konto noch braucht, und das Konto zur Löschung vorgemerkt. Leer: abgeschaltet. | leer |
| `ACCOUNT_DELETION_GRACE_DAYS` | Frist nach der E-Mail. Wer sich in dieser Zeit anmeldet, behält das Konto; sonst wird es mit allen Daten gelöscht. | `30` |
| `ACTIVITY_LOG_RETENTION_DAYS` | Einträge im Aktivitätsprotokoll werden nach so vielen Tagen gelöscht. Leer: unbegrenzt aufbewahren. | leer |
| `ACTIVITY_LOG_LOGIN_RETENTION_DAYS` | Eigene, meist kürzere Frist für Anmelde-Einträge. Leer: wie `ACTIVITY_LOG_RETENTION_DAYS`. | leer |

::: tip Datenschutz
Legt die Fristen gemeinsam mit euren Datenschutzbeauftragten fest. Für Anmeldedaten sind wenige Wochen meist ausreichend, z. B. `ACTIVITY_LOG_LOGIN_RETENTION_DAYS=30`.
:::

## Sicherheit

| Variable | Bedeutung | Standard |
| --- | --- | --- |
| `CSP_ENABLED` | Content-Security-Policy-Header setzen. Nur zur Fehlersuche abschalten. | `true` |
