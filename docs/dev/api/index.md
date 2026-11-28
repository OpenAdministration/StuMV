<script setup>
import specUrl from './openapi.yaml?url'
</script>

# Verzeichnis-API

Über die Verzeichnis-API können angebundene Anwendungen die Gremien, Rollen, Rechtegruppen und Mitglieder einer Studierendenschaft abfragen, etwa um Zugriffsrechte abzugleichen oder Gremienlisten anzuzeigen. Die API ist **nur lesend**.

::: tip API oder OpenID Connect?
Die API ist für Anwendungen gedacht, die **ohne angemeldete Person** Daten abrufen. Soll sich eine Person mit ihrem StuMV-Konto in einer Anwendung anmelden, ist ein [OIDC-Client](/user/administration/oidc-clients) der richtige Weg.
:::

Die Referenz der einzelnen Endpunkte findest du in der Seitenleiste. Die Beschreibungen dort stammen aus der OpenAPI-Spezifikation und sind auf Englisch.

## Grundlagen

| | |
| --- | --- |
| **Basis-URL** | `https://<stumv-adresse>/api` |
| **Format** | JSON |
| **Authentifizierung** | OAuth 2.0 Client Credentials |
| **Limit** | 60 Anfragen pro Minute |
| **Sortierung** | Listen sind alphabetisch aufsteigend sortiert, außer `POST /{realm}/members`, das die Sortierung selbst wählen lässt |

Alle Pfade beginnen mit dem Kürzel der Studierendenschaft (`{realm}`), z. B. `/api/stura/committees`.

## Zugang einrichten

1. Lass dir von den Administrator:innen der Studierendenschaft einen [API-Client](/user/administration/api-clients) registrieren und nur die Berechtigungen geben, die deine Anwendung braucht.
2. Du erhältst eine **Client-ID** und ein **Client-Secret**. Das Secret wird nur einmal angezeigt.

Ein Client gehört zu genau **einer** Studierendenschaft und kann nur deren Daten abfragen, unabhängig von seinen Berechtigungen.

## Token abrufen

```bash
curl -X POST https://<stumv-adresse>/<kürzel>/oauth/token \
  -d grant_type=client_credentials \
  -d client_id=<client-id> \
  -d client_secret=<client-secret> \
  -d scope="committees groups users"
```

Die Antwort enthält ein `access_token`, das du bei jeder Anfrage mitschickst:

```bash
curl https://<stumv-adresse>/api/<kürzel>/committees \
  -H "Authorization: Bearer <access-token>" \
  -H "Accept: application/json"
```

Läuft das Token ab, rufst du einfach ein neues ab.

## Berechtigungen (Scopes)

| Scope | Endpunkte |
| --- | --- |
| `committees` | Gremien, ihre Rollen und deren aktuelle Mitglieder |
| `groups` | Rechtegruppen und ihre aktuellen Mitglieder |
| `users` | einzelne Personen: Profil sowie ihre Rollen, Gremien und Gruppen |

Fragst du einen Endpunkt ohne passenden Scope ab, antwortet die API mit `403`.

## OpenAPI-Spezifikation

Die vollständige Spezifikation kannst du als <a :href="specUrl" download="openapi.yaml">openapi.yaml</a> herunterladen, z. B. um daraus Client-Bibliotheken zu generieren oder sie in Werkzeuge wie Postman oder Bruno zu importieren.
