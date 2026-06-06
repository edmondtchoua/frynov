<?php

namespace App\Modules\ImportExport\Services;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customers\Models\Customer;
use App\Modules\ImportExport\Jobs\AnalyzeImportJob;
use App\Modules\ImportExport\Jobs\ExecuteImportJob;
use App\Modules\ImportExport\Models\ImportRow;
use App\Modules\ImportExport\Models\ImportSession;
use App\Modules\ImportExport\Parsers\ColumnMapper;
use App\Modules\ImportExport\Parsers\CustomerImportParser;
use App\Modules\ImportExport\Parsers\ProductImportParser;
use App\Modules\ImportExport\Parsers\SupplierImportParser;
use App\Modules\Suppliers\Models\Supplier;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Str;

class ImportService
{
    private const DISK          = 'local';
    private const UPLOAD_PATH   = 'imports';
    private const LARGE_ROW_THRESHOLD = 200; // rows; above this → dispatch job

    // ── Upload ────────────────────────────────────────────────────────────────

    /**
     * Store the uploaded file and create a draft ImportSession.
     */
    public function upload(
        UploadedFile $file,
        string       $entityType,
        string       $mode,
        string       $tenantId,
        string       $userId
    ): ImportSession {
        $this->assertValidType($entityType);
        $this->assertValidMode($mode);

        $filename   = $file->getClientOriginalName();
        $stored     = $file->storeAs(
            self::UPLOAD_PATH . '/' . $tenantId,
            Str::uuid() . '.' . $file->getClientOriginalExtension(),
            self::DISK
        );

        return ImportSession::create([
            'tenant_id'         => $tenantId,
            'performed_by'      => $userId,
            'type'              => $entityType,
            'mode'              => $mode,
            'status'            => ImportSession::STATUS_DRAFT,
            'original_filename' => $filename,
            'stored_path'       => $stored,
        ]);
    }

    // ── Analyze ───────────────────────────────────────────────────────────────

    /**
     * Kick off analysis: small files run inline, large files via Horizon job.
     */
    public function analyze(ImportSession $session): ImportSession
    {
        if (!$session->isDraft()) {
            throw new DomainException("Impossible d'analyser une session au statut «{$session->status}».");
        }

        $session->update(['status' => ImportSession::STATUS_ANALYZING]);

        // Read file to decide sync vs async
        $rows = $this->readFile($session);

        if (count($rows) > self::LARGE_ROW_THRESHOLD) {
            AnalyzeImportJob::dispatch($session->id);
            return $session->fresh();
        }

        return $this->runAnalysis($session, $rows);
    }

    /**
     * Core analysis logic (used by both sync path and AnalyzeImportJob).
     */
    public function runAnalysis(ImportSession $session, ?array $rows = null): ImportSession
    {
        try {
            $rows   = $rows ?? $this->readFile($session);
            $headers = array_shift($rows) ?? [];

            $autoMapping  = ColumnMapper::autoMap($headers, $session->type);
            $userMapping  = $session->column_mapping ?? [];
            $finalMapping = array_merge($autoMapping, $userMapping);

            // Update mapping on session so user can review/correct it
            $session->update([
                'column_mapping' => $finalMapping,
                'total_rows'     => count($rows),
            ]);

            $parser    = $this->makeParser($session);
            $validRows = $errorRows = $warningRows = 0;

            DB::transaction(function () use ($session, $rows, $finalMapping, $parser, &$validRows, &$errorRows, &$warningRows) {
                // Delete any previous rows from a re-analysis
                ImportRow::where('session_id', $session->id)->delete();

                foreach ($rows as $idx => $rawRow) {
                    $rowNum  = $idx + 1;
                    $rawAssoc = array_combine(array_keys($finalMapping), array_pad(array_values($rawRow), count($finalMapping), null));
                    $mapped  = ColumnMapper::applyMapping($rawAssoc, $finalMapping);
                    $result  = $parser->parseRow($mapped, $rowNum);

                    ImportRow::create([
                        'session_id'  => $session->id,
                        'row_number'  => $rowNum,
                        'status'      => $result['status'],
                        'raw_data'    => $rawRow,
                        'mapped_data' => $result['mapped_data'],
                        'errors'      => $result['errors'] ?: null,
                        'warnings'    => $result['warnings'] ?: null,
                        'action'      => $result['action'],
                        'entity_id'   => $result['entity_id'],
                    ]);

                    match ($result['status']) {
                        ImportRow::STATUS_ERROR   => $errorRows++,
                        ImportRow::STATUS_WARNING => $warningRows++,
                        default                   => $validRows++,
                    };
                }
            });

            $nextStatus = $errorRows > 0
                ? ImportSession::STATUS_ANALYZED
                : ImportSession::STATUS_AWAITING_APPROVAL;

            $session->update([
                'status'      => $nextStatus,
                'valid_rows'  => $validRows,
                'error_rows'  => $errorRows,
                'warning_rows'=> $warningRows,
                'analyzed_at' => now(),
            ]);

        } catch (\Throwable $e) {
            $session->update([
                'status'        => ImportSession::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }

        return $session->fresh();
    }

    // ── Column mapping update ─────────────────────────────────────────────────

    /**
     * User-provided mapping correction. Re-runs analysis.
     */
    public function updateMapping(ImportSession $session, array $mapping): ImportSession
    {
        if (!$session->canUpdateMapping()) {
            throw new DomainException("Impossible de modifier le mapping au statut «{$session->status}».");
        }

        $session->update([
            'column_mapping' => $mapping,
            'status'         => ImportSession::STATUS_DRAFT,
        ]);

        return $this->analyze($session->fresh());
    }

    // ── Approve ───────────────────────────────────────────────────────────────

    public function approve(ImportSession $session, string $approvedBy): ImportSession
    {
        if (!$session->canBeApproved()) {
            throw new DomainException("La session ne peut pas être approuvée au statut «{$session->status}».");
        }

        $session->update([
            'status'      => ImportSession::STATUS_AWAITING_APPROVAL,
            'approved_at' => now(),
            'approved_by' => $approvedBy,
        ]);

        return $session->fresh();
    }

    // ── Execute ───────────────────────────────────────────────────────────────

    /**
     * Start the actual import. Small batches inline, large via Horizon.
     */
    public function execute(ImportSession $session): ImportSession
    {
        if (!$session->canBeApproved() && $session->status !== ImportSession::STATUS_AWAITING_APPROVAL) {
            throw new DomainException("La session doit être en attente d'approbation pour être exécutée.");
        }

        $count = ImportRow::where('session_id', $session->id)
            ->whereIn('status', [ImportRow::STATUS_VALID, ImportRow::STATUS_WARNING])
            ->count();

        $session->update(['status' => ImportSession::STATUS_IMPORTING]);

        if ($count > self::LARGE_ROW_THRESHOLD) {
            ExecuteImportJob::dispatch($session->id);
            return $session->fresh();
        }

        return $this->runExecution($session);
    }

    /**
     * Core execution logic (used by both sync path and ExecuteImportJob).
     */
    public function runExecution(ImportSession $session): ImportSession
    {
        try {
            $importedRows = $skippedRows = 0;

            DB::transaction(function () use ($session, &$importedRows, &$skippedRows) {
                $rows = ImportRow::where('session_id', $session->id)
                    ->whereIn('status', [ImportRow::STATUS_VALID, ImportRow::STATUS_WARNING])
                    ->where('action', '!=', ImportRow::ACTION_SKIP)
                    ->orderBy('row_number')
                    ->get();

                foreach ($rows as $row) {
                    if ($session->isSimulate()) {
                        $row->update(['status' => ImportRow::STATUS_IMPORTED]);
                        $importedRows++;
                        continue;
                    }

                    try {
                        // Per-row savepoint: if executeRow fails mid-way (e.g. SKU
                        // collision after a category/supplier firstOrCreate), roll back
                        // ONLY this row's writes so no orphan category/supplier is left.
                        $entityId = DB::transaction(fn () => $this->executeRow($session, $row));
                        $row->update([
                            'status'    => ImportRow::STATUS_IMPORTED,
                            'entity_id' => $entityId,
                        ]);
                        $importedRows++;
                    } catch (\Throwable $e) {
                        // Marked outside the rolled-back savepoint (outer tx still valid)
                        $row->update([
                            'status' => ImportRow::STATUS_ERROR,
                            'errors' => [['field' => 'general', 'message' => $e->getMessage()]],
                        ]);
                        $skippedRows++;
                    }
                }

                // Count rows that were skipped (action=skip)
                $skippedRows += ImportRow::where('session_id', $session->id)
                    ->where('action', ImportRow::ACTION_SKIP)
                    ->count();
            });

            $finalStatus = $skippedRows > 0
                ? ImportSession::STATUS_PARTIAL
                : ImportSession::STATUS_COMPLETED;

            $session->update([
                'status'        => $finalStatus,
                'imported_rows' => $importedRows,
                'skipped_rows'  => $skippedRows,
                'completed_at'  => now(),
                'summary'       => $this->buildSummary($session),
            ]);

        } catch (\Throwable $e) {
            $session->update([
                'status'        => ImportSession::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }

        return $session->fresh();
    }

    // ── Cancel ────────────────────────────────────────────────────────────────

    public function cancel(ImportSession $session): ImportSession
    {
        if (!$session->canBeCancelled()) {
            throw new DomainException("La session ne peut pas être annulée au statut «{$session->status}».");
        }

        $session->update(['status' => ImportSession::STATUS_CANCELLED]);

        // Clean up stored file
        Storage::disk(self::DISK)->delete($session->stored_path);

        return $session->fresh();
    }

    // ── History ───────────────────────────────────────────────────────────────

    public function history(string $tenantId, array $filters = [])
    {
        $query = ImportSession::forTenant($tenantId)
            ->orderByDesc('created_at');

        if (!empty($filters['type'])) {
            $query->byType($filters['type']);
        }
        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        return $query->paginate(20);
    }

    // ── Finders ───────────────────────────────────────────────────────────────

    public function findOrFail(string $id, string $tenantId): ImportSession
    {
        $session = ImportSession::forTenant($tenantId)->find($id);

        if (!$session) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                "Import session {$id} not found."
            );
        }

        return $session;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Read the stored file and return rows as [index => [header => value], ...]
     * First element is the header row.
     */
    public function readFile(ImportSession $session): array
    {
        $path = Storage::disk(self::DISK)->path($session->stored_path);

        $spreadsheet = IOFactory::load($path);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = [];

        foreach ($sheet->getRowIterator() as $row) {
            $cellValues = [];
            foreach ($row->getCellIterator() as $cell) {
                $cellValues[] = $cell->getFormattedValue();
            }
            // Skip empty rows
            if (array_filter($cellValues, fn($v) => $v !== '' && $v !== null)) {
                $rows[] = $cellValues;
            }
        }

        return $rows;
    }

    private function makeParser(ImportSession $session): ProductImportParser|CustomerImportParser|SupplierImportParser
    {
        return match ($session->type) {
            ImportSession::TYPE_PRODUCTS  => new ProductImportParser($session->tenant_id, $session->mode),
            ImportSession::TYPE_CUSTOMERS => new CustomerImportParser($session->tenant_id, $session->mode),
            ImportSession::TYPE_SUPPLIERS => new SupplierImportParser($session->tenant_id, $session->mode),
            default => throw new DomainException("Type d'import inconnu: {$session->type}"),
        };
    }

    private function executeRow(ImportSession $session, ImportRow $row): string
    {
        return match ($session->type) {
            ImportSession::TYPE_PRODUCTS  => $this->executeProductRow($session, $row),
            ImportSession::TYPE_CUSTOMERS => $this->executeCustomerRow($session, $row),
            ImportSession::TYPE_SUPPLIERS => $this->executeSupplierRow($session, $row),
            default => throw new DomainException("Type d'import inconnu: {$session->type}"),
        };
    }

    private function executeProductRow(ImportSession $session, ImportRow $row): string
    {
        $data = $row->mapped_data;

        // Resolve category: find or create
        if ($data['category_id'] === null && !empty($data['_raw_category'])) {
            $cat = Category::firstOrCreate(
                ['tenant_id' => $session->tenant_id, 'name' => $data['_raw_category']],
                ['slug' => Str::slug($data['_raw_category']), 'is_active' => true]
            );
            $data['category_id'] = $cat->id;
        }

        // Resolve supplier: find or create
        if ($data['supplier_id'] === null && !empty($data['_raw_supplier'])) {
            $sup = Supplier::firstOrCreate(
                ['tenant_id' => $session->tenant_id, 'name' => $data['_raw_supplier']],
                ['status' => 'active']
            );
            $data['supplier_id'] = $sup->id;
        }

        $productData = array_filter([
            'tenant_id'       => $session->tenant_id,
            'sku'             => $data['sku'],
            'name'            => $data['name'],
            'description'     => $data['description'],
            'price_amount'    => $data['price_amount'],
            'price_currency'  => $data['price_currency'] ?? 'XOF',
            'cost_amount'     => $data['cost_amount'],
            'barcode'         => $data['barcode'],
            'weight_kg'       => $data['weight_kg'],
            'status'          => $data['status'],
            'category_id'     => $data['category_id'],
            'supplier_id'     => $data['supplier_id'],
        ], fn($v) => $v !== null);

        if ($row->action === ImportRow::ACTION_UPDATE && $row->entity_id) {
            $product = Product::findOrFail($row->entity_id);
            $product->update($productData);
            return $product->id;
        }

        $product = Product::create($productData);
        return $product->id;
    }

    private function executeCustomerRow(ImportSession $session, ImportRow $row): string
    {
        $data = $row->mapped_data;

        $customerData = array_filter([
            'tenant_id' => $session->tenant_id,
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'],
            'address'   => $data['address'],
            'notes'     => $data['notes'],
        ], fn($v) => $v !== null);

        if ($row->action === ImportRow::ACTION_UPDATE && $row->entity_id) {
            $customer = Customer::findOrFail($row->entity_id);
            $customer->update($customerData);
            return $customer->id;
        }

        $customer = Customer::create($customerData);
        return $customer->id;
    }

    private function executeSupplierRow(ImportSession $session, ImportRow $row): string
    {
        $data = $row->mapped_data;

        $supplierData = array_filter([
            'tenant_id'     => $session->tenant_id,
            'name'          => $data['name'],
            'code'          => $data['code'],
            'email'         => $data['email'],
            'phone'         => $data['phone'],
            'contact_name'  => $data['contact_name'],
            'payment_terms' => $data['payment_terms'],
            'notes'         => $data['notes'],
            'status'        => $data['status'] ?? 'active',
        ], fn($v) => $v !== null);

        if ($row->action === ImportRow::ACTION_UPDATE && $row->entity_id) {
            $supplier = Supplier::findOrFail($row->entity_id);
            $supplier->update($supplierData);
            return $supplier->id;
        }

        $supplier = Supplier::create($supplierData);
        return $supplier->id;
    }

    private function buildSummary(ImportSession $session): array
    {
        $rows = ImportRow::where('session_id', $session->id)->get();

        return [
            'created' => $rows->where('action', ImportRow::ACTION_CREATE)->where('status', ImportRow::STATUS_IMPORTED)->count(),
            'updated' => $rows->where('action', ImportRow::ACTION_UPDATE)->where('status', ImportRow::STATUS_IMPORTED)->count(),
            'skipped' => $rows->where('action', ImportRow::ACTION_SKIP)->count(),
            'errors'  => $rows->where('status', ImportRow::STATUS_ERROR)->count(),
        ];
    }

    private function assertValidType(string $type): void
    {
        $valid = [ImportSession::TYPE_PRODUCTS, ImportSession::TYPE_CUSTOMERS, ImportSession::TYPE_SUPPLIERS];
        if (!in_array($type, $valid)) {
            throw new DomainException("Type d'entité invalide: {$type}. Valeurs acceptées: " . implode(', ', $valid));
        }
    }

    private function assertValidMode(string $mode): void
    {
        $valid = [ImportSession::MODE_CREATE_ONLY, ImportSession::MODE_UPDATE_ONLY, ImportSession::MODE_CREATE_UPDATE, ImportSession::MODE_SIMULATE];
        if (!in_array($mode, $valid)) {
            throw new DomainException("Mode d'import invalide: {$mode}. Valeurs acceptées: " . implode(', ', $valid));
        }
    }
}
