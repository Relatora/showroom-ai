<?php

namespace App\Actions\Leads;

use App\Enums\ActivityType;
use App\Enums\LeadStage;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a lead, and the customer too if we haven't met them before.
 *
 * Customers are matched on email so a repeat buyer doesn't end up as two records. That's
 * deliberately simple. Real-world dedupe (phone numbers, typos) would be its own feature.
 */
class CreateLead
{
    public function __construct(private readonly LogActivity $logActivity) {}

    /**
     * @param  array{first_name: string, last_name: string, email?: string|null, phone?: string|null, source: string, interest: string, budget_max?: int|null, temperature: string, trade_in?: string|null, vehicle_id?: int|null, assigned_user_id?: int|null}  $data
     */
    public function handle(array $data, User $by): Lead
    {
        return DB::transaction(function () use ($data, $by) {
            $customer = filled($data['email'] ?? null)
                ? Customer::firstOrCreate(['email' => $data['email']], $this->customerFields($data))
                : Customer::create($this->customerFields($data));

            // A rep creating a lead keeps it. The BDC and managers can hand it to someone,
            // or leave it unassigned for the morning triage.
            $assignee = $by->seesWholePipeline() ? ($data['assigned_user_id'] ?? null) : $by->id;

            $lead = Lead::create([
                'customer_id' => $customer->id,
                'assigned_user_id' => $assignee,
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'source' => $data['source'],
                'stage' => LeadStage::New,
                'temperature' => $data['temperature'],
                'budget_max' => $data['budget_max'] ?? null,
                'interest' => $data['interest'],
                'trade_in' => $data['trade_in'] ?? null,
                'position' => 0,
            ]);

            Lead::where('stage', LeadStage::New)->whereKeyNot($lead->id)->increment('position');

            $this->logActivity->handle($lead, $by, ActivityType::Note, "Lead created by {$by->name}. {$lead->interest}");

            return $lead;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function customerFields(array $data): array
    {
        return [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ];
    }
}
