<template>
  <div class="login-form">
    <h2 class="form-title">Connexion</h2>
    <p class="form-subtitle">Accédez à votre espace de gestion</p>

    <form @submit.prevent="handleSubmit" novalidate>

      <div class="field">
        <label for="email">Adresse email</label>
        <input
          id="email"
          v-model="form.email"
          type="email"
          placeholder="vous@boutique.sn"
          autocomplete="email"
          :class="{ 'input-error': errors.email }"
          @input="clearError('email')"
        />
        <span v-if="errors.email" class="error-msg">{{ errors.email }}</span>
      </div>

      <div class="field">
        <label for="password">Mot de passe</label>
        <div class="password-wrap">
          <input
            id="password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            placeholder="••••••••"
            autocomplete="current-password"
            :class="{ 'input-error': errors.password }"
            @input="clearError('password')"
          />
          <button
            type="button"
            class="toggle-pass"
            @click="showPassword = !showPassword"
            :aria-label="showPassword ? 'Masquer' : 'Afficher'"
          >{{ showPassword ? '🙈' : '👁' }}</button>
        </div>
        <span v-if="errors.password" class="error-msg">{{ errors.password }}</span>
      </div>

      <div v-if="globalError" class="alert-error" role="alert">
        {{ globalError }}
      </div>

      <button type="submit" class="btn-submit" :disabled="loading">
        <span v-if="loading" class="spinner"></span>
        <span>{{ loading ? 'Connexion…' : 'Se connecter' }}</span>
      </button>

    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { AxiosError } from 'axios'
import type { ApiError } from '@/api/types'

const router = useRouter()
const route  = useRoute()
const auth   = useAuthStore()

const form = reactive({ email: '', password: '' })
const errors = reactive<Record<string, string>>({})
const globalError = ref('')
const loading     = ref(false)
const showPassword = ref(false)

function clearError(field: string) {
  delete errors[field]
  globalError.value = ''
}

async function handleSubmit() {
  // Client-side validation
  if (!form.email)    { errors.email    = 'L\'email est requis'; return }
  if (!form.password) { errors.password = 'Le mot de passe est requis'; return }

  loading.value = true
  globalError.value = ''

  try {
    await auth.login({ email: form.email, password: form.password })
    const redirect = route.query.redirect as string
    router.push(redirect || '/dashboard')
  } catch (err) {
    const axiosErr = err as AxiosError<ApiError>
    const status   = axiosErr.response?.status

    if (status === 401) {
      globalError.value = 'Email ou mot de passe incorrect.'
    } else if (status === 403) {
      globalError.value = 'Votre compte ou votre boutique est suspendu.'
    } else if (status === 422) {
      const validationErrors = axiosErr.response?.data?.errors ?? {}
      Object.entries(validationErrors).forEach(([field, msgs]) => {
        errors[field] = msgs[0]
      })
    } else {
      globalError.value = 'Impossible de se connecter. Vérifiez votre connexion.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-form { }

.form-title {
  font-size: 1.4rem;
  font-weight: 700;
  color: #111827;
  margin: 0 0 0.3rem;
  text-align: center;
}

.form-subtitle {
  font-size: 0.875rem;
  color: #6b7280;
  text-align: center;
  margin: 0 0 2rem;
}

.field {
  margin-bottom: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.field label {
  font-size: 0.875rem;
  font-weight: 500;
  color: #374151;
}

.field input {
  padding: 0.65rem 0.875rem;
  border: 1.5px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.95rem;
  outline: none;
  transition: border-color 0.15s;
  width: 100%;
  box-sizing: border-box;
}

.field input:focus { border-color: #059669; box-shadow: 0 0 0 3px rgba(5,150,105,0.1); }
.field input.input-error { border-color: #dc2626; }

.password-wrap { position: relative; }
.password-wrap input { padding-right: 2.5rem; }

.toggle-pass {
  position: absolute;
  right: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  font-size: 1rem;
  padding: 0;
  line-height: 1;
}

.error-msg {
  font-size: 0.8rem;
  color: #dc2626;
}

.alert-error {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #dc2626;
  border-radius: 8px;
  padding: 0.75rem 1rem;
  font-size: 0.875rem;
  margin-bottom: 1.25rem;
}

.btn-submit {
  width: 100%;
  padding: 0.75rem;
  background: #059669;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.btn-submit:hover:not(:disabled) { background: #047857; }
.btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }

.spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255,255,255,0.4);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
  flex-shrink: 0;
}

@keyframes spin { to { transform: rotate(360deg); } }
</style>
