<template>
  <div>

    <div class="page-header">
      <h2>Paramètres</h2>
      <p class="page-subtitle">Gérez votre espace de travail et vos préférences</p>
    </div>

    <div class="settings-layout">

      <!-- Sidebar nav -->
      <nav class="settings-nav">
        <button
          v-for="tab in visibleTabs"
          :key="tab.id"
          class="settings-nav-item"
          :class="{ active: activeTab === tab.id }"
          @click="activeTab = tab.id"
        >
          <component :is="tab.icon" class="nav-icon" />
          {{ tab.label }}
        </button>
      </nav>

      <!-- Content panels -->
      <div class="settings-panel">

        <!-- Company -->
        <section v-if="activeTab === 'company'">
          <div class="panel-header">
            <h3>Informations entreprise</h3>
            <p>Ces informations apparaissent sur vos factures et documents commerciaux.</p>
          </div>

          <div v-if="companyLoading" class="state-loading">Chargement…</div>

          <form v-else @submit.prevent="saveCompanySettings" class="company-form">

            <div class="form-section">
              <div class="form-section-title">Identité</div>
              <div class="form-row">
                <label>Nom de l'entreprise <span class="req">*</span></label>
                <input v-model="companyForm.name" class="form-input" placeholder="Ma Société SARL" required />
              </div>
              <div class="form-row">
                <label>Domaine personnalisé <span class="hint">(optionnel)</span></label>
                <div class="input-hint-row">
                  <input v-model="companyForm.domain" class="form-input" placeholder="ex. masociete.com" />
                  <span class="input-hint-text">Utilisé pour identifier votre espace de travail</span>
                </div>
              </div>
            </div>

            <div class="form-section">
              <div class="form-section-title">Localisation &amp; coordonnées</div>
              <div class="form-grid-2">
                <div class="form-row">
                  <label>Pays</label>
                  <select v-model="companyForm.country" class="form-select">
                    <option value="">— Sélectionner —</option>
                    <option value="SN">Sénégal</option>
                    <option value="CI">Côte d'Ivoire</option>
                    <option value="CM">Cameroun</option>
                    <option value="ML">Mali</option>
                    <option value="BF">Burkina Faso</option>
                    <option value="GN">Guinée</option>
                    <option value="TG">Togo</option>
                    <option value="BJ">Bénin</option>
                    <option value="NE">Niger</option>
                    <option value="GH">Ghana</option>
                    <option value="NG">Nigeria</option>
                    <option value="MA">Maroc</option>
                    <option value="DZ">Algérie</option>
                    <option value="TN">Tunisie</option>
                  </select>
                </div>
                <div class="form-row">
                  <label>Devise</label>
                  <select v-model="companyForm.currency" class="form-select">
                    <option value="">— Sélectionner —</option>
                    <option value="XOF">CFA BCEAO (XOF)</option>
                    <option value="XAF">CFA BEAC (XAF)</option>
                    <option value="GHS">Cedi ghanéen (GHS)</option>
                    <option value="NGN">Naira nigérian (NGN)</option>
                    <option value="MAD">Dirham marocain (MAD)</option>
                    <option value="DZD">Dinar algérien (DZD)</option>
                  </select>
                </div>
                <div class="form-row">
                  <label>Téléphone</label>
                  <input v-model="companyForm.phone" class="form-input" placeholder="+221 77 000 00 00" />
                </div>
                <div class="form-row">
                  <label>Site web</label>
                  <input v-model="companyForm.website" class="form-input" placeholder="https://masociete.com" />
                </div>
              </div>
              <div class="form-row">
                <label>Adresse</label>
                <textarea v-model="companyForm.address" class="form-textarea" rows="2" placeholder="Adresse complète…" />
              </div>
            </div>

            <!-- Session timeout -->
            <div class="form-section">
              <div class="form-section-title">Sécurité &amp; Sessions</div>
              <div class="form-row">
                <label>Déconnexion automatique par inactivité</label>
                <div class="input-hint-row">
                  <select v-model="companyForm.session_timeout" class="form-select">
                    <option value="30">30 minutes</option>
                    <option value="60">1 heure</option>
                    <option value="240">4 heures</option>
                    <option value="480">8 heures</option>
                    <option value="1440">24 heures (défaut)</option>
                    <option value="10080">7 jours</option>
                    <option value="43200">30 jours</option>
                  </select>
                  <span class="input-hint-text">Durée d'inactivité avant déconnexion automatique de tous les utilisateurs du tenant.</span>
                </div>
              </div>
            </div>

            <div v-if="companyError"   class="form-error">{{ companyError }}</div>
            <div v-if="companySuccess" class="form-success">{{ companySuccess }}</div>

            <div class="form-actions">
              <button type="submit" class="btn btn-primary" :disabled="companySaving">
                {{ companySaving ? 'Enregistrement…' : 'Enregistrer les modifications' }}
              </button>
            </div>

          </form>
        </section>

        <!-- Team -->
        <section v-else-if="activeTab === 'team'">
          <div class="panel-header-row">
            <div>
              <h3>Équipe &amp; permissions</h3>
              <p>
                {{ teamUsers.length }}
                membre{{ teamUsers.length !== 1 ? 's' : '' }} dans votre espace de travail.
              </p>
            </div>
            <button v-if="canManageTeam" class="btn btn-primary btn-sm-pad" @click="openInvite">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <path d="M7 1v12M1 7h12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              </svg>
              Inviter un membre
            </button>
          </div>

          <div v-if="teamLoading" class="state-loading">Chargement…</div>

          <div v-else-if="!teamUsers.length" class="coming-soon">
            <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
              <rect x="4" y="4" width="32" height="32" rx="8" fill="var(--brand-secondary-light)"/>
              <circle cx="20" cy="17" r="5" stroke="var(--brand-secondary)" stroke-width="2"/>
              <path d="M9 32c0-6.075 4.925-11 11-11s11 4.925 11 11" stroke="var(--brand-secondary)" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <p>Aucun membre pour l'instant. Invitez votre équipe pour collaborer.</p>
          </div>

          <div v-else class="team-table-wrap">
            <table class="team-table">
              <thead>
                <tr>
                  <th>Membre</th>
                  <th>Rôle</th>
                  <th>Statut</th>
                  <th>Ajouté le</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="u in teamUsers"
                  :key="u.id"
                  :class="{ 'row-inactive': !u.is_active }"
                >
                  <td>
                    <div class="user-cell">
                      <div class="user-avatar" :class="{ 'avatar-inactive': !u.is_active }">
                        {{ initials(u.name) }}
                      </div>
                      <div>
                        <div class="user-name">
                          {{ u.name }}
                          <span v-if="u.id === auth.user?.id" class="self-tag">vous</span>
                        </div>
                        <div class="user-email">{{ u.email }}</div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <select
                      v-if="canManageTeam && u.id !== auth.user?.id"
                      :value="u.roles[0] ?? 'member'"
                      class="role-select"
                      @change="changeRole(u, ($event.target as HTMLSelectElement).value)"
                    >
                      <option value="admin">Admin</option>
                      <option value="manager">Manager</option>
                      <option value="member">Membre</option>
                      <option value="viewer">Lecteur</option>
                      <optgroup v-if="customRoles.length" label="Rôles personnalisés">
                        <option v-for="r in customRoles" :key="r.id" :value="r.name">{{ r.name }}</option>
                      </optgroup>
                    </select>
                    <span
                      v-else
                      class="role-badge"
                      :class="`role-${u.roles[0] ?? 'member'}`"
                    >{{ roleLabel(u.roles[0]) }}</span>
                  </td>
                  <td>
                    <span
                      class="team-status-badge"
                      :class="u.is_active ? 'status-active' : 'status-inactive'"
                    >
                      {{ u.is_active ? 'Actif' : 'Inactif' }}
                    </span>
                  </td>
                  <td class="team-date">{{ teamFmtDate(u.created_at) }}</td>
                  <td>
                    <template v-if="canManageTeam && u.id !== auth.user?.id">
                      <button
                        class="btn-toggle-user"
                        :class="{ 'btn-reactivate': !u.is_active }"
                        @click="toggleUser(u)"
                      >{{ u.is_active ? 'Désactiver' : 'Réactiver' }}</button>
                      <button
                        v-if="!isManagerRole(u)"
                        class="btn-toggle-user"
                        style="margin-left:0.4rem"
                        title="Restreindre l'accès à certains entrepôts (multi-sites)"
                        @click="openWarehouseModal(u)"
                      >Sites{{ u.warehouse_ids?.length ? ` (${u.warehouse_ids.length})` : '' }}</button>
                      <button
                        class="btn-toggle-user"
                        style="margin-left:0.4rem"
                        title="Accorder un accès temporaire (rôle à durée limitée)"
                        @click="openTempModal(u)"
                      >Accès temp.{{ u.temporary_access?.length ? ` (${u.temporary_access.length})` : '' }}</button>
                    </template>
                    <span v-else class="team-date">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Roles (admin only) -->
        <section v-else-if="activeTab === 'roles'">
          <RolesPanel />
        </section>

        <!-- Billing -->
        <section v-else-if="activeTab === 'billing'">
          <div class="panel-header">
            <h3>Abonnement & facturation</h3>
            <p>Votre plan actuel et options de mise à niveau.</p>
          </div>

          <!-- No subscription -->
          <div v-if="!auth.user?.subscription" class="coming-soon">
            <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
              <rect x="4" y="4" width="32" height="32" rx="8" fill="var(--brand-primary-bg)"/>
              <path d="M4 16h32M10 10h4M18 10h4" stroke="var(--brand-primary)" stroke-width="2" stroke-linecap="round"/>
              <rect x="8" y="22" width="8" height="4" rx="1" fill="var(--brand-primary)" opacity=".5"/>
            </svg>
            <p>Aucun abonnement actif pour le moment.</p>
          </div>

          <!-- Live plan card -->
          <div v-else class="plan-card">

            <div class="plan-card-top">
              <div>
                <div class="plan-badge">Plan actuel</div>
                <div class="plan-name">{{ auth.user.subscription.plan_name }}</div>
              </div>
              <span :class="subStatusBadgeClass(auth.user.subscription.status)" class="badge">
                {{ subStatusLabel(auth.user.subscription.status) }}
              </span>
            </div>

            <div class="plan-price" v-if="auth.user.subscription.plan_price_monthly">
              {{ formatPrice(auth.user.subscription.plan_price_monthly, auth.user.subscription.currency) }}
              <span>/ mois</span>
            </div>
            <div class="plan-price" v-else>
              Gratuit <span>pendant la période de bêta</span>
            </div>

            <!-- Trial / renewal info -->
            <div v-if="trialDaysLeft !== null" class="plan-trial-info">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.5"/>
                <path d="M7 4v3.5L9 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
              Essai gratuit — <strong>{{ trialDaysLeft }} jour{{ trialDaysLeft > 1 ? 's' : '' }}</strong> restant{{ trialDaysLeft > 1 ? 's' : '' }}
            </div>
            <div v-else-if="auth.user.subscription.current_period_end" class="plan-renewal">
              Renouvellement le {{ formatPeriodDate(auth.user.subscription.current_period_end) }}
            </div>

            <!-- Usage limits -->
            <div v-if="hasLimits" class="plan-limits">
              <div v-if="auth.user.subscription.max_users" class="plan-limit-row">
                <span>Utilisateurs max</span>
                <strong>{{ auth.user.subscription.max_users }}</strong>
              </div>
              <div v-if="auth.user.subscription.max_products" class="plan-limit-row">
                <span>Produits max</span>
                <strong>{{ auth.user.subscription.max_products }}</strong>
              </div>
              <div v-if="auth.user.subscription.max_monthly_orders" class="plan-limit-row">
                <span>Commandes / mois</span>
                <strong>{{ auth.user.subscription.max_monthly_orders }}</strong>
              </div>
            </div>

            <!-- Features -->
            <ul v-if="auth.user.subscription.features?.length" class="plan-features">
              <li v-for="feat in auth.user.subscription.features" :key="feat">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                  <path d="M3 8l4 4 6-7" stroke="var(--brand-primary)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                {{ feat }}
              </li>
            </ul>

          </div>

          <!-- Promo code section -->
          <div class="promo-section">
            <div class="promo-section-title">Code promotionnel</div>
            <div class="promo-input-row">
              <input
                v-model="promoCode"
                class="promo-input"
                placeholder="Entrez votre code…"
                :disabled="promoApplied"
                @keyup.enter="checkPromo"
                style="text-transform:uppercase"
              />
              <button
                v-if="!promoApplied"
                class="btn promo-btn"
                :disabled="promoLoading || !promoCode"
                @click="checkPromo"
              >
                {{ promoLoading ? '…' : 'Valider' }}
              </button>
              <span v-else class="promo-success-chip">✓ Appliqué</span>
            </div>
            <div v-if="promoFeedback" class="promo-feedback" :class="promoApplied ? 'promo-feedback--ok' : 'promo-feedback--err'">
              {{ promoFeedback }}
            </div>
          </div>

          <!-- Upgrade CTA -->
          <div class="upgrade-cta">
            <button class="btn btn-primary upgrade-btn" @click="upgradeModal.open = true">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <path d="M8 2v12M3 7l5-5 5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              Demander une mise à niveau de plan
            </button>
          </div>

        </section>

        <!-- Integrations -->
        <section v-else-if="activeTab === 'integrations'">
          <div class="panel-header">
            <h3>Intégrations</h3>
            <p>Connectez Frynov ERP à vos outils existants.</p>
          </div>
          <div style="text-align:center;padding:40px;color:#94a3b8;">
            <p style="font-size:0.95rem;">Fonctionnalite bientot disponible.</p>
          </div>
        </section>

        <!-- Notifications -->
        <section v-else-if="activeTab === 'notifications'">
          <div class="panel-header">
            <h3>Notifications</h3>
            <p>Configurez vos alertes et rappels.</p>
          </div>
          <div style="text-align:center;padding:40px;color:#94a3b8;">
            <p style="font-size:0.95rem;">Fonctionnalite bientot disponible.</p>
          </div>
        </section>

      </div>
    </div>

    <!-- ── Invite team member modal ──────────────────────────────────────── -->
    <div v-if="inviteModal.open" class="modal-overlay" @click.self="closeInvite">
      <div class="modal" role="dialog" aria-modal="true" v-focus-trap>
        <div class="modal-header">
          <h3>Inviter un membre</h3>
          <button class="modal-close" @click="closeInvite">✕</button>
        </div>
        <div class="modal-body">
          <!-- After success: show temp password -->
          <div v-if="inviteModal.success" class="invite-success">
            <div class="invite-success-msg">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <circle cx="8" cy="8" r="7" fill="#dcfce7" stroke="#16a34a" stroke-width="1.2"/>
                <path d="M5 8l2.5 2.5L11 5.5" stroke="#16a34a" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              {{ inviteModal.success }}
            </div>
            <div class="temp-password-box">
              <div class="temp-password-label">Mot de passe temporaire</div>
              <div class="temp-password-row">
                <code class="temp-password-value">{{ inviteModal.tempPassword }}</code>
                <button class="btn-copy" @click="copyPassword">{{ copiedPwd ? '✓ Copié' : 'Copier' }}</button>
              </div>
            </div>
            <p class="invite-hint">
              Communiquez ce mot de passe à l'utilisateur — il pourra le changer depuis son profil.
            </p>
          </div>
          <!-- Invite form -->
          <template v-else>
            <div class="form-row">
              <label>Nom complet <span class="req">*</span></label>
              <input v-model="inviteForm.name" class="form-input" placeholder="Marie Dupont" />
            </div>
            <div class="form-row">
              <label>Adresse email <span class="req">*</span></label>
              <input v-model="inviteForm.email" type="email" class="form-input" placeholder="marie@entreprise.com" />
            </div>
            <div class="form-row">
              <label>Rôle <span class="req">*</span></label>
              <select v-model="inviteForm.role" class="form-select">
                <option value="">— Sélectionner un rôle —</option>
                <option value="manager">Manager — gestion équipe &amp; données</option>
                <option value="member">Membre — accès complet aux modules</option>
                <option value="viewer">Lecteur — consultation uniquement</option>
                <optgroup v-if="customRoles.length" label="Rôles personnalisés">
                  <option v-for="r in customRoles" :key="r.id" :value="r.name">{{ r.name }}</option>
                </optgroup>
              </select>
            </div>
            <div v-if="inviteModal.error" class="form-error">{{ inviteModal.error }}</div>
          </template>
        </div>
        <div class="modal-footer">
          <button class="btn-cancel" @click="closeInvite">
            {{ inviteModal.success ? 'Fermer' : 'Annuler' }}
          </button>
          <button
            v-if="!inviteModal.success"
            class="btn-submit"
            :disabled="inviteModal.saving || !inviteForm.name || !inviteForm.email || !inviteForm.role"
            @click="submitInvite"
          >{{ inviteModal.saving ? 'Ajout…' : 'Ajouter le membre' }}</button>
        </div>
      </div>
    </div>

    <!-- ── Member site/warehouse access modal (multi-sites) ───────────────── -->
    <div v-if="whModal.open" class="modal-overlay" @click.self="whModal.open = false">
      <div class="modal" role="dialog" aria-modal="true" v-focus-trap>
        <div class="modal-header">
          <h3>Accès aux entrepôts — {{ whModal.userName }}</h3>
          <button class="modal-close" @click="whModal.open = false">✕</button>
        </div>
        <div class="modal-body">
          <p class="modal-desc">
            Cochez les entrepôts auxquels ce membre a accès. <strong>Aucune sélection = accès à tous les sites.</strong>
            Les managers et admins voient toujours tous les sites.
          </p>
          <div v-if="whModal.error" class="form-error">{{ whModal.error }}</div>
          <label v-for="w in warehouses" :key="w.id" style="display:flex; align-items:center; gap:0.5rem; padding:0.35rem 0; font-size:0.9rem; cursor:pointer;">
            <input v-model="whModal.selected" type="checkbox" :value="w.id" />
            {{ w.is_default ? '⭐ ' : '' }}{{ w.name }} <span style="color:#94a3b8; font-size:0.8rem;">{{ w.code }}</span>
          </label>
          <p v-if="!warehouses.length" class="modal-desc">Aucun entrepôt. Créez-en dans Stock &amp; Inventaire → Entrepôts.</p>
        </div>
        <div class="modal-footer">
          <button class="btn-cancel" @click="whModal.open = false">Annuler</button>
          <button class="btn-submit" :disabled="whModal.saving" @click="saveWarehouses">
            {{ whModal.saving ? 'Enregistrement…' : 'Enregistrer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── Member temporary access modal (auto-expiring role) ─────────────── -->
    <div v-if="tempModal.open" class="modal-overlay" @click.self="tempModal.open = false">
      <div class="modal" role="dialog" aria-modal="true" v-focus-trap>
        <div class="modal-header">
          <h3>Accès temporaire — {{ tempModal.userName }}</h3>
          <button class="modal-close" @click="tempModal.open = false">✕</button>
        </div>
        <div class="modal-body">
          <p class="modal-desc">Accordez un rôle à durée limitée. Il est <strong>révoqué automatiquement</strong> à l'échéance, sans action manuelle.</p>
          <div v-if="tempModal.error" class="form-error">{{ tempModal.error }}</div>

          <div v-if="tempModal.grants.length" style="margin-bottom:0.75rem; display:flex; flex-direction:column; gap:0.35rem;">
            <div v-for="g in tempModal.grants" :key="g.id" style="display:flex; justify-content:space-between; align-items:center; font-size:0.85rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:0.4rem 0.6rem;">
              <span>{{ roleLabel(g.role) }} — jusqu'au {{ teamFmtDate(g.expires_at) }}</span>
              <button style="background:none; border:0; color:#dc2626; font-weight:600; cursor:pointer;" @click="revokeTemp(g.id)">Révoquer</button>
            </div>
          </div>

          <div class="form-row"><label>Rôle *</label>
            <select v-model="tempForm.role" class="form-select">
              <option value="manager">Manager</option>
              <option value="member">Membre</option>
              <option value="viewer">Lecteur</option>
              <option value="cashier">Caissier</option>
              <option value="agent">Agent</option>
              <option value="delivery">Livreur</option>
            </select>
          </div>
          <div class="form-row"><label>Expire le *</label>
            <input v-model="tempForm.expires_at" type="datetime-local" class="form-input" />
          </div>
          <div class="form-row"><label>Note</label>
            <input v-model="tempForm.note" type="text" class="form-input" placeholder="Optionnel" />
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-cancel" @click="tempModal.open = false">Fermer</button>
          <button class="btn-submit" :disabled="tempModal.saving || !tempForm.expires_at" @click="grantTemp">
            {{ tempModal.saving ? 'Attribution…' : 'Accorder l’accès' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── Upgrade / payment proof modal (teleported to root level) ───────── -->
    <div v-if="upgradeModal.open" class="modal-overlay" @click.self="upgradeModal.open = false">
      <div class="modal" role="dialog" aria-modal="true" v-focus-trap>
        <div class="modal-header">
          <h3>Demande de mise à niveau</h3>
          <button class="modal-close" @click="upgradeModal.open = false">✕</button>
        </div>
        <div class="modal-body">
          <p class="modal-desc">
            Soumettez votre preuve de paiement. Un administrateur validera votre demande dans les 24h.
          </p>
          <div class="form-row">
            <label>Plan souhaité *</label>
            <select v-model="upgradeForm.plan_code" class="form-select">
              <option value="">— Sélectionner —</option>
              <option value="pro">Pro</option>
              <option value="enterprise">Enterprise</option>
            </select>
          </div>
          <div class="form-row">
            <label>Méthode de paiement *</label>
            <select v-model="upgradeForm.payment_method" class="form-select">
              <option value="">— Sélectionner —</option>
              <option value="orange_money">Orange Money</option>
              <option value="wave">Wave</option>
              <option value="mtn_money">MTN Money</option>
              <option value="moov_money">Moov Money</option>
              <option value="bank_transfer">Virement bancaire</option>
            </select>
          </div>
          <div class="form-row">
            <label>Montant payé (FCFA) *</label>
            <input v-model.number="upgradeForm.amount_fcfa" type="number" min="1" class="form-input" placeholder="ex. 15000" />
          </div>
          <div class="form-row">
            <label>Preuve de paiement <span class="hint">(photo ou PDF, 5 Mo max)</span></label>
            <input
              type="file"
              accept=".jpg,.jpeg,.png,.pdf,.webp"
              class="form-file"
              @change="onProofFileChange"
            />
            <span v-if="upgradeForm.proofFile" class="file-selected">{{ upgradeForm.proofFile.name }}</span>
          </div>
          <div class="form-row">
            <label>Code promo <span class="hint">(optionnel)</span></label>
            <input v-model="upgradeForm.promo_code" class="form-input" placeholder="ex. PROMO20" style="text-transform:uppercase" />
          </div>
          <div class="form-row">
            <label>Notes <span class="hint">(optionnel)</span></label>
            <textarea v-model="upgradeForm.notes" class="form-textarea" rows="2" placeholder="Référence de transaction, remarques…"></textarea>
          </div>
          <div v-if="upgradeModal.error" class="form-error">{{ upgradeModal.error }}</div>
          <div v-if="upgradeModal.success" class="form-success">{{ upgradeModal.success }}</div>
        </div>
        <div class="modal-footer">
          <button class="btn-cancel" @click="upgradeModal.open = false">Annuler</button>
          <button class="btn-submit" :disabled="upgradeModal.saving" @click="submitUpgrade">
            {{ upgradeModal.saving ? 'Envoi…' : 'Soumettre la demande' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, reactive, watch, defineComponent, h } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { authService } from '@/modules/auth/services/authService'
import { roleService, type TenantRole } from '@/modules/settings/services/roleService'
import RolesPanel from '@/modules/settings/components/RolesPanel.vue'
import { useWarehouses } from '@/composables/useWarehouses'
import type { WorkspaceUser } from '@/modules/auth/types'

const activeTab = ref('company')
const auth      = useAuthStore()

// ── Company settings ──────────────────────────────────────────────────────────
const companyLoading = ref(false)
const companyLoaded  = ref(false)
const companySaving  = ref(false)
const companySuccess = ref('')
const companyError   = ref('')

const companyForm = reactive({
  name:            '',
  domain:          '',
  country:         '',
  currency:        '',
  phone:           '',
  address:         '',
  website:         '',
  session_timeout: '1440', // minutes — default 24h
})

async function loadCompanySettings() {
  companyLoading.value = true
  try {
    const result = await authService.getWorkspaceSettings()
    companyForm.name     = result.name ?? ''
    companyForm.domain   = result.domain ?? ''
    companyForm.country  = String(result.settings?.country ?? '')
    companyForm.currency = String(result.settings?.currency ?? '')
    companyForm.phone    = String(result.settings?.phone ?? '')
    companyForm.address  = String(result.settings?.address ?? '')
    companyForm.website          = String(result.settings?.website ?? '')
    companyForm.session_timeout  = String(result.settings?.session_timeout_minutes ?? 1440)
    companyLoaded.value  = true
  } finally {
    companyLoading.value = false
  }
}

async function saveCompanySettings() {
  companySuccess.value = ''
  companyError.value   = ''
  companySaving.value  = true
  try {
    await authService.updateWorkspaceSettings({
      name:   companyForm.name,
      domain: companyForm.domain || null,
      settings: {
        country:  companyForm.country  || undefined,
        currency:                companyForm.currency                || undefined,
        phone:                   companyForm.phone                   || undefined,
        address:                 companyForm.address                 || undefined,
        website:                 companyForm.website                 || undefined,
        session_timeout_minutes: Number(companyForm.session_timeout) || 1440,
      },
    })
    companySuccess.value = 'Modifications enregistrées avec succès.'
    if (auth.user?.tenant) auth.user.tenant.name = companyForm.name
  } catch (err: any) {
    companyError.value = err?.response?.data?.message ?? 'Erreur lors de la sauvegarde.'
  } finally {
    companySaving.value = false
  }
}

// ── Team management ───────────────────────────────────────────────────────────
const teamUsers   = ref<WorkspaceUser[]>([])
const teamLoading = ref(false)
const teamLoaded  = ref(false)

const canManageTeam = computed(() =>
  auth.user?.roles?.some(r => ['admin', 'manager'].includes(r)) ?? false
)

// Only tenant-admins manage custom roles (GET /workspace/roles is admin-only).
const isAdmin = computed(() => auth.user?.roles?.includes('admin') ?? false)

// Custom roles for the team/invite selectors. Loaded with the team tab; for
// managers (who cannot read the roles catalogue) this stays empty — base roles only.
const tenantRoles = ref<TenantRole[]>([])
const customRoles = computed(() => tenantRoles.value.filter(r => r.is_custom))

async function loadTenantRoles() {
  try {
    tenantRoles.value = (await roleService.list()).data
  } catch {
    tenantRoles.value = [] // 403 for managers, or roles endpoint unavailable
  }
}

const inviteModal = reactive({
  open:        false,
  saving:      false,
  error:       '',
  success:     '',
  tempPassword: '',
})

const inviteForm = reactive({ name: '', email: '', role: '' })
const copiedPwd  = ref(false)

// ── Member warehouse access (multi-sites) ─────────────────────────────────────
const { warehouses, loadWarehouses } = useWarehouses()
const whModal = reactive({ open: false, saving: false, error: '', userId: '', userName: '', selected: [] as string[] })

function isManagerRole(user: WorkspaceUser): boolean {
  return user.roles.some(r => ['admin', 'manager'].includes(r))
}

function openWarehouseModal(user: WorkspaceUser) {
  whModal.userId   = user.id
  whModal.userName = user.name
  whModal.selected = [...(user.warehouse_ids ?? [])]
  whModal.error    = ''
  whModal.open     = true
  void loadWarehouses()
}

async function saveWarehouses() {
  whModal.saving = true
  whModal.error  = ''
  try {
    const updated = await authService.setUserWarehouses(whModal.userId, whModal.selected)
    const idx = teamUsers.value.findIndex(u => u.id === whModal.userId)
    if (idx !== -1) teamUsers.value[idx] = updated
    whModal.open = false
  } catch (err: any) {
    whModal.error = err?.response?.data?.message ?? 'Enregistrement impossible.'
  } finally {
    whModal.saving = false
  }
}

// ── Member temporary access (auto-expiring role) ──────────────────────────────
const tempModal = reactive({ open: false, saving: false, error: '', userId: '', userName: '', grants: [] as Array<{ id: string; role: string; expires_at: string }> })
const tempForm = reactive({ role: 'manager', expires_at: '', note: '' })

function openTempModal(user: WorkspaceUser) {
  tempModal.userId = user.id
  tempModal.userName = user.name
  tempModal.grants = [...(user.temporary_access ?? [])]
  tempModal.error = ''
  Object.assign(tempForm, { role: 'manager', expires_at: '', note: '' })
  tempModal.open = true
}

async function grantTemp() {
  tempModal.saving = true
  tempModal.error = ''
  try {
    const iso = new Date(tempForm.expires_at).toISOString()
    await authService.grantTemporaryAccess(tempModal.userId, { role: tempForm.role, expires_at: iso, note: tempForm.note || undefined })
    await loadTeamUsers()
    tempModal.grants = [...(teamUsers.value.find(u => u.id === tempModal.userId)?.temporary_access ?? [])]
    Object.assign(tempForm, { role: 'manager', expires_at: '', note: '' })
  } catch (e: any) {
    tempModal.error = e?.response?.data?.message
      ?? (Object.values(e?.response?.data?.errors ?? {})?.[0] as string[] | undefined)?.[0]
      ?? 'Attribution impossible.'
  } finally {
    tempModal.saving = false
  }
}

async function revokeTemp(grantId: string) {
  try {
    await authService.revokeTemporaryAccess(grantId)
    await loadTeamUsers()
    tempModal.grants = [...(teamUsers.value.find(u => u.id === tempModal.userId)?.temporary_access ?? [])]
  } catch (e: any) {
    tempModal.error = e?.response?.data?.message ?? 'Révocation impossible.'
  }
}

async function loadTeamUsers() {
  teamLoading.value = true
  try {
    teamUsers.value = await authService.getWorkspaceUsers()
    teamLoaded.value = true
  } finally {
    teamLoading.value = false
  }
}

function openInvite() {
  Object.assign(inviteForm, { name: '', email: '', role: '' })
  Object.assign(inviteModal, { open: true, saving: false, error: '', success: '', tempPassword: '' })
}

function closeInvite() {
  if (inviteModal.success) loadTeamUsers()
  inviteModal.open = false
}

async function submitInvite() {
  inviteModal.error  = ''
  inviteModal.saving = true
  try {
    const result = await authService.inviteUser({
      name: inviteForm.name,
      email: inviteForm.email,
      role: inviteForm.role,
    })
    inviteModal.success      = result.message
    inviteModal.tempPassword = result.temp_password
    await loadTeamUsers()
  } catch (err: any) {
    inviteModal.error = err?.response?.data?.message ?? "Erreur lors de l'invitation."
  } finally {
    inviteModal.saving = false
  }
}

async function changeRole(user: WorkspaceUser, newRole: string) {
  if (!newRole || newRole === user.roles[0]) return
  try {
    const updated = await authService.updateUser(user.id, { role: newRole })
    const idx = teamUsers.value.findIndex(u => u.id === user.id)
    if (idx !== -1) teamUsers.value[idx] = updated
  } catch (err: any) {
    alert(err?.response?.data?.message ?? 'Erreur lors du changement de rôle.')
    await loadTeamUsers()
  }
}

async function toggleUser(user: WorkspaceUser) {
  const action = user.is_active ? 'désactiver' : 'réactiver'
  if (!confirm(`Voulez-vous ${action} ${user.name} ?`)) return
  try {
    const result = await authService.toggleUser(user.id)
    const idx = teamUsers.value.findIndex(u => u.id === user.id)
    if (idx !== -1) teamUsers.value[idx] = result.data
  } catch (err: any) {
    alert(err?.response?.data?.message ?? 'Erreur.')
  }
}

function copyPassword() {
  navigator.clipboard.writeText(inviteModal.tempPassword)
  copiedPwd.value = true
  setTimeout(() => { copiedPwd.value = false }, 2000)
}

function initials(name: string): string {
  return name.split(' ').slice(0, 2).map(w => w[0]?.toUpperCase() ?? '').join('')
}

function roleLabel(role: string): string {
  const m: Record<string, string> = { admin: 'Admin', manager: 'Manager', member: 'Membre', viewer: 'Lecteur' }
  return m[role] ?? role
}

function teamFmtDate(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
}

// Lazy-load each tab on first activation
watch(activeTab, tab => {
  if (tab === 'company' && !companyLoaded.value) loadCompanySettings()
  if (tab === 'team') {
    if (!teamLoaded.value) loadTeamUsers()
    if (!tenantRoles.value.length) loadTenantRoles() // custom roles for the role selectors
  }
}, { immediate: true })

// ── Promo code ────────────────────────────────────────────────────────────────
const promoCode     = ref('')
const promoLoading  = ref(false)
const promoApplied  = ref(false)
const promoFeedback = ref('')

async function checkPromo() {
  if (!promoCode.value) return
  promoLoading.value = true
  promoFeedback.value = ''

  const result = await authService.validatePromo(
    promoCode.value,
    auth.user?.subscription?.plan_code,
  )

  if (result.valid) {
    try {
      await authService.applyPromo(promoCode.value, auth.user?.subscription?.plan_code)
      promoApplied.value  = true
      const label = result.discount_type === 'percent'
        ? `${result.discount_value} %`
        : `${result.discount_value} centimes`
      promoFeedback.value = `Code appliqué ! Réduction de ${label}.`
    } catch (err: any) {
      promoFeedback.value = err?.response?.data?.message ?? 'Erreur lors de l\'application.'
    }
  } else {
    promoFeedback.value = result.message ?? 'Code invalide.'
  }

  promoLoading.value = false
}

// ── Plan upgrade modal ────────────────────────────────────────────────────────
const upgradeModal = reactive({
  open:    false,
  saving:  false,
  error:   '',
  success: '',
})

const upgradeForm = reactive({
  plan_code:      '',
  payment_method: '',
  amount_fcfa:    '' as number | '',
  promo_code:     '',
  notes:          '',
  proofFile:      null as File | null,
})

function onProofFileChange(e: Event) {
  const input = e.target as HTMLInputElement
  upgradeForm.proofFile = input.files?.[0] ?? null
}

async function submitUpgrade() {
  upgradeModal.error   = ''
  upgradeModal.success = ''

  if (!upgradeForm.plan_code)      { upgradeModal.error = 'Sélectionnez un plan.'; return }
  if (!upgradeForm.payment_method) { upgradeModal.error = 'Sélectionnez une méthode de paiement.'; return }
  if (!upgradeForm.amount_fcfa)    { upgradeModal.error = 'Entrez le montant payé.'; return }

  upgradeModal.saving = true

  const fd = new FormData()
  fd.append('plan_code',      upgradeForm.plan_code)
  fd.append('payment_method', upgradeForm.payment_method)
  // amount_cents convention = value × 100 everywhere (admin & billing views divide
  // by 100 to display). Sending the raw FCFA value made manual payments show 1/100th.
  fd.append('amount_cents',   String(Math.round(Number(upgradeForm.amount_fcfa) * 100)))
  fd.append('currency',       'XOF')
  if (upgradeForm.promo_code) fd.append('promo_code', upgradeForm.promo_code.toUpperCase())
  if (upgradeForm.notes)      fd.append('notes', upgradeForm.notes)
  if (upgradeForm.proofFile)  fd.append('proof', upgradeForm.proofFile)

  try {
    // Import client for multipart upload
    const client = (await import('@/api/client')).default
    await client.post('/api/me/manual-payments', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    upgradeModal.success = 'Demande soumise avec succès ! Un administrateur va vérifier votre paiement.'
    // Reset form
    Object.assign(upgradeForm, {
      plan_code: '', payment_method: '', amount_fcfa: '',
      promo_code: '', notes: '', proofFile: null,
    })
  } catch (err: any) {
    upgradeModal.error = err?.response?.data?.message ?? 'Une erreur est survenue.'
  } finally {
    upgradeModal.saving = false
  }
}

// ── Subscription helpers ──────────────────────────────────────────────────────

const trialDaysLeft = computed(() => {
  const end = auth.user?.subscription?.trial_ends_at
  if (!end) return null
  const diff = Math.ceil((new Date(end).getTime() - Date.now()) / 86_400_000)
  return diff > 0 ? diff : null
})

const hasLimits = computed(() => {
  const s = auth.user?.subscription
  return s && (s.max_users || s.max_products || s.max_monthly_orders)
})

function formatPrice(cents: number | null | undefined, currency = 'XOF'): string {
  if (!cents) return 'Gratuit'
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(cents / 100)
}

function formatPeriodDate(iso: string | null | undefined): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
}

function subStatusLabel(status: string): string {
  const m: Record<string, string> = {
    trialing:         'Essai',
    active:           'Actif',
    suspended:        'Suspendu',
    cancelled:        'Annulé',
    pending_approval: 'En attente',
  }
  return m[status] ?? status
}

function subStatusBadgeClass(status: string): string {
  const m: Record<string, string> = {
    trialing:         'badge-blue',
    active:           'badge-green',
    suspended:        'badge-red',
    cancelled:        'badge-gray',
    pending_approval: 'badge-amber',
  }
  return m[status] ?? 'badge-gray'
}

// Icon components
const IconCompany = defineComponent({
  render: () => h('svg', { width: 16, height: 16, viewBox: '0 0 16 16', fill: 'none' }, [
    h('path', { d: 'M2 14V5l6-3 6 3v9H2z', stroke: 'currentColor', 'stroke-width': '1.4', 'stroke-linejoin': 'round' }),
    h('path', { d: 'M6 14v-4h4v4', stroke: 'currentColor', 'stroke-width': '1.4', 'stroke-linejoin': 'round' }),
  ]),
})

const IconTeam = defineComponent({
  render: () => h('svg', { width: 16, height: 16, viewBox: '0 0 16 16', fill: 'none' }, [
    h('circle', { cx: 6, cy: 6, r: 2.5, stroke: 'currentColor', 'stroke-width': '1.4' }),
    h('path', { d: 'M2 14c0-2.21 1.79-4 4-4s4 1.79 4 4', stroke: 'currentColor', 'stroke-width': '1.4', 'stroke-linecap': 'round' }),
    h('path', { d: 'M11 4.5a2 2 0 010 4M13.5 14c0-1.93-1.27-3.57-3-4.09', stroke: 'currentColor', 'stroke-width': '1.4', 'stroke-linecap': 'round' }),
  ]),
})

const IconRoles = defineComponent({
  render: () => h('svg', { width: 16, height: 16, viewBox: '0 0 16 16', fill: 'none' }, [
    h('path', { d: 'M8 1.5l5.5 2.2v3.4c0 3.2-2.3 5.5-5.5 6.4-3.2-.9-5.5-3.2-5.5-6.4V3.7L8 1.5z', stroke: 'currentColor', 'stroke-width': '1.4', 'stroke-linejoin': 'round' }),
    h('path', { d: 'M5.8 8l1.6 1.6L10.4 6.4', stroke: 'currentColor', 'stroke-width': '1.4', 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }),
  ]),
})

const IconBilling = defineComponent({
  render: () => h('svg', { width: 16, height: 16, viewBox: '0 0 16 16', fill: 'none' }, [
    h('rect', { x: 1, y: 4, width: 14, height: 9, rx: 2, stroke: 'currentColor', 'stroke-width': '1.4' }),
    h('path', { d: 'M1 7h14', stroke: 'currentColor', 'stroke-width': '1.4' }),
    h('path', { d: 'M4 10.5h2M10 10.5h2', stroke: 'currentColor', 'stroke-width': '1.4', 'stroke-linecap': 'round' }),
  ]),
})

const IconIntegrations = defineComponent({
  render: () => h('svg', { width: 16, height: 16, viewBox: '0 0 16 16', fill: 'none' }, [
    h('circle', { cx: 4, cy: 8, r: 2, stroke: 'currentColor', 'stroke-width': '1.4' }),
    h('circle', { cx: 12, cy: 8, r: 2, stroke: 'currentColor', 'stroke-width': '1.4' }),
    h('path', { d: 'M6 8h4', stroke: 'currentColor', 'stroke-width': '1.4' }),
  ]),
})

const IconNotifications = defineComponent({
  render: () => h('svg', { width: 16, height: 16, viewBox: '0 0 16 16', fill: 'none' }, [
    h('path', { d: 'M8 2a5 5 0 015 5v3l1.5 2H1.5L3 10V7a5 5 0 015-5z', stroke: 'currentColor', 'stroke-width': '1.4', 'stroke-linejoin': 'round' }),
    h('path', { d: 'M6.5 13a1.5 1.5 0 003 0', stroke: 'currentColor', 'stroke-width': '1.4', 'stroke-linecap': 'round' }),
  ]),
})

const tabs = [
  { id: 'company',       label: 'Entreprise',    icon: IconCompany },
  { id: 'team',          label: 'Équipe',         icon: IconTeam },
  { id: 'roles',         label: 'Rôles',          icon: IconRoles },
  { id: 'billing',       label: 'Abonnement',     icon: IconBilling },
  { id: 'integrations',  label: 'Intégrations (bientot)',   icon: IconIntegrations },
  { id: 'notifications', label: 'Notifications (bientot)',  icon: IconNotifications },
]

// The Rôles tab is admin-only (role management is an escalation surface).
const visibleTabs = computed(() => tabs.filter(t => t.id !== 'roles' || isAdmin.value))
</script>

<style scoped>
.page-subtitle {
  color: var(--gray-500);
  font-size: var(--text-sm);
  margin-top: 0.25rem;
}

/* ── Layout ──────────────────────────────────────────────────────────────── */
.settings-layout {
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: 1.5rem;
  align-items: flex-start;
}

@media (max-width: 768px) {
  .settings-layout {
    grid-template-columns: 1fr;
  }

  .settings-nav {
    display: flex;
    flex-direction: row;
    overflow-x: auto;
    padding-bottom: 0.5rem;
    gap: 0.25rem !important;
    border: none !important;
    -webkit-overflow-scrolling: touch;
  }

  .settings-nav-item {
    white-space: nowrap;
    flex-shrink: 0;
  }
}

/* ── Sidebar nav ─────────────────────────────────────────────────────────── */
.settings-nav {
  background: white;
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-lg);
  padding: 0.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  position: sticky;
  top: calc(var(--topbar-height) + 1rem);
}

.settings-nav-item {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.625rem 0.875rem;
  border-radius: var(--radius-md);
  border: none;
  background: none;
  cursor: pointer;
  font-size: var(--text-sm);
  font-weight: 500;
  color: var(--gray-600);
  text-align: left;
  transition: background 0.12s, color 0.12s;
  width: 100%;
}
.settings-nav-item:hover {
  background: var(--gray-50);
  color: var(--gray-900);
}
.settings-nav-item.active {
  background: var(--brand-primary-bg);
  color: var(--brand-primary-dark);
}

.nav-icon { flex-shrink: 0; }

/* ── Panel ───────────────────────────────────────────────────────────────── */
.settings-panel {
  background: white;
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-lg);
  padding: 2rem;
  min-height: 400px;
}

.panel-header {
  margin-bottom: 2rem;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid var(--gray-100);
}
.panel-header h3 {
  font-size: var(--text-lg);
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 0.3rem;
}
.panel-header p {
  font-size: var(--text-sm);
  color: var(--gray-500);
  margin: 0;
}

/* ── Coming soon state ───────────────────────────────────────────────────── */
.coming-soon {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  padding: 4rem 2rem;
  text-align: center;
}
.coming-soon p {
  font-size: var(--text-sm);
  color: var(--gray-400);
  max-width: 300px;
  margin: 0;
}

/* ── Plan card ───────────────────────────────────────────────────────────── */
.plan-card {
  border: 2px solid var(--brand-primary);
  border-radius: var(--radius-lg);
  padding: 1.5rem;
  max-width: 420px;
  background: var(--brand-primary-bg);
}

.plan-card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.5rem;
}

.plan-badge {
  display: inline-block;
  font-size: var(--text-xs);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--brand-primary-dark);
  background: white;
  padding: 0.2rem 0.6rem;
  border-radius: var(--radius-full);
  margin-bottom: 0.5rem;
}
.plan-name {
  font-size: var(--text-2xl);
  font-weight: 700;
  color: var(--gray-900);
}
.plan-price {
  font-size: var(--text-base);
  font-weight: 600;
  color: var(--brand-primary-dark);
  margin: 0.5rem 0 1rem;
}
.plan-price span {
  font-size: var(--text-sm);
  font-weight: 400;
  color: var(--gray-500);
}

/* Trial info */
.plan-trial-info {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: var(--text-sm);
  color: #92400e;
  background: #fffbeb;
  border: 1px solid #fcd34d;
  border-radius: var(--radius-md);
  padding: 0.5rem 0.75rem;
  margin-bottom: 1rem;
}
.plan-renewal {
  font-size: var(--text-sm);
  color: var(--gray-500);
  margin-bottom: 1rem;
}

/* Limits table */
.plan-limits {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  background: white;
  border: 1px solid var(--brand-primary-light);
  border-radius: var(--radius-md);
  padding: 0.75rem 1rem;
  margin-bottom: 1.25rem;
}
.plan-limit-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: var(--text-sm);
  color: var(--gray-600);
}
.plan-limit-row strong {
  color: var(--gray-900);
  font-weight: 700;
}

.plan-features {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.plan-features li {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: var(--text-sm);
  color: var(--gray-700);
}

/* ── Promo code ──────────────────────────────────────────────────────────── */
.promo-section {
  margin-top: 1.5rem;
  padding-top: 1.25rem;
  border-top: 1px solid var(--gray-200);
}
.promo-section-title {
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--gray-700);
  margin-bottom: 0.625rem;
}
.promo-input-row {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}
.promo-input {
  flex: 1;
  border: 1px solid var(--gray-300);
  border-radius: var(--radius-md);
  padding: 0.5rem 0.75rem;
  font-size: var(--text-sm);
  font-family: ui-monospace, monospace;
  font-weight: 600;
  letter-spacing: 0.05em;
  outline: none;
  transition: border-color 0.15s;
  max-width: 200px;
}
.promo-input:focus { border-color: var(--brand-primary); }
.promo-input:disabled { background: var(--gray-50); color: var(--gray-400); }
.promo-btn {
  padding: 0.5rem 1rem;
  font-size: var(--text-sm);
  font-weight: 600;
}
.promo-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.promo-success-chip {
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--brand-primary-dark);
  background: var(--brand-primary-bg);
  padding: 0.375rem 0.75rem;
  border-radius: var(--radius-full);
}
.promo-feedback {
  font-size: var(--text-xs);
  margin-top: 0.375rem;
  padding: 0.375rem 0.625rem;
  border-radius: var(--radius-sm, 4px);
}
.promo-feedback--ok  { background: var(--brand-primary-bg); color: var(--brand-primary-dark); }
.promo-feedback--err { background: #fff5f5; color: var(--color-error, #ef4444); }

/* ── Upgrade CTA ─────────────────────────────────────────────────────────── */
.upgrade-cta {
  margin-top: 1.5rem;
  padding-top: 1.25rem;
  border-top: 1px solid var(--gray-200);
}
.upgrade-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: var(--brand-secondary, #3b82f6);
  color: white;
  border: none;
  border-radius: var(--radius-md);
  padding: 0.625rem 1.25rem;
  font-size: var(--text-sm);
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.15s;
}
.upgrade-btn:hover { opacity: 0.88; }

/* ── Modal (shared) ──────────────────────────────────────────────────────── */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, .45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 1rem;
}
.modal {
  background: white;
  border-radius: var(--radius-lg);
  width: 100%;
  max-width: 520px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0, 0, 0, .2);
}
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--gray-100);
}
.modal-header h3 { font-size: var(--text-base); font-weight: 700; color: var(--gray-900); margin: 0; }
.modal-close { background: none; border: none; font-size: 1.125rem; color: var(--gray-400); cursor: pointer; padding: 0.25rem; }
.modal-close:hover { color: var(--gray-700); }
.modal-body { padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
.modal-desc { font-size: var(--text-sm); color: var(--gray-500); margin: 0; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.75rem; padding: 1rem 1.5rem; border-top: 1px solid var(--gray-100); }

.form-row { display: flex; flex-direction: column; gap: 0.375rem; }
.form-row label { font-size: var(--text-sm); font-weight: 500; color: var(--gray-600); }
.hint { font-size: var(--text-xs); font-weight: 400; color: var(--gray-400); }

.form-input, .form-select {
  border: 1px solid var(--gray-300);
  border-radius: var(--radius-md);
  padding: 0.5rem 0.75rem;
  font-size: var(--text-sm);
  color: var(--gray-900);
  outline: none;
  transition: border-color 0.15s;
}
.form-input:focus, .form-select:focus { border-color: var(--brand-primary); }

.form-file {
  font-size: var(--text-sm);
  color: var(--gray-600);
  border: 1px dashed var(--gray-300);
  border-radius: var(--radius-md);
  padding: 0.5rem;
  cursor: pointer;
}

.form-textarea {
  border: 1px solid var(--gray-300);
  border-radius: var(--radius-md);
  padding: 0.5rem 0.75rem;
  font-size: var(--text-sm);
  color: var(--gray-900);
  outline: none;
  resize: vertical;
  font-family: inherit;
  width: 100%;
  box-sizing: border-box;
}
.form-textarea:focus { border-color: var(--brand-primary); }

.file-selected { font-size: var(--text-xs); color: var(--brand-primary-dark); }
.form-error  { background: #fff5f5; border: 1px solid #fecaca; border-radius: var(--radius-sm, 4px); padding: 0.5rem 0.75rem; font-size: var(--text-sm); color: #ef4444; }
.form-success { background: var(--brand-primary-bg); border: 1px solid var(--brand-primary-light); border-radius: var(--radius-sm, 4px); padding: 0.5rem 0.75rem; font-size: var(--text-sm); color: var(--brand-primary-dark); }

.btn-cancel { padding: 0.5rem 1rem; border: 1px solid var(--gray-300); border-radius: var(--radius-md); background: white; color: var(--gray-600); font-size: var(--text-sm); cursor: pointer; }
.btn-cancel:hover { background: var(--gray-50); }
.btn-submit { padding: 0.5rem 1.25rem; border: none; border-radius: var(--radius-md); background: var(--brand-secondary, #3b82f6); color: white; font-size: var(--text-sm); font-weight: 600; cursor: pointer; }
.btn-submit:hover:not(:disabled) { opacity: 0.88; }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

/* ── Shared utility ──────────────────────────────────────────────────────── */
.state-loading {
  padding: 2rem;
  text-align: center;
  color: var(--gray-400);
  font-size: var(--text-sm);
}

.req {
  color: var(--color-error, #ef4444);
  font-weight: 600;
}

/* ── Company form ────────────────────────────────────────────────────────── */
.company-form {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.form-section {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-section-title {
  font-size: var(--text-xs);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--gray-400);
  padding-bottom: 0.5rem;
  border-bottom: 1px solid var(--gray-100);
}

.form-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

@media (max-width: 640px) {
  .form-grid-2 { grid-template-columns: 1fr; }
}

.input-hint-row {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.input-hint-text {
  font-size: var(--text-xs);
  color: var(--gray-400);
}

.form-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding-top: 0.5rem;
}

/* ── Team panel ──────────────────────────────────────────────────────────── */
.panel-header-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.5rem;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid var(--gray-100);
}

.panel-header-row h3 {
  font-size: var(--text-lg);
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 0.25rem;
}

.panel-header-row p {
  font-size: var(--text-sm);
  color: var(--gray-500);
  margin: 0;
}

.btn-sm-pad {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.5rem 0.875rem;
  font-size: var(--text-sm);
  font-weight: 600;
  border: none;
  border-radius: var(--radius-md);
  background: var(--brand-primary);
  color: white;
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
  transition: opacity 0.15s;
}
.btn-sm-pad:hover { opacity: 0.88; }

/* Team table */
.team-table-wrap {
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-md);
  overflow: auto;
}

.team-table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--text-sm);
}

.team-table th {
  text-align: left;
  font-size: var(--text-xs);
  font-weight: 600;
  color: var(--gray-400);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 0.625rem 1rem;
  border-bottom: 1px solid var(--gray-200);
  background: var(--gray-50);
  white-space: nowrap;
}

.team-table td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--gray-100);
  color: var(--gray-700);
  vertical-align: middle;
}

.team-table tr:last-child td { border-bottom: none; }

.row-inactive td { opacity: 0.55; }

/* User cell */
.user-cell {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.user-avatar {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: var(--brand-primary);
  color: white;
  font-size: 0.6875rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  letter-spacing: 0.03em;
}

.avatar-inactive {
  background: var(--gray-300);
}

.user-name {
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--gray-900);
  display: flex;
  align-items: center;
  gap: 0.375rem;
}

.self-tag {
  font-size: var(--text-xs);
  font-weight: 500;
  color: var(--brand-primary-dark);
  background: var(--brand-primary-bg);
  padding: 1px 6px;
  border-radius: var(--radius-full);
}

.user-email {
  font-size: var(--text-xs);
  color: var(--gray-400);
  margin-top: 1px;
}

/* Role select & badge */
.role-select {
  font-size: var(--text-xs);
  font-weight: 600;
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-sm, 4px);
  padding: 0.25rem 0.5rem;
  background: white;
  color: var(--gray-700);
  cursor: pointer;
  outline: none;
}
.role-select:focus { border-color: var(--brand-primary); }

.role-badge {
  display: inline-block;
  font-size: var(--text-xs);
  font-weight: 600;
  padding: 2px 8px;
  border-radius: var(--radius-sm, 4px);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.role-admin   { background: #fef2f2; color: #991b1b; }
.role-manager { background: #eff6ff; color: #1d4ed8; }
.role-member  { background: #f0fdf4; color: #166534; }
.role-viewer  { background: #f8fafc; color: var(--gray-500); }

/* Status badge */
.team-status-badge {
  display: inline-block;
  font-size: var(--text-xs);
  font-weight: 600;
  padding: 2px 8px;
  border-radius: var(--radius-sm, 4px);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.status-active   { background: #dcfce7; color: #166534; }
.status-inactive { background: var(--gray-100); color: var(--gray-400); }

.team-date { font-size: var(--text-xs); color: var(--gray-400); white-space: nowrap; }

/* Action buttons */
.btn-toggle-user {
  font-size: var(--text-xs);
  font-weight: 600;
  padding: 3px 10px;
  border: 1px solid #fecaca;
  border-radius: var(--radius-sm, 4px);
  background: white;
  color: #ef4444;
  cursor: pointer;
  transition: background 0.12s;
  white-space: nowrap;
}
.btn-toggle-user:hover { background: #fff5f5; }
.btn-reactivate { border-color: #bbf7d0; color: #16a34a; }
.btn-reactivate:hover { background: #f0fdf4; }

/* ── Invite modal extras ─────────────────────────────────────────────────── */
.invite-success {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.invite-success-msg {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: var(--text-sm);
  font-weight: 600;
  color: #166534;
}

.temp-password-box {
  background: #f8fafc;
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-md);
  padding: 0.875rem 1rem;
}

.temp-password-label {
  font-size: var(--text-xs);
  font-weight: 600;
  color: var(--gray-500);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 0.5rem;
}

.temp-password-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.temp-password-value {
  font-family: ui-monospace, 'Cascadia Code', monospace;
  font-size: var(--text-base);
  font-weight: 700;
  color: var(--gray-900);
  letter-spacing: 0.1em;
  flex: 1;
}

.btn-copy {
  font-size: var(--text-xs);
  font-weight: 600;
  padding: 4px 10px;
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-sm, 4px);
  background: white;
  color: var(--gray-600);
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.12s;
}
.btn-copy:hover { background: var(--gray-50); }

.invite-hint {
  font-size: var(--text-xs);
  color: var(--gray-400);
  margin: 0;
  line-height: 1.5;
}
</style>
