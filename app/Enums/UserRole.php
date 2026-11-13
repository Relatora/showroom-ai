<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * What a staff member can see and do. Reps work their own leads; managers and the BDC
 * (business development centre, who handle inbound enquiries) see the whole pipeline.
 */
enum UserRole: string
{
    use HasOptions;

    case Rep = 'rep';
    case Manager = 'manager';
    case Bdc = 'bdc';

    public function label(): string
    {
        return match ($this) {
            self::Rep => 'Sales rep',
            self::Manager => 'Sales manager',
            self::Bdc => 'BDC agent',
        };
    }

    /**
     * Which design token the UI should colour this with.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Rep => 'info',
            self::Manager => 'primary',
            self::Bdc => 'success',
        };
    }

    /**
     * Managers and the BDC see every lead. Reps only see the ones assigned to them.
     */
    public function seesWholePipeline(): bool
    {
        return $this !== self::Rep;
    }
}
