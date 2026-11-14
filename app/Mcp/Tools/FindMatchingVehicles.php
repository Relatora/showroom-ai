<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ActsForStaff;
use App\Models\Vehicle;
use App\Services\Leads\VehicleMatcher;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Returns the same shortlist the lead page's "Find matches" builds, but skips the local model's
 * ranking: the client calling this tool is itself a language model, and usually a stronger one,
 * so it gets the facts and does the judging. No point running two models for one decision.
 */
#[Name('find_matching_vehicles')]
#[Description('In-stock vehicles that fit a lead: searched by what the customer asked for, then filtered to available vehicles within 10% of their budget. Returns up to 6 candidates with their key facts, best search match first. Decide which ones really fit and explain why.')]
#[IsReadOnly]
class FindMatchingVehicles extends Tool
{
    use ActsForStaff;

    public function handle(Request $request, VehicleMatcher $matcher): Response|ResponseFactory
    {
        $user = $this->staff($request);
        $args = $request->validate(['lead_id' => ['required', 'integer']]);

        $lead = $this->visibleLead($user, $args['lead_id']);

        if (! $lead) {
            return Response::error("There's no lead #{$args['lead_id']} that you can see. Use search_leads to find one.");
        }

        $vehicles = $matcher->shortlist($lead, $user);

        if ($vehicles->isEmpty()) {
            return Response::text("Nothing available in stock fits {$lead->customer->name}'s needs within their budget right now.");
        }

        return Response::structured([
            'customer_wants' => $this->mask($lead->interest),
            'budget_max' => $lead->budget_max,
            'candidates' => $vehicles->map(fn (Vehicle $v) => [
                'id' => $v->id,
                'stock_number' => $v->stock_number,
                'facts' => $v->keyFacts(),
                'price' => $v->price,
                'days_on_lot' => $v->daysOnLot(),
                'url' => route('inventory.show', $v),
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
        ];
    }
}
