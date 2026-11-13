<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * New, used or certified pre-owned.
 */
enum VehicleCondition: string
{
    use HasOptions;

    case New = 'new';
    case Used = 'used';
    case Certified = 'certified';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Used => 'Used',
            self::Certified => 'Certified pre-owned',
        };
    }

    /**
     * Which design token the UI should colour this with.
     */
    public function tone(): string
    {
        return match ($this) {
            self::New => 'primary',
            self::Used => 'muted',
            self::Certified => 'success',
        };
    }
}
