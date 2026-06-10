<template>
  <div class="onboarding-page">

    <!-- Header -->
    <header class="onboarding-header">
      <RouterLink to="/" class="onboarding-logo">
        <FrynovLogo variant="light" />
      </RouterLink>
      <RouterLink to="/login" class="btn btn-ghost btn-sm" style="color: var(--gray-500);">
        {{ $t('onboarding.alreadyRegistered') }}
      </RouterLink>
    </header>

    <!-- Progress bar -->
    <div class="progress-track">
      <div class="progress-fill" :style="{ width: progressPercent + '%' }"></div>
    </div>

    <!-- Step container -->
    <main class="onboarding-main">
      <div class="onboarding-card">

        <!-- Step indicator -->
        <div class="step-indicator">
          <span class="step-count">{{ $t('onboarding.stepCount', { step, total: totalSteps }) }}</span>
          <div class="step-dots">
            <div
              v-for="s in totalSteps"
              :key="s"
              class="step-dot"
              :class="{ active: s === step, done: s < step }"
            ></div>
          </div>
        </div>

        <!-- ── STEP 1 — Business type ──────────────────── -->
        <Transition name="slide" mode="out-in">
          <div v-if="step === 1" key="step1" class="step-content">
            <h2>{{ $t('onboarding.step1Title') }}</h2>
            <p class="step-subtitle">{{ $t('onboarding.step1Sub') }}</p>

            <div class="choice-grid">
              <button
                v-for="type in businessTypes"
                :key="type.value"
                class="choice-card"
                :class="{ selected: answers.type === type.value }"
                @click="answers.type = type.value"
              >
                <div class="choice-icon">{{ type.icon }}</div>
                <div class="choice-label">{{ type.label }}</div>
                <div class="choice-desc">{{ type.desc }}</div>
                <div class="choice-check">
                  <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <path d="M2.5 7l3 3 6-6" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </div>
              </button>
            </div>
          </div>
        </Transition>

        <!-- ── STEP 2 — Team size ─────────────────────── -->
        <Transition name="slide" mode="out-in">
          <div v-if="step === 2" key="step2" class="step-content">
            <h2>{{ $t('onboarding.step2Title') }}</h2>
            <p class="step-subtitle">{{ $t('onboarding.step2Sub') }}</p>

            <div class="choice-grid choice-grid--row">
              <button
                v-for="size in teamSizes"
                :key="size.value"
                class="choice-card choice-card--compact"
                :class="{ selected: answers.teamSize === size.value }"
                @click="answers.teamSize = size.value"
              >
                <div class="choice-icon">{{ size.icon }}</div>
                <div class="choice-label">{{ size.label }}</div>
                <div class="choice-desc">{{ size.desc }}</div>
                <div class="choice-check">
                  <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <path d="M2.5 7l3 3 6-6" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </div>
              </button>
            </div>
          </div>
        </Transition>

        <!-- ── STEP 3 — Modules ───────────────────────── -->
        <Transition name="slide" mode="out-in">
          <div v-if="step === 3" key="step3" class="step-content">
            <h2>{{ $t('onboarding.step3Title') }}</h2>
            <p class="step-subtitle">{{ $t('onboarding.step3Sub', { type: selectedTypeName }) }}</p>

            <div class="modules-grid">
              <button
                v-for="mod in availableModules"
                :key="mod.id"
                class="module-toggle"
                :class="{ selected: answers.modules.includes(mod.id), recommended: mod.recommended }"
                @click="toggleModule(mod.id)"
              >
                <div class="module-toggle-icon">{{ mod.icon }}</div>
                <div class="module-toggle-body">
                  <div class="module-toggle-label">
                    {{ mod.label }}
                    <span v-if="mod.recommended" class="module-badge">{{ $t('onboarding.recommended') }}</span>
                  </div>
                  <div class="module-toggle-desc">{{ mod.desc }}</div>
                </div>
                <div class="module-toggle-check">
                  <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <path d="M2.5 7l3 3 6-6" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </div>
              </button>
            </div>
          </div>
        </Transition>

        <!-- ── STEP 4 — Vos besoins ───────────────────── -->
        <Transition name="slide" mode="out-in">
          <div v-if="step === 4" key="step4" class="step-content">
            <h2>{{ $t('onboarding.step4Title') }}</h2>
            <p class="step-subtitle">{{ $t('onboarding.step4Sub') }}</p>

            <div class="needs-list">
              <div class="needs-item">
                <div class="needs-item-body">
                  <div class="needs-item-icon">📦</div>
                  <div>
                    <div class="needs-item-label">{{ $t('onboarding.needs.stock.label') }}</div>
                    <div class="needs-item-desc">{{ $t('onboarding.needs.stock.desc') }}</div>
                  </div>
                </div>
                <button
                  class="toggle-switch"
                  :class="{ on: needs.needs_stock }"
                  @click="needs.needs_stock = !needs.needs_stock"
                  type="button"
                >
                  <span class="toggle-thumb"></span>
                </button>
              </div>

              <div class="needs-item">
                <div class="needs-item-body">
                  <div class="needs-item-icon">🖥️</div>
                  <div>
                    <div class="needs-item-label">{{ $t('onboarding.needs.pos.label') }}</div>
                    <div class="needs-item-desc">{{ $t('onboarding.needs.pos.desc') }}</div>
                  </div>
                </div>
                <button
                  class="toggle-switch"
                  :class="{ on: needs.needs_pos }"
                  @click="needs.needs_pos = !needs.needs_pos"
                  type="button"
                >
                  <span class="toggle-thumb"></span>
                </button>
              </div>

              <div class="needs-item">
                <div class="needs-item-body">
                  <div class="needs-item-icon">🚚</div>
                  <div>
                    <div class="needs-item-label">{{ $t('onboarding.needs.delivery.label') }}</div>
                    <div class="needs-item-desc">{{ $t('onboarding.needs.delivery.desc') }}</div>
                  </div>
                </div>
                <button
                  class="toggle-switch"
                  :class="{ on: needs.needs_delivery }"
                  @click="needs.needs_delivery = !needs.needs_delivery"
                  type="button"
                >
                  <span class="toggle-thumb"></span>
                </button>
              </div>

              <div class="needs-item">
                <div class="needs-item-body">
                  <div class="needs-item-icon">🛍️</div>
                  <div>
                    <div class="needs-item-label">{{ $t('onboarding.needs.ecommerce.label') }}</div>
                    <div class="needs-item-desc">{{ $t('onboarding.needs.ecommerce.desc') }}</div>
                  </div>
                </div>
                <button
                  class="toggle-switch"
                  :class="{ on: needs.needs_ecommerce }"
                  @click="needs.needs_ecommerce = !needs.needs_ecommerce"
                  type="button"
                >
                  <span class="toggle-thumb"></span>
                </button>
              </div>

              <div class="needs-item">
                <div class="needs-item-body">
                  <div class="needs-item-icon">📵</div>
                  <div>
                    <div class="needs-item-label">{{ $t('onboarding.needs.offline.label') }}</div>
                    <div class="needs-item-desc">{{ $t('onboarding.needs.offline.desc') }}</div>
                  </div>
                </div>
                <button
                  class="toggle-switch"
                  :class="{ on: needs.needs_offline }"
                  @click="needs.needs_offline = !needs.needs_offline"
                  type="button"
                >
                  <span class="toggle-thumb"></span>
                </button>
              </div>
            </div>

            <!-- nb_branches slider -->
            <div class="form-group" style="margin-top: 1.5rem; margin-bottom: 0;">
              <label class="form-label">
                {{ $t('onboarding.nbBranches') }}
                <strong class="branch-count">{{ needs.nb_branches }}</strong>
              </label>
              <div class="slider-row">
                <span class="slider-edge">1</span>
                <input
                  type="range"
                  min="1"
                  max="20"
                  step="1"
                  v-model.number="needs.nb_branches"
                  class="range-slider"
                />
                <span class="slider-edge">20</span>
              </div>
              <div class="slider-ticks">
                <span v-for="n in [1,5,10,15,20]" :key="n" class="slider-tick">{{ n }}</span>
              </div>
            </div>
          </div>
        </Transition>

        <!-- ── STEP 5 — Company info ──────────────────── -->
        <Transition name="slide" mode="out-in">
          <div v-if="step === 5" key="step5" class="step-content">
            <h2>{{ $t('onboarding.step5Title') }}</h2>
            <p class="step-subtitle">{{ $t('onboarding.step5Sub') }}</p>

            <div class="form-group">
              <label class="form-label" for="companyName">{{ $t('onboarding.companyName') }} *</label>
              <input
                id="companyName"
                v-model="answers.company"
                type="text"
                class="form-input"
                :class="{ error: step5Errors.company }"
                :placeholder="$t('onboarding.companyNamePlaceholder')"
              />
              <span v-if="step5Errors.company" class="form-error">{{ step5Errors.company }}</span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label class="form-label" for="country">{{ $t('onboarding.country') }}</label>
                <select id="country" v-model="answers.country" class="form-input" @change="onCountryChange">
                  <option v-for="c in countries" :key="c.code" :value="c.code">{{ c.flag }} {{ $t('geo.country.' + c.code) }}</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label" for="currency">{{ $t('onboarding.currency') }}</label>
                <select id="currency" v-model="answers.currency" class="form-input">
                  <option v-for="cur in currencies" :key="cur.code" :value="cur.code">{{ cur.code }} — {{ $t('common.currencyName.' + cur.code) }}</option>
                </select>
              </div>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label" for="timezone">{{ $t('onboarding.timezone') }}</label>
              <select id="timezone" v-model="answers.timezone" class="form-input">
                <option v-for="tz in timezones" :key="tz.value" :value="tz.value">{{ $t('geo.timezone.' + tz.value) }}</option>
              </select>
            </div>

            <!-- UX-08 — "Ce qui sera configuré" : récap avant le provisionnement -->
            <div class="onboard-recap" :aria-label="$t('onboarding.recapTitle')">
              <p class="onboard-recap__title">{{ $t('onboarding.recapTitle') }}</p>
              <ul class="onboard-recap__list">
                <li><span>{{ $t('onboarding.recapCompany') }}</span><strong>{{ answers.company || '—' }}</strong></li>
                <li><span>{{ $t('onboarding.recapCurrency') }}</span><strong>{{ answers.currency }}</strong></li>
                <li><span>{{ $t('onboarding.recapCountryTz') }}</span><strong>{{ answers.country }} · {{ answers.timezone }}</strong></li>
                <li><span>{{ $t('onboarding.recapModules') }}</span><strong>{{ $t('onboarding.modulesEnabled', { count: answers.modules.length }) }}</strong></li>
              </ul>
              <div v-if="answers.modules.length" class="onboard-recap__chips">
                <span v-for="mid in answers.modules" :key="mid" class="summary-chip">
                  {{ allModules.find(m => m.id === mid)?.label }}
                </span>
              </div>
            </div>
          </div>
        </Transition>

        <!-- ── STEP 6 — Confirmation ──────────────────── -->
        <Transition name="slide" mode="out-in">
          <div v-if="step === 6" key="step6" class="step-content step-content--center">
            <div v-if="provisioning" class="provisioning-state">
              <div class="spinner"></div>
              <p class="step-subtitle" style="margin-top: 1rem;">{{ $t('onboarding.provisioning') }}</p>
            </div>

            <template v-else-if="provisionError">
              <div class="error-icon">
                <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
                  <circle cx="18" cy="18" r="17" stroke="#ef4444" stroke-width="2"/>
                  <path d="M13 13l10 10M23 13l-10 10" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/>
                </svg>
              </div>
              <h2>{{ $t('onboarding.errorTitle') }}</h2>
              <p class="step-subtitle">{{ provisionError }}</p>
              <button class="btn btn-primary" @click="submitOnboarding">{{ $t('common.retry') }}</button>
            </template>

            <template v-else>
              <div class="success-icon">
                <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
                  <circle cx="18" cy="18" r="17" stroke="url(#grad)" stroke-width="2"/>
                  <path d="M11 18l5 5 9-9" stroke="url(#grad2)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                  <defs>
                    <linearGradient id="grad" x1="0" y1="0" x2="36" y2="36" gradientUnits="userSpaceOnUse">
                      <stop stop-color="#10b981"/><stop offset="1" stop-color="#3b82f6"/>
                    </linearGradient>
                    <linearGradient id="grad2" x1="11" y1="13" x2="25" y2="23" gradientUnits="userSpaceOnUse">
                      <stop stop-color="#10b981"/><stop offset="1" stop-color="#3b82f6"/>
                    </linearGradient>
                  </defs>
                </svg>
              </div>
              <h2>{{ $t('onboarding.ready') }}</h2>
              <p class="step-subtitle">
                <strong>{{ answers.company || $t('onboarding.yourCompany') }}</strong>
                {{ $t('onboarding.readyDescTail', { count: answers.modules.length }) }}
              </p>

              <div class="summary-chips">
                <span v-for="mid in answers.modules" :key="mid" class="summary-chip">
                  {{ allModules.find(m => m.id === mid)?.icon }} {{ allModules.find(m => m.id === mid)?.label }}
                </span>
              </div>

              <!-- UX-08: concrete next actions instead of a dead-end success screen. -->
              <div class="next-steps">
                <p class="next-steps__title">{{ $t('onboarding.nextSteps') }}</p>
                <div class="next-steps__grid">
                  <RouterLink to="/catalog/products/create" class="next-step"><span aria-hidden="true">➕</span> {{ $t('onboarding.nextAddProduct') }}</RouterLink>
                  <RouterLink to="/import/history" class="next-step"><span aria-hidden="true">📥</span> {{ $t('onboarding.nextImport') }}</RouterLink>
                  <RouterLink to="/orders" class="next-step"><span aria-hidden="true">🧾</span> {{ $t('onboarding.nextOrder') }}</RouterLink>
                  <RouterLink to="/settings" class="next-step"><span aria-hidden="true">👥</span> {{ $t('onboarding.nextInvite') }}</RouterLink>
                </div>
              </div>
            </template>
          </div>
        </Transition>

        <!-- ── Navigation ─────────────────────────────── -->
        <div class="step-nav">
          <button
            v-if="step > 1 && step < 6"
            class="btn btn-secondary"
            @click="step--"
          >
            {{ $t('onboarding.back') }}
          </button>
          <div v-else style="flex: 1;"></div>

          <!-- Steps 1-3 : need a selection -->
          <button
            v-if="step < 4"
            class="btn btn-primary"
            :disabled="!canProceed"
            @click="nextStep"
          >
            {{ $t('onboarding.continue') }}
          </button>

          <!-- Step 4 : needs (always valid, slider defaults to 1) -->
          <button
            v-if="step === 4"
            class="btn btn-primary"
            @click="step++"
          >
            {{ $t('onboarding.continue') }}
          </button>

          <!-- Step 5 : form validation -->
          <button
            v-if="step === 5"
            class="btn btn-primary"
            @click="submitStep5"
          >
            {{ $t('onboarding.finalize') }}
          </button>

          <!-- Step 6 : go to dashboard (only shown after success) -->
          <button
            v-if="step === 6 && !provisioning && !provisionError"
            class="btn btn-primary btn-lg"
            @click="router.push('/dashboard')"
          >
            {{ $t('onboarding.goDashboard') }}
          </button>
        </div>

      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, reactive, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import FrynovLogo from '@/shared/components/FrynovLogo.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'
import { t } from '@/i18n'

const router = useRouter()
const auth   = useAuthStore()

const step       = ref(1)
const totalSteps = 6

const progressPercent = computed(() => ((step.value - 1) / (totalSteps - 1)) * 100)

const answers = reactive({
  type:     '' as string,
  teamSize: '' as string,
  modules:  [] as string[],
  company:  '',
  country:  'SN',
  currency: 'XOF',
  timezone: 'Africa/Dakar',
})

const needs = reactive({
  needs_stock:    true,
  needs_pos:      false,
  needs_delivery: false,
  needs_ecommerce: false,
  needs_offline:  false,
  nb_branches:    1,
})

const step5Errors = reactive({ company: '' })

const provisioning  = ref(false)
const provisionError = ref('')

// UX-07 — garde anti-perte : active dès que l'utilisateur a commencé l'assistant,
// levée une fois le provisioning réussi (pour ne pas bloquer la redirection finale).
const onboardingComplete = ref(false)
const dirty = computed(() =>
  !onboardingComplete.value && (step.value > 1 || !!answers.type || !!answers.company.trim()),
)
useUnsavedChanges(dirty, t('onboarding.leaveConfirm'))

// ── Step 1 — Business types (libellés i18n) ────────────────
const businessTypes = computed(() => [
  { value: 'commerce',      icon: '🏪', label: t('onboarding.businessType.commerce.label'),     desc: t('onboarding.businessType.commerce.desc') },
  { value: 'distribution',  icon: '📦', label: t('onboarding.businessType.distribution.label'), desc: t('onboarding.businessType.distribution.desc') },
  { value: 'services',      icon: '💼', label: t('onboarding.businessType.services.label'),     desc: t('onboarding.businessType.services.desc') },
  { value: 'industrie',     icon: '🏭', label: t('onboarding.businessType.industrie.label'),    desc: t('onboarding.businessType.industrie.desc') },
  { value: 'restauration',  icon: '🍽️', label: t('onboarding.businessType.restauration.label'), desc: t('onboarding.businessType.restauration.desc') },
  { value: 'agriculture',   icon: '🌾', label: t('onboarding.businessType.agriculture.label'),  desc: t('onboarding.businessType.agriculture.desc') },
  { value: 'immobilier',    icon: '🏘️', label: t('onboarding.businessType.immobilier.label'),    desc: t('onboarding.businessType.immobilier.desc') },
  { value: 'pharmacie',     icon: '💊', label: t('onboarding.businessType.pharmacie.label'),     desc: t('onboarding.businessType.pharmacie.desc') },
])

// ── Step 2 — Team sizes (libellés i18n) ────────────────────
const teamSizes = computed(() => [
  { value: 'solo',   icon: '🙋', label: t('onboarding.teamSize.solo.label'),   desc: t('onboarding.teamSize.solo.desc') },
  { value: 'small',  icon: '👥', label: t('onboarding.teamSize.small.label'),  desc: t('onboarding.teamSize.small.desc') },
  { value: 'medium', icon: '🏢', label: t('onboarding.teamSize.medium.label'), desc: t('onboarding.teamSize.medium.desc') },
  { value: 'large',  icon: '🏬', label: t('onboarding.teamSize.large.label'),  desc: t('onboarding.teamSize.large.desc') },
])

// ── Step 3 — Modules (libellés i18n) ───────────────────────
const allModules = computed(() => [
  { id: 'catalog',   icon: '📦', label: t('onboarding.module.catalog.label'),   desc: t('onboarding.module.catalog.desc') },
  { id: 'inventory', icon: '🏪', label: t('onboarding.module.inventory.label'), desc: t('onboarding.module.inventory.desc') },
  { id: 'orders',    icon: '🛒', label: t('onboarding.module.orders.label'),    desc: t('onboarding.module.orders.desc') },
  { id: 'customers', icon: '👥', label: t('onboarding.module.customers.label'), desc: t('onboarding.module.customers.desc') },
  { id: 'payments',  icon: '💳', label: t('onboarding.module.payments.label'),  desc: t('onboarding.module.payments.desc') },
  { id: 'delivery',  icon: '🚚', label: t('onboarding.module.delivery.label'),  desc: t('onboarding.module.delivery.desc') },
  { id: 'reports',   icon: '📊', label: t('onboarding.module.reports.label'),   desc: t('onboarding.module.reports.desc') },
])

const modulesSuggestion: Record<string, string[]> = {
  commerce:     ['catalog', 'inventory', 'orders', 'customers', 'payments', 'reports'],
  distribution: ['inventory', 'orders', 'delivery', 'customers', 'reports'],
  services:     ['customers', 'orders', 'payments', 'reports'],
  industrie:    ['catalog', 'inventory', 'orders', 'reports'],
  restauration: ['catalog', 'orders', 'payments', 'delivery', 'reports'],
  agriculture:  ['catalog', 'inventory', 'orders', 'reports'],
  immobilier:   ['customers', 'orders', 'payments', 'reports'],
  pharmacie:    ['catalog', 'inventory', 'orders', 'customers', 'payments', 'reports'],
}

watch(() => answers.type, (type) => {
  if (type && modulesSuggestion[type]) {
    answers.modules = [...modulesSuggestion[type]]
  }
})

const availableModules = computed(() =>
  allModules.value.map(m => ({
    ...m,
    recommended: (modulesSuggestion[answers.type] ?? []).includes(m.id),
  })).sort((a, b) => (b.recommended ? 1 : 0) - (a.recommended ? 1 : 0)),
)

function toggleModule(id: string) {
  const idx = answers.modules.indexOf(id)
  if (idx >= 0) answers.modules.splice(idx, 1)
  else answers.modules.push(id)
}

const selectedTypeName = computed(
  () => businessTypes.value.find(bt => bt.value === answers.type)?.label ?? answers.type,
)

// ── Navigation ─────────────────────────────────────────────
const canProceed = computed(() => {
  if (step.value === 1) return !!answers.type
  if (step.value === 2) return !!answers.teamSize
  if (step.value === 3) return answers.modules.length > 0
  return true
})

function nextStep() {
  if (canProceed.value && step.value < totalSteps) step.value++
}

function submitStep5() {
  step5Errors.company = ''
  if (!answers.company.trim()) {
    step5Errors.company = t('onboarding.companyRequired')
    return
  }
  step.value = 6
  submitOnboarding()
}

async function submitOnboarding() {
  provisioning.value  = true
  provisionError.value = ''
  try {
    await api.post('/api/workspace/provision', {
      company_name:    answers.company,
      country:         answers.country,
      currency:        answers.currency,
      timezone:        answers.timezone,
      sector:          answers.type,
      nb_branches:     needs.nb_branches,
      needs_stock:     needs.needs_stock,
      needs_pos:       needs.needs_pos,
      needs_delivery:  needs.needs_delivery,
      needs_ecommerce: needs.needs_ecommerce,
      needs_offline:   needs.needs_offline,
      modules:         answers.modules,
    })

    // Provisioning assigns the admin role + creates the subscription + sets
    // onboarded=true. Refresh from /me so auth.user carries the (now tenant-scoped)
    // roles and subscription before the user enters the app — otherwise the RBAC
    // tab menus and billing screen would be empty for a freshly-onboarded account.
    await auth.fetchCurrentUser()
    onboardingComplete.value = true // UX-07 : lève la garde anti-perte (succès → redirection libre)
  } catch (err: any) {
    provisionError.value =
      err?.response?.data?.message ?? t('onboarding.provisionError')
  } finally {
    provisioning.value = false
  }
}

// ── Countries ──────────────────────────────────────────────
const countries = [
  // Afrique de l'Ouest
  { code: 'SN', flag: '🇸🇳', name: 'Sénégal',           currency: 'XOF', tz: 'Africa/Dakar' },
  { code: 'CI', flag: '🇨🇮', name: "Côte d'Ivoire",      currency: 'XOF', tz: 'Africa/Abidjan' },
  { code: 'ML', flag: '🇲🇱', name: 'Mali',               currency: 'XOF', tz: 'Africa/Bamako' },
  { code: 'BF', flag: '🇧🇫', name: 'Burkina Faso',        currency: 'XOF', tz: 'Africa/Ouagadougou' },
  { code: 'BJ', flag: '🇧🇯', name: 'Bénin',              currency: 'XOF', tz: 'Africa/Porto-Novo' },
  { code: 'TG', flag: '🇹🇬', name: 'Togo',               currency: 'XOF', tz: 'Africa/Lome' },
  { code: 'GN', flag: '🇬🇳', name: 'Guinée',             currency: 'GNF', tz: 'Africa/Conakry' },
  { code: 'GH', flag: '🇬🇭', name: 'Ghana',              currency: 'GHS', tz: 'Africa/Accra' },
  { code: 'NG', flag: '🇳🇬', name: 'Nigeria',            currency: 'NGN', tz: 'Africa/Lagos' },
  // Afrique Centrale
  { code: 'CM', flag: '🇨🇲', name: 'Cameroun',           currency: 'XAF', tz: 'Africa/Douala' },
  // Afrique du Nord
  { code: 'MA', flag: '🇲🇦', name: 'Maroc',              currency: 'MAD', tz: 'Africa/Casablanca' },
  { code: 'DZ', flag: '🇩🇿', name: 'Algérie',            currency: 'DZD', tz: 'Africa/Algiers' },
  { code: 'TN', flag: '🇹🇳', name: 'Tunisie',            currency: 'TND', tz: 'Africa/Tunis' },
  { code: 'EG', flag: '🇪🇬', name: 'Égypte',             currency: 'EGP', tz: 'Africa/Cairo' },
  // Afrique de l'Est
  { code: 'KE', flag: '🇰🇪', name: 'Kenya',              currency: 'KES', tz: 'Africa/Nairobi' },
  { code: 'TZ', flag: '🇹🇿', name: 'Tanzanie',           currency: 'TZS', tz: 'Africa/Dar_es_Salaam' },
  { code: 'UG', flag: '🇺🇬', name: 'Ouganda',            currency: 'UGX', tz: 'Africa/Kampala' },
  { code: 'ET', flag: '🇪🇹', name: 'Éthiopie',           currency: 'ETB', tz: 'Africa/Addis_Ababa' },
  // Afrique Australe
  { code: 'ZA', flag: '🇿🇦', name: 'Afrique du Sud',     currency: 'ZAR', tz: 'Africa/Johannesburg' },
  { code: 'MZ', flag: '🇲🇿', name: 'Mozambique',         currency: 'MZN', tz: 'Africa/Maputo' },
  // Europe
  { code: 'FR', flag: '🇫🇷', name: 'France',             currency: 'EUR', tz: 'Europe/Paris' },
  { code: 'BE', flag: '🇧🇪', name: 'Belgique',           currency: 'EUR', tz: 'Europe/Brussels' },
  { code: 'CH', flag: '🇨🇭', name: 'Suisse',             currency: 'CHF', tz: 'Europe/Zurich' },
  { code: 'GB', flag: '🇬🇧', name: 'Royaume-Uni',        currency: 'GBP', tz: 'Europe/London' },
  { code: 'DE', flag: '🇩🇪', name: 'Allemagne',          currency: 'EUR', tz: 'Europe/Berlin' },
  { code: 'ES', flag: '🇪🇸', name: 'Espagne',            currency: 'EUR', tz: 'Europe/Madrid' },
  { code: 'IT', flag: '🇮🇹', name: 'Italie',             currency: 'EUR', tz: 'Europe/Rome' },
  // Amériques
  { code: 'CA', flag: '🇨🇦', name: 'Canada',             currency: 'CAD', tz: 'America/Toronto' },
  { code: 'US', flag: '🇺🇸', name: 'États-Unis',         currency: 'USD', tz: 'America/New_York' },
]

const currencies = [
  { code: 'XOF', name: 'Franc CFA BCEAO' },
  { code: 'XAF', name: 'Franc CFA BEAC' },
  { code: 'NGN', name: 'Naira nigérian' },
  { code: 'GHS', name: 'Cedi ghanéen' },
  { code: 'KES', name: 'Shilling kényan' },
  { code: 'TZS', name: 'Shilling tanzanien' },
  { code: 'UGX', name: 'Shilling ougandais' },
  { code: 'ETB', name: 'Birr éthiopien' },
  { code: 'ZAR', name: 'Rand sud-africain' },
  { code: 'MAD', name: 'Dirham marocain' },
  { code: 'TND', name: 'Dinar tunisien' },
  { code: 'DZD', name: 'Dinar algérien' },
  { code: 'EGP', name: 'Livre égyptienne' },
  { code: 'EUR', name: 'Euro' },
  { code: 'USD', name: 'Dollar américain' },
  { code: 'GBP', name: 'Livre sterling' },
  { code: 'CAD', name: 'Dollar canadien' },
]

const timezones = [
  { value: 'Africa/Dakar',          label: '(UTC+0) Dakar, Abidjan, Accra' },
  { value: 'Africa/Lagos',          label: '(UTC+1) Lagos, Douala, Yaoundé' },
  { value: 'Africa/Casablanca',     label: '(UTC+1) Casablanca, Rabat' },
  { value: 'Africa/Tunis',          label: '(UTC+1) Tunis' },
  { value: 'Africa/Algiers',        label: '(UTC+1) Alger' },
  { value: 'Africa/Cairo',          label: '(UTC+2) Le Caire' },
  { value: 'Africa/Nairobi',        label: '(UTC+3) Nairobi, Kampala, Addis-Abeba' },
  { value: 'Africa/Dar_es_Salaam',  label: '(UTC+3) Dar es Salaam' },
  { value: 'Africa/Johannesburg',   label: '(UTC+2) Johannesburg, Maputo' },
  { value: 'Europe/Paris',          label: '(UTC+1) Paris, Madrid, Rome' },
  { value: 'Europe/London',         label: '(UTC+0) Londres, Lisbonne' },
  { value: 'Europe/Berlin',         label: '(UTC+1) Berlin, Bruxelles, Amsterdam' },
  { value: 'Europe/Zurich',         label: '(UTC+1) Zürich, Genève' },
  { value: 'America/New_York',      label: '(UTC-5) New York, Miami' },
  { value: 'America/Toronto',       label: '(UTC-5) Toronto, Montréal' },
]

function onCountryChange() {
  const c = countries.find(x => x.code === answers.country)
  if (c) {
    answers.currency = c.currency
    answers.timezone = c.tz
  }
}
</script>

<style scoped>
/* ── Page shell ──────────────────────────────────────────── */
.onboarding-page {
  min-height: 100vh;
  background: var(--gray-50);
  display: flex;
  flex-direction: column;
}

.onboarding-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 2rem;
  background: white;
  border-bottom: 1px solid var(--gray-200);
}

.onboarding-logo { text-decoration: none; }

/* Progress */
.progress-track {
  height: 3px;
  background: var(--gray-200);
  position: relative;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--brand-primary), var(--brand-secondary));
  transition: width 0.4s ease;
}

/* Main */
.onboarding-main {
  flex: 1;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 3rem 1.5rem;
}

.onboarding-card {
  background: white;
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-md);
  padding: 2.5rem 2rem;
  width: 100%;
  max-width: 640px;
}

/* Step indicator */
.step-indicator {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 2rem;
}

.step-count {
  font-size: var(--text-xs);
  font-weight: 600;
  color: var(--gray-400);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.step-dots { display: flex; gap: 6px; }

.step-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--gray-200);
  transition: background 0.2s, transform 0.2s;
}

.step-dot.active {
  background: var(--brand-primary);
  transform: scale(1.2);
}

.step-dot.done {
  background: var(--brand-primary);
  opacity: 0.5;
}

/* Step content */
.step-content h2 {
  font-size: var(--text-xl);
  font-weight: 800;
  color: var(--gray-900);
  margin-bottom: 0.5rem;
  letter-spacing: -0.3px;
}

.step-subtitle {
  font-size: var(--text-sm);
  color: var(--gray-500);
  line-height: 1.6;
  margin: 0 0 2rem;
}

.step-content--center { text-align: center; }

/* Choice cards — 2 columns */
.choice-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  margin-bottom: 2rem;
}

.choice-grid--row { grid-template-columns: repeat(4, 1fr); }

.choice-card {
  position: relative;
  background: white;
  border: 2px solid var(--gray-200);
  border-radius: var(--radius-lg);
  padding: 1.25rem;
  cursor: pointer;
  text-align: left;
  transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
}

.choice-card:hover { border-color: var(--brand-primary-light); background: var(--brand-primary-bg); }
.choice-card.selected { border-color: var(--brand-primary); background: var(--brand-primary-bg); }

.choice-card--compact { text-align: center; padding: 1rem 0.5rem; }

.choice-icon { font-size: 1.75rem; margin-bottom: 0.5rem; }
.choice-card--compact .choice-icon { font-size: 1.5rem; }

.choice-label {
  font-size: var(--text-sm);
  font-weight: 700;
  color: var(--gray-900);
  margin-bottom: 0.25rem;
}

.choice-desc {
  font-size: var(--text-xs);
  color: var(--gray-500);
  line-height: 1.4;
}

.choice-check {
  position: absolute;
  top: 0.75rem;
  right: 0.75rem;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--gray-200);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.15s;
}

.choice-card.selected .choice-check {
  background: var(--brand-primary);
}

/* Module toggles */
.modules-grid {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 2rem;
}

.module-toggle {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  width: 100%;
  padding: 0.875rem 1rem;
  background: white;
  border: 2px solid var(--gray-200);
  border-radius: var(--radius-md);
  cursor: pointer;
  text-align: left;
  transition: border-color 0.15s, background 0.15s;
}

.module-toggle:hover    { border-color: var(--brand-primary-light); background: var(--brand-primary-bg); }
.module-toggle.selected { border-color: var(--brand-primary); background: var(--brand-primary-bg); }

.module-toggle-icon { font-size: 1.3rem; flex-shrink: 0; }

.module-toggle-body { flex: 1; text-align: left; }

.module-toggle-label {
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--gray-800);
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.module-badge {
  font-size: 0.6rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  background: var(--brand-primary-bg);
  color: #065f46;
  padding: 0.1rem 0.4rem;
  border-radius: 10px;
}

.module-toggle-desc {
  font-size: var(--text-xs);
  color: var(--gray-400);
  margin-top: 0.1rem;
}

.module-toggle-check {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--gray-200);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: background 0.15s;
}

.module-toggle.selected .module-toggle-check { background: var(--brand-primary); }

/* ── Needs step ──────────────────────────────────────────── */
.needs-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.needs-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.875rem 1rem;
  background: white;
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-md);
  transition: background 0.15s;
}

.needs-item:hover { background: var(--gray-50); }

.needs-item-body {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.needs-item-icon { font-size: 1.25rem; flex-shrink: 0; }

.needs-item-label {
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--gray-800);
}

.needs-item-desc {
  font-size: var(--text-xs);
  color: var(--gray-400);
  margin-top: 0.1rem;
}

/* Toggle switch */
.toggle-switch {
  position: relative;
  width: 44px;
  height: 24px;
  border-radius: 12px;
  background: var(--gray-300);
  border: none;
  cursor: pointer;
  flex-shrink: 0;
  transition: background 0.2s;
  padding: 0;
}

.toggle-switch.on { background: var(--brand-primary); }

.toggle-thumb {
  position: absolute;
  top: 3px;
  left: 3px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: white;
  box-shadow: 0 1px 3px rgba(0,0,0,0.2);
  transition: transform 0.2s;
  display: block;
}

.toggle-switch.on .toggle-thumb { transform: translateX(20px); }

/* Slider */
.slider-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-top: 0.5rem;
}

.slider-edge {
  font-size: var(--text-xs);
  color: var(--gray-400);
  flex-shrink: 0;
  width: 1.5rem;
  text-align: center;
}

.range-slider {
  flex: 1;
  -webkit-appearance: none;
  appearance: none;
  height: 4px;
  border-radius: 2px;
  background: var(--gray-200);
  outline: none;
  cursor: pointer;
}

.range-slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: var(--brand-primary);
  cursor: pointer;
  box-shadow: 0 1px 3px rgba(0,0,0,0.15);
}

.range-slider::-moz-range-thumb {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: var(--brand-primary);
  cursor: pointer;
  border: none;
  box-shadow: 0 1px 3px rgba(0,0,0,0.15);
}

.slider-ticks {
  display: flex;
  justify-content: space-between;
  margin-top: 0.25rem;
  padding: 0 1.75rem;
}

.slider-tick {
  font-size: 0.65rem;
  color: var(--gray-400);
}

.branch-count {
  display: inline-block;
  min-width: 1.5rem;
  text-align: center;
  background: var(--brand-primary-bg);
  color: var(--brand-primary);
  border-radius: 6px;
  padding: 0 0.4rem;
  margin-left: 0.5rem;
  font-size: var(--text-sm);
}

/* Success / error step */
.success-icon {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(59,130,246,0.1));
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1.5rem;
}

.error-icon {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: rgba(239,68,68,0.08);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1.5rem;
}

.provisioning-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 2rem 0;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid var(--gray-200);
  border-top-color: var(--brand-primary);
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

.summary-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  justify-content: center;
  margin-top: 1.5rem;
}

.summary-chip {
  font-size: var(--text-xs);
  font-weight: 600;
  background: var(--brand-primary-bg);
  color: #065f46;
  border: 1px solid var(--brand-primary-light);
  padding: 0.3rem 0.75rem;
  border-radius: 20px;
}

/* Next-steps checklist (UX-08) */
.next-steps { margin-top: 1.5rem; width: 100%; }
.next-steps__title {
  font-size: var(--text-xs);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--gray-400);
  margin: 0 0 0.6rem;
}
.next-steps__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 0.5rem;
}
.next-step {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.6rem 0.8rem;
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-md);
  background: white;
  color: var(--gray-700);
  text-decoration: none;
  font-size: var(--text-sm);
  font-weight: 500;
  transition: border-color 0.15s, background 0.15s;
}
.next-step:hover { border-color: var(--brand-primary); background: var(--brand-primary-bg); }

/* "Ce qui sera configuré" recap (UX-08) */
.onboard-recap {
  margin-top: 1.5rem;
  padding: 1rem 1.1rem;
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-md, 8px);
  background: var(--gray-50);
}
.onboard-recap__title {
  font-size: var(--text-xs);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--gray-400);
  margin: 0 0 0.6rem;
}
.onboard-recap__list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.35rem; }
.onboard-recap__list li { display: flex; justify-content: space-between; gap: 1rem; font-size: var(--text-sm); color: var(--gray-600); }
.onboard-recap__list strong { color: var(--gray-900); font-weight: 600; }
.onboard-recap__chips { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.7rem; }

/* Navigation */
.step-nav {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 0.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--gray-100);
}

/* Slide transition */
.slide-enter-active,
.slide-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.slide-enter-from { opacity: 0; transform: translateX(20px); }
.slide-leave-to   { opacity: 0; transform: translateX(-20px); }

/* ── Responsive ──────────────────────────────────────────── */
@media (max-width: 640px) {
  .onboarding-card { padding: 1.5rem 1.25rem; }
  .choice-grid { grid-template-columns: 1fr; }
  .choice-grid--row { grid-template-columns: repeat(2, 1fr); }
  .onboarding-main { padding: 1.5rem 1rem; }
}
</style>
