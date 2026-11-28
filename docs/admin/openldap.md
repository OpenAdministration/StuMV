# OpenLDAP einrichten

StuMV speichert Personen, Passwörter, Studierendenschaften, Gremien, Rollen und Rechtegruppen in OpenLDAP. Diese Anleitung richtet einen lokalen `slapd` auf Debian 13 ein, der genauso konfiguriert ist wie die Referenzinstallation.

Die Konfiguration benötigt:

| Baustein | Wofür |
| --- | --- |
| Modul `argon2` | Passwörter werden mit Argon2 gehasht |
| Overlay `dynlist` | erzeugt das Attribut `memberOf` aus Gruppenmitgliedschaften – StuMV ist darauf angewiesen |
| Overlay `ppolicy` | hasht Klartext-Passwörter und protokolliert die letzte erfolgreiche Anmeldung |
| `olcLastBind` | speichert den Zeitpunkt der letzten Anmeldung (Grundlage für das Löschen inaktiver Konten) |
| StartTLS | StuMV verbindet sich immer per StartTLS |

## 1. slapd installieren

```bash
apt install -y slapd ldap-utils
dpkg-reconfigure slapd
```

Beantworte die Fragen so:

| Frage | Antwort |
| --- | --- |
| OpenLDAP-Server-Konfiguration auslassen? | Nein |
| DNS-Domainname | `stumv.de` (ergibt die Basis `dc=stumv,dc=de`) |
| Name der Organisation | z. B. `Studentische Mitgliederverwaltung` |
| Administrator-Passwort | ein sicheres Passwort für `cn=admin,dc=stumv,dc=de` |
| Datenbank beim Entfernen von slapd löschen? | Nein |
| Alte Datenbank verschieben? | Ja |

Prüfe, ob die benötigten Module vorhanden sind:

```bash
ls /usr/lib/ldap/ | grep -E '^(argon2|dynlist|ppolicy)\.so$'
ls /etc/ldap/schema/dyngroup.ldif
```

Es müssen drei Module und die Schemadatei gefunden werden.

## 2. Nur lokal lauschen

Läuft StuMV auf demselben Server, muss slapd nicht von außen erreichbar sein. In `/etc/default/slapd`:

```bash
SLAPD_SERVICES="ldap://127.0.0.1:389/ ldapi:///"
```

::: tip Zugriff für andere Dienste
Sollen andere Dienste das Verzeichnis direkt lesen, lass slapd zusätzlich auf einer internen Adresse lauschen und lege für jeden Dienst ein eigenes Service-Konto in der Gruppe `cn=read` an (siehe unten).
:::

## 3. TLS-Zertifikat erzeugen

Für die lokale Verbindung genügt ein selbst signiertes Zertifikat:

```bash
mkdir -p /etc/ldap/tls
openssl req -x509 -newkey rsa:3072 -nodes -days 3650 -subj "/CN=localhost" \
  -addext "subjectAltName=DNS:localhost,IP:127.0.0.1" \
  -keyout /etc/ldap/tls/server.key -out /etc/ldap/tls/server.crt
chown openldap:openldap /etc/ldap/tls/server.key
chmod 600 /etc/ldap/tls/server.key
chmod 644 /etc/ldap/tls/server.crt
```

StuMV vertraut dem Zertifikat über `LDAP_TLS_CACERT=/etc/ldap/tls/server.crt` in der `.env`. Soll slapd auch von anderen Rechnern erreichbar sein, verwende stattdessen ein Zertifikat für dessen echten Hostnamen.

## 4. Konfiguration anpassen

Speichere die folgende Datei als `stumv-config.ldif`. Ersetze `dc=stumv,dc=de` überall, falls du eine andere Basis gewählt hast.

::: details stumv-config.ldif (aufklappen)
```text
# TLS
dn: cn=config
changetype: modify
replace: olcTLSCertificateFile
olcTLSCertificateFile: /etc/ldap/tls/server.crt
-
replace: olcTLSCertificateKeyFile
olcTLSCertificateKeyFile: /etc/ldap/tls/server.key

# Module
dn: cn=module{0},cn=config
changetype: modify
add: olcModuleLoad
olcModuleLoad: ppolicy
olcModuleLoad: dynlist
olcModuleLoad: argon2

# Neue Passwörter mit Argon2 hashen
dn: olcDatabase={-1}frontend,cn=config
changetype: modify
replace: olcPasswordHash
olcPasswordHash: {ARGON2}

# Letzte Anmeldung speichern (höchstens einmal pro Minute schreiben)
dn: olcDatabase={1}mdb,cn=config
changetype: modify
replace: olcLastBind
olcLastBind: TRUE
-
replace: olcLastBindPrecision
olcLastBindPrecision: 60
-
replace: olcAccess
olcAccess: {0}to dn.one="ou=Services,dc=stumv,dc=de"
  by anonymous auth
olcAccess: {1}to dn.one="ou=People,dc=stumv,dc=de" attrs=userPassword
  by group/groupOfUniqueNames/uniqueMember="cn=write,ou=Permissions,ou=Services,dc=stumv,dc=de" write
  by self write
  by anonymous auth
  by * none
olcAccess: {2}to dn.exact="ou=People,dc=stumv,dc=de"
  by group/groupOfUniqueNames/uniqueMember="cn=write,ou=Permissions,ou=Services,dc=stumv,dc=de" write
  by users read
olcAccess: {3}to dn.sub="ou=People,dc=stumv,dc=de"
  by group/groupOfUniqueNames/uniqueMember="cn=write,ou=Permissions,ou=Services,dc=stumv,dc=de" write
  by group/groupOfUniqueNames/uniqueMember="cn=read,ou=Permissions,ou=Services,dc=stumv,dc=de" read
  by anonymous none
olcAccess: {4}to dn.exact="cn=super-admins,dc=stumv,dc=de"
  by group/groupOfUniqueNames/uniqueMember="cn=write,ou=Permissions,ou=Services,dc=stumv,dc=de" write
  by group/groupOfUniqueNames/uniqueMember="cn=read,ou=Permissions,ou=Services,dc=stumv,dc=de" read
olcAccess: {5}to dn.exact="ou=Communities,dc=stumv,dc=de"
  by group/groupOfUniqueNames/uniqueMember="cn=write,ou=Permissions,ou=Services,dc=stumv,dc=de" write
  by users read
olcAccess: {6}to dn.regex="^uid=[^,]+,ou=People,ou=[^,]+,ou=Communities,dc=stumv,dc=de$" attrs=userPassword
  by group/groupOfUniqueNames/uniqueMember="cn=write,ou=Permissions,ou=Services,dc=stumv,dc=de" write
  by self write
  by anonymous auth
  by * none
olcAccess: {7}to dn.regex="^ou=People,ou=[^,]+,ou=Communities,dc=stumv,dc=de$"
  by group/groupOfUniqueNames/uniqueMember="cn=write,ou=Permissions,ou=Services,dc=stumv,dc=de" write
  by users read
olcAccess: {8}to dn.regex="^[^,]+,ou=People,ou=[^,]+,ou=Communities,dc=stumv,dc=de$"
  by group/groupOfUniqueNames/uniqueMember="cn=write,ou=Permissions,ou=Services,dc=stumv,dc=de" write
  by group/groupOfUniqueNames/uniqueMember="cn=read,ou=Permissions,ou=Services,dc=stumv,dc=de" read
  by anonymous none
olcAccess: {9}to dn.sub="ou=Communities,dc=stumv,dc=de"
  by group/groupOfUniqueNames/uniqueMember="cn=write,ou=Permissions,ou=Services,dc=stumv,dc=de" write
  by group/groupOfUniqueNames/uniqueMember="cn=read,ou=Permissions,ou=Services,dc=stumv,dc=de" read
  by anonymous none
olcAccess: {10}to dn.exact="dc=stumv,dc=de" attrs=children
  by group/groupOfUniqueNames/uniqueMember="cn=write,ou=Permissions,ou=Services,dc=stumv,dc=de" write
  by * none
olcAccess: {11}to dn.exact="dc=stumv,dc=de"
  by users read
olcAccess: {12}to *
  by users read
  by anonymous none
```
:::

Und die Overlays als `stumv-overlays.ldif`:

```text
# memberOf aus uniqueMember erzeugen
dn: olcOverlay=dynlist,olcDatabase={1}mdb,cn=config
objectClass: olcOverlayConfig
objectClass: olcDynListConfig
olcOverlay: dynlist
olcDynListAttrSet: groupOfURLs memberURL uniqueMember+memberOf@groupOfUniqueNames

# Klartext-Passwörter hashen, letzte Anmeldung protokollieren
dn: olcOverlay=ppolicy,olcDatabase={1}mdb,cn=config
objectClass: olcOverlayConfig
objectClass: olcPPolicyConfig
olcOverlay: ppolicy
olcPPolicyDefault: cn=default,ou=Policies,dc=stumv,dc=de
olcPPolicyHashCleartext: TRUE
olcPPolicyForwardUpdates: FALSE
```

Einspielen:

```bash
ldapadd    -Y EXTERNAL -H ldapi:/// -f /etc/ldap/schema/dyngroup.ldif
ldapmodify -Y EXTERNAL -H ldapi:/// -f stumv-config.ldif
ldapadd    -Y EXTERNAL -H ldapi:/// -f stumv-overlays.ldif
systemctl restart slapd
```

::: info Zugriffsregeln
Die Reihenfolge der `olcAccess`-Regeln ist wichtig: OpenLDAP wendet die erste passende Regel an. Kurz zusammengefasst darf

- jede Person ihr eigenes Passwort ändern und sich anmelden,
- das Service-Konto in `cn=write` alles schreiben, was StuMV verwaltet,
- ein Service-Konto in `cn=read` Personen, Gremien und Gruppen lesen,
- niemand anonym Daten lesen.
:::

## 5. Grundstruktur anlegen

Erzeuge einen Passwort-Hash für das Service-Konto, mit dem StuMV sich am LDAP anmeldet:

```bash
slappasswd -o module-path=/usr/lib/ldap -o module-load=argon2 -h '{ARGON2}'
```

Speichere die folgende Datei als `stumv-base.ldif` und setze den Hash bei `userPassword` ein:

::: details stumv-base.ldif (aufklappen)
```text
dn: ou=People,dc=stumv,dc=de
objectClass: organizationalUnit
ou: People

dn: ou=Communities,dc=stumv,dc=de
objectClass: organizationalUnit
ou: Communities

dn: ou=Policies,dc=stumv,dc=de
objectClass: organizationalUnit
ou: Policies

# Kontosperren übernimmt StuMV selbst, daher pwdLockout: FALSE
dn: cn=default,ou=Policies,dc=stumv,dc=de
objectClass: pwdPolicy
objectClass: device
cn: default
pwdAttribute: userPassword
pwdLockout: FALSE

dn: ou=Services,dc=stumv,dc=de
objectClass: organizationalUnit
ou: Services

# Service-Konto für StuMV
dn: uid=stumv,ou=Services,dc=stumv,dc=de
objectClass: account
objectClass: simpleSecurityObject
uid: stumv
description: StuMV-Anwendung
userPassword: {ARGON2}$argon2id$...

dn: ou=Permissions,ou=Services,dc=stumv,dc=de
objectClass: organizationalUnit
ou: Permissions

dn: cn=write,ou=Permissions,ou=Services,dc=stumv,dc=de
objectClass: groupOfUniqueNames
cn: write
uniqueMember: uid=stumv,ou=Services,dc=stumv,dc=de

dn: cn=read,ou=Permissions,ou=Services,dc=stumv,dc=de
objectClass: groupOfUniqueNames
cn: read
uniqueMember: uid=stumv,ou=Services,dc=stumv,dc=de

# Studierendenschaft der Superadmins
dn: ou=admin,ou=Communities,dc=stumv,dc=de
objectClass: organizationalUnit
ou: admin
description: Superadmins

dn: ou=People,ou=admin,ou=Communities,dc=stumv,dc=de
objectClass: organizationalUnit
ou: People
```
:::

```bash
ldapadd -x -H ldapi:/// -D "cn=admin,dc=stumv,dc=de" -W -f stumv-base.ldif
```

## 6. Verbindung testen

Prüfe, ob sich das Service-Konto per StartTLS anmelden kann:

```bash
LDAPTLS_CACERT=/etc/ldap/tls/server.crt \
  ldapwhoami -x -ZZ -H ldap://127.0.0.1 -D "uid=stumv,ou=Services,dc=stumv,dc=de" -W
```

Erwartete Ausgabe: `dn:uid=stumv,ou=Services,dc=stumv,dc=de`.

Trage das Passwort des Service-Kontos als `LDAP_PASSWORD` in die `.env` ein und setze `LDAP_HOST=127.0.0.1`. Dann geht es mit der [Installation](./installation#_5-stumv-herunterladen) weiter.

## Weitere Service-Konten

Andere Dienste, die das Verzeichnis lesen sollen (z. B. ein Mailserver oder eine Nextcloud mit LDAP-Anbindung), bekommen ein eigenes Konto unter `ou=Services`, das nur Mitglied in `cn=read` ist:

```text
dn: uid=nextcloud,ou=Services,dc=stumv,dc=de
objectClass: account
objectClass: simpleSecurityObject
uid: nextcloud
userPassword: {ARGON2}$argon2id$...

dn: cn=read,ou=Permissions,ou=Services,dc=stumv,dc=de
changetype: modify
add: uniqueMember
uniqueMember: uid=nextcloud,ou=Services,dc=stumv,dc=de
```

```bash
ldapmodify -a -x -H ldapi:/// -D "cn=admin,dc=stumv,dc=de" -W -f nextcloud.ldif
```
