<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Where a vehicle is in its life on the lot.
 */
enum VehicleStatus: string
{
    use HasOptions;

    case Available = 'available';
    case OnHold = 'on_hold';
    case Sold = 'sold';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::OnHold => 'On hold',
            self::Sold => 'Sold',
        };
    }

    /**
     * Which design token the UI should colour this with.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::OnHold => 'warning',
            self::Sold => 'muted',
        };
    }
}
