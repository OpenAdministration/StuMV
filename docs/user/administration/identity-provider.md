# Identity Provider

Ein externer Identity Provider ermöglicht Mitgliedern den Login mit einem bestehenden Konto, meist dem **Hochschul-Login**. Auf der Login-Seite erscheint dann unter *„oder anmelden mit“* ein zusätzlicher Button. Unterstützt werden Identity Provider, die OpenID Connect sprechen.

::: tip Umgekehrte Richtung
Hier geht es darum, dass StuMV *einen anderen* Login nutzt. Sollen andere Anwendungen den StuMV-Login nutzen, brauchst du einen [OIDC-Client](./oidc-clients).
:::

## Identity Provider hinzufügen

Zuerst muss StuMV beim Identity Provider als Anwendung registriert werden – das übernimmt in der Regel das Rechenzentrum der Hochschule. Du erhältst dabei eine Client-ID und ein Client-Secret.

1. Öffne **Einstellungen → Identity Provider** und klicke auf **Neuer Identity Provider**.
2. Fülle die Felder aus:

   | Feld | Bedeutung |
   | --- | --- |
   | **Name** | Beschriftung des Buttons, z. B. *TU Musterstadt* |
   | **Issuer-URL** | Basis-URL des Identity Providers. Die Konfiguration wird automatisch unter `{issuer}/.well-known/openid-configuration` abgerufen. |
   | **Client-ID** / **Client-Secret** | vom Identity Provider erhalten |
   | **Scopes** | leerzeichengetrennt; `openid` wird immer mitgesendet. Manche Anbieter liefern Gruppen nur mit einem zusätzlichen Scope (z. B. `groups` bei Okta). |
   | **E-Mail-Verifizierung beachten** | Lehnt die Anmeldung ab, wenn der Identity Provider die E-Mail-Adresse ausdrücklich als nicht verifiziert meldet. Nur abschalten, wenn der Anbieter grundsätzlich keine Verifizierung meldet. |
   | **Gruppen-Claim** | Name des Feldes, in dem der Identity Provider die Gruppen übermittelt (Standard: `groups`) |
   | **Aktiviert** | blendet den Button auf der Login-Seite ein oder aus |

3. Speichere.
4. Trage die angezeigte **Back-Channel-Logout-URL** beim Identity Provider ein. Dann werden Mitglieder auch aus StuMV abgemeldet, wenn sie sich beim Identity Provider abmelden.

## Wie Konten zugeordnet werden

Meldet sich jemand über den Identity Provider an, sucht StuMV ein Konto mit der übermittelten E-Mail-Adresse – auch unter den bestätigten [weiteren Adressen](/user/mitglieder/profil#e-mail-adressen) im Profil.

- **Konto gefunden:** Die Person wird angemeldet.
- **Kein Konto:** Die Person wählt einen Anmeldenamen und StuMV legt ein neues Konto in eurer Studierendenschaft an.

## Automatische Zuordnungen

Übermittelt der Identity Provider Gruppen (z. B. „ist Mitglied im Studierendenrat“), kann StuMV daraus automatisch Rollen oder Rechtegruppen vergeben.

### Rollenzuordnungen

Im Reiter **Rollenzuordnungen** verknüpfst du eine **externe Gruppe** mit einer **Rolle** in einem Gremium. Beim Login wird die Person der Rolle hinzugefügt, wenn die Gruppe übereinstimmt. Die Mitgliedschaft erhält den Kommentar *„Automatisch zugeordnet über den Identity Provider …“*.

### Gruppenzuordnungen

Im Reiter **Gruppenzuordnungen** verknüpfst du eine externe Gruppe direkt mit einer [Rechtegruppe](./rechtegruppen). Das wirkt sofort beim Login, ohne Umweg über eine Rolle, und die Gruppe wird an OIDC-Clients weitergegeben.

::: warning Zuordnungen werden nie automatisch entfernt
Fällt eine Person beim Identity Provider aus einer Gruppe heraus, bleibt ihre Rolle bzw. Gruppenmitgliedschaft in StuMV bestehen. Beende solche Mitgliedschaften von Hand.
:::
