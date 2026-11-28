import DefaultTheme from 'vitepress/theme'
import { locales, theme, useOpenapi } from 'vitepress-openapi/client'
import 'vitepress-openapi/dist/style.css'
// also published for download at /openapi.yaml
import spec from '../../public/openapi.yaml?raw'
import de from './openapi-de.json'
import './custom.css'

export default {
  extends: DefaultTheme,
  async enhanceApp(ctx) {
    useOpenapi({
      spec,
      config: {
        i18n: { locale: 'de', fallbackLocale: 'en', messages: { ...locales, de } },
        // the playground would call the docs host, not a StuMV instance
        operation: { hiddenSlots: ['playground', 'branding'] },
        linksPrefixes: { operations: '/dev/api/' },
        // the spec's server is relative (/api); show an absolute example in the samples
        // OAOperationContext reads servers[0].url, despite the string[] type
        server: { getServers: () => [{ url: 'https://stumv.example.org/api' }] as never },
      },
    })
    theme.enhanceApp(ctx)
  },
}
