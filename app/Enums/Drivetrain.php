<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Which wheels are driven.
 */
enum Drivetrain: string
{
    use HasOptions;

    case Fwd = 'fwd';
    case Rwd = 'rwd';
    case Awd = 'awd';
    case FourWd = '4wd';

    public function label(): string
    {
        return match ($this) {
            self::Fwd => 'Front-wheel drive',
            self::Rwd => 'Rear-wheel drive',
            self::Awd => 'All-wheel drive',
            self::FourWd => 'Four-wheel drive',
        };
    }
}
