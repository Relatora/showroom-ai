<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Where the enquiry came from. Managers use this to see which channels are worth paying for.
 */
enum LeadSource: string
{
    use HasOptions;

    case Website = 'website';
    case WalkIn = 'walk_in';
    case Phone = 'phone';
    case Referral = 'referral';
    case Marketplace = 'marketplace';
    case Chat = 'chat';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website',
            self::WalkIn => 'Walk-in',
            self::Phone => 'Phone',
            self::Referral => 'Referral',
            self::Marketplace => 'Marketplace',
            self::Chat => 'Live chat',
        };
    }

    /**
     * Which design token the UI should colour this with.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Website => 'info',
            self::WalkIn => 'primary',
            self::Phone => 'muted',
            self::Referral => 'success',
            self::Marketplace => 'warning',
            self::Chat => 'info',
        };
    }
}
