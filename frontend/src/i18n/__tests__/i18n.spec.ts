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
    // Garde-fou : ces clés doivent exister en EN (pas de retour au fallback FR).
    expect(t('common.update')).toBe('Update')
    expect(t('common.confirm')).toBe('Confirm')
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

  it('translates the catalog module namespace (status + columns)', () => {
    setLocale('fr')
    expect(t('catalog.productsTitle')).toBe('Catalogue produits')
    expect(t('catalog.status.archived')).toBe('Archivé')
    setLocale('en')
    expect(t('catalog.productsTitle')).toBe('Product catalog')
    expect(t('catalog.status.archived')).toBe('Archived')
  })

  it('translates the catalog categories sub-namespace (title + interpolated confirm)', () => {
    setLocale('fr')
    expect(t('catalog.categoriesTitle')).toBe('Catégories')
    expect(t('catalog.noParent')).toBe('Aucune (catégorie racine)')
    expect(t('catalog.confirmDeleteCategory', { name: 'Vêtements' }))
      .toBe('Supprimer "Vêtements" ? Cette action est irréversible.')
    setLocale('en')
    expect(t('catalog.categoriesTitle')).toBe('Categories')
    expect(t('catalog.confirmDeleteCategory', { name: 'Clothing' }))
      .toBe('Delete "Clothing"? This action cannot be undone.')
  })

  it('translates the inventory module namespace (warehouses + clé imbriquée)', () => {
    setLocale('fr')
    expect(t('inventory.warehousesTitle')).toBe('Entrepôts & Boutiques')
    expect(t('inventory.typeBadge.shop')).toBe('Boutique')
    expect(t('inventory.setDefault')).toBe('Définir par défaut')
    expect(t('inventory.stockTitle')).toBe('Stock')
    expect(t('inventory.reasonOpt.sale')).toBe('Vente')
    expect(t('inventory.productsInStock', { count: 12 })).toBe('12 produits en stock')
    setLocale('en')
    expect(t('inventory.warehousesTitle')).toBe('Warehouses & Shops')
    expect(t('inventory.typeBadge.shop')).toBe('Shop')
    expect(t('inventory.reasonOpt.sale')).toBe('Sale')
  })

  it('translates the orders module namespace (tabs + status)', () => {
    setLocale('fr')
    expect(t('orders.title')).toBe('Commandes')
    expect(t('orders.tab.draft')).toBe('Brouillons')
    expect(t('orders.status.fulfilled')).toBe('Livrée')
    setLocale('en')
    expect(t('orders.title')).toBe('Orders')
    expect(t('orders.status.fulfilled')).toBe('Fulfilled')
  })

  it('translates the reports module namespace (nested keys + interpolation)', () => {
    setLocale('fr')
    expect(t('reports.salesTitle')).toBe('Rapport des ventes')
    expect(t('reports.kpiRevenue', { period: '30 derniers jours' })).toBe('CA total — 30 derniers jours')
    expect(t('reports.movement.in')).toBe('Entrées')
    setLocale('en')
    expect(t('reports.salesTitle')).toBe('Sales report')
    expect(t('reports.movement.in')).toBe('Inflows')
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
