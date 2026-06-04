import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import SettingsView from '@/modules/settings/views/SettingsView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import client from '@/api/client'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/settings', name: 'settings', component: { template: '<div/>' } }],
})

const SETTINGS = {
  name: 'Boutique Teranga',
  domain: 'teranga.sn',
  settings: {
    country: 'SN', currency: 'XOF', phone: '+221 77 123 45 67',
    address: 'Dakar, Plateau', website: 'https://teranga.sn',
    session_timeout_minutes: 1440,
  },
}

function mockApi() {
  vi.mocked(client.get).mockImplementation((url: string) => {
    if (url.includes('/workspace/settings')) return Promise.resolve({ data: { data: SETTINGS } }) as any
    return Promise.resolve({ data: { data: [] } }) as any
  })
  vi.mocked(client.patch).mockResolvedValue({ data: { data: SETTINGS, message: 'ok' } } as any)
}

async function mountView() {
  mockApi()
  const w = mount(SettingsView, { global: { plugins: [router, setupManagerAuth()] } })
  await flushPromises()
  return w
}

describe('SettingsView', () => {
  beforeEach(() => vi.clearAllMocks())

  it('lazy-loads company settings on mount and populates the form', async () => {
    const w = await mountView()
    expect(client.get).toHaveBeenCalledWith('/api/workspace/settings')
    const nameInput = w.find('input.form-input').element as HTMLInputElement
    expect(nameInput.value).toBe('Boutique Teranga')
  })

  it('persists company settings via PATCH on submit', async () => {
    const w = await mountView()
    await w.find('form.company-form').trigger('submit.prevent')
    await flushPromises()
    expect(client.patch).toHaveBeenCalledWith(
      '/api/workspace/settings',
      expect.objectContaining({ name: 'Boutique Teranga' }),
    )
    expect(w.text()).toContain('Modifications enregistrées')
  })

  it('renders the settings tab navigation', async () => {
    const w = await mountView()
    expect(w.text()).toContain('Entreprise')
  })
})
