<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * How likely the lead is to buy soon, as judged by the rep.
 */
enum LeadTemperature: string
{
    use HasOptions;

    case Hot = 'hot';
    case Warm = 'warm';
    case Cold = 'cold';

    public function label(): string
    {
        return match ($this) {
            self::Hot => 'Hot',
            self::Warm => 'Warm',
            self::Cold => 'Cold',
        };
    }

    /**
     * Which design token the UI should colour this with.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Hot => 'destructive',
            self::Warm => 'warning',
            self::Cold => 'info',
        };
    }
}
