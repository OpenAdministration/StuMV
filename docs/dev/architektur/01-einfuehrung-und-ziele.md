# 1. Einführung und Ziele

## Aufgabenstellung

StuMV verwaltet die Mitglieder von Studierendenschaften und ähnlichen Organisationen. Die Kernaufgaben sind:

- **Mitglieder verwalten:** Konten je Studierendenschaft, Registrierung über Hochschul-Domains, Einladungen und externe Identity Provider
- **Funktionen dokumentieren:** Personen werden Rollen in verschachtelten Gremien zugeordnet, mit Start- und Enddatum, sodass eine nachvollziehbare Historie entsteht
- **Rechte ableiten:** Rollen werden Rechtegruppen zugeordnet; diese werden an andere Anwendungen weitergegeben, per LDAP, OpenID Connect, Verzeichnis-API und Mailman-Mailinglisten
- **Identity Provider sein:** andere Anwendungen nutzen StuMV für den Login (OpenID Connect)

Die fachlichen Begriffe erklärt das [Glossar](./12-glossar), die Bedienung das [Benutzerhandbuch](/user/einfuehrung/was-ist-stumv).

## Qualitätsziele

| Priorität | Qualitätsziel | Bedeutung für StuMV |
| :-: | --- | --- |
| 1 | **Sicherheit** | StuMV vergibt Zugänge zu anderen Systemen, etwa zum Finanzsystem. Fehler in Authentifizierung und Berechtigungen wirken sich direkt auf diese Systeme aus. |
| 2 | **Mandantentrennung** | Studierendenschaften sind voneinander unabhängig. Keine darf Konten oder Daten einer anderen sehen oder verändern, auch nicht über angebundene Clients. |
| 3 | **Nachvollziehbarkeit** | Wer wann welche Funktion hatte und wer was geändert hat, muss auch Jahre später belegbar sein (Mitgliedschafts-Historie, Aktivitätsprotokoll). |
| 4 | **Datenschutz** | Es werden nur nötige Daten weitergegeben (Zustimmung je Client, Scopes) und personenbezogene Daten nach festgelegten Fristen gelöscht. |
| 5 | **Bedienbarkeit** | Ehrenamtliche ohne IT-Hintergrund müssen Gremien und Rollen selbst pflegen können. |

Die Qualitätsanforderungen im Detail stehen in [Abschnitt 10](./10-qualitaetsanforderungen).

## Stakeholder

| Rolle | Erwartung |
| --- | --- |
| **Mitglieder** | ein Konto für alle Dienste, Überblick über eigene Funktionen, Tätigkeits-Bescheinigungen |
| **Moderator:innen** | Gremien, Rollen und Besetzungen ohne technische Kenntnisse pflegen |
| **Administrator:innen der Studierendenschaft** | Rechte und Anbindung anderer Anwendungen steuern |
| **Betreiber:innen** (z. B. Open Administration) | mehrere Studierendenschaften auf einer Installation sicher und wartungsarm betreiben |
| **Angebundene Anwendungen** (StuFiS, Wikis, Clouds, Mailman, …) | verlässliche, standardkonforme Schnittstellen (OIDC, LDAP, REST) |
| **Entwickler:innen** | verständliche Struktur, gute Testbarkeit |
