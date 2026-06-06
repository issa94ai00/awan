<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InquiryResource extends JsonResource
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
            'subject' => $this->subject,
            'subject_label' => $this->getSubjectLabelAttribute(),
            'message' => $this->message,
            'status' => $this->status,
            'status_text' => $this->getStatusText(),
            'status_label' => $this->getStatusText(),
            'priority' => $this->priority,
            'priority_label' => $this->getPriorityLabelAttribute(),
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'closed_at_formatted' => $this->getClosedAtFormattedAttribute(),
            
            // Relationships
            'product' => $this->when($this->relationLoaded('product'), function () {
                return $this->product ? [
                    'id' => $this->product->id,
                    'name_ar' => $this->product->name_ar,
                    'name_en' => $this->product->name_en,
                    'slug' => $this->product->slug,
                    'image_main' => $this->product->image_main ? asset('storage/' . $this->product->image_main) : null,
                ] : null;
            }),
            
            'assigned_to' => $this->when($this->relationLoaded('assignedTo'), function () {
                return $this->assignedTo ? [
                    'id' => $this->assignedTo->id,
                    'name' => $this->assignedTo->name,
                    'email' => $this->assignedTo->email,
                ] : null;
            }),

            'replies' => $this->when($this->relationLoaded('replies'), function () {
                return $this->replies->map(function ($reply) {
                    return [
                        'id' => $reply->id,
                        'message' => $reply->message,
                        'created_at_human' => $reply->created_at->diffForHumans(),
                        'admin_name' => $reply->admin ? $reply->admin->name : 'مشرف',
                    ];
                })->all();
            }),
            
            'user' => $this->when($this->relationLoaded('user'), function () {
                return $this->user ? [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ] : null;
            }),
            
            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Formatted dates
            'created_at_formatted' => $this->created_at->format('Y-m-d H:i:s'),
            'created_at_human' => $this->created_at->diffForHumans(),
        ];
    }
}
