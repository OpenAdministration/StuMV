import { readFileSync } from 'node:fs'
import { parseSpec, usePaths } from 'vitepress-openapi'

const spec = parseSpec(readFileSync(new URL('./openapi.yaml', import.meta.url), 'utf8'))

// one page per operation, e.g. /dev/api/listCommittees
export default {
  paths() {
    return usePaths({ spec })
      .getPathsByVerbs()
      .map(({ operationId, summary }) => ({ params: { operationId, title: summary } }))
  },
}
