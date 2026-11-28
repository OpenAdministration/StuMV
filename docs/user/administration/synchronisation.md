# Synchronisation

StuMV überträgt Rollen und Rechtegruppen in ein Verzeichnis (LDAP) und auf Mailinglisten, aus denen angeschlossene Anwendungen sie lesen. Das passiert **jede Nacht automatisch**:

| Uhrzeit | Was |
| --- | --- |
| 00:01 | Rollenmitgliedschaften (u. a. Mitgliedschaften, deren Start- oder Enddatum erreicht ist) |
| 00:15 | Rechtegruppen |
| 00:30 | Mailinglisten |

Neue Zuordnungen sind deshalb manchmal erst am nächsten Tag in allen Anwendungen sichtbar.

## Sofort synchronisieren

Administrator:innen können den Abgleich jederzeit selbst anstoßen – etwa wenn eine neu gewählte Person sofort Zugriff braucht. Öffne dazu in der Kopfzeile das Menü **Synchronisieren**.

### LDAP

1. Wähle **Synchronisieren → LDAP**.
2. Unter **Änderungen prüfen** siehst du, welche Personen in welchen Rollen und Gruppen hinzugefügt oder entfernt werden. Steht dort *„Keine Änderungen“*, ist alles aktuell.
3. Klicke auf **Synchronisieren**.

### Mailinglisten

1. Wähle **Synchronisieren → Mailinglisten**.
2. Bestätige. Alle Mailinglisten der Studierendenschaft werden an ihre Rechtegruppen angeglichen.

Bei großen Listen kann das einige Minuten dauern. Einzelne Listen synchronisierst du unter [Mailinglisten](./mailinglisten#synchronisieren).

## Status

Neben dem Menü zeigt StuMV an, ob es ausstehende Änderungen gibt. **Prüfen** öffnet die Vorschau, **Neu prüfen** aktualisiert den Status.
