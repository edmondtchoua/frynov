<?php

namespace App\Modules\Auth\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'email'          => $this->email,
            'is_super_admin' => $this->is_super_admin,
            'tenant_id'      => $this->tenant_id,
            'tenant'         => $this->whenLoaded('tenant', fn () => [
                'id'                  => $this->tenant->id,
                'name'                => $this->tenant->name,
                'slug'                => $this->tenant->slug,
                'domain'              => $this->tenant->domain,
                'plan'                => $this->tenant->plan,
                'status'              => $this->tenant->status,
                'subscription_status' => $this->tenant->subscription_status,
                // Include settings so frontend can read session_timeout_minutes
                'settings'            => $this->tenant->settings ?? [],
            ]),
            'roles'          => $this->getRoleNames(),
            'permissions'    => $this->getPermissionNames(),
            'created_at'     => $this->created_at?->toISOString(),
        ];
    }
}
