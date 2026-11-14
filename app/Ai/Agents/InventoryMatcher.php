<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesLocalModelOptions;
use App\Models\Lead;
use App\Models\Vehicle;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Picks the best vehicles for a lead from a shortlist, and says why in one line each.
 *
 * The shortlist comes from search and hard filters (in stock, within budget), so the model is
 * never asked to *find* cars, only to judge the ones it's given. That's a much easier job for
 * a small model, and it means it can't recommend a car we don't have. The structured output
 * schema makes the answer a JSON object we can validate, not prose we'd have to parse.
 */
#[MaxTokens(400)]
#[Temperature(0.1)]
#[Timeout(300)]
class InventoryMatcher implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable, UsesLocalModelOptions;

    public function instructions(): string
    {
        return <<<'PROMPT'
            You help a car salesperson choose which vehicles to show a customer.
            You get a description of the customer and a numbered shortlist of vehicles in stock.
            Pick up to 3 vehicles that best fit what the customer asked for, best first.
            - fit is "strong" when it matches every stated need, "good" when it matches most, "stretch" when it misses something important (say what).
            - reason is one short sentence a salesperson could say to the customer. Don't repeat the vehicle's name (it's shown next to the reason); start with the fact that matters most to this customer: towing, seats, range, price against budget.
            - Only use stock numbers from the shortlist.
            PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'matches' => $schema->array()->max(3)->items($schema->object([
                'stock_number' => $schema->string()->required(),
                'fit' => $schema->string()->enum(['strong', 'good', 'stretch'])->required(),
                'reason' => $schema->string()->max(220)->required(),
            ]))->required(),
        ];
    }

    /**
     * @param  iterable<Vehicle>  $shortlist
     */
    public static function promptFor(Lead $lead, iterable $shortlist): string
    {
        $lines = [];

        foreach ($shortlist as $vehicle) {
            $lines[] = "- {$vehicle->stock_number}: {$vehicle->keyFacts()}";
        }

        return "Customer: {$lead->summary()}\n\nShortlist:\n".implode("\n", $lines);
    }
}
