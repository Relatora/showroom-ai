<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * What makes it go.
 */
enum Powertrain: string
{
    use HasOptions;

    case Gas = 'gas';
    case Hybrid = 'hybrid';
    case Phev = 'phev';
    case Ev = 'ev';
    case Diesel = 'diesel';

    public function label(): string
    {
        return match ($this) {
            self::Gas => 'Gasoline',
            self::Hybrid => 'Hybrid',
            self::Phev => 'Plug-in hybrid',
            self::Ev => 'Electric',
            self::Diesel => 'Diesel',
        };
    }

    /**
     * Which design token the UI should colour this with.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Gas => 'muted',
            self::Hybrid => 'success',
            self::Phev => 'success',
            self::Ev => 'info',
            self::Diesel => 'muted',
        };
    }
}
