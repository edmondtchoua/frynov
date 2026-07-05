<?php

namespace App\Modules\Accounting\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** RC-30 — ligne de facture (HT, remise en points de base, TVA optionnelle). */
class InvoiceLine extends Model
{
    use HasTenant, HasUuids;

    protected $table = 'invoice_lines';

    protected $fillable = [
        'tenant_id', 'invoice_id', 'product_id', 'label', 'quantity',
        'unit_price_minor', 'discount_bp', 'tax_id',
        'subtotal_minor', 'tax_minor', 'total_minor',
    ];

    protected function casts(): array
    {
        return [
            'quantity'         => 'integer',
            'unit_price_minor' => 'integer',
            'discount_bp'      => 'integer',
            'subtotal_minor'   => 'integer',
            'tax_minor'        => 'integer',
            'total_minor'      => 'integer',
        ];
    }
}
