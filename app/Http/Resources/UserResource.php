<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'email_verified_at' => $this->email_verified_at,
            'is_email_verified' => $this->email_verified_at !== null,
            'is_admin' => (bool) $this->is_admin,
            'role' => $this->role ? [
                'id' => $this->role->id,
                'name' => $this->role->name,
                'display_name' => $this->role->display_name,
            ] : null,
            'can_manage_orders' => $this->isAdmin() || $this->hasRole('manager') || $this->hasRole('employee'),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Formatted dates
            'created_at_formatted' => $this->created_at->format('Y-m-d H:i:s'),
            'created_at_human' => $this->created_at->diffForHumans(),
            'last_login' => $this->last_login_at?->format('Y-m-d H:i:s'),
            'last_login_human' => $this->last_login_at?->diffForHumans(),
        ];
    }
}
