# Entwicklungsumgebung

## Voraussetzungen

- PHP 8.4 mit den Erweiterungen `gd`, `intl`, `ldap`, `pdo_mysql` (für Tests gegen PostgreSQL zusätzlich `pdo_pgsql`)
- Composer 2, Node.js 22
- Podman oder Docker mit Compose
- Zugangsdaten für [Flux Pro](https://fluxui.dev/) (`livewire/flux-pro`)

## Projekt einrichten

```bash
git clone https://github.com/OpenAdministration/StuMV
cd StuMV
composer config http-basic.composer.fluxui.dev "<e-mail>" "<lizenzschlüssel>"
composer install
npm ci
```

## Container-Stack

`docker-compose.dev.yaml` beschreibt einen vollständigen Stack: OpenLDAP (dasselbe Symas-Build wie in Produktion), MariaDB, php-fpm, nginx, Mailman 3, Mailpit, phpLDAPadmin, phpMyAdmin und einen Workspace-Container mit Node. Die Konfiguration steht in `.env.docker` und enthält nur Wegwerf-Zugangsdaten.

```bash
# alles starten (baut die Images beim ersten Mal)
podman compose --env-file .env.docker -f docker-compose.dev.yaml up -d

# Logs verfolgen, stoppen
podman compose --env-file .env.docker -f docker-compose.dev.yaml logs -f php-fpm
podman compose --env-file .env.docker -f docker-compose.dev.yaml down

# artisan/composer/npm im Container
podman compose --env-file .env.docker -f docker-compose.dev.yaml exec workspace bash
```

Die Befehle funktionieren genauso mit `docker`. Die Zeilen `userns_mode: keep-id` braucht nur rootless Podman.

| Dienst | Adresse | Zugang |
| --- | --- | --- |
| Anwendung | http://localhost:8080 | siehe [Demo-Zugänge](#demo-zugange) |
| phpLDAPadmin | http://localhost:8081 | `stumv` / `stumv-not-production` |
| phpMyAdmin | http://localhost:8082 | Server `mariadb`, `stumv` / `local_stumv_password` |
| Vite-Dev-Server | http://localhost:5173 | `npm run dev` im Workspace |
| Mailman REST-API | http://localhost:18001/3.1 | `restadmin` / `mailman-not-production` |
| Mailpit | http://localhost:18025 | fängt alle E-Mails ab |
| OpenLDAP | `ldap://localhost:13389` | `uid=stumv,ou=Services,dc=stumv,dc=de` / `stumv-not-production` |
| MariaDB | `localhost:13306` | Datenbank `stumv`, `stumv` / `local_stumv_password` |

Beim Start migriert der php-fpm-Container die Datenbank und legt die Datenbankeinträge der Demo-Konten an (`DemoUsersSeeder`, `DemoMailmanSeeder`).

### Das LDAP zurücksetzen

Das Verzeichnis wird beim Bau des Images aus `docker/openldap/bootstrap/` befüllt und hat kein Volume. Ein neu erstellter Container beginnt deshalb immer im selben, sauberen Zustand:

```bash
podman compose --env-file .env.docker -f docker-compose.dev.yaml down ldap
podman compose --env-file .env.docker -f docker-compose.dev.yaml up -d --build ldap
```

::: warning Tests verändern das LDAP
Die Testsuite verwendet dasselbe LDAP (Port 13389). Tests, die global abgleichen, leeren dabei z. B. die Mitglieder der Demo-Rollen. Setze das LDAP nach Testläufen zurück, wenn du mit den Demo-Daten arbeiten willst. Siehe [Risiken](./architektur/11-risiken-und-technische-schulden).
:::

## Demo-Zugänge

`20-demo.ldif` legt die Studierendenschaft `demo` an. Alle Konten haben das Passwort `Demo-password1`:

| Anmeldename | Name | Rolle in der Demo |
| --- | --- | --- |
| `demo-hhv` | Lena Haushalt | Haushaltsverantwortliche, Moderatorin |
| `demo-kv` | Jonas Kasse | Kassenverantwortlicher, Moderator |
| `demo-revision` | Mira Revision | Revision |
| `demo-stura` | Tom Studierendenrat | Mitglied im Studierendenrat, Administrator |
| `demo-fsr` | Paul Fachschaft | Mitglied im Fachschaftsrat |
| `demo-studi` | Emma Studentin | ohne Rolle |

Ein Superadmin-Konto (`admin` / `admin-not-production`) legt `10-sample.ldif` in der Studierendenschaft `admin` an. Angemeldet wird unter `/demo/login` bzw. `/admin/login`.

## Ohne Container für die Anwendung

Du kannst die Anwendung auch direkt auf dem Host starten und nur LDAP und Datenbank aus dem Stack nutzen. Setze dazu in der `.env` die Ports des Stacks (`DB_PORT=13306`, `LDAP_PORT=13389`, `LDAP_TLS_VERIFY=false`) und starte:

```bash
php artisan serve
npm run dev
```

## Mailman

Der Stack enthält Mailman 3 Core. `mailman-seed` legt die Liste `finanzen@lists.demo.stumv.de` mit einem veralteten Mitglied an, `DemoMailmanSeeder` ordnet ihr die Demo-Gruppe `ref-finanzen` zu. Die Daten von Mailman sind flüchtig.

```bash
podman compose --env-file .env.docker -f docker-compose.dev.yaml exec php-fpm php artisan mailman:sync-lists demo
```

## Dokumentation bearbeiten

```bash
npm run docs:dev      # Vorschau mit Live-Reload
npm run docs:build    # prüft auch tote Links
```

Wie die Screenshots im Benutzerhandbuch entstehen, beschreibt `docs/screenshots/README.md`.
