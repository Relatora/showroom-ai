<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Whether the appointment happened.
 */
enum AppointmentStatus: string
{
    use HasOptions;

    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Completed => 'Completed',
            self::NoShow => 'No-show',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Which design token the UI should colour this with.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Scheduled => 'info',
            self::Completed => 'success',
            self::NoShow => 'destructive',
            self::Cancelled => 'muted',
        };
    }
}
