<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * The sales pipeline, in order. Won and Lost are terminal.
 */
enum LeadStage: string
{
    use HasOptions;

    case New = 'new';
    case Contacted = 'contacted';
    case Appointment = 'appointment';
    case TestDrive = 'test_drive';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Appointment => 'Appointment set',
            self::TestDrive => 'Test drive',
            self::Negotiation => 'Negotiation',
            self::Won => 'Won',
            self::Lost => 'Lost',
        };
    }

    /**
     * Which design token the UI should colour this with.
     */
    public function tone(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Contacted => 'muted',
            self::Appointment => 'warning',
            self::TestDrive => 'warning',
            self::Negotiation => 'primary',
            self::Won => 'success',
            self::Lost => 'destructive',
        };
    }

    /**
     * Still being worked, as opposed to won or lost.
     */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Won, self::Lost], true);
    }

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return array_values(array_filter(self::cases(), fn (self $stage) => $stage->isOpen()));
    }
}
