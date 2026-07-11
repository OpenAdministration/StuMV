# Studentische MitgliederVerwaltung (StuMV)

Mit StuMV können die Daten der Mitglieder von Organisationen wie beispielsweise Studierendenschaften oder Vereinen verwaltet werden. Mitglieder können zu Rollen in Gruppen bzw. Gremien, denen sie angehören, zugeordnet werden. Dabei wird aufgezeichnet, wer von wann bis wann in welcher Rolle aktiv war. Mit den Mitgliedschaften in Rollen lassen sich außerdem Berechtigungen für weitere IT-Dienste verknüpfen.

StuMV kann als Identity Provider für andere Anwendungen dienen und unterstützt dabei LDAP und OpenID Connect.

Weitere Informationen: [www.stufis.de/stumv](https://www.stufis.de/stumv)

## Installation

Zunächst müssen [OpenLDAP](https://www.openldap.org/) und eine [MariaDB](https://mariadb.org/)- oder [MySQL](https://www.mysql.com/)-Datenbank eingerichtet werden.

[Infos zur Einrichtung von OpenLDAP](https://github.com/OpenAdministration/StuMV/blob/main/docs/install.md)

```
git clone https://github.com/OpenAdministration/StuMV
composer install
npm install
npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Zur Aktivierung von [Flux Pro](https://fluxui.dev/) ist die Eingabe eines Lizenzschlüssels nötig.

```
php artisan flux:activate
```

Nun müssen in der Datei `.env` noch ein paar Einstellungen wie App-Einstellungen, die Zugangsdaten für die Datenbank und E-Mail-Versand gesetzt werden.

## Contributing

### Requirements

- **PHP 8.4** 
- **Node 22**
- Access to **[Flux Pro](https://fluxui.dev/)** (`livewire/flux-pro`) — a licensed package

### Local development with Docker

A ready-to-run stack (OpenLDAP matching production + MariaDB + php-fpm + nginx +
phpLDAPadmin + a node/vite workspace) is defined in `docker-compose.dev.yaml`.
Configuration lives in `.env.docker` (throwaway development credentials; the app talks
to the in-network `ldap`/`mariadb` services). It works with Docker too — the
`userns_mode: keep-id` lines are only needed for rootless podman.

All commands below use `podman`; swap in `docker` if that's what you run.

#### Spinning the stack up

```bash
# start everything in the background (builds images the first time)
podman compose --env-file .env.docker -f docker-compose.dev.yaml up -d

# force a rebuild of the images (e.g. after editing a Dockerfile)
podman compose --env-file .env.docker -f docker-compose.dev.yaml up -d --build

# start only some services (dependencies are started automatically)
podman compose --env-file .env.docker -f docker-compose.dev.yaml up -d nginx
podman compose --env-file .env.docker -f docker-compose.dev.yaml up -d ldap phpldapadmin

# follow logs (all services, or a single one)
podman compose --env-file .env.docker -f docker-compose.dev.yaml logs -f
podman compose --env-file .env.docker -f docker-compose.dev.yaml logs -f php-fpm

# run artisan/composer/npm inside the toolbox container
podman compose --env-file .env.docker -f docker-compose.dev.yaml exec workspace bash
``` 

```bash
# start everything in the background
podman compose --env-file .env.docker -f docker-compose.dev.yaml up -d

# follow logs / stop / remove
podman compose --env-file .env.docker -f docker-compose.dev.yaml logs -f
podman compose --env-file .env.docker -f docker-compose.dev.yaml down

# run artisan/composer/npm inside the toolbox container
podman compose --env-file .env.docker -f docker-compose.dev.yaml exec workspace bash
```

Once up, these are exposed on the host:

| Service                      | URL / port              | Notes                                                    |
| ---------------------------- | ----------------------- | -------------------------------------------------------- |
| App (nginx → php-fpm)        | http://localhost:8080   |                                                          |
| phpLDAPadmin (LDAP web UI)   | http://localhost:8081   | Log in with the service account — user id `stumv` / `stumv-not-production` |
| Vite dev server (workspace)  | http://localhost:5173   | run `npm run dev` inside the workspace container         |
| OpenLDAP                     | `ldap://localhost:13389`| bind `uid=stumv,ou=Services,dc=stumv,dc=de` / `stumv-not-production` |
| MariaDB                      | `localhost:13306`       | database `stumv`, user `stumv` / `local_stumv_password`  |

The LDAP directory is seeded from `docker/openldap/bootstrap/` and is ephemeral — every
`up` starts from the same clean, production-like state.

#### Demo logins

`docker/openldap/bootstrap/20-demo.ldif` seeds a **`demo` community** mirroring the public
[StuFiS demo](https://stufis.de/demo-login) — six accounts covering the ThürStudFVO
separation of financial responsibilities. All share the password **`Demo-password1`**:

| Username        | Role                                        |
| --------------- | ------------------------------------------- |
| `demo-hhv`      | Haushaltsverantwortliche (Budget Manager)   |
| `demo-kv`       | Kassenverantwortliche (Cashier)             |
| `demo-revision` | Revision (Internal Auditor)                 |
| `demo-stura`    | Studierendenrat (Student Council Member)    |
| `demo-fsr`      | Fachschaftsrat (Department Council Member)  |
| `demo-studi`    | Studentin (Guest/Student)                   |

For a **super-admin** login (member of `cn=super-admins`, full access across all
communities), use the seeded `admin` account — username `admin`, password
`admin-not-production` (from `10-sample.ldif`).

### Code quality

Composer scripts wrap the tooling. Run **`composer fix` before committing.**

| Command               | What it does                                            |
| --------------------- | ------------------------------------------------------- |
| `composer pint`       | Fix code style (Laravel Pint)                           |
| `composer lint`       | Static analysis (PHPStan / Larastan)                    |
| `composer rector`     | Apply Rector refactorings                               |
| `composer rector-dry` | Preview Rector changes without writing                  |
| `composer fix`        | Run Rector, then Pint (the pre-commit shortcut)         |

### Tests

The suite runs against a **real dockerized OpenLDAP** (see `docker/openldap/`) and a
MariaDB test database, configured in `.env.testing` (LDAP on `:13389`, database
`stumv_testing` on `:13306`). Bring up the Docker stack above (it publishes both on those
ports; create the `stumv_testing` database once), then:

```bash
./vendor/bin/phpunit
```

The LDAP registration/login flow is covered end-to-end by
`tests/Feature/Auth/LdapAuthenticationTest`. Some older tests are quarantined
(`markTestSkipped`, grep `Quarantined:`) pending a rewrite for the current UI.

### Continuous Integration

Every push and pull request runs four workflows in `.github/workflows/` (PHP 8.4):

- **testing** — PHPUnit against a dockerized OpenLDAP + MariaDB service
- **lint** — Pint (`--test`)
- **analysis** — PHPStan / Larastan
- **rector** — Rector dry-run

CI requires a repository secret **`COMPOSER_AUTH`** holding the Flux Pro credentials so
`composer install` can fetch `livewire/flux-pro`:

```json
{"http-basic":{"composer.fluxui.dev":{"username":"<email>","password":"<license-key>"}}}
```

## Security

Please write a mail to service@open-administration.de for a responsible disclosure procedure.
