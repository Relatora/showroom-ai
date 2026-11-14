<?php

namespace App\Actions\Leads;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;

/**
 * Adds an entry to a lead's timeline.
 *
 * Lives in an action (not a controller) because three different callers need it: the lead page,
 * stage changes, and later the MCP `log_activity` tool. Same rules for all of them.
 */
class LogActivity
{
    /**
     * Anything that counts as actually talking to the customer resets the "stale" clock.
     * A private note doesn't, otherwise reps could game the stale list by jotting "TBD".
     */
    private const CONTACT_TYPES = [
        ActivityType::Call,
        ActivityType::Email,
        ActivityType::Sms,
        ActivityType::Meeting,
        ActivityType::TestDrive,
    ];

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function handle(Lead $lead, ?User $author, ActivityType $type, string $body, ?array $meta = null): Activity
    {
        $activity = $lead->activities()->create([
            'user_id' => $author?->id,
            'type' => $type,
            'body' => trim($body),
            'meta' => $meta,
            'occurred_at' => now(),
        ]);

        if (in_array($type, self::CONTACT_TYPES, true)) {
            $lead->forceFill(['last_contacted_at' => $activity->occurred_at])->save();
        }

        return $activity;
    }
}
