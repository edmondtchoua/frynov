<?php

namespace App\Modules\ImportExport\Parsers;

use App\Modules\Customers\Models\Customer;
use App\Modules\ImportExport\Models\ImportRow;

/**
 * Validates and enriches a mapped customer row.
 */
class CustomerImportParser
{
    private string $tenantId;
    private string $mode;

    private array $emailIndex = [];  // lowercase email => customer_id
    private array $phoneIndex = [];  // e164-ish phone  => customer_id
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

        Customer::where('tenant_id', $this->tenantId)
            ->whereNull('deleted_at')
            ->get(['id', 'email', 'phone'])
            ->each(function (Customer $c) {
                if ($c->email) {
                    $this->emailIndex[strtolower($c->email)] = $c->id;
                }
                if ($c->phone) {
                    $this->phoneIndex[$this->normalizePhone($c->phone)] = $c->id;
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
            $errors[] = ['field' => 'name', 'message' => 'Le nom du client est obligatoire.'];
        }

        // ── Email ─────────────────────────────────────────────────────────
        $email = strtolower(trim($mapped['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = ['field' => 'email', 'message' => "Email invalide: «{$email}»."];
            $email = '';
        }

        // ── Phone ─────────────────────────────────────────────────────────
        $phone = trim($mapped['phone'] ?? '');

        // ── At least email or phone required ──────────────────────────────
        if ($email === '' && $phone === '') {
            $warnings[] = ['field' => 'email', 'message' => 'Ni email ni téléphone fourni — risque de doublon non détectable.'];
        }

        // ── Duplicate detection ───────────────────────────────────────────
        $existingId = null;

        if ($email !== '') {
            $existingId = $this->emailIndex[$email] ?? null;
        }
        if ($existingId === null && $phone !== '') {
            $existingId = $this->phoneIndex[$this->normalizePhone($phone)] ?? null;
        }

        $action = ImportRow::ACTION_CREATE;

        if ($existingId) {
            if ($this->mode === 'create_only') {
                $action     = ImportRow::ACTION_SKIP;
                $warnings[] = ['field' => 'email', 'message' => 'Client déjà existant (mode create_only → ignoré).'];
            } else {
                $action = ImportRow::ACTION_UPDATE;
            }
        } else {
            if ($this->mode === 'update_only') {
                $action     = ImportRow::ACTION_SKIP;
                $warnings[] = ['field' => 'email', 'message' => 'Client introuvable (mode update_only → ignoré).'];
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
            'name'    => $name ?: null,
            'email'   => $email ?: null,
            'phone'   => $phone ?: null,
            'address' => $this->parseAddress($mapped['address'] ?? null),
            'notes'   => trim($mapped['notes'] ?? '') ?: null,
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

    public function registerEmail(string $email, string $id): void
    {
        $this->emailIndex[strtolower($email)] = $id;
    }

    private function parseAddress(mixed $address): ?array
    {
        $street = trim((string) ($address ?? ''));

        if ($street === '') {
            return null;
        }

        return ['street' => $street];
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', $phone);
    }
}
