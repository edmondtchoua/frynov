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
      genericError: 'Une erreur est survenue.', allStatuses: 'Tous les statuts',
      date: 'Date', amount: 'Montant', note: 'Note', previous: 'Précédent', next: 'Suivant',
      allWarehouses: 'Tous les entrepôts', product: 'Produit', quantity: 'Qté', view: 'Voir',
      description: 'Description', update: 'Mettre à jour',
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
    deliveries: {
      title: 'Livraisons', itemSingular: 'livraison', itemPlural: 'livraisons',
      new: 'Nouvelle livraison', empty: 'Aucune livraison',
      emptyFiltered: 'Aucune livraison avec ce statut.', emptyDefault: 'Les livraisons apparaîtront ici.',
      colOrder: 'Commande', colCarrier: 'Transporteur', colTracking: 'Suivi', colDates: 'Dates',
      created: 'Créée {date}', dispatch: 'Expédier', markDelivered: 'Livré', fail: 'Échec',
      carrier: 'Transporteur', tracking: 'N° de suivi',
      failTitle: 'Signaler un échec', failReason: "Raison de l'échec",
      failReasonPlaceholder: 'Ex : Adresse introuvable, client absent…', failConfirm: "Confirmer l'échec",
      status: { pending: 'En attente', dispatched: 'Expédiée', in_transit: 'En transit', delivered: 'Livrée', failed: 'Échec' },
    },
    payments: {
      title: 'Paiements', recordSingular: 'enregistrement', recordPlural: 'enregistrements',
      new: 'Nouveau paiement', allMethods: 'Tous les moyens', allWarehouses: 'Tous les entrepôts',
      empty: 'Aucun paiement', emptyFiltered: 'Aucun paiement pour ce moyen de paiement.',
      emptyDefault: 'Les paiements enregistrés apparaîtront ici.',
      colOrder: 'Commande', colMethod: 'Moyen', colReference: 'Référence',
      void: 'Annuler', voidTitle: 'Annuler ce paiement', voidConfirm: 'Annuler ce paiement de {amount} ?',
      totalShown: 'Total affiché', modalTitle: 'Enregistrer un paiement',
      methodLabel: 'Moyen de paiement', referenceLabel: 'Référence transaction',
      referencePlaceholder: 'N° reçu, TX ID…',
      method: { cash: 'Espèces', mobile_money: 'Mobile Money', card: 'Carte', transfer: 'Virement', cheque: 'Chèque' },
    },
    reports: {
      salesTitle: 'Rapport des ventes', salesSubtitle: "Chiffre d'affaires, top produits et méthodes de paiement",
      stockTitle: 'Rapport de stock', stockSubtitle: 'Valeur du stock, ruptures et produits en alerte',
      kpiRevenue: 'CA total — {period}', kpiPayments: 'Paiements enregistrés',
      kpiAvgBasket: 'Panier moyen', kpiTopMethod: 'Méthode principale',
      revenueChartTitle: 'Évolution du CA', topProducts: 'Top produits',
      noSales: 'Aucune vente sur la période.', colRevenue: 'CA',
      byMethodTitle: 'Répartition par moyen de paiement', noPayments: 'Aucun paiement sur la période.',
      paymentWord: 'paiement', paymentsWord: 'paiements', chartEmpty: 'Aucune donnée.',
      loadError: 'Erreur de chargement des données.', viewAlerts: 'Voir les alertes',
      kpiStockValue: 'Valeur du stock (coût)', kpiSkus: 'Références suivies',
      kpiLowStock: 'En alerte stock bas', kpiOutOfStock: 'En rupture de stock',
      lowStockTitle: 'Produits en alerte', noLowStock: 'Aucun produit en alerte stock.',
      movementsTitle: 'Mouvements — 30 derniers jours', noMovements: 'Aucun mouvement enregistré.',
      operationWord: 'opération', operationsWord: 'opérations', unitsWord: 'unités',
      period: { '7d': '7J', '30d': '30J', '90d': '90J', '1y': '1 an' },
      periodLong: { '7d': '7 derniers jours', '30d': '30 derniers jours', '90d': '90 derniers jours', '1y': 'Dernière année' },
      movement: { in: 'Entrées', out: 'Sorties', adjustment: 'Ajustements', return: 'Retours' },
    },
    orders: {
      title: 'Commandes', new: 'Nouvelle commande', searchPlaceholder: 'N° commande, client...',
      dateFrom: 'Depuis', dateTo: "Jusqu'au",
      loadErrorTitle: 'Erreur de chargement', loadError: 'Impossible de charger les commandes.',
      empty: 'Aucune commande', emptyHint: 'Les commandes apparaîtront ici.',
      colNumber: 'N°', colItems: 'Articles', colTotal: 'Total',
      articleWord: 'article', articlesWord: 'articles',
      tab: { all: 'Toutes', draft: 'Brouillons', confirmed: 'Confirmées', fulfilled: 'Livrées', cancelled: 'Annulées' },
      status: { draft: 'Brouillon', confirmed: 'Confirmée', fulfilled: 'Livrée', cancelled: 'Annulée' },
    },
    catalog: {
      productsTitle: 'Catalogue produits', productSingular: 'produit', productPlural: 'produits',
      newProduct: 'Nouveau produit', addProduct: 'Ajouter un produit',
      searchPlaceholder: 'Nom, SKU, code-barres…', allCategories: 'Toutes catégories',
      emptyProducts: 'Aucun produit', emptyProductsHint: 'Commencez par ajouter votre premier produit.',
      noResults: 'Aucun résultat pour cette recherche.',
      colProduct: 'Produit', colCategory: 'Catégorie', colPrice: 'Prix',
      variantSingular: 'variante', variantPlural: 'variantes',
      selectedSingular: 'produit sélectionné', selectedPlural: 'produits sélectionnés',
      copies: 'Copies :', thermal: 'Thermique', a4sheet: 'Planche A4', deselect: 'Désélectionner',
      viewProduct: 'Voir la fiche', printLabel: 'Imprimer étiquette',
      archive: 'Archiver', activate: 'Activer',
      status: { active: 'Actif', draft: 'Brouillon', archived: 'Archivé' },
      categoriesTitle: 'Catégories', categorySingular: 'catégorie', categoryPlural: 'catégories',
      newCategory: 'Nouvelle catégorie', createCategory: 'Créer une catégorie', editCategory: 'Modifier la catégorie',
      emptyCategories: 'Aucune catégorie', emptyCategoriesHint: 'Organisez vos produits en créant votre première catégorie.',
      colParent: 'Parent', colOrder: 'Ordre', statusActive: 'Active', statusInactive: 'Inactive',
      categoryNamePlaceholder: 'Ex : Vêtements', parentCategory: 'Catégorie parente', noParent: 'Aucune (catégorie racine)',
      descriptionPlaceholder: 'Description courte…', displayOrder: "Ordre d'affichage",
      nameRequired: 'Le nom est requis', confirmDeleteCategory: 'Supprimer "{name}" ? Cette action est irréversible.',
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
      genericError: 'An error occurred.', allStatuses: 'All statuses',
      date: 'Date', amount: 'Amount', note: 'Note', previous: 'Previous', next: 'Next',
      allWarehouses: 'All warehouses', product: 'Product', quantity: 'Qty', view: 'View',
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
    deliveries: {
      title: 'Deliveries', itemSingular: 'delivery', itemPlural: 'deliveries',
      new: 'New delivery', empty: 'No delivery',
      emptyFiltered: 'No delivery with this status.', emptyDefault: 'Deliveries will appear here.',
      colOrder: 'Order', colCarrier: 'Carrier', colTracking: 'Tracking', colDates: 'Dates',
      created: 'Created {date}', dispatch: 'Dispatch', markDelivered: 'Delivered', fail: 'Fail',
      carrier: 'Carrier', tracking: 'Tracking number',
      failTitle: 'Report a failure', failReason: 'Failure reason',
      failReasonPlaceholder: 'E.g. address not found, customer absent…', failConfirm: 'Confirm failure',
      status: { pending: 'Pending', dispatched: 'Dispatched', in_transit: 'In transit', delivered: 'Delivered', failed: 'Failed' },
    },
    payments: {
      title: 'Payments', recordSingular: 'record', recordPlural: 'records',
      new: 'New payment', allMethods: 'All methods', allWarehouses: 'All warehouses',
      empty: 'No payment', emptyFiltered: 'No payment for this method.',
      emptyDefault: 'Recorded payments will appear here.',
      colOrder: 'Order', colMethod: 'Method', colReference: 'Reference',
      void: 'Void', voidTitle: 'Void this payment', voidConfirm: 'Void this {amount} payment?',
      totalShown: 'Displayed total', modalTitle: 'Record a payment',
      methodLabel: 'Payment method', referenceLabel: 'Transaction reference',
      referencePlaceholder: 'Receipt no., TX ID…',
      method: { cash: 'Cash', mobile_money: 'Mobile Money', card: 'Card', transfer: 'Transfer', cheque: 'Cheque' },
    },
    reports: {
      salesTitle: 'Sales report', salesSubtitle: 'Revenue, top products and payment methods',
      stockTitle: 'Stock report', stockSubtitle: 'Stock value, stockouts and low-stock products',
      kpiRevenue: 'Total revenue — {period}', kpiPayments: 'Recorded payments',
      kpiAvgBasket: 'Average basket', kpiTopMethod: 'Top method',
      revenueChartTitle: 'Revenue trend', topProducts: 'Top products',
      noSales: 'No sales in this period.', colRevenue: 'Revenue',
      byMethodTitle: 'Breakdown by payment method', noPayments: 'No payment in this period.',
      paymentWord: 'payment', paymentsWord: 'payments', chartEmpty: 'No data.',
      loadError: 'Failed to load data.', viewAlerts: 'View alerts',
      kpiStockValue: 'Stock value (cost)', kpiSkus: 'Tracked SKUs',
      kpiLowStock: 'Low-stock alerts', kpiOutOfStock: 'Out of stock',
      lowStockTitle: 'Low-stock products', noLowStock: 'No low-stock product.',
      movementsTitle: 'Movements — last 30 days', noMovements: 'No movement recorded.',
      operationWord: 'operation', operationsWord: 'operations', unitsWord: 'units',
      period: { '7d': '7d', '30d': '30d', '90d': '90d', '1y': '1y' },
      periodLong: { '7d': 'Last 7 days', '30d': 'Last 30 days', '90d': 'Last 90 days', '1y': 'Last year' },
      movement: { in: 'Inflows', out: 'Outflows', adjustment: 'Adjustments', return: 'Returns' },
    },
    orders: {
      title: 'Orders', new: 'New order', searchPlaceholder: 'Order no., customer...',
      dateFrom: 'From', dateTo: 'To',
      loadErrorTitle: 'Loading error', loadError: 'Could not load orders.',
      empty: 'No order', emptyHint: 'Orders will appear here.',
      colNumber: 'No.', colItems: 'Items', colTotal: 'Total',
      articleWord: 'item', articlesWord: 'items',
      tab: { all: 'All', draft: 'Drafts', confirmed: 'Confirmed', fulfilled: 'Fulfilled', cancelled: 'Cancelled' },
      status: { draft: 'Draft', confirmed: 'Confirmed', fulfilled: 'Fulfilled', cancelled: 'Cancelled' },
    },
    catalog: {
      productsTitle: 'Product catalog', productSingular: 'product', productPlural: 'products',
      newProduct: 'New product', addProduct: 'Add a product',
      searchPlaceholder: 'Name, SKU, barcode…', allCategories: 'All categories',
      emptyProducts: 'No product', emptyProductsHint: 'Start by adding your first product.',
      noResults: 'No result for this search.',
      colProduct: 'Product', colCategory: 'Category', colPrice: 'Price',
      variantSingular: 'variant', variantPlural: 'variants',
      selectedSingular: 'product selected', selectedPlural: 'products selected',
      copies: 'Copies:', thermal: 'Thermal', a4sheet: 'A4 sheet', deselect: 'Deselect',
      viewProduct: 'View product', printLabel: 'Print label',
      archive: 'Archive', activate: 'Activate',
      status: { active: 'Active', draft: 'Draft', archived: 'Archived' },
      categoriesTitle: 'Categories', categorySingular: 'category', categoryPlural: 'categories',
      newCategory: 'New category', createCategory: 'Create a category', editCategory: 'Edit category',
      emptyCategories: 'No category', emptyCategoriesHint: 'Organize your products by creating your first category.',
      colParent: 'Parent', colOrder: 'Order', statusActive: 'Active', statusInactive: 'Inactive',
      categoryNamePlaceholder: 'E.g. Clothing', parentCategory: 'Parent category', noParent: 'None (root category)',
      descriptionPlaceholder: 'Short description…', displayOrder: 'Display order',
      nameRequired: 'Name is required', confirmDeleteCategory: 'Delete "{name}"? This action cannot be undone.',
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
