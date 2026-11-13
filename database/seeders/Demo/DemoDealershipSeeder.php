<?php

namespace Database\Seeders\Demo;

use App\Enums\ActivityType;
use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Enums\LeadTemperature;
use App\Enums\UserRole;
use App\Enums\VehicleCondition;
use App\Enums\VehicleStatus;
use App\Models\Activity;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Seeds "Northgate Motors", a made-up multi-brand dealer in the GTA.
 *
 * It's deterministic (fixed random seed), so every machine gets the same dealership. That keeps
 * screenshots, demo scripts and the RAG evaluation questions stable. Dates are relative to
 * today, so the dashboard always looks like a busy current week.
 */
class DemoDealershipSeeder extends Seeder
{
    private const SEED = 2026;

    /** @var array<string, int> */
    private array $positions = [];

    public function run(): void
    {
        mt_srand(self::SEED);
        fake()->seed(self::SEED);

        DB::transaction(function () {
            $staff = $this->staff();
            $vehicles = $this->vehicles();
            $reps = $staff->where('role', UserRole::Rep)->values();

            foreach (range(1, 150) as $i) {
                $customer = $this->customer();
                $this->lead($customer, $reps, $vehicles, $staff);

                // Some returning customers: an older, closed lead from a previous purchase cycle.
                if ($i % 9 === 0) {
                    $this->lead($customer, $reps, $vehicles, $staff, closedHistory: true);
                }
            }
        });
    }

    /**
     * @return Collection<int, User>
     */
    private function staff(): Collection
    {
        $people = [
            ['Dana Reyes', 'dana@northgate.test', UserRole::Manager, 'Sales Manager'],
            ['Ethan Brooks', 'ethan@northgate.test', UserRole::Bdc, 'BDC Coordinator'],
            ['Marcus Chen', 'marcus@northgate.test', UserRole::Rep, 'Senior Sales Consultant'],
            ['Priya Patel', 'priya@northgate.test', UserRole::Rep, 'Sales Consultant'],
            ['Jordan Blake', 'jordan@northgate.test', UserRole::Rep, 'Sales Consultant'],
            ['Sofia Alvarez', 'sofia@northgate.test', UserRole::Rep, 'EV Specialist'],
        ];

        return collect($people)->map(fn (array $person) => User::updateOrCreate(
            ['email' => $person[1]],
            [
                'name' => $person[0],
                'role' => $person[2],
                'title' => $person[3],
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        ));
    }

    /**
     * @return Collection<int, Vehicle>
     */
    private function vehicles(): Collection
    {
        $vehicles = collect();
        $stock = 1000;

        foreach (VehicleCatalog::models() as $model) {
            foreach (range(1, mt_rand(1, 3)) as $_) {
                $vehicles->push($this->vehicle($model, ++$stock));
            }
        }

        return $vehicles;
    }

    /**
     * @param  array{make: string, model: string, body: string, powertrain: string, drivetrain: string, seats: int, trims: array<string, int>, towing_lb?: int, range_km?: int, fuel?: float, pitch: string}  $model
     */
    private function vehicle(array $model, int $stock): Vehicle
    {
        $trim = array_rand($model['trims']);
        $msrp = $model['trims'][$trim];
        $roll = mt_rand(1, 100);
        $condition = $roll <= 55 ? VehicleCondition::New : ($roll <= 88 ? VehicleCondition::Used : VehicleCondition::Certified);

        if ($condition === VehicleCondition::New) {
            $year = mt_rand(2025, 2026);
            $km = mt_rand(5, 120);
            $price = $msrp + mt_rand(-5, 15) * 100;
        } else {
            $year = mt_rand(2019, 2024);
            $age = 2026 - $year;
            $km = $age * mt_rand(14, 24) * 1000 + mt_rand(0, 999);
            // Rough depreciation curve: about 12% a year plus a bit per kilometre.
            $price = (int) (round(($msrp * (0.88 ** $age) - $km * 0.03) / 100) * 100);
        }

        [$colour, $hex] = VehicleCatalog::colours()[array_rand(VehicleCatalog::colours())];

        $features = collect(VehicleCatalog::features())->shuffle()->take(mt_rand(5, 9));
        if (($model['towing_lb'] ?? 0) >= 3500) {
            $features->push('Trailer tow package');
        }
        if ($model['seats'] >= 7) {
            $features->push('Third-row seating');
        }
        if ($model['powertrain'] === 'ev') {
            $features->push('Level 2 home charging cable');
        }

        $statusRoll = mt_rand(1, 100);

        return Vehicle::create([
            'stock_number' => 'S'.$stock,
            'vin' => $this->vin(),
            'year' => $year,
            'make' => $model['make'],
            'model' => $model['model'],
            'trim' => $trim,
            'body_style' => $model['body'],
            'powertrain' => $model['powertrain'],
            'drivetrain' => $model['drivetrain'],
            'seats' => $model['seats'],
            'exterior_color' => $colour,
            'exterior_hex' => $hex,
            'interior_color' => fake()->randomElement(['Black leatherette', 'Grey cloth', 'Black leather', 'Saddle brown leather', 'Light grey cloth']),
            'km' => $km,
            'price' => $price,
            'condition' => $condition,
            'status' => $statusRoll <= 80 ? VehicleStatus::Available : ($statusRoll <= 90 ? VehicleStatus::OnHold : VehicleStatus::Sold),
            'towing_lb' => $model['towing_lb'] ?? null,
            'range_km' => $model['range_km'] ?? null,
            'fuel_l_per_100km' => $model['fuel'] ?? null,
            'features' => $features->values()->all(),
            'description' => $this->describe($model, $condition, $km),
            'arrived_on' => now()->subDays(mt_rand(1, 120))->toDateString(),
        ]);
    }

    /**
     * @param  array{pitch: string}  $model
     */
    private function describe(array $model, VehicleCondition $condition, int $km): string
    {
        $history = match ($condition) {
            VehicleCondition::New => 'Brand new, full factory warranty.',
            VehicleCondition::Certified => sprintf('Certified pre-owned with %s km, passed a 160-point inspection and comes with an extended powertrain warranty.', number_format($km)),
            VehicleCondition::Used => sprintf('Pre-owned with %s km. One owner, clean history report, safety certified.', number_format($km)),
        };

        return $model['pitch'].' '.$history;
    }

    private function vin(): string
    {
        // VINs never use I, O or Q because they look too much like 1 and 0.
        $alphabet = 'ABCDEFGHJKLMNPRSTUVWXYZ0123456789';

        return collect(range(1, 17))->map(fn () => $alphabet[mt_rand(0, strlen($alphabet) - 1)])->implode('');
    }

    private function customer(): Customer
    {
        $first = fake()->firstName();
        $last = fake()->lastName();

        return Customer::create([
            'first_name' => $first,
            'last_name' => $last,
            'email' => strtolower("{$first}.{$last}").mt_rand(1, 99).'@example.com',
            // 555-01xx numbers are reserved for fiction, so nobody real gets a call.
            'phone' => fake()->randomElement(['416', '647', '905', '289']).'-555-01'.str_pad((string) mt_rand(0, 99), 2, '0', STR_PAD_LEFT),
            'city' => fake()->randomElement(['Toronto', 'Mississauga', 'Brampton', 'Vaughan', 'Markham', 'Oakville', 'Richmond Hill', 'Pickering', 'Oshawa', 'Barrie', 'Newmarket']),
            'province' => 'ON',
            'preferred_contact' => fake()->randomElement(['email', 'phone', 'sms']),
        ]);
    }

    /**
     * @param  Collection<int, User>  $reps
     * @param  Collection<int, Vehicle>  $vehicles
     * @param  Collection<int, User>  $staff
     */
    private function lead(Customer $customer, Collection $reps, Collection $vehicles, Collection $staff, bool $closedHistory = false): void
    {
        $personaKey = array_rand(BuyerPersonas::all());
        $persona = BuyerPersonas::all()[$personaKey];
        $stage = $closedHistory ? LeadStage::Won : $this->pickStage();
        $budget = (int) (round(mt_rand(...$persona['budget']) / 1000) * 1000);
        $createdAt = $closedHistory ? now()->subDays(mt_rand(400, 900)) : now()->subDays(mt_rand(0, 45))->subMinutes(mt_rand(0, 600));
        $rep = $reps->random();

        // About a fifth of brand-new web leads haven't been picked up by a rep yet. The BDC's job.
        $assignee = $stage === LeadStage::New && mt_rand(1, 100) <= 40 ? null : $rep;

        $match = $vehicles
            ->filter(fn (Vehicle $v) => in_array($v->body_style->value, $persona['bodies'], true)
                && in_array($v->powertrain->value, $persona['powertrains'], true)
                && $v->price <= $budget * 1.1)
            ->whenEmpty(fn () => $vehicles->filter(fn (Vehicle $v) => in_array($v->body_style->value, $persona['bodies'], true)));

        $lastContact = $this->lastContact($stage, $createdAt);

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'assigned_user_id' => $assignee?->id,
            'vehicle_id' => mt_rand(1, 100) <= 60 && $match->isNotEmpty() ? $match->random()->id : null,
            'source' => fake()->randomElement(LeadSource::cases()),
            'stage' => $stage,
            'temperature' => $this->temperature($stage),
            'budget_max' => $budget,
            'interest' => str_replace('{budget}', '$'.number_format($budget), fake()->randomElement($persona['interest'])),
            'trade_in' => mt_rand(1, 100) <= 55 ? fake()->randomElement(BuyerPersonas::tradeIns()) : null,
            'position' => $this->positions[$stage->value] = ($this->positions[$stage->value] ?? 0) + 1,
            'last_contacted_at' => $lastContact,
            'next_follow_up_at' => $stage->isOpen() ? now()->addDays(mt_rand(-2, 6))->setTime(mt_rand(9, 17), 0) : null,
            'closed_at' => $stage->isOpen() ? null : ($lastContact ?? $createdAt->copy()->addDays(10)),
            'lost_reason' => $stage === LeadStage::Lost ? fake()->randomElement(['Bought elsewhere', 'Price too high', 'Decided to keep current vehicle', 'Financing declined', 'Stopped responding']) : null,
            'created_at' => $createdAt,
            'updated_at' => $lastContact ?? $createdAt,
        ]);

        $this->timeline($lead, $persona, $createdAt, $lastContact, $assignee);
        $this->appointments($lead, $vehicles, $assignee ?? $rep);
    }

    private function pickStage(): LeadStage
    {
        $roll = mt_rand(1, 100);

        return match (true) {
            $roll <= 14 => LeadStage::New,
            $roll <= 32 => LeadStage::Contacted,
            $roll <= 44 => LeadStage::Appointment,
            $roll <= 54 => LeadStage::TestDrive,
            $roll <= 64 => LeadStage::Negotiation,
            $roll <= 84 => LeadStage::Won,
            default => LeadStage::Lost,
        };
    }

    private function temperature(LeadStage $stage): LeadTemperature
    {
        return match ($stage) {
            LeadStage::TestDrive, LeadStage::Negotiation => mt_rand(1, 100) <= 70 ? LeadTemperature::Hot : LeadTemperature::Warm,
            LeadStage::Lost => LeadTemperature::Cold,
            LeadStage::New => fake()->randomElement([LeadTemperature::Hot, LeadTemperature::Warm, LeadTemperature::Warm, LeadTemperature::Cold]),
            default => fake()->randomElement([LeadTemperature::Hot, LeadTemperature::Warm, LeadTemperature::Cold]),
        };
    }

    private function lastContact(LeadStage $stage, CarbonImmutable $createdAt): ?CarbonImmutable
    {
        if ($stage === LeadStage::New) {
            return null;
        }

        // Roughly a third of open leads have gone quiet for more than three days. That's the
        // "stale leads" list a manager chases every morning.
        $daysAgo = mt_rand(1, 100) <= 35 ? mt_rand(4, 12) : mt_rand(0, 3);
        $contact = now()->subDays($daysAgo)->setTime(mt_rand(9, 18), mt_rand(0, 59));

        return $contact->lt($createdAt) ? $createdAt->copy()->addHours(mt_rand(1, 20)) : $contact;
    }

    /**
     * @param  array{notes: list<string>, interest: list<string>}  $persona
     */
    private function timeline(Lead $lead, array $persona, CarbonImmutable $from, ?CarbonImmutable $to, ?User $author): void
    {
        $to ??= $from->copy()->addHours(2);
        $entries = [[ActivityType::Note, "Lead came in via {$lead->source->label()}. {$lead->interest}", null]];

        foreach (collect($persona['notes'])->shuffle()->take(mt_rand(1, 3)) as $note) {
            $entries[] = [fake()->randomElement([ActivityType::Call, ActivityType::Note, ActivityType::Meeting]), $note, $author];
        }

        foreach (collect(BuyerPersonas::followUps())->shuffle()->take(mt_rand(1, 3)) as $note) {
            $entries[] = [fake()->randomElement([ActivityType::Call, ActivityType::Email, ActivityType::Sms]), $note, $author];
        }

        if ($lead->trade_in) {
            $entries[] = [ActivityType::Note, "Trade-in: {$lead->trade_in}. Wants an appraisal.", $author];
        }

        if ($lead->stage !== LeadStage::New) {
            $entries[] = [ActivityType::StageChange, "Moved to {$lead->stage->label()}.", $author];
        }

        $count = count($entries);
        $span = max(1, $from->diffInMinutes($to));

        foreach ($entries as $i => [$type, $body, $user]) {
            Activity::create([
                'lead_id' => $lead->id,
                'user_id' => $user?->id,
                'type' => $type,
                'body' => $body,
                'meta' => $type === ActivityType::StageChange ? ['to' => $lead->stage->value] : null,
                'occurred_at' => $from->copy()->addMinutes((int) ($span * $i / max(1, $count - 1))),
            ]);
        }
    }

    /**
     * @param  Collection<int, Vehicle>  $vehicles
     */
    private function appointments(Lead $lead, Collection $vehicles, User $rep): void
    {
        $vehicleId = $lead->vehicle_id ?? $vehicles->random()->id;

        [$type, $status, $when] = match ($lead->stage) {
            // Booked, happening this week. These fill up the calendar.
            LeadStage::Appointment => [AppointmentType::TestDrive, AppointmentStatus::Scheduled, now()->addDays(mt_rand(0, 6))],
            LeadStage::TestDrive => [AppointmentType::TestDrive, AppointmentStatus::Completed, now()->subDays(mt_rand(1, 5))],
            LeadStage::Negotiation => [AppointmentType::Appraisal, mt_rand(0, 1) ? AppointmentStatus::Scheduled : AppointmentStatus::Completed, now()->addDays(mt_rand(-3, 4))],
            LeadStage::Won => [AppointmentType::Delivery, $lead->closed_at?->isFuture() ? AppointmentStatus::Scheduled : AppointmentStatus::Completed, $lead->closed_at ?? now()],
            default => [null, null, null],
        };

        if ($type === null || $status === null || $when === null) {
            return;
        }

        // Keep it to business hours, on the hour or half hour.
        $start = $when->copy()->setTime(mt_rand(9, 17), fake()->randomElement([0, 30]));

        if ($start->isPast() && $status === AppointmentStatus::Scheduled) {
            $start = now()->addDays(mt_rand(1, 6))->setTime(mt_rand(9, 17), 0);
        }

        Appointment::create([
            'lead_id' => $lead->id,
            'user_id' => $lead->assigned_user_id ?? $rep->id,
            'vehicle_id' => $vehicleId,
            'type' => $type,
            'status' => $status,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes($type === AppointmentType::Delivery ? 90 : 45),
            'notes' => $type === AppointmentType::Appraisal ? "Appraise trade-in: {$lead->trade_in}" : null,
        ]);
    }
}
