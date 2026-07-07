/**
 * RC-19 — impression du ticket de caisse.
 *
 * `RECEIPT_CSS` est la source UNIQUE du style ticket (préviewé dans PosReceipt.vue et injecté tel
 * quel dans le document d'impression). Format pensé pour les imprimantes thermiques 80 mm
 * (largeur utile ~72 mm, police monospace, pas de couleur), lisible aussi en A4.
 *
 * `printHtml` imprime via une IFRAME CACHÉE (pas de popup → pas de blocage bloqueur de popups,
 * pas de fenêtre orpheline). L'iframe est retirée après l'impression.
 */
export const RECEIPT_CSS = `
.pos-receipt { width: 280px; margin: 0 auto; font-family: 'Courier New', ui-monospace, monospace; font-size: 12px; color: #000; background: #fff; padding: 8px 6px; }
.pos-receipt * { box-sizing: border-box; }
.pos-receipt .r-center { text-align: center; }
.pos-receipt .r-name { font-size: 14px; font-weight: 700; text-transform: uppercase; }
.pos-receipt .r-muted { font-size: 11px; }
.pos-receipt .r-sep { border: none; border-top: 1px dashed #000; margin: 6px 0; }
.pos-receipt .r-row { display: flex; justify-content: space-between; gap: 8px; }
.pos-receipt .r-line-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pos-receipt .r-qty { white-space: nowrap; }
.pos-receipt .r-amount { white-space: nowrap; text-align: right; }
.pos-receipt .r-total { font-size: 14px; font-weight: 700; }
.pos-receipt .r-footer { margin-top: 8px; font-size: 11px; }
@media print {
  @page { margin: 0; size: 80mm auto; }
  body { margin: 0; }
}
`

export function printHtml(innerHtml: string, css: string = RECEIPT_CSS): void {
  const iframe = document.createElement('iframe')
  iframe.style.position = 'fixed'
  iframe.style.right = '0'
  iframe.style.bottom = '0'
  iframe.style.width = '0'
  iframe.style.height = '0'
  iframe.style.border = '0'
  document.body.appendChild(iframe)

  const doc = iframe.contentDocument
  if (!doc) {
    document.body.removeChild(iframe)
    return
  }

  doc.open()
  doc.write(`<!doctype html><html><head><meta charset="utf-8"><style>${css}</style></head><body>${innerHtml}</body></html>`)
  doc.close()

  // Laisser le layout se poser avant de lancer l'impression, puis nettoyer.
  const win = iframe.contentWindow
  setTimeout(() => {
    try {
      win?.focus()
      win?.print()
    } finally {
      setTimeout(() => { document.body.removeChild(iframe) }, 1000)
    }
  }, 50)
}
