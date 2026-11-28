# Mailinglisten

StuMV kann die Mitglieder von [Rechtegruppen](./rechtegruppen) automatisch auf Mailinglisten eines Mailman-3-Servers eintragen. Damit stimmt z. B. der Verteiler des Vorstands immer mit der aktuellen Besetzung überein – ohne dass jemand nach einer Wahl Adressen von Hand pflegen muss.

::: info
Diese Funktion steht nur zur Verfügung, wenn die Installation an einen Mailman-Server angebunden ist.
:::

## Mailingliste zuordnen

1. Öffne **Mailinglisten** in der Seitenleiste und klicke auf **Neue Mailingliste**.
2. Wähle, ob du eine **bestehende Liste verknüpfen** oder eine **neue Liste anlegen** möchtest:
   - *Bestehende Liste:* Gib die **Mailman-Listen-ID** ein, z. B. `vorstand.lists.example.org` – das ist nicht die Versandadresse.
   - *Neue Liste:* Gib einen **Listennamen** ein und wähle eine **Domain**. Welche Domains zur Auswahl stehen, legen die Betreiber:innen fest.
3. Wähle die Gruppen:

   | Feld | Wirkung |
   | --- | --- |
   | **Mitglieder** | Die Personen dieser Gruppen werden als Mitglieder der Liste eingetragen. |
   | **Moderation** | Die Personen dieser Gruppen moderieren die Liste. Ohne Auswahl verwaltet StuMV die Moderation nicht. |

4. Speichere.

## Status

Die Übersicht zeigt für jede Liste, ob sie aktuell ist:

| Status | Bedeutung |
| --- | --- |
| **Synchronisiert** | Die Liste stimmt mit den Gruppen überein. |
| **Personen fehlen** | Gruppenmitglieder stehen noch nicht auf der Liste. |
| **Personen sollten entfernt werden** | Auf der Liste stehen Adressen, die nicht mehr zur Gruppe gehören. |

Über **Mitglieder ansehen** siehst du den Status jeder einzelnen Person und kannst sie gezielt **eintragen** oder **austragen**.

## Synchronisieren

Alle Listen werden jede Nacht automatisch abgeglichen. Mit **Synchronisieren** bei einer Liste stößt du den Abgleich sofort an; alle Listen auf einmal synchronisierst du über das Menü [Synchronisieren](./synchronisation) in der Kopfzeile.

::: warning
Beim Synchronisieren werden Adressen, die nicht mehr zur Gruppe gehören, **von der Liste entfernt**. Wer zusätzlich zu den Gruppenmitgliedern auf der Liste stehen soll, braucht deshalb eine passende Rolle.
:::

## Liste deaktivieren oder Zuordnung löschen

- **Deaktivieren** pausiert den Abgleich, die Zuordnung bleibt erhalten. Mit **Aktivieren** geht es weiter.
- **Löschen** entfernt die Zuordnung. Die Mailingliste selbst und ihre Mitglieder bleiben in Mailman unverändert.
