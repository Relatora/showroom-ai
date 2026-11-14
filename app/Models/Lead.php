<?php

namespace App\Models;

use App\Contracts\Indexable;
use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Enums\LeadTemperature;
use App\Observers\KnowledgeObserver;
use App\Services\Knowledge\ChunkDraft;
use Carbon\CarbonImmutable;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One customer's interest in buying, tracked through the pipeline.
 *
 * @property int $id
 * @property int $customer_id
 * @property int|null $assigned_user_id
 * @property int|null $vehicle_id
 * @property LeadSource $source
 * @property LeadStage $stage
 * @property LeadTemperature $temperature
 * @property int|null $budget_max
 * @property string $interest
 * @property string|null $trade_in
 * @property int $position
 * @property CarbonImmutable|null $last_contacted_at
 * @property CarbonImmutable|null $next_follow_up_at
 * @property CarbonImmutable|null $closed_at
 * @property string|null $lost_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Customer $customer
 * @property-read User|null $assignee
 * @property-read Vehicle|null $vehicle
 */
#[Fillable([
    'customer_id', 'assigned_user_id', 'vehicle_id', 'source', 'stage', 'temperature', 'budget_max',
    'interest', 'trade_in', 'position', 'last_contacted_at', 'next_follow_up_at', 'closed_at', 'lost_reason',
])]
#[ObservedBy(KnowledgeObserver::class)]
class Lead extends Model implements Indexable
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    /**
     * How long a lead can go without contact before we call it stale. Industry rule of thumb is
     * that response rates fall off a cliff after a couple of days, so three is generous.
     */
    public const STALE_AFTER_DAYS = 3;

    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'stage' => LeadStage::class,
            'temperature' => LeadTemperature::class,
            'last_contacted_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * The specific vehicle they asked about, if any.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest('occurred_at');
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function isStale(): bool
    {
        return $this->stage->isOpen()
            && ($this->last_contacted_at === null
                || $this->last_contacted_at->lt(now()->subDays(self::STALE_AFTER_DAYS)));
    }

    /**
     * The one rule that decides who sees which leads. Every list, search, AI retrieval and MCP
     * tool goes through this scope, so there's exactly one place to get it right.
     *
     * @param  Builder<Lead>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (! $user->seesWholePipeline()) {
            $query->where('assigned_user_id', $user->id);
        }
    }

    /**
     * @param  Builder<Lead>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('stage', LeadStage::open());
    }

    /**
     * Open leads nobody has spoken to recently.
     *
     * @param  Builder<Lead>  $query
     */
    #[Scope]
    protected function stale(Builder $query): void
    {
        $query->whereIn('stage', LeadStage::open())
            ->where(fn (Builder $q) => $q
                ->whereNull('last_contacted_at')
                ->orWhere('last_contacted_at', '<', now()->subDays(self::STALE_AFTER_DAYS)));
    }

    /**
     * A summary of what this customer wants, so "who's looking for a truck that tows 7,000 lb?"
     * finds them even if nobody wrote it in a note.
     */
    public function toChunks(): array
    {
        $this->loadMissing(['customer', 'assignee', 'vehicle']);

        $content = implode(' ', array_filter([
            "{$this->customer->name} is a {$this->temperature->label()} lead in the {$this->stage->label()} stage, from {$this->source->label()}.",
            "Looking for: {$this->interest}",
            $this->budget_max ? 'Budget up to $'.number_format($this->budget_max).'.' : null,
            $this->trade_in ? "Trade-in: {$this->trade_in}." : null,
            $this->vehicle ? "Interested in the {$this->vehicle->title} ({$this->vehicle->stock_number})." : null,
            $this->assignee ? "Sales rep: {$this->assignee->name}." : 'Not assigned to a rep yet.',
            $this->lost_reason ? "Lost because: {$this->lost_reason}." : null,
        ]));

        return [new ChunkDraft($content, "Lead: {$this->customer->name}")];
    }

    public function indexLeadId(): ?int
    {
        return $this->id;
    }
}
