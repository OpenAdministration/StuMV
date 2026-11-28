# 6. Laufzeitsicht

## Anmeldung

Die Anmeldung ist immer an eine Studierendenschaft gebunden. Der LDAP-Auth-Provider sucht das Konto nur unter `ou=People` dieser Studierendenschaft (`ScopedToRealmPeople`).

```mermaid
sequenceDiagram
  actor P as Person
  participant C as AuthenticatedSessionController
  participant G as Auth-Guard (LdapRecord)
  participant L as OpenLDAP
  participant D as Datenbank

  P->>C: POST /{realm}/login (Anmeldename oder E-Mail, Passwort)
  C->>G: attempt()
  G->>L: Suche in ou=People,ou={realm},…
  G->>L: Bind als gefundene Person
  L-->>G: ok
  G->>G: Regel DenyLockedUsers
  G->>D: user-Zeile anlegen/aktualisieren (uid = entryUUID)
  G-->>C: angemeldet
  C-->>P: Weiterleitung (ursprünglich angefragte Seite)
```

Passwörter liegen nur im LDAP; die Datenbank speichert keine nutzbaren Passwörter (`sync_passwords: false`).

## Person einer Rolle zuordnen

```mermaid
sequenceDiagram
  actor M as Moderator:in
  participant W as Livewire AddUserToRole
  participant D as Datenbank
  participant S as RoleMembershipLdapSync
  participant L as OpenLDAP
  participant MM as Mailman

  M->>W: save()
  W->>D: Transaktion: user-Zeile sicherstellen,<br/>RoleMembership anlegen, Protokolleintrag
  W->>S: sync(membership)
  alt Mitgliedschaft heute gültig
    S->>L: Person in Rolle eintragen
    S->>L: Person in zugeordnete Rechtegruppen eintragen
  end
  S-->>W: ok / fehlgeschlagen
  W-->>M: Erfolgsmeldung (ggf. Warnung „nächster Abgleich“)
  Note over S,MM: nach der Antwort (app()->terminating)
  S->>MM: betroffene Mailinglisten abgleichen
```

Beginnt die Mitgliedschaft erst in der Zukunft, ändert sich das LDAP noch nicht; der [nächtliche Abgleich](#nachtlicher-abgleich) trägt sie am Starttag ein.

## Anmeldung bei einer angebundenen Anwendung (OIDC)

```mermaid
sequenceDiagram
  actor P as Person
  participant RP as Anwendung (OIDC-Client)
  participant S as StuMV /{realm}/oauth
  participant D as Datenbank

  P->>RP: „Mit StuMV anmelden“
  RP->>S: GET /authorize (client_id, redirect_uri, scope, state, nonce, PKCE)
  S->>S: Middleware: Client gehört zu {realm}? max_age? nonce merken
  opt nicht angemeldet
    S-->>P: Login-Seite der Studierendenschaft
    P->>S: Anmeldung
  end
  opt Client verlangt Zustimmung
    S-->>P: Seite „Informationsweitergabe“
    P->>S: Zulassen (Entscheidung wird protokolliert)
  end
  S-->>RP: Redirect mit code
  RP->>S: POST /token (code, Client-Authentifizierung oder PKCE)
  S->>D: Tokens speichern
  S-->>RP: access_token, id_token (mit sid), refresh_token
  RP->>S: GET /userinfo
  S-->>RP: Claims je nach Scope (Profil, E-Mail, Gruppen, …)
```

Meldet sich die Person später bei StuMV ab, erhalten alle Clients mit Back-Channel-Logout-URL ein signiertes `logout_token` (`SendBackChannelLogoutNotification`).

## Anmeldung über einen externen Identity Provider

```mermaid
sequenceDiagram
  actor P as Person
  participant C as OidcLoginController
  participant I as Externer Identity Provider
  participant D as Datenbank
  participant L as OpenLDAP

  P->>C: GET /{realm}/identity-provider/{id}
  C-->>P: Weiterleitung zum Identity Provider
  P->>I: Anmeldung
  I-->>C: GET …/callback mit code
  C->>I: Token + Claims abrufen
  C->>C: email_verified prüfen (falls aktiviert)
  C->>D: Konto der Studierendenschaft über E-Mail suchen,<br/>sonst über bestätigte weitere Adresse
  alt Konto gefunden
    C->>L: gesperrt?
    C->>C: Auth::login()
    C->>D: IdentityProviderGroupRoleSync / GroupSync:<br/>Rollen und Gruppen aus dem Gruppen-Claim
    C->>D: IdentityProviderSession (für Back-Channel Logout)
  else kein Konto
    C-->>P: Anmeldename wählen → neues Konto
  end
```

Rollen, die diese Synchronisation selbst vergeben hat (`identity_provider_id` gesetzt), werden beendet, sobald die Gruppe im Claim fehlt; andere Rollen bleiben unberührt.

## Nächtlicher Abgleich

```mermaid
sequenceDiagram
  participant Cron
  participant K as Scheduler
  participant D as Datenbank
  participant L as OpenLDAP
  participant MM as Mailman

  Cron->>K: schedule:run (jede Minute)
  K->>D: 00:01 ldap:sync-roles: heute gültige Mitgliedschaften
  K->>L: uniqueMember aller Rollen angleichen
  K->>D: 00:15 ldap:sync-groups: Gruppe ↔ Rolle ↔ Personen
  K->>L: uniqueMember aller Rechtegruppen angleichen
  K->>MM: 00:30 mailman:sync-lists
  K->>D: 03:00 / 03:30 inaktive Konten vormerken / löschen
  K->>D: 04:00 alte Protokolleinträge löschen
```

Der Abgleich ist idempotent: Er überträgt nur Unterschiede und fängt damit auch Änderungen auf, deren sofortige Übertragung fehlgeschlagen ist. Administrator:innen können ihn über das Menü **Synchronisieren** jederzeit anstoßen; `LdapSyncDiff` zeigt vorher an, was sich ändern würde.
