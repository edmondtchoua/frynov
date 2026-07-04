<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RC-6D — définition dynamique d'identifiant métier (IMEI, VIN, MAC, compteur…). `tenant_id` null =
 * globale (seedée) ; résolution tenant → global par code. Pilote normalisation, validation et unicité
 * des unités sérialisées. PAS de HasTenant (les globales ont tenant_id null).
 */
class SpecialAttributeDefinition extends Model
{
    use HasUuids, SoftDeletes;

    public const NORM_DIGITS_ONLY = 'digits_only';
    public const NORM_ALNUM_UPPER = 'alnum_upper';
    public const NORM_UPPER_TRIM  = 'upper_trim';
    public const NORM_NONE        = 'none';
    public const NORMALIZATIONS = [self::NORM_DIGITS_ONLY, self::NORM_ALNUM_UPPER, self::NORM_UPPER_TRIM, self::NORM_NONE];

    protected $table = 'special_attribute_definitions';

    protected $fillable = [
        'tenant_id', 'code', 'label', 'scope',
        'normalization_strategy', 'validation_regex',
        'is_unique', 'is_active', 'sort_order', 'help_text',
    ];

    protected function casts(): array
    {
        return [
            'is_unique'  => 'boolean',
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** Applique la stratégie de normalisation de CETTE définition. */
    public function normalize(string $value): string
    {
        return match ($this->normalization_strategy) {
            self::NORM_DIGITS_ONLY => (string) preg_replace('/\D+/', '', $value),
            self::NORM_ALNUM_UPPER => strtoupper((string) preg_replace('/[^a-zA-Z0-9]+/', '', $value)),
            self::NORM_UPPER_TRIM  => strtoupper((string) preg_replace('/\s+/', '', trim($value))),
            default                => trim($value),
        };
    }

    /** Valide la valeur NORMALISÉE contre la regex de la définition (pas de regex = accepté). */
    public function validateNormalized(string $normalized): bool
    {
        if ($normalized === '') {
            return false;
        }
        if (! $this->validation_regex) {
            return true;
        }

        return (bool) preg_match('/' . str_replace('/', '\/', $this->validation_regex) . '/', $normalized);
    }

    /** Définition applicable pour un code : celle du tenant sinon la globale. */
    public static function resolve(string $tenantId, string $code): ?self
    {
        return static::where('code', strtolower($code))
            ->where('scope', 'inventory_unit')
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))
            ->orderByRaw('tenant_id IS NULL') // tenant avant global
            ->first();
    }

    public function toApiArray(): array
    {
        return [
            'id'                     => $this->id,
            'code'                   => $this->code,
            'label'                  => $this->label,
            'normalization_strategy' => $this->normalization_strategy,
            'validation_regex'       => $this->validation_regex,
            'is_unique'              => $this->is_unique,
            'is_active'              => $this->is_active,
            'sort_order'             => $this->sort_order,
            'help_text'              => $this->help_text,
            'is_global'              => $this->tenant_id === null,
        ];
    }
}
