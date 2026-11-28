# Ersteinrichtung

Nach der Installation gibt es noch keine Konten. Die Ersteinrichtung legt den ersten Superadmin an. Alles Weitere geschieht dann in der Oberfläche.

## Ersten Superadmin anlegen

Superadmins sind Konten in der besonderen Studierendenschaft `admin`. Das erste Konto wird direkt im LDAP angelegt.

1. Passwort-Hash erzeugen:

   ```bash
   slappasswd -o module-path=/usr/lib/ldap -o module-load=argon2 -h '{ARGON2}'
   ```

2. Konto als `superadmin.ldif` beschreiben:

   ```text
   dn: uid=jdoe,ou=People,ou=admin,ou=Communities,dc=stumv,dc=de
   objectClass: inetOrgPerson
   uid: jdoe
   cn: Jana Doe
   givenName: Jana
   sn: Doe
   mail: jana.doe@example.org
   userPassword: {ARGON2}$argon2id$...
   ```

3. Ins LDAP einspielen:

   ```bash
   ldapadd -x -H ldapi:/// -D "cn=admin,dc=stumv,dc=de" -W -f superadmin.ldif
   ```

4. Das Konto in der Datenbank bekannt machen und die E-Mail-Adresse als bestätigt markieren:

   ```bash
   cd /var/www/stumv
   sudo -u www-data php artisan app:import-ldap-users admin --verified
   ```

5. Unter `https://stumv.example.org/admin/login` anmelden.

::: tip Weitere Superadmins
Weitere Superadmins legst du genauso an. Jede Person bekommt ein eigenes Konto – teilt keine Zugangsdaten.
:::

## Erste Studierendenschaft anlegen

Ab hier geht alles in der Oberfläche, wie im Benutzerhandbuch unter [Superadmin](/user/superadmin/) beschrieben:

1. **Studischaft wechseln → Neue Studischaft**: Kürzel und Namen eingeben.
2. In der neuen Studierendenschaft unter **Einstellungen → Domains** die E-Mail-Domain(s) der Hochschule hinterlegen.
3. Die erste Administrator:in registrieren lassen (oder unter **Mitglieder → Neues Mitglied hinzufügen** anlegen) und unter **Administrator:innen** ernennen.

Ab dann kann die Studierendenschaft sich selbst verwalten.

Alternativ geht es auch auf der Kommandozeile:

```bash
sudo -u www-data php artisan community:new stura
sudo -u www-data php artisan community:domain:new stura stud.hs-musterstadt.de
```

## Checkliste vor dem Livegang

- [ ] `APP_ENV=production` und `APP_DEBUG=false`
- [ ] HTTPS mit gültigem Zertifikat
- [ ] Testmail kommt an (z. B. über *Passwort vergessen*) und landet nicht im Spam
- [ ] Cronjob läuft – nach einem Tag erscheinen die nächtlichen Läufe im Log bzw. die [Synchronisation](/user/administration/synchronisation) zeigt *Aktuell*
- [ ] `OPERATOR_NAME`, `HELP_CONTACT_MAIL` und die Links zu Impressum und Datenschutz sind gesetzt
- [ ] Fristen für [inaktive Konten und Aufbewahrung](./konfiguration#inaktive-konten-und-aufbewahrung) sind festgelegt
- [ ] [Datensicherung](./wartung#datensicherung) ist eingerichtet und eine Wiederherstellung wurde einmal getestet
