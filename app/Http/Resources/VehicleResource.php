<?php

namespace App\Http\Resources;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vehicle
 */
class VehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'stock_number' => $this->stock_number,
            'vin' => $this->vin,
            'year' => $this->year,
            'make' => $this->make,
            'model' => $this->model,
            'trim' => $this->trim,
            'body_style' => $this->body_style->value,
            'powertrain' => $this->powertrain->value,
            'drivetrain' => $this->drivetrain->value,
            'drivetrain_label' => $this->drivetrain->label(),
            'seats' => $this->seats,
            'exterior_color' => $this->exterior_color,
            'exterior_hex' => $this->exterior_hex,
            'interior_color' => $this->interior_color,
            'km' => $this->km,
            'price' => $this->price,
            'condition' => $this->condition->value,
            'status' => $this->status->value,
            'towing_lb' => $this->towing_lb,
            'range_km' => $this->range_km,
            'fuel_l_per_100km' => $this->fuel_l_per_100km !== null ? (float) $this->fuel_l_per_100km : null,
            'features' => $this->features,
            'description' => $this->description,
            'days_on_lot' => $this->daysOnLot(),
            'leads_count' => $this->whenCounted('leads'),
        ];
    }
}
