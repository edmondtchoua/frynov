<?php

namespace App\Modules\ImportExport\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Customers\Models\Customer;
use App\Modules\Suppliers\Models\Supplier;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports entity lists as formatted Excel files.
 */
class ExcelExporter
{
    public function exportProducts(string $tenantId, array $filters = []): StreamedResponse
    {
        $query = Product::with(['category', 'supplier'])
            ->where('tenant_id', $tenantId)
            ->orderBy('name');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        $products = $query->get();

        $headers = ['SKU', 'Nom Produit', 'Description', 'Prix', 'Coût', 'Code Barre', 'Catégorie', 'Fournisseur', 'Poids (kg)', 'Statut'];
        $rows = $products->map(fn(Product $p) => [
            $p->sku,
            $p->name,
            $p->description,
            number_format($p->price_amount / 100, 2, '.', ''),
            $p->cost_amount ? number_format($p->cost_amount / 100, 2, '.', '') : '',
            $p->barcode,
            $p->category?->name,
            $p->supplier?->name,
            $p->weight_kg,
            $p->status,
        ]);

        $spreadsheet = $this->buildSpreadsheet('Produits', $headers, $rows->all());
        return $this->stream($spreadsheet, 'export_produits_' . date('Ymd') . '.xlsx');
    }

    public function exportCustomers(string $tenantId, array $filters = []): StreamedResponse
    {
        $customers = Customer::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();

        $headers = ['Nom', 'Email', 'Téléphone', 'Adresse', 'Notes', 'Créé le'];
        $rows = $customers->map(fn(Customer $c) => [
            $c->name,
            $c->email,
            $c->phone,
            is_array($c->address) ? implode(', ', array_filter($c->address)) : $c->address,
            $c->notes,
            $c->created_at?->format('d/m/Y'),
        ]);

        $spreadsheet = $this->buildSpreadsheet('Clients', $headers, $rows->all());
        return $this->stream($spreadsheet, 'export_clients_' . date('Ymd') . '.xlsx');
    }

    public function exportSuppliers(string $tenantId, array $filters = []): StreamedResponse
    {
        $suppliers = Supplier::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();

        $headers = ['Code', 'Nom', 'Email', 'Téléphone', 'Contact', 'Conditions Paiement', 'Statut', 'Créé le'];
        $rows = $suppliers->map(fn(Supplier $s) => [
            $s->code,
            $s->name,
            $s->email,
            $s->phone,
            $s->contact_name,
            $s->payment_terms,
            $s->status,
            $s->created_at?->format('d/m/Y'),
        ]);

        $spreadsheet = $this->buildSpreadsheet('Fournisseurs', $headers, $rows->all());
        return $this->stream($spreadsheet, 'export_fournisseurs_' . date('Ymd') . '.xlsx');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function buildSpreadsheet(string $title, array $headers, array $rows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle($title);

        // Header row
        foreach ($headers as $colIdx => $header) {
            $col  = chr(65 + $colIdx);
            $cell = $col . '1';
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a56db']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
            ]);
            $sheet->getColumnDimensionByColumn($colIdx + 1)->setAutoSize(true);
        }

        // Data rows
        foreach ($rows as $rowIdx => $row) {
            $rowNum  = $rowIdx + 2;
            $bgColor = $rowIdx % 2 === 0 ? 'FFFFFF' : 'F7F9FF';

            foreach ($row as $colIdx => $value) {
                $col  = chr(65 + $colIdx);
                $cell = $col . $rowNum;
                $sheet->setCellValue($cell, $value ?? '');
                $sheet->getStyle($cell)->applyFromArray([
                    'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'EEEEEE']]],
                ]);
            }
        }

        // Freeze header
        $sheet->freezePane('A2');

        $spreadsheet->getProperties()
            ->setCreator('Frynov ERP')
            ->setTitle($title . ' — Export ' . date('d/m/Y'));

        return $spreadsheet;
    }

    private function stream(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }
}
