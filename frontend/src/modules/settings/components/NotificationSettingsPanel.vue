<template>
  <div>
    <div class="panel-header">
      <h3>{{ $t('settings.notif.title') }}</h3>
      <p>{{ $t('settings.notif.desc') }}</p>
    </div>

    <!-- Sub-tabs -->
    <div class="ntf-tabs">
      <button v-for="st in ['channels', 'templates', 'outbox', 'credits']" :key="st"
              class="ntf-tab" :class="{ active: tab === st }" @click="switchTab(st as any)">
        {{ $t('settings.notif.tab.' + st) }}
      </button>
    </div>

    <!-- ── Channels ────────────────────────────────────────────────────── -->
    <div v-if="tab === 'channels'">
      <div style="display:flex;justify-content:flex-end;margin-bottom:10px">
        <button class="btn btn-primary btn-sm" @click="openChannelForm()">{{ $t('settings.notif.addChannel') }}</button>
      </div>

      <div v-if="!channels.length" class="ntf-empty">{{ $t('settings.notif.noChannel') }}</div>
      <table v-else class="data-table">
        <thead><tr>
          <th>{{ $t('common.name') }}</th>
          <th>{{ $t('settings.notif.channelType') }}</th>
          <th>{{ $t('settings.notif.provider') }}</th>
          <th>{{ $t('settings.notif.sender') }}</th>
          <th>{{ $t('common.status') }}</th>
          <th></th>
        </tr></thead>
        <tbody>
          <tr v-for="c in channels" :key="c.id">
            <td>{{ c.name }} <span v-if="c.is_default" class="badge badge-blue" style="font-size:0.68rem">{{ $t('settings.notif.default') }}</span></td>
            <td>{{ $t('settings.notif.channelName.' + c.channel) }}</td>
            <td><code style="font-size:0.78rem">{{ c.provider }}</code></td>
            <td style="font-size:0.82rem">{{ c.from_name }} <span class="text-muted">{{ c.from_address }}</span></td>
            <td><span class="badge" :class="c.is_active ? 'badge-success' : 'badge-gray'">{{ c.is_active ? $t('settings.notif.active') : $t('settings.notif.inactive') }}</span></td>
            <td style="white-space:nowrap;text-align:right">
              <button class="btn btn-ghost btn-sm" @click="openTest(c)">{{ $t('settings.notif.test') }}</button>
              <button class="btn btn-ghost btn-sm" @click="openChannelForm(c)">{{ $t('common.edit') }}</button>
              <button class="btn btn-ghost btn-sm" style="color:#dc2626" @click="removeChannel(c)">{{ $t('common.delete') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── Templates ───────────────────────────────────────────────────── -->
    <div v-else-if="tab === 'templates'">
      <div v-if="!templates.length" class="ntf-empty">{{ $t('settings.notif.noTemplate') }}</div>
      <table v-else class="data-table">
        <thead><tr>
          <th>{{ $t('settings.notif.templateCode') }}</th>
          <th>{{ $t('settings.notif.channelType') }}</th>
          <th>{{ $t('settings.notif.subject') }}</th>
          <th>{{ $t('settings.notif.origin') }}</th>
          <th></th>
        </tr></thead>
        <tbody>
          <tr v-for="tp in templates" :key="tp.id">
            <td><code style="font-size:0.78rem">{{ tp.code }}</code></td>
            <td>{{ $t('settings.notif.channelName.' + tp.channel) }}</td>
            <td style="font-size:0.82rem">{{ tp.subject || '—' }}</td>
            <td><span class="badge" :class="tp.is_global ? 'badge-gray' : 'badge-blue'">{{ tp.is_global ? $t('settings.notif.global') : $t('settings.notif.customized') }}</span></td>
            <td style="text-align:right"><button class="btn btn-ghost btn-sm" @click="openTemplateForm(tp)">{{ $t('common.edit') }}</button></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── Outbox / journal ────────────────────────────────────────────── -->
    <div v-else-if="tab === 'outbox'">
      <div v-if="!outbox.length" class="ntf-empty">{{ $t('settings.notif.noOutbox') }}</div>
      <table v-else class="data-table">
        <thead><tr>
          <th>{{ $t('common.date') }}</th>
          <th>{{ $t('settings.notif.recipient') }}</th>
          <th>{{ $t('settings.notif.subject') }}</th>
          <th>{{ $t('common.status') }}</th>
        </tr></thead>
        <tbody>
          <tr v-for="o in outbox" :key="o.id">
            <td style="font-size:0.8rem">{{ new Date(o.created_at).toLocaleString() }}</td>
            <td style="font-size:0.82rem">{{ o.recipient }}</td>
            <td style="font-size:0.82rem">{{ o.subject || o.template_code }}</td>
            <td>
              <span class="badge" :class="{ sent: 'badge-success', failed: 'badge-error', pending: 'badge-warning', no_credit: 'badge-error' }[o.status]"
                    :title="o.last_error || ''">
                {{ $t('settings.notif.outboxStatus.' + o.status) }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── Crédits de communication (RC-7E) ────────────────────────────── -->
    <div v-else-if="tab === 'credits'">
      <p class="ntf-credit-intro">{{ $t('settings.notif.credit.intro') }}</p>

      <div class="ntf-credit-grid">
        <div v-for="ch in (['email', 'sms', 'whatsapp'] as const)" :key="ch" class="ntf-credit-card">
          <div class="ntf-credit-head">
            <span class="ntf-credit-channel">{{ $t('settings.notif.channelName.' + ch) }}</span>
            <span v-if="credits.metered.includes(ch)" class="badge badge-blue" style="font-size:0.66rem">{{ $t('settings.notif.credit.metered') }}</span>
            <span v-else class="badge badge-gray" style="font-size:0.66rem">{{ $t('settings.notif.credit.free') }}</span>
          </div>
          <div class="ntf-credit-balance">{{ (credits.balances[ch] ?? 0).toLocaleString() }}</div>
          <div class="ntf-credit-unit">{{ $t('settings.notif.credit.sends') }}</div>
          <button class="btn btn-primary btn-sm" style="margin-top:8px" @click="openRecharge(ch)">{{ $t('settings.notif.credit.recharge') }}</button>
        </div>
      </div>

      <!-- RC-7F — commandes de recharge Mobile Money (référence payable → crédit auto au webhook) -->
      <template v-if="orders.length">
        <h4 class="ntf-credit-subtitle">{{ $t('settings.notif.credit.orders') }}</h4>
        <table class="data-table" style="margin-bottom:22px">
          <thead><tr>
            <th>{{ $t('common.date') }}</th>
            <th>{{ $t('settings.notif.credit.reference') }}</th>
            <th>{{ $t('settings.notif.channelType') }}</th>
            <th style="text-align:right">{{ $t('settings.notif.credit.amount') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th></th>
          </tr></thead>
          <tbody>
            <tr v-for="o in orders" :key="o.id">
              <td style="font-size:0.8rem">{{ new Date(o.created_at).toLocaleString() }}</td>
              <td><code style="font-size:0.8rem">{{ o.reference }}</code></td>
              <td>{{ $t('settings.notif.channelName.' + o.channel) }}</td>
              <td style="text-align:right">{{ (o.price_cents / 100).toLocaleString() }} {{ o.currency }}</td>
              <td>
                <span class="badge" :class="{ pending: 'badge-warning', paid: 'badge-success', cancelled: 'badge-gray', needs_review: 'badge-error' }[o.status]">
                  {{ $t('settings.notif.credit.orderStatus.' + o.status) }}
                </span>
              </td>
              <td style="text-align:right">
                <button v-if="o.status === 'pending'" class="btn btn-ghost btn-sm" @click="cancelOrder(o)">{{ $t('common.cancel') }}</button>
              </td>
            </tr>
          </tbody>
        </table>
      </template>

      <h4 class="ntf-credit-subtitle">{{ $t('settings.notif.credit.movements') }}</h4>
      <div v-if="!movements.length" class="ntf-empty">{{ $t('settings.notif.credit.noMovement') }}</div>
      <table v-else class="data-table">
        <thead><tr>
          <th>{{ $t('common.date') }}</th>
          <th>{{ $t('settings.notif.channelType') }}</th>
          <th>{{ $t('settings.notif.credit.reason') }}</th>
          <th style="text-align:right">{{ $t('settings.notif.credit.delta') }}</th>
          <th style="text-align:right">{{ $t('settings.notif.credit.balanceAfter') }}</th>
        </tr></thead>
        <tbody>
          <tr v-for="m in movements" :key="m.id">
            <td style="font-size:0.8rem">{{ new Date(m.created_at).toLocaleString() }}</td>
            <td>{{ $t('settings.notif.channelName.' + m.channel) }}</td>
            <td>{{ $t('settings.notif.credit.reasonName.' + m.reason) }}</td>
            <td style="text-align:right" :style="{ color: m.delta < 0 ? '#dc2626' : '#059669' }">{{ m.delta > 0 ? '+' : '' }}{{ m.delta.toLocaleString() }}</td>
            <td style="text-align:right">{{ m.balance_after.toLocaleString() }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── Recharge modal (RC-7E + RC-7F) ──────────────────────────────── -->
    <BaseModal v-model="rechargeModal.open" :title="$t('settings.notif.credit.rechargeTitle')">
      <div style="display:flex;flex-direction:column;gap:12px">
        <!-- RC-7F — la commande Mobile Money vient d'être créée : afficher la référence payable. -->
        <template v-if="rechargeModal.createdOrder">
          <p class="hint">{{ $t('settings.notif.credit.orderCreatedHint') }}</p>
          <div class="ntf-order-ref">
            <div class="ntf-order-ref-code">{{ rechargeModal.createdOrder.reference }}</div>
            <div class="ntf-order-ref-amount">{{ (rechargeModal.createdOrder.price_cents / 100).toLocaleString() }} {{ rechargeModal.createdOrder.currency }}</div>
          </div>
        </template>

        <template v-else>
          <p class="hint">{{ $t('settings.notif.credit.rechargeHint') }}</p>
          <div class="form-group">
            <label class="form-label">{{ $t('settings.notif.credit.mode') }}</label>
            <select v-model="rechargeForm.mode" class="form-input">
              <option value="mobile_money">{{ $t('settings.notif.credit.modeMomo') }}</option>
              <option value="manual">{{ $t('settings.notif.credit.modeManual') }}</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">{{ $t('settings.notif.credit.pack') }}</label>
            <select v-model="rechargeForm.pack_code" class="form-input">
              <option v-for="p in packsForChannel(rechargeModal.channel)" :key="p.code" :value="p.code">
                {{ p.credits.toLocaleString() }} {{ $t('settings.notif.credit.sends') }} — {{ (p.price_cents / 100).toLocaleString() }} {{ p.currency }}
              </option>
            </select>
          </div>
          <div v-if="rechargeForm.mode === 'manual'" class="form-group">
            <label class="form-label">{{ $t('settings.notif.credit.paymentRef') }}</label>
            <input v-model="rechargeForm.payment_reference" class="form-input" :placeholder="$t('settings.notif.credit.paymentRefPlaceholder')" />
          </div>
          <p v-else class="hint">{{ $t('settings.notif.credit.momoHint') }}</p>
        </template>

        <p v-if="rechargeModal.error" style="color:#dc2626;font-size:0.85rem">{{ rechargeModal.error }}</p>
      </div>
      <template #footer>
        <button class="btn btn-ghost" @click="rechargeModal.open = false">{{ rechargeModal.createdOrder ? $t('common.close') : $t('common.cancel') }}</button>
        <button v-if="!rechargeModal.createdOrder" class="btn btn-primary" :disabled="rechargeModal.saving || !rechargeForm.pack_code" @click="submitRecharge">
          <span v-if="rechargeModal.saving" class="spinner-sm"></span>
          {{ rechargeForm.mode === 'manual' ? $t('settings.notif.credit.confirmRecharge') : $t('settings.notif.credit.createOrder') }}
        </button>
      </template>
    </BaseModal>

    <!-- ── Channel modal ───────────────────────────────────────────────── -->
    <BaseModal v-model="channelModal.open" :title="channelModal.id ? $t('settings.notif.editChannel') : $t('settings.notif.addChannel')">
      <div style="display:flex;flex-direction:column;gap:12px">
        <div class="form-row2" style="display:flex;gap:10px">
          <div class="form-group" style="flex:1">
            <label class="form-label">{{ $t('settings.notif.channelType') }}</label>
            <select v-model="channelForm.channel" class="form-input">
              <option value="email">{{ $t('settings.notif.channelName.email') }}</option>
              <option value="sms">{{ $t('settings.notif.channelName.sms') }}</option>
              <option value="whatsapp">{{ $t('settings.notif.channelName.whatsapp') }}</option>
            </select>
          </div>
          <div class="form-group" style="flex:1">
            <label class="form-label">{{ $t('settings.notif.provider') }}</label>
            <select v-model="channelForm.provider" class="form-input">
              <option v-if="channelForm.channel === 'email'" value="smtp">{{ $t('settings.notif.providerName.smtp') }}</option>
              <option value="http_api">{{ $t('settings.notif.providerName.http_api') }}</option>
              <option value="log">{{ $t('settings.notif.providerName.log') }}</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">{{ $t('common.name') }}</label>
          <input v-model="channelForm.name" class="form-input" :placeholder="$t('settings.notif.namePlaceholder')" />
        </div>

        <div class="form-row2" style="display:flex;gap:10px">
          <div class="form-group" style="flex:1">
            <label class="form-label">{{ $t('settings.notif.fromName') }}</label>
            <input v-model="channelForm.from_name" class="form-input" />
          </div>
          <div class="form-group" style="flex:1">
            <label class="form-label">{{ $t('settings.notif.fromAddress') }}</label>
            <input v-model="channelForm.from_address" class="form-input" :placeholder="channelForm.channel === 'email' ? 'no-reply@…' : 'FRYNOV / 221xx'" />
          </div>
        </div>

        <!-- SMTP config -->
        <template v-if="channelForm.provider === 'smtp'">
          <div class="form-row2" style="display:flex;gap:10px">
            <div class="form-group" style="flex:2">
              <label class="form-label">{{ $t('settings.notif.smtpHost') }}</label>
              <input v-model="channelForm.cfg.host" class="form-input" placeholder="smtp.example.com" />
            </div>
            <div class="form-group" style="flex:1">
              <label class="form-label">{{ $t('settings.notif.smtpPort') }}</label>
              <input v-model="channelForm.cfg.port" class="form-input" placeholder="587" />
            </div>
          </div>
          <div class="form-row2" style="display:flex;gap:10px">
            <div class="form-group" style="flex:1">
              <label class="form-label">{{ $t('settings.notif.smtpUser') }}</label>
              <input v-model="channelForm.cfg.username" class="form-input" autocomplete="off" />
            </div>
            <div class="form-group" style="flex:1">
              <label class="form-label">{{ $t('settings.notif.smtpPass') }}</label>
              <input v-model="channelForm.cfg.password" type="password" class="form-input" autocomplete="new-password"
                     :placeholder="channelModal.id ? $t('settings.notif.secretKept') : ''" />
            </div>
          </div>
        </template>

        <!-- HTTP proxy config -->
        <template v-else-if="channelForm.provider === 'http_api'">
          <div class="form-group">
            <label class="form-label">{{ $t('settings.notif.apiUrl') }}</label>
            <input v-model="channelForm.cfg.url" class="form-input" placeholder="https://api.provider.com/send" />
          </div>
          <div class="form-group">
            <label class="form-label">{{ $t('settings.notif.apiHeaders') }}</label>
            <textarea v-model="channelForm.headersJson" class="form-input" rows="2" placeholder='{"Authorization": "Bearer …"}'></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">{{ $t('settings.notif.apiPayload') }}</label>
            <textarea v-model="channelForm.payloadJson" class="form-input" rows="3" placeholder='{"to": "{{to}}", "message": "{{message}}", "sender": "{{from}}"}'></textarea>
            <span class="hint">{{ $t('settings.notif.apiPayloadHint') }}</span>
          </div>
        </template>

        <label style="display:flex;align-items:center;gap:8px;font-size:0.85rem">
          <input v-model="channelForm.is_default" type="checkbox" /> {{ $t('settings.notif.makeDefault') }}
        </label>

        <p v-if="channelModal.error" style="color:#dc2626;font-size:0.85rem">{{ channelModal.error }}</p>
      </div>
      <template #footer>
        <button class="btn btn-ghost" @click="channelModal.open = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="channelModal.saving || !channelForm.name" @click="saveChannel">
          <span v-if="channelModal.saving" class="spinner-sm"></span>
          {{ $t('common.save') }}
        </button>
      </template>
    </BaseModal>

    <!-- ── Template modal ──────────────────────────────────────────────── -->
    <BaseModal v-model="templateModal.open" :title="$t('settings.notif.editTemplate')">
      <div style="display:flex;flex-direction:column;gap:12px">
        <div><code style="font-size:0.8rem">{{ templateForm.code }}</code> · {{ $t('settings.notif.channelName.' + templateForm.channel) }}</div>
        <div class="form-group" v-if="templateForm.channel === 'email'">
          <label class="form-label">{{ $t('settings.notif.subject') }}</label>
          <input v-model="templateForm.subject" class="form-input" />
        </div>
        <div class="form-group">
          <label class="form-label">{{ $t('settings.notif.body') }}</label>
          <textarea v-model="templateForm.body" class="form-input" rows="8"></textarea>
          <span class="hint">{{ $t('settings.notif.placeholdersHint') }}</span>
        </div>
        <p v-if="templateModal.error" style="color:#dc2626;font-size:0.85rem">{{ templateModal.error }}</p>
      </div>
      <template #footer>
        <button class="btn btn-ghost" @click="templateModal.open = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="templateModal.saving || !templateForm.body" @click="saveTemplate">
          <span v-if="templateModal.saving" class="spinner-sm"></span>
          {{ $t('common.save') }}
        </button>
      </template>
    </BaseModal>

    <!-- ── Test modal ──────────────────────────────────────────────────── -->
    <BaseModal v-model="testModal.open" :title="$t('settings.notif.testTitle')">
      <div class="form-group">
        <label class="form-label">{{ $t('settings.notif.recipient') }}</label>
        <input v-model="testModal.recipient" class="form-input" :placeholder="testModal.channelType === 'email' ? 'vous@exemple.com' : '+221…'" />
      </div>
      <p v-if="testModal.result" :style="{ color: testModal.ok ? '#059669' : '#dc2626', fontSize: '0.85rem' }">{{ testModal.result }}</p>
      <template #footer>
        <button class="btn btn-ghost" @click="testModal.open = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="testModal.sending || !testModal.recipient" @click="sendTest">
          <span v-if="testModal.sending" class="spinner-sm"></span>
          {{ $t('settings.notif.sendTest') }}
        </button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import client from '@/api/client'
import BaseModal from '@/shared/ui/BaseModal.vue'
import { t } from '@/i18n'

type ChannelKind = 'email' | 'sms' | 'whatsapp'
interface Channel { id: string; channel: ChannelKind; provider: string; name: string; from_name: string | null; from_address: string | null; config_keys: string[]; is_active: boolean; is_default: boolean }
interface Template { id: string; code: string; channel: string; locale: string; subject: string | null; body: string; is_global: boolean }
interface OutboxItem { id: string; recipient: string; subject: string | null; template_code: string | null; status: 'pending' | 'sent' | 'failed' | 'no_credit'; attempts: number; last_error: string | null; created_at: string }
interface CreditPack { code: string; channel: ChannelKind; credits: number; price_cents: number; currency: string }
interface CreditMovement { id: string; channel: ChannelKind; delta: number; balance_after: number; reason: string; reference: string | null; created_at: string }
// RC-7F — commande de recharge payable par Mobile Money (webhook → crédit auto).
interface RechargeOrder { id: string; reference: string; pack_code: string; channel: ChannelKind; credits: number; price_cents: number; currency: string; status: 'pending' | 'paid' | 'cancelled' | 'needs_review'; created_at: string }

const tab       = ref<'channels' | 'templates' | 'outbox' | 'credits'>('channels')
const channels  = ref<Channel[]>([])
const templates = ref<Template[]>([])
const outbox    = ref<OutboxItem[]>([])

function switchTab(next: 'channels' | 'templates' | 'outbox' | 'credits') {
  tab.value = next
  if (next === 'templates' && !templates.value.length) loadTemplates()
  if (next === 'outbox') loadOutbox()
  if (next === 'credits') loadCredits()
}

async function loadChannels()  { try { channels.value  = (await client.get('/api/notifications/channels')).data.data } catch { channels.value = [] } }
async function loadTemplates() { try { templates.value = (await client.get('/api/notifications/templates')).data.data } catch { templates.value = [] } }
async function loadOutbox()    { try { outbox.value    = (await client.get('/api/notifications/outbox')).data.data } catch { outbox.value = [] } }

// ── Crédits de communication (RC-7E) ───────────────────────────────────────
const credits   = reactive({ enabled: true, balances: {} as Record<string, number>, metered: [] as string[], packs: [] as CreditPack[] })
const movements = ref<CreditMovement[]>([])
const orders    = ref<RechargeOrder[]>([])

async function loadCredits() {
  try {
    const { data } = await client.get('/api/notifications/credits')
    credits.enabled  = data.data.enabled
    credits.balances = data.data.balances
    credits.metered  = data.data.metered
    credits.packs    = data.data.packs
  } catch { /* silencieux : panneau vide */ }
  try { movements.value = (await client.get('/api/notifications/credits/movements')).data.data } catch { movements.value = [] }
  try { orders.value    = (await client.get('/api/notifications/credits/orders')).data.data } catch { orders.value = [] }
}

function packsForChannel(channel: ChannelKind): CreditPack[] {
  return credits.packs.filter(p => p.channel === channel)
}

const rechargeModal = reactive({ open: false, saving: false, error: '', channel: 'sms' as ChannelKind, createdOrder: null as RechargeOrder | null })
const rechargeForm  = reactive({ mode: 'mobile_money' as 'mobile_money' | 'manual', pack_code: '', payment_reference: '' })

function openRecharge(channel: ChannelKind) {
  rechargeModal.channel = channel
  rechargeModal.error = ''
  rechargeModal.createdOrder = null
  rechargeForm.mode = 'mobile_money'
  rechargeForm.pack_code = packsForChannel(channel)[0]?.code ?? ''
  rechargeForm.payment_reference = ''
  rechargeModal.open = true
}

async function submitRecharge() {
  rechargeModal.saving = true
  rechargeModal.error = ''
  try {
    if (rechargeForm.mode === 'manual') {
      // RC-7E — rail manuel : l'opérateur a déjà encaissé, le solde est crédité immédiatement.
      await client.post('/api/notifications/credits/recharge', {
        pack_code: rechargeForm.pack_code,
        payment_reference: rechargeForm.payment_reference || undefined,
      })
      rechargeModal.open = false
    } else {
      // RC-7F — Mobile Money : on génère la référence payable ; le webhook créditera à la confirmation.
      const { data } = await client.post('/api/notifications/credits/orders', { pack_code: rechargeForm.pack_code })
      rechargeModal.createdOrder = data.data
    }
    loadCredits()
  } catch (e: any) {
    rechargeModal.error = e?.response?.data?.message ?? t('common.genericError')
  } finally {
    rechargeModal.saving = false
  }
}

async function cancelOrder(o: RechargeOrder) {
  // Recharge la liste dans tous les cas : un échec signifie souvent que la commande vient d'être
  // payée par le webhook (course), et l'affichage doit refléter le nouvel état.
  try { await client.post(`/api/notifications/credits/orders/${o.id}/cancel`) } catch { /* état rafraîchi ci-dessous */ }
  loadCredits()
}

// ── Channel form ─────────────────────────────────────────────────────────
const channelModal = reactive({ open: false, saving: false, error: '', id: '' })
const channelForm  = reactive({
  channel: 'email' as Channel['channel'], provider: 'smtp', name: '', from_name: '', from_address: '',
  is_default: true, cfg: { host: '', port: '', username: '', password: '', url: '' } as Record<string, string>,
  headersJson: '', payloadJson: '',
})

function openChannelForm(c?: Channel) {
  channelModal.id = c?.id ?? ''
  channelModal.error = ''
  channelForm.channel      = c?.channel ?? 'email'
  channelForm.provider     = c?.provider ?? (channelForm.channel === 'email' ? 'smtp' : 'http_api')
  channelForm.name         = c?.name ?? ''
  channelForm.from_name    = c?.from_name ?? ''
  channelForm.from_address = c?.from_address ?? ''
  channelForm.is_default   = c?.is_default ?? true
  channelForm.cfg          = { host: '', port: '', username: '', password: '', url: '' }
  channelForm.headersJson  = ''
  channelForm.payloadJson  = ''
  channelModal.open = true
}

function buildConfig(): Record<string, unknown> | undefined {
  const cfg: Record<string, unknown> = {}
  if (channelForm.provider === 'smtp') {
    if (channelForm.cfg.host) cfg.host = channelForm.cfg.host
    if (channelForm.cfg.port) cfg.port = Number(channelForm.cfg.port)
    if (channelForm.cfg.username) cfg.username = channelForm.cfg.username
    if (channelForm.cfg.password) cfg.password = channelForm.cfg.password // absent = secret conservé côté serveur
  } else if (channelForm.provider === 'http_api') {
    if (channelForm.cfg.url) cfg.url = channelForm.cfg.url
    try { if (channelForm.headersJson.trim()) cfg.headers = JSON.parse(channelForm.headersJson) } catch { /* validé serveur */ }
    try { if (channelForm.payloadJson.trim()) cfg.payload = JSON.parse(channelForm.payloadJson) } catch { /* validé serveur */ }
  }
  return Object.keys(cfg).length ? cfg : undefined
}

async function saveChannel() {
  channelModal.saving = true
  channelModal.error = ''
  const payload = {
    channel: channelForm.channel, provider: channelForm.provider, name: channelForm.name,
    from_name: channelForm.from_name || undefined, from_address: channelForm.from_address || undefined,
    is_default: channelForm.is_default, config: buildConfig(),
  }
  try {
    if (channelModal.id) await client.patch(`/api/notifications/channels/${channelModal.id}`, payload)
    else await client.post('/api/notifications/channels', payload)
    channelModal.open = false
    loadChannels()
  } catch (e: any) {
    channelModal.error = e?.response?.data?.message ?? t('common.genericError')
  } finally {
    channelModal.saving = false
  }
}

async function removeChannel(c: Channel) {
  try { await client.delete(`/api/notifications/channels/${c.id}`); loadChannels() } catch { /* surfaced ailleurs */ }
}

// ── Template form ────────────────────────────────────────────────────────
const templateModal = reactive({ open: false, saving: false, error: '' })
const templateForm  = reactive({ code: '', channel: 'email', locale: 'fr', subject: '', body: '' })

function openTemplateForm(tp: Template) {
  templateForm.code    = tp.code
  templateForm.channel = tp.channel
  templateForm.locale  = tp.locale
  templateForm.subject = tp.subject ?? ''
  templateForm.body    = tp.body
  templateModal.error  = ''
  templateModal.open   = true
}

async function saveTemplate() {
  templateModal.saving = true
  templateModal.error  = ''
  try {
    await client.put('/api/notifications/templates', {
      code: templateForm.code, channel: templateForm.channel, locale: templateForm.locale,
      subject: templateForm.subject || undefined, body: templateForm.body,
    })
    templateModal.open = false
    loadTemplates()
  } catch (e: any) {
    templateModal.error = e?.response?.data?.message ?? t('common.genericError')
  } finally {
    templateModal.saving = false
  }
}

// ── Test send ────────────────────────────────────────────────────────────
const testModal = reactive({ open: false, sending: false, recipient: '', result: '', ok: false, channelId: '', channelType: 'email' })

function openTest(c: Channel) {
  testModal.channelId = c.id
  testModal.channelType = c.channel
  testModal.recipient = ''
  testModal.result = ''
  testModal.open = true
}

async function sendTest() {
  testModal.sending = true
  testModal.result = ''
  try {
    const res = await client.post(`/api/notifications/channels/${testModal.channelId}/test`, { recipient: testModal.recipient })
    testModal.ok = true
    testModal.result = res.data.message
  } catch (e: any) {
    testModal.ok = false
    testModal.result = e?.response?.data?.message ?? t('common.genericError')
  } finally {
    testModal.sending = false
  }
}

onMounted(loadChannels)
</script>

<style scoped>
.panel-header h3 { margin: 0 0 4px; }
.panel-header p { color: var(--gray-500); font-size: 0.85rem; margin: 0 0 14px; }
.ntf-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--gray-200); margin-bottom: 14px; }
.ntf-tab {
  border: none; background: transparent; padding: 8px 14px; cursor: pointer;
  font-size: 0.85rem; color: var(--gray-500); border-bottom: 2px solid transparent;
}
.ntf-tab.active { color: var(--brand-primary); border-bottom-color: var(--brand-primary); font-weight: 600; }
.ntf-empty { text-align: center; padding: 32px; color: var(--gray-400); font-size: 0.9rem; }
.hint { font-size: 0.75rem; color: var(--gray-400); }
.text-muted { color: var(--gray-400); }
.ntf-credit-intro { color: var(--gray-500); font-size: 0.85rem; margin: 0 0 14px; }
.ntf-credit-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 22px; }
.ntf-credit-card { border: 1px solid var(--gray-200); border-radius: 10px; padding: 14px; text-align: center; }
.ntf-credit-head { display: flex; align-items: center; justify-content: center; gap: 6px; margin-bottom: 8px; }
.ntf-credit-channel { font-weight: 600; font-size: 0.9rem; }
.ntf-credit-balance { font-size: 1.8rem; font-weight: 700; color: var(--brand-primary); line-height: 1.1; }
.ntf-credit-unit { font-size: 0.72rem; color: var(--gray-400); text-transform: uppercase; letter-spacing: 0.04em; }
.ntf-credit-subtitle { margin: 0 0 10px; font-size: 0.92rem; }
.ntf-order-ref { border: 1px dashed var(--gray-300); border-radius: 10px; padding: 16px; text-align: center; }
.ntf-order-ref-code { font-family: monospace; font-size: 1.3rem; font-weight: 700; letter-spacing: 0.06em; }
.ntf-order-ref-amount { color: var(--gray-500); font-size: 0.95rem; margin-top: 4px; }
</style>
