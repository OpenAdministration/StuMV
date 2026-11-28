# Tests

Die Tests sind mit [Pest](https://pestphp.com/) geschrieben. `tests/Feature` enthält die Feature-Tests, die die ganze Anwendung booten und gegen das echte OpenLDAP und eine echte Datenbank laufen. `tests/Unit` enthält wenige reine Unit-Tests ohne Framework.

## Ausführen

::: danger Tests auf dem Host ausführen
Führe die Tests auf dem Host aus, **nicht** in einem Container des Stacks. Dort überschreibt `.env.docker` die `.env.testing`, und `RefreshDatabase` leert dann die Entwicklungsdatenbank.
:::

Die Tests brauchen LDAP und MariaDB aus dem [Container-Stack](./entwicklungsumgebung#container-stack). `.env.testing` zeigt auf deren Ports und auf die eigene Datenbank `stumv_testing`.

| Befehl | Wirkung |
| --- | --- |
| `composer test` | alle Tests in einem Prozess |
| `composer test:parallel` | parallel, danach die Gruppe `ldap-global` seriell |
| `./vendor/bin/pest tests/Feature/Console` | ein Verzeichnis |
| `./vendor/bin/pest --filter="role members"` | nach Namen filtern |

### Parallel

Parallel bekommt jeder Prozess eine eigene Datenbank `stumv_testing_test_<n>`. Der Testbenutzer muss sie anlegen dürfen; einmalig im Stack:

```bash
podman compose --env-file .env.docker -f docker-compose.dev.yaml exec mariadb \
  mariadb -uroot -e "GRANT ALL PRIVILEGES ON \`stumv\\_testing%\`.* TO 'stumv'@'%'"
```

### Gegen PostgreSQL

Die CI testet gegen MariaDB **und** PostgreSQL. Lokal überschreibst du dafür die Verbindung:

```bash
podman run --rm -d --name stumv-pgtest -p 127.0.0.1:15432:5432 \
  -e POSTGRES_USER=stumv -e POSTGRES_PASSWORD=local_stumv_password -e POSTGRES_DB=stumv_testing \
  docker.io/library/postgres:17

DB_CONNECTION=pgsql DB_PORT=15432 composer test:parallel
```

Umgebungsvariablen haben Vorrang vor `.env.testing`.

## Tests schreiben

### Testdaten im LDAP

Berechtigungen hängen an Gruppenmitgliedschaften und am Ort eines Kontos im LDAP. `tests/Support/TestLdap.php` und die Helfer in `tests/Pest.php` bauen deshalb echte LDAP-Strukturen auf und räumen sie nach jedem Test wieder ab:

```php
test('a moderator can add a role', function (): void {
    $community = newCommunity();                         // Studierendenschaft mit Grundstruktur
    $committee = TestLdap::makeCommittee($community, 'fsr');
    actingAsModerator($community);                       // LDAP-Konto + Datenbankeintrag, angemeldet

    Livewire::test(NewRole::class, ['realm' => $community, 'ou' => 'fsr'])
        ->set('cn', 'kasse')
        ->set('description', 'Kassenverantwortung')
        ->call('save')
        ->assertHasNoErrors();
});
```

| Helfer | Legt an |
| --- | --- |
| `newCommunity()` | Studierendenschaft mit zufälligem Kürzel und allen OUs/Gruppen |
| `actingAsMember/Moderator/Admin($community)` | Konto mit entsprechender Zuständigkeit, meldet es an |
| `actingAsSuperAdmin()` | Konto in der Studierendenschaft `admin` |
| `TestLdap::makeCommittee()`, `makeRole()`, `makeGroup()`, `makeUser()` | einzelne Einträge |
| `TestLdap::track($entry)` | räumt einen Eintrag ab, den der getestete Code selbst angelegt hat |

Alle Feature-Tests laufen mit `RefreshDatabase` (zentral in `tests/Pest.php` gesetzt), Änderungen an der Datenbank werden also nach jedem Test zurückgerollt.

### Die Gruppe `ldap-global`

Parallel laufende Tests sehen die LDAP-Testdaten der anderen Prozesse. Ein Test, der **über alle Studierendenschaften** iteriert oder außerhalb seiner eigenen Testdaten schreibt – etwa ein globales `ldap:sync-groups` –, würde andere Tests stören oder selbst sporadisch scheitern. Markiere solche Testdateien:

```php
// works on every realm in the directory, so it can't run alongside other tests
pest()->group('ldap-global');
```

### Datenbankunabhängig bleiben

Tests laufen gegen MariaDB und PostgreSQL. Vermeide treiberspezifisches SQL. Wo es nicht anders geht, unterscheide nach `DB::getDriverName()`. Für Suchen ohne Beachtung der Groß-/Kleinschreibung nimm `whereLike()`, denn `LIKE` ist in PostgreSQL case-sensitiv.

## Continuous Integration

`.github/workflows/testing.yml` testet bei jedem Push gegen PHP 8.4, 8.5 und 8.6, jeweils mit MariaDB und PostgreSQL. Das LDAP wird dort aus `docker/openldap` gebaut, also mit derselben Konfiguration wie lokal.
