<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Appointment
 */
class AppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'notes' => $this->notes,
            'lead_id' => $this->lead_id,
            'lead' => LeadResource::make($this->whenLoaded('lead')),
            'user' => UserSummaryResource::make($this->whenLoaded('user')),
            'vehicle' => VehicleResource::make($this->whenLoaded('vehicle')),
        ];
    }
}
