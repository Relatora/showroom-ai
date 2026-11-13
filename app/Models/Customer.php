<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Someone who has (or had) an interest in buying from us.
 *
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $city
 * @property string|null $province
 * @property string $preferred_contact
 * @property-read string $name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['first_name', 'last_name', 'email', 'phone', 'city', 'province', 'preferred_contact'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $appends = ['name'];

    /**
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->first_name} {$this->last_name}"));
    }

    /**
     * A customer is visible to you if you can see at least one of their leads.
     *
     * @param  Builder<Customer>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->seesWholePipeline()) {
            return;
        }

        $query->whereHas('leads', fn (Builder $leads) => $leads->where('assigned_user_id', $user->id));
    }
}
