# Fehlerbehebung

Die erste Anlaufstelle ist das Log der Anwendung:

```bash
tail -f /var/www/stumv/storage/logs/laravel.log
```

Weitere Logs: `/var/log/nginx/error.log`, `journalctl -u php8.4-fpm` und `journalctl -u slapd`.

## Verbindung zum LDAP schlägt fehl

**Symptom:** Fehlerseite oder `Can't contact LDAP server` im Log.

- Läuft slapd? `systemctl status slapd`
- Funktioniert die Anmeldung des Service-Kontos auf der Kommandozeile? Siehe [Verbindung testen](./openldap#_6-verbindung-testen).
- StuMV nutzt **immer StartTLS**. Prüfe, ob `LDAP_TLS_CACERT` auf das richtige Zertifikat zeigt und der Hostname in `LDAP_HOST` im Zertifikat steht (bei einem selbst signierten Zertifikat für `127.0.0.1` also `LDAP_HOST=127.0.0.1`).
- Das Zertifikat muss für `www-data` lesbar sein.

## Anmeldung funktioniert nicht

- Ist die E-Mail-Adresse bestätigt? Bei Konten, die direkt im LDAP angelegt wurden, mit `app:import-ldap-users <kürzel> --verified` nachholen.
- Ist das Konto gesperrt? Administrator:innen sehen das im Profil unter **Konto**.
- Zu viele Fehlversuche sperren die Anmeldung für kurze Zeit; die Meldung nennt die Wartezeit.

## Hochladen von Bildern schlägt fehl

**Symptom:** Fehler `413 Request Entity Too Large` oder das Bild wird stillschweigend nicht gespeichert.

`client_max_body_size` in nginx sowie `upload_max_filesize` und `post_max_size` in PHP müssen groß genug sein – siehe [PHP anpassen](./installation#_2-php-anpassen).

## Hochgeladene Bilder werden nicht angezeigt

Der symbolische Link `public/storage` fehlt:

```bash
sudo -u www-data php artisan storage:link
```

## OIDC-Login bricht ab

- **`Unable to authenticate the request due to a server configuration issue`**: Die OAuth-Schlüssel fehlen oder sind nicht lesbar. `sudo -u www-data php artisan passport:keys` ausführen und prüfen, dass `storage/oauth-*.key` `www-data` gehören.
- **Redirect-URI wird abgelehnt**: Die Adresse muss exakt mit einer im [OIDC-Client](/user/administration/oidc-clients) hinterlegten übereinstimmen – inklusive Schrägstrich am Ende und `http`/`https`.
- **Anwendung findet die Konfiguration nicht**: Ist `https://stumv.example.org/<kürzel>/.well-known/openid-configuration` erreichbar? Achte darauf, dass `APP_URL` mit der öffentlichen Adresse übereinstimmt und der Webserver `.well-known` nicht blockiert.

## Änderungen kommen nicht in anderen Anwendungen an

Rollen und Rechtegruppen werden nachts übertragen. Läuft der Cronjob?

```bash
sudo -u www-data php artisan schedule:list
grep CRON /var/log/syslog | tail
```

Sofort übertragen geht über das Menü **Synchronisieren** in der Oberfläche oder mit `php artisan ldap:sync-roles` und `ldap:sync-groups`.

## Mailinglisten werden nicht synchronisiert

- Ist `MAILMAN_API_URL` gesetzt und vom Server aus erreichbar? `curl -u restadmin:… $MAILMAN_API_URL/system/versions`
- Ist die Liste in StuMV deaktiviert?
- Fehlermeldungen von Mailman stehen im Log der Anwendung.

## Oberfläche reagiert nicht oder sieht kaputt aus

- Wurden nach einem Update die Assets neu gebaut (`npm run build`) und die Caches erneuert? Siehe [Updates](./wartung#updates).
- Zeigt die Browser-Konsole Meldungen zur Content-Security-Policy, kann `CSP_ENABLED=false` testweise klären, ob die CSP die Ursache ist. Danach wieder einschalten.

## Wartungsmodus hängt

Ist ein Update abgebrochen, bleibt StuMV im Wartungsmodus:

```bash
sudo -u www-data php artisan up
```
