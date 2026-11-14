<?php

namespace App\Services\Knowledge;

/**
 * Masks contact details before text is stored for retrieval or sent to a model.
 *
 * With a local model nothing leaves the building anyway, but the habit matters: the day someone
 * flips AI_PROVIDER to a hosted API, customer phone numbers and emails still never go out.
 * Names stay, because "what did Natalie say about towing?" is exactly the question reps ask.
 */
class PiiMasker
{
    private const PATTERNS = [
        // Emails: anything@anything.tld
        '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i' => '[email]',
        // North American phone numbers in the usual shapes: 416-555-0199, (416) 555 0199, +1 416.555.0199
        '/(?<!\d)(?:\+?1[\s.-]?)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}(?!\d)/' => '[phone]',
        // Anything that looks like a card number (13 to 19 digits, optionally grouped).
        '/(?<!\d)(?:\d[ -]?){13,19}(?!\d)/' => '[card number]',
    ];

    public function mask(string $text): string
    {
        return (string) preg_replace(array_keys(self::PATTERNS), array_values(self::PATTERNS), $text);
    }
}
