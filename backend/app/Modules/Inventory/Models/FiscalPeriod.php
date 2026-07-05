<?php
namespace App\Modules\Inventory\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FiscalPeriod extends Model
{
    use HasTenant, HasUuids;

    protected $fillable = [
        'tenant_id','name','type','starts_at','ends_at','status',
        'locked_by','locked_at','lock_reason',
        'total_value_cents_at_lock','integrity_hash',
    ];

    protected function casts(): array
    {
        return [
            'starts_at'  => 'date',
            'ends_at'    => 'date',
            'locked_at'  => 'datetime',
        ];
    }

    public function isOpen(): bool   { return $this->status === 'open'; }
    public function isLocked(): bool { return $this->status === 'locked'; }

    /** Returns true if the given date falls within this period */
    public function coversDate(string $date): bool
    {
        return $this->starts_at->lte($date) && $this->ends_at->gte($date);
    }
}
