# 11. Risiken und technische Schulden

Sortiert nach geschätzter Bedeutung.

## Risiken

| # | Risiko | Auswirkung | Maßnahme / Stand |
| :-: | --- | --- | --- |
| R1 | **Abhängigkeit von Flux Pro** (kommerziell) | Ohne gültige Lizenz keine Installation und keine CI | Lizenz zentral verwalten; Komponenten nicht unnötig an Pro-Funktionen binden |
| R2 | **Kopplung an die OpenLDAP-Konfiguration** (`dynlist` für `memberOf`, Zugriffsregeln, `ppolicy`) | Abweichende Server-Konfigurationen führen zu fehlenden Rechten oder fehlgeschlagenen Schreibzugriffen | Referenzkonfiguration in `docker/openldap/config.ldif`, Tests gegen dieses Image, Anleitung im [Administrationshandbuch](/admin/openldap) |
| R3 | **Konsistenz zwischen Datenbank und LDAP** (z. B. beim Umbenennen oder Verschieben von Rollen und Gremien, deren DNs in der Datenbank stehen) | Verwaiste Mitgliedschaften oder Zuordnungen | eigene Befehle für Umbenennen und Verschieben (`app:rename-role`, `app:move-committee`, `app:move-role-to-committee`) |
| R4 | **`Community::adminsGroup()` ist nicht nullable**, eine unvollständige Studierendenschaft (fehlende `cn=admins`) führt zu einem `TypeError` | Die Liste der Studierendenschaften bricht ab | robuster machen oder Grundstruktur beim Laden prüfen |

## Technische Schulden

| # | Schuld | Folge | Vorschlag |
| :-: | --- | --- | --- |
| T1 | **Tests und Entwicklungsstack teilen sich das LDAP** (Port 13389). Globale Tests leeren z. B. die Mitglieder der Demo-Rollen; `TestLdap::makeUser($uid)` löscht ein gleichnamiges vorhandenes Konto. | Die Demo-Daten im Dev-Stack sind nach Testläufen beschädigt. | eigener LDAP-Container für Tests (wie in der CI) |
| T2 | **Legacy-API** unter `/api-legacy` (`my/committees`, `my/groups`, `user`) mit Benutzer-Tokens, nicht in der OpenAPI-Spezifikation | Zweite, undokumentierte Schnittstelle | Nutzer ermitteln, auf die Verzeichnis-API bzw. OIDC-Claims migrieren, dann entfernen |
| T3 | **Alte Eloquent-Modelle** `App\Models\Committee`, `Role`, `Group`, `Domain`, `Realm` und die Tabellen aus 2022 werden nicht mehr verwendet; dazu `legacy:import:*`-Befehle | Verwirrung zwischen gleichnamigen LDAP- und Datenbankmodellen | entfernen, sobald keine Installation mehr importieren muss |
| T4 | **PHPStan auf Level 8** (Level 9 meldet derzeit 517 weitere Fehler, überwiegend `mixed` aus Konfiguration, Requests und LDAP-Attributen) | Typfehler bei `mixed`-Werten bleiben unentdeckt | Level schrittweise anheben, Fehler an der Ursache beheben |
| T5 | **Studierendenschaft als String ohne Fremdschlüssel** in den meisten Tabellen | Aufräumen beim Löschen muss jede Tabelle kennen (`RealmDataPurger`) | neue Tabellen dort eintragen; Tests für das Löschen einer Studierendenschaft |
| T6 | **Passwort-Hashing in Tests**: Argon2 mit Produktionswerten kostet ~0,3 s je Hash | Lange Testläufe | Hash-Werte für Tests per Konfiguration senken |
