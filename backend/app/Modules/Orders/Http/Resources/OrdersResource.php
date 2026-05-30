<?php

namespace App\Modules\Orders\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrdersResource extends JsonResource
{
    public function toArray(Request $request): array
    {
return [
    'id'         => $this->id,
    'created_at' => $this->created_at,
    'updated_at' => $this->updated_at,
    // TODO: ajouter les champs du module
];
    }
}