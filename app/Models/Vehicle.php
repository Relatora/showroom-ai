<?php

namespace App\Models;

use App\Enums\BodyStyle;
use App\Enums\Drivetrain;
use App\Enums\Powertrain;
use App\Enums\VehicleCondition;
use App\Enums\VehicleStatus;
use Carbon\CarbonImmutable;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
class Vehicle extends Model
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
}
