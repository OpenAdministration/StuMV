# Administrationshandbuch

Dieses Handbuch richtet sich an alle, die StuMV **betreiben**: installieren, konfigurieren, aktualisieren und sichern. Wie die Anwendung bedient wird – auch von Superadmins –, steht im [Benutzerhandbuch](/user/einfuehrung/was-ist-stumv).

Die Anleitungen beziehen sich auf **Debian 13 (Trixie)**. Auf anderen Distributionen funktioniert es genauso; nur Paketnamen und Pfade weichen ab.

## Aufbau

StuMV ist eine Laravel-Anwendung, die ihre Daten auf zwei Speicher verteilt:

```
                ┌──────────────────────────────┐
 Browser ──────▶│ nginx ─▶ PHP-FPM (StuMV)     │──▶ SMTP-Server
 OIDC-Clients ─▶│                              │──▶ Mailman 3 (optional)
 API-Clients ──▶│                              │──▶ Hochschul-LDAP (optional)
                └──────┬───────────────┬───────┘
                       │               │
                 ┌─────▼─────┐   ┌─────▼──────┐
                 │ OpenLDAP  │   │  MariaDB   │
                 └───────────┘   └────────────┘
```

| Speicher | Inhalt |
| --- | --- |
| **OpenLDAP** | Personen und Passwörter, Studierendenschaften, Gremien, Rollen, Rechtegruppen, Domains. Andere Dienste können das Verzeichnis direkt lesen. |
| **MariaDB** | Rollenmitgliedschaften mit Zeiträumen, Sitzungen, OIDC- und API-Clients, Identity Provider, Einladungen, Aktivitätsprotokoll, Export-Vorlagen |
| **Dateisystem** | Profilbilder, Logos, Hintergrundbilder, Logos von OIDC-Clients, Schlüssel für OIDC/OAuth |

Was in der Datenbank steht, wird regelmäßig ins LDAP übertragen (siehe [Automatische Aufgaben](./wartung#automatische-aufgaben)). Deshalb braucht StuMV einen **Cronjob**.

## Voraussetzungen

| Komponente | Version |
| --- | --- |
| PHP | **8.4** oder neuer, mit den Erweiterungen `gd`, `intl`, `ldap`, `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip` |
| Datenbank | MariaDB, MySQL oder [PostgreSQL](./konfiguration#datenbank) |
| OpenLDAP | **2.6** mit den Modulen `mdb`, `ppolicy`, `dynlist` und `argon2` |
| Webserver | nginx oder Apache mit PHP-FPM |
| Composer | 2.x |
| Node.js | zum Bauen der Frontend-Assets |
| Lizenz | ein Lizenzschlüssel für [Flux Pro](https://fluxui.dev/) (UI-Bibliothek) |


## Weiter

1. [Installation auf Debian](./installation)
2. [OpenLDAP einrichten](./openldap)
3. [Konfiguration](./konfiguration)
4. [Ersteinrichtung](./ersteinrichtung) – erster Superadmin und erste Studierendenschaft
5. [Wartung & Updates](./wartung)

Bestehende v1-Installationen aktualisierst du mit der Anleitung [Upgrade von v1](./upgrade-v1).
