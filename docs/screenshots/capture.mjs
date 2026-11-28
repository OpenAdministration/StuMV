// Takes the user manual screenshots from a running StuMV with the demo data.
// See docs/screenshots/README.md.
import { chromium } from 'playwright'
import { mkdir } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'

const BASE = process.env.BASE_URL ?? 'http://127.0.0.1:8123'
const OUT = fileURLToPath(new URL('../public/screenshots/', import.meta.url))
const ONLY = process.argv.slice(2)

const USERS = {
  member: { realm: 'demo', uid: 'demo-studi', password: 'Demo-password1' },
  moderator: { realm: 'demo', uid: 'demo-hhv', password: 'Demo-password1' },
  admin: { realm: 'demo', uid: 'demo-stura', password: 'Demo-password1' },
  superadmin: {
    realm: 'admin',
    uid: process.env.SUPERADMIN_UID ?? 'admin',
    password: process.env.SUPERADMIN_PASSWORD ?? 'admin-not-production',
  },
}

// [file, user (null = logged out), path, options]
const SHOTS = [
  ['login', null, '/demo/login'],
  ['dashboard', 'member', '/demo/dashboard'],
  ['profile', 'member', '/demo/profile/demo-studi'],
  ['memberships', 'moderator', '/demo/profile/demo-hhv/memberships'],
  ['membership-export', 'moderator', '/demo/profile/demo-hhv/memberships/export'],
  ['sessions', 'member', '/demo/profile/demo-studi/sessions'],
  ['committees', 'moderator', '/demo/committees'],
  ['roles', 'moderator', '/demo/committees/Referat Finanzen'],
  ['role-members', 'moderator', '/demo/committees/StuRa/roles/mitglied'],
  ['add-role-member', 'moderator', '/demo/committees/StuRa/roles/mitglied/new-member'],
  ['new-committee', 'moderator', '/demo/new-committee'],
  ['tools', 'moderator', '/demo/tools'],
  ['invite-user', 'moderator', '/demo/tools/invite-user'],
  ['settings', 'admin', '/demo/settings'],
  ['groups', 'admin', '/demo/groups'],
  ['group-roles', 'admin', '/demo/group/ref-finanzen/roles'],
  ['domains', 'admin', '/demo/settings/domains'],
  ['branding', 'admin', '/demo/settings/branding'],
  ['export-templates', 'admin', '/demo/settings/membership-export'],
  ['export-template', 'admin', '/demo/settings/membership-export', { click: 'text=Flexsemester-Antrag' }],
  ['oidc-clients', 'admin', '/demo/settings/oidc-clients'],
  ['new-oidc-client', 'admin', '/demo/settings/new-oidc-client'],
  ['api-clients', 'admin', '/demo/settings/api-clients'],
  ['activity-log', 'admin', '/demo/activity-log'],
  ['pick-realm', 'superadmin', '/pick-realm'],
]

const browser = await chromium.launch()
await mkdir(OUT, { recursive: true })
const contexts = {}

async function contextFor(user) {
  if (contexts[user]) return contexts[user]
  const context = await browser.newContext({
    viewport: { width: 1280, height: 800 },
    locale: 'de-DE',
    colorScheme: 'light',
  })
  if (user) {
    const { realm, uid, password } = USERS[user]
    const page = await context.newPage()
    await page.goto(`${BASE}/${realm}/login`)
    await page.fill('#uid', uid)
    await page.fill('#password', password)
    await Promise.all([page.waitForURL((url) => !url.pathname.endsWith('/login')), page.click('button[type=submit]')])
    await page.close()
  }
  return (contexts[user] = context)
}

let failed = 0
for (const [file, user, path, options = {}] of SHOTS) {
  if (ONLY.length && !ONLY.includes(file)) continue
  const page = await (await contextFor(user)).newPage()
  try {
    const response = await page.goto(BASE + encodeURI(path), { waitUntil: 'networkidle' })
    if (!response.ok()) throw new Error(`HTTP ${response.status()}`)
    if (options.click) {
      // links use wire:navigate, so wait for the URL rather than a page load
      const before = page.url()
      await Promise.all([page.waitForURL((url) => url.href !== before), page.click(options.click)])
      await page.waitForLoadState('networkidle')
      await page.waitForTimeout(800) // let the navigation progress bar fade out
    }
    await page.screenshot({ path: `${OUT}${file}.png` })
    console.log(`✓ ${file}`)
  } catch (error) {
    failed++
    console.error(`✗ ${file}: ${error.message}`)
  } finally {
    await page.close()
  }
}

await browser.close()
process.exit(failed ? 1 : 0)
