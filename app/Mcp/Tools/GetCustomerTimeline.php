<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ActsForStaff;
use App\Models\Activity;
use App\Models\Appointment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_customer_timeline')]
#[Description('Everything about one lead: who the customer is, what they want, their budget and trade-in, upcoming appointments, and the timeline of calls, emails, notes and stage changes (newest first). Contact details are masked.')]
#[IsReadOnly]
class GetCustomerTimeline extends Tool
{
    use ActsForStaff;

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $this->staff($request);
        $args = $request->validate([
            'lead_id' => ['required', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $lead = $this->visibleLead($user, $args['lead_id']);

        if (! $lead) {
            return Response::error("There's no lead #{$args['lead_id']} that you can see. Use search_leads to find one.");
        }

        $activities = $lead->activities()->with('user:id,name')->limit($args['limit'] ?? 20)->get();
        $appointments = $lead->appointments()
            ->with('vehicle')
            ->where('starts_at', '>=', now()->startOfDay())
            ->orderBy('starts_at')
            ->get();

        return Response::structured([
            'lead' => [
                ...$this->leadCard($lead),
                'source' => $lead->source->value,
                'preferred_contact' => $lead->customer->preferred_contact,
                'city' => $lead->customer->city,
                'interested_in_vehicle' => $lead->vehicle?->keyFacts(),
                'lost_reason' => $lead->lost_reason,
            ],
            'upcoming_appointments' => $appointments->map(fn (Appointment $a) => [
                'type' => $a->type->value,
                'starts_at' => $a->starts_at->toIso8601String(),
                'vehicle' => $a->vehicle?->title,
            ])->all(),
            'timeline' => $activities->map(fn (Activity $a) => [
                'type' => $a->type->value,
                'at' => $a->occurred_at->toIso8601String(),
                'by' => $a->user?->name,
                'text' => $this->mask($a->body),
            ])->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_id' => $schema->integer()->required()->description('The lead id, from search_leads.'),
            'limit' => $schema->integer()->min(1)->max(50)->description('How many timeline entries (default 20).'),
        ];
    }
}
