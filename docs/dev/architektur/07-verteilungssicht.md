# 7. Verteilungssicht

## Produktion

Die Referenzinstallation läuft auf einem einzelnen Server. Die Schritte dafür beschreibt das [Administrationshandbuch](/admin/installation).

```mermaid
flowchart TB
  internet((Internet)) -- "HTTPS :443" --> nginx

  subgraph server["Server (Debian 13)"]
    nginx[nginx] -- "FastCGI" --> fpm[PHP-FPM 8.4<br/>StuMV]
    cron[cron] -- "jede Minute<br/>schedule:run" --> cli[PHP CLI<br/>StuMV]
    fpm --> slapd[(slapd<br/>OpenLDAP 2.6)]
    cli --> slapd
    fpm --> db[(MariaDB oder<br/>PostgreSQL)]
    cli --> db
    fpm --> fs[/storage/<br/>Bilder, OAuth-Schlüssel/]
  end

  fpm -- "SMTP" --> smtp[Mailserver]
  fpm -- "HTTPS" --> mailman[Mailman 3]
  cli -- "HTTPS" --> mailman
  fpm -- "LDAP" --> uni[Uni-LDAP]
  others[andere Dienste] -- "LDAP + StartTLS<br/>(optional)" --> slapd
```

| Knoten | Hinweise |
| --- | --- |
| **nginx + PHP-FPM** | Dokumentenwurzel `public/`; `client_max_body_size` und PHP-Limits für Bild-Uploads anheben |
| **cron** | einziger Hintergrundprozess, kein Queue-Worker |
| **slapd** | lauscht lokal; für andere Dienste optional auf einer internen Adresse |
| **Datenbank** | lokal |
| **storage/** | `storage/app` (Uploads) und `storage/oauth-*.key` müssen gesichert werden |

Mehrere Anwendungsserver sind möglich, wenn Sitzungen in der Datenbank liegen (`SESSION_DRIVER=database`), `storage/` geteilt wird und der Scheduler nur auf einem Server läuft (`onOneServer()` ist bei den Aufgaben gesetzt, braucht dann aber einen gemeinsamen Cache).

## Entwicklung

```mermaid
flowchart LR
  dev[Entwickler:in] --> nginx
  subgraph compose[docker-compose.dev.yaml]
    nginx[nginx :8080] --> fpm[php-fpm]
    ws[workspace<br/>Node/Vite :5173]
    fpm --> ldap[(OpenLDAP :13389)]
    fpm --> maria[(MariaDB :13306)]
    fpm --> mm[Mailman Core :18001]
    mm --> pit[Mailpit :18025]
    pla[phpLDAPadmin :8081] --> ldap
    pma[phpMyAdmin :8082] --> maria
  end
  tests[Pest auf dem Host] --> ldap
  tests --> maria
```

Das OpenLDAP-Image wird aus `docker/openldap` gebaut: dieselbe Symas-Distribution und eine aus der Produktion abgeleitete `cn=config`. Die Testsuite und die CI nutzen dasselbe Image. Siehe [Entwicklungsumgebung](../entwicklungsumgebung).

## CI

```mermaid
flowchart LR
  push[git push] --> gh[GitHub Actions]
  gh --> matrix["testing: PHP 8.4 / 8.5 / 8.6<br/>× MariaDB / PostgreSQL"]
  gh --> lint[lint: Pint]
  gh --> analysis[analysis: PHPStan]
  gh --> rector[rector: Dry-Run]
  matrix --> ldapimg[OpenLDAP aus docker/openldap]
```
