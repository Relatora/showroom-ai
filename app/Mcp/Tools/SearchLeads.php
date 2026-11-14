<?php

namespace App\Mcp\Tools;

use App\Enums\LeadStage;
use App\Enums\LeadTemperature;
use App\Mcp\Concerns\ActsForStaff;
use App\Models\Lead;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search_leads')]
#[Description('Find leads you can see in the CRM. Filter by free text (customer name or what they want), stage, temperature, or only stale leads (no customer contact for more than 3 days). Open leads only unless a closed stage is asked for. Stalest first.')]
#[IsReadOnly]
class SearchLeads extends Tool
{
    use ActsForStaff;

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->staff($request);

        $args = $request->validate([
            'query' => ['nullable', 'string', 'max:200'],
            'stage' => ['nullable', Rule::enum(LeadStage::class)],
            'temperature' => ['nullable', Rule::enum(LeadTemperature::class)],
            'stale_only' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        $leads = Lead::visibleTo($user)
            ->with(['customer', 'assignee', 'vehicle'])
            ->when(
                $args['stage'] ?? null,
                fn (Builder $q, string $stage) => $q->where('stage', $stage),
                fn (Builder $q) => $q->open(),
            )
            ->when($args['temperature'] ?? null, fn (Builder $q, string $t) => $q->where('temperature', $t))
            ->when($args['stale_only'] ?? false, fn (Builder $q) => $q->stale())
            ->when($args['query'] ?? null, fn (Builder $q, string $text) => $q->where(fn (Builder $q) => $q
                ->where('interest', 'ilike', "%{$text}%")
                ->orWhereHas('customer', fn (Builder $c) => $c
                    ->whereRaw("first_name || ' ' || last_name ilike ?", ["%{$text}%"]))))
            // Longest silence first: that's almost always who needs attention.
            ->orderByRaw('last_contacted_at asc nulls first')
            ->limit($args['limit'] ?? 10)
            ->get();

        return Response::structured([
            'count' => $leads->count(),
            'leads' => $leads->map(fn (Lead $lead) => $this->leadCard($lead))->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Text to look for in the customer name or what they are looking for, e.g. "tow" or "Singh".'),
            'stage' => $schema->string()->enum(array_column(LeadStage::cases(), 'value'))->description('Only leads in this stage.'),
            'temperature' => $schema->string()->enum(array_column(LeadTemperature::cases(), 'value')),
            'stale_only' => $schema->boolean()->description('Only leads with no customer contact for more than 3 days.'),
            'limit' => $schema->integer()->min(1)->max(25)->description('How many to return (default 10).'),
        ];
    }
}
