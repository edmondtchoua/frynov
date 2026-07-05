/**
 * Frynov ERP — Presentation Video Recorder
 * Uses Playwright to capture all views, then ffmpeg compiles into MP4.
 */

import { chromium } from '@playwright/test';
import { execSync } from 'child_process';
import { existsSync, mkdirSync, readdirSync, unlinkSync } from 'fs';
import { join } from 'path';

const BASE_URL   = 'http://localhost:5173';
const OUTPUT_DIR = 'C:/Users/pro/Source/repos/ETech/video_frames';
const VIDEO_OUT  = 'C:/Users/pro/Source/repos/ETech/frynov_erp_presentation.mp4';
const VIEWPORT   = { width: 1280, height: 720 };

// Clean previous frames
if (existsSync(OUTPUT_DIR)) {
  readdirSync(OUTPUT_DIR).forEach(f => unlinkSync(join(OUTPUT_DIR, f)));
} else {
  mkdirSync(OUTPUT_DIR, { recursive: true });
}

const FAKE_USER = {
  id: 'demo-user-id',
  name: 'Fatou Diallo',
  email: 'fatou@boutique-dakar.sn',
  is_super_admin: true,
  tenant_id: 'demo-tenant-id',
  tenant: {
    id: 'demo-tenant-id', name: 'Boutique Dakar', slug: 'boutique-dakar',
    domain: null, plan: 'pro', status: 'active', subscription_status: 'active',
  },
  roles: ['admin'],
  permissions: [],
};

let frameIdx = 0;

async function shot(page, label, extraDelay = 0) {
  if (extraDelay) await page.waitForTimeout(extraDelay);
  await page.waitForTimeout(400);
  const file = join(OUTPUT_DIR, `frame_${String(frameIdx).padStart(4, '0')}_${label.replace(/[^a-z0-9]/gi, '_')}.png`);
  await page.screenshot({ path: file, fullPage: false });
  console.log(`  📸 [${frameIdx}] ${label}`);
  frameIdx++;
  return file;
}

// Take N duplicate frames to hold a view for a given duration
async function hold(page, label, seconds = 2) {
  const fps = 24;
  const count = Math.round(fps * seconds);
  const file = join(OUTPUT_DIR, `frame_${String(frameIdx).padStart(4, '0')}_${label.replace(/[^a-z0-9]/gi, '_')}.png`);
  await page.screenshot({ path: file });
  // Write duplicate frames by copying (use ffmpeg -loop instead — just one frame is enough with -framerate trick)
  for (let i = 1; i < count; i++) {
    const dup = join(OUTPUT_DIR, `frame_${String(frameIdx + i).padStart(4, '0')}_${label.replace(/[^a-z0-9]/gi, '_')}_dup${i}.png`);
    execSync(`copy "${file.replace(/\//g, '\\')}" "${dup.replace(/\//g, '\\')}"`);
  }
  console.log(`  🎬 [${frameIdx}] HOLD ${label} (${seconds}s = ${count} frames)`);
  frameIdx += count;
}

async function injectAuth(page) {
  await page.evaluate((user) => {
    const app = document.querySelector('#app')?.__vue_app__;
    if (!app) return;
    const auth = app.config.globalProperties.$pinia?._s?.get('auth');
    if (!auth) return;
    auth.token = 'preview-token';
    auth.user  = user;
  }, FAKE_USER);
}

async function goto(page, path) {
  await page.evaluate((p) => {
    const app    = document.querySelector('#app')?.__vue_app__;
    const router = app?.config?.globalProperties?.$router;
    if (router) router.push(p);
  }, path);
  await page.waitForTimeout(600);
}

// ── MAIN ──────────────────────────────────────────────────────────────────────

const browser = await chromium.launch({
  headless: true,
  executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
});
const context = await browser.newContext({ viewport: VIEWPORT });
const page    = await context.newPage();

console.log('\n🎬 Frynov ERP — Démarrage de l\'enregistrement\n');

// ── 1. LANDING PAGE ──
await page.goto(BASE_URL + '/');
await page.waitForTimeout(1200);
await shot(page, 'landing_hero');
await hold(page, 'landing_hero', 3);

// Scroll to features
await page.evaluate(() => window.scrollTo({ top: 1100, behavior: 'smooth' }));
await page.waitForTimeout(800);
await hold(page, 'landing_features', 2);

// Scroll to modules
await page.evaluate(() => window.scrollTo({ top: 2200, behavior: 'smooth' }));
await page.waitForTimeout(800);
await hold(page, 'landing_modules', 2);

// Scroll to CTA
await page.evaluate(() => window.scrollTo({ top: 3300, behavior: 'smooth' }));
await page.waitForTimeout(800);
await hold(page, 'landing_cta', 2);

// Scroll to footer
await page.evaluate(() => window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' }));
await page.waitForTimeout(800);
await hold(page, 'landing_footer', 1.5);

// ── 2. AUTH ──
await page.evaluate(() => window.scrollTo({ top: 0 }));
await page.goto(BASE_URL + '/login');
await page.waitForTimeout(800);
await hold(page, 'login', 2.5);

await page.goto(BASE_URL + '/register');
await page.waitForTimeout(800);
await hold(page, 'register', 2.5);

// ── 3. ONBOARDING ──
await page.goto(BASE_URL + '/onboarding');
await page.waitForTimeout(800);
await hold(page, 'onboarding_step1', 2);

// Click "Commerce & Retail" then Continuer
await page.evaluate(() => {
  const cards = Array.from(document.querySelectorAll('button.choice-card'));
  const card  = cards.find(c => c.textContent.includes('Commerce'));
  card?.click();
});
await page.waitForTimeout(400);
await page.evaluate(() => {
  const btns = Array.from(document.querySelectorAll('button'));
  btns.find(b => b.textContent.includes('Continuer'))?.click();
});
await page.waitForTimeout(600);
await hold(page, 'onboarding_step2_teams', 1.5);

// Select team size then continue
await page.evaluate(() => {
  const cards = Array.from(document.querySelectorAll('button.choice-card'));
  cards.find(c => c.textContent.includes('2'))?.click();
});
await page.waitForTimeout(300);
await page.evaluate(() => {
  Array.from(document.querySelectorAll('button')).find(b => b.textContent.includes('Continuer'))?.click();
});
await page.waitForTimeout(600);
await hold(page, 'onboarding_step3_modules', 2);

// Continue to step 4
await page.evaluate(() => {
  Array.from(document.querySelectorAll('button')).find(b => b.textContent.includes('Continuer'))?.click();
});
await page.waitForTimeout(600);
await hold(page, 'onboarding_step4_company', 1.5);

// Fill company name and finalize
await page.fill('input[placeholder="Acme SAS"]', 'Boutique Dakar').catch(() => {});
await page.waitForTimeout(300);
await page.evaluate(() => {
  Array.from(document.querySelectorAll('button')).find(b => b.textContent.includes('Finaliser'))?.click();
});
await page.waitForTimeout(600);
await hold(page, 'onboarding_step5_success', 2.5);

// ── 4. APP — inject auth and navigate ──
await page.goto(BASE_URL + '/dashboard');
await page.waitForTimeout(800);
await injectAuth(page);
await goto(page, '/dashboard');
await hold(page, 'dashboard', 3);

// Catalog
await goto(page, '/catalog');
await hold(page, 'catalog_products', 2);

await goto(page, '/catalog/products/create');
await hold(page, 'catalog_create', 2);

await goto(page, '/catalog/categories');
await hold(page, 'catalog_categories', 1.5);

// Inventory
await goto(page, '/inventory');
await hold(page, 'inventory_stock', 2);

await goto(page, '/inventory/alerts');
await hold(page, 'inventory_alerts', 1.5);

// Orders
await goto(page, '/orders');
await hold(page, 'orders_list', 2);

await goto(page, '/orders/new');
await hold(page, 'orders_create', 2);

// Customers
await goto(page, '/customers');
await hold(page, 'customers_list', 2);

// Payments
await goto(page, '/payments');
await hold(page, 'payments_list', 2);

// Deliveries
await goto(page, '/deliveries');
await hold(page, 'deliveries_list', 2);

// Suppliers
await goto(page, '/suppliers');
await hold(page, 'suppliers_list', 2);

// Import
await goto(page, '/import/new');
await hold(page, 'import_wizard', 2);

await goto(page, '/import/history');
await hold(page, 'import_history', 1.5);

// Reports
await goto(page, '/reports/sales');
await hold(page, 'reports_sales', 2);

await goto(page, '/reports/stock');
await hold(page, 'reports_stock', 1.5);

// Settings
await goto(page, '/settings');
await hold(page, 'settings_company', 2);

// Click team tab
await page.evaluate(() => {
  const tabs = Array.from(document.querySelectorAll('button, [role="tab"]'));
  tabs.find(t => t.textContent.includes('quipe') || t.textContent.includes('Team'))?.click();
});
await page.waitForTimeout(500);
await hold(page, 'settings_team', 1.5);

// Click billing tab
await page.evaluate(() => {
  const tabs = Array.from(document.querySelectorAll('button, [role="tab"]'));
  tabs.find(t => t.textContent.includes('bonnement') || t.textContent.includes('billing'))?.click();
});
await page.waitForTimeout(500);
await hold(page, 'settings_billing', 1.5);

// ── 5. ADMIN ──
await goto(page, '/admin');
await hold(page, 'admin_dashboard', 2.5);

await goto(page, '/admin/tenants');
await hold(page, 'admin_tenants', 2);

await goto(page, '/admin/modules');
await hold(page, 'admin_modules', 2);

await goto(page, '/admin/plans');
await hold(page, 'admin_plans', 2);

await goto(page, '/admin/audit');
await hold(page, 'admin_audit', 2);

// ── FIN ──
await browser.close();

const totalFrames = readdirSync(OUTPUT_DIR).length;
console.log(`\n✅ ${totalFrames} frames capturées dans ${OUTPUT_DIR}`);
console.log('\n🎞️  Compilation vidéo avec ffmpeg...\n');

// Compile with ffmpeg — 24fps, H264, high quality
const ffmpegCmd = [
  'ffmpeg -y',
  `-framerate 24`,
  `-pattern_type glob -i "${OUTPUT_DIR.replace(/\//g, '/')}/*.png"`,
  `-vf "scale=1280:720:force_original_aspect_ratio=decrease,pad=1280:720:(ow-iw)/2:(oh-ih)/2:black,format=yuv420p"`,
  `-c:v libx264 -preset slow -crf 18`,
  `-movflags +faststart`,
  `"${VIDEO_OUT}"`
].join(' ');

try {
  execSync(ffmpegCmd, { stdio: 'pipe' });
  console.log(`\n🎬 Vidéo générée : ${VIDEO_OUT}`);
} catch (e) {
  console.error('ffmpeg error:', e.message);
  // Fallback: simpler ffmpeg command
  const fallbackCmd = [
    'ffmpeg -y',
    `-framerate 24`,
    `-i "${OUTPUT_DIR.replace(/\//g, '/')}/frame_%04d_*.png"`,
    `-c:v libx264 -pix_fmt yuv420p`,
    `"${VIDEO_OUT}"`
  ].join(' ');
  console.log('Trying fallback command...');
  execSync(fallbackCmd, { stdio: 'inherit' });
}
