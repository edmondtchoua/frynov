<template>
  <div class="login-form">
    <div class="form-header">
      <h2>{{ $t('auth.invitation.title') }}</h2>
      <p>{{ $t('auth.invitation.subtitle') }}</p>
    </div>

    <div v-if="notice" class="alert alert-info" role="status" style="margin-bottom:1rem">{{ notice }}</div>
    <p v-if="error" class="form-error" role="alert" style="margin-bottom:1rem">{{ error }}</p>

    <form @submit.prevent="submit" novalidate>
      <div class="form-group">
        <label class="form-label" for="ai-email">{{ $t('auth.emailLabel') }}</label>
        <input id="ai-email" v-model.trim="form.email" type="email" class="form-input"
               :placeholder="$t('auth.emailPlaceholder')" autocomplete="email" />
      </div>
      <div class="form-group">
        <label class="form-label" for="ai-code">{{ $t('auth.invitation.code') }}</label>
        <input id="ai-code" v-model.trim="form.code" class="form-input" inputmode="numeric"
               :placeholder="$t('auth.invitation.codePlaceholder')" autocomplete="one-time-code" />
      </div>
      <div class="form-group">
        <label class="form-label" for="ai-pass">{{ $t('auth.invitation.password') }}</label>
        <input id="ai-pass" v-model="form.password" type="password" class="form-input"
               placeholder="••••••••" autocomplete="new-password" />
      </div>
      <div class="form-group">
        <label class="form-label" for="ai-pass2">{{ $t('auth.invitation.confirmPassword') }}</label>
        <input id="ai-pass2" v-model="form.password_confirmation" type="password" class="form-input"
               placeholder="••••••••" autocomplete="new-password" />
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%"
              :disabled="busy || !form.email || !form.code || form.password.length < 8 || form.password !== form.password_confirmation">
        <span v-if="busy" class="spinner-sm"></span>{{ $t('auth.invitation.submit') }}
      </button>
    </form>

    <p style="margin-top:1.25rem;text-align:center;font-size:0.9rem">
      <router-link to="/login" class="forgot-link">{{ $t('auth.reset.backToLogin') }}</router-link>
    </p>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { authService } from '@/modules/auth/services/authService'
import { t } from '@/i18n'

const route = useRoute()
const router = useRouter()
const busy = ref(false)
const error = ref('')
const notice = ref('')
const form = reactive({
  email: typeof route.query.email === 'string' ? route.query.email : '',
  code: '', password: '', password_confirmation: '',
})

async function submit() {
  busy.value = true; error.value = ''
  try {
    await authService.acceptInvitation({
      email: form.email, code: form.code,
      password: form.password, password_confirmation: form.password_confirmation,
    })
    notice.value = t('auth.invitation.success')
    setTimeout(() => router.push({ name: 'login' }), 900)
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? t('auth.invitation.badCode')
  } finally { busy.value = false }
}
</script>
