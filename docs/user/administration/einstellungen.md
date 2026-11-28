# Einstellungen der Studierendenschaft

Unter **Einstellungen** in der Seitenleiste verwalten Administrator:innen die Studierendenschaft selbst. Die Übersicht führt zu folgenden Bereichen:

![Übersicht der Einstellungen](/screenshots/settings.png)

- [Studierendenschaft](#studierendenschaft) – Name, Anschrift, Kontoverbindung
- [Erscheinungsbild](#erscheinungsbild) – Logo, Hintergrundbild, Hinweis auf der Login-Seite
- [Domains](#domains) – automatische Zuordnung neuer Mitglieder
- [Mitgliedschaften-Export](#mitgliedschaften-export) – Vorlagen für Tätigkeits-Bescheinigungen
- [OIDC-Clients](./oidc-clients), [Identity Provider](./identity-provider), [API-Clients](./api-clients) – Anbindung anderer Anwendungen

## Studierendenschaft

Hier pflegst du die Stammdaten deiner Studierendenschaft:

- **Name**
- **Anschrift und Kontakt**: Straße, Hausnummer, Postleitzahl, Ort, Gebäude, Raum, E-Mail-Adresse, Telefonnummer, Website
- **Kontoverbindung**: Kreditinstitut, IBAN, BIC

Anschrift, Kontakt und Kontoverbindung erscheinen im Briefkopf und in der Fußzeile der [Tätigkeits-Bescheinigungen](/user/mitglieder/mitgliedschaften#mit-anschreiben).

## Erscheinungsbild

Passe die Login- und Registrierungsseite an eure Studierendenschaft an:

| Einstellung | Wirkung |
| --- | --- |
| **Logo** | ersetzt das Standard-Logo auf Login- und Registrierungsseite |
| **Hintergrundbild** | wird als Hintergrund dieser Seiten angezeigt |
| **Hinweis auf der Login-Seite** | ein frei formulierbarer Text, z. B. für Wartungsankündigungen oder Hilfe bei der Anmeldung; mit **Hinweis anzeigen** ein- und ausschaltbar |

Logo und Hintergrundbild lassen sich nach dem Hochladen zuschneiden und jederzeit wieder entfernen.

![Einstellungen zum Erscheinungsbild der Login-Seite](/screenshots/branding.png)

## Domains

Über Domains werden neue Mitglieder automatisch eurer Studierendenschaft zugeordnet: Wer sich mit einer E-Mail-Adresse einer hinterlegten Domain registriert, landet direkt bei euch. Registrierungen mit anderen Adressen werden abgelehnt.

![Liste der hinterlegten Domains](/screenshots/domains.png)

### Domain hinzufügen

1. Öffne **Einstellungen → Domains** und klicke auf **Domain hinzufügen**.
2. Gib die Domain im Feld **Domain (FQDN)** ein, z. B. `stud.hs-musterstadt.de` – ohne `@` und ohne `https://`.
3. Speichere.

::: tip Mehrere Domains
Viele Hochschulen nutzen unterschiedliche Domains für Studierende und Beschäftigte (z. B. `stud.hs-musterstadt.de` und `hs-musterstadt.de`). Lege dann beide an.
:::

### Domain löschen

Nach dem Löschen können sich Personen mit Adressen dieser Domain nicht mehr selbst registrieren. Bestehende Konten bleiben unverändert.

Personen ohne passende Adresse nehmt ihr über eine [Einladung](/user/moderation/hilfsmittel#zur-registrierung-einladen) auf.

## Mitgliedschaften-Export

Vorlagen bestimmen, wie die [Tätigkeits-Bescheinigungen](/user/mitglieder/mitgliedschaften#tatigkeits-bescheinigung-erstellen) eurer Mitglieder aussehen. Beim Export wählen Mitglieder unter *„Was benötigst du?“* eine Vorlage aus. Ohne Vorlage enthält der Export nur eine Übersicht der Mitgliedschaften.

![Liste der Vorlagen für den Mitgliedschaften-Export](/screenshots/export-templates.png)

### Vorlage anlegen

1. Öffne **Einstellungen → Mitgliedschaften-Export** und klicke auf **Neue Vorlage**.
2. Gib einen **Namen** ein, unter dem Mitglieder die Vorlage finden, z. B. *Flexsemester-Antrag*.
3. Wähle das **Datumsformat** für die Zeiträume.
4. Lege fest, ob ein **Anschreiben** vorangestellt wird, und fülle es gegebenenfalls aus (siehe unten).
5. Speichere.

![Bearbeiten einer Vorlage mit Briefkopf, Empfänger und Absender](/screenshots/export-template.png)

### Anschreiben

| Bereich | Inhalt |
| --- | --- |
| **Briefkopf** | Überschrift und Untertitel |
| **Anschreiben von** | *Studierendenschaft*, *Exportierende Person* oder *Mitglied* – bestimmt, wessen Name und Anschrift als Absender erscheinen |
| **Absender** | Absender-Organisation, Struktureinheit (z. B. *Sprecher:innen*), Gebäude, Raum, E-Mail, Telefon, Website |
| **Empfänger** | *Mitglied* (Adresse aus dessen Profil) oder *Anderer Adressat* mit Organisation, Struktureinheit, Name und Anschrift – z. B. das Prüfungsamt |
| **Inhalt** | Betreff und Text |

### Platzhalter

In Betreff und Text kannst du Platzhalter verwenden, die beim Export ersetzt werden:

| Platzhalter | wird ersetzt durch |
| --- | --- |
| `[person.name]` | Name der exportierten Person |
| `[person.email]` | E-Mail-Adresse |
| `[person.course]` | Studiengang |
| `[person.street]` | Straße und Hausnummer |
| `[person.postal_code]` | Postleitzahl |
| `[person.city]` | Ort |
| `[person.phone]` | Telefonnummer |
| `[sender.name]` | Name der exportierenden Person |
| `[realm.name]` | Name der Studierendenschaft |
| `[today]` | heutiges Datum im gewählten Format |
| `[signature]` | Unterschriftsfeld |

Das **Unterschriftsfeld** muss allein in einem eigenen Absatz stehen. Optional können darunter Name und Funktion gedruckt werden:

```
[signature;name=[sender.name];function=Sprecher:in]
```

::: details Beispiel: Text für einen Flexsemester-Antrag
```
Sehr geehrte Damen und Herren,

hiermit bestätigen wir, dass [person.name] (Studiengang [person.course])
sich in den unten aufgeführten Gremien der [realm.name] engagiert hat.

Mit freundlichen Grüßen

[signature;name=[sender.name];function=Sprecher:in]
```
:::
