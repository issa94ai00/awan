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

            // Who this account is as a member of staff.
            //
            // The apps carry an `employee_id` field and had nowhere to fill it
            // from: this resource is what login returns, and it stopped at the
            // user account. So every order the staff app raised went in with no
            // responsible employee — invisible in "my orders", with nobody to
            // chase it — and the warehouse could not be derived from the person
            // either. Both are attached here, at the one place that knows.
            'employee_id' => $this->employee?->id,
            'employee_warehouse_id' => $this->employee?->warehouse_id,

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
