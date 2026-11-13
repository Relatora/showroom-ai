<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * What the appointment is for.
 */
enum AppointmentType: string
{
    use HasOptions;

    case TestDrive = 'test_drive';
    case Consultation = 'consultation';
    case Delivery = 'delivery';
    case Appraisal = 'appraisal';

    public function label(): string
    {
        return match ($this) {
            self::TestDrive => 'Test drive',
            self::Consultation => 'Consultation',
            self::Delivery => 'Delivery',
            self::Appraisal => 'Trade-in appraisal',
        };
    }

    /**
     * Which design token the UI should colour this with.
     */
    public function tone(): string
    {
        return match ($this) {
            self::TestDrive => 'primary',
            self::Consultation => 'info',
            self::Delivery => 'success',
            self::Appraisal => 'warning',
        };
    }
}
