<template>
  <div class="register-form">
    <div class="form-header">
      <h2>{{ $t('auth.registerTitle') }}</h2>
      <p>{{ $t('auth.registerSubtitle') }}</p>
    </div>

    <form @submit.prevent="handleSubmit" novalidate>

      <div class="form-group">
        <label class="form-label" for="company">{{ $t('auth.companyLabel') }}</label>
        <input
          id="company"
          v-model="form.company"
          type="text"
          class="form-input"
          :class="{ error: errors.company }"
          :placeholder="$t('auth.companyPlaceholder')"
          autocomplete="organization"
          @input="clearError('company')"
        />
        <span v-if="errors.company" class="form-error">{{ errors.company }}</span>
      </div>

      <div class="form-group">
        <label class="form-label" for="name">{{ $t('auth.nameLabel') }}</label>
        <input
          id="name"
          v-model="form.name"
          type="text"
          class="form-input"
          :class="{ error: errors.name }"
          :placeholder="$t('auth.namePlaceholder')"
          autocomplete="name"
          @input="clearError('name')"
        />
        <span v-if="errors.name" class="form-error">{{ errors.name }}</span>
      </div>

      <div class="form-group">
        <label class="form-label" for="email">{{ $t('auth.emailProLabel') }}</label>
        <input
          id="email"
          v-model="form.email"
          type="email"
          class="form-input"
          :class="{ error: errors.email }"
          :placeholder="$t('auth.emailProPlaceholder')"
          autocomplete="email"
          @input="clearError('email')"
        />
        <span v-if="errors.email" class="form-error">{{ errors.email }}</span>
      </div>

      <div class="form-group" style="margin-bottom: 0.25rem;">
        <label class="form-label" for="password">{{ $t('auth.password') }}</label>
        <div class="password-wrap">
          <input
            id="password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            class="form-input"
            :class="{ error: errors.password }"
            :placeholder="$t('auth.passwordPlaceholder')"
            autocomplete="new-password"
            @input="clearError('password')"
          />
          <button
            type="button"
            class="toggle-pass"
            :aria-label="showPassword ? $t('auth.hideShort') : $t('auth.showShort')"
            @click="showPassword = !showPassword"
          >
            <svg v-if="!showPassword" width="16" height="16" viewBox="0 0 16 16" fill="none">
              <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" stroke="currentColor" stroke-width="1.4"/>
              <circle cx="8" cy="8" r="2" stroke="currentColor" stroke-width="1.4"/>
            </svg>
            <svg v-else width="16" height="16" viewBox="0 0 16 16" fill="none">
              <path d="M2 2l12 12M6.5 6.7A2 2 0 009.4 9.5M4.4 4.5C2.8 5.5 1 8 1 8s2.5 5 7 5c1.4 0 2.7-.4 3.7-1M7 3.1C7.3 3 7.7 3 8 3c4.5 0 7 5 7 5s-.8 1.5-2 2.8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
            </svg>
          </button>
        </div>
        <span v-if="errors.password" class="form-error">{{ errors.password }}</span>
      </div>

      <!-- Password strength -->
      <div class="password-strength" style="margin-bottom: 1.25rem;">
        <div class="strength-bars">
          <div
            v-for="n in 4"
            :key="n"
            class="strength-bar"
            :class="strengthLevel >= n ? `level-${strengthLevel}` : ''"
          ></div>
        </div>
        <span class="strength-label">{{ strengthLabel }}</span>
      </div>

      <div v-if="globalError" class="alert alert-error" role="alert" style="margin-bottom: 1rem;">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="flex-shrink:0">
          <circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.4"/>
          <path d="M8 5v3.5M8 10.5v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        {{ globalError }}
      </div>

      <p class="terms-notice">
        {{ $t('auth.termsBefore') }}
        <a href="#">{{ $t('auth.termsOfUse') }}</a> {{ $t('auth.termsAnd') }}
        <a href="#">{{ $t('auth.privacyPolicy') }}</a>.
      </p>

      <button type="submit" class="btn btn-primary btn-xl" :disabled="loading" style="width: 100%; margin-top: 1rem; justify-content: center;">
        <span v-if="loading" class="spinner-sm spinner-white"></span>
        {{ loading ? $t('auth.creating') : $t('auth.createMyWorkspace') }}
      </button>

    </form>

    <div class="divider-text" style="margin: 1.5rem 0;">{{ $t('auth.or') }}</div>

    <p class="login-cta">
      {{ $t('auth.alreadyRegistered') }}
      <RouterLink to="/login" class="login-link">{{ $t('auth.login') }}</RouterLink>
    </p>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { authService } from '../services/authService'
import { useAuthStore } from '@/stores/auth'
import { t } from '@/i18n'

const router   = useRouter()
const authStore = useAuthStore()

const form = reactive({ company: '', name: '', email: '', password: '' })
const errors = reactive<Record<string, string>>({})
const globalError  = ref('')
const loading      = ref(false)
const showPassword = ref(false)

function clearError(field: string) {
  delete errors[field]
  globalError.value = ''
}

// Password strength
const strengthLevel = computed(() => {
  const p = form.password
  if (!p) return 0
  let score = 0
  if (p.length >= 8)   score++
  if (/[A-Z]/.test(p)) score++
  if (/[0-9]/.test(p)) score++
  if (/[^A-Za-z0-9]/.test(p)) score++
  return score
})

const strengthLabel = computed(() => {
  const k = ['', 'weak', 'medium', 'good', 'excellent'][strengthLevel.value]
  return k ? t(`auth.strength.${k}`) : ''
})

function validate(): boolean {
  let valid = true
  if (!form.company.trim()) { errors.company  = t('auth.companyRequired'); valid = false }
  if (!form.name.trim())    { errors.name     = t('auth.nameRequired'); valid = false }
  if (!form.email.trim())   { errors.email    = t('auth.emailRequired'); valid = false }
  else if (!/\S+@\S+\.\S+/.test(form.email)) { errors.email = t('auth.emailInvalid'); valid = false }
  if (!form.password)                        { errors.password = t('auth.passwordRequired'); valid = false }
  else if (form.password.length < 8)         { errors.password = t('auth.passwordMin'); valid = false }
  return valid
}

async function handleSubmit() {
  if (!validate()) return

  loading.value     = true
  globalError.value = ''

  try {
    const response = await authService.register({
      company_name:          form.company,
      name:                  form.name,
      email:                 form.email,
      password:              form.password,
      password_confirmation: form.password,
    })

    // Persist session in store
    authStore.setToken(response.token)
    authStore.setUser(response.user)

    router.push('/onboarding')
  } catch (err: any) {
    const status = err?.response?.status
    if (status === 422) {
      const validationErrors = err.response?.data?.errors ?? {}
      Object.entries(validationErrors).forEach(([field, msgs]: [string, any]) => {
        // Map backend field names to form field names
        const key = field === 'company_name' ? 'company' : field
        errors[key] = Array.isArray(msgs) ? msgs[0] : msgs
      })
    } else {
      globalError.value = err.response?.data?.message ?? t('auth.registerFailed')
    }
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.form-header {
  text-align: center;
  margin-bottom: 2rem;
}

.form-header h2 {
  font-size: var(--text-2xl);
  font-weight: 700;
  color: var(--gray-900);
  margin-bottom: 0.3rem;
}

.form-header p {
  font-size: var(--text-sm);
  color: var(--gray-500);
  margin: 0;
}

.password-wrap { position: relative; }

.toggle-pass {
  position: absolute;
  right: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
  color: var(--gray-400);
  display: flex;
  align-items: center;
  transition: color 0.15s;
}
.toggle-pass:hover { color: var(--gray-700); }

.password-strength {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.strength-bars { display: flex; gap: 4px; }

.strength-bar {
  height: 4px;
  width: 40px;
  border-radius: 2px;
  background: var(--gray-200);
  transition: background 0.2s;
}

.strength-bar.level-1 { background: var(--color-error); }
.strength-bar.level-2 { background: var(--color-warning); }
.strength-bar.level-3 { background: var(--brand-primary); }
.strength-bar.level-4 { background: var(--brand-primary); }

.strength-label {
  font-size: var(--text-xs);
  color: var(--gray-500);
  min-width: 60px;
}

.terms-notice {
  font-size: var(--text-xs);
  color: var(--gray-400);
  line-height: 1.6;
  margin: 0;
}

.terms-notice a {
  color: var(--brand-secondary);
  text-decoration: none;
}
.terms-notice a:hover { text-decoration: underline; }

.login-cta {
  text-align: center;
  font-size: var(--text-sm);
  color: var(--gray-500);
  margin: 0;
}

.login-link {
  color: var(--brand-secondary);
  font-weight: 600;
  text-decoration: none;
  transition: color 0.15s;
}
.login-link:hover { color: var(--brand-secondary-dark); text-decoration: underline; }
</style>
