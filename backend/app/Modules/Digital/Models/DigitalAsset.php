<?php

namespace App\Modules\Digital\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RC-5I — fichier privé rattaché à un produit digital. Le `path` (chemin de stockage) n'est JAMAIS
 * exposé en API : le client passe par un lien signé.
 */
class DigitalAsset extends Model
{
    use HasTenant, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'product_id', 'variant_id',
        'name', 'disk', 'path', 'size_bytes', 'mime', 'checksum', 'version', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'version'    => 'integer',
            'is_active'  => 'boolean',
        ];
    }

    /** Payload API — sans `path`/`disk` (jamais exposés). */
    public function toApiArray(): array
    {
        return [
            'id'         => $this->id,
            'product_id' => $this->product_id,
            'name'       => $this->name,
            'size_bytes' => $this->size_bytes,
            'mime'       => $this->mime,
            'version'    => $this->version,
            'is_active'  => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
