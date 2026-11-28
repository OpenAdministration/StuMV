# API-Clients

API-Clients sind Anwendungen, die das Verzeichnis eurer Studierendenschaft **ohne Login einer Person** abfragen dürfen – etwa um nachts eine Liste aller Gremien und ihrer Besetzung abzurufen.

![Liste der API-Clients](/screenshots/api-clients.png)

::: tip API-Client oder OIDC-Client?
- Soll sich eine **Person** in einer Anwendung mit ihrem StuMV-Konto anmelden? → [OIDC-Client](./oidc-clients)
- Soll eine **Anwendung selbstständig** Daten aus StuMV abrufen? → API-Client
:::

## Client registrieren

1. Öffne **Einstellungen → API-Clients** und klicke auf **Neuer Client**.
2. Gib einen **Namen** ein, z. B. *StuFiS*.
3. Wähle die **Berechtigungen**:

   | Berechtigung | Zugriff auf |
   | --- | --- |
   | **Gremien & Rollen** | Gremien, Rollen und deren Mitglieder |
   | **Gruppen** | Rechtegruppen und deren Mitglieder |
   | **Nutzer:innen** | Name, Benutzername und Studiengang der Mitglieder |

4. Speichere und kopiere **Client-ID** und **Client-Secret** in die Anwendung.

::: danger Secret sofort sichern
Das Client-Secret wird nur einmal angezeigt. Geht es verloren, widerrufe den Client und registriere einen neuen.
:::

Die technische Beschreibung der Schnittstelle (OpenAPI) findet ihr unter `https://<stumv-adresse>/api-docs/`.

## Client bearbeiten, widerrufen, löschen

- **Bearbeiten**: Name und Berechtigungen ändern.
- **Widerrufen**: Der Client verliert sofort den Zugriff. Das kann nicht rückgängig gemacht werden.
- **Löschen**: entfernt den Client vollständig.
