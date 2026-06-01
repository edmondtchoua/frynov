<template>
  <div class="onboarding-page">

    <!-- Header -->
    <header class="onboarding-header">
      <RouterLink to="/" class="onboarding-logo">
        <FrynovLogo variant="light" />
      </RouterLink>
      <RouterLink to="/login" class="btn btn-ghost btn-sm" style="color: var(--gray-500);">
        Déjà inscrit ? Connexion
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
          <span class="step-count">Étape {{ step }} sur {{ totalSteps }}</span>
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
            <h2>Quel type d'entreprise gérez-vous ?</h2>
            <p class="step-subtitle">Frynov adaptera automatiquement l'espace à votre activité.</p>

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
            <h2>Quelle est la taille de votre équipe ?</h2>
            <p class="step-subtitle">Cela nous aide à configurer les permissions et les workflows.</p>

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
            <h2>Quels modules souhaitez-vous activer ?</h2>
            <p class="step-subtitle">
              Sélection suggérée pour <strong>{{ selectedTypeName }}</strong> — vous pouvez modifier à tout moment.
            </p>

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
                    <span v-if="mod.recommended" class="module-badge">Recommandé</span>
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

        <!-- ── STEP 4 — Company info ──────────────────── -->
        <Transition name="slide" mode="out-in">
          <div v-if="step === 4" key="step4" class="step-content">
            <h2>Parlez-nous de votre entreprise</h2>
            <p class="step-subtitle">Ces informations apparaîtront sur vos documents et factures.</p>

            <div class="form-group">
              <label class="form-label" for="companyName">Nom de l'entreprise *</label>
              <input
                id="companyName"
                v-model="answers.company"
                type="text"
                class="form-input"
                :class="{ error: step4Errors.company }"
                placeholder="Acme SAS"
              />
              <span v-if="step4Errors.company" class="form-error">{{ step4Errors.company }}</span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label class="form-label" for="country">Pays</label>
                <select id="country" v-model="answers.country" class="form-input" @change="onCountryChange">
                  <option v-for="c in countries" :key="c.code" :value="c.code">{{ c.flag }} {{ c.name }}</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label" for="currency">Devise</label>
                <select id="currency" v-model="answers.currency" class="form-input">
                  <option v-for="cur in currencies" :key="cur.code" :value="cur.code">{{ cur.code }} — {{ cur.name }}</option>
                </select>
              </div>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label" for="timezone">Fuseau horaire</label>
              <select id="timezone" v-model="answers.timezone" class="form-input">
                <option v-for="tz in timezones" :key="tz.value" :value="tz.value">{{ tz.label }}</option>
              </select>
            </div>
          </div>
        </Transition>

        <!-- ── STEP 5 — Confirmation ──────────────────── -->
        <Transition name="slide" mode="out-in">
          <div v-if="step === 5" key="step5" class="step-content step-content--center">
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
            <h2>Votre espace est prêt !</h2>
            <p class="step-subtitle">
              <strong>{{ answers.company || 'Votre entreprise' }}</strong> est configurée avec
              {{ answers.modules.length }} module{{ answers.modules.length > 1 ? 's' : '' }} activé{{ answers.modules.length > 1 ? 's' : '' }}.
              Vous pouvez tout modifier dans les paramètres.
            </p>

            <div class="summary-chips">
              <span v-for="mid in answers.modules" :key="mid" class="summary-chip">
                {{ allModules.find(m => m.id === mid)?.icon }} {{ allModules.find(m => m.id === mid)?.label }}
              </span>
            </div>
          </div>
        </Transition>

        <!-- ── Navigation ─────────────────────────────── -->
        <div class="step-nav">
          <button
            v-if="step > 1 && step < 5"
            class="btn btn-secondary"
            @click="step--"
          >
            ← Retour
          </button>
          <div v-else style="flex: 1;"></div>

          <!-- Steps 1-3 : need a selection -->
          <button
            v-if="step < 4"
            class="btn btn-primary"
            :disabled="!canProceed"
            @click="nextStep"
          >
            Continuer →
          </button>

          <!-- Step 4 : form validation -->
          <button
            v-if="step === 4"
            class="btn btn-primary"
            @click="submitStep4"
          >
            Finaliser la configuration →
          </button>

          <!-- Step 5 : go to dashboard -->
          <RouterLink
            v-if="step === 5"
            to="/dashboard"
            class="btn btn-primary btn-lg"
          >
            Accéder à mon tableau de bord →
          </RouterLink>
        </div>

      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, reactive, watch } from 'vue'
import { RouterLink } from 'vue-router'
import FrynovLogo from '@/shared/components/FrynovLogo.vue'

const step       = ref(1)
const totalSteps = 5

const progressPercent = computed(() => ((step.value - 1) / (totalSteps - 1)) * 100)

const answers = reactive({
  type:     '' as string,
  teamSize: '' as string,
  modules:  [] as string[],
  company:  '',
  country:  'FR',
  currency: 'EUR',
  timezone: 'Europe/Paris',
})

const step4Errors = reactive({ company: '' })

// ── Step 1 — Business types ────────────────────────────────
const businessTypes = [
  { value: 'commerce',     icon: '🏪', label: 'Commerce & Retail',     desc: 'Boutiques, e-commerce, points de vente' },
  { value: 'distribution', icon: '📦', label: 'Distribution',          desc: 'Grossistes, logistique, approvisionnement' },
  { value: 'services',     icon: '💼', label: 'Services & Consulting',  desc: 'Agences, prestataires, cabinets' },
  { value: 'industrie',    icon: '🏭', label: 'Industrie & Production', desc: 'Fabrication, artisanat, production' },
]

// ── Step 2 — Team sizes ────────────────────────────────────
const teamSizes = [
  { value: 'solo',   icon: '🙋', label: 'Juste moi',     desc: 'Entrepreneur individuel' },
  { value: 'small',  icon: '👥', label: '2 – 10',        desc: 'Petite équipe' },
  { value: 'medium', icon: '🏢', label: '11 – 100',      desc: 'Équipe intermédiaire' },
  { value: 'large',  icon: '🏬', label: '100+',          desc: 'Grande organisation' },
]

// ── Step 3 — Modules ───────────────────────────────────────
const allModules = [
  { id: 'catalog',   icon: '📦', label: 'Catalogue produits',  desc: 'Produits, variantes, prix, codes-barres' },
  { id: 'inventory', icon: '🏪', label: 'Inventaire & Stock',  desc: 'Suivi des stocks, alertes, mouvements' },
  { id: 'orders',    icon: '🛒', label: 'Commandes & Ventes',  desc: 'Cycle de vente complet' },
  { id: 'customers', icon: '👥', label: 'Clients & CRM',       desc: 'Gestion des clients et historique' },
  { id: 'payments',  icon: '💳', label: 'Paiements',           desc: 'Cash, mobile money, carte' },
  { id: 'delivery',  icon: '🚚', label: 'Livraisons',          desc: 'Suivi des livraisons et transporteurs' },
  { id: 'reports',   icon: '📊', label: 'Rapports & Analytics',desc: 'Tableaux de bord et exports' },
]

// Suggested modules per business type
const modulesSuggestion: Record<string, string[]> = {
  commerce:     ['catalog', 'inventory', 'orders', 'customers', 'payments', 'reports'],
  distribution: ['inventory', 'orders', 'delivery', 'customers', 'reports'],
  services:     ['customers', 'orders', 'payments', 'reports'],
  industrie:    ['catalog', 'inventory', 'orders', 'reports'],
}

// Auto-suggest modules when type changes
watch(() => answers.type, (type) => {
  if (type && modulesSuggestion[type]) {
    answers.modules = [...modulesSuggestion[type]]
  }
})

const availableModules = computed(() =>
  allModules.map(m => ({
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
  () => businessTypes.find(t => t.value === answers.type)?.label ?? answers.type,
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

function submitStep4() {
  step4Errors.company = ''
  if (!answers.company.trim()) {
    step4Errors.company = 'Le nom de l\'entreprise est requis'
    return
  }
  step.value = 5
}

// ── Countries / currencies / timezones ────────────────────
const countries = [
  { code: 'FR', flag: '🇫🇷', name: 'France',         currency: 'EUR', tz: 'Europe/Paris' },
  { code: 'BE', flag: '🇧🇪', name: 'Belgique',        currency: 'EUR', tz: 'Europe/Brussels' },
  { code: 'CH', flag: '🇨🇭', name: 'Suisse',          currency: 'CHF', tz: 'Europe/Zurich' },
  { code: 'CA', flag: '🇨🇦', name: 'Canada',          currency: 'CAD', tz: 'America/Toronto' },
  { code: 'US', flag: '🇺🇸', name: 'États-Unis',      currency: 'USD', tz: 'America/New_York' },
  { code: 'GB', flag: '🇬🇧', name: 'Royaume-Uni',     currency: 'GBP', tz: 'Europe/London' },
  { code: 'DE', flag: '🇩🇪', name: 'Allemagne',       currency: 'EUR', tz: 'Europe/Berlin' },
  { code: 'ES', flag: '🇪🇸', name: 'Espagne',         currency: 'EUR', tz: 'Europe/Madrid' },
  { code: 'IT', flag: '🇮🇹', name: 'Italie',          currency: 'EUR', tz: 'Europe/Rome' },
  { code: 'MA', flag: '🇲🇦', name: 'Maroc',           currency: 'MAD', tz: 'Africa/Casablanca' },
  { code: 'TN', flag: '🇹🇳', name: 'Tunisie',         currency: 'TND', tz: 'Africa/Tunis' },
  { code: 'SN', flag: '🇸🇳', name: 'Sénégal',         currency: 'XOF', tz: 'Africa/Dakar' },
  { code: 'CI', flag: '🇨🇮', name: "Côte d'Ivoire",   currency: 'XOF', tz: 'Africa/Abidjan' },
  { code: 'CM', flag: '🇨🇲', name: 'Cameroun',        currency: 'XAF', tz: 'Africa/Douala' },
]

const currencies = [
  { code: 'EUR', name: 'Euro' },
  { code: 'USD', name: 'Dollar américain' },
  { code: 'GBP', name: 'Livre sterling' },
  { code: 'CHF', name: 'Franc suisse' },
  { code: 'CAD', name: 'Dollar canadien' },
  { code: 'MAD', name: 'Dirham marocain' },
  { code: 'TND', name: 'Dinar tunisien' },
  { code: 'XOF', name: 'Franc CFA BCEAO' },
  { code: 'XAF', name: 'Franc CFA BEAC' },
]

const timezones = [
  { value: 'Europe/Paris',       label: '(UTC+1) Paris, Madrid, Rome' },
  { value: 'Europe/London',      label: '(UTC+0) Londres, Lisbonne' },
  { value: 'Europe/Berlin',      label: '(UTC+1) Berlin, Bruxelles, Amsterdam' },
  { value: 'Europe/Zurich',      label: '(UTC+1) Zürich, Genève' },
  { value: 'America/New_York',   label: '(UTC-5) New York, Miami' },
  { value: 'America/Toronto',    label: '(UTC-5) Toronto, Montréal' },
  { value: 'Africa/Casablanca',  label: '(UTC+1) Casablanca, Rabat' },
  { value: 'Africa/Tunis',       label: '(UTC+1) Tunis' },
  { value: 'Africa/Dakar',       label: '(UTC+0) Dakar, Abidjan' },
  { value: 'Africa/Douala',      label: '(UTC+1) Douala, Yaoundé' },
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

/* Success step */
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
