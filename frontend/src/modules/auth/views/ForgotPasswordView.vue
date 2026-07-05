<template>
  <div class="login-form">
    <div class="form-header">
      <h2>{{ $t('auth.reset.title') }}</h2>
      <p>{{ mode === 'request' ? $t('auth.reset.subtitleRequest') : $t('auth.reset.subtitleReset') }}</p>
    </div>

    <div v-if="notice" class="alert alert-info" role="status" style="margin-bottom:1rem">{{ notice }}</div>
    <p v-if="error" class="form-error" role="alert" style="margin-bottom:1rem">{{ error }}</p>

    <!-- Étape 1 : demande du code -->
    <form v-if="mode === 'request'" @submit.prevent="requestCode" novalidate>
      <div class="form-group">
        <label class="form-label" for="rp-email">{{ $t('auth.emailLabel') }}</label>
        <input id="rp-email" v-model.trim="form.email" type="email" class="form-input"
               :placeholder="$t('auth.emailPlaceholder')" autocomplete="email" />
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%" :disabled="busy || !form.email">
        <span v-if="busy" class="spinner-sm"></span>{{ $t('auth.reset.sendCode') }}
      </button>
    </form>

    <!-- Étape 2 : saisie du code + nouveau mot de passe -->
    <form v-else @submit.prevent="submitReset" novalidate>
      <div class="form-group">
        <label class="form-label" for="rp-code">{{ $t('auth.reset.code') }}</label>
        <input id="rp-code" v-model.trim="form.code" class="form-input" inputmode="numeric"
               :placeholder="$t('auth.reset.codePlaceholder')" autocomplete="one-time-code" />
      </div>
      <div class="form-group">
        <label class="form-label" for="rp-pass">{{ $t('auth.reset.newPassword') }}</label>
        <input id="rp-pass" v-model="form.password" type="password" class="form-input"
               placeholder="••••••••" autocomplete="new-password" />
      </div>
      <div class="form-group">
        <label class="form-label" for="rp-pass2">{{ $t('auth.reset.confirmPassword') }}</label>
        <input id="rp-pass2" v-model="form.password_confirmation" type="password" class="form-input"
               placeholder="••••••••" autocomplete="new-password" />
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%"
              :disabled="busy || !form.code || form.password.length < 8 || form.password !== form.password_confirmation">
        <span v-if="busy" class="spinner-sm"></span>{{ $t('auth.reset.submit') }}
      </button>
      <p style="margin-top:8px;font-size:0.85rem;text-align:center">
        <a href="#" class="forgot-link" @click.prevent="mode = 'request'">{{ $t('auth.reset.resend') }}</a>
      </p>
    </form>

    <p style="margin-top:1.25rem;text-align:center;font-size:0.9rem">
      <router-link to="/login" class="forgot-link">{{ $t('auth.reset.backToLogin') }}</router-link>
    </p>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { authService } from '@/modules/auth/services/authService'
import { t } from '@/i18n'

const router = useRouter()
const mode = ref<'request' | 'reset'>('request')
const busy = ref(false)
const error = ref('')
const notice = ref('')
const form = reactive({ email: '', code: '', password: '', password_confirmation: '' })

async function requestCode() {
  busy.value = true; error.value = ''; notice.value = ''
  try {
    const res = await authService.forgotPassword(form.email)
    notice.value = res.message ?? t('auth.reset.codeSent')
    mode.value = 'reset'
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? t('common.genericError')
  } finally { busy.value = false }
}

async function submitReset() {
  busy.value = true; error.value = ''
  try {
    await authService.resetPassword({
      email: form.email, code: form.code,
      password: form.password, password_confirmation: form.password_confirmation,
    })
    notice.value = t('auth.reset.success')
    setTimeout(() => router.push({ name: 'login' }), 900)
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? t('auth.reset.badCode')
  } finally { busy.value = false }
}
</script>
