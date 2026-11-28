# 9. Architekturentscheidungen

Die Entscheidungen sind im Nachhinein aus Code, Kommentaren und Versionsgeschichte rekonstruiert. Neue Entscheidungen sollten hier in derselben Form ergänzt werden.

## ADR 1: Mitgliedschaften in der Datenbank, LDAP als Projektion

**Kontext:** Funktionen sind zeitlich begrenzt, beginnen oft in der Zukunft und müssen nachträglich belegbar sein (Bescheinigungen). LDAP-Gruppen kennen nur „Mitglied oder nicht“.

**Entscheidung:** Rollenmitgliedschaften mit Zeitraum, Beschlussdatum und Kommentar liegen in der Datenbank (`role_user_relation`), ebenso die Zuordnung von Rechtegruppen zu Rollen (`role_group_relation`). Die Mitglieder der LDAP-Rollen und -Gruppen werden daraus abgeleitet – sofort bei jeder Änderung und nachts vollständig.

**Konsequenzen:**
- Historie und zukünftige Mitgliedschaften sind möglich; andere Dienste sehen im LDAP trotzdem einen einfachen, aktuellen Stand.
- Direkte Änderungen an Rollen- oder Gruppenmitgliedern im LDAP gehen beim nächsten Abgleich verloren.
- Die Struktur (Gremien, Rollen, Gruppen) liegt weiterhin im LDAP; Datenbank und LDAP müssen konsistent gehalten werden (z. B. beim Umbenennen einer Rolle).

## ADR 2: Konten je Studierendenschaft

**Kontext:** In v1 lagen alle Konten in einem gemeinsamen Zweig; die Zugehörigkeit ergab sich aus einer Gruppe `cn=members`. Eine Person in zwei Studierendenschaften hatte *ein* gemeinsames Konto.

**Entscheidung:** Jede Studierendenschaft hat ihren eigenen Zweig `ou=People`. Wer in mehreren Studierendenschaften ist, hat dort jeweils ein **unabhängiges** Konto. Die Anmeldung ist an die Studierendenschaft gebunden (`/{realm}/login`, `ScopedToRealmPeople`).

**Konsequenzen:**
- Klare Mandantentrennung: Administrator:innen einer Studierendenschaft können nur deren Konten verwalten.
- Personen mit mehreren Zugehörigkeiten haben mehrere Passwörter und Profile.
- Anmeldenamen sind nur innerhalb einer Studierendenschaft eindeutig; Code darf Konten nie allein über den Anmeldenamen suchen (`(username, realm)` ist der Schlüssel).
- Der Umstieg von v1 erfordert eine einmalige Migration (`app:split-people-by-realm`, siehe [Upgrade von v1](/admin/upgrade-v1)).

## ADR 3: Superadmins als eigene Studierendenschaft

**Kontext:** v1 kannte eine globale Gruppe `cn=super-admins`.

**Entscheidung:** Superadmins sind Konten in der Studierendenschaft `admin`, die nur aus `ou=People` besteht. `CommunityPolicy` gibt ihnen in allen anderen Studierendenschaften die Rechte von Moderator:innen und Administrator:innen.

**Konsequenzen:** Dieselben Mechanismen (Login je Studierendenschaft, Kontoverwaltung) gelten auch für Superadmins. Seiten, die in `admin` keinen Sinn ergeben, müssen mit `denyAdminRealm` gesperrt werden.

## ADR 4: OIDC-Endpunkte je Studierendenschaft

**Kontext:** Passport registriert globale Endpunkte unter `/oauth/…`. Ein Client einer Studierendenschaft hätte damit Konten jeder Studierendenschaft anmelden können.

**Entscheidung:** Die Passport-Routen sind abgeschaltet und unter `/{realm}/oauth/…` neu registriert, mit eigener Discovery je Studierendenschaft. Jeder Client ist über `community_uid` an genau eine Studierendenschaft gebunden.

**Konsequenzen:** Angebundene Anwendungen konfigurieren eine Discovery-URL je Studierendenschaft. Erweiterungen am OIDC-Provider müssen die Realm-Bindung beachten (siehe Middleware `EnsureOidcClientMatchesRealm`).

## ADR 5: Livewire und Flux statt Single-Page-App

**Entscheidung:** Die Oberfläche besteht aus serverseitig gerenderten Livewire-Komponenten mit der Komponentenbibliothek Flux (Pro); eine separate Frontend-Anwendung mit eigener API gibt es nicht.

**Konsequenzen:**
- Fachlogik und Berechtigungen bleiben auf dem Server; wenig JavaScript.
- Abhängigkeit von einer kommerziellen Bibliothek (Lizenz für Installation und CI).
- Livewire-Aktionen sind eigene Requests; Berechtigungen müssen dort erneut geprüft werden.

## ADR 6: Kein Queue-Worker

**Kontext:** Studierendenschaften und kleine Betreiber sollen StuMV mit möglichst wenig Infrastruktur betreiben können.

**Entscheidung:** `QUEUE_CONNECTION=sync`. LDAP-Änderungen erfolgen synchron im Request; langsame Schritte wie der Mailman-Abgleich laufen nach dem Senden der Antwort (`app()->terminating`). Alles Zeitgesteuerte übernimmt der Scheduler über Cron.

**Konsequenzen:** Einfacher Betrieb, aber kein automatisches Wiederholen fehlgeschlagener Jobs; dafür sorgen die idempotenten nächtlichen Abgleiche. Lange laufende Aufgaben gehören in Artisan-Befehle, nicht in Requests.

## ADR 7: Tests gegen echtes OpenLDAP

**Entscheidung:** Feature-Tests laufen gegen ein OpenLDAP mit der Konfiguration der Produktion (`docker/openldap`), nicht gegen einen Mock.

**Konsequenzen:** Zugriffsregeln, `dynlist`/`memberOf` und `ppolicy` werden mitgetestet. Die Tests sind langsamer und teilen sich das Verzeichnis, was Regeln für parallele Läufe erfordert (Gruppe `ldap-global`, siehe [Tests](../tests)).

## ADR 8: Barrierefreie PDF-Bescheinigungen

**Entscheidung:** Bescheinigungen werden direkt mit tc-lib-pdf als getaggtes PDF/UA-2 gezeichnet statt aus HTML konvertiert.

**Konsequenzen:** Screenreader-taugliche Dokumente; das Layout ist dafür im Code statt in einer Vorlage definiert.
