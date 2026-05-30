<?php

namespace App\Modules\Payments\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payments extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [];

    protected function casts(): array
    {
return [
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
];
    }
}