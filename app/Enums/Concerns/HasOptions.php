<?php

namespace App\Enums\Concerns;

/**
 * Lets the frontend render selects, badges and filters straight from the PHP enum, so the list
 * of stages (or sources, or body styles) lives in exactly one place.
 */
trait HasOptions
{
    abstract public function label(): string;

    /**
     * @return list<array{value: string, label: string, tone: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'tone' => method_exists($case, 'tone') ? $case->tone() : 'muted',
        ], self::cases());
    }
}
