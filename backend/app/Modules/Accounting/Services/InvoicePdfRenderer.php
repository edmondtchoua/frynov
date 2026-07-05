<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Invoice;
use App\Modules\Tenants\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * RC-30 — rendu PDF d'une facture (DomPDF, réutilise le pattern ImportExport/PdfExporter).
 * HTML inline (pas de vue Blade), A4 portrait.
 */
class InvoicePdfRenderer
{
    public function download(Invoice $invoice): Response
    {
        $invoice->loadMissing('lines');
        $tenant   = Tenant::withoutGlobalScopes()->find($invoice->tenant_id);
        $settings = $tenant?->settings ?? [];
        $currency = $invoice->currency;

        $fmt = fn (int $minor) => $this->money($minor, $currency);

        $rows = '';
        foreach ($invoice->lines as $l) {
            $rows .= '<tr>'
                . '<td>' . e($l->label) . '</td>'
                . '<td class="num">' . $l->quantity . '</td>'
                . '<td class="num">' . $fmt($l->unit_price_minor) . '</td>'
                . '<td class="num">' . ($l->discount_bp > 0 ? number_format($l->discount_bp / 100, 2) . ' %' : '—') . '</td>'
                . '<td class="num">' . $fmt($l->subtotal_minor) . '</td>'
                . '<td class="num">' . $fmt($l->tax_minor) . '</td>'
                . '<td class="num">' . $fmt($l->total_minor) . '</td>'
                . '</tr>';
        }

        $isCreditNote = $invoice->isCreditNote();
        $docTitle     = $isCreditNote ? 'AVOIR' : 'FACTURE';
        $settledLabel = $isCreditNote ? 'Appliqué' : 'Réglé';
        $remainLabel  = $isCreditNote ? 'Reste à appliquer' : 'Reste dû';
        $settledMinor = (int) $invoice->paid_minor + (int) $invoice->credited_minor;

        $businessName = e($tenant?->name ?? '');
        $address      = e($settings['address'] ?? '');
        $phone        = e($settings['phone'] ?? '');
        $customer     = e($invoice->customer_name ?? '—');
        $number       = e($invoice->number ?? 'BROUILLON');
        $issue        = $invoice->issue_date?->format('d/m/Y') ?? '—';
        $due          = $invoice->due_date?->format('d/m/Y') ?? '—';

        $html = <<<HTML
        <html><head><meta charset="utf-8"><style>
          body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
          .head { display: flex; justify-content: space-between; margin-bottom: 24px; }
          .biz-name { font-size: 18px; font-weight: bold; }
          .muted { color: #666; font-size: 11px; }
          h1 { font-size: 22px; margin: 0 0 4px; }
          table { width: 100%; border-collapse: collapse; margin-top: 16px; }
          th { background: #f3f4f6; text-align: left; padding: 6px 8px; font-size: 11px; text-transform: uppercase; }
          td { padding: 6px 8px; border-bottom: 1px solid #eee; }
          .num { text-align: right; }
          .totals { margin-top: 16px; width: 40%; float: right; }
          .totals td { border: none; padding: 3px 8px; }
          .grand { font-weight: bold; font-size: 14px; border-top: 2px solid #111; }
          .foot { clear: both; margin-top: 60px; color: #666; font-size: 10px; text-align: center; }
        </style></head><body>
          <div class="head">
            <div>
              <div class="biz-name">{$businessName}</div>
              <div class="muted">{$address}</div>
              <div class="muted">{$phone}</div>
            </div>
            <div style="text-align:right">
              <h1>{$docTitle}</h1>
              <div><strong>{$number}</strong></div>
              <div class="muted">Émis le {$issue}</div>
              <div class="muted">Échéance {$due}</div>
            </div>
          </div>
          <div><strong>Client :</strong> {$customer}</div>
          <table>
            <thead><tr>
              <th>Désignation</th><th class="num">Qté</th><th class="num">P.U. HT</th>
              <th class="num">Remise</th><th class="num">HT</th><th class="num">TVA</th><th class="num">TTC</th>
            </tr></thead>
            <tbody>{$rows}</tbody>
          </table>
          <table class="totals">
            <tr><td>Total HT</td><td class="num">{$fmt($invoice->subtotal_minor)}</td></tr>
            <tr><td>TVA</td><td class="num">{$fmt($invoice->tax_total_minor)}</td></tr>
            <tr class="grand"><td>Total TTC</td><td class="num">{$fmt($invoice->total_minor)}</td></tr>
            <tr><td>{$settledLabel}</td><td class="num">{$fmt($settledMinor)}</td></tr>
            <tr><td>{$remainLabel}</td><td class="num">{$fmt($invoice->remainingMinor())}</td></tr>
          </table>
          <div class="foot">Document généré par Frynov ERP</div>
        </body></html>
        HTML;

        $filePrefix = $isCreditNote ? 'avoir_' : 'facture_';

        return Pdf::loadHTML($html)->setPaper('a4', 'portrait')
            ->download($filePrefix . ($invoice->number ?? $invoice->id) . '.pdf');
    }

    private function money(int $minor, string $currency): string
    {
        // XOF/XAF sans décimales ; sinon 2 décimales. Cohérent avec l'affichage front.
        $noDecimals = in_array($currency, ['XOF', 'XAF'], true);
        $value = $noDecimals ? (string) intdiv($minor, 100) : number_format($minor / 100, 2, ',', ' ');
        if ($noDecimals) {
            $value = number_format((int) $value, 0, ',', ' ');
        }

        return $value . ' ' . $currency;
    }
}
