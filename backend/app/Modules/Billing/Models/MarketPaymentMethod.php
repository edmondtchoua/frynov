<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Moyen de paiement disponible pour un marché (P6-1).
 *
 * Référence plateforme (non tenant-scopée), calquée sur PlanPrice. Le champ `mode` décide
 * du traitement attendu côté checkout :
 *  - `auto`   : rail PSP réel branché (post-1.0) ;
 *  - `manual` : encaissement déclaratif (preuve + validation admin via ManualPayment) ;
 *  - `quote`  : « sur devis » (contact commercial).
 */
class MarketPaymentMethod extends Model
{
    use HasUuids;

    public const MODE_AUTO   = 'auto';
    public const MODE_MANUAL = 'manual';
    public const MODE_QUOTE  = 'quote';

    public const MODES = [self::MODE_AUTO, self::MODE_MANUAL, self::MODE_QUOTE];

    protected $fillable = [
        'market_code',
        'country_code',
        'currency',
        'method',
        'mode',
        'is_active',
        'display_order',
        'label',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active'     => 'boolean',
            'display_order' => 'integer',
            'metadata'      => 'array',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
