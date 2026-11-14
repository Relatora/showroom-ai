<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A searchable piece of text, with its embedding. The unit the assistant retrieves and cites.
 *
 * @property int $id
 * @property string $source_type
 * @property int $source_id
 * @property int|null $lead_id
 * @property int $position
 * @property string|null $heading
 * @property string $content
 * @property int $token_estimate
 * @property string $content_hash
 * @property array<int, float>|null $embedding
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Model $source
 */
#[Fillable(['source_type', 'source_id', 'lead_id', 'position', 'heading', 'content', 'token_estimate', 'content_hash', 'embedding'])]
class Chunk extends Model
{
    // The generated tsvector column is for Postgres to search, not for PHP to read.
    protected $hidden = ['search', 'embedding'];

    protected function casts(): array
    {
        return ['embedding' => AsVector::class];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Shared knowledge (documents, vehicles) is visible to everyone. Chunks that belong to a lead
     * follow the lead's visibility rule, so the assistant can't quote another rep's customer.
     *
     * @param  Builder<Chunk>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->seesWholePipeline()) {
            return;
        }

        $query->where(fn (Builder $q) => $q
            ->whereNull('lead_id')
            ->orWhereIn('lead_id', Lead::visibleTo($user)->select('id')));
    }
}
