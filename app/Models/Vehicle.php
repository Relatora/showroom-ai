<?php

namespace App\Models;

use App\Contracts\Indexable;
use App\Enums\BodyStyle;
use App\Enums\Drivetrain;
use App\Enums\Powertrain;
use App\Enums\VehicleCondition;
use App\Enums\VehicleStatus;
use App\Observers\KnowledgeObserver;
use App\Services\Knowledge\ChunkDraft;
use Carbon\CarbonImmutable;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A vehicle on (or recently off) the lot.
 *
 * @property int $id
 * @property string $stock_number
 * @property string $vin
 * @property int $year
 * @property string $make
 * @property string $model
 * @property string|null $trim
 * @property BodyStyle $body_style
 * @property Powertrain $powertrain
 * @property Drivetrain $drivetrain
 * @property int $seats
 * @property string $exterior_color
 * @property string $exterior_hex
 * @property string $interior_color
 * @property int $km
 * @property int $price
 * @property VehicleCondition $condition
 * @property VehicleStatus $status
 * @property int|null $towing_lb
 * @property int|null $range_km
 * @property string|null $fuel_l_per_100km
 * @property list<string> $features
 * @property string $description
 * @property CarbonImmutable $arrived_on
 * @property-read string $title
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'stock_number', 'vin', 'year', 'make', 'model', 'trim', 'body_style', 'powertrain', 'drivetrain',
    'seats', 'exterior_color', 'exterior_hex', 'interior_color', 'km', 'price', 'condition', 'status',
    'towing_lb', 'range_km', 'fuel_l_per_100km', 'features', 'description', 'arrived_on',
])]
#[ObservedBy(KnowledgeObserver::class)]
class Vehicle extends Model implements Indexable
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['title'];

    protected function casts(): array
    {
        return [
            'body_style' => BodyStyle::class,
            'powertrain' => Powertrain::class,
            'drivetrain' => Drivetrain::class,
            'condition' => VehicleCondition::class,
            'status' => VehicleStatus::class,
            'features' => 'array',
            'arrived_on' => 'date',
        ];
    }

    /**
     * Leads who've shown interest in this specific vehicle.
     *
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * "2024 Toyota Highlander Hybrid XLE", the way a salesperson would say it.
     *
     * @return Attribute<string, never>
     */
    protected function title(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->year} {$this->make} {$this->model} {$this->trim}"));
    }

    /**
     * Days on the lot. Aged stock costs the dealership money every day it sits.
     */
    public function daysOnLot(): int
    {
        return (int) $this->arrived_on->diffInDays(now());
    }

    /**
     * @param  Builder<Vehicle>  $query
     */
    #[Scope]
    protected function available(Builder $query): void
    {
        $query->where('status', VehicleStatus::Available);
    }

    /**
     * The facts that decide whether a vehicle suits someone, in one line. The AI features put
     * this in their prompts, so they quote real numbers instead of guessing them. Shorter than
     * the search text on purpose: every token of prompt costs time on a local model.
     */
    public function keyFacts(): string
    {
        return implode(', ', array_filter([
            $this->title,
            $this->condition->label(),
            '$'.number_format($this->price),
            number_format($this->km).' km',
            "{$this->seats} seats",
            $this->powertrain->label(),
            $this->drivetrain->label(),
            $this->towing_lb ? 'tows '.number_format($this->towing_lb).' lb' : null,
            $this->range_km ? "{$this->range_km} km electric range" : null,
        ]));
    }

    /**
     * One chunk per vehicle, written the way a salesperson would describe it, so questions like
     * "a 3-row hybrid that tows 3,500 lb" match on meaning and on the numbers.
     */
    public function toChunks(): array
    {
        $specs = array_filter([
            "{$this->condition->label()} {$this->body_style->label()}, {$this->powertrain->label()}, {$this->drivetrain->label()}",
            "{$this->seats} seats",
            $this->towing_lb ? 'tows up to '.number_format($this->towing_lb).' lb' : null,
            $this->range_km ? "{$this->range_km} km electric range" : null,
            // Formatted explicitly: a fresh model holds 6.0 as a float, a loaded one as the string "6.0",
            // and the text has to be identical either way or the hash changes and it gets re-embedded.
            $this->fuel_l_per_100km !== null ? number_format((float) $this->fuel_l_per_100km, 1).' L/100 km' : null,
            number_format($this->km).' km',
            "{$this->exterior_color} with {$this->interior_color} interior",
        ]);

        $content = sprintf(
            '%s. Stock %s, VIN %s. Price $%s. Status: %s. %s.

%s

Features: %s.',
            $this->title,
            $this->stock_number,
            $this->vin,
            number_format($this->price),
            $this->status->label(),
            ucfirst(implode('; ', $specs)),
            $this->description,
            implode(', ', $this->features),
        );

        return [new ChunkDraft($content, "Inventory: {$this->title} ({$this->stock_number})")];
    }

    public function indexLeadId(): ?int
    {
        return null;
    }
}
