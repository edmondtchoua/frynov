/**
 * Lightweight i18n (audit UX-13 — foundation).
 *
 * A dependency-free internationalization core that mirrors the vue-i18n `t()` API, so call
 * sites (`$t('key')` / `t('key')`) stay identical if we later graduate to vue-i18n for advanced
 * features (plural rules, per-locale number/date/currency formatting, lazy-loaded locales).
 *
 * - `locale` is a reactive ref → changing it re-renders every template that reads `$t`.
 * - Dot-path keys, `{param}` interpolation, fallback to French then to the raw key (so a missing
 *   translation is visible, not blank).
 * - Choice persisted in localStorage (non-sensitive — unlike the auth token, which is memory-only).
 *
 * Adoption is incremental: strings are migrated view by view. French is the source of truth.
 */
import { ref, computed } from 'vue'
import type { App } from 'vue'

export const SUPPORTED_LOCALES = ['fr', 'en'] as const
export type Locale = (typeof SUPPORTED_LOCALES)[number]

export const LOCALE_LABELS: Record<Locale, string> = { fr: 'Français', en: 'English' }

const STORAGE_KEY = 'frynov.locale'

type Dict = { [key: string]: string | Dict }

const messages: Record<Locale, Dict> = {
  fr: {
    common: {
      save: 'Enregistrer', cancel: 'Annuler', delete: 'Supprimer', edit: 'Modifier',
      create: 'Créer', search: 'Rechercher', loading: 'Chargement…', back: 'Retour',
      welcome: 'Bienvenue {name}',
      saving: 'Enregistrement…', retry: 'Réessayer', name: 'Nom', email: 'Email',
      phone: 'Téléphone', status: 'Statut', actions: 'Actions', notes: 'Notes',
      active: 'Actif', inactive: 'Inactif', createdAt: 'Créé le', updatedAt: 'Dernière modification',
      genericError: 'Une erreur est survenue.',
    },
    nav: {
      dashboard: 'Tableau de bord', catalog: 'Catalogue', inventory: 'Stock',
      orders: 'Ventes', customers: 'Clients', payments: 'Paiements',
      reports: 'Rapports', settings: 'Paramètres',
    },
    notFound: {
      title: 'Page introuvable',
      message: "La page que vous cherchez n'existe pas ou a été déplacée.",
      back: 'Retour au tableau de bord',
    },
    suppliers: {
      title: 'Fournisseurs', itemSingular: 'fournisseur', itemPlural: 'fournisseurs',
      new: 'Nouveau fournisseur', searchPlaceholder: 'Rechercher un fournisseur…',
      allStatuses: 'Tous les statuts', activePlural: 'Actifs', inactivePlural: 'Inactifs',
      empty: 'Aucun fournisseur trouvé', addFirst: 'Ajouter le premier',
      colCode: 'Code', colContact: 'Contact', colTerms: 'Conditions',
      viewDetail: 'Voir le détail', contactPerson: 'Personne de contact', address: 'Adresse',
      modalCreateTitle: 'Nouveau fournisseur', modalEditTitle: 'Modifier le fournisseur',
      namePlaceholder: 'Raison sociale', emailPlaceholder: 'contact@fournisseur.com',
      contactPlaceholder: 'M. Dupont', terms: 'Conditions de paiement',
      termsPlaceholder: 'Ex : Net 30, Net 60', notesPlaceholder: 'Commentaires internes…',
      confirmDelete: 'Supprimer le fournisseur « {name} » ?', deleteFailed: 'Suppression impossible.',
      fetchError: 'Impossible de charger ce fournisseur.', updateError: 'Erreur lors de la mise à jour.',
      deleteConfirmTitle: 'Supprimer ce fournisseur ?',
      deleteConfirmDesc: 'Cette action est irréversible. Le fournisseur {name} sera définitivement supprimé.',
      deleting: 'Suppression…',
    },
    language: { label: 'Langue' },
  },
  en: {
    common: {
      save: 'Save', cancel: 'Cancel', delete: 'Delete', edit: 'Edit',
      create: 'Create', search: 'Search', loading: 'Loading…', back: 'Back',
      welcome: 'Welcome {name}',
      saving: 'Saving…', retry: 'Retry', name: 'Name', email: 'Email',
      phone: 'Phone', status: 'Status', actions: 'Actions', notes: 'Notes',
      active: 'Active', inactive: 'Inactive', createdAt: 'Created on', updatedAt: 'Last updated',
      genericError: 'An error occurred.',
    },
    nav: {
      dashboard: 'Dashboard', catalog: 'Catalog', inventory: 'Inventory',
      orders: 'Sales', customers: 'Customers', payments: 'Payments',
      reports: 'Reports', settings: 'Settings',
    },
    notFound: {
      title: 'Page not found',
      message: "The page you are looking for doesn't exist or has been moved.",
      back: 'Back to dashboard',
    },
    suppliers: {
      title: 'Suppliers', itemSingular: 'supplier', itemPlural: 'suppliers',
      new: 'New supplier', searchPlaceholder: 'Search a supplier…',
      allStatuses: 'All statuses', activePlural: 'Active', inactivePlural: 'Inactive',
      empty: 'No supplier found', addFirst: 'Add the first one',
      colCode: 'Code', colContact: 'Contact', colTerms: 'Terms',
      viewDetail: 'View details', contactPerson: 'Contact person', address: 'Address',
      modalCreateTitle: 'New supplier', modalEditTitle: 'Edit supplier',
      namePlaceholder: 'Company name', emailPlaceholder: 'contact@supplier.com',
      contactPlaceholder: 'Mr. Smith', terms: 'Payment terms',
      termsPlaceholder: 'e.g. Net 30, Net 60', notesPlaceholder: 'Internal notes…',
      confirmDelete: 'Delete supplier "{name}"?', deleteFailed: 'Could not delete.',
      fetchError: 'Could not load this supplier.', updateError: 'Update failed.',
      deleteConfirmTitle: 'Delete this supplier?',
      deleteConfirmDesc: 'This cannot be undone. Supplier {name} will be permanently deleted.',
      deleting: 'Deleting…',
    },
    language: { label: 'Language' },
  },
}

function detectInitial(): Locale {
  try {
    const saved = localStorage.getItem(STORAGE_KEY)
    if (saved && (SUPPORTED_LOCALES as readonly string[]).includes(saved)) return saved as Locale
  } catch { /* localStorage may be unavailable */ }
  const nav = (typeof navigator !== 'undefined' ? navigator.language : 'fr').slice(0, 2)
  return (SUPPORTED_LOCALES as readonly string[]).includes(nav) ? (nav as Locale) : 'fr'
}

const locale = ref<Locale>(detectInitial())

function lookup(dict: Dict, key: string): string | undefined {
  const found = key.split('.').reduce<string | Dict | undefined>(
    (node, part) => (node && typeof node === 'object' ? node[part] : undefined),
    dict,
  )
  return typeof found === 'string' ? found : undefined
}

/** Translate a dot-path key with optional `{param}` interpolation. */
export function t(key: string, params?: Record<string, string | number>): string {
  let value = lookup(messages[locale.value], key) ?? lookup(messages.fr, key)
  if (value === undefined) return key // visible signal for a missing translation
  if (params) {
    for (const [k, v] of Object.entries(params)) {
      value = value.replace(new RegExp(`\\{${k}\\}`, 'g'), String(v))
    }
  }
  return value
}

export function setLocale(next: Locale): void {
  if (!(SUPPORTED_LOCALES as readonly string[]).includes(next)) return
  locale.value = next
  try { localStorage.setItem(STORAGE_KEY, next) } catch { /* ignore */ }
  if (typeof document !== 'undefined') document.documentElement.lang = next
}

/** Composable mirroring vue-i18n's `useI18n()` shape. */
export function useI18n() {
  return {
    locale: computed(() => locale.value),
    availableLocales: SUPPORTED_LOCALES,
    localeLabels: LOCALE_LABELS,
    t,
    setLocale,
  }
}

/** Vue plugin: exposes `$t` globally and seeds <html lang>. */
export const i18n = {
  install(app: App) {
    app.config.globalProperties.$t = t
    if (typeof document !== 'undefined') document.documentElement.lang = locale.value
  },
}

// Make `$t` known to the template type-checker (vue-tsc).
declare module 'vue' {
  interface ComponentCustomProperties {
    $t: (key: string, params?: Record<string, string | number>) => string
  }
}
