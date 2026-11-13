<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Body styles we stock. Also drives which silhouette the UI draws for a vehicle.
 */
enum BodyStyle: string
{
    use HasOptions;

    case Sedan = 'sedan';
    case Hatchback = 'hatchback';
    case Suv = 'suv';
    case Truck = 'truck';
    case Minivan = 'minivan';
    case Coupe = 'coupe';
    case Wagon = 'wagon';

    public function label(): string
    {
        return match ($this) {
            self::Sedan => 'Sedan',
            self::Hatchback => 'Hatchback',
            self::Suv => 'SUV',
            self::Truck => 'Pickup truck',
            self::Minivan => 'Minivan',
            self::Coupe => 'Coupe',
            self::Wagon => 'Wagon',
        };
    }
}
