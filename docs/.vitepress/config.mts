import { defineConfig } from 'vitepress'

export default defineConfig({
  lang: 'de-DE',
  title: 'StuMV',
  description: 'Benutzerhandbuch der Studentischen Mitgliederverwaltung',
  cleanUrls: true,
  lastUpdated: true,
  // developer docs, not part of the user manual
  srcExclude: ['install.md', 'install-dev.md', 'screenshots/**'],
  head: [['link', { rel: 'icon', type: 'image/svg+xml', href: '/logo.svg' }]],

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Benutzerhandbuch', link: '/user/einfuehrung/was-ist-stumv', activeMatch: '^/user/' },
      { text: 'Administrationshandbuch', link: '/admin/', activeMatch: '^/admin/' },
    ],

    sidebar: {
      '/user/': [
        {
          text: 'Einführung',
          items: [
            { text: 'Was ist StuMV?', link: '/user/einfuehrung/was-ist-stumv' },
            { text: 'Grundbegriffe', link: '/user/einfuehrung/grundbegriffe' },
            { text: 'Zuständigkeiten & Rechte', link: '/user/einfuehrung/zustaendigkeiten' },
            { text: 'Die Oberfläche', link: '/user/einfuehrung/oberflaeche' },
          ],
        },
        {
          text: 'Für Mitglieder',
          items: [
            { text: 'Registrierung & Anmeldung', link: '/user/mitglieder/registrierung-und-anmeldung' },
            { text: 'Profil & Konto', link: '/user/mitglieder/profil' },
            { text: 'Mitgliedschaften & Bescheinigungen', link: '/user/mitglieder/mitgliedschaften' },
            { text: 'Bei anderen Diensten anmelden', link: '/user/mitglieder/andere-dienste' },
          ],
        },
        {
          text: 'Für Moderator:innen',
          items: [
            { text: 'Gremien', link: '/user/moderation/gremien' },
            { text: 'Rollen', link: '/user/moderation/rollen' },
            { text: 'Rollenmitgliedschaften', link: '/user/moderation/rollenmitgliedschaften' },
            { text: 'Moderator:innen', link: '/user/moderation/moderatorinnen' },
            { text: 'Hilfsmittel', link: '/user/moderation/hilfsmittel' },
          ],
        },
        {
          text: 'Für Administrator:innen',
          items: [
            { text: 'Einstellungen der Studierendenschaft', link: '/user/administration/einstellungen' },
            { text: 'Rechtegruppen', link: '/user/administration/rechtegruppen' },
            { text: 'Mailinglisten', link: '/user/administration/mailinglisten' },
            { text: 'Synchronisation', link: '/user/administration/synchronisation' },
            { text: 'OIDC-Clients', link: '/user/administration/oidc-clients' },
            { text: 'Identity Provider', link: '/user/administration/identity-provider' },
            { text: 'API-Clients', link: '/user/administration/api-clients' },
            { text: 'Aktivitätsprotokoll', link: '/user/administration/aktivitaetsprotokoll' },
          ],
        },
        {
          text: 'Für Superadmins',
          items: [{ text: 'Superadmin', link: '/user/superadmin/' }],
        },
      ],
      '/admin/': [
        {
          text: 'Administrationshandbuch',
          items: [
            { text: 'Überblick', link: '/admin/' },
            { text: 'Installation auf Debian', link: '/admin/installation' },
            { text: 'OpenLDAP einrichten', link: '/admin/openldap' },
            { text: 'Konfiguration', link: '/admin/konfiguration' },
            { text: 'Ersteinrichtung', link: '/admin/ersteinrichtung' },
            { text: 'Wartung & Updates', link: '/admin/wartung' },
            { text: 'Upgrade von v1', link: '/admin/upgrade-v1' },
            { text: 'Fehlerbehebung', link: '/admin/fehlerbehebung' },
          ],
        },
      ],
    },

    search: {
      provider: 'local',
      options: {
        translations: {
          button: { buttonText: 'Suchen', buttonAriaLabel: 'Suchen' },
          modal: {
            noResultsText: 'Keine Ergebnisse für',
            resetButtonTitle: 'Suche zurücksetzen',
            footer: { selectText: 'auswählen', navigateText: 'navigieren', closeText: 'schließen' },
          },
        },
      },
    },

    socialLinks: [{ icon: 'github', link: 'https://github.com/OpenAdministration/StuMV' }],

    editLink: {
      pattern: 'https://github.com/OpenAdministration/StuMV/edit/main/docs/:path',
      text: 'Diese Seite auf GitHub bearbeiten',
    },

    outline: { label: 'Auf dieser Seite', level: [2, 3] },
    docFooter: { prev: 'Vorherige Seite', next: 'Nächste Seite' },
    lastUpdated: { text: 'Zuletzt aktualisiert' },
    returnToTopLabel: 'Nach oben',
    sidebarMenuLabel: 'Menü',
    darkModeSwitchLabel: 'Erscheinungsbild',
    lightModeSwitchTitle: 'Helles Design',
    darkModeSwitchTitle: 'Dunkles Design',
    notFound: {
      title: 'Seite nicht gefunden',
      quote: 'Diese Seite gibt es leider nicht.',
      linkText: 'Zur Startseite',
    },

    footer: {
      message: 'StuMV ist freie Software von Open Administration.',
    },
  },
})
