<template>
  <section>
    <div class="panel-header-row">
      <div>
        <h3>Rôles &amp; permissions</h3>
        <p>
          Les rôles de base sont partagés et en lecture seule. Créez des rôles
          personnalisés pour accorder des permissions précises à votre équipe.
        </p>
      </div>
      <button class="btn-sm-pad" @click="openCreate">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
          <path d="M7 1v12M1 7h12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
        </svg>
        Créer un rôle
      </button>
    </div>

    <div v-if="loading" class="state-loading">Chargement…</div>

    <div v-else-if="error" class="form-error">{{ error }}</div>

    <div v-else class="roles-grid">
      <article
        v-for="role in sortedRoles"
        :key="role.id"
        class="role-card"
        :class="{ 'role-card--base': !role.is_custom }"
      >
        <div class="role-card-head">
          <div class="role-card-title">
            {{ prettyRoleName(role.name) }}
            <span class="role-tag" :class="role.is_custom ? 'role-tag--custom' : 'role-tag--base'">
              {{ role.is_custom ? 'Personnalisé' : 'Base' }}
            </span>
          </div>
          <div v-if="role.is_custom" class="role-card-actions">
            <button class="link-btn" @click="openEdit(role)">Modifier</button>
            <button class="link-btn link-btn--danger" @click="confirmDelete(role)">Supprimer</button>
          </div>
        </div>
        <p class="role-card-count">
          {{ role.permissions.length }} permission{{ role.permissions.length !== 1 ? 's' : '' }}
        </p>
        <div v-if="role.permissions.length" class="role-card-perms">
          <span v-for="p in role.permissions.slice(0, 6)" :key="p" class="perm-chip">{{ actionLabel(p) }}</span>
          <span v-if="role.permissions.length > 6" class="perm-chip perm-chip--more">
            +{{ role.permissions.length - 6 }}
          </span>
        </div>
        <p v-else class="role-card-empty">Aucune permission.</p>
      </article>
    </div>

    <!-- ── Create / edit modal ──────────────────────────────────────────── -->
    <div v-if="modal.open" class="modal-overlay" @click.self="closeModal">
      <div class="modal modal--wide" role="dialog" aria-modal="true" v-focus-trap="closeModal">
        <div class="modal-header">
          <h3>{{ modal.editing ? 'Modifier le rôle' : 'Nouveau rôle personnalisé' }}</h3>
          <button class="modal-close" @click="closeModal">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-row">
            <label>Nom du rôle <span class="req">*</span></label>
            <input
              v-model="form.name"
              class="form-input"
              placeholder="ex. Responsable dépôt"
              maxlength="50"
            />
          </div>

          <div v-if="!grantable.length" class="modal-desc">
            Aucune permission accordable avec votre plan actuel.
          </div>

          <div v-for="group in groupedGrantable" :key="group.module" class="perm-group">
            <div class="perm-group-head">
              <span class="perm-group-title">{{ moduleLabel(group.module) }}</span>
              <button type="button" class="link-btn" @click="toggleGroup(group)">
                {{ allChecked(group) ? 'Tout décocher' : 'Tout cocher' }}
              </button>
            </div>
            <div class="perm-group-grid">
              <label v-for="perm in group.perms" :key="perm" class="perm-check">
                <input type="checkbox" :value="perm" v-model="form.permissions" />
                <span>{{ actionLabel(perm) }}</span>
              </label>
            </div>
          </div>

          <div v-if="modal.error" class="form-error">{{ modal.error }}</div>
        </div>
        <div class="modal-footer">
          <button class="btn-cancel" @click="closeModal">Annuler</button>
          <button class="btn-submit" :disabled="modal.saving || !form.name.trim()" @click="save">
            {{ modal.saving ? 'Enregistrement…' : (modal.editing ? 'Enregistrer' : 'Créer le rôle') }}
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { roleService, type TenantRole } from '../services/roleService'

const roles = ref<TenantRole[]>([])
const grantable = ref<string[]>([])
const loading = ref(true)
const error = ref('')

const modal = reactive({ open: false, editing: false, saving: false, error: '', id: '' as string | number })
const form = reactive<{ name: string; permissions: string[] }>({ name: '', permissions: [] })

const sortedRoles = computed(() =>
  [...roles.value].sort((a, b) => Number(b.is_custom) - Number(a.is_custom) || a.name.localeCompare(b.name)),
)

interface PermGroup { module: string; perms: string[] }

const groupedGrantable = computed<PermGroup[]>(() => {
  const map = new Map<string, string[]>()
  for (const perm of grantable.value) {
    const mod = perm.includes('.') ? perm.slice(0, perm.indexOf('.')) : perm
    if (!map.has(mod)) map.set(mod, [])
    map.get(mod)!.push(perm)
  }
  return [...map.entries()]
    .map(([module, perms]) => ({ module, perms: perms.sort() }))
    .sort((a, b) => moduleLabel(a.module).localeCompare(moduleLabel(b.module)))
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await roleService.list()
    roles.value = res.data
    grantable.value = res.grantable
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Impossible de charger les rôles.'
  } finally {
    loading.value = false
  }
}

function openCreate() {
  modal.editing = false
  modal.id = ''
  modal.error = ''
  form.name = ''
  form.permissions = []
  modal.open = true
}

function openEdit(role: TenantRole) {
  modal.editing = true
  modal.id = role.id
  modal.error = ''
  form.name = role.name
  form.permissions = [...role.permissions]
  modal.open = true
}

function closeModal() {
  modal.open = false
}

function allChecked(group: PermGroup): boolean {
  return group.perms.every(p => form.permissions.includes(p))
}

function toggleGroup(group: PermGroup) {
  if (allChecked(group)) {
    form.permissions = form.permissions.filter(p => !group.perms.includes(p))
  } else {
    const set = new Set(form.permissions)
    group.perms.forEach(p => set.add(p))
    form.permissions = [...set]
  }
}

async function save() {
  if (!form.name.trim()) return
  modal.saving = true
  modal.error = ''
  try {
    if (modal.editing) {
      await roleService.update(modal.id, { name: form.name.trim(), permissions: form.permissions })
    } else {
      await roleService.create({ name: form.name.trim(), permissions: form.permissions })
    }
    modal.open = false
    await load()
  } catch (e: any) {
    modal.error = e?.response?.data?.message
      ?? (Object.values(e?.response?.data?.errors ?? {})?.[0] as string[] | undefined)?.[0]
      ?? 'Enregistrement impossible.'
  } finally {
    modal.saving = false
  }
}

async function confirmDelete(role: TenantRole) {
  if (!confirm(`Supprimer le rôle « ${role.name} » ? Les membres concernés perdront ces permissions.`)) return
  try {
    await roleService.remove(role.id)
    await load()
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Suppression impossible.'
  }
}

// ── Labels ──────────────────────────────────────────────────────────────────
const MODULE_LABELS: Record<string, string> = {
  dashboard: 'Tableau de bord', catalog: 'Catalogue', products: 'Produits',
  inventory: 'Stock', stock: 'Stock (mouvements)', orders: 'Commandes',
  customers: 'Clients', payments: 'Paiements', delivery: 'Livraisons',
  deliveries: 'Livraisons (terrain)', suppliers: 'Fournisseurs',
  import_export: 'Import / Export', reports: 'Rapports', marketplace: 'Marketplace',
  pos: 'Point de vente', users: 'Utilisateurs', roles: 'Rôles', modules: 'Modules',
  subscriptions: 'Abonnement', tenants: 'Espace de travail', catalog_read: 'Catalogue',
}

const ACTION_LABELS: Record<string, string> = {
  view: 'Consulter', read: 'Lire', create: 'Créer', update: 'Modifier',
  delete: 'Supprimer', export: 'Exporter', manage: 'Gérer', adjust: 'Ajuster le stock',
  receive: 'Réceptionner', transfer: 'Transférer', audit: 'Auditer',
  print_labels: 'Imprimer étiquettes', confirm: 'Confirmer', archive: 'Archiver',
  duplicate: 'Dupliquer', access: 'Accéder', open: 'Ouvrir', close: 'Clôturer',
  sale: 'Vendre', refund: 'Rembourser',
}

function moduleLabel(mod: string): string {
  return MODULE_LABELS[mod] ?? mod.charAt(0).toUpperCase() + mod.slice(1).replace(/_/g, ' ')
}

/** Friendly label for a permission shown inside its module group (the action part). */
function actionLabel(perm: string): string {
  const action = perm.includes('.') ? perm.slice(perm.indexOf('.') + 1) : perm
  if (ACTION_LABELS[action]) return ACTION_LABELS[action]
  return action.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

function prettyRoleName(name: string): string {
  const base: Record<string, string> = {
    admin: 'Admin', manager: 'Manager', member: 'Membre', viewer: 'Lecteur',
    agent: 'Agent', cashier: 'Caissier', commercial: 'Commercial', delivery: 'Livreur',
  }
  return base[name] ?? name
}

onMounted(load)

defineExpose({ load })
</script>

<style scoped>
.panel-header-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.5rem;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid var(--gray-100);
}
.panel-header-row h3 { font-size: var(--text-lg); font-weight: 600; color: var(--gray-900); margin: 0 0 0.25rem; }
.panel-header-row p { font-size: var(--text-sm); color: var(--gray-500); margin: 0; max-width: 60ch; }

.btn-sm-pad {
  display: inline-flex; align-items: center; gap: 0.375rem;
  padding: 0.5rem 0.875rem; font-size: var(--text-sm); font-weight: 600;
  border: none; border-radius: var(--radius-md); background: var(--brand-primary);
  color: white; cursor: pointer; white-space: nowrap; flex-shrink: 0; transition: opacity 0.15s;
}
.btn-sm-pad:hover { opacity: 0.88; }

.roles-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 1rem;
}

.role-card {
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-lg);
  padding: 1rem 1.1rem;
  background: white;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.role-card--base { background: var(--gray-50); }

.role-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.5rem; }
.role-card-title { font-size: var(--text-base); font-weight: 700; color: var(--gray-900); display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }

.role-tag {
  font-size: var(--text-xs); font-weight: 600; padding: 1px 7px; border-radius: var(--radius-full);
  text-transform: uppercase; letter-spacing: 0.04em;
}
.role-tag--custom { background: var(--brand-primary-bg); color: var(--brand-primary-dark); }
.role-tag--base { background: var(--gray-200); color: var(--gray-500); }

.role-card-actions { display: flex; gap: 0.5rem; flex-shrink: 0; }
.link-btn { background: none; border: none; font-size: var(--text-xs); font-weight: 600; color: var(--brand-secondary, #3b82f6); cursor: pointer; padding: 0; }
.link-btn:hover { text-decoration: underline; }
.link-btn--danger { color: #dc2626; }

.role-card-count { font-size: var(--text-xs); color: var(--gray-500); margin: 0; }
.role-card-perms { display: flex; flex-wrap: wrap; gap: 0.3rem; }
.role-card-empty { font-size: var(--text-xs); color: var(--gray-400); margin: 0; font-style: italic; }

.perm-chip {
  font-size: var(--text-xs); background: var(--gray-100); color: var(--gray-600);
  padding: 2px 7px; border-radius: var(--radius-sm, 4px);
}
.perm-chip--more { background: var(--gray-200); font-weight: 600; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: flex; align-items: center; justify-content: center; z-index: 2000; padding: 1rem; }
.modal { background: white; border-radius: var(--radius-lg); width: 100%; max-width: 520px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,.2); }
.modal--wide { max-width: 640px; }
.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--gray-100); }
.modal-header h3 { font-size: var(--text-base); font-weight: 700; color: var(--gray-900); margin: 0; }
.modal-close { background: none; border: none; font-size: 1.125rem; color: var(--gray-400); cursor: pointer; padding: 0.25rem; }
.modal-close:hover { color: var(--gray-700); }
.modal-body { padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
.modal-desc { font-size: var(--text-sm); color: var(--gray-500); margin: 0; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.75rem; padding: 1rem 1.5rem; border-top: 1px solid var(--gray-100); }

.form-row { display: flex; flex-direction: column; gap: 0.375rem; }
.form-row label { font-size: var(--text-sm); font-weight: 500; color: var(--gray-600); }
.form-input { border: 1px solid var(--gray-300); border-radius: var(--radius-md); padding: 0.5rem 0.75rem; font-size: var(--text-sm); color: var(--gray-900); outline: none; transition: border-color 0.15s; }
.form-input:focus { border-color: var(--brand-primary); }
.req { color: var(--color-error, #ef4444); font-weight: 600; }

.perm-group { border: 1px solid var(--gray-200); border-radius: var(--radius-md); overflow: hidden; }
.perm-group-head { display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0.75rem; background: var(--gray-50); border-bottom: 1px solid var(--gray-100); }
.perm-group-title { font-size: var(--text-xs); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500); }
.perm-group-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 0.4rem 0.75rem; padding: 0.7rem 0.75rem; }
.perm-check { display: flex; align-items: center; gap: 0.4rem; font-size: var(--text-sm); color: var(--gray-700); cursor: pointer; }
.perm-check input { cursor: pointer; }

.state-loading { padding: 2rem; text-align: center; color: var(--gray-400); font-size: var(--text-sm); }
.form-error { background: #fff5f5; border: 1px solid #fecaca; border-radius: var(--radius-sm, 4px); padding: 0.5rem 0.75rem; font-size: var(--text-sm); color: #ef4444; }

.btn-cancel { padding: 0.5rem 1rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); background: white; color: var(--gray-600); font-size: var(--text-sm); cursor: pointer; }
.btn-cancel:hover { background: var(--gray-50); }
.btn-submit { padding: 0.5rem 1.25rem; border: none; border-radius: var(--radius-md); background: var(--brand-secondary, #3b82f6); color: white; font-size: var(--text-sm); font-weight: 600; cursor: pointer; }
.btn-submit:hover:not(:disabled) { opacity: 0.88; }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
