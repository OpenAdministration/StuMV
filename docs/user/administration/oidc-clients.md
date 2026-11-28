# OIDC-Clients

Über OIDC-Clients bindest du Anwendungen an, die den Login an StuMV abgeben sollen – z. B. ein Wiki, eine Cloud, ein Ticketsystem oder das StuFiS. Mitglieder melden sich dort dann mit ihrem StuMV-Konto an (siehe [Bei anderen Diensten anmelden](/user/mitglieder/andere-dienste)). StuMV verwendet dafür die Standards **OpenID Connect** und **OAuth 2.0**.

![Liste der OIDC-Clients](/screenshots/oidc-clients.png)

## Client registrieren

1. Öffne **Einstellungen → OIDC-Clients** und klicke auf **Neuer Client**.
2. Fülle die Reiter aus (Details unten).
3. Speichere.
4. Kopiere die angezeigte **Client-ID** und das **Client-Secret** und trage sie in der Anwendung ein.

![Formular zum Registrieren eines OIDC-Clients](/screenshots/new-oidc-client.png)

::: danger Secret sofort sichern
Das Client-Secret wird **nur einmal** angezeigt. Geht es verloren, kannst du beim Bearbeiten ein neues erzeugen (**Secret neu generieren**) – die Anwendung muss dann umgestellt werden.
:::

### Allgemein

| Feld | Bedeutung |
| --- | --- |
| **Name** | wird Mitgliedern auf der Seite *Informationsweitergabe* angezeigt |
| **Beschreibung** | optional, ebenfalls auf dieser Seite sichtbar |
| **Berechtigungen** | welche Daten die Anwendung abfragen darf: OpenID, Profil, E-Mail-Adresse, Telefonnummer, Adresse, Gruppen |
| **Um Erlaubnis fragen** | Standardmäßig aktiv: Mitglieder müssen die Weitergabe bestätigen. Deaktiviert werden sie ohne Rückfrage angemeldet – sinnvoll für eigene, vertrauenswürdige Dienste. |
| **Logo** | kann nach dem Registrieren beim Bearbeiten hinzugefügt werden (SVG, PNG, JPEG oder WebP) |

::: tip Nur so viele Daten wie nötig
Gib einer Anwendung nur die Berechtigungen, die sie wirklich braucht. Die Freigabe **Gruppen** ist nötig, wenn die Anwendung Rechte aus den [Rechtegruppen](./rechtegruppen) ableiten soll.
:::

### URIs

| Feld | Bedeutung |
| --- | --- |
| **Redirect-URIs** | Eine URL pro Zeile. Nach dem Login wird nur zu einer dieser Adressen zurückgeleitet. Sie müssen exakt übereinstimmen – Wildcards (`*`) sind nicht erlaubt. Die richtige Adresse steht in der Dokumentation der Anwendung. |
| **Post-Logout-Redirect-URIs** | Optional, eine pro Zeile. Wohin die Anwendung nach dem Abmelden zurückleiten darf. Ein abschließendes `*` steht für einen beliebigen Rest, z. B. `https://wiki.example.org/*`. |
| **Back-Channel-Logout-URL** | Optional. Meldet sich jemand bei StuMV ab, schickt StuMV eine Benachrichtigung an diese Adresse, damit die Anwendung die Sitzung ebenfalls beendet. |

### Sicherheit

**Client-Authentifizierung deaktivieren** registriert die Anwendung als *öffentlichen Client* ohne Secret. Das ist nur für Anwendungen gedacht, die kein Secret geheim halten können (z. B. Zammad). Sie müssen stattdessen PKCE (S256) verwenden.

### Dienstanbieter

Optional: Betreiber des Dienstes sowie Links zu **Impressum**, **Nutzungsbedingungen** und **Datenschutzerklärung**. Sie werden Mitgliedern auf der Seite *Informationsweitergabe* angezeigt.

## Angaben für die Anwendung

Die meisten Anwendungen brauchen nur die *Discovery-URL*, die Client-ID und das Secret. Die Discovery-URL deiner Studierendenschaft lautet:

```
https://<stumv-adresse>/<kürzel>/.well-known/openid-configuration
```

Daraus liest die Anwendung alle weiteren Adressen (Autorisierung, Token, Benutzerinformationen, Abmeldung) selbst aus.

::: info Nur für die eigene Studierendenschaft
Ein OIDC-Client gehört zu genau einer Studierendenschaft. Nur deren Mitglieder können sich über ihn anmelden.
:::

## Client widerrufen oder löschen

- **Widerrufen** sperrt den Client sofort. Das kann nicht rückgängig gemacht werden.
- **Löschen** entfernt ihn vollständig.
