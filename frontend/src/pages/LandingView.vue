<template>
  <div class="landing">

    <!-- ── Navbar ─────────────────────────────────────────── -->
    <header class="nav" :class="{ 'nav--scrolled': scrolled }">
      <div class="container nav-inner">
        <RouterLink to="/" class="nav-logo">
          <NexoraLogo variant="color" />
        </RouterLink>

        <nav class="nav-links hide-mobile">
          <a href="#features">Fonctionnalités</a>
          <a href="#modules">Modules</a>
          <a href="#how">Comment ça marche</a>
          <a href="#faq">FAQ</a>
        </nav>

        <div class="nav-actions">
          <RouterLink to="/login" class="btn btn-ghost btn-sm">Connexion</RouterLink>
          <RouterLink to="/register" class="btn btn-primary btn-sm">Démarrer gratuitement</RouterLink>
        </div>
      </div>
    </header>

    <!-- ── Hero ───────────────────────────────────────────── -->
    <section class="hero">
      <div class="container hero-inner">
        <div class="hero-content">
          <div class="hero-badge">✦ ERP nouvelle génération</div>
          <h1 class="hero-title">
            La gestion intelligente<br>
            <span class="hero-title-accent">de votre entreprise</span>
          </h1>
          <p class="hero-subtitle">
            Nexora centralise inventaire, commandes, clients et rapports
            en une plateforme unifiée. Configuré en minutes. Évolutif sans limite.
          </p>
          <div class="hero-actions">
            <RouterLink to="/register" class="btn btn-primary btn-xl">
              Démarrer gratuitement
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </RouterLink>
            <a href="#how" class="btn btn-secondary btn-xl">Voir comment ça marche</a>
          </div>
          <div class="hero-social-proof">
            <div class="proof-avatars">
              <div class="proof-avatar" style="background: #10b981;">A</div>
              <div class="proof-avatar" style="background: #3b82f6;">M</div>
              <div class="proof-avatar" style="background: #8b5cf6;">S</div>
              <div class="proof-avatar" style="background: #f59e0b;">K</div>
            </div>
            <span>Rejoint par <strong>+1 200 entreprises</strong> dans 30 pays</span>
          </div>
        </div>

        <div class="hero-visual">
          <div class="dashboard-mockup">
            <div class="mockup-topbar">
              <div class="mockup-dots">
                <span></span><span></span><span></span>
              </div>
              <div class="mockup-title">Nexora ERP — Tableau de bord</div>
            </div>
            <div class="mockup-body">
              <div class="mockup-kpis">
                <div class="mockup-kpi" v-for="k in mockKpis" :key="k.label">
                  <div class="mockup-kpi-value" :style="{ color: k.color }">{{ k.value }}</div>
                  <div class="mockup-kpi-label">{{ k.label }}</div>
                </div>
              </div>
              <div class="mockup-chart">
                <div class="chart-bars">
                  <div
                    v-for="h in chartBars"
                    :key="h"
                    class="chart-bar"
                    :style="{ height: h + '%' }"
                  ></div>
                </div>
                <div class="chart-label">Évolution des ventes — 12 derniers mois</div>
              </div>
              <div class="mockup-table-rows">
                <div class="mockup-row" v-for="r in 4" :key="r">
                  <div class="row-dot"></div>
                  <div class="row-line long"></div>
                  <div class="row-line short"></div>
                  <div class="row-badge"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ── Trusted by ─────────────────────────────────────── -->
    <section class="trusted">
      <div class="container">
        <p class="trusted-label">Utilisé par des entreprises de toutes tailles</p>
        <div class="trusted-logos">
          <div v-for="name in trustedNames" :key="name" class="trusted-logo">{{ name }}</div>
        </div>
      </div>
    </section>

    <!-- ── Features ───────────────────────────────────────── -->
    <section id="features" class="features">
      <div class="container">
        <div class="section-header">
          <div class="section-badge">Fonctionnalités</div>
          <h2>Tout ce dont vous avez besoin, <span class="accent">rien de superflu</span></h2>
          <p>Une plateforme complète conçue pour simplifier votre quotidien, pas pour le compliquer.</p>
        </div>

        <div class="features-grid">
          <div v-for="feat in features" :key="feat.title" class="feature-card">
            <div class="feature-icon" :style="{ background: feat.iconBg }">
              <span v-html="feat.icon"></span>
            </div>
            <h3>{{ feat.title }}</h3>
            <p>{{ feat.description }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ── How it works ───────────────────────────────────── -->
    <section id="how" class="how">
      <div class="container">
        <div class="section-header">
          <div class="section-badge">Simple à démarrer</div>
          <h2>Opérationnel en <span class="accent">moins de 5 minutes</span></h2>
          <p>Notre onboarding intelligent configure votre espace selon votre activité.</p>
        </div>

        <div class="steps">
          <div v-for="(step, i) in steps" :key="step.title" class="step">
            <div class="step-number">{{ i + 1 }}</div>
            <div class="step-content">
              <h3>{{ step.title }}</h3>
              <p>{{ step.description }}</p>
            </div>
            <div v-if="i < steps.length - 1" class="step-connector"></div>
          </div>
        </div>
      </div>
    </section>

    <!-- ── Modules ────────────────────────────────────────── -->
    <section id="modules" class="modules">
      <div class="container">
        <div class="section-header">
          <div class="section-badge">Modules</div>
          <h2>Activez uniquement ce dont <span class="accent">vous avez besoin</span></h2>
          <p>Chaque module fonctionne de manière autonome. Activez-les à la demande, selon votre croissance.</p>
        </div>

        <div class="modules-grid">
          <div v-for="mod in modules" :key="mod.title" class="module-card">
            <div class="module-header">
              <div class="module-icon">
                <span v-html="mod.icon"></span>
              </div>
              <span class="module-badge" :class="mod.status === 'Disponible' ? 'badge-green' : 'badge-gray'">
                {{ mod.status }}
              </span>
            </div>
            <h3>{{ mod.title }}</h3>
            <p>{{ mod.description }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ── CTA ────────────────────────────────────────────── -->
    <section class="cta-section">
      <div class="container cta-inner">
        <div class="cta-text">
          <h2>Prêt à transformer<br>votre gestion ?</h2>
          <p>Rejoignez plus de 1 200 entreprises qui font confiance à Nexora ERP pour piloter leur croissance.</p>
          <div class="cta-actions">
            <RouterLink to="/register" class="btn btn-primary btn-xl">
              Créer votre espace — c'est gratuit
            </RouterLink>
            <RouterLink to="/login" class="btn-text-white">
              Déjà inscrit ? Connexion →
            </RouterLink>
          </div>
        </div>
        <div class="cta-visual">
          <div class="cta-stats">
            <div v-for="stat in ctaStats" :key="stat.label" class="cta-stat">
              <div class="cta-stat-value">{{ stat.value }}</div>
              <div class="cta-stat-label">{{ stat.label }}</div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ── FAQ ────────────────────────────────────────────── -->
    <section id="faq" class="faq">
      <div class="container faq-inner">
        <div class="section-header">
          <div class="section-badge">FAQ</div>
          <h2>Questions fréquentes</h2>
        </div>

        <div class="faq-list">
          <div
            v-for="item in faqs"
            :key="item.q"
            class="faq-item"
            :class="{ open: openFaq === item.q }"
            @click="openFaq = openFaq === item.q ? null : item.q"
          >
            <div class="faq-question">
              {{ item.q }}
              <svg class="faq-chevron" width="16" height="16" viewBox="0 0 16 16" fill="none">
                <path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <div class="faq-answer">{{ item.a }}</div>
          </div>
        </div>
      </div>
    </section>

    <!-- ── Footer ─────────────────────────────────────────── -->
    <footer class="footer">
      <div class="container footer-inner">
        <div class="footer-brand">
          <NexoraLogo variant="light" />
          <p>ERP moderne pour entreprises ambitieuses.</p>
        </div>
        <div class="footer-links">
          <div class="footer-col">
            <div class="footer-col-title">Produit</div>
            <a href="#features">Fonctionnalités</a>
            <a href="#modules">Modules</a>
            <RouterLink to="/register">Démarrer</RouterLink>
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
        <div class="container">
          <span>&copy; {{ year }} Nexora ERP. Tous droits réservés.</span>
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
import { ref, onMounted, onUnmounted } from 'vue'
import { RouterLink } from 'vue-router'
import NexoraLogo from '@/shared/components/NexoraLogo.vue'

const year    = new Date().getFullYear()
const scrolled = ref(false)
const openFaq  = ref<string | null>(null)

function onScroll() { scrolled.value = window.scrollY > 20 }
onMounted(() => window.addEventListener('scroll', onScroll))
onUnmounted(() => window.removeEventListener('scroll', onScroll))

const mockKpis = [
  { label: 'Chiffre d\'affaires', value: '284k €', color: '#10b981' },
  { label: 'Commandes',           value: '1 248',   color: '#3b82f6' },
  { label: 'En stock',            value: '4 892',   color: '#8b5cf6' },
  { label: 'Clients actifs',      value: '367',     color: '#f59e0b' },
]

const chartBars = [45, 60, 40, 75, 55, 80, 65, 90, 70, 85, 60, 95]

const trustedNames = ['Distrib Pro', 'MarketHub', 'BizCore', 'RetailMax', 'FlowTrade', 'NovaCom']

const features = [
  {
    title: 'Inventaire en temps réel',
    description: 'Suivez chaque unité, recevez des alertes automatiques et évitez les ruptures de stock.',
    icon: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M3 6l7-4 7 4v8l-7 4-7-4V6z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M10 2v18M3 6l7 4 7-4" stroke="currentColor" stroke-width="1.5"/></svg>',
    iconBg: 'rgba(16,185,129,0.1)',
  },
  {
    title: 'Gestion des commandes',
    description: 'Du devis à la livraison, pilotez tout votre cycle de vente en quelques clics.',
    icon: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M3 3h14v12a1 1 0 01-1 1H4a1 1 0 01-1-1V3z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M7 8h6M7 11h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
    iconBg: 'rgba(59,130,246,0.1)',
  },
  {
    title: 'CRM & Clients',
    description: 'Centralisez l\'historique de vos clients, segmentez et fidélisez avec précision.',
    icon: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="8" cy="6" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M2 18c0-3.3 2.7-6 6-6s6 2.7 6 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M15 8a2 2 0 010-5M18 18c0-2.8-1.6-5-4-5.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
    iconBg: 'rgba(139,92,246,0.1)',
  },
  {
    title: 'Rapports & Analytics',
    description: 'Tableaux de bord personnalisés, KPIs en direct et exports automatisés.',
    icon: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="2" y="10" width="3" height="8" rx="1" stroke="currentColor" stroke-width="1.5"/><rect x="8" y="6" width="3" height="12" rx="1" stroke="currentColor" stroke-width="1.5"/><rect x="14" y="2" width="3" height="16" rx="1" stroke="currentColor" stroke-width="1.5"/></svg>',
    iconBg: 'rgba(245,158,11,0.1)',
  },
  {
    title: 'Équipe & Permissions',
    description: 'Invitez votre équipe, définissez les rôles et contrôlez les accès par module.',
    icon: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="6" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M4 18c0-3.3 2.7-6 6-6s6 2.7 6 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14 13l2 2 3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    iconBg: 'rgba(239,68,68,0.1)',
  },
  {
    title: 'Multi-entrepôts',
    description: 'Gérez plusieurs sites, entrepôts ou points de vente depuis une interface unique.',
    icon: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M2 8l8-5 8 5v9H2V8z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M8 17V11h4v6" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>',
    iconBg: 'rgba(16,185,129,0.08)',
  },
]

const steps = [
  {
    title: 'Créez votre espace',
    description: 'Inscription en 30 secondes. Email + mot de passe, c\'est tout. Aucune carte bancaire requise.',
  },
  {
    title: 'Configurez votre activité',
    description: 'Notre wizard intelligent identifie votre secteur et active les modules adaptés automatiquement.',
  },
  {
    title: 'Invitez votre équipe',
    description: 'Ajoutez vos collaborateurs, définissez leurs rôles. Ils sont opérationnels en 2 minutes.',
  },
]

const modules = [
  { title: 'Catalogue produits',    description: 'Gérez produits, variantes, prix et codes-barres.',           icon: '📦', status: 'Disponible' },
  { title: 'Inventaire & Stock',    description: 'Mouvements, alertes, comptages, multi-entrepôts.',            icon: '🏪', status: 'Disponible' },
  { title: 'Commandes & Ventes',    description: 'Cycle complet draft → confirmé → livré.',                    icon: '🛒', status: 'Disponible' },
  { title: 'Clients & CRM',         description: 'Historique, segmentation, fidélisation.',                    icon: '👥', status: 'Bientôt' },
  { title: 'Paiements',             description: 'Cash, mobile money, carte, virement.',                       icon: '💳', status: 'Bientôt' },
  { title: 'Livraisons',            description: 'Suivi des livraisons, transporteurs, tracking.',             icon: '🚚', status: 'Bientôt' },
]

const ctaStats = [
  { value: '30s',    label: 'pour s\'inscrire'       },
  { value: '<5 min', label: 'pour être opérationnel' },
  { value: '30+',    label: 'pays couverts'          },
  { value: '99.9%',  label: 'de disponibilité'       },
]

const faqs = [
  {
    q: 'Nexora ERP est-il adapté aux petites entreprises ?',
    a: 'Absolument. Nexora ERP est conçu pour s\'adapter à toute taille d\'organisation. Vous pouvez commencer seul et inviter votre équipe progressivement. Les modules s\'activent à la demande.',
  },
  {
    q: 'Est-ce que mes données sont sécurisées ?',
    a: 'Oui. Toutes les données sont chiffrées en transit et au repos. Nos serveurs sont hébergés en Europe avec des sauvegardes quotidiennes automatiques. Conformité RGPD intégrée.',
  },
  {
    q: 'Puis-je importer mes données existantes ?',
    a: 'Oui. Nexora ERP supporte l\'import de produits, clients et historiques depuis des fichiers CSV ou Excel. Des connecteurs avec les outils courants sont en cours de développement.',
  },
  {
    q: 'Y a-t-il une période d\'essai gratuite ?',
    a: 'Oui. Vous pouvez démarrer gratuitement et utiliser toutes les fonctionnalités sans limite pendant 30 jours. Aucune carte bancaire requise à l\'inscription.',
  },
]
</script>

<style scoped>
/* ── Base ────────────────────────────────────────────────── */
.landing {
  font-family: var(--font-sans);
  color: var(--gray-800);
  overflow-x: hidden;
}

.container {
  max-width: 1180px;
  margin: 0 auto;
  padding: 0 1.5rem;
}

.accent { color: var(--brand-primary); }

/* ── Navbar ──────────────────────────────────────────────── */
.nav {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
  background: transparent;
  transition: background 0.2s, box-shadow 0.2s;
}

.nav--scrolled {
  background: rgba(255,255,255,0.95);
  backdrop-filter: blur(12px);
  box-shadow: 0 1px 0 var(--gray-200);
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
  gap: 1.75rem;
}

.nav-links a {
  font-size: var(--text-sm);
  font-weight: 500;
  color: var(--gray-600);
  text-decoration: none;
  transition: color 0.15s;
}
.nav-links a:hover { color: var(--gray-900); }

.nav-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

/* ── Section common ──────────────────────────────────────── */
.section-header {
  text-align: center;
  max-width: 640px;
  margin: 0 auto 3.5rem;
}

.section-badge {
  display: inline-block;
  font-size: var(--text-xs);
  font-weight: 700;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--brand-primary);
  background: var(--brand-primary-bg);
  padding: 0.3rem 0.8rem;
  border-radius: 20px;
  margin-bottom: 1rem;
}

.section-header h2 {
  font-size: clamp(1.5rem, 3vw, 2.25rem);
  font-weight: 800;
  color: var(--gray-900);
  line-height: 1.2;
  margin-bottom: 0.75rem;
  letter-spacing: -0.5px;
}

.section-header p {
  font-size: var(--text-base);
  color: var(--gray-500);
  line-height: 1.7;
  margin: 0;
}

/* ── Hero ────────────────────────────────────────────────── */
.hero {
  padding: 140px 0 80px;
  background: linear-gradient(160deg, #f0fdf9 0%, #eff6ff 50%, #f8fafc 100%);
  position: relative;
  overflow: hidden;
}

.hero::before {
  content: '';
  position: absolute;
  top: -200px;
  right: -200px;
  width: 600px;
  height: 600px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(16,185,129,0.12) 0%, transparent 70%);
  pointer-events: none;
}

.hero-inner {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4rem;
  align-items: center;
}

.hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: var(--text-xs);
  font-weight: 700;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  color: var(--brand-primary);
  background: var(--brand-primary-bg);
  border: 1px solid var(--brand-primary-light);
  padding: 0.3rem 0.8rem;
  border-radius: 20px;
  margin-bottom: 1.5rem;
}

.hero-title {
  font-size: clamp(2rem, 4vw, 3rem);
  font-weight: 900;
  color: var(--gray-900);
  line-height: 1.15;
  letter-spacing: -1px;
  margin-bottom: 1.25rem;
}

.hero-title-accent {
  background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.hero-subtitle {
  font-size: var(--text-lg);
  color: var(--gray-600);
  line-height: 1.7;
  margin-bottom: 2rem;
}

.hero-actions {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
  margin-bottom: 2rem;
}

.hero-social-proof {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: var(--text-sm);
  color: var(--gray-500);
}

.proof-avatars {
  display: flex;
}

.proof-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 2px solid white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.65rem;
  font-weight: 700;
  color: white;
  margin-left: -6px;
}
.proof-avatar:first-child { margin-left: 0; }

/* Dashboard mockup */
.hero-visual { position: relative; }

.dashboard-mockup {
  background: white;
  border-radius: var(--radius-xl);
  border: 1px solid var(--gray-200);
  box-shadow: 0 32px 80px rgba(0,0,0,0.12), 0 8px 24px rgba(0,0,0,0.06);
  overflow: hidden;
  transform: perspective(1000px) rotateY(-3deg) rotateX(2deg);
}

.mockup-topbar {
  background: var(--gray-800);
  padding: 0.6rem 1rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.mockup-dots { display: flex; gap: 5px; }
.mockup-dots span {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: rgba(255,255,255,0.3);
}

.mockup-title { font-size: 0.65rem; color: rgba(255,255,255,0.5); }

.mockup-body { padding: 1rem; background: var(--gray-50); }

.mockup-kpis {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}

.mockup-kpi {
  background: white;
  padding: 0.6rem;
  border-radius: 6px;
  border: 1px solid var(--gray-200);
}

.mockup-kpi-value { font-size: 0.75rem; font-weight: 700; line-height: 1.3; }
.mockup-kpi-label { font-size: 0.55rem; color: var(--gray-400); margin-top: 2px; }

.mockup-chart {
  background: white;
  border: 1px solid var(--gray-200);
  border-radius: 6px;
  padding: 0.75rem;
  margin-bottom: 0.75rem;
}

.chart-bars {
  display: flex;
  align-items: flex-end;
  gap: 3px;
  height: 60px;
  margin-bottom: 0.4rem;
}

.chart-bar {
  flex: 1;
  background: linear-gradient(to top, var(--brand-primary), rgba(16,185,129,0.4));
  border-radius: 2px 2px 0 0;
  transition: height 0.3s;
}

.chart-label { font-size: 0.55rem; color: var(--gray-400); text-align: center; }

.mockup-table-rows { display: flex; flex-direction: column; gap: 5px; }
.mockup-row {
  background: white;
  border: 1px solid var(--gray-200);
  border-radius: 4px;
  padding: 0.4rem 0.6rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.row-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--brand-primary); flex-shrink: 0; }
.row-line { height: 6px; border-radius: 3px; background: var(--gray-200); }
.row-line.long { flex: 1; }
.row-line.short { width: 40px; }
.row-badge { width: 32px; height: 12px; border-radius: 6px; background: var(--brand-primary-bg); flex-shrink: 0; }

/* ── Trusted ─────────────────────────────────────────────── */
.trusted {
  padding: 2.5rem 0;
  background: white;
  border-top: 1px solid var(--gray-200);
  border-bottom: 1px solid var(--gray-200);
}

.trusted-label {
  text-align: center;
  font-size: var(--text-xs);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--gray-400);
  margin: 0 0 1.25rem;
}

.trusted-logos {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 2.5rem;
  flex-wrap: wrap;
}

.trusted-logo {
  font-size: var(--text-sm);
  font-weight: 700;
  color: var(--gray-300);
  letter-spacing: 0.5px;
}

/* ── Features ────────────────────────────────────────────── */
.features {
  padding: 6rem 0;
  background: var(--gray-50);
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.5rem;
}

.feature-card {
  background: white;
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-xl);
  padding: 1.75rem;
  transition: transform 0.2s, box-shadow 0.2s;
}

.feature-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-md);
}

.feature-icon {
  width: 44px;
  height: 44px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1rem;
  color: var(--gray-700);
}

.feature-card h3 {
  font-size: var(--text-base);
  font-weight: 700;
  color: var(--gray-900);
  margin-bottom: 0.5rem;
}

.feature-card p {
  font-size: var(--text-sm);
  color: var(--gray-500);
  line-height: 1.6;
  margin: 0;
}

/* ── How it works ────────────────────────────────────────── */
.how {
  padding: 6rem 0;
  background: white;
}

.steps {
  display: flex;
  align-items: flex-start;
  gap: 0;
  position: relative;
}

.step {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 0 1.5rem;
  position: relative;
}

.step-number {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
  color: white;
  font-size: var(--text-lg);
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1rem;
  position: relative;
  z-index: 1;
}

.step-connector {
  position: absolute;
  top: 24px;
  right: -50%;
  width: 100%;
  height: 2px;
  background: linear-gradient(to right, var(--brand-primary-light), var(--brand-primary-light));
  z-index: 0;
}

.step-content h3 {
  font-size: var(--text-base);
  font-weight: 700;
  color: var(--gray-900);
  margin-bottom: 0.5rem;
}

.step-content p {
  font-size: var(--text-sm);
  color: var(--gray-500);
  line-height: 1.6;
  margin: 0;
}

/* ── Modules ─────────────────────────────────────────────── */
.modules {
  padding: 6rem 0;
  background: var(--gray-50);
}

.modules-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.25rem;
}

.module-card {
  background: white;
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-lg);
  padding: 1.5rem;
}

.module-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.75rem;
}

.module-icon { font-size: 1.5rem; }

.module-card h3 {
  font-size: var(--text-sm);
  font-weight: 700;
  color: var(--gray-900);
  margin-bottom: 0.4rem;
}

.module-card p {
  font-size: var(--text-sm);
  color: var(--gray-500);
  line-height: 1.5;
  margin: 0;
}

/* ── CTA section ─────────────────────────────────────────── */
.cta-section {
  padding: 6rem 0;
  background: linear-gradient(135deg, var(--gray-900) 0%, #0d1f2d 100%);
  position: relative;
  overflow: hidden;
}

.cta-section::before {
  content: '';
  position: absolute;
  top: -200px;
  left: -200px;
  width: 500px;
  height: 500px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(16,185,129,0.15) 0%, transparent 70%);
}

.cta-inner {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4rem;
  align-items: center;
  position: relative;
  z-index: 1;
}

.cta-text h2 {
  font-size: clamp(1.75rem, 3.5vw, 2.5rem);
  font-weight: 800;
  color: white;
  line-height: 1.2;
  letter-spacing: -0.5px;
  margin-bottom: 1rem;
}

.cta-text p {
  font-size: var(--text-base);
  color: rgba(255,255,255,0.65);
  line-height: 1.7;
  margin-bottom: 2rem;
}

.cta-actions {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 1rem;
}

.btn-text-white {
  font-size: var(--text-sm);
  color: rgba(255,255,255,0.6);
  text-decoration: none;
  font-weight: 500;
  transition: color 0.15s;
}
.btn-text-white:hover { color: white; }

.cta-stats {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
}

.cta-stat {
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: var(--radius-lg);
  padding: 1.25rem;
}

.cta-stat-value {
  font-size: var(--text-2xl);
  font-weight: 800;
  color: var(--brand-primary);
  margin-bottom: 0.25rem;
}

.cta-stat-label {
  font-size: var(--text-xs);
  color: rgba(255,255,255,0.5);
}

/* ── FAQ ─────────────────────────────────────────────────── */
.faq { padding: 6rem 0; background: white; }
.faq-inner { max-width: 720px; margin: 0 auto; }
.faq-list { display: flex; flex-direction: column; gap: 0.75rem; }

.faq-item {
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-lg);
  overflow: hidden;
  cursor: pointer;
  transition: border-color 0.15s;
}

.faq-item:hover { border-color: var(--brand-primary-light); }
.faq-item.open  { border-color: var(--brand-primary-light); }

.faq-question {
  padding: 1.1rem 1.25rem;
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--gray-800);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  user-select: none;
}

.faq-chevron {
  flex-shrink: 0;
  transition: transform 0.2s;
  color: var(--gray-400);
}
.faq-item.open .faq-chevron { transform: rotate(180deg); color: var(--brand-primary); }

.faq-answer {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.25s ease;
  font-size: var(--text-sm);
  color: var(--gray-600);
  line-height: 1.7;
  padding: 0 1.25rem;
}
.faq-item.open .faq-answer { max-height: 200px; padding-bottom: 1.1rem; }

/* ── Footer ──────────────────────────────────────────────── */
.footer {
  background: var(--gray-900);
  color: rgba(255,255,255,0.6);
}

.footer-inner {
  padding: 3rem 1.5rem 2rem;
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 4rem;
  align-items: start;
}

.footer-brand p {
  font-size: var(--text-sm);
  margin-top: 0.75rem;
  max-width: 240px;
  line-height: 1.6;
}

.footer-links { display: flex; gap: 3rem; }
.footer-col { display: flex; flex-direction: column; gap: 0.5rem; }

.footer-col-title {
  font-size: var(--text-xs);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: rgba(255,255,255,0.4);
  margin-bottom: 0.25rem;
}

.footer-col a {
  font-size: var(--text-sm);
  color: rgba(255,255,255,0.55);
  text-decoration: none;
  transition: color 0.15s;
}
.footer-col a:hover { color: white; }

.footer-bottom {
  border-top: 1px solid rgba(255,255,255,0.08);
  padding: 1.25rem 1.5rem;
}

.footer-bottom .container {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: var(--text-xs);
  color: rgba(255,255,255,0.35);
}

.footer-legal { display: flex; gap: 1.5rem; }
.footer-legal a { color: rgba(255,255,255,0.35); text-decoration: none; }
.footer-legal a:hover { color: rgba(255,255,255,0.6); }

/* ── Responsive ──────────────────────────────────────────── */
@media (max-width: 1024px) {
  .hero-inner { grid-template-columns: 1fr; gap: 3rem; }
  .dashboard-mockup { transform: none; max-width: 600px; margin: 0 auto; }
  .features-grid { grid-template-columns: repeat(2, 1fr); }
  .modules-grid  { grid-template-columns: repeat(2, 1fr); }
  .cta-inner { grid-template-columns: 1fr; gap: 2.5rem; }
  .cta-stats { grid-template-columns: repeat(4, 1fr); }
}

@media (max-width: 768px) {
  .nav-links { display: none; }
  .hero { padding: 110px 0 60px; }
  .hero-title { font-size: 2rem; }
  .hero-actions { flex-direction: column; }
  .hero-actions .btn { width: 100%; justify-content: center; }
  .features-grid { grid-template-columns: 1fr; }
  .modules-grid  { grid-template-columns: 1fr; }
  .steps { flex-direction: column; gap: 2rem; }
  .step-connector { display: none; }
  .mockup-kpis { grid-template-columns: repeat(2, 1fr); }
  .cta-stats { grid-template-columns: repeat(2, 1fr); }
  .footer-inner { grid-template-columns: 1fr; gap: 2rem; }
  .footer-links { flex-direction: column; gap: 1.5rem; }
  .footer-bottom .container { flex-direction: column; gap: 0.5rem; text-align: center; }
  .trusted-logos { gap: 1.5rem; }
}

@media (max-width: 480px) {
  .hero-title { font-size: 1.75rem; }
  .cta-text h2 { font-size: 1.75rem; }
  .cta-stats { grid-template-columns: 1fr 1fr; }
}
</style>
