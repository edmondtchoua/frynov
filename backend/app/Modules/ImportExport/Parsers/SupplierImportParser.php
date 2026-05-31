<?php

namespace App\Modules\ImportExport\Parsers;

use App\Modules\ImportExport\Models\ImportRow;
use App\Modules\Suppliers\Models\Supplier;

/**
 * Validates and enriches a mapped supplier row.
 */
class SupplierImportParser
{
    private string $tenantId;
    private string $mode;

    private array $emailIndex = [];  // lowercase email => supplier_id
    private array $codeIndex  = [];  // lowercase code  => supplier_id
    private bool  $indexed    = false;

    public function __construct(string $tenantId, string $mode)
    {
        $this->tenantId = $tenantId;
        $this->mode     = $mode;
    }

    public function buildIndex(): void
    {
        if ($this->indexed) {
            return;
        }

        Supplier::where('tenant_id', $this->tenantId)
            ->whereNull('deleted_at')
            ->get(['id', 'code', 'email'])
            ->each(function (Supplier $s) {
                if ($s->email) {
                    $this->emailIndex[strtolower($s->email)] = $s->id;
                }
                if ($s->code) {
                    $this->codeIndex[strtolower($s->code)] = $s->id;
                }
            });

        $this->indexed = true;
    }

    /**
     * @param  array $mapped  [ system_field => value ]
     * @return array { status, action, entity_id, errors, warnings, mapped_data }
     */
    public function parseRow(array $mapped, int $rowNum): array
    {
        $this->buildIndex();

        $errors   = [];
        $warnings = [];

        // ── Required fields ───────────────────────────────────────────────
        $name = trim($mapped['name'] ?? '');
        if ($name === '') {
            $errors[] = ['field' => 'name', 'message' => 'Le nom du fournisseur est obligatoire.'];
        }

        // ── Email ─────────────────────────────────────────────────────────
        $email = strtolower(trim($mapped['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = ['field' => 'email', 'message' => "Email invalide: «{$email}»."];
            $email = '';
        }

        // ── Code ──────────────────────────────────────────────────────────
        $code = strtoupper(trim($mapped['code'] ?? ''));

        // ── Status ────────────────────────────────────────────────────────
        $rawStatus = strtolower(trim($mapped['status'] ?? 'active'));
        $status    = in_array($rawStatus, ['active', 'actif', '1', 'oui', 'yes']) ? 'active' : 'inactive';

        // ── Duplicate detection ───────────────────────────────────────────
        $existingId = null;

        if ($code !== '') {
            $existingId = $this->codeIndex[strtolower($code)] ?? null;
        }
        if ($existingId === null && $email !== '') {
            $existingId = $this->emailIndex[$email] ?? null;
        }

        $action = ImportRow::ACTION_CREATE;

        if ($existingId) {
            if ($this->mode === 'create_only') {
                $action     = ImportRow::ACTION_SKIP;
                $warnings[] = ['field' => 'code', 'message' => 'Fournisseur déjà existant (mode create_only → ignoré).'];
            } else {
                $action = ImportRow::ACTION_UPDATE;
            }
        } else {
            if ($this->mode === 'update_only') {
                $action     = ImportRow::ACTION_SKIP;
                $warnings[] = ['field' => 'code', 'message' => 'Fournisseur introuvable (mode update_only → ignoré).'];
            }
        }

        // ── Final status ──────────────────────────────────────────────────
        if (!empty($errors)) {
            $rowStatus = ImportRow::STATUS_ERROR;
        } elseif ($action === ImportRow::ACTION_SKIP) {
            $rowStatus = ImportRow::STATUS_WARNING;
        } elseif (!empty($warnings)) {
            $rowStatus = ImportRow::STATUS_WARNING;
        } else {
            $rowStatus = ImportRow::STATUS_VALID;
        }

        $mappedData = [
            'name'          => $name ?: null,
            'code'          => $code ?: null,
            'email'         => $email ?: null,
            'phone'         => trim($mapped['phone'] ?? '') ?: null,
            'contact_name'  => trim($mapped['contact_name'] ?? '') ?: null,
            'payment_terms' => trim($mapped['payment_terms'] ?? '') ?: null,
            'notes'         => trim($mapped['notes'] ?? '') ?: null,
            'status'        => $status,
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

    public function registerCode(string $code, string $id): void
    {
        $this->codeIndex[strtolower($code)] = $id;
    }

    public function registerEmail(string $email, string $id): void
    {
        $this->emailIndex[strtolower($email)] = $id;
    }
}
