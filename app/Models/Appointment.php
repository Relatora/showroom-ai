<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use Carbon\CarbonImmutable;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A booked time with a customer: test drive, appraisal, delivery.
 *
 * @property int $id
 * @property int $lead_id
 * @property int $user_id
 * @property int|null $vehicle_id
 * @property AppointmentType $type
 * @property AppointmentStatus $status
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Lead $lead
 * @property-read User $user
 * @property-read Vehicle|null $vehicle
 */
#[Fillable(['lead_id', 'user_id', 'vehicle_id', 'type', 'status', 'starts_at', 'ends_at', 'notes'])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => AppointmentType::class,
            'status' => AppointmentStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
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
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Appointments follow the lead they belong to: you see them if you can see the lead.
     *
     * @param  Builder<Appointment>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->whereHas('lead', fn (Builder $leads) => $leads->visibleTo($user));
    }
}
