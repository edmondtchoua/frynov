<template>
  <div>
    <div class="page-header">
      <h2>Mon profil</h2>
      <p class="page-subtitle">Gérez vos informations personnelles et la sécurité de votre compte.</p>
    </div>

    <div class="profile-layout">

      <!-- ── Left column : identity card ────────────────────────── -->
      <div class="profile-card profile-card--id">
        <div class="avatar-wrap">
          <div class="avatar-xl">{{ initials }}</div>
        </div>
        <div class="id-name">{{ auth.user?.name }}</div>
        <div class="id-email">{{ auth.user?.email }}</div>
        <div class="id-roles">
          <span
            v-for="role in auth.user?.roles ?? []"
            :key="role"
            class="role-chip"
            :class="`role-chip--${role}`"
          >{{ roleLabel(role) }}</span>
        </div>
        <div v-if="auth.user?.tenant" class="id-tenant">
          <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
            <path d="M1.5 12V5L6.5 1.5 11.5 5V12" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>
            <path d="M4.5 12V8.5h4V12" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>
          </svg>
          {{ auth.user.tenant.name }}
        </div>
        <div v-if="auth.user?.is_super_admin" class="id-superadmin">
          <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
            <path d="M6.5 1L8 4.5H12L9 7 10 11 6.5 9 3 11 4 7 1 4.5H5L6.5 1Z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/>
          </svg>
          Super administrateur
        </div>
      </div>

      <!-- ── Right column : forms ────────────────────────────────── -->
      <div class="profile-forms">

        <!-- Edit profile -->
        <div class="profile-section">
          <div class="section-header">
            <h3>Informations personnelles</h3>
            <p>Nom et adresse email associés à votre compte.</p>
          </div>

          <form @submit.prevent="saveProfile" class="profile-form">
            <div class="form-row-2">
              <div class="form-group">
                <label class="form-label">Nom complet</label>
                <input v-model="profileForm.name" class="form-input" placeholder="Votre nom" required />
              </div>
              <div class="form-group">
                <label class="form-label">Adresse email</label>
                <input v-model="profileForm.email" type="email" class="form-input" placeholder="vous@exemple.com" required />
              </div>
            </div>

            <Transition name="fade">
              <div v-if="profileMsg" class="form-feedback" :class="profileError ? 'form-feedback--err' : 'form-feedback--ok'">
                {{ profileMsg }}
              </div>
            </Transition>

            <div class="form-actions">
              <button type="submit" class="btn btn-primary" :disabled="profileSaving">
                <span v-if="profileSaving" class="spinner-sm spinner-white"></span>
                {{ profileSaving ? 'Enregistrement…' : 'Enregistrer' }}
              </button>
            </div>
          </form>
        </div>

        <!-- Change password -->
        <div class="profile-section">
          <div class="section-header">
            <h3>Changer le mot de passe</h3>
            <p>Après modification, toutes les autres sessions seront révoquées.</p>
          </div>

          <form @submit.prevent="savePassword" class="profile-form">
            <div class="form-group">
              <label class="form-label">Mot de passe actuel</label>
              <div class="input-password-wrap">
                <input
                  v-model="pwdForm.current"
                  :type="showCurrent ? 'text' : 'password'"
                  class="form-input"
                  :class="{ error: pwdErrors.current }"
                  autocomplete="current-password"
                />
                <button type="button" class="toggle-pass" @click="showCurrent = !showCurrent">
                  <EyeIcon :open="showCurrent" />
                </button>
              </div>
              <span v-if="pwdErrors.current" class="form-error">{{ pwdErrors.current }}</span>
            </div>

            <div class="form-row-2">
              <div class="form-group">
                <label class="form-label">Nouveau mot de passe</label>
                <div class="input-password-wrap">
                  <input
                    v-model="pwdForm.password"
                    :type="showNew ? 'text' : 'password'"
                    class="form-input"
                    :class="{ error: pwdErrors.password }"
                    autocomplete="new-password"
                  />
                  <button type="button" class="toggle-pass" @click="showNew = !showNew">
                    <EyeIcon :open="showNew" />
                  </button>
                </div>
                <span v-if="pwdErrors.password" class="form-error">{{ pwdErrors.password }}</span>
                <!-- Strength indicator -->
                <div class="pwd-strength" v-if="pwdForm.password">
                  <div
                    v-for="i in 4" :key="i"
                    class="pwd-strength-bar"
                    :class="{ active: i <= pwdStrength }"
                    :style="{ background: pwdStrengthColor }"
                  />
                  <span class="pwd-strength-label">{{ pwdStrengthLabel }}</span>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Confirmer le mot de passe</label>
                <div class="input-password-wrap">
                  <input
                    v-model="pwdForm.confirmation"
                    :type="showConfirm ? 'text' : 'password'"
                    class="form-input"
                    :class="{ error: pwdErrors.confirmation }"
                    autocomplete="new-password"
                  />
                  <button type="button" class="toggle-pass" @click="showConfirm = !showConfirm">
                    <EyeIcon :open="showConfirm" />
                  </button>
                </div>
                <span v-if="pwdErrors.confirmation" class="form-error">{{ pwdErrors.confirmation }}</span>
              </div>
            </div>

            <Transition name="fade">
              <div v-if="pwdMsg" class="form-feedback" :class="pwdMsgError ? 'form-feedback--err' : 'form-feedback--ok'">
                {{ pwdMsg }}
              </div>
            </Transition>

            <div class="form-actions">
              <button
                type="submit"
                class="btn btn-primary"
                :disabled="pwdSaving || !pwdForm.current || !pwdForm.password || !pwdForm.confirmation"
              >
                <span v-if="pwdSaving" class="spinner-sm spinner-white"></span>
                {{ pwdSaving ? 'Modification…' : 'Modifier le mot de passe' }}
              </button>
            </div>
          </form>
        </div>

        <!-- Active sessions -->
        <div class="profile-section">
          <div class="section-header">
            <h3>Sessions actives</h3>
            <p>Les autres appareils connectés à votre compte.</p>
          </div>

          <div v-if="sessionsLoading" class="state-loading">Chargement…</div>
          <div v-else class="sessions-list">
            <div
              v-for="s in sessions"
              :key="s.id"
              class="session-row"
              :class="{ 'session-row--current': s.is_current }"
            >
              <div class="session-icon">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                  <rect x="1" y="3" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.3"/>
                  <path d="M5 12.5h6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                </svg>
              </div>
              <div class="session-info">
                <span class="session-name">
                  {{ s.name }}
                  <span v-if="s.is_current" class="session-current-badge">Session actuelle</span>
                </span>
                <span class="session-date">
                  Dernière activité {{ s.last_used_at ? fmtDate(s.last_used_at) : 'jamais' }} —
                  Créée le {{ fmtDate(s.created_at) }}
                </span>
              </div>
              <button
                v-if="!s.is_current"
                class="btn btn-sm btn-ghost session-revoke"
                @click="revokeSession(s.id)"
              >Révoquer</button>
            </div>
          </div>
        </div>

      </div><!-- /profile-forms -->
    </div><!-- /profile-layout -->
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, defineComponent, h } from 'vue'
import { formatDateTime } from '@/shared/utils/date'
import { useAuthStore } from '@/stores/auth'
import client from '@/api/client'

const auth = useAuthStore()

// ── Eye icon helper ───────────────────────────────────────────────────────────
const EyeIcon = defineComponent({
  props: { open: Boolean },
  render({ open }: { open: boolean }) {
    return open
      ? h('svg', { width: 15, height: 15, viewBox: '0 0 16 16', fill: 'none' }, [
          h('path', { d: 'M2 2l12 12M6.5 6.7A2 2 0 009.4 9.5M4.4 4.5C2.8 5.5 1 8 1 8s2.5 5 7 5c1.4 0 2.7-.4 3.7-1M7 3.1C7.3 3 7.7 3 8 3c4.5 0 7 5 7 5s-.8 1.5-2 2.8', stroke: 'currentColor', 'stroke-width': '1.4', 'stroke-linecap': 'round' }),
        ])
      : h('svg', { width: 15, height: 15, viewBox: '0 0 16 16', fill: 'none' }, [
          h('path', { d: 'M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z', stroke: 'currentColor', 'stroke-width': '1.4' }),
          h('circle', { cx: 8, cy: 8, r: 2, stroke: 'currentColor', 'stroke-width': '1.4' }),
        ])
  },
})

// ── Computed ──────────────────────────────────────────────────────────────────
const initials = computed(() => {
  const name = auth.user?.name ?? ''
  return name.split(' ').slice(0, 2).map(w => w[0]?.toUpperCase() ?? '').join('') || 'U'
})

function roleLabel(r: string): string {
  return { admin: 'Admin', manager: 'Manager', member: 'Membre', viewer: 'Lecteur', 'super-admin': 'Super Admin' }[r] ?? r
}

// ── Profile form ──────────────────────────────────────────────────────────────
const profileForm  = reactive({ name: auth.user?.name ?? '', email: auth.user?.email ?? '' })
const profileSaving = ref(false)
const profileMsg   = ref('')
const profileError = ref(false)

async function saveProfile() {
  profileMsg.value = ''
  profileSaving.value = true
  try {
    const { data } = await client.patch('/api/me/profile', {
      name:  profileForm.name,
      email: profileForm.email,
    })
    profileError.value = false
    profileMsg.value   = data.message ?? 'Profil mis à jour.'
    // Update store
    if (auth.user) {
      auth.user.name  = profileForm.name
      auth.user.email = profileForm.email
    }
  } catch (err: any) {
    profileError.value = true
    const msgs = err?.response?.data?.errors ?? {}
    profileMsg.value   = Object.values(msgs).flat().join(' ') || err?.response?.data?.message || 'Erreur.'
  } finally {
    profileSaving.value = false
    setTimeout(() => { profileMsg.value = '' }, 4000)
  }
}

// ── Password form ─────────────────────────────────────────────────────────────
const pwdForm     = reactive({ current: '', password: '', confirmation: '' })
const pwdErrors   = reactive<Record<string, string>>({})
const pwdMsg      = ref('')
const pwdMsgError = ref(false)
const pwdSaving   = ref(false)
const showCurrent = ref(false)
const showNew     = ref(false)
const showConfirm = ref(false)

const pwdStrength = computed((): number => {
  const p = pwdForm.password
  if (!p) return 0
  let score = 0
  if (p.length >= 8)               score++
  if (/[A-Z]/.test(p))             score++
  if (/[0-9]/.test(p))             score++
  if (/[^A-Za-z0-9]/.test(p))      score++
  return score
})

const pwdStrengthLabel = computed(() => {
  const labels = ['', 'Faible', 'Moyen', 'Fort', 'Très fort']
  return labels[pwdStrength.value] ?? ''
})

const pwdStrengthColor = computed(() => {
  const colors = ['', '#ef4444', '#f59e0b', '#10b981', '#059669']
  return colors[pwdStrength.value] ?? ''
})

async function savePassword() {
  Object.keys(pwdErrors).forEach(k => delete (pwdErrors as any)[k])
  pwdMsg.value   = ''

  if (pwdForm.password !== pwdForm.confirmation) {
    pwdErrors.confirmation = 'Les mots de passe ne correspondent pas.'
    return
  }

  pwdSaving.value = true
  try {
    const { data } = await client.post('/api/me/password', {
      current_password:              pwdForm.current,
      password:                      pwdForm.password,
      password_confirmation:         pwdForm.confirmation,
    })
    pwdMsgError.value = false
    pwdMsg.value      = data.message ?? 'Mot de passe modifié.'
    Object.assign(pwdForm, { current: '', password: '', confirmation: '' })
    await loadSessions() // refresh sessions list
  } catch (err: any) {
    pwdMsgError.value = true
    const errs = err?.response?.data?.errors ?? {}
    Object.assign(pwdErrors, Object.fromEntries(
      Object.entries(errs).map(([k, v]: any) => [k, v[0]])
    ))
    pwdMsg.value = err?.response?.data?.message ?? 'Erreur.'
  } finally {
    pwdSaving.value = false
    setTimeout(() => { pwdMsg.value = '' }, 5000)
  }
}

// ── Sessions ──────────────────────────────────────────────────────────────────
interface Session { id: number; name: string; last_used_at: string | null; created_at: string; is_current: boolean }

const sessions        = ref<Session[]>([])
const sessionsLoading = ref(true)

async function loadSessions() {
  sessionsLoading.value = true
  try {
    const { data } = await client.get('/api/me/sessions')
    sessions.value = data.data
  } finally {
    sessionsLoading.value = false
  }
}

async function revokeSession(id: number) {
  if (!confirm('Révoquer cette session ?')) return
  await client.delete(`/api/me/sessions/${id}`)
  sessions.value = sessions.value.filter(s => s.id !== id)
}

const fmtDate = formatDateTime

onMounted(loadSessions)
</script>

<style scoped>
.page-subtitle {
  color: var(--gray-500);
  font-size: var(--text-sm);
  margin-top: 0.25rem;
}

/* ── Layout ──────────────────────────────────────────────────────────────── */
.profile-layout {
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: 1.5rem;
  align-items: flex-start;
}

@media (max-width: 768px) {
  .profile-layout { grid-template-columns: 1fr; }
}

/* ── Identity card ───────────────────────────────────────────────────────── */
.profile-card--id {
  background: white;
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-lg);
  padding: 1.75rem 1rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  text-align: center;
  position: sticky;
  top: calc(var(--topbar-height) + 1rem);
}

.avatar-xl {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  font-weight: 700;
  color: white;
  margin-bottom: 0.5rem;
  box-shadow: 0 4px 16px rgba(16,185,129,0.3);
}

.id-name  { font-size: var(--text-base); font-weight: 700; color: var(--gray-900); }
.id-email { font-size: var(--text-xs); color: var(--gray-500); word-break: break-all; }

.id-roles { display: flex; flex-wrap: wrap; gap: 0.375rem; justify-content: center; margin-top: 0.25rem; }

.role-chip {
  font-size: 0.6875rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: var(--radius-sm);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.role-chip--admin       { background: #fef2f2; color: #991b1b; }
.role-chip--super-admin { background: #fef2f2; color: #991b1b; }
.role-chip--manager     { background: #eff6ff; color: #1d4ed8; }
.role-chip--member      { background: #f0fdf4; color: #166534; }
.role-chip--viewer      { background: var(--gray-100); color: var(--gray-500); }

.id-tenant, .id-superadmin {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: var(--text-xs);
  color: var(--gray-500);
  margin-top: 0.25rem;
}
.id-superadmin { color: #ef4444; font-weight: 600; }

/* ── Form sections ───────────────────────────────────────────────────────── */
.profile-forms {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.profile-section {
  background: white;
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-lg);
  padding: 1.75rem 2rem;
}

.section-header {
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--gray-100);
}
.section-header h3 {
  font-size: var(--text-base);
  font-weight: 700;
  color: var(--gray-900);
  margin: 0 0 0.25rem;
}
.section-header p {
  font-size: var(--text-sm);
  color: var(--gray-500);
  margin: 0;
}

.profile-form { display: flex; flex-direction: column; gap: 1rem; }

.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

@media (max-width: 640px) {
  .form-row-2 { grid-template-columns: 1fr; }
}

.form-group { display: flex; flex-direction: column; gap: 0.375rem; }

.form-feedback {
  padding: 0.5rem 0.875rem;
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-weight: 500;
}
.form-feedback--ok  { background: var(--brand-primary-bg); color: var(--brand-primary-dark); }
.form-feedback--err { background: var(--color-error-bg); color: #991b1b; }

.form-actions { display: flex; gap: 0.75rem; }

/* Password input wrapper */
.input-password-wrap { position: relative; }
.input-password-wrap .form-input { padding-right: 2.5rem; }
.toggle-pass {
  position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer; color: var(--gray-400);
  display: flex; align-items: center; padding: 0;
  transition: color 0.15s;
}
.toggle-pass:hover { color: var(--gray-700); }

/* Password strength */
.pwd-strength {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  margin-top: 0.375rem;
}
.pwd-strength-bar {
  flex: 1;
  height: 3px;
  border-radius: 2px;
  background: var(--gray-200);
  transition: background 0.3s ease;
}
.pwd-strength-bar.active { background: currentColor; }
.pwd-strength-label {
  font-size: var(--text-xs);
  color: var(--gray-500);
  white-space: nowrap;
  min-width: 55px;
  text-align: right;
}

/* ── Sessions ────────────────────────────────────────────────────────────── */
.sessions-list { display: flex; flex-direction: column; gap: 0.75rem; }

.session-row {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  padding: 0.75rem 1rem;
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-md);
  background: var(--gray-50);
  transition: border-color 0.15s;
}

.session-row--current {
  border-color: var(--brand-primary-light);
  background: var(--brand-primary-bg);
}

.session-icon {
  width: 32px; height: 32px;
  background: white;
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-md);
  display: flex; align-items: center; justify-content: center;
  color: var(--gray-500);
  flex-shrink: 0;
}

.session-info { flex: 1; min-width: 0; }

.session-name {
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--gray-800);
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.session-current-badge {
  font-size: var(--text-xs);
  font-weight: 600;
  background: var(--brand-primary);
  color: white;
  padding: 1px 6px;
  border-radius: var(--radius-full);
}

.session-date {
  display: block;
  font-size: var(--text-xs);
  color: var(--gray-400);
  margin-top: 2px;
}

.session-revoke { color: var(--color-error); font-size: var(--text-xs); }

.state-loading { color: var(--gray-400); font-size: var(--text-sm); padding: 1rem 0; }
</style>
