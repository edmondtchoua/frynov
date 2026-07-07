<template>
  <div class="contact-page">
    <div class="contact-card">
      <!-- Écran de succès -->
      <div v-if="sent" class="contact-success" role="status">
        <div class="contact-success-icon">✓</div>
        <h2>{{ $t('contact.successTitle') }}</h2>
        <p>{{ $t('contact.successMessage') }}</p>
        <router-link to="/" class="btn btn-primary">{{ $t('contact.backHome') }}</router-link>
      </div>

      <!-- Formulaire -->
      <template v-else>
        <div class="form-header">
          <h2>{{ $t('contact.title') }}</h2>
          <p>{{ $t('contact.subtitle') }}</p>
        </div>

        <p v-if="error" class="form-error" role="alert" style="margin-bottom:1rem">{{ error }}</p>

        <form @submit.prevent="submit" novalidate>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="c-first">{{ $t('contact.firstName') }}</label>
              <input id="c-first" v-model.trim="form.first_name" class="form-input" autocomplete="given-name" />
            </div>
            <div class="form-group">
              <label class="form-label" for="c-last">{{ $t('contact.lastName') }}</label>
              <input id="c-last" v-model.trim="form.last_name" class="form-input" autocomplete="family-name" />
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="c-email">{{ $t('contact.email') }} *</label>
            <input id="c-email" v-model.trim="form.email" type="email" class="form-input"
                   autocomplete="email" required />
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="c-phone">{{ $t('contact.phone') }}</label>
              <input id="c-phone" v-model.trim="form.phone" class="form-input" autocomplete="tel" />
            </div>
            <div class="form-group">
              <label class="form-label" for="c-company">{{ $t('contact.company') }}</label>
              <input id="c-company" v-model.trim="form.company" class="form-input" autocomplete="organization" />
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="c-need">{{ $t('contact.need') }}</label>
            <input id="c-need" v-model.trim="form.primary_need" class="form-input"
                   :placeholder="$t('contact.needPlaceholder')" />
          </div>

          <div class="form-group">
            <label class="form-label">{{ $t('contact.modules') }}</label>
            <div class="contact-modules">
              <label v-for="m in moduleKeys" :key="m" class="contact-chip"
                     :class="{ 'contact-chip--on': form.modules.includes(m) }">
                <input type="checkbox" :value="m" v-model="form.modules" />
                {{ $t('contact.moduleList.' + m) }}
              </label>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="c-message">{{ $t('contact.message') }}</label>
            <textarea id="c-message" v-model.trim="form.message" class="form-input" rows="3"
                      :placeholder="$t('contact.messagePlaceholder')"></textarea>
          </div>

          <!-- Honeypot anti-bot : masqué, ne doit jamais être rempli -->
          <div class="contact-hp" aria-hidden="true">
            <label>Website<input v-model="form.website" tabindex="-1" autocomplete="off" /></label>
          </div>

          <label class="contact-consent">
            <input type="checkbox" v-model="form.consent_contact" required />
            <span>{{ $t('contact.consentContact') }} *</span>
          </label>
          <label class="contact-consent">
            <input type="checkbox" v-model="form.consent_demo_email" />
            <span>{{ $t('contact.consentEmail') }}</span>
          </label>

          <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.5rem"
                  :disabled="busy || !form.email || !form.consent_contact">
            <span v-if="busy" class="spinner-sm"></span>
            {{ busy ? $t('contact.submitting') : $t('contact.submit') }}
          </button>
        </form>

        <p style="margin-top:1.25rem;text-align:center;font-size:0.9rem">
          <router-link to="/" class="forgot-link">{{ $t('contact.backHome') }}</router-link>
        </p>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { demoService } from '@/services/demoService'
import { t, useI18n } from '@/i18n'

const { locale } = useI18n()

const moduleKeys = ['catalog', 'inventory', 'orders', 'customers', 'payments', 'accounting', 'pos', 'reports']

const busy = ref(false)
const sent = ref(false)
const error = ref('')

const form = reactive({
  first_name: '', last_name: '', email: '', phone: '', company: '',
  primary_need: '', message: '', modules: [] as string[],
  consent_contact: false, consent_demo_email: true, website: '',
})

async function submit() {
  if (!form.consent_contact) { error.value = t('contact.consentRequired'); return }
  busy.value = true; error.value = ''
  try {
    await demoService.submitRequest({
      ...form,
      source: 'contact-page',
      locale: locale.value,
    })
    sent.value = true
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? t('contact.error')
  } finally {
    busy.value = false
  }
}
</script>

<style scoped>
.contact-page {
  min-height: 100vh;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 3rem 1rem;
  background: var(--color-bg, #f7f8fa);
}
.contact-card {
  width: 100%;
  max-width: 560px;
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
  padding: 2rem;
}
.form-header { margin-bottom: 1.5rem; }
.form-header h2 { font-size: 1.5rem; font-weight: 700; margin: 0 0 0.4rem; }
.form-header p { color: #667085; font-size: 0.95rem; margin: 0; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
@media (max-width: 480px) { .form-row { grid-template-columns: 1fr; } }
.contact-modules { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.contact-chip {
  display: inline-flex; align-items: center; gap: 0.35rem;
  padding: 0.35rem 0.7rem; border: 1px solid #d0d5dd; border-radius: 999px;
  font-size: 0.85rem; cursor: pointer; user-select: none;
}
.contact-chip input { display: none; }
.contact-chip--on { background: var(--color-primary, #2563eb); color: #fff; border-color: transparent; }
.contact-consent {
  display: flex; align-items: flex-start; gap: 0.5rem;
  font-size: 0.88rem; color: #475467; margin: 0.5rem 0;
}
.contact-consent input { margin-top: 0.2rem; }
.contact-hp { position: absolute; left: -9999px; height: 0; overflow: hidden; }
.contact-success { text-align: center; padding: 1.5rem 0; }
.contact-success-icon {
  width: 56px; height: 56px; border-radius: 50%; margin: 0 auto 1rem;
  display: flex; align-items: center; justify-content: center;
  background: #d1fae5; color: #059669; font-size: 1.8rem; font-weight: 700;
}
.contact-success h2 { margin: 0 0 0.5rem; }
.contact-success p { color: #667085; margin: 0 0 1.5rem; }
</style>
