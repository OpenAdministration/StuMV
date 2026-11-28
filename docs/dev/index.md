# Entwicklerhandbuch

Dieses Handbuch richtet sich an alle, die an StuMV mitentwickeln oder eine Anwendung an StuMV anbinden. Wie StuMV bedient wird, steht im [Benutzerhandbuch](/user/einfuehrung/was-ist-stumv), wie es betrieben wird im [Administrationshandbuch](/admin/).

## Einstieg

| Seite | Inhalt |
| --- | --- |
| [Entwicklungsumgebung](./entwicklungsumgebung) | lokalen Stack mit Containern starten, Demo-Zugänge |
| [Tests](./tests) | Testsuite ausführen und schreiben, parallele Läufe, PostgreSQL |
| [Beitragen](./beitragen) | Code-Qualität, Konventionen, CI, Dokumentation |

## Architektur

Die Architekturdokumentation folgt der Gliederung von [arc42](https://arc42.org/overview):

1. [Einführung und Ziele](./architektur/01-einfuehrung-und-ziele)
2. [Randbedingungen](./architektur/02-randbedingungen)
3. [Kontextabgrenzung](./architektur/03-kontextabgrenzung)
4. [Lösungsstrategie](./architektur/04-loesungsstrategie)
5. [Bausteinsicht](./architektur/05-bausteinsicht)
6. [Laufzeitsicht](./architektur/06-laufzeitsicht)
7. [Verteilungssicht](./architektur/07-verteilungssicht)
8. [Querschnittliche Konzepte](./architektur/08-querschnittliche-konzepte)
9. [Architekturentscheidungen](./architektur/09-architekturentscheidungen)
10. [Qualitätsanforderungen](./architektur/10-qualitaetsanforderungen)
11. [Risiken und technische Schulden](./architektur/11-risiken-und-technische-schulden)
12. [Glossar](./architektur/12-glossar)

## Schnittstellen

- [Verzeichnis-API](./api/) – lesender Zugriff für angebundene Anwendungen (OAuth 2.0 Client Credentials)
- OpenID Connect – siehe [OIDC-Clients](/user/administration/oidc-clients) und [Querschnittliche Konzepte](./architektur/08-querschnittliche-konzepte#openid-connect-provider)

## Das Repository auf einen Blick

| Pfad | Inhalt |
| --- | --- |
| `app/Livewire/` | die Oberfläche: eine Livewire-Komponente pro Seite, nach Bereich gruppiert |
| `app/Ldap/` | LDAP-Modelle (LdapRecord): Studierendenschaften, Personen, Gremien, Rollen, Gruppen, Domains |
| `app/Models/` | Eloquent-Modelle: Mitgliedschaften, Clients, Identity Provider, Protokoll, Einstellungen |
| `app/Policies/`, `app/Http/Middleware/` | Berechtigungen |
| `app/Support/` | fachliche Dienste, z. B. LDAP-Abgleich, Mailman, PDF, Protokoll |
| `app/Services/Oidc/` | Anpassungen am OpenID-Connect-Provider |
| `app/Console/Commands/` | Artisan-Befehle für Abgleich, Wartung und Migration |
| `routes/` | `web.php` (Oberfläche, OIDC), `api.php` (Verzeichnis-API), `api-legacy.php` |
| `resources/views/` | Blade-Views der Livewire-Komponenten |
| `lang/de`, `lang/en` | Oberflächentexte |
| `docker/` | Container für den Entwicklungsstack, u. a. das produktionsnahe OpenLDAP |
| `tests/` | Pest-Tests |
| `docs/` | diese Dokumentation (VitePress) |
