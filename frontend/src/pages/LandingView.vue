<template>
  <div class="landing">

    <!-- ═══════════════════════════════════════════════════════════
         STICKY NAVBAR
    ═══════════════════════════════════════════════════════════ -->
    <header class="nav" :class="{ 'nav--scrolled': scrolled }">
      <div class="container nav-inner">
        <RouterLink to="/" class="nav-logo">
          <FrynovLogo variant="color" />
        </RouterLink>

        <nav class="nav-links hide-mobile" aria-label="Navigation principale">
          <a href="#features">Fonctionnalités</a>
          <a href="#pricing">Tarifs</a>
          <a href="#testimonials">Témoignages</a>
          <a href="#faq">FAQ</a>
        </nav>

        <div class="nav-actions hide-mobile">
          <template v-if="auth.isAuthenticated">
            <RouterLink
              :to="auth.user?.is_super_admin ? '/admin' : '/dashboard'"
              class="cta-primary"
            >Tableau de bord →</RouterLink>
          </template>
          <template v-else>
            <RouterLink to="/login" class="nav-login">Connexion</RouterLink>
            <RouterLink to="/register" class="cta-primary">Démarrer gratuitement</RouterLink>
          </template>
        </div>

        <!-- Mobile hamburger -->
        <button
          class="hamburger"
          :class="{ open: mobileOpen }"
          aria-label="Menu mobile"
          @click="mobileOpen = !mobileOpen"
        >
          <span></span>
          <span></span>
          <span></span>
        </button>
      </div>

      <!-- Mobile drawer -->
      <Transition name="drawer">
        <div v-if="mobileOpen" class="mobile-drawer" @click.self="mobileOpen = false">
          <nav class="drawer-nav">
            <a href="#features" @click="mobileOpen = false">Fonctionnalités</a>
            <a href="#pricing"  @click="mobileOpen = false">Tarifs</a>
            <a href="#testimonials" @click="mobileOpen = false">Témoignages</a>
            <a href="#faq"     @click="mobileOpen = false">FAQ</a>
            <div class="drawer-sep"></div>
            <template v-if="auth.isAuthenticated">
              <RouterLink
                :to="auth.user?.is_super_admin ? '/admin' : '/dashboard'"
                class="cta-primary drawer-cta"
                @click="mobileOpen = false"
              >Tableau de bord →</RouterLink>
            </template>
            <template v-else>
              <RouterLink to="/login"    class="drawer-login"  @click="mobileOpen = false">Connexion</RouterLink>
              <RouterLink to="/register" class="cta-primary drawer-cta" @click="mobileOpen = false">
                Démarrer gratuitement
              </RouterLink>
            </template>
          </nav>
        </div>
      </Transition>
    </header>

    <!-- ═══════════════════════════════════════════════════════════
         HERO
    ═══════════════════════════════════════════════════════════ -->
    <section class="hero">
      <div class="hero-glow hero-glow--tl"></div>
      <div class="hero-glow hero-glow--br"></div>

      <div class="container hero-inner">
        <!-- Left: copy -->
        <div class="hero-content">
          <div class="hero-eyebrow">
            <span class="eyebrow-dot"></span>
            {{ heroEyebrow }}
          </div>

          <h1 class="hero-title">
            Pilotez votre commerce<br>
            <span class="hero-title-gradient">avec Frynov ERP</span>
          </h1>

          <p class="hero-sub">{{ heroSub }}</p>

          <div class="hero-actions">
            <template v-if="auth.isAuthenticated">
              <RouterLink
                :to="auth.user?.is_super_admin ? '/admin' : '/dashboard'"
                class="cta-primary cta-xl"
              >Tableau de bord →</RouterLink>
            </template>
            <template v-else>
              <RouterLink to="/register" class="cta-primary cta-xl">
                Démarrer gratuitement
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                  <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </RouterLink>
              <a href="#pricing" class="cta-ghost cta-xl">Voir les tarifs</a>
            </template>
          </div>

          <!-- Social proof -->
          <div class="social-proof">
            <div class="sp-avatars">
              <div
                v-for="c in trustAvatars"
                :key="c.color"
                class="sp-avatar"
                :style="{ background: c.color }"
              >{{ c.letter }}</div>
            </div>
            <span>{{ socialProofText }}</span>
          </div>
        </div>

        <!-- Right: dashboard mockup -->
        <div class="hero-visual">
          <div class="dashboard-mockup">
            <div class="mockup-bar">
              <div class="mockup-dots">
                <span class="dot dot-red"></span>
                <span class="dot dot-yellow"></span>
                <span class="dot dot-green"></span>
              </div>
              <div class="mockup-title-bar">Frynov ERP — Tableau de bord</div>
              <div class="mockup-pill">● En direct</div>
            </div>

            <div class="mockup-body">
              <!-- KPI cards -->
              <div class="kpi-grid">
                <div v-for="k in kpis" :key="k.label" class="kpi-card">
                  <div class="kpi-icon" :style="{ background: k.bg }">
                    <span v-html="k.icon"></span>
                  </div>
                  <div class="kpi-info">
                    <div class="kpi-val" :style="{ color: k.color }">{{ k.value }}</div>
                    <div class="kpi-lbl">{{ k.label }}</div>
                  </div>
                  <div class="kpi-trend" :class="k.up ? 'up' : 'down'">
                    {{ k.up ? '▲' : '▼' }} {{ k.delta }}
                  </div>
                </div>
              </div>

              <!-- Mini bar chart -->
              <div class="chart-card">
                <div class="chart-header">
                  <span class="chart-title">{{ chartLabel }}</span>
                  <span class="chart-badge">+34%</span>
                </div>
                <div class="chart-bars">
                  <div
                    v-for="(h, idx) in bars"
                    :key="idx"
                    class="chart-bar"
                    :style="{ height: h + '%' }"
                    :class="{ active: idx === bars.length - 1 }"
                  ></div>
                </div>
              </div>

              <!-- Orders table -->
              <div class="orders-card">
                <div class="orders-head">
                  <span>Commandes récentes</span>
                  <span class="orders-link">Voir tout →</span>
                </div>
                <div v-for="o in mockOrders" :key="o.name" class="order-row">
                  <div class="order-avatar" :style="{ background: o.color }">{{ o.initials }}</div>
                  <div class="order-name">{{ o.name }}</div>
                  <div class="order-amount">{{ o.amount }}</div>
                  <div class="order-status" :class="o.statusClass">{{ o.status }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Floating payment notification — geo-aware -->
          <div class="wave-card">
            <div class="wave-card-inner">
              <div class="wave-header">
                <span class="wave-label">{{ isAfrica ? 'Wave' : 'Paiement' }}</span>
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                  <path d="M10 2C5.58 2 2 5.58 2 10s3.58 8 8 8 8-3.58 8-8-3.58-8-8-8z" fill="rgba(255,255,255,0.3)"/>
                  <path d="M7 10l2 2 4-4" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </div>
              <div class="wave-amount">{{ isAfrica ? '48 500' : '1 240' }} <span>{{ isAfrica ? 'XOF' : '€' }}</span></div>
              <div class="wave-sub">{{ floatNotif.title }}</div>
              <div class="wave-dots">
                <span></span><span></span><span></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         LOGOS STRIP
    ═══════════════════════════════════════════════════════════ -->
    <section class="logos-strip">
      <div class="container">
        <p class="logos-label">Utilisé par des leaders du commerce en Afrique</p>
        <div class="logos-row">
          <div v-for="name in logoNames" :key="name" class="logo-chip">
            <span class="logo-dot"></span>{{ name }}
          </div>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         PROBLEM → SOLUTION
    ═══════════════════════════════════════════════════════════ -->
    <section class="problem-solution">
      <div class="container ps-inner">
        <!-- Before column -->
        <div class="ps-col ps-before">
          <div class="ps-badge ps-badge--red">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <circle cx="7" cy="7" r="6" stroke="#ef4444" stroke-width="1.5"/>
              <path d="M7 4v4M7 9.5v.5" stroke="#ef4444" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            Avant Frynov
          </div>
          <h2 class="ps-heading">Vous gérez votre commerce<br><span class="ps-red">avec des rustines</span></h2>
          <ul class="ps-list ps-list--bad">
            <li v-for="p in pains" :key="p">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M4 4l8 8M12 4l-8 8" stroke="#ef4444" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
              {{ p }}
            </li>
          </ul>
        </div>

        <!-- Arrow separator -->
        <div class="ps-arrow" aria-hidden="true">
          <div class="arrow-circle">
            <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
              <path d="M8 14h12M16 10l4 4-4 4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
        </div>

        <!-- After column -->
        <div class="ps-col ps-after">
          <div class="ps-badge ps-badge--green">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <circle cx="7" cy="7" r="6" stroke="#10b981" stroke-width="1.5"/>
              <path d="M4.5 7l2 2 3-3" stroke="#10b981" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Avec Frynov
          </div>
          <h2 class="ps-heading">Une plateforme unifiée<br><span class="ps-green">{{ psHeadingHighlight }}</span></h2>
          <ul class="ps-list ps-list--good">
            <li v-for="g in gains" :key="g">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M3 8l4 4 6-6" stroke="#10b981" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              {{ g }}
            </li>
          </ul>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         FEATURES GRID
    ═══════════════════════════════════════════════════════════ -->
    <section id="features" class="features">
      <div class="container">
        <div class="section-header">
          <div class="section-badge">Fonctionnalités</div>
          <h2>Tout ce qu'il faut pour <span class="accent">piloter votre activité</span></h2>
          <p>Six modules intégrés, zéro configuration complexe. Chacun fonctionne le jour J.</p>
        </div>

        <div class="features-grid">
          <div v-for="feat in features" :key="feat.title" class="feat-card">
            <div class="feat-icon" :style="{ background: feat.bg }">
              <span v-html="feat.icon"></span>
            </div>
            <h3>{{ feat.title }}</h3>
            <p>{{ feat.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         PRICING
    ═══════════════════════════════════════════════════════════ -->
    <section id="pricing" class="pricing">
      <div class="container">
        <div class="section-header">
          <div class="section-badge">Tarifs localisés</div>
          <h2>Transparent, <span class="accent">{{ pricingHeading }}</span></h2>
          <p>{{ pricingSub }}</p>
          <label class="market-selector" for="market-select">
            <span>Tarifs pour</span>
            <select id="market-select" v-model="selectedMarket" aria-label="Choisir le pays ou la devise">
              <option v-for="m in selectableMarkets" :key="m.code" :value="m.code">
                {{ m.label }}
              </option>
            </select>
          </label>

          <div class="billing-toggle" role="group" aria-label="Périodicité de facturation">
            <button
              type="button"
              class="billing-toggle__btn"
              :class="{ active: billingInterval === 'monthly' }"
              :aria-pressed="billingInterval === 'monthly'"
              @click="billingInterval = 'monthly'"
            >Mensuel</button>
            <button
              type="button"
              class="billing-toggle__btn"
              :class="{ active: billingInterval === 'yearly' }"
              :aria-pressed="billingInterval === 'yearly'"
              @click="billingInterval = 'yearly'"
            >
              Annuel <span class="billing-toggle__pill">2 mois offerts</span>
            </button>
          </div>
        </div>

        <div class="plans-grid">
          <div
            v-for="plan in plans"
            :key="plan.name"
            class="plan-card"
            :class="{ featured: plan.featured }"
          >
            <div v-if="plan.featured" class="plan-badge">Plus populaire</div>
            <div class="plan-name">{{ plan.name }}</div>
            <div class="plan-price">
              <span class="plan-amount">{{ plan.price }}</span>
              <span v-if="plan.period" class="plan-period">{{ plan.period }}</span>
            </div>
            <div v-if="plan.monthlyEquivalent" class="plan-annual-eq">{{ plan.monthlyEquivalent }}</div>
            <div v-if="plan.savingsPct > 0" class="plan-savings">Économisez {{ plan.savingsPct }}%</div>
            <p class="plan-tagline">{{ plan.tagline }}</p>
            <ul class="plan-features">
              <li v-for="f in plan.features" :key="f">
                <svg width="15" height="15" viewBox="0 0 15 15" fill="none" aria-hidden="true">
                  <path d="M2.5 7.5l3.5 3.5 6.5-6.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                {{ f }}
              </li>
            </ul>
            <template v-if="auth.isAuthenticated">
              <RouterLink
                :to="auth.user?.is_super_admin ? '/admin' : '/dashboard'"
                class="plan-cta"
                :class="{ 'plan-cta--primary': plan.featured }"
              >Tableau de bord →</RouterLink>
            </template>
            <template v-else>
              <RouterLink
                to="/register"
                class="plan-cta"
                :class="{ 'plan-cta--primary': plan.featured }"
              >{{ plan.cta }}</RouterLink>
            </template>
          </div>
        </div>

        <p class="pricing-note">
          {{ market.pricingNote }} Les modules métier sont accessibles sur tous les plans ; les limites portent sur les utilisateurs et volumes critiques.
        </p>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         TESTIMONIALS
    ═══════════════════════════════════════════════════════════ -->
    <section id="testimonials" class="testimonials">
      <div class="container">
        <div class="section-header">
          <div class="section-badge">Témoignages</div>
          <h2>Ils ont transformé <span class="accent">leur gestion</span></h2>
        </div>

        <div class="testi-grid">
          <div v-for="t in testimonials" :key="t.name" class="testi-card">
            <div class="testi-stars">★★★★★</div>
            <blockquote class="testi-quote">"{{ t.quote }}"</blockquote>
            <div class="testi-author">
              <div class="testi-avatar" :style="{ background: t.color }">{{ t.initials }}</div>
              <div class="testi-info">
                <div class="testi-name">{{ t.name }}</div>
                <div class="testi-role">{{ t.role }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         STATS BAND
    ═══════════════════════════════════════════════════════════ -->
    <section class="stats-band">
      <div class="container stats-grid">
        <div v-for="s in stats" :key="s.label" class="stat-item">
          <div class="stat-val">{{ s.val }}</div>
          <div class="stat-lbl">{{ s.label }}</div>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         FINAL CTA
    ═══════════════════════════════════════════════════════════ -->
    <section class="final-cta">
      <div class="fc-glow"></div>
      <div class="container fc-inner">
        <div class="fc-kicker">Sans carte bancaire · Sans engagement · En XOF</div>
        <h2 class="fc-title">Prêt à piloter votre commerce<br>comme les grandes entreprises&nbsp;?</h2>
        <p class="fc-sub">
          Rejoignez plus de 1 400 PME africaines qui utilisent Frynov ERP
          pour gagner du temps et augmenter leurs marges.
        </p>
        <div class="fc-actions">
          <template v-if="auth.isAuthenticated">
            <RouterLink
              :to="auth.user?.is_super_admin ? '/admin' : '/dashboard'"
              class="cta-primary cta-xl"
            >Tableau de bord →</RouterLink>
          </template>
          <template v-else>
            <RouterLink to="/register" class="cta-primary cta-xl">
              Créer mon espace gratuitement
            </RouterLink>
            <RouterLink to="/login" class="fc-login">Déjà inscrit ? Connexion →</RouterLink>
          </template>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         FAQ
    ═══════════════════════════════════════════════════════════ -->
    <section id="faq" class="faq">
      <div class="container faq-wrap">
        <div class="section-header">
          <div class="section-badge">FAQ</div>
          <h2>Questions fréquentes</h2>
          <p>Tout ce que vous voulez savoir avant de commencer.</p>
        </div>

        <div class="faq-list">
          <div
            v-for="item in faqs"
            :key="item.q"
            class="faq-item"
            :class="{ open: openFaq === item.q }"
            @click="openFaq = openFaq === item.q ? null : item.q"
            role="button"
            :aria-expanded="openFaq === item.q"
          >
            <div class="faq-q">
              <span>{{ item.q }}</span>
              <svg
                class="faq-chevron"
                width="18" height="18"
                viewBox="0 0 18 18"
                fill="none"
                aria-hidden="true"
              >
                <path d="M5 7l4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <div class="faq-a">{{ item.a }}</div>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════
         FOOTER
    ═══════════════════════════════════════════════════════════ -->
    <footer class="footer">
      <div class="container footer-main">
        <div class="footer-brand">
          <FrynovLogo variant="light" />
          <p v-if="isAfrica">ERP moderne conçu pour les PME d'Afrique de l'Ouest. En français, en XOF, sur mobile.</p>
          <p v-else>ERP nouvelle génération pour PME ambitieuses. Multi-devises, multi-utilisateurs, partout dans le monde.</p>
          <div class="footer-mm-logos" v-if="isAfrica">
            <span class="mm-chip wave">Wave</span>
            <span class="mm-chip orange">Orange Money</span>
            <span class="mm-chip mtn">MTN MoMo</span>
          </div>
        </div>

        <div class="footer-cols">
          <div class="footer-col">
            <div class="footer-col-title">Produit</div>
            <a href="#features">Fonctionnalités</a>
            <a href="#pricing">Tarifs</a>
            <RouterLink to="/register">Démarrer</RouterLink>
          </div>
          <div class="footer-col">
            <div class="footer-col-title">{{ isAfrica ? 'Marché' : 'Ressources' }}</div>
            <template v-if="isAfrica">
              <a href="#">Côte d'Ivoire</a>
              <a href="#">Sénégal</a>
              <a href="#">Mali</a>
              <a href="#">Cameroun</a>
            </template>
            <template v-else>
              <a href="#">Documentation</a>
              <a href="#">API</a>
              <a href="#">Blog</a>
              <a href="#">Partenaires</a>
            </template>
          </div>
          <div class="footer-col">
            <div class="footer-col-title">Support</div>
            <a href="#faq">FAQ</a>
            <a href="#">Documentation</a>
            <a href="#">Contact</a>
          </div>
        </div>
      </div>

      <div class="footer-bottom">
        <div class="container footer-bottom-inner">
          <span>© 2026 Frynov ERP — Tous droits réservés</span>
          <div class="footer-legal">
            <a href="#">Confidentialité</a>
            <a href="#">Conditions d'utilisation</a>
          </div>
        </div>
      </div>
    </footer>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useGeoContent } from '@/composables/useGeoContent'
import { fetchPublicPricing, type PublicPlan } from '@/services/publicPricingService'
import FrynovLogo from '@/shared/components/FrynovLogo.vue'

const auth       = useAuthStore()
const scrolled   = ref(false)
const mobileOpen = ref(false)
const openFaq    = ref<string | null>(null)

// ── Geo-personalization ────────────────────────────────────────────────────
// Default: global (neutral). Updates reactively once IP detection resolves.
const { region, market, isAfrica, selectableMarkets, selectedMarket } = useGeoContent()

function onScroll() { scrolled.value = window.scrollY > 40 }
onMounted(()   => window.addEventListener('scroll', onScroll, { passive: true }))
onUnmounted(() => window.removeEventListener('scroll', onScroll))

/* ── Localized pricing from the backend (single source of truth) ───────────────
   The landing must NOT hardcode contractual prices. We fetch them from the public
   pricing API for the resolved/selected market; `pricingAmounts` below is kept ONLY
   as an offline fallback if the API is unreachable. Re-fetches when the visitor
   changes market in the selector. */
const apiPlans = ref<PublicPlan[] | null>(null)

/** Périodicité affichée (mensuel par défaut). L'annuel = ~10 mois (≈ 2 mois offerts). RC-1D. */
const billingInterval = ref<PricingInterval>('monthly')

async function loadPricing(marketCode: string, interval: PricingInterval): Promise<void> {
  // The API knows real markets, not the legacy 'africa' alias → map it to UEMOA/XOF.
  const code = marketCode === 'africa' ? 'waemu' : marketCode
  try {
    const res = await fetchPublicPricing({ market: code, interval })
    apiPlans.value = res.data
  } catch {
    apiPlans.value = null // graceful: fall back to local currency-aware amounts
  }
}

watch(
  [() => market.value.code, billingInterval],
  ([code, interval]) => { void loadPricing(code, interval) },
  { immediate: true },
)

const apiPlanByCode = computed<Record<string, PublicPlan>>(() =>
  Object.fromEntries((apiPlans.value ?? []).map(p => [p.code, p])),
)

/** Format an integer-centimes amount as a plain localized number (currency shown separately). */
function formatPlanAmount(minor: number, currency: string): string {
  const noDecimals = currency === 'XOF' || currency === 'XAF'
  return new Intl.NumberFormat('fr-FR', {
    minimumFractionDigits: noDecimals ? 0 : 2,
    maximumFractionDigits: noDecimals ? 0 : 2,
  }).format(minor / 100)
}

/** API price for a plan code, falling back to the local hardcoded amount if the API is down. */
function planAmount(code: string, fallback: string): string {
  const plan = apiPlanByCode.value[code]
  if (!plan) return fallback
  if (!plan.price || plan.price.base_amount_minor === 0) return 'Gratuit'
  return formatPlanAmount(plan.price.base_amount_minor, plan.price.currency)
}

function planPeriod(code: string, fallback: string): string {
  const plan = apiPlanByCode.value[code]
  if (!plan) return fallback
  if (!plan.price || plan.price.base_amount_minor === 0) return ''
  const suffix = billingInterval.value === 'yearly' ? '/ an' : '/ mois'
  return `${plan.price.currency} ${suffix}`
}

/** Sur l'annuel : « ≈ X CURRENCY / mois » (équivalent mensuel). Vide en mensuel ou plan gratuit. */
function planMonthlyEquivalent(code: string): string {
  const plan = apiPlanByCode.value[code]
  if (billingInterval.value !== 'yearly' || !plan?.price?.base_amount_minor) return ''
  const eq = plan.price.monthly_equivalent_minor
  if (!eq) return ''
  return `≈ ${formatPlanAmount(eq, plan.price.currency)} ${plan.price.currency} / mois`
}

/** Pourcentage d'économie de l'offre annuelle pour ce plan (0 si non applicable). */
function planSavingsPct(code: string): number {
  const plan = apiPlanByCode.value[code]
  if (billingInterval.value !== 'yearly' || !plan?.price?.base_amount_minor) return 0
  return plan.price.savings_pct ?? 0
}

/* ── Trust avatars ────────────────────────────────────────── */
const trustAvatars = [
  { color: '#10b981', letter: 'A' },
  { color: '#3b82f6', letter: 'M' },
  { color: '#8b5cf6', letter: 'F' },
  { color: '#f59e0b', letter: 'K' },
  { color: '#ef4444', letter: 'S' },
]

/* ══════════════════════════════════════════════════════════
   GEO-PERSONALIZED DATA — global (default) vs africa
══════════════════════════════════════════════════════════ */

// ── KPI cards ──────────────────────────────────────────────────────────────
const kpisGlobal = [
  { label: "Chiffre d'affaires", value: '12 400 €',   color: '#10b981', bg: 'rgba(16,185,129,0.12)',  delta: '12%', up: true,  icon: '<svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 11l3-3 2.5 2 4.5-5" stroke="#10b981" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>' },
  { label: 'Commandes',          value: '248',         color: '#3b82f6', bg: 'rgba(59,130,246,0.12)',  delta: '8%',  up: true,  icon: '<svg width="14" height="14" viewBox="0 0 14 14" fill="none"><rect x="2" y="3" width="10" height="8" rx="1" stroke="#3b82f6" stroke-width="1.4"/><path d="M5 6h4M5 8.5h2.5" stroke="#3b82f6" stroke-width="1.2" stroke-linecap="round"/></svg>' },
  { label: 'En stock',           value: '4 892',       color: '#8b5cf6', bg: 'rgba(139,92,246,0.12)', delta: '3%',  up: false, icon: '<svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 5l5-3 5 3v6l-5 3-5-3V5z" stroke="#8b5cf6" stroke-width="1.4" stroke-linejoin="round"/></svg>' },
  { label: 'Clients actifs',     value: '367',         color: '#f59e0b', bg: 'rgba(245,158,11,0.12)', delta: '5%',  up: true,  icon: '<svg width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="5.5" cy="4.5" r="2" stroke="#f59e0b" stroke-width="1.4"/><path d="M1 12c0-2.5 2-4.5 4.5-4.5S10 9.5 10 12" stroke="#f59e0b" stroke-width="1.4" stroke-linecap="round"/></svg>' },
]

const kpisAfrica = [
  { label: "Chiffre d'affaires", value: '2.84M XOF',  color: '#10b981', bg: 'rgba(16,185,129,0.12)',  delta: '12%', up: true,  icon: kpisGlobal[0].icon },
  { label: 'Commandes',          value: '248',         color: '#3b82f6', bg: 'rgba(59,130,246,0.12)',  delta: '8%',  up: true,  icon: kpisGlobal[1].icon },
  { label: 'En stock',           value: '4 892',       color: '#8b5cf6', bg: 'rgba(139,92,246,0.12)', delta: '3%',  up: false, icon: kpisGlobal[2].icon },
  { label: 'Clients actifs',     value: '367',         color: '#f59e0b', bg: 'rgba(245,158,11,0.12)', delta: '5%',  up: true,  icon: kpisGlobal[3].icon },
]

const demoAmounts: Record<string, { revenue: string; orders: string[]; float: string }> = {
  XOF: { revenue: '2.84M XOF', orders: ['38 000 XOF', '74 500 XOF', '21 000 XOF'], float: '85 000 XOF' },
  XAF: { revenue: '2.84M XAF', orders: ['38 000 XAF', '74 500 XAF', '21 000 XAF'], float: '85 000 XAF' },
  NGN: { revenue: '7.1M NGN', orders: ['95 000 NGN', '185 000 NGN', '52 000 NGN'], float: '210 000 NGN' },
  GHS: { revenue: '48 000 GHS', orders: ['640 GHS', '1 250 GHS', '350 GHS'], float: '1 420 GHS' },
  KES: { revenue: '1.5M KES', orders: ['19 000 KES', '37 000 KES', '10 500 KES'], float: '42 000 KES' },
  ZAR: { revenue: '215 000 ZAR', orders: ['2 800 ZAR', '5 600 ZAR', '1 550 ZAR'], float: '6 400 ZAR' },
  EUR: { revenue: '12 400 €', orders: ['1 240 €', '3 850 €', '890 €'], float: '1 240 €' },
  CAD: { revenue: '18 600 CAD', orders: ['1 860 CAD', '5 775 CAD', '1 335 CAD'], float: '1 860 CAD' },
  USD: { revenue: '13 500 USD', orders: ['1 350 USD', '4 190 USD', '970 USD'], float: '1 350 USD' },
}

const demoMoney = computed(() => demoAmounts[market.value.currency] ?? demoAmounts.USD)
const kpis = computed(() => (isAfrica.value ? kpisAfrica : kpisGlobal).map((k, index) => (
  index === 0 ? { ...k, value: demoMoney.value.revenue } : k
)))

/* ── Bar chart data ───────────────────────────────────────── */
const bars = [35, 48, 42, 62, 55, 70, 58, 80, 72, 85, 68, 95]

/* ── Mock orders ──────────────────────────────────────────── */
const mockOrdersGlobal = [
  { name: 'Sophie Martin',    initials: 'SM', color: '#10b981', amount: '1 240 €',  status: 'Livré',    statusClass: 'status-delivered' },
  { name: 'Carlos Rodrigues', initials: 'CR', color: '#3b82f6', amount: '3 850 €',  status: 'En cours', statusClass: 'status-pending'   },
  { name: 'Lena Fischer',     initials: 'LF', color: '#f59e0b', amount: '890 €',    status: 'Confirmé', statusClass: 'status-confirmed' },
]

const mockOrdersAfrica = [
  { name: 'Aminata Diallo',   initials: 'AD', color: '#10b981', amount: '38 000 XOF', status: 'Livré',    statusClass: 'status-delivered' },
  { name: 'Moussa Coulibaly', initials: 'MC', color: '#3b82f6', amount: '74 500 XOF', status: 'En cours', statusClass: 'status-pending'   },
  { name: 'Fatou Sow',        initials: 'FS', color: '#f59e0b', amount: '21 000 XOF', status: 'Confirmé', statusClass: 'status-confirmed' },
]

const mockOrders = computed(() => (isAfrica.value ? mockOrdersAfrica : mockOrdersGlobal).map((order, index) => ({
  ...order,
  amount: demoMoney.value.orders[index] ?? order.amount,
})))

/* ── Logo strip ───────────────────────────────────────────── */
const logoNamesGlobal = ['DistribCo', 'TradePro', 'BizHub', 'RetailFlow', 'SupplySuite', 'NovaBiz']
const logoNamesAfrica = ['Distrib Pro CI', 'BizCore SN', 'MarketHub CM', 'TradeFlow GH', 'RetailMax ML', 'NovaCom TG']

const logoNames = computed(() => isAfrica.value ? logoNamesAfrica : logoNamesGlobal)

/* ── Social proof text ────────────────────────────────────── */
const socialProofText = computed(() => isAfrica.value
  ? 'Déjà +1 400 PME en Côte d\'Ivoire, Sénégal, Mali...'
  : 'Rejoint par +1 400 entreprises en Europe, Amérique du Nord & Afrique'
)

/* ── Hero floating notification ───────────────────────────── */
const floatNotif = computed(() => ({
  title: isAfrica.value ? 'Paiement local reçu' : 'Paiement reçu',
  sub: `CMD-00247 · ${demoMoney.value.float}`,
}))

/* ── Hero eyebrow text ────────────────────────────────────── */
const heroEyebrow = computed(() => isAfrica.value
  ? 'Conçu pour l\'Afrique de l\'Ouest'
  : 'Europe · Amérique du Nord · Afrique'
)

/* ── Hero subtitle ────────────────────────────────────────── */
const heroSub = computed(() => isAfrica.value
  ? 'Stock en temps réel, commandes, Mobile Money intégré, CRM et rapports — tout en XOF, en français, depuis votre téléphone ou votre bureau.'
  : 'Frynov centralise stock, commandes, clients et finances en une plateforme unifiée. Disponible en Europe, en Amérique du Nord et en Afrique — dans votre langue, dans votre devise.'
)

/* ── Chart label ──────────────────────────────────────────── */
const chartLabel = computed(() => `Ventes 12 mois (${market.value.currency})`)

/* ── Problem → Solution heading ───────────────────────────── */
const psHeadingHighlight = computed(() => isAfrica.value
  ? 'pensée pour l\'Afrique'
  : 'pour chaque marché'
)

/* ── Problem → Solution ───────────────────────────────────── */
const painsGlobal = [
  'Stock géré sur Excel, données éparpillées',
  'Commandes par email ou messagerie, rien de tracé',
  'Marge réelle impossible à calculer',
  'Impossible de déléguer sans perdre le contrôle',
  'Zéro reporting, zéro visibilité sur la croissance',
]

const painsAfrica = [
  'Stock géré sur Excel qui plante chaque semaine',
  'Commandes reçues sur WhatsApp, pertes inévitables',
  'Marge réelle impossible à calculer',
  'Impossible de déléguer sans tout expliquer',
  'Zéro reporting, zéro visibilité sur la croissance',
]

const pains = computed(() => isAfrica.value ? painsAfrica : painsGlobal)

const gainsGlobal = [
  'Stock mis à jour en temps réel à chaque vente',
  'Tous vos modes de paiement centralisés',
  'CMUP et marges calculés automatiquement',
  'Rôles et permissions granulaires par utilisateur',
  'Rapports PDF et Excel générés en un clic',
]

const gainsAfrica = [
  'Stock mis à jour en temps réel à chaque vente',
  'Mobile Money (Wave, Orange) intégré nativement',
  'CMUP et marges calculés automatiquement',
  'Rôles et permissions granulaires par employé',
  'Rapports PDF et Excel générés en un clic',
]

const gains = computed(() => isAfrica.value ? gainsAfrica : gainsGlobal)

/* ── Features grid ────────────────────────────────────────── */
// Shared SVG icons
const _icoPay = '<svg width="22" height="22" viewBox="0 0 22 22" fill="none"><rect x="3" y="5" width="16" height="12" rx="2" stroke="#f59e0b" stroke-width="1.6"/><path d="M3 9h16" stroke="#f59e0b" stroke-width="1.5"/><circle cx="7" cy="13" r="1" fill="#f59e0b"/></svg>'

// Card 3 is the only geo-variant (payments)
const paymentFeatureGlobal = {
  title: 'Paiements intégrés',
  desc: 'Carte, virement, lien de paiement et méthodes locales. Réconciliation automatique en temps réel.',
  bg: 'rgba(245,158,11,0.10)',
  icon: _icoPay,
}

const paymentFeatureAfrica = {
  title: 'Mobile Money XOF',
  desc: 'Encaissez par Wave, Orange Money ou MTN MoMo. Réconciliation automatique en FCFA.',
  bg: 'rgba(245,158,11,0.10)',
  icon: _icoPay,
}

const featuresBase = [
  {
    title: 'Inventaire temps réel',
    desc: 'Mouvements de stock automatiques à chaque vente ou réception. Alertes de seuil configurables.',
    bg: 'rgba(16,185,129,0.10)',
    icon: '<svg width="22" height="22" viewBox="0 0 22 22" fill="none"><path d="M3 7l8-4 8 4v8l-8 4-8-4V7z" stroke="#10b981" stroke-width="1.6" stroke-linejoin="round"/><path d="M11 3v18M3 7l8 4 8-4" stroke="#10b981" stroke-width="1.6"/></svg>',
  },
  {
    title: 'Gestion des commandes',
    desc: 'Du devis au bon de livraison : créez, confirmez, expédiez et archivez en quelques clics.',
    bg: 'rgba(59,130,246,0.10)',
    icon: '<svg width="22" height="22" viewBox="0 0 22 22" fill="none"><rect x="3" y="3" width="16" height="16" rx="2" stroke="#3b82f6" stroke-width="1.6"/><path d="M7 9h8M7 13h5" stroke="#3b82f6" stroke-width="1.5" stroke-linecap="round"/></svg>',
  },
  null, // ← replaced by geo-variant (payments card)
  {
    title: 'CRM & Clients',
    desc: "Fiches clients complètes, historique d'achats, segmentation et relances automatiques.",
    bg: 'rgba(139,92,246,0.10)',
    icon: '<svg width="22" height="22" viewBox="0 0 22 22" fill="none"><circle cx="9" cy="7" r="3.5" stroke="#8b5cf6" stroke-width="1.6"/><path d="M2 20c0-3.9 3.1-7 7-7s7 3.1 7 7" stroke="#8b5cf6" stroke-width="1.6" stroke-linecap="round"/><path d="M16 9a3 3 0 010-6M20 20c0-3.3-1.9-6-5-6.6" stroke="#8b5cf6" stroke-width="1.4" stroke-linecap="round"/></svg>',
  },
  {
    title: 'Analytics & Rapports',
    desc: 'Tableau de bord avec KPIs en direct. Export PDF/Excel des rapports en un clic.',
    bg: 'rgba(239,68,68,0.10)',
    icon: '<svg width="22" height="22" viewBox="0 0 22 22" fill="none"><rect x="3" y="12" width="4" height="8" rx="1" stroke="#ef4444" stroke-width="1.6"/><rect x="9" y="7" width="4" height="13" rx="1" stroke="#ef4444" stroke-width="1.6"/><rect x="15" y="3" width="4" height="17" rx="1" stroke="#ef4444" stroke-width="1.6"/></svg>',
  },
  {
    title: 'Multi-utilisateurs',
    desc: 'Invitez votre équipe, attribuez des rôles (Vendeur, Gestionnaire, Admin) et contrôlez les accès.',
    bg: 'rgba(16,185,129,0.07)',
    icon: '<svg width="22" height="22" viewBox="0 0 22 22" fill="none"><circle cx="8" cy="7" r="3" stroke="#10b981" stroke-width="1.6"/><path d="M2 19c0-3.3 2.7-6 6-6s6 2.7 6 6" stroke="#10b981" stroke-width="1.6" stroke-linecap="round"/><path d="M15 11l2 2 3.5-3.5" stroke="#10b981" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  },
]

const features = computed(() => featuresBase.map(f => f ?? (isAfrica.value ? paymentFeatureAfrica : paymentFeatureGlobal)))

/* ── Pricing plans ────────────────────────────────────────── */
interface LandingPlan {
  name: string
  price: string
  period: string
  tagline: string
  featured: boolean
  cta: string
  features: string[]
  /** Annuel (RC-1D) : équivalent mensuel « ≈ X / mois » + % d'économie. Vides en mensuel. */
  monthlyEquivalent: string
  savingsPct: number
}

const planLimits = {
  discovery: ['1 utilisateur inclus', '100 produits · 50 commandes/mois'],
  essential: ['2 utilisateurs inclus', '500 produits · 300 commandes/mois'],
  growth: ['5 utilisateurs inclus', '5 000 produits · 2 000 commandes/mois'],
  business: ['10 utilisateurs inclus', 'multi-sites + API'],
}

const pricingAmounts: Record<string, { discovery: string; essential: string; growth: string; business: string; period: string }> = {
  waemu_xof:        { discovery: 'Gratuit', essential: '9 900',  growth: '24 900', business: '59 900', period: 'XOF / mois' },
  cemac_xaf:        { discovery: 'Gratuit', essential: '9 900',  growth: '24 900', business: '59 900', period: 'XAF / mois' },
  nigeria_ngn:      { discovery: 'Gratuit', essential: '15 000', growth: '39 000', business: '99 000', period: 'NGN / mois' },
  ghana_ghs:        { discovery: 'Gratuit', essential: '150',    growth: '390',    business: '990',    period: 'GHS / mois' },
  kenya_kes:        { discovery: 'Gratuit', essential: '2 500',  growth: '6 500',  business: '16 900', period: 'KES / mois' },
  south_africa_zar: { discovery: 'Gratuit', essential: '349',    growth: '899',    business: '2 399',  period: 'ZAR / mois' },
  europe_eur:       { discovery: 'Gratuit', essential: '19',     growth: '49',     business: '129',    period: '€ / mois' },
  canada_cad:       { discovery: 'Gratuit', essential: '25',     growth: '65',     business: '169',    period: 'CAD / mois' },
  usa_usd:          { discovery: 'Gratuit', essential: '19',     growth: '49',     business: '129',    period: 'USD / mois' },
  global_usd:       { discovery: 'Gratuit', essential: '19',     growth: '49',     business: '129',    period: 'USD / mois' },
}

const pricing = computed(() => pricingAmounts[market.value.priceBook] ?? pricingAmounts.global_usd)
const pricingHeading = computed(() => `${market.value.currency} · ${market.value.label}`)
const pricingSub = computed(() => `Tous les modules métier sont inclus. Vous payez selon votre équipe, vos volumes et votre zone géographique.`)

const plans = computed<LandingPlan[]>(() => [
  {
    name: 'Starter / Découverte',
    price: planAmount('starter', pricing.value.discovery),
    period: planPeriod('starter', ''),
    monthlyEquivalent: planMonthlyEquivalent('starter'),
    savingsPct: planSavingsPct('starter'),
    tagline: 'Pour tester Frynov sans engagement.',
    featured: false,
    cta: 'Commencer gratuitement',
    features: [
      planLimits.discovery[0],
      planLimits.discovery[1],
      'Tous les modules métier visibles',
      'Catalogue, stock, commandes, clients',
      'Support communauté',
    ],
  },
  {
    name: 'Essentiel',
    price: planAmount('essential', pricing.value.essential),
    period: planPeriod('essential', pricing.value.period),
    monthlyEquivalent: planMonthlyEquivalent('essential'),
    savingsPct: planSavingsPct('essential'),
    tagline: 'Pour gérer une boutique active au quotidien.',
    featured: false,
    cta: 'Choisir Essentiel',
    features: [
      planLimits.essential[0],
      planLimits.essential[1],
      'Paiements, livraisons, fournisseurs',
      'Import / Export simple',
      `Paiement local : ${market.value.paymentCopy}`,
      'Support email',
    ],
  },
  {
    name: 'Pro / Croissance',
    price: planAmount('pro', pricing.value.growth),
    period: planPeriod('pro', pricing.value.period),
    monthlyEquivalent: planMonthlyEquivalent('pro'),
    savingsPct: planSavingsPct('pro'),
    tagline: 'Pour les équipes qui vendent plus et veulent automatiser.',
    featured: true,
    cta: 'Essayer Croissance 30 jours',
    features: [
      planLimits.growth[0],
      planLimits.growth[1],
      'Rapports avancés & exports',
      'Marketplace et alertes avancées',
      '3 boutiques / 3 entrepôts',
      'Support prioritaire',
    ],
  },
  {
    name: 'Business / Enterprise',
    price: planAmount('enterprise', pricing.value.business),
    period: planPeriod('enterprise', pricing.value.period),
    monthlyEquivalent: planMonthlyEquivalent('enterprise'),
    savingsPct: planSavingsPct('enterprise'),
    tagline: 'Pour les groupes, grossistes et réseaux multi-sites.',
    featured: false,
    cta: 'Contacter l’équipe',
    features: [
      planLimits.business[0],
      planLimits.business[1],
      'Volumes élevés ou sur devis',
      'API & intégrations custom',
      'SLA, onboarding et formation',
      'Account manager dédié',
    ],
  },
])

/* ── Testimonials ─────────────────────────────────────────── */
const testimonialsGlobal = [
  {
    name: 'Sophie Martin',
    role: 'Directrice, DistribCo — Paris',
    initials: 'SM',
    color: '#10b981',
    quote: "Before Frynov, I spent every Monday reconciling three Excel files. Now my stock updates automatically with every sale and I can see the real margin instantly. It saves me 6 hours a week.",
  },
  {
    name: 'Carlos Rodrigues',
    role: 'Founder, TradePro — Madrid',
    initials: 'CR',
    color: '#3b82f6',
    quote: "I hired two new sales reps and gave them role-based access in minutes. The real-time reports show me who sells what, and the multi-warehouse feature handles our three locations perfectly.",
  },
  {
    name: 'Lena Fischer',
    role: 'Manager, BizHub — Munich',
    initials: 'LF',
    color: '#f59e0b',
    quote: "Frynov replaced four separate tools we were using. Our accountant receives monthly PDF reports automatically. No more double entry, no more errors. Our team was up and running in an afternoon.",
  },
]

const testimonialsAfrica = [
  {
    name: 'Awa Traoré',
    role: 'Directrice, Distrib Pro CI — Abidjan',
    initials: 'AT',
    color: '#10b981',
    quote: "Avant Frynov, je passais chaque lundi à réconcilier 3 fichiers Excel. Maintenant mon stock s'ajuste automatiquement et je reçois les paiements Wave directement dans Frynov. C'est un gain de 6 heures par semaine.",
  },
  {
    name: 'Ibrahima Ndiaye',
    role: 'Fondateur, BizCore SN — Dakar',
    initials: 'IN',
    color: '#3b82f6',
    quote: "J'ai pu recruter deux vendeurs en leur donnant accès uniquement à ce dont ils ont besoin. La gestion des rôles est simple et les rapports me montrent en temps réel qui vend quoi. Frynov a transformé notre organisation.",
  },
  {
    name: 'Fatoumata Koné',
    role: 'Gérante, RetailMax ML — Bamako',
    initials: 'FK',
    color: '#f59e0b',
    quote: "Mes clients paient par Orange Money et ça se retrouve directement dans Frynov. Plus de double saisie, plus d'erreurs. Mon comptable reçoit les états en PDF chaque fin de mois sans que je fasse quoi que ce soit.",
  },
]

const testimonials = computed(() => isAfrica.value ? testimonialsAfrica : testimonialsGlobal)

/* ── Stats band ───────────────────────────────────────────── */
const statsGlobal = [
  { val: '+1 400', label: 'entreprises actives'              },
  { val: '3',      label: 'continents — Europe, Amériques, Afrique' },
  { val: '30+',    label: 'pays couverts'                    },
  { val: '99,9%',  label: 'disponibilité garantie'           },
  { val: '<5 min', label: 'pour être opérationnel'           },
  { val: '24/7',   label: 'support disponible'               },
]

const statsAfrica = [
  { val: '+1 400', label: 'PME actives'               },
  { val: '6',      label: "pays en Afrique de l'Ouest" },
  { val: '99,9%',  label: 'disponibilité garantie'    },
  { val: '3',      label: 'opérateurs Mobile Money'   },
  { val: '<5 min', label: 'pour être opérationnel'    },
  { val: 'FCFA',   label: 'devise native XOF'         },
]

const stats = computed(() => (isAfrica.value ? statsAfrica : statsGlobal).map(stat => (
  stat.val === 'FCFA' ? { ...stat, val: market.value.currency, label: `devise native ${market.value.currency}` } : stat
)))

/* ── FAQ ──────────────────────────────────────────────────── */
const faqsGlobal = [
  {
    q: 'Puis-je essayer Frynov gratuitement ?',
    a: "Oui. Le plan Starter est entièrement gratuit — aucune carte bancaire requise. Vous accédez à toutes les fonctionnalités de base sans limitation de temps.",
  },
  {
    q: 'Mes données sont-elles sécurisées ?',
    a: "Toutes les données sont chiffrées en transit (TLS 1.3) et au repos (AES-256). Sauvegardes automatiques quotidiennes. Hébergement en Europe, conformité RGPD.",
  },
  {
    q: 'Puis-je importer mon catalogue depuis Excel ?',
    a: "Absolument. Frynov supporte l'import Excel et CSV pour les produits, clients, fournisseurs et stocks initiaux. Un assistant de mapping vous guide colonne par colonne.",
  },
  {
    q: 'Y a-t-il une application mobile ?',
    a: "Frynov est une Progressive Web App (PWA) : installez-le depuis Chrome ou Safari sur Android/iOS. Toutes les fonctions principales sont disponibles sur mobile.",
  },
  {
    q: "Combien d'utilisateurs puis-je inviter ?",
    a: "Le plan Starter inclut 1 utilisateur. Le plan Pro monte à 10, et l'Enterprise est illimité. Vous pouvez définir des rôles précis (Admin, Manager, Vendeur, Lecteur) pour chaque membre.",
  },
  {
    q: 'Puis-je annuler à tout moment ?',
    a: "Oui, sans engagement ni frais de résiliation. En cas d'annulation, vous conservez l'accès jusqu'à la fin de votre période payée et pouvez exporter toutes vos données.",
  },
]

const faqsAfrica = [
  {
    q: 'Frynov fonctionne-t-il sans connexion internet stable ?',
    a: 'Frynov est optimisé pour les connexions lentes ou instables. Les pages critiques (stock, caisse) se chargent en moins de 2 secondes sur 3G. Une version hors-ligne est en développement.',
  },
  {
    q: 'Puis-je payer par Mobile Money (Wave, Orange Money) ?',
    a: 'Oui. Toutes nos formules payantes acceptent Wave CI, Wave SN, Orange Money CI/SN/ML et MTN MoMo. Vous recevez une confirmation automatique et une facture en XOF.',
  },
  {
    q: 'Mes données sont-elles stockées en Afrique ?',
    a: "Nos serveurs principaux sont en Europe (conformité RGPD) avec un nœud de réplication en Afrique pour réduire la latence. Aucune donnée n'est revendue à des tiers.",
  },
  {
    q: 'Est-ce que Frynov gère plusieurs devises ?',
    a: 'Frynov affiche les tarifs et documents dans la devise de votre marché : XOF, XAF, NGN, GHS, KES, ZAR, EUR, CAD ou USD selon le pays sélectionné.',
  },
  {
    q: 'Puis-je importer mon catalogue depuis Excel ?',
    a: "Absolument. Frynov supporte l'import Excel et CSV pour les produits, clients, fournisseurs et stocks initiaux. Un assistant de mapping vous guide colonne par colonne.",
  },
  {
    q: 'Y a-t-il une application mobile ?',
    a: "Frynov est une Progressive Web App (PWA) : installez-le depuis Chrome ou Safari sur Android/iOS. Une app native est prévue pour T3 2026. Sur mobile, toutes les fonctions principales sont disponibles.",
  },
]

const faqs = computed(() => isAfrica.value ? faqsAfrica : faqsGlobal)
</script>

<style scoped>
/* ════════════════════════════════════════════════════════════
   BASE
════════════════════════════════════════════════════════════ */
.landing {
  font-family: var(--font-sans, system-ui, -apple-system, sans-serif);
  color: #1e293b;
  overflow-x: hidden;
  background: #fff;
}

.container {
  max-width: 1180px;
  margin: 0 auto;
  padding: 0 1.5rem;
}

.accent { color: #10b981; }

/* ════════════════════════════════════════════════════════════
   SECTION HEADERS
════════════════════════════════════════════════════════════ */
.section-header {
  text-align: center;
  max-width: 640px;
  margin: 0 auto 3.5rem;
}

.section-badge {
  display: inline-block;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 1.2px;
  text-transform: uppercase;
  color: #10b981;
  background: rgba(16,185,129,0.08);
  border: 1px solid rgba(16,185,129,0.2);
  padding: 0.3rem 0.9rem;
  border-radius: 20px;
  margin-bottom: 1rem;
}

.section-header h2 {
  font-size: clamp(1.6rem, 3vw, 2.3rem);
  font-weight: 800;
  color: #0f172a;
  line-height: 1.2;
  letter-spacing: -0.5px;
  margin-bottom: 0.75rem;
}

.section-header p {
  font-size: 1rem;
  color: #64748b;
  line-height: 1.7;
  margin: 0;
}

.market-selector {
  margin: 1.25rem auto 0;
  display: inline-flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.5rem 0.65rem 0.5rem 0.85rem;
  border: 1px solid #d1fae5;
  border-radius: 999px;
  background: white;
  color: #047857;
  font-size: 0.875rem;
  font-weight: 700;
  box-shadow: 0 8px 24px rgba(16,185,129,0.08);
}

.market-selector select {
  border: 0;
  border-radius: 999px;
  background: #ecfdf5;
  color: #065f46;
  font: inherit;
  padding: 0.35rem 0.8rem;
  cursor: pointer;
}

.market-selector select:focus {
  outline: 2px solid rgba(16,185,129,0.35);
  outline-offset: 2px;
}

/* ── Toggle périodicité Mensuel / Annuel (RC-1D) ───────────────────────────── */
.billing-toggle {
  margin: 1rem auto 0;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.3rem;
  border: 1px solid #d1fae5;
  border-radius: 999px;
  background: white;
  box-shadow: 0 8px 24px rgba(16,185,129,0.08);
}

.billing-toggle__btn {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: #475569;
  font: inherit;
  font-weight: 700;
  font-size: 0.875rem;
  padding: 0.45rem 1.1rem;
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}

.billing-toggle__btn.active {
  background: #059669;
  color: white;
}

.billing-toggle__btn:focus-visible {
  outline: 2px solid rgba(16,185,129,0.5);
  outline-offset: 2px;
}

.billing-toggle__pill {
  font-size: 0.7rem;
  font-weight: 700;
  padding: 0.1rem 0.5rem;
  border-radius: 999px;
  background: #fef3c7;
  color: #92400e;
}

.billing-toggle__btn.active .billing-toggle__pill {
  background: rgba(255,255,255,0.22);
  color: white;
}

.plan-annual-eq {
  margin-top: 0.35rem;
  font-size: 0.85rem;
  font-weight: 600;
  color: #64748b;
}

.plan-savings {
  display: inline-block;
  margin-top: 0.4rem;
  font-size: 0.78rem;
  font-weight: 700;
  padding: 0.15rem 0.6rem;
  border-radius: 999px;
  background: #ecfdf5;
  color: #047857;
}

/* ════════════════════════════════════════════════════════════
   CTA PRIMITIVES
════════════════════════════════════════════════════════════ */
.cta-primary {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  background: linear-gradient(135deg, #10b981, #059669);
  color: white;
  font-weight: 700;
  font-size: 0.9rem;
  padding: 0.65rem 1.4rem;
  border-radius: 10px;
  text-decoration: none;
  transition: opacity 0.15s, transform 0.15s, box-shadow 0.15s;
  white-space: nowrap;
}
.cta-primary:hover {
  opacity: 0.93;
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(16,185,129,0.35);
}

.cta-xl {
  font-size: 1rem;
  padding: 0.8rem 1.75rem;
  border-radius: 12px;
}

.cta-ghost {
  display: inline-flex;
  align-items: center;
  background: transparent;
  border: 2px solid #e2e8f0;
  color: #475569;
  font-weight: 600;
  font-size: 0.9rem;
  padding: 0.65rem 1.4rem;
  border-radius: 10px;
  text-decoration: none;
  transition: border-color 0.15s, color 0.15s;
  white-space: nowrap;
}
.cta-ghost:hover { border-color: #10b981; color: #10b981; }

/* ════════════════════════════════════════════════════════════
   NAVBAR
════════════════════════════════════════════════════════════ */
.nav {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
  background: transparent;
  transition: background 0.25s, box-shadow 0.25s;
}

.nav--scrolled {
  background: rgba(255,255,255,0.92);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  box-shadow: 0 1px 0 #e2e8f0;
}

.nav-inner {
  height: 68px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 2rem;
}

.nav-logo { text-decoration: none; }

.nav-links {
  display: flex;
  align-items: center;
  gap: 2rem;
}

.nav-links a {
  font-size: 0.875rem;
  font-weight: 500;
  color: #475569;
  text-decoration: none;
  transition: color 0.15s;
}
.nav-links a:hover { color: #0f172a; }

.nav-actions {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.nav-login {
  font-size: 0.875rem;
  font-weight: 600;
  color: #475569;
  text-decoration: none;
  padding: 0.5rem 0.9rem;
  border-radius: 8px;
  transition: color 0.15s, background 0.15s;
}
.nav-login:hover { background: #f1f5f9; color: #0f172a; }

/* ── Hamburger ─────────────────────────────────────────── */
.hamburger {
  display: none;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  gap: 5px;
  width: 40px;
  height: 40px;
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px;
  border-radius: 8px;
  transition: background 0.15s;
}
.hamburger:hover { background: #f1f5f9; }

.hamburger span {
  display: block;
  width: 22px;
  height: 2px;
  background: #475569;
  border-radius: 2px;
  transition: transform 0.25s, opacity 0.25s;
  transform-origin: center;
}

.hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.hamburger.open span:nth-child(2) { opacity: 0; transform: scaleX(0); }
.hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

/* ── Mobile drawer ─────────────────────────────────────── */
.mobile-drawer {
  position: fixed;
  inset: 68px 0 0 0;
  background: rgba(15,23,42,0.4);
  backdrop-filter: blur(4px);
  z-index: 99;
}

.drawer-nav {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  background: white;
  padding: 1.25rem 1.5rem 1.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}

.drawer-nav a {
  font-size: 1rem;
  font-weight: 600;
  color: #334155;
  text-decoration: none;
  padding: 0.7rem 0.5rem;
  border-radius: 8px;
  transition: background 0.15s, color 0.15s;
}
.drawer-nav a:hover { background: #f8fafc; color: #10b981; }

.drawer-sep { height: 1px; background: #e2e8f0; margin: 0.5rem 0; }

.drawer-login {
  font-size: 1rem;
  font-weight: 600;
  color: #475569;
  text-decoration: none;
  padding: 0.7rem 0.5rem;
}

.drawer-cta { margin-top: 0.5rem; justify-content: center; }

/* ── Drawer transition ─────────────────────────────────── */
.drawer-enter-active,
.drawer-leave-active { transition: opacity 0.2s; }
.drawer-enter-from,
.drawer-leave-to { opacity: 0; }

/* ════════════════════════════════════════════════════════════
   HERO
════════════════════════════════════════════════════════════ */
.hero {
  padding: 138px 0 80px;
  background: linear-gradient(160deg, #f0fdf9 0%, #eff6ff 55%, #f8fafc 100%);
  position: relative;
  overflow: hidden;
}

.hero-glow {
  position: absolute;
  width: 500px;
  height: 500px;
  border-radius: 50%;
  pointer-events: none;
}
.hero-glow--tl {
  top: -180px;
  left: -180px;
  background: radial-gradient(circle, rgba(16,185,129,0.14) 0%, transparent 65%);
}
.hero-glow--br {
  bottom: -200px;
  right: -200px;
  background: radial-gradient(circle, rgba(59,130,246,0.10) 0%, transparent 65%);
}

.hero-inner {
  display: grid;
  grid-template-columns: 1fr 1.1fr;
  gap: 4rem;
  align-items: center;
  position: relative;
  z-index: 1;
}

.hero-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.6px;
  text-transform: uppercase;
  color: #10b981;
  background: rgba(16,185,129,0.07);
  border: 1px solid rgba(16,185,129,0.2);
  padding: 0.3rem 0.85rem;
  border-radius: 20px;
  margin-bottom: 1.5rem;
}

.eyebrow-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #10b981;
  animation: blink 2s ease-in-out infinite;
}

@keyframes blink {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0.3; }
}

.hero-title {
  font-size: clamp(2.1rem, 4vw, 3.2rem);
  font-weight: 900;
  color: #0f172a;
  line-height: 1.12;
  letter-spacing: -1px;
  margin-bottom: 1.25rem;
}

.hero-title-gradient {
  background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.hero-sub {
  font-size: 1.05rem;
  color: #64748b;
  line-height: 1.75;
  margin-bottom: 2rem;
  max-width: 480px;
}

.hero-actions {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
  margin-bottom: 2rem;
}

/* Social proof */
.social-proof {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.875rem;
  color: #64748b;
}

.sp-avatars { display: flex; }
.sp-avatar {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  border: 2.5px solid white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.6rem;
  font-weight: 800;
  color: white;
  margin-left: -7px;
  box-shadow: 0 0 0 1px rgba(0,0,0,0.06);
}
.sp-avatar:first-child { margin-left: 0; }

/* ── Dashboard mockup ──────────────────────────────────── */
.hero-visual { position: relative; }

.dashboard-mockup {
  background: white;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  box-shadow:
    0 2px 4px rgba(0,0,0,0.04),
    0 8px 24px rgba(0,0,0,0.08),
    0 32px 72px rgba(0,0,0,0.10);
  overflow: hidden;
  transform: perspective(1200px) rotateY(-4deg) rotateX(2deg);
  transform-style: preserve-3d;
}

.mockup-bar {
  background: #0f172a;
  padding: 0.6rem 1rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.mockup-dots { display: flex; gap: 5px; }
.dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
}
.dot-red    { background: #ef4444; }
.dot-yellow { background: #f59e0b; }
.dot-green  { background: #10b981; }

.mockup-title-bar {
  flex: 1;
  font-size: 0.6rem;
  color: rgba(255,255,255,0.45);
  letter-spacing: 0.3px;
}

.mockup-pill {
  font-size: 0.55rem;
  color: #10b981;
  background: rgba(16,185,129,0.12);
  padding: 0.15rem 0.5rem;
  border-radius: 20px;
  font-weight: 600;
}

.mockup-body {
  padding: 0.9rem;
  background: #f8fafc;
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}

/* KPI grid */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.45rem;
}

.kpi-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 0.55rem 0.55rem 0.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.kpi-icon {
  width: 22px;
  height: 22px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.kpi-val {
  font-size: 0.72rem;
  font-weight: 800;
  line-height: 1.2;
}

.kpi-lbl {
  font-size: 0.5rem;
  color: #94a3b8;
  margin-top: 1px;
}

.kpi-trend {
  font-size: 0.48rem;
  font-weight: 700;
  margin-top: 2px;
}
.kpi-trend.up   { color: #10b981; }
.kpi-trend.down { color: #ef4444; }

/* Chart card */
.chart-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 0.7rem;
}

.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.55rem;
}

.chart-title {
  font-size: 0.58rem;
  font-weight: 600;
  color: #475569;
}

.chart-badge {
  font-size: 0.5rem;
  font-weight: 700;
  color: #10b981;
  background: rgba(16,185,129,0.1);
  padding: 0.1rem 0.35rem;
  border-radius: 20px;
}

.chart-bars {
  display: flex;
  align-items: flex-end;
  gap: 2.5px;
  height: 56px;
}

.chart-bar {
  flex: 1;
  background: rgba(16,185,129,0.25);
  border-radius: 2px 2px 0 0;
  transition: background 0.15s;
}
.chart-bar.active { background: #10b981; }

/* Orders card */
.orders-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 0.7rem;
}

.orders-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.58rem;
  font-weight: 700;
  color: #334155;
  margin-bottom: 0.55rem;
}

.orders-link { color: #10b981; font-weight: 600; }

.order-row {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.3rem 0;
  border-top: 1px solid #f1f5f9;
}
.order-row:first-of-type { border-top: none; }

.order-avatar {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  font-size: 0.45rem;
  font-weight: 800;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.order-name {
  flex: 1;
  font-size: 0.55rem;
  font-weight: 600;
  color: #334155;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.order-amount {
  font-size: 0.52rem;
  font-weight: 700;
  color: #0f172a;
  white-space: nowrap;
}

.order-status {
  font-size: 0.48rem;
  font-weight: 700;
  padding: 0.1rem 0.35rem;
  border-radius: 20px;
  white-space: nowrap;
}

.status-delivered { color: #10b981; background: rgba(16,185,129,0.1); }
.status-pending   { color: #f59e0b; background: rgba(245,158,11,0.1);  }
.status-confirmed { color: #3b82f6; background: rgba(59,130,246,0.1);  }

/* ── Wave floating card ─────────────────────────────────── */
.wave-card {
  position: absolute;
  bottom: -24px;
  left: -32px;
  z-index: 2;
  animation: float 4s ease-in-out infinite;
}

@keyframes float {
  0%,100% { transform: translateY(0px); }
  50%      { transform: translateY(-8px); }
}

.wave-card-inner {
  background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
  border-radius: 14px;
  padding: 0.95rem 1.25rem;
  width: 170px;
  box-shadow:
    0 4px 16px rgba(14,165,233,0.4),
    0 12px 32px rgba(37,99,235,0.25);
  color: white;
}

.wave-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.wave-label {
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 0.5px;
}

.wave-amount {
  font-size: 1.15rem;
  font-weight: 800;
  line-height: 1.2;
  margin-bottom: 0.3rem;
}
.wave-amount span {
  font-size: 0.65rem;
  font-weight: 600;
  opacity: 0.75;
}

.wave-sub {
  font-size: 0.6rem;
  opacity: 0.75;
  margin-bottom: 0.75rem;
}

.wave-dots {
  display: flex;
  gap: 3px;
}
.wave-dots span {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: rgba(255,255,255,0.4);
}
.wave-dots span:last-child { background: rgba(255,255,255,0.8); }

/* ════════════════════════════════════════════════════════════
   LOGOS STRIP
════════════════════════════════════════════════════════════ */
.logos-strip {
  padding: 2.5rem 0;
  background: white;
  border-top: 1px solid #f1f5f9;
  border-bottom: 1px solid #f1f5f9;
}

.logos-label {
  text-align: center;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.2px;
  color: #94a3b8;
  margin: 0 0 1.25rem;
}

.logos-row {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1.5rem;
  flex-wrap: wrap;
}

.logo-chip {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.8rem;
  font-weight: 700;
  color: #cbd5e1;
  letter-spacing: 0.3px;
  transition: color 0.15s;
}
.logo-chip:hover { color: #94a3b8; }

.logo-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #cbd5e1;
}

/* ════════════════════════════════════════════════════════════
   PROBLEM → SOLUTION
════════════════════════════════════════════════════════════ */
.problem-solution {
  padding: 6rem 0;
  background: #f8fafc;
}

.ps-inner {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  gap: 2rem;
  align-items: start;
}

.ps-col {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  border: 1px solid #e2e8f0;
}

.ps-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.8px;
  text-transform: uppercase;
  padding: 0.25rem 0.7rem;
  border-radius: 20px;
  margin-bottom: 1.25rem;
}
.ps-badge--red   { color: #ef4444; background: rgba(239,68,68,0.07);  border: 1px solid rgba(239,68,68,0.15); }
.ps-badge--green { color: #10b981; background: rgba(16,185,129,0.07); border: 1px solid rgba(16,185,129,0.15); }

.ps-heading {
  font-size: clamp(1.2rem, 2.5vw, 1.6rem);
  font-weight: 800;
  color: #0f172a;
  line-height: 1.3;
  margin-bottom: 1.5rem;
  letter-spacing: -0.3px;
}
.ps-red   { color: #ef4444; }
.ps-green { color: #10b981; }

.ps-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.ps-list li {
  display: flex;
  align-items: flex-start;
  gap: 0.6rem;
  font-size: 0.9rem;
  line-height: 1.5;
  color: #475569;
}
.ps-list li svg { flex-shrink: 0; margin-top: 2px; }

.ps-arrow {
  display: flex;
  align-items: center;
  padding-top: 4rem;
}

.arrow-circle {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: linear-gradient(135deg, #10b981, #3b82f6);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 16px rgba(16,185,129,0.3);
  flex-shrink: 0;
}

/* ════════════════════════════════════════════════════════════
   FEATURES GRID
════════════════════════════════════════════════════════════ */
.features {
  padding: 6rem 0;
  background: white;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.5rem;
}

.feat-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 1.75rem;
  transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
  cursor: default;
}

.feat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 40px rgba(0,0,0,0.08);
  border-color: rgba(16,185,129,0.25);
}

.feat-icon {
  width: 46px;
  height: 46px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1rem;
}

.feat-card h3 {
  font-size: 1rem;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 0.5rem;
}

.feat-card p {
  font-size: 0.875rem;
  color: #64748b;
  line-height: 1.65;
  margin: 0;
}

/* ════════════════════════════════════════════════════════════
   PRICING
════════════════════════════════════════════════════════════ */
.pricing {
  padding: 6rem 0;
  background: #f0fdf9;
}

.plans-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1.5rem;
  align-items: start;
}

.plan-card {
  background: white;
  border: 2px solid #e2e8f0;
  border-radius: 16px;
  padding: 2rem;
  position: relative;
  transition: transform 0.2s, box-shadow 0.2s;
}

.plan-card:hover { transform: translateY(-3px); box-shadow: 0 12px 40px rgba(0,0,0,0.09); }

.plan-card.featured {
  border-color: #10b981;
  transform: scale(1.02);
  box-shadow: 0 8px 32px rgba(16,185,129,0.2);
}
.plan-card.featured:hover { transform: scale(1.02) translateY(-3px); }

.plan-badge {
  position: absolute;
  top: -13px;
  left: 50%;
  transform: translateX(-50%);
  background: linear-gradient(135deg, #10b981, #059669);
  color: white;
  font-size: 0.7rem;
  font-weight: 800;
  letter-spacing: 0.4px;
  padding: 0.25rem 0.9rem;
  border-radius: 20px;
  white-space: nowrap;
}

.plan-name {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: #94a3b8;
  margin-bottom: 0.75rem;
}

.plan-price {
  display: flex;
  align-items: baseline;
  gap: 0.4rem;
  margin-bottom: 0.5rem;
}

.plan-amount {
  font-size: 2rem;
  font-weight: 900;
  color: #0f172a;
  letter-spacing: -1px;
}

.plan-period {
  font-size: 0.8rem;
  color: #94a3b8;
  font-weight: 500;
}

.plan-tagline {
  font-size: 0.875rem;
  color: #64748b;
  margin-bottom: 1.5rem;
  line-height: 1.5;
}

.plan-features {
  list-style: none;
  margin: 0 0 1.75rem;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.plan-features li {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  font-size: 0.875rem;
  color: #475569;
}

.plan-features li svg { flex-shrink: 0; color: #10b981; }

.plan-cta {
  display: block;
  text-align: center;
  padding: 0.75rem;
  border-radius: 10px;
  font-weight: 700;
  font-size: 0.9rem;
  text-decoration: none;
  border: 2px solid #e2e8f0;
  color: #475569;
  transition: border-color 0.15s, color 0.15s, background 0.15s;
}
.plan-cta:hover { border-color: #10b981; color: #10b981; }

.plan-cta--primary {
  background: linear-gradient(135deg, #10b981, #059669);
  border-color: transparent;
  color: white;
  box-shadow: 0 4px 16px rgba(16,185,129,0.3);
}
.plan-cta--primary:hover {
  color: white;
  opacity: 0.92;
  border-color: transparent;
}

.pricing-note {
  text-align: center;
  margin-top: 2rem;
  font-size: 0.8rem;
  color: #94a3b8;
}

/* ════════════════════════════════════════════════════════════
   TESTIMONIALS
════════════════════════════════════════════════════════════ */
.testimonials {
  padding: 6rem 0;
  background: white;
}

.testi-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.5rem;
}

.testi-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 1.75rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  transition: transform 0.2s, box-shadow 0.2s;
}
.testi-card:hover { transform: translateY(-3px); box-shadow: 0 10px 32px rgba(0,0,0,0.08); }

.testi-stars { color: #f59e0b; font-size: 0.85rem; letter-spacing: 1px; }

.testi-quote {
  font-size: 0.9rem;
  color: #334155;
  line-height: 1.7;
  font-style: italic;
  margin: 0;
  flex: 1;
}

.testi-author {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-top: auto;
}

.testi-avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  font-weight: 800;
  color: white;
  flex-shrink: 0;
}

.testi-name { font-size: 0.875rem; font-weight: 700; color: #0f172a; }
.testi-role { font-size: 0.75rem; color: #94a3b8; margin-top: 1px; }

/* ════════════════════════════════════════════════════════════
   STATS BAND
════════════════════════════════════════════════════════════ */
.stats-band {
  padding: 4.5rem 0;
  background: #0f172a;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 1.5rem;
  text-align: center;
}

.stat-item { display: flex; flex-direction: column; gap: 0.35rem; }

.stat-val {
  font-size: clamp(1.5rem, 2.5vw, 2rem);
  font-weight: 900;
  color: #10b981;
  letter-spacing: -0.5px;
}

.stat-lbl {
  font-size: 0.78rem;
  color: rgba(255,255,255,0.5);
  line-height: 1.4;
}

/* ════════════════════════════════════════════════════════════
   FINAL CTA
════════════════════════════════════════════════════════════ */
.final-cta {
  padding: 7rem 0;
  background: #0f172a;
  text-align: center;
  position: relative;
  overflow: hidden;
}

.fc-glow {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 600px;
  height: 400px;
  background: radial-gradient(ellipse, rgba(16,185,129,0.18) 0%, transparent 65%);
  pointer-events: none;
}

.fc-inner { position: relative; z-index: 1; }

.fc-kicker {
  display: inline-block;
  font-size: 0.75rem;
  font-weight: 600;
  color: rgba(16,185,129,0.8);
  letter-spacing: 0.5px;
  text-transform: uppercase;
  margin-bottom: 1.25rem;
}

.fc-title {
  font-size: clamp(1.75rem, 4vw, 3rem);
  font-weight: 900;
  color: white;
  line-height: 1.2;
  letter-spacing: -1px;
  margin-bottom: 1.25rem;
}

.fc-sub {
  font-size: 1rem;
  color: rgba(255,255,255,0.6);
  line-height: 1.7;
  max-width: 540px;
  margin: 0 auto 2.25rem;
}

.fc-actions {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}

.fc-login {
  font-size: 0.875rem;
  color: rgba(255,255,255,0.55);
  text-decoration: none;
  font-weight: 600;
  transition: color 0.15s;
}
.fc-login:hover { color: white; }

/* ════════════════════════════════════════════════════════════
   FAQ
════════════════════════════════════════════════════════════ */
.faq { padding: 6rem 0; background: #f8fafc; }

.faq-wrap { max-width: 760px; margin: 0 auto; }

.faq-list { display: flex; flex-direction: column; gap: 0.6rem; }

.faq-item {
  background: white;
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.faq-item:hover { border-color: rgba(16,185,129,0.35); }
.faq-item.open  { border-color: rgba(16,185,129,0.5); box-shadow: 0 4px 16px rgba(16,185,129,0.08); }

.faq-q {
  padding: 1.1rem 1.3rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  font-size: 0.925rem;
  font-weight: 600;
  color: #1e293b;
  user-select: none;
}

.faq-chevron {
  flex-shrink: 0;
  color: #94a3b8;
  transition: transform 0.25s, color 0.15s;
}
.faq-item.open .faq-chevron { transform: rotate(180deg); color: #10b981; }

.faq-a {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.3s ease, padding 0.3s ease;
  font-size: 0.875rem;
  color: #64748b;
  line-height: 1.75;
  padding: 0 1.3rem;
}
.faq-item.open .faq-a { max-height: 200px; padding-bottom: 1.2rem; }

/* ════════════════════════════════════════════════════════════
   FOOTER
════════════════════════════════════════════════════════════ */
.footer {
  background: #0f172a;
  color: rgba(255,255,255,0.6);
}

.footer-main {
  padding: 3.5rem 1.5rem 2.5rem;
  display: grid;
  grid-template-columns: 1.5fr 1fr;
  gap: 4rem;
  align-items: start;
}

.footer-brand p {
  font-size: 0.875rem;
  margin-top: 0.85rem;
  max-width: 280px;
  line-height: 1.65;
  color: rgba(255,255,255,0.45);
}

.footer-mm-logos {
  display: flex;
  gap: 0.5rem;
  margin-top: 1.25rem;
  flex-wrap: wrap;
}

.mm-chip {
  font-size: 0.65rem;
  font-weight: 700;
  padding: 0.2rem 0.55rem;
  border-radius: 20px;
  letter-spacing: 0.3px;
}
.mm-chip.wave   { background: rgba(14,165,233,0.2);  color: #38bdf8; }
.mm-chip.orange { background: rgba(249,115,22,0.2);  color: #fb923c; }
.mm-chip.mtn    { background: rgba(245,158,11,0.2);  color: #fbbf24; }

.footer-cols {
  display: flex;
  gap: 3rem;
}

.footer-col {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.footer-col-title {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: rgba(255,255,255,0.3);
  margin-bottom: 0.25rem;
}

.footer-col a {
  font-size: 0.875rem;
  color: rgba(255,255,255,0.5);
  text-decoration: none;
  transition: color 0.15s;
}
.footer-col a:hover { color: white; }

.footer-bottom {
  border-top: 1px solid rgba(255,255,255,0.07);
  padding: 1.25rem 1.5rem;
}

.footer-bottom-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.75rem;
  color: rgba(255,255,255,0.3);
}

.footer-legal { display: flex; gap: 1.5rem; }
.footer-legal a { color: rgba(255,255,255,0.3); text-decoration: none; transition: color 0.15s; }
.footer-legal a:hover { color: rgba(255,255,255,0.6); }

/* ════════════════════════════════════════════════════════════
   RESPONSIVE
════════════════════════════════════════════════════════════ */
.hide-mobile { display: flex; }

@media (max-width: 1024px) {
  .hero-inner     { grid-template-columns: 1fr; gap: 3rem; }
  .dashboard-mockup { transform: none; max-width: 580px; margin: 0 auto; }
  .wave-card { bottom: -16px; left: -10px; }

  .ps-inner       { grid-template-columns: 1fr; gap: 1.5rem; }
  .ps-arrow       { justify-content: center; padding-top: 0; }
  .arrow-circle   { transform: rotate(90deg); }

  .features-grid  { grid-template-columns: repeat(2, 1fr); }
  .plans-grid     { grid-template-columns: 1fr; max-width: 420px; margin: 0 auto; }
  .plan-card.featured { transform: scale(1); }
  .plan-card.featured:hover { transform: translateY(-3px); }

  .testi-grid     { grid-template-columns: 1fr; max-width: 520px; margin: 0 auto; }
  .stats-grid     { grid-template-columns: repeat(3, 1fr); }
  .footer-main    { grid-template-columns: 1fr; gap: 2.5rem; }
  .footer-cols    { flex-wrap: wrap; gap: 2rem; }
}

@media (max-width: 768px) {
  .hide-mobile    { display: none !important; }
  .hamburger      { display: flex; }

  .hero           { padding: 110px 0 60px; }
  .hero-title     { font-size: 2rem; }
  .hero-actions   { flex-direction: column; }
  .hero-actions .cta-primary,
  .hero-actions .cta-ghost { width: 100%; justify-content: center; }

  .kpi-grid       { grid-template-columns: repeat(2, 1fr); }

  .features-grid  { grid-template-columns: 1fr; }
  .plans-grid     { max-width: 100%; }

  .stats-grid     { grid-template-columns: repeat(2, 1fr); }

  .fc-actions     { flex-direction: column; align-items: stretch; }
  .fc-actions .cta-primary { justify-content: center; }

  .footer-bottom-inner { flex-direction: column; gap: 0.5rem; text-align: center; }
  .footer-legal   { justify-content: center; }
}

@media (max-width: 480px) {
  .hero-title     { font-size: 1.75rem; }
  .wave-card      { display: none; }
  .ps-inner       { grid-template-columns: 1fr; }
  .stats-grid     { grid-template-columns: repeat(2, 1fr); gap: 1rem; }
  .fc-title       { font-size: 1.75rem; }
}
</style>
