import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const projectRoot = resolve(__dirname, '../../..')

function read(relativePath: string): string {
  return readFileSync(resolve(projectRoot, relativePath), 'utf8')
}

describe('frontend security acceptance rules', () => {
  it('does not persist bearer tokens in localStorage or sessionStorage', () => {
    const authStore = read('src/stores/auth.ts')
    const apiClient = read('src/api/client.ts')

    expect(authStore).not.toMatch(/localStorage\.(getItem|setItem)\(['"]auth_token['"]/)
    expect(authStore).not.toMatch(/sessionStorage\.(getItem|setItem)\(['"]auth_token['"]/)
    expect(apiClient).not.toContain("localStorage.getItem('auth_token')")
    expect(apiClient).not.toContain('localStorage.getItem("auth_token")')
  })

  it('does not render database supplied SVG or HTML with v-html', () => {
    const dynamicHtmlBindings = [
      'src/modules/admin/views/ModuleListView.vue',
      'src/modules/dashboard/views/DashboardView.vue',
    ]

    for (const file of dynamicHtmlBindings) {
      expect(read(file), `${file} must sanitize or componentize icons instead of using v-html`).not.toContain('v-html=')
    }
  })
})
