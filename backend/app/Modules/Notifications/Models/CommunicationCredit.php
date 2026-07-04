<?php

namespace App\Modules\Notifications\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * RC-7E — solde de crédits de communication d'un tenant pour un canal donné (email/SMS/WhatsApp).
 */
class CommunicationCredit extends Model
{
    use HasTenant, HasUuids;

    protected $table = 'communication_credits';

    protected $fillable = ['tenant_id', 'channel', 'balance'];

    protected function casts(): array
    {
        return ['balance' => 'integer'];
    }
}
