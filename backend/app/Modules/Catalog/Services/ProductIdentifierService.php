<?php
namespace App\Modules\Catalog\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductIdentifierService
{
    public function generateSku(string $tenantId, string $categoryPrefix = null): string
    {
        return DB::transaction(function () use ($tenantId, $categoryPrefix) {
            $seq = DB::table('tenant_sequences')
                ->where('tenant_id', $tenantId)
                ->where('sequence_key', 'product_sku')
                ->lockForUpdate()->first();

            if (!$seq) {
                DB::table('tenant_sequences')->insert(['id' => (string)Str::uuid(), 'tenant_id' => $tenantId, 'sequence_key' => 'product_sku', 'prefix' => 'PROD', 'pattern' => '{PREFIX}-{SEQ}', 'next_number' => 1, 'padding' => 6, 'created_at' => now(), 'updated_at' => now()]);
                $seq = DB::table('tenant_sequences')->where('tenant_id', $tenantId)->where('sequence_key', 'product_sku')->lockForUpdate()->first();
            }

            $n = $seq->next_number;
            DB::table('tenant_sequences')->where('tenant_id', $tenantId)->where('sequence_key', 'product_sku')->update(['next_number' => $n + 1, 'updated_at' => now()]);

            $prefix = $categoryPrefix ? strtoupper(preg_replace('/[^A-Z0-9]/', '', strtoupper(substr($categoryPrefix, 0, 4)))) : ($seq->prefix ?: 'PROD');
            $prefix = $prefix ?: 'PROD';

            return $this->render($seq->pattern, $prefix, $n, (int)$seq->padding);
        });
    }

    public function generateInternalBarcode(string $tenantId): string
    {
        return DB::transaction(function () use ($tenantId) {
            $seq = DB::table('tenant_sequences')
                ->where('tenant_id', $tenantId)
                ->where('sequence_key', 'product_barcode')
                ->lockForUpdate()->first();

            if (!$seq) {
                DB::table('tenant_sequences')->insert(['id' => (string)Str::uuid(), 'tenant_id' => $tenantId, 'sequence_key' => 'product_barcode', 'prefix' => 'FRY', 'pattern' => '{PREFIX}{SEQ}', 'next_number' => 1, 'padding' => 10, 'created_at' => now(), 'updated_at' => now()]);
                $seq = DB::table('tenant_sequences')->where('tenant_id', $tenantId)->where('sequence_key', 'product_barcode')->lockForUpdate()->first();
            }

            $n = $seq->next_number;
            DB::table('tenant_sequences')->where('tenant_id', $tenantId)->where('sequence_key', 'product_barcode')->update(['next_number' => $n + 1, 'updated_at' => now()]);

            return 'FRY' . str_pad((string)$n, 10, '0', STR_PAD_LEFT);
        });
    }

    public function validateGtin(string $gtin): void
    {
        $d = preg_replace('/\D/', '', $gtin);
        if (!in_array(strlen($d), [8,12,13,14], true)) {
            throw new \InvalidArgumentException("GTIN invalide: longueur doit etre 8, 12, 13 ou 14 chiffres.");
        }
        if (!$this->checkGtinDigit($d)) {
            throw new \InvalidArgumentException("GTIN invalide: le chiffre de controle GS1 est incorrect.");
        }
    }

    public function checkGtinDigit(string $d): bool
    {
        $digits = str_split($d);
        $check  = (int)array_pop($digits);
        $sum    = 0;
        foreach (array_reverse($digits) as $i => $c) {
            $sum += ($i % 2 === 0 ? 3 : 1) * (int)$c;
        }
        return $check === (10 - ($sum % 10)) % 10;
    }

    private function render(string $pattern, string $prefix, int $n, int $pad): string
    {
        $s = str_replace(["{PREFIX}","{SEQ}","{SEQUENCE}","{YEAR}","{MONTH}"], [$prefix, str_pad((string)$n, $pad, '0', STR_PAD_LEFT), str_pad((string)$n, $pad, '0', STR_PAD_LEFT), date('Y'), date('m')], $pattern);
        return strtoupper(preg_replace('/[^A-Z0-9\-_]/', '', str_replace(' ', '-', $s)));
    }
}
