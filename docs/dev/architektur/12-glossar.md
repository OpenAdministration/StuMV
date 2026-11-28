# 12. Glossar

| Begriff | Im Code | Bedeutung |
| --- | --- | --- |
| **Studierendenschaft** | `Community`, `realm`, `uid` / Kürzel | ein Mandant; im LDAP `ou=<kürzel>,ou=Communities` |
| **Admin-Realm** | `Community::ADMIN_REALM_UID` = `admin` | Studierendenschaft, die nur die Konten der Superadmins enthält |
| **Mitglied** | `App\Ldap\User`, `App\Models\User` | Konto in `ou=People` einer Studierendenschaft |
| **Gremium** | `Committee`, `ou` | Gremium, Organisation oder Gliederung; verschachtelbare `organizationalUnit` unter `ou=Committees` |
| **Rolle** | `Role`, `cn` | Funktion innerhalb eines Gremiums; `groupOfUniqueNames` |
| **Rollenmitgliedschaft** | `RoleMembership` | Zuordnung Person × Rolle mit `from`, `until`, `decided`, `comment` |
| **Rechtegruppe** | `Group` | `groupOfUniqueNames` unter `ou=Groups`, deren Mitglieder aus Rollen abgeleitet werden |
| **Gruppen-Rollen-Zuordnung** | `GroupMembership` | welche Rollen einer Rechtegruppe angehören |
| **Domain** | `Domain` | E-Mail-Domain, über die sich Personen selbst einer Studierendenschaft zuordnen |
| **Moderator:in** | `cn=moderators` | verwaltet Gremien, Rollen und Besetzungen einer Studierendenschaft |
| **Gremien-Moderator:in** | `cn=moderators` im Gremium | wie Moderator:in, beschränkt auf ein Gremium und seine Untergremien |
| **Administrator:in** | `cn=admins` | verwaltet Rechtegruppen, Clients und Einstellungen |
| **Superadmin** | Konto im Admin-Realm | Betreiber:in mit Rechten in allen Studierendenschaften |
| **Projektion** | – | die aus der Datenbank abgeleiteten Mitglieder von Rollen und Rechtegruppen im LDAP |
| **Abgleich** | `ldap:sync-roles`, `ldap:sync-groups`, `RoleMembershipLdapSync` | Übertragung der Mitgliedschaften aus der Datenbank ins LDAP |
| **DN** | Distinguished Name | eindeutiger Pfad eines LDAP-Eintrags, z. B. `cn=mitglied,ou=FSR,ou=Committees,ou=demo,…` |
| **entryUUID** | `user.uid`, `getConvertedGuid()` | unveränderliche ID eines LDAP-Eintrags; verknüpft Datenbankzeile und LDAP-Konto |
| **OIDC-Client** | `PassportClient` mit Authorization-Code-Grant | Anwendung, die den Login an StuMV abgibt |
| **API-Client** | `PassportClient` mit Client-Credentials-Grant | Anwendung, die die Verzeichnis-API abfragt |
| **Identity Provider** | `RealmIdentityProvider` | externer Login (z. B. Hochschule), bei dem StuMV selbst Relying Party ist |
| **Scope** | `tokens_can` in `config/openid.php` | Umfang des Zugriffs eines Tokens: `openid`, `profile`, `email`, `phone`, `address`, `groups`, `committees`, `users` |
| **Back-Channel Logout** | `SendBackChannelLogoutNotification` | Server-zu-Server-Benachrichtigung über eine beendete Sitzung |
| **Aktivitätsprotokoll** | `ActivityLog`, `ActivityLogger` | wer wann was geändert hat |
| **Tätigkeits-Bescheinigung** | `MembershipCertificate` | PDF-Nachweis über Mitgliedschaften |
