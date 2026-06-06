<?php

namespace App\Modules\Sync\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sync extends Model
{
    use HasFactory, HasTenant, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
    ];

    protected function casts(): array
    {
return [
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
];
    }
}