<template>
  <div class="product-show">
    <CatalogTabNav />

    <!-- ── Loading ─────────────────────────────────────────────────── -->
    <div v-if="loading" class="loading-center" style="min-height:300px">
      <span class="spinner-sm" style="width:28px;height:28px;border-width:3px"></span>
    </div>

    <!-- ── 404 ─────────────────────────────────────────────────────── -->
    <div v-else-if="!product" class="empty-state">
      <p>Produit introuvable.</p>
      <RouterLink to="/catalog" class="btn btn-secondary">← Retour catalogue</RouterLink>
    </div>

    <template v-else>
      <!-- ── Page header ──────────────────────────────────────────── -->
      <div class="product-header">
        <div class="product-header-left">
          <RouterLink to="/catalog" class="back-link">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </RouterLink>
          <div>
            <div class="product-header-name">
              {{ product.name }}
              <!-- If has variants but type not updated yet, show Variable badge -->
              <span
                class="product-type-badge"
                :class="`type-${productHasVariants && product.product_type === 'simple' ? 'variable' : product.product_type}`"
              >
                {{ typeLabel(productHasVariants && product.product_type === 'simple' ? 'variable' : product.product_type) }}
              </span>
            </div>
            <div class="product-header-sub">
              <code class="sku-mono">{{ product.sku }}</code>
              <span class="status-pill" :class="`status-${product.status}`">
                {{ statusLabel(product.status) }}
              </span>
              <span v-if="productHasVariants" class="variant-count">
                {{ product.variants?.length ?? 0 }} variante(s)
              </span>
            </div>
          </div>
        </div>

        <!-- Actions rapides -->
        <div class="product-header-actions">
          <button
            v-if="isManagerOrAbove && product.product_type !== 'service'"
            class="btn btn-secondary btn-sm"
            @click="openReceiveDrawer"
          >
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
              <path d="M8 2v8M5 7l3 3 3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              <path d="M2 12h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            Entrée stock
          </button>
          <button
            v-if="isManagerOrAbove && product.product_type !== 'service'"
            class="btn btn-secondary btn-sm"
            @click="openAdjustDrawer"
          >
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
              <circle cx="8" cy="8" r="5.5" stroke="currentColor" stroke-width="1.4"/>
              <path d="M8 5v3l2 2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
            </svg>
            Ajuster
          </button>
          <RouterLink
            v-if="isManagerOrAbove"
            :to="`/catalog/products/${product.id}/edit`"
            class="btn btn-primary btn-sm"
          >
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
              <path d="M11.5 2.5a2.121 2.121 0 013 3L5 15H2v-3L11.5 2.5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
            </svg>
            Modifier
          </RouterLink>
        </div>
      </div>

      <!-- ── Tab navigation ──────────────────────────────────────── -->
      <div class="show-tabs">
        <button
          v-for="tab in visibleTabs"
          :key="tab.key"
          class="show-tab"
          :class="{ active: activeTab === tab.key }"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
          <span v-if="tab.badge" class="tab-badge-pill">{{ tab.badge }}</span>
        </button>
      </div>

      <!-- ═══════════════════════════════════════════════════════════ -->
      <!-- TAB: Vue d'ensemble                                        -->
      <!-- ═══════════════════════════════════════════════════════════ -->
      <div v-if="activeTab === 'overview'" class="tab-content">
        <div class="overview-grid">

          <!-- Left — product info -->
          <div class="overview-main">

            <!-- Identification -->
            <div class="card info-card">
              <h3 class="card-section-title">Identification</h3>
              <div class="info-rows">
                <div class="info-row">
                  <span class="info-label">SKU</span>
                  <code class="info-value mono">{{ product.sku }}</code>
                </div>
                <div class="info-row" v-if="product.internal_barcode">
                  <span class="info-label">Code-barres interne</span>
                  <code class="info-value mono">{{ product.internal_barcode }}</code>
                </div>
                <div class="info-row" v-if="product.gtin">
                  <span class="info-label">GTIN / EAN</span>
                  <code class="info-value mono">{{ product.gtin }}</code>
                </div>
                <div class="info-row">
                  <span class="info-label">Catégorie</span>
                  <span class="info-value">{{ product.category?.name ?? '—' }}</span>
                </div>
                <div class="info-row" v-if="product.supplier">
                  <span class="info-label">Fournisseur</span>
                  <span class="info-value">
                    {{ product.supplier.name }}
                    <span v-if="product.supplier.code" class="code-chip">{{ product.supplier.code }}</span>
                  </span>
                </div>
                <div class="info-row" v-if="product.weight_kg">
                  <span class="info-label">Poids</span>
                  <span class="info-value">{{ product.weight_kg }} kg</span>
                </div>
                <div class="info-row" v-if="product.description">
                  <span class="info-label">Description</span>
                  <span class="info-value text-wrap">{{ product.description }}</span>
                </div>
              </div>
            </div>

            <!-- Pricing -->
            <div class="card info-card">
              <h3 class="card-section-title">Prix</h3>
              <div class="price-grid">
                <div class="price-cell">
                  <div class="price-label">Prix de vente</div>
                  <div class="price-value primary">{{ product.price.formatted }}</div>
                </div>
                <div class="price-cell" v-if="product.compare_at_price">
                  <div class="price-label">Prix barré</div>
                  <div class="price-value strikethrough">{{ product.compare_at_price.formatted }}</div>
                </div>
                <div class="price-cell" v-if="product.cost">
                  <div class="price-label">Coût d'achat</div>
                  <div class="price-value">{{ product.cost.formatted }}</div>
                </div>
                <div class="price-cell" v-if="margin !== null">
                  <div class="price-label">Marge</div>
                  <div class="price-value" :class="margin >= 0 ? 'margin-ok' : 'margin-bad'">
                    {{ margin.toFixed(1) }}%
                  </div>
                </div>
              </div>
            </div>

          </div>

          <!-- Right — stock summary (if stockable) -->
          <div class="overview-side" v-if="product.product_type !== 'service'">

            <div class="card stock-summary-card">
              <div class="stock-header">
                <h3 class="card-section-title" style="margin:0">Stock</h3>
                <span v-if="stockLoading" class="spinner-xs"></span>
                <button class="refresh-btn" @click="loadStockSummary" title="Actualiser">
                  <svg width="12" height="12" viewBox="0 0 16 16" fill="none">
                    <path d="M13.5 8A5.5 5.5 0 112.5 8M2.5 3v5h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </button>
              </div>

              <div v-if="stockSummary" class="stock-kpis">
                <div class="stock-kpi">
                  <div class="stock-kpi-val">{{ stockSummary.total_quantity.toLocaleString('fr-FR') }}</div>
                  <div class="stock-kpi-lbl">Total</div>
                </div>
                <div class="stock-kpi">
                  <div class="stock-kpi-val available">{{ stockSummary.available_quantity.toLocaleString('fr-FR') }}</div>
                  <div class="stock-kpi-lbl">Disponible</div>
                </div>
                <div class="stock-kpi">
                  <div class="stock-kpi-val reserved">{{ stockSummary.reserved_quantity.toLocaleString('fr-FR') }}</div>
                  <div class="stock-kpi-lbl">Réservé</div>
                </div>
                <div class="stock-kpi" v-if="stockSummary.low_stock_count > 0">
                  <div class="stock-kpi-val warning">{{ stockSummary.low_stock_count }}</div>
                  <div class="stock-kpi-lbl">⚠ Stock bas</div>
                </div>
              </div>

              <!-- Per-warehouse breakdown -->
              <div v-if="stockSummary && stockSummary.by_warehouse.length > 1" class="warehouse-breakdown">
                <div
                  v-for="wh in stockSummary.by_warehouse"
                  :key="wh.warehouse_id ?? 'default'"
                  class="wh-row"
                  :class="{ 'wh-low': wh.low_stock }"
                >
                  <span class="wh-name">{{ wh.warehouse_name }}</span>
                  <span class="wh-qty">{{ wh.available }} dispo</span>
                </div>
              </div>

              <!-- Quick stock actions -->
              <div v-if="isManagerOrAbove" class="stock-quick-actions">
                <button class="btn btn-primary btn-sm" style="flex:1" @click="openReceiveDrawer">
                  + Entrée stock
                </button>
                <button class="btn btn-ghost btn-sm" @click="openAdjustDrawer">
                  Ajuster
                </button>
              </div>
            </div>

            <!-- Label print card -->
            <div class="card">
              <h3 class="card-section-title">Étiquettes</h3>
              <div class="label-btns">
                <button class="btn btn-secondary btn-sm" @click="printLabel('thermal')">
                  🖨 Thermique
                </button>
                <button class="btn btn-secondary btn-sm" @click="printLabel('a4sheet')">
                  📄 Planche A4
                </button>
              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════ -->
      <!-- TAB: Variantes                                             -->
      <!-- ═══════════════════════════════════════════════════════════ -->
      <div v-if="activeTab === 'variants'" class="tab-content">
        <div v-if="!productHasVariants" class="empty-state">
          <p>Ce produit n'a pas de variantes.</p>
          <RouterLink :to="`/catalog/products/${product.id}/edit`" class="btn btn-primary">
            Activer les variantes
          </RouterLink>
        </div>

        <template v-else>
          <!-- Variant filter/search + actions -->
          <div class="variant-toolbar">
            <input
              v-model="variantSearch"
              type="text"
              class="form-input search-input"
              placeholder="Rechercher une variante…"
              style="max-width:280px"
            />
            <span class="variant-count-text">
              {{ filteredVariants.length }} variante(s)
            </span>
          </div>

          <div class="card" style="padding:0;overflow:hidden">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Déclinaison</th>
                  <th>SKU</th>
                  <th>Code-barres</th>
                  <th>Prix</th>
                  <th>Stock dispo</th>
                  <th>Statut</th>
                  <th style="text-align:right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="filteredVariants.length === 0">
                  <td colspan="6" class="empty-cell">Aucune variante</td>
                </tr>
                <tr v-for="v in filteredVariants" :key="v.id" :class="{ 'row-inactive': !v.is_active }">
                  <td>
                    <span class="variant-label">{{ v.label ?? v.name ?? v.sku }}</span>
                    <span v-if="!v.is_active" class="inactive-pill">Inactif</span>
                  </td>
                  <td><code class="mono">{{ v.sku }}</code></td>
                  <td>
                    <code v-if="v.barcode" class="mono text-xs">{{ v.barcode }}</code>
                    <span v-else class="text-muted">—</span>
                  </td>
                  <td>
                    <span v-if="v.price">{{ v.price.formatted }}</span>
                    <span v-else class="text-muted inherit">↑ {{ product.price.formatted }}</span>
                  </td>
                  <td>
                    <!-- Stock disponible pour cette variante -->
                    <div
                      v-if="variantStockMap[v.id] !== undefined"
                      class="variant-stock-cell"
                      :class="{
                        'vs-zero': variantStockMap[v.id].available === 0,
                        'vs-low':  variantStockMap[v.id].low_stock && variantStockMap[v.id].available > 0,
                        'vs-ok':   !variantStockMap[v.id].low_stock && variantStockMap[v.id].available > 0,
                      }"
                    >
                      <strong>{{ variantStockMap[v.id].available }}</strong>
                      <span
                        v-if="variantStockMap[v.id].quantity !== variantStockMap[v.id].available"
                        class="vs-total"
                      >/ {{ variantStockMap[v.id].quantity }}</span>
                    </div>
                    <span v-else class="text-muted" style="font-size:0.8rem">—</span>
                  </td>
                  <td>
                    <span class="status-dot-sm" :class="v.is_active !== false ? 'dot-active' : 'dot-inactive'"></span>
                  </td>
                  <td style="text-align:right">
                    <div class="variant-row-actions">
                      <!-- Stock entry for this specific variant -->
                      <button
                        v-if="isManagerOrAbove"
                        class="btn-action btn-stock-in"
                        title="Entrée stock pour cette variante"
                        @click="openVariantReceiveDrawer(v)"
                      >
                        <svg width="13" height="13" viewBox="0 0 16 16" fill="none">
                          <path d="M8 2v8M5 7l3 3 3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                          <path d="M2 13h12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                        </svg>
                      </button>
                      <!-- Print thermal label — fetches via axios (authenticated) -->
                      <button
                        class="btn-action"
                        title="Imprimer étiquette thermique"
                        @click="printVariantLabel(v.id)"
                      >
                        <svg width="13" height="13" viewBox="0 0 16 16" fill="none">
                          <rect x="2" y="5" width="12" height="7" rx="1.5" stroke="currentColor" stroke-width="1.4"/>
                          <path d="M5 5V3a1 1 0 011-1h4a1 1 0 011 1v2M5 10h6M5 12h4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                          <circle cx="12" cy="8" r="1" fill="currentColor"/>
                        </svg>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </div>

      <!-- ═══════════════════════════════════════════════════════════ -->
      <!-- TAB: Stock                                                 -->
      <!-- ═══════════════════════════════════════════════════════════ -->
      <div v-if="activeTab === 'stock'" class="tab-content">
        <div v-if="product.product_type === 'service'" class="empty-state">
          <p>Les services ne gèrent pas de stock physique.</p>
        </div>

        <template v-else>
          <!-- Stock sub-tabs -->
          <div class="sub-tabs">
            <button
              v-for="st in stockSubTabs"
              :key="st.key"
              class="sub-tab"
              :class="{ active: activeStockTab === st.key }"
              @click="activeStockTab = st.key; loadMovements()"
            >{{ st.label }}</button>
          </div>

          <!-- Current stock -->
          <div v-if="activeStockTab === 'current'">
            <div v-if="stockSummary" class="stock-detail-cards">
              <div class="stock-kpi-card">
                <div class="skc-val">{{ stockSummary.total_quantity.toLocaleString('fr-FR') }}</div>
                <div class="skc-lbl">Quantité totale</div>
              </div>
              <div class="stock-kpi-card available">
                <div class="skc-val">{{ stockSummary.available_quantity.toLocaleString('fr-FR') }}</div>
                <div class="skc-lbl">Disponible</div>
              </div>
              <div class="stock-kpi-card reserved">
                <div class="skc-val">{{ stockSummary.reserved_quantity.toLocaleString('fr-FR') }}</div>
                <div class="skc-lbl">Réservé</div>
              </div>
            </div>

            <!-- Per-warehouse table -->
            <div v-if="stockSummary && stockSummary.by_warehouse.length" class="card" style="padding:0;overflow:hidden;margin-top:1rem">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Entrepôt</th>
                    <th>Quantité</th>
                    <th>Réservé</th>
                    <th>Disponible</th>
                    <th>Valeur</th>
                    <th>Alerte</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="wh in stockSummary.by_warehouse" :key="wh.warehouse_id ?? 'x'" :class="{ 'row-warn': wh.low_stock }">
                    <td>{{ wh.warehouse_name }}</td>
                    <td>{{ wh.quantity.toLocaleString('fr-FR') }}</td>
                    <td>{{ wh.reserved }}</td>
                    <td><strong>{{ wh.available }}</strong></td>
                    <td>{{ fmtCents(wh.total_value_cents) }}</td>
                    <td><span v-if="wh.low_stock" class="warn-badge">⚠ Bas</span></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="isManagerOrAbove" class="stock-actions-row">
              <button class="btn btn-primary" @click="openReceiveDrawer">
                ↓ Entrée stock
              </button>
              <button class="btn btn-secondary" @click="openAdjustDrawer">
                ↕ Ajustement
              </button>
            </div>
          </div>

          <!-- Movements -->
          <div v-if="activeStockTab === 'movements'">
            <div v-if="movementsLoading" class="loading-center" style="min-height:150px">
              <span class="spinner-sm"></span>
            </div>
            <div v-else class="card" style="padding:0;overflow:hidden">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Type</th>
                    <th>Quantité</th>
                    <th>Avant</th>
                    <th>Après</th>
                    <th>Motif</th>
                    <th>Référence</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="movements.length === 0">
                    <td colspan="7" class="empty-cell">Aucun mouvement enregistré</td>
                  </tr>
                  <tr v-for="m in movements" :key="m.id">
                    <td>
                      <span class="mvt-type" :class="`mvt-${m.type}`">
                        {{ mvtTypeLabel(m.type) }}
                      </span>
                    </td>
                    <td>
                      <span :class="m.type === 'out' ? 'qty-neg' : 'qty-pos'">
                        {{ m.type === 'out' ? '-' : '+' }}{{ m.quantity }}
                      </span>
                    </td>
                    <td class="text-muted">{{ m.quantity_before }}</td>
                    <td><strong>{{ m.quantity_after }}</strong></td>
                    <td>{{ m.reason }}</td>
                    <td class="text-muted mono">{{ m.reference ?? '—' }}</td>
                    <td class="text-muted">{{ formatDate(m.created_at) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

        </template>
      </div>

      <!-- ═══════════════════════════════════════════════════════════ -->
      <!-- TAB: Prix                                                  -->
      <!-- ═══════════════════════════════════════════════════════════ -->
      <div v-if="activeTab === 'prices'" class="tab-content">
        <div class="card">
          <h3 class="card-section-title">Tarification</h3>
          <div class="price-grid-full">
            <div class="pf-row">
              <span class="pf-label">Prix de vente</span>
              <span class="pf-val primary">{{ product.price.formatted }}</span>
            </div>
            <div class="pf-row" v-if="product.compare_at_price">
              <span class="pf-label">Prix barré / promotionnel</span>
              <span class="pf-val strikethrough">{{ product.compare_at_price.formatted }}</span>
            </div>
            <div class="pf-row" v-if="product.cost">
              <span class="pf-label">Coût d'achat (CMUP)</span>
              <span class="pf-val">{{ product.cost.formatted }}</span>
            </div>
            <div class="pf-row" v-if="margin !== null">
              <span class="pf-label">Marge brute</span>
              <span class="pf-val" :class="margin >= 0 ? 'margin-ok' : 'margin-bad'">
                {{ margin.toFixed(2) }}%
              </span>
            </div>
          </div>
        </div>

        <!-- Variant prices -->
        <div v-if="productHasVariants && product.variants?.some(v => v.price)" class="card" style="margin-top:1rem">
          <h3 class="card-section-title">Prix par variante</h3>
          <table class="data-table">
            <thead><tr><th>Variante</th><th>Prix</th><th>vs Base</th></tr></thead>
            <tbody>
              <tr v-for="v in product.variants?.filter(vv => vv.price)" :key="v.id">
                <td>{{ v.label ?? v.name ?? v.sku }}</td>
                <td>{{ v.price?.formatted }}</td>
                <td>
                  <span v-if="v.price && v.price.amount !== product.price.amount"
                        :class="v.price.amount > product.price.amount ? 'qty-pos' : 'qty-neg'">
                    {{ v.price.amount > product.price.amount ? '+' : '' }}{{ ((v.price.amount - product.price.amount) / 100).toLocaleString('fr-FR') }}
                  </span>
                  <span v-else class="text-muted">=</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </template>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- DRAWER: Entrée stock                                          -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="showReceiveDrawer" class="drawer-overlay" @click.self="showReceiveDrawer = false">
        <div class="drawer">
          <div class="drawer-header">
            <h3>Entrée de stock</h3>
            <button class="drawer-close" @click="showReceiveDrawer = false">×</button>
          </div>
          <div class="drawer-body">
            <!-- Product/variant context -->
            <div class="receive-context">
              <div class="receive-product">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                  <path d="M2 5l6-3 6 3v6l-6 3-6-3V5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
                </svg>
                <strong>{{ product?.name }}</strong>
                <code class="mono" style="font-size:0.75rem;color:var(--gray-400)">{{ product?.sku }}</code>
              </div>
              <!-- Show variant target if set -->
              <div v-if="receiveTarget.variantLabel" class="receive-variant-badge">
                <svg width="12" height="12" viewBox="0 0 16 16" fill="none">
                  <circle cx="4" cy="4" r="2.5" stroke="currentColor" stroke-width="1.4"/>
                  <circle cx="12" cy="4" r="2.5" stroke="currentColor" stroke-width="1.4"/>
                  <circle cx="4" cy="12" r="2.5" stroke="currentColor" stroke-width="1.4"/>
                  <circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.4"/>
                </svg>
                Variante : <strong>{{ receiveTarget.variantLabel }}</strong>
              </div>
              <div v-else-if="product?.has_variants" class="receive-variant-hint">
                Entrée sur le produit global (toutes variantes)
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Quantité à réceptionner <span class="req">*</span></label>
              <input
                v-model.number="receiveForm.quantity"
                type="number"
                min="1"
                class="form-input"
                :class="{ error: receiveErrors.quantity }"
              />
              <span v-if="receiveErrors.quantity" class="form-error">{{ receiveErrors.quantity }}</span>
            </div>

            <div class="form-group" v-if="warehouses.length > 1">
              <label class="form-label">Entrepôt</label>
              <select v-model="receiveForm.warehouse_id" class="form-input">
                <option value="">Entrepôt par défaut</option>
                <option v-for="w in warehouses" :key="w.id" :value="w.id">
                  {{ w.is_default ? '⭐ ' : '' }}{{ w.name }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Coût unitaire (optionnel)</label>
              <div class="input-adorn-right">
                <input
                  v-model.number="receiveForm.unit_cost_display"
                  type="number"
                  min="0"
                  class="form-input"
                  placeholder="0"
                />
                <span class="adorn-text">{{ product?.price.currency }}</span>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Note / Référence</label>
              <input
                v-model="receiveForm.note"
                type="text"
                class="form-input"
                placeholder="N° bon de livraison, commentaire…"
              />
            </div>

            <div v-if="receiveError" class="alert alert-error">{{ receiveError }}</div>
          </div>
          <div class="drawer-footer">
            <button class="btn btn-ghost" @click="showReceiveDrawer = false">Annuler</button>
            <button
              class="btn btn-primary"
              :disabled="receiveSaving"
              @click="submitReceive"
            >
              <span v-if="receiveSaving" class="spinner-sm spinner-white"></span>
              {{ receiveSaving ? 'Enregistrement…' : 'Valider l\'entrée' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- DRAWER: Ajustement stock                                      -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="showAdjustDrawer" class="drawer-overlay" @click.self="showAdjustDrawer = false">
        <div class="drawer">
          <div class="drawer-header">
            <h3>Ajustement de stock</h3>
            <button class="drawer-close" @click="showAdjustDrawer = false">×</button>
          </div>
          <div class="drawer-body">
            <p class="drawer-subtitle">
              Stock actuel : <strong>{{ stockSummary?.available_quantity ?? '?' }}</strong>
            </p>

            <div class="form-group">
              <label class="form-label">Nouvelle quantité <span class="req">*</span></label>
              <input
                v-model.number="adjustForm.newQty"
                type="number"
                min="0"
                class="form-input"
                :class="{ error: adjustErrors.newQty }"
              />
              <span v-if="adjustErrors.newQty" class="form-error">{{ adjustErrors.newQty }}</span>
              <p v-if="adjustDelta !== null" class="form-hint" :class="adjustDelta >= 0 ? 'hint-ok' : 'hint-warn'">
                Δ {{ adjustDelta >= 0 ? '+' : '' }}{{ adjustDelta }} unité(s)
              </p>
            </div>

            <div class="form-group">
              <label class="form-label">Justification <span class="req">*</span></label>
              <textarea
                v-model="adjustForm.note"
                class="form-input"
                :class="{ error: adjustErrors.note }"
                rows="2"
                placeholder="Ex : Inventaire physique du 02/06 — comptage manuel…"
              />
              <span v-if="adjustErrors.note" class="form-error">{{ adjustErrors.note }}</span>
              <span class="form-hint hint-ok" style="font-size:0.75rem">Minimum 5 caractères requis</span>
            </div>

            <div v-if="adjustError" class="alert alert-error">{{ adjustError }}</div>
          </div>
          <div class="drawer-footer">
            <button class="btn btn-ghost" @click="showAdjustDrawer = false">Annuler</button>
            <button
              class="btn btn-primary"
              :disabled="adjustSaving"
              @click="submitAdjust"
            >
              <span v-if="adjustSaving" class="spinner-sm spinner-white"></span>
              {{ adjustSaving ? 'Ajustement…' : 'Confirmer l\'ajustement' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'
import { pushToast } from '@/composables/useNotifications'
import { formatDateTime } from '@/shared/utils/date'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { formatMoney } from '@/shared/utils/money'
import { productService } from '../services/productService'
import { usePermission } from '@/composables/usePermission'
import CatalogTabNav from '../components/CatalogTabNav.vue'
import client from '@/api/client'
import type { Product, ProductStockSummary, StockMovementItem } from '../types'

const route  = useRoute()
const router = useRouter()
const { isManagerOrAbove } = usePermission()

const productId = route.params.id as string

// ── State ──────────────────────────────────────────────────────────────────
const product      = ref<Product | null>(null)
const loading      = ref(false)
const stockSummary = ref<ProductStockSummary | null>(null)
const stockLoading = ref(false)
const movements    = ref<StockMovementItem[]>([])
const movementsLoading = ref(false)
const warehouses   = ref<any[]>([])
const activeTab    = ref('overview')
const activeStockTab = ref<'current' | 'movements'>('current')
const variantSearch = ref('')

// ── Tabs ───────────────────────────────────────────────────────────────────
const visibleTabs = computed(() => {
  const tabs: { key: string; label: string; badge?: number | string }[] = [
    { key: 'overview', label: 'Vue d\'ensemble' },
    { key: 'variants', label: 'Variantes', badge: product.value?.variants?.length || undefined },
    { key: 'stock',    label: 'Stock' },
    { key: 'prices',   label: 'Prix' },
  ]
  if (product.value?.product_type === 'service') {
    return tabs.filter(t => t.key !== 'stock')
  }
  return tabs
})

const stockSubTabs = [
  { key: 'current',   label: 'Stock actuel' },
  { key: 'movements', label: 'Mouvements' },
]

// ── Derived ────────────────────────────────────────────────────────────────
// True if the product has variants (either by flag OR by actual variant records)
const productHasVariants = computed(() =>
  (product.value?.has_variants === true) || (product.value?.variants?.length ?? 0) > 0
)

// Quick lookup: variantId → { available, quantity, low_stock }
const variantStockMap = computed(() => {
  const map: Record<string, { available: number; quantity: number; low_stock: boolean }> = {}
  for (const row of (stockSummary.value?.by_variant ?? [])) {
    map[row.variant_id] = { available: row.available, quantity: row.quantity, low_stock: row.low_stock }
  }
  return map
})

const filteredVariants = computed(() => {
  if (!product.value?.variants) return []
  const q = variantSearch.value.toLowerCase()
  if (!q) return product.value.variants
  return product.value.variants.filter(v =>
    (v.label ?? v.name ?? v.sku).toLowerCase().includes(q) || v.sku.toLowerCase().includes(q)
  )
})

const margin = computed(() => {
  if (!product.value?.cost || !product.value.price) return null
  const sell = product.value.price.amount
  const cost = product.value.cost.amount
  if (cost === 0 || sell === 0) return null
  return ((sell - cost) / sell) * 100
})

const adjustDelta = computed(() => {
  if (adjustForm.newQty === null || adjustForm.newQty === undefined) return null
  const current = stockSummary.value?.total_quantity ?? 0
  return adjustForm.newQty - current
})

// ── Load product ───────────────────────────────────────────────────────────
async function loadProduct() {
  loading.value = true
  try {
    product.value = await productService.getDetail(productId)
  } catch {
    product.value = null
  } finally {
    loading.value = false
  }
}

async function loadStockSummary() {
  if (!product.value || product.value.product_type === 'service') return
  stockLoading.value = true
  try {
    stockSummary.value = await productService.getStockSummary(productId)
  } catch {
    // silent — stock may not exist yet
  } finally {
    stockLoading.value = false
  }
}

async function loadMovements() {
  if (activeStockTab.value !== 'movements') return
  movementsLoading.value = true
  try {
    const res = await client.get(`/api/inventory/stock/${productId}/movements`, { params: { per_page: 50 } })
    movements.value = (res.data.data ?? res.data) as StockMovementItem[]
  } catch {
    movements.value = []
  } finally {
    movementsLoading.value = false
  }
}

async function loadWarehouses() {
  try {
    const res = await client.get('/api/inventory/warehouses')
    warehouses.value = res.data.data ?? res.data ?? []
  } catch {
    warehouses.value = []
  }
}

// ── Labels — IMPORTANT: must fetch via axios (with auth token) ─────────────
// Using window.open(url) would strip the Bearer token → Laravel redirects
// to route('login') which doesn't exist in API-only mode → 500 error.
async function printLabel(format: 'thermal' | 'a4sheet') {
  if (!product.value) return
  await fetchAndPrintLabel(product.value.id, format, undefined)
}

async function printVariantLabel(variantId: string) {
  if (!product.value) return
  await fetchAndPrintLabel(product.value.id, 'thermal', variantId)
}

async function fetchAndPrintLabel(
  productId: string,
  format: 'thermal' | 'a4sheet',
  variantId?: string,
) {
  try {
    const params: Record<string, string | number> = { format, price: 1, copies: 1 }
    const url = variantId
      ? `/api/catalog/products/${productId}/variants/${variantId}/label`
      : `/api/catalog/products/${productId}/label`

    // Axios sends the Bearer token → backend returns HTML
    const resp = await client.get(url, { params, responseType: 'text' })
    const html = resp.data as string

    const win = window.open('', '_blank')
    if (win) {
      win.document.open()
      win.document.write(html)
      win.document.close()
      // Give the browser a moment to render before triggering print
      win.addEventListener('load', () => win.print(), { once: true })
    }
  } catch (e: any) {
    pushToast('Erreur lors de l\'impression : ' + (e?.response?.data?.message ?? e?.message))
  }
}

// ── Receive drawer (product-level + variant-level) ─────────────────────────
const showReceiveDrawer  = ref(false)
const receiveSaving      = ref(false)
const receiveError       = ref('')
const receiveErrors      = reactive<Record<string, string>>({})

interface ReceiveTarget { variantId?: string; variantLabel?: string }
const receiveTarget = ref<ReceiveTarget>({})

const receiveForm = reactive({
  quantity:          0,
  warehouse_id:      '',
  unit_cost_display: 0,
  note:              '',
})

/** Open for the product itself (no variant) */
function openReceiveDrawer() {
  receiveTarget.value = {}
  resetReceiveForm()
  showReceiveDrawer.value = true
}

/** Open for a specific variant row */
function openVariantReceiveDrawer(v: { id: string; label?: string; name?: string; sku: string }) {
  receiveTarget.value = {
    variantId:    v.id,
    variantLabel: v.label ?? v.name ?? v.sku,
  }
  resetReceiveForm()
  showReceiveDrawer.value = true
}

function resetReceiveForm() {
  receiveForm.quantity          = 0
  receiveForm.warehouse_id      = ''
  receiveForm.unit_cost_display = 0
  receiveForm.note              = ''
  receiveError.value            = ''
  Object.keys(receiveErrors).forEach(k => delete receiveErrors[k])
}

async function submitReceive() {
  Object.keys(receiveErrors).forEach(k => delete receiveErrors[k])
  receiveError.value = ''

  if (!receiveForm.quantity || receiveForm.quantity < 1) {
    receiveErrors.quantity = 'La quantité doit être ≥ 1'
    return
  }

  receiveSaving.value = true
  try {
    // POST /api/inventory/stock/{productId}/move-in
    // MoveStockRequest: quantity (min:1), reason (required), note (nullable)
    // Optional query param: ?variant_id=... for variant-level stock
    const params: Record<string, string> = {}
    if (receiveTarget.value.variantId) {
      params.variant_id = receiveTarget.value.variantId
    }

    await client.post(`/api/inventory/stock/${productId}/move-in`, {
      quantity:  receiveForm.quantity,
      reason:    'delivery',
      reference: receiveForm.note || undefined,
      note:      receiveForm.note || undefined,
      // variant_id in body (InventoryController reads data['variant_id'])
      ...(receiveTarget.value.variantId ? { variant_id: receiveTarget.value.variantId } : {}),
    })

    showReceiveDrawer.value = false
    // Reload stock summary to update the variant stock column instantly
    await loadStockSummary()
  } catch (e: any) {
    receiveError.value = e?.response?.data?.message ?? 'Erreur lors de l\'entrée de stock.'
  } finally {
    receiveSaving.value = false
  }
}

// ── Adjust drawer ──────────────────────────────────────────────────────────
const showAdjustDrawer = ref(false)
const adjustSaving = ref(false)
const adjustError  = ref('')
const adjustErrors = reactive<Record<string, string>>({})
const adjustForm   = reactive({
  newQty: null as number | null,
  note:   '',
})

function openAdjustDrawer() {
  adjustForm.newQty = stockSummary.value?.total_quantity ?? 0
  adjustForm.note   = ''
  adjustError.value = ''
  Object.keys(adjustErrors).forEach(k => delete adjustErrors[k])
  showAdjustDrawer.value = true
}

async function submitAdjust() {
  Object.keys(adjustErrors).forEach(k => delete adjustErrors[k])
  adjustError.value = ''

  if (adjustForm.newQty === null || adjustForm.newQty < 0) {
    adjustErrors.newQty = 'La quantité doit être ≥ 0'
    return
  }
  if (!adjustForm.note || adjustForm.note.trim().length < 5) {
    adjustErrors.note = 'La justification doit faire au moins 5 caractères'
    return
  }

  adjustSaving.value = true
  try {
    // AdjustStockRequest: quantity (new absolute) + note (required, min:5)
    await client.post(`/api/inventory/stock/${productId}/adjust`, {
      quantity: adjustForm.newQty,
      note:     adjustForm.note.trim(),
    })
    showAdjustDrawer.value = false
    await loadStockSummary()
  } catch (e: any) {
    adjustError.value = e?.response?.data?.message ?? 'Erreur lors de l\'ajustement.'
  } finally {
    adjustSaving.value = false
  }
}

// ── Helpers ────────────────────────────────────────────────────────────────
function typeLabel(t: string) {
  return { simple: 'Simple', variable: 'Variable', service: 'Service', kit: 'Kit' }[t] ?? t
}
function statusLabel(s: string) {
  return { active: 'Actif', draft: 'Brouillon', archived: 'Archivé' }[s] ?? s
}
function mvtTypeLabel(t: string) {
  return { in: 'Entrée', out: 'Sortie', adjustment: 'Ajustement', return: 'Retour' }[t] ?? t
}
const formatDate = formatDateTime
const fmtCents = (c: number) => formatMoney(c)

// ── Init ───────────────────────────────────────────────────────────────────
onMounted(async () => {
  await loadProduct()
  await Promise.all([loadStockSummary(), loadWarehouses()])
})
</script>

<style scoped>
.product-show { padding: 24px; }

/* ── Page header ──────────────────────────────────────────────────────────── */
.product-header {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1.25rem;
}
.product-header-left {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  flex: 1;
  min-width: 0;
}
.back-link {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  color: var(--gray-500);
  text-decoration: none;
  border: 1px solid var(--gray-200);
  flex-shrink: 0;
  transition: all 0.15s;
  margin-top: 2px;
}
.back-link:hover { background: var(--gray-50); color: var(--gray-900); }

.product-header-name {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--gray-900);
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.product-header-sub {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-top: 0.25rem;
  flex-wrap: wrap;
}
.product-header-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}

/* Type badge */
.product-type-badge {
  font-size: 0.7rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 20px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.type-simple   { background: #e0f2fe; color: #0369a1; }
.type-variable { background: #f3e8ff; color: #7e22ce; }
.type-service  { background: #fef9c3; color: #854d0e; }
.type-kit      { background: #dcfce7; color: #166534; }

.sku-mono { font-family: ui-monospace, monospace; font-size: 0.8rem; color: var(--gray-600); background: var(--gray-100); padding: 2px 8px; border-radius: 4px; }
.status-pill { font-size: 0.72rem; font-weight: 600; padding: 2px 8px; border-radius: 20px; }
.status-active   { background: #d1fae5; color: #065f46; }
.status-draft    { background: var(--gray-100); color: var(--gray-500); }
.status-archived { background: #fef3c7; color: #92400e; }
.variant-count  { font-size: 0.8rem; color: var(--gray-500); }

/* ── Tabs ─────────────────────────────────────────────────────────────────── */
.show-tabs {
  display: flex;
  gap: 0;
  border-bottom: 2px solid var(--gray-100);
  margin-bottom: 1.25rem;
  overflow-x: auto;
  scrollbar-width: none;
}
.show-tabs::-webkit-scrollbar { display: none; }
.show-tab {
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  padding: 0.625rem 1rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--gray-500);
  cursor: pointer;
  white-space: nowrap;
  transition: color 0.15s, border-color 0.15s;
  display: flex;
  align-items: center;
  gap: 0.375rem;
}
.show-tab:hover { color: var(--gray-800); }
.show-tab.active { color: var(--brand-primary); border-bottom-color: var(--brand-primary); font-weight: 600; }
.tab-badge-pill { background: var(--gray-200); color: var(--gray-600); font-size: 0.7rem; font-weight: 700; padding: 1px 6px; border-radius: 10px; }

/* ── Sub tabs ─────────────────────────────────────────────────────────────── */
.sub-tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
.sub-tab {
  padding: 0.375rem 0.875rem;
  border-radius: 20px;
  font-size: 0.8125rem;
  font-weight: 500;
  border: 1.5px solid var(--gray-200);
  background: white;
  color: var(--gray-600);
  cursor: pointer;
  transition: all 0.12s;
}
.sub-tab.active { border-color: var(--brand-primary); background: var(--brand-primary-bg); color: var(--brand-primary); }

/* ── Cards ────────────────────────────────────────────────────────────────── */
.card { background: white; border-radius: var(--radius-lg); border: 1px solid var(--gray-200); padding: 1.25rem; }
.card-section-title { font-size: 0.875rem; font-weight: 700; color: var(--gray-700); margin: 0 0 0.875rem; text-transform: uppercase; letter-spacing: 0.04em; }

/* ── Overview grid ────────────────────────────────────────────────────────── */
.overview-grid {
  display: grid;
  grid-template-columns: 1fr 280px;
  gap: 1.25rem;
}
.overview-main { display: flex; flex-direction: column; gap: 1.25rem; }
.overview-side { display: flex; flex-direction: column; gap: 1.25rem; }
@media (max-width: 900px) { .overview-grid { grid-template-columns: 1fr; } }

.info-card {}
.info-rows { display: flex; flex-direction: column; gap: 0.75rem; }
.info-row { display: grid; grid-template-columns: 140px 1fr; gap: 0.5rem; align-items: start; }
.info-label { font-size: 0.8rem; color: var(--gray-400); font-weight: 500; padding-top: 1px; }
.info-value { font-size: 0.875rem; color: var(--gray-800); }
.info-value.mono { font-family: ui-monospace, monospace; }
.info-value.text-wrap { white-space: pre-line; }
.code-chip { font-size: 0.72rem; background: var(--gray-100); color: var(--gray-500); padding: 1px 6px; border-radius: 4px; margin-left: 0.375rem; }

/* ── Price grid ───────────────────────────────────────────────────────────── */
.price-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 1rem; }
.price-cell {}
.price-label { font-size: 0.75rem; color: var(--gray-400); font-weight: 500; margin-bottom: 0.25rem; }
.price-value { font-size: 1.1rem; font-weight: 700; color: var(--gray-900); }
.price-value.primary { color: var(--brand-primary); font-size: 1.25rem; }
.price-value.strikethrough { text-decoration: line-through; color: var(--gray-400); font-size: 1rem; }
.margin-ok  { color: var(--brand-primary); }
.margin-bad { color: #ef4444; }

/* ── Stock summary card ───────────────────────────────────────────────────── */
.stock-summary-card { display: flex; flex-direction: column; gap: 0.875rem; }
.stock-header { display: flex; align-items: center; gap: 0.5rem; }
.refresh-btn { background: none; border: none; color: var(--gray-400); cursor: pointer; padding: 2px; border-radius: 4px; margin-left: auto; }
.refresh-btn:hover { color: var(--gray-700); background: var(--gray-100); }
.stock-kpis { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
.stock-kpi { text-align: center; padding: 0.5rem; background: var(--gray-50); border-radius: var(--radius-sm); }
.stock-kpi-val { font-size: 1.1rem; font-weight: 700; color: var(--gray-900); }
.stock-kpi-val.available { color: var(--brand-primary); }
.stock-kpi-val.reserved  { color: var(--gray-400); }
.stock-kpi-val.warning   { color: #f59e0b; }
.stock-kpi-lbl { font-size: 0.7rem; color: var(--gray-400); margin-top: 2px; }
.warehouse-breakdown { display: flex; flex-direction: column; gap: 0.375rem; }
.wh-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.8125rem; padding: 0.25rem 0.375rem; border-radius: 4px; }
.wh-row.wh-low { background: #fef3c7; color: #92400e; }
.wh-name { color: var(--gray-700); font-weight: 500; }
.wh-qty  { color: var(--gray-500); }
.stock-quick-actions { display: flex; gap: 0.5rem; margin-top: 0.25rem; }
.label-btns { display: flex; flex-direction: column; gap: 0.5rem; }

/* ── Variant table ────────────────────────────────────────────────────────── */
.variant-toolbar { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
.variant-count-text { font-size: 0.8rem; color: var(--gray-400); }
.variant-label { font-weight: 500; color: var(--gray-900); }
.inactive-pill { font-size: 0.7rem; background: var(--gray-100); color: var(--gray-400); padding: 1px 6px; border-radius: 8px; margin-left: 0.375rem; }
.row-inactive td { opacity: 0.5; }
.status-dot-sm { display: inline-block; width: 8px; height: 8px; border-radius: 50%; }
.dot-active   { background: var(--brand-primary); }
.dot-inactive { background: var(--gray-300); }
.text-muted { color: var(--gray-400); }
.inherit { font-style: italic; }
.text-xs { font-size: 0.75rem; }
.mono { font-family: ui-monospace, monospace; font-size: 0.8125rem; }
.btn-action { background: none; border: none; cursor: pointer; padding: 0.25rem 0.5rem; border-radius: var(--radius-sm); }
.btn-action:hover { background: var(--gray-100); }
.empty-cell { text-align: center; padding: 2rem; color: var(--gray-400); }

/* ── Stock detail ─────────────────────────────────────────────────────────── */
.stock-detail-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1rem; }
.stock-kpi-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-md); padding: 1rem 1.25rem; text-align: center; }
.stock-kpi-card.available { border-color: #a7f3d0; background: #ecfdf5; }
.stock-kpi-card.reserved  { border-color: var(--gray-200); background: var(--gray-50); }
.skc-val { font-size: 1.5rem; font-weight: 700; color: var(--gray-900); }
.skc-lbl { font-size: 0.75rem; color: var(--gray-400); margin-top: 0.25rem; }
.stock-actions-row { display: flex; gap: 0.75rem; margin-top: 1.25rem; }
.row-warn td { background: #fffbeb; }
.warn-badge { font-size: 0.72rem; background: #fef3c7; color: #92400e; padding: 2px 6px; border-radius: 4px; }

/* Movement type colors */
.mvt-type { font-size: 0.75rem; font-weight: 600; padding: 2px 8px; border-radius: 12px; }
.mvt-in         { background: #d1fae5; color: #065f46; }
.mvt-out        { background: #fee2e2; color: #991b1b; }
.mvt-adjustment { background: #e0f2fe; color: #0369a1; }
.mvt-return     { background: #f3e8ff; color: #6b21a8; }
.qty-pos { color: var(--brand-primary); font-weight: 600; }
.qty-neg { color: #ef4444; font-weight: 600; }

/* ── Prices tab ───────────────────────────────────────────────────────────── */
.price-grid-full { display: flex; flex-direction: column; gap: 0.875rem; }
.pf-row { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-100); }
.pf-row:last-child { border-bottom: none; }
.pf-label { font-size: 0.875rem; color: var(--gray-500); }
.pf-val { font-size: 0.9375rem; font-weight: 600; color: var(--gray-900); }
.pf-val.primary { color: var(--brand-primary); font-size: 1.1rem; }
.pf-val.strikethrough { text-decoration: line-through; color: var(--gray-400); }
.ml-1 { margin-left: 0.25rem; }

/* ── Drawers ──────────────────────────────────────────────────────────────── */
.drawer-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.35);
  z-index: 999;
  display: flex;
  justify-content: flex-end;
  backdrop-filter: blur(1px);
}
.drawer {
  width: 420px;
  max-width: 95vw;
  background: white;
  height: 100%;
  display: flex;
  flex-direction: column;
  box-shadow: var(--shadow-lg, -4px 0 24px rgba(0,0,0,0.12));
  animation: slideIn 0.22s cubic-bezier(0.4,0,0.2,1);
}
@keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
.drawer-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--gray-200);
  font-size: 1rem;
  font-weight: 700;
  color: var(--gray-900);
}
.drawer-close {
  background: none; border: none; font-size: 1.25rem; cursor: pointer;
  color: var(--gray-400); padding: 0.25rem; border-radius: 4px; line-height: 1;
}
.drawer-close:hover { color: var(--gray-700); background: var(--gray-100); }
.drawer-body {
  flex: 1;
  padding: 1.5rem;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.drawer-subtitle { font-size: 0.875rem; color: var(--gray-500); margin: 0 0 0.25rem; }
.drawer-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--gray-200);
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

/* ── Form helpers ─────────────────────────────────────────────────────────── */
.form-group { display: flex; flex-direction: column; gap: 0.375rem; }
.form-label { font-size: 0.8125rem; font-weight: 600; color: var(--gray-700); }
.req { color: #ef4444; }
.form-input { padding: 0.5rem 0.75rem; border: 1px solid var(--gray-300); border-radius: var(--radius-sm); font-size: 0.875rem; background: white; transition: border-color 0.15s; }
.form-input:focus { outline: none; border-color: var(--brand-primary); box-shadow: 0 0 0 3px var(--brand-primary-bg); }
.form-input.error { border-color: #ef4444; }
.form-error { font-size: 0.78rem; color: #ef4444; }
.form-hint { font-size: 0.78rem; margin-top: 0.25rem; }
.hint-ok   { color: var(--brand-primary); }
.hint-warn { color: #f59e0b; }
.input-adorn-right { position: relative; }
.input-adorn-right .form-input { padding-right: 3.5rem; }
.adorn-text { position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); font-size: 0.78rem; color: var(--gray-500); font-weight: 600; pointer-events: none; }

/* ── Misc ─────────────────────────────────────────────────────────────────── */
.empty-state { text-align: center; padding: 4rem 2rem; color: var(--gray-400); }
.loading-center { display: flex; align-items: center; justify-content: center; gap: 0.75rem; color: var(--gray-500); }
.alert { padding: 0.75rem 1rem; border-radius: var(--radius-sm); font-size: 0.875rem; }
.alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
.spinner-xs { width: 12px; height: 12px; border: 2px solid var(--gray-200); border-top-color: var(--brand-primary); border-radius: 50%; animation: spin 0.6s linear infinite; }
.spinner-sm { width: 18px; height: 18px; border: 2px solid rgba(255,255,255,0.3); border-top-color: currentColor; border-radius: 50%; animation: spin 0.6s linear infinite; display: inline-block; }
.spinner-white { border-color: rgba(255,255,255,0.3); border-top-color: white; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Variant stock cell ───────────────────────────────────────────────────── */
.variant-stock-cell { display: inline-flex; align-items: baseline; gap: 3px; font-size: 0.9rem; }
.vs-ok   strong { color: var(--brand-primary); }
.vs-low  strong { color: #f59e0b; }
.vs-zero strong { color: var(--gray-300); }
.vs-total { font-size: 0.72rem; color: var(--gray-400); }

/* ── Variant row actions ──────────────────────────────────────────────────── */
.variant-row-actions { display: flex; align-items: center; justify-content: flex-end; gap: 4px; }
.btn-stock-in { color: var(--brand-primary); }
.btn-stock-in:hover { background: var(--brand-primary-bg); }

/* ── Receive drawer context banner ───────────────────────────────────────── */
.receive-context {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 10px 12px;
  background: var(--gray-50);
  border-radius: var(--radius-md);
  border: 1px solid var(--gray-200);
  margin-bottom: 0.5rem;
}
.receive-product {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.875rem;
  color: var(--gray-700);
}
.receive-variant-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.8125rem;
  color: var(--brand-primary);
  background: var(--brand-primary-bg);
  padding: 3px 10px;
  border-radius: 20px;
  align-self: flex-start;
}
.receive-variant-hint {
  font-size: 0.78rem;
  color: var(--gray-400);
  font-style: italic;
}
</style>
