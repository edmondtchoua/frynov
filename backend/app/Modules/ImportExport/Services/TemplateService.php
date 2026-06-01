<?php

namespace App\Modules\ImportExport\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generates downloadable Excel import templates with:
 * - Styled header row
 * - Example data row
 * - Column notes (required / optional)
 * - Data validation dropdowns where applicable
 */
class TemplateService
{
    private static array $templates = [
        'products' => [
            'title'    => 'Import Produits — Frynov ERP',
            'filename' => 'template_produits.xlsx',
            'headers'  => [
                'SKU'             => ['required' => true,  'note' => 'Identifiant unique du produit (obligatoire)'],
                'Nom Produit'     => ['required' => true,  'note' => 'Nom du produit (obligatoire)'],
                'Prix'            => ['required' => true,  'note' => 'Prix de vente en devise locale (ex: 15000)'],
                'Description'     => ['required' => false, 'note' => 'Description détaillée'],
                'Coût'            => ['required' => false, 'note' => "Prix d'achat (optionnel)"],
                'Code Barre'      => ['required' => false, 'note' => 'EAN/GTIN (optionnel)'],
                'Catégorie'       => ['required' => false, 'note' => 'Nom de catégorie — créée si inexistante'],
                'Fournisseur'     => ['required' => false, 'note' => 'Nom du fournisseur — créé si inexistant'],
                'Poids (kg)'      => ['required' => false, 'note' => 'Poids en kilogrammes (ex: 1.5)'],
                'Statut'          => ['required' => false, 'note' => 'active ou draft (défaut: active)'],
            ],
            'example' => ['PROD-001', 'T-Shirt Coton Premium', '15000', 'T-shirt 100% coton, coupe moderne', '8000', '5901234123457', 'Vêtements', 'Fournisseur ABC', '0.3', 'active'],
        ],
        'customers' => [
            'title'    => 'Import Clients — Frynov ERP',
            'filename' => 'template_clients.xlsx',
            'headers'  => [
                'Nom'         => ['required' => true,  'note' => 'Nom complet ou raison sociale (obligatoire)'],
                'Email'       => ['required' => false, 'note' => 'Adresse email (détection doublon)'],
                'Téléphone'   => ['required' => false, 'note' => 'Numéro de téléphone (détection doublon)'],
                'Adresse'     => ['required' => false, 'note' => 'Adresse complète (optionnel)'],
                'Notes'       => ['required' => false, 'note' => 'Commentaires internes (optionnel)'],
            ],
            'example' => ['Amadou Traoré', 'amadou@exemple.com', '+225 07 00 00 00', 'Abidjan, Plateau', ''],
        ],
        'suppliers' => [
            'title'    => 'Import Fournisseurs — Frynov ERP',
            'filename' => 'template_fournisseurs.xlsx',
            'headers'  => [
                'Nom'                  => ['required' => true,  'note' => 'Raison sociale (obligatoire)'],
                'Code Fournisseur'     => ['required' => false, 'note' => 'Code unique — généré automatiquement si vide'],
                'Email'                => ['required' => false, 'note' => 'Email principal (détection doublon)'],
                'Téléphone'            => ['required' => false, 'note' => 'Numéro de téléphone'],
                'Contact'              => ['required' => false, 'note' => "Nom de l'interlocuteur principal"],
                'Conditions Paiement'  => ['required' => false, 'note' => 'Ex: Net 30, Net 60'],
                'Notes'                => ['required' => false, 'note' => 'Commentaires internes'],
                'Statut'               => ['required' => false, 'note' => 'active ou inactive (défaut: active)'],
            ],
            'example' => ['TextilePro Sarl', 'SUP-0001', 'contact@textilepro.com', '+225 27 20 00 00', 'M. Koné', 'Net 30', '', 'active'],
        ],
    ];

    public function download(string $entityType): StreamedResponse
    {
        $tpl = self::$templates[$entityType]
            ?? throw new \InvalidArgumentException("Type inconnu: {$entityType}");

        $spreadsheet = $this->build($tpl);
        $filename    = $tpl['filename'];

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    private function build(array $tpl): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Import');

        $headers = array_keys($tpl['headers']);
        $colCount = count($headers);

        // ── Header row (row 1) ────────────────────────────────────────────
        foreach ($headers as $colIdx => $header) {
            $col     = chr(65 + $colIdx);
            $cell    = $col . '1';
            $meta    = $tpl['headers'][$header];
            $label   = $meta['required'] ? $header . ' *' : $header;

            $sheet->setCellValue($cell, $label);
            $sheet->getComment($cell)->getText()->createTextRun($meta['note']);

            // Header style
            $bgColor = $meta['required'] ? '1a56db' : '4e81bd';
            $sheet->getStyle($cell)->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
            ]);
            $sheet->getColumnDimensionByColumn($colIdx + 1)->setAutoSize(true);
        }

        // ── Example row (row 2) ───────────────────────────────────────────
        $example = $tpl['example'];
        foreach ($example as $colIdx => $value) {
            $colLetter = chr(65 + $colIdx);
            $exCell    = $colLetter . '2';
            $sheet->setCellValue($exCell, $value);
            $sheet->getStyle($exCell)->applyFromArray([
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF2FF']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
            ]);
        }

        // ── Legend below ─────────────────────────────────────────────────
        $legendRow = 4;
        $sheet->setCellValue('A' . $legendRow, '* Champ obligatoire. Survolez les en-têtes pour afficher les consignes.');
        $sheet->getStyle('A' . $legendRow)->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '888888'], 'size' => 9],
        ]);
        $sheet->mergeCells('A' . $legendRow . ':' . chr(64 + $colCount) . $legendRow);

        // Freeze header row
        $sheet->freezePane('A2');

        $spreadsheet->getProperties()
            ->setCreator('Frynov ERP')
            ->setTitle($tpl['title']);

        return $spreadsheet;
    }
}
