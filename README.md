# Studentische MitgliederVerwaltung (StuMV)

Mit StuMV können die Daten der Mitglieder von Organisationen wie beispielsweise Studierendenschaften oder Vereinen verwaltet werden. Mitglieder können zu Rollen in Gruppen bzw. Gremien, denen sie angehören, zugeordnet werden. Dabei wird aufgezeichnet, wer von wann bis wann in welcher Rolle aktiv war. Mit den Mitgliedschaften in Rollen lassen sich außerdem Berechtigungen für weitere IT-Dienste verknüpfen.

StuMV kann als Identity Provider für andere Anwendungen dienen und unterstützt dabei LDAP und OpenID Connect.

Weitere Informationen: [www.stufis.de/stumv](https://www.stufis.de/stumv)

## Installation

Zunächst müssen [OpenLDAP](https://www.openldap.org/) und eine [MariaDB](https://mariadb.org/)- oder [MySQL](https://www.mysql.com/)-Datenbank eingerichtet werden.

[Infos zur Einrichtung von OpenLDAP](https://github.com/OpenAdministration/StuMV/blob/main/docs/install.md)

```
git clone https://github.com/OpenAdministration/StuMV
composer install
npm install
npm run production
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Nun müssen in der Datei `.env` noch ein paar Einstellungen wie App-Einstellungen, die Zugangsdaten für die Datenbank und E-Mail-Versand gesetzt werden.

## Security

Please write a mail to service@open-administration.de for a responsible disclosure procedure.
