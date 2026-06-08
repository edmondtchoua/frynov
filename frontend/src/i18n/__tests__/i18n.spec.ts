import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { t, setLocale, useI18n, SUPPORTED_LOCALES } from '@/i18n'
import LanguageSwitcher from '@/shared/components/LanguageSwitcher.vue'

// The i18n locale is a module singleton — always restore the default so the
// active locale never leaks into other spec files (test isolation).
afterEach(() => setLocale('fr'))

describe('i18n core', () => {
  beforeEach(() => setLocale('fr'))

  it('translates dot-path keys in the active locale', () => {
    setLocale('fr')
    expect(t('common.save')).toBe('Enregistrer')
    expect(t('notFound.title')).toBe('Page introuvable')
    setLocale('en')
    expect(t('common.save')).toBe('Save')
    expect(t('notFound.title')).toBe('Page not found')
  })

  it('interpolates {params}', () => {
    setLocale('fr')
    expect(t('common.welcome', { name: 'Awa' })).toBe('Bienvenue Awa')
    setLocale('en')
    expect(t('common.welcome', { name: 'Awa' })).toBe('Welcome Awa')
  })

  it('returns the raw key when a translation is missing (visible signal)', () => {
    expect(t('does.not.exist')).toBe('does.not.exist')
  })

  it('translates the suppliers module namespace in both locales', () => {
    setLocale('fr')
    expect(t('suppliers.title')).toBe('Fournisseurs')
    expect(t('suppliers.confirmDelete', { name: 'ACME' })).toBe('Supprimer le fournisseur « ACME » ?')
    setLocale('en')
    expect(t('suppliers.title')).toBe('Suppliers')
    expect(t('suppliers.confirmDelete', { name: 'ACME' })).toBe('Delete supplier "ACME"?')
  })

  it('persists the chosen locale to localStorage', () => {
    setLocale('en')
    expect(localStorage.getItem('frynov.locale')).toBe('en')
    expect(document.documentElement.lang).toBe('en')
  })

  it('ignores unsupported locales', () => {
    setLocale('fr')
    setLocale('zz' as never)
    expect(useI18n().locale.value).toBe('fr')
  })
})

describe('LanguageSwitcher', () => {
  beforeEach(() => setLocale('fr'))

  it('renders an option per supported locale and switches the active locale', async () => {
    const w = mount(LanguageSwitcher)
    expect(w.findAll('option').length).toBe(SUPPORTED_LOCALES.length)

    await w.find('select').setValue('en')
    expect(useI18n().locale.value).toBe('en')
  })
})
