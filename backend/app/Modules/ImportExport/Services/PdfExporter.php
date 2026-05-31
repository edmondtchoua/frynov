<?php

namespace App\Modules\ImportExport\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Customers\Models\Customer;
use App\Modules\ImportExport\Models\ImportSession;
use App\Modules\Suppliers\Models\Supplier;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exports entity lists and import reports as PDF using DomPDF.
 */
class PdfExporter
{
    public function exportProducts(string $tenantId, array $filters = []): Response
    {
        $products = Product::with(['category', 'supplier'])
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->when(!empty($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->get();

        $html = $this->renderHtml('products', [
            'title'    => 'Liste des Produits',
            'date'     => now()->format('d/m/Y H:i'),
            'products' => $products,
        ]);

        return $this->streamPdf($html, 'export_produits_' . date('Ymd') . '.pdf');
    }

    public function exportCustomers(string $tenantId): Response
    {
        $customers = Customer::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();

        $html = $this->renderHtml('customers', [
            'title'     => 'Liste des Clients',
            'date'      => now()->format('d/m/Y H:i'),
            'customers' => $customers,
        ]);

        return $this->streamPdf($html, 'export_clients_' . date('Ymd') . '.pdf');
    }

    public function exportSuppliers(string $tenantId): Response
    {
        $suppliers = Supplier::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();

        $html = $this->renderHtml('suppliers', [
            'title'     => 'Liste des Fournisseurs',
            'date'      => now()->format('d/m/Y H:i'),
            'suppliers' => $suppliers,
        ]);

        return $this->streamPdf($html, 'export_fournisseurs_' . date('Ymd') . '.pdf');
    }

    public function exportImportReport(ImportSession $session): Response
    {
        $rows = $session->rows()->orderBy('row_number')->get();

        $html = $this->renderHtml('import_report', [
            'title'    => 'Rapport d\'Import — ' . ucfirst($session->type),
            'date'     => now()->format('d/m/Y H:i'),
            'session'  => $session,
            'rows'     => $rows,
        ]);

        return $this->streamPdf($html, 'rapport_import_' . $session->id . '.pdf');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function renderHtml(string $view, array $data): string
    {
        // Inline HTML generation to avoid needing Blade view files
        return match ($view) {
            'products'      => $this->productsHtml($data),
            'customers'     => $this->customersHtml($data),
            'suppliers'     => $this->suppliersHtml($data),
            'import_report' => $this->importReportHtml($data),
            default         => '',
        };
    }

    private function streamPdf(string $html, string $filename): Response
    {
        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'landscape');
        return $pdf->download($filename);
    }

    private function baseStyle(): string
    {
        return <<<CSS
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e2a3b; margin: 20px; }
        h1   { font-size: 16px; color: #1a56db; margin-bottom: 4px; }
        .meta { font-size: 9px; color: #888; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: #1a56db; color: #fff; }
        th { padding: 6px 8px; text-align: left; font-weight: bold; }
        td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; }
        tr:nth-child(even) td { background: #f7f9ff; }
        .badge-active   { background: #d1fae5; color: #065f46; padding: 1px 6px; border-radius: 10px; }
        .badge-draft    { background: #f3f4f6; color: #374151; padding: 1px 6px; border-radius: 10px; }
        .badge-error    { background: #fee2e2; color: #991b1b; padding: 1px 6px; border-radius: 10px; }
        .badge-imported { background: #d1fae5; color: #065f46; padding: 1px 6px; border-radius: 10px; }
        CSS;
    }

    private function productsHtml(array $data): string
    {
        $style    = $this->baseStyle();
        $products = $data['products'];
        $rows     = '';

        foreach ($products as $p) {
            $status = "<span class=\"badge-{$p->status}\">{$p->status}</span>";
            $price  = number_format($p->price_amount / 100, 0, '.', ' ');
            $rows  .= "<tr>
                <td>{$p->sku}</td>
                <td>{$p->name}</td>
                <td>{$p->category?->name}</td>
                <td>{$p->supplier?->name}</td>
                <td style=\"text-align:right\">{$price}</td>
                <td>{$status}</td>
            </tr>";
        }

        return <<<HTML
        <!DOCTYPE html><html><head><meta charset="utf-8"><style>{$style}</style></head><body>
        <h1>{$data['title']}</h1>
        <div class="meta">Exporté le {$data['date']} — {$products->count()} produit(s)</div>
        <table>
            <thead><tr><th>SKU</th><th>Nom</th><th>Catégorie</th><th>Fournisseur</th><th>Prix</th><th>Statut</th></tr></thead>
            <tbody>{$rows}</tbody>
        </table>
        </body></html>
        HTML;
    }

    private function customersHtml(array $data): string
    {
        $style     = $this->baseStyle();
        $customers = $data['customers'];
        $rows      = '';

        foreach ($customers as $c) {
            $rows .= "<tr>
                <td>{$c->name}</td>
                <td>{$c->email}</td>
                <td>{$c->phone}</td>
                <td>{$c->created_at?->format('d/m/Y')}</td>
            </tr>";
        }

        return <<<HTML
        <!DOCTYPE html><html><head><meta charset="utf-8"><style>{$style}</style></head><body>
        <h1>{$data['title']}</h1>
        <div class="meta">Exporté le {$data['date']} — {$customers->count()} client(s)</div>
        <table>
            <thead><tr><th>Nom</th><th>Email</th><th>Téléphone</th><th>Créé le</th></tr></thead>
            <tbody>{$rows}</tbody>
        </table>
        </body></html>
        HTML;
    }

    private function suppliersHtml(array $data): string
    {
        $style     = $this->baseStyle();
        $suppliers = $data['suppliers'];
        $rows      = '';

        foreach ($suppliers as $s) {
            $status = "<span class=\"badge-{$s->status}\">{$s->status}</span>";
            $rows  .= "<tr>
                <td>{$s->code}</td>
                <td>{$s->name}</td>
                <td>{$s->email}</td>
                <td>{$s->phone}</td>
                <td>{$s->contact_name}</td>
                <td>{$status}</td>
            </tr>";
        }

        return <<<HTML
        <!DOCTYPE html><html><head><meta charset="utf-8"><style>{$style}</style></head><body>
        <h1>{$data['title']}</h1>
        <div class="meta">Exporté le {$data['date']} — {$suppliers->count()} fournisseur(s)</div>
        <table>
            <thead><tr><th>Code</th><th>Nom</th><th>Email</th><th>Téléphone</th><th>Contact</th><th>Statut</th></tr></thead>
            <tbody>{$rows}</tbody>
        </table>
        </body></html>
        HTML;
    }

    private function importReportHtml(array $data): string
    {
        $style   = $this->baseStyle();
        $session = $data['session'];
        $rows    = $data['rows'];
        $rowsHtml = '';

        foreach ($rows as $r) {
            $statusClass = match ($r->status) {
                'imported' => 'badge-imported',
                'error'    => 'badge-error',
                default    => 'badge-draft',
            };
            $errorsText = '';
            if (!empty($r->errors)) {
                $errorsText = implode(' | ', array_column($r->errors, 'message'));
            }
            $statusBadge = "<span class=\"{$statusClass}\">{$r->status}</span>";
            $mappedName  = $r->mapped_data['name'] ?? $r->mapped_data['sku'] ?? '—';

            $rowsHtml .= "<tr>
                <td>{$r->row_number}</td>
                <td>{$mappedName}</td>
                <td>{$r->action}</td>
                <td>{$statusBadge}</td>
                <td style=\"font-size:8px;color:#666\">{$errorsText}</td>
            </tr>";
        }

        $summary = $session->summary ?? [];
        $created = $summary['created'] ?? 0;
        $updated = $summary['updated'] ?? 0;
        $errors  = $summary['errors'] ?? 0;

        return <<<HTML
        <!DOCTYPE html><html><head><meta charset="utf-8"><style>{$style}</style></head><body>
        <h1>{$data['title']}</h1>
        <div class="meta">
            Généré le {$data['date']} | Fichier: {$session->original_filename} |
            Mode: {$session->mode} | Créés: {$created} | Mis à jour: {$updated} | Erreurs: {$errors}
        </div>
        <table>
            <thead><tr><th>#</th><th>Entité</th><th>Action</th><th>Statut</th><th>Erreurs</th></tr></thead>
            <tbody>{$rowsHtml}</tbody>
        </table>
        </body></html>
        HTML;
    }
}
