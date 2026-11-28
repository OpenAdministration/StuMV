# 2. Randbedingungen

## Technische Randbedingungen

| Randbedingung | Erläuterung |
| --- | --- |
| **PHP 8.4+, Laravel 13** | Grundlage der Anwendung |
| **OpenLDAP 2.6** | Verzeichnis für Personen und Strukturen. Benötigt die Overlays `dynlist` (erzeugt `memberOf`) und `ppolicy`, das Passwort-Modul `argon2` sowie `olcLastBind`. Die Referenzkonfiguration steht in `docker/openldap/config.ldif`. |
| **MariaDB/MySQL oder PostgreSQL** | relationale Daten. Die Testsuite läuft gegen MariaDB und PostgreSQL. |
| **Livewire 4 und Flux Pro** | Oberfläche. Flux Pro ist eine **kommerzielle** Komponentenbibliothek; Installation und CI brauchen einen Lizenzschlüssel. |
| **Kein Queue-Worker** | Der Betrieb soll einfach bleiben: Jobs laufen synchron oder nach der Antwort (`QUEUE_CONNECTION=sync`). |
| **Cron** | Der Laravel-Scheduler übernimmt den nächtlichen Abgleich und Aufräumarbeiten und muss jede Minute laufen. |
| **Standards** | OpenID Connect Core, Discovery, RP-Initiated und Back-Channel Logout; OAuth 2.0 mit PKCE, Token Introspection (RFC 7662), Token Revocation (RFC 7009) |

## Organisatorische Randbedingungen

| Randbedingung | Erläuterung |
| --- | --- |
| **Lizenz** | GNU AGPL v3. Wer StuMV verändert als Dienst anbietet, muss den Quellcode bereitstellen (Link im Footer: `SOURCE_CODE_URL`). |
| **Zielgruppe Ehrenamt** | Nutzer:innen wechseln häufig (Amtszeiten von etwa einem Jahr); die Oberfläche muss ohne Schulung verständlich sein. |
| **Mehrmandantenbetrieb** | Eine Installation betreut viele Studierendenschaften; Betreiber:innen und Studierendenschaften sind verschiedene Organisationen. |
| **Datenschutz (DSGVO)** | Löschfristen für inaktive Konten und Protokolle müssen einstellbar sein. |

## Konventionen

- Oberflächensprache Deutsch (Duzen, Gendern mit Doppelpunkt), zusätzlich Englisch; Code, Kommentare und Commit-Messages auf Englisch
- Code-Stil: Laravel Pint, automatische Refactorings mit Rector, statische Analyse mit Larastan
- Tests mit Pest, Feature-Tests gegen echtes LDAP und echte Datenbank

Siehe auch [Beitragen](../beitragen).
