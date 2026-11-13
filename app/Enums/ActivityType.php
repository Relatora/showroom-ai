<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Entries on a lead's timeline.
 */
enum ActivityType: string
{
    use HasOptions;

    case Note = 'note';
    case Call = 'call';
    case Email = 'email';
    case Sms = 'sms';
    case Meeting = 'meeting';
    case StageChange = 'stage_change';
    case TestDrive = 'test_drive';

    public function label(): string
    {
        return match ($this) {
            self::Note => 'Note',
            self::Call => 'Call',
            self::Email => 'Email',
            self::Sms => 'Text message',
            self::Meeting => 'In person',
            self::StageChange => 'Stage change',
            self::TestDrive => 'Test drive',
        };
    }

    /**
     * Which design token the UI should colour this with.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Note => 'muted',
            self::Call => 'info',
            self::Email => 'info',
            self::Sms => 'info',
            self::Meeting => 'primary',
            self::StageChange => 'warning',
            self::TestDrive => 'success',
        };
    }
}
