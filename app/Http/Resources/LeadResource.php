<?php

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lead
 */
class LeadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stage' => $this->stage->value,
            'temperature' => $this->temperature->value,
            'source' => $this->source->value,
            'budget_max' => $this->budget_max,
            'interest' => $this->interest,
            'trade_in' => $this->trade_in,
            'position' => $this->position,
            'is_stale' => $this->isStale(),
            'last_contacted_at' => $this->last_contacted_at?->toIso8601String(),
            'next_follow_up_at' => $this->next_follow_up_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'lost_reason' => $this->lost_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'assignee' => UserSummaryResource::make($this->whenLoaded('assignee')),
            'vehicle' => VehicleResource::make($this->whenLoaded('vehicle')),
            'activities' => ActivityResource::collection($this->whenLoaded('activities')),
            'appointments' => AppointmentResource::collection($this->whenLoaded('appointments')),
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'reassign' => $request->user()?->can('reassign', $this->resource) ?? false,
            ],
        ];
    }
}
