<?php

namespace App\Modules\ImportExport\Parsers;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\ImportExport\Models\ImportRow;
use App\Modules\Suppliers\Models\Supplier;

/**
 * Validates and enriches a mapped product row.
 * Returns status, errors, warnings and resolved action (create|update|skip).
 */
class ProductImportParser
{
    private string $tenantId;
    private string $mode;

    // Caches to avoid N+1 queries across rows
    private array $skuIndex      = [];  // sku => product_id
    private array $barcodeIndex  = [];  // barcode => product_id
    private array $categoryCache = [];  // lowercase name => category_id
    private array $supplierCache = [];  // lowercase name => supplier_id
    private bool  $indexed       = false;

    public function __construct(string $tenantId, string $mode)
    {
        $this->tenantId = $tenantId;
        $this->mode     = $mode;
    }

    /**
     * Pre-load existing SKUs and barcodes for the tenant to detect duplicates.
     */
    public function buildIndex(): void
    {
        if ($this->indexed) {
            return;
        }

        Product::where('tenant_id', $this->tenantId)
            ->whereNull('deleted_at')
            ->get(['id', 'sku', 'barcode'])
            ->each(function (Product $p) {
                if ($p->sku) {
                    $this->skuIndex[strtolower($p->sku)] = $p->id;
                }
                if ($p->barcode) {
                    $this->barcodeIndex[strtolower($p->barcode)] = $p->id;
                }
            });

        $this->indexed = true;
    }

    /**
     * Parse and validate one mapped row.
     *
     * @param  array $mapped   [ system_field => value ]
     * @param  int   $rowNum   1-based row number (for error messages)
     * @return array { status, action, entity_id, errors, warnings, mapped_data }
     */
    public function parseRow(array $mapped, int $rowNum): array
    {
        $this->buildIndex();

        $errors   = [];
        $warnings = [];

        // ── Required fields ───────────────────────────────────────────────
        $sku = trim($mapped['sku'] ?? '');
        if ($sku === '') {
            $errors[] = ['field' => 'sku', 'message' => 'Le SKU est obligatoire.'];
        }

        $name = trim($mapped['name'] ?? '');
        if ($name === '') {
            $errors[] = ['field' => 'name', 'message' => 'Le nom du produit est obligatoire.'];
        }

        $rawPrice = $mapped['price'] ?? null;
        $priceCents = $this->parseMoney($rawPrice);
        if ($rawPrice !== null && $rawPrice !== '' && $priceCents === null) {
            $errors[] = ['field' => 'price', 'message' => "Prix invalide: «{$rawPrice}»."];
        } elseif ($rawPrice === null || $rawPrice === '') {
            $errors[] = ['field' => 'price', 'message' => 'Le prix est obligatoire.'];
        } elseif ($priceCents !== null && $priceCents < 0) {
            $errors[] = ['field' => 'price', 'message' => 'Le prix ne peut pas être négatif.'];
        }

        // ── Optional numeric fields ───────────────────────────────────────
        $rawCost = $mapped['cost'] ?? null;
        $costCents = null;
        if ($rawCost !== null && $rawCost !== '') {
            $costCents = $this->parseMoney($rawCost);
            if ($costCents === null) {
                $warnings[] = ['field' => 'cost', 'message' => "Coût ignoré (valeur invalide: «{$rawCost}»)."];
            }
        }

        $rawWeight = $mapped['weight_kg'] ?? null;
        $weightKg  = null;
        if ($rawWeight !== null && $rawWeight !== '') {
            $weightKg = (float) str_replace(',', '.', $rawWeight);
            if ($weightKg < 0) {
                $warnings[] = ['field' => 'weight_kg', 'message' => 'Poids ignoré (valeur négative).'];
                $weightKg = null;
            }
        }

        // ── Status ────────────────────────────────────────────────────────
        $rawStatus = strtolower(trim($mapped['status'] ?? 'active'));
        $status    = in_array($rawStatus, ['active', 'actif', '1', 'oui', 'yes']) ? 'active' : 'draft';

        // ── Category resolution ───────────────────────────────────────────
        $categoryId = null;
        $rawCategory = trim($mapped['category'] ?? '');
        if ($rawCategory !== '') {
            $categoryId = $this->resolveCategory($rawCategory);
            if ($categoryId === null) {
                $warnings[] = ['field' => 'category', 'message' => "Catégorie «{$rawCategory}» introuvable — sera créée lors de l'import."];
            }
        }

        // ── Supplier resolution ───────────────────────────────────────────
        $supplierId = null;
        $rawSupplier = trim($mapped['supplier'] ?? '');
        if ($rawSupplier !== '') {
            $supplierId = $this->resolveSupplier($rawSupplier);
            if ($supplierId === null) {
                $warnings[] = ['field' => 'supplier', 'message' => "Fournisseur «{$rawSupplier}» introuvable — sera créé lors de l'import."];
            }
        }

        // ── Duplicate / mode check ────────────────────────────────────────
        $existingId = null;
        $action     = ImportRow::ACTION_CREATE;

        if ($sku !== '') {
            $existingId = $this->skuIndex[strtolower($sku)] ?? null;

            $barcode = trim($mapped['barcode'] ?? '');
            if ($existingId === null && $barcode !== '') {
                $existingId = $this->barcodeIndex[strtolower($barcode)] ?? null;
            }
        }

        if ($existingId) {
            if ($this->mode === 'create_only') {
                $action = ImportRow::ACTION_SKIP;
                $warnings[] = ['field' => 'sku', 'message' => "Produit avec SKU «{$sku}» existe déjà (mode create_only → ignoré)."];
            } else {
                $action = ImportRow::ACTION_UPDATE;
            }
        } else {
            if ($this->mode === 'update_only') {
                $action = ImportRow::ACTION_SKIP;
                $warnings[] = ['field' => 'sku', 'message' => "Produit avec SKU «{$sku}» introuvable (mode update_only → ignoré)."];
            }
        }

        // ── Final status decision ─────────────────────────────────────────
        if (!empty($errors)) {
            $rowStatus = ImportRow::STATUS_ERROR;
        } elseif ($action === ImportRow::ACTION_SKIP) {
            $rowStatus = ImportRow::STATUS_WARNING;
        } elseif (!empty($warnings)) {
            $rowStatus = ImportRow::STATUS_WARNING;
        } else {
            $rowStatus = ImportRow::STATUS_VALID;
        }

        // ── Build normalized mapped data ──────────────────────────────────
        $mappedData = [
            'sku'           => $sku ?: null,
            'name'          => $name ?: null,
            'description'   => trim($mapped['description'] ?? '') ?: null,
            'price_amount'  => $priceCents,
            'price_currency'=> 'XOF',   // tenant default; can be extended
            'cost_amount'   => $costCents,
            'barcode'       => trim($mapped['barcode'] ?? '') ?: null,
            'weight_kg'     => $weightKg,
            'status'        => $status,
            'category_id'   => $categoryId,
            'supplier_id'   => $supplierId,
            // Keep raw names for deferred resolution during execution
            '_raw_category' => $rawCategory ?: null,
            '_raw_supplier' => $rawSupplier ?: null,
        ];

        return [
            'status'      => $rowStatus,
            'action'      => $action,
            'entity_id'   => $existingId,
            'errors'      => $errors,
            'warnings'    => $warnings,
            'mapped_data' => $mappedData,
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function parseMoney(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        // Accept "1 234,56" or "1234.56" or "1234"
        $clean = str_replace([' ', "\u{00A0}"], '', (string) $value);
        $clean = str_replace(',', '.', $clean);
        if (!is_numeric($clean)) {
            return null;
        }
        return (int) round((float) $clean * 100);
    }

    private function resolveCategory(string $name): ?string
    {
        $key = strtolower($name);
        if (array_key_exists($key, $this->categoryCache)) {
            return $this->categoryCache[$key];
        }

        $cat = Category::where('tenant_id', $this->tenantId)
            ->whereRaw('LOWER(name) = ?', [$key])
            ->whereNull('deleted_at')
            ->first();

        $this->categoryCache[$key] = $cat?->id;
        return $this->categoryCache[$key];
    }

    private function resolveSupplier(string $name): ?string
    {
        $key = strtolower($name);
        if (array_key_exists($key, $this->supplierCache)) {
            return $this->supplierCache[$key];
        }

        $sup = Supplier::where('tenant_id', $this->tenantId)
            ->whereRaw('LOWER(name) = ?', [$key])
            ->whereNull('deleted_at')
            ->first();

        $this->supplierCache[$key] = $sup?->id;
        return $this->supplierCache[$key];
    }

    /**
     * Register newly created entity IDs in caches to avoid
     * re-creating the same category/supplier for subsequent rows.
     */
    public function registerCategory(string $name, string $id): void
    {
        $this->categoryCache[strtolower($name)] = $id;
    }

    public function registerSupplier(string $name, string $id): void
    {
        $this->supplierCache[strtolower($name)] = $id;
    }

    public function registerSku(string $sku, string $id): void
    {
        $this->skuIndex[strtolower($sku)] = $id;
    }
}
