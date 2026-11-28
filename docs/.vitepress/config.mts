import { readFileSync } from 'node:fs'
import { defineConfig } from 'vitepress'
import { withMermaid } from 'vitepress-plugin-mermaid'
import { parseSpec, useSidebar } from 'vitepress-openapi'

// the directory API reference is generated from the spec the app serves
const apiSpec = parseSpec(readFileSync(new URL('../dev/api/openapi.yaml', import.meta.url), 'utf8'))
const apiSidebar = useSidebar({ spec: apiSpec, linkPrefix: '/dev/api/' })

export default withMermaid(defineConfig({
  lang: 'de-DE',
  title: 'StuMV',
  description: 'Dokumentation der Studentischen Mitgliederverwaltung',
  cleanUrls: true,
  lastUpdated: true,
  // the dev handbook lists the local container stack's URLs
  ignoreDeadLinks: 'localhostLinks',
  // developer docs, not part of the user manual
  srcExclude: ['screenshots/**'],
  head: [['link', { rel: 'icon', type: 'image/svg+xml', href: '/logo.svg' }]],
  // vitepress-openapi's components need vitepress's client API during SSR
  vite: {
    ssr: { noExternal: ['vitepress-openapi'] },
    // pre-bundle mermaid as a whole: newer releases pull in CommonJS deps (fastdom)
    // the mermaid plugin's own include list doesn't know about
    optimizeDeps: { include: ['mermaid'] },
  },

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Benutzerhandbuch', link: '/user/einfuehrung/was-ist-stumv', activeMatch: '^/user/' },
      { text: 'Administrationshandbuch', link: '/admin/', activeMatch: '^/admin/' },
      { text: 'Entwicklerhandbuch', link: '/dev/', activeMatch: '^/dev/' },
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
      '/dev/': [
        {
          text: 'Einstieg',
          items: [
            { text: 'Überblick', link: '/dev/' },
            { text: 'Entwicklungsumgebung', link: '/dev/entwicklungsumgebung' },
            { text: 'Tests', link: '/dev/tests' },
            { text: 'Beitragen', link: '/dev/beitragen' },
          ],
        },
        {
          text: 'Architektur (arc42)',
          collapsed: false,
          items: [
            { text: '1. Einführung und Ziele', link: '/dev/architektur/01-einfuehrung-und-ziele' },
            { text: '2. Randbedingungen', link: '/dev/architektur/02-randbedingungen' },
            { text: '3. Kontextabgrenzung', link: '/dev/architektur/03-kontextabgrenzung' },
            { text: '4. Lösungsstrategie', link: '/dev/architektur/04-loesungsstrategie' },
            { text: '5. Bausteinsicht', link: '/dev/architektur/05-bausteinsicht' },
            { text: '6. Laufzeitsicht', link: '/dev/architektur/06-laufzeitsicht' },
            { text: '7. Verteilungssicht', link: '/dev/architektur/07-verteilungssicht' },
            { text: '8. Querschnittliche Konzepte', link: '/dev/architektur/08-querschnittliche-konzepte' },
            { text: '9. Architekturentscheidungen', link: '/dev/architektur/09-architekturentscheidungen' },
            { text: '10. Qualitätsanforderungen', link: '/dev/architektur/10-qualitaetsanforderungen' },
            { text: '11. Risiken und technische Schulden', link: '/dev/architektur/11-risiken-und-technische-schulden' },
            { text: '12. Glossar', link: '/dev/architektur/12-glossar' },
          ],
        },
        {
          text: 'Verzeichnis-API',
          items: [{ text: 'Überblick', link: '/dev/api/' }],
        },
        ...apiSidebar.generateSidebarGroups(),
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
}))
