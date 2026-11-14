<?php

namespace App\Models;

use App\Contracts\Indexable;
use App\Enums\ActivityType;
use App\Observers\KnowledgeObserver;
use App\Services\Knowledge\ChunkDraft;
use Carbon\CarbonImmutable;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something that happened on a lead: a call, a note, a stage change.
 *
 * Notes are where the useful context lives ("wife wants heated seats", "tows a 2,000 lb boat"),
 * which is why they get indexed for the assistant later on.
 *
 * @property int $id
 * @property int $lead_id
 * @property int|null $user_id
 * @property ActivityType $type
 * @property string $body
 * @property array<string, mixed>|null $meta
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Lead $lead
 * @property-read User|null $user
 */
#[Fillable(['lead_id', 'user_id', 'type', 'body', 'meta', 'occurred_at'])]
#[ObservedBy(KnowledgeObserver::class)]
class Activity extends Model implements Indexable
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Notes, calls and emails are where the real detail lives ("tows a 2,000 lb boat"). Stage
     * changes are just bookkeeping, so they aren't indexed.
     */
    public function toChunks(): array
    {
        if ($this->type === ActivityType::StageChange) {
            return [];
        }

        $this->loadMissing(['lead.customer', 'user']);

        $by = $this->user ? " by {$this->user->name}" : '';
        $content = "{$this->type->label()}{$by} on {$this->occurred_at->format('M j, Y')}: {$this->body}";

        return [new ChunkDraft($content, "Timeline: {$this->lead->customer->name}")];
    }

    public function indexLeadId(): ?int
    {
        return $this->lead_id;
    }
}
