<template>
  <div class="login-form">
    <div class="form-header">
      <h2>Connexion</h2>
      <p>Bienvenue sur Frynov ERP</p>
    </div>

    <!-- Inactivity session expiry message -->
    <div v-if="inactivityMsg" class="alert alert-info" role="alert" style="margin-bottom:1.25rem">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="flex-shrink:0">
        <circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.4"/>
        <path d="M8 5v3M8 10v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
      </svg>
      {{ inactivityMsg }}
    </div>

    <form @submit.prevent="handleSubmit" novalidate>

      <div class="form-group">
        <label class="form-label" for="email">Adresse email</label>
        <input
          id="email"
          v-model="form.email"
          type="email"
          class="form-input"
          :class="{ error: errors.email }"
          placeholder="admin@mycompany.com"
          autocomplete="email"
          @input="clearError('email')"
        />
        <span v-if="errors.email" class="form-error">{{ errors.email }}</span>
      </div>

      <div class="form-group" style="margin-bottom: 0.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <label class="form-label" for="password">Mot de passe</label>
          <a href="#" class="forgot-link" @click.prevent="showForgotMsg = !showForgotMsg">Mot de passe oublié ?</a>
        </div>
        <p v-if="showForgotMsg" style="color:#64748b;font-size:0.85rem;margin-top:4px;">Contactez votre administrateur pour reinitialiser votre mot de passe.</p>
        <div class="password-wrap">
          <input
            id="password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            class="form-input"
            :class="{ error: errors.password }"
            placeholder="••••••••"
            autocomplete="current-password"
            @input="clearError('password')"
          />
          <button
            type="button"
            class="toggle-pass"
            :aria-label="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
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

      <div v-if="globalError" class="alert alert-error" role="alert" style="margin: 1rem 0;">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="flex-shrink:0">
          <circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.4"/>
          <path d="M8 5v3.5M8 10.5v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        {{ globalError }}
      </div>

      <button type="submit" class="btn btn-primary btn-xl" :disabled="loading" style="width: 100%; margin-top: 1.25rem; justify-content: center;">
        <span v-if="loading" class="spinner-sm spinner-white"></span>
        {{ loading ? 'Connexion…' : 'Se connecter' }}
      </button>

    </form>

    <div class="divider-text" style="margin: 1.5rem 0;">ou</div>

    <p class="signup-cta">
      Pas encore de compte ?
      <RouterLink to="/register" class="signup-link">Créer un espace de travail</RouterLink>
    </p>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { useRouter, useRoute, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { AxiosError } from 'axios'
import type { ApiError } from '@/api/types'

const router = useRouter()
const route  = useRoute()
const auth   = useAuthStore()

const form          = reactive({ email: '', password: '' })
const errors        = reactive<Record<string, string>>({})
const globalError   = ref('')
const loading       = ref(false)
const showPassword  = ref(false)
const showForgotMsg = ref(false)

// Show info banner if redirected due to session inactivity
const inactivityMsg = computed(() =>
  route.query.reason === 'inactivity'
    ? 'Votre session a expiré par inactivité. Veuillez vous reconnecter.'
    : ''
)

function clearError(field: string) {
  delete errors[field]
  globalError.value = ''
}

async function handleSubmit() {
  if (!form.email)    { errors.email    = 'L\'email est requis'; return }
  if (!form.password) { errors.password = 'Le mot de passe est requis'; return }

  loading.value    = true
  globalError.value = ''

  try {
    await auth.login({ email: form.email, password: form.password })
    const redirect = route.query.redirect as string
    // Super admin goes directly to the back-office, never the tenant app
    if (auth.user?.is_super_admin) {
      router.push(redirect?.startsWith('/admin') ? redirect : '/admin')
    } else {
      router.push(redirect || '/dashboard')
    }
  } catch (err) {
    const axiosErr = err as AxiosError<ApiError>
    const status   = axiosErr.response?.status

    if (status === 401) {
      globalError.value = 'Email ou mot de passe incorrect.'
    } else if (status === 403) {
      globalError.value = 'Votre compte est désactivé. Contactez votre administrateur.'
    } else if (status === 422) {
      const validationErrors = axiosErr.response?.data?.errors ?? {}
      Object.entries(validationErrors).forEach(([field, msgs]) => {
        errors[field] = msgs[0]
      })
    } else {
      globalError.value = 'Connexion impossible. Vérifiez votre connexion internet.'
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

.forgot-link {
  font-size: var(--text-xs);
  color: var(--brand-secondary);
  font-weight: 500;
  text-decoration: none;
  transition: color 0.15s;
}
.forgot-link:hover { color: var(--brand-secondary-dark); text-decoration: underline; }

.signup-cta {
  text-align: center;
  font-size: var(--text-sm);
  color: var(--gray-500);
  margin: 0;
}

.signup-link {
  color: var(--brand-secondary);
  font-weight: 600;
  text-decoration: none;
  transition: color 0.15s;
}
.signup-link:hover { color: var(--brand-secondary-dark); text-decoration: underline; }
</style>
