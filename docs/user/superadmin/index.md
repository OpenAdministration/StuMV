# Superadmin

Superadmins betreiben die StuMV-Installation. Sie haben in **allen** Studierendenschaften die Rechte von Moderator:innen und Administrator:innen und gehören zur besonderen Studierendenschaft `admin`, die nur der Verwaltung dient und keine eigenen Gremien hat.

::: info
Wie StuMV installiert, konfiguriert und gewartet wird, steht im [Administrationshandbuch](/admin/).
:::

## Studierendenschaft wechseln

Über **Studischaft wechseln** in der Seitenleiste wählst du die Studierendenschaft aus, die du verwalten möchtest. Mit **Eintreten** gelangst du in ihre Übersicht.

![Auswahl der Studierendenschaften für Superadmins](/screenshots/pick-realm.png)

## Neue Studierendenschaft anlegen

1. Öffne **Studischaft wechseln** und klicke auf **Neue Studischaft**.
2. Gib Kürzel und Namen ein. Das Kürzel erscheint in allen Adressen und lässt sich später nicht ändern.
3. Speichere. StuMV legt die Grundstruktur (Moderator:innen, Administrator:innen) automatisch an.
4. Ernenne die ersten [Administrator:innen](#erste-administrator-innen) und hinterlege die [Domains](/user/administration/einstellungen#domains).

## Erste Administrator:innen

Eine neue Studierendenschaft hat noch keine Mitglieder. Damit sich die erste Person registrieren kann, hinterlege zunächst die Domain der Hochschule oder lege das Konto direkt an:

- **Mitglieder → Neues Mitglied hinzufügen** registriert ein Konto direkt in dieser Studierendenschaft.
- Anschließend ernennst du die Person unter **Administrator:innen** zur Administrator:in.

Ab dann kann die Studierendenschaft sich selbst verwalten.

## Mailinglisten-Domains

Unter **Einstellungen → Mailinglisten-Domains** legst du fest, welche Studierendenschaften unter welcher Mailman-Domain neue [Mailinglisten](/user/administration/mailinglisten) anlegen dürfen.

## Aktivitätsprotokoll aller Studierendenschaften

In der Studierendenschaft `admin` zeigt das [Aktivitätsprotokoll](/user/administration/aktivitaetsprotokoll) die Änderungen aller Studierendenschaften sowie übergreifende Einstellungen.

## Automatische Aufgaben

Welche Aufgaben nachts automatisch laufen und wie sie konfiguriert werden, steht unter [Wartung & Updates](/admin/wartung#automatische-aufgaben).
