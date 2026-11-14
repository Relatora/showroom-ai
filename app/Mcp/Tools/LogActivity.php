<?php

namespace App\Mcp\Tools;

use App\Actions\Leads\LogActivity as LogActivityAction;
use App\Enums\ActivityType;
use App\Mcp\Concerns\ActsForStaff;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

/**
 * The one tool that writes. It goes through the same LogActivity action as the lead page, so
 * the rules match exactly: calls, emails, texts, meetings and test drives reset the stale clock,
 * notes don't, and stage changes can't be logged by hand at all.
 *
 * Every entry records that it came through MCP, so a manager reading the timeline can tell
 * which notes an assistant wrote on someone's behalf.
 */
#[Name('log_activity')]
#[Description("Add an entry to a lead's timeline, as you. Use type call, email, sms, meeting or test_drive for real contact with the customer (this resets the stale-lead clock), or note for an internal note (it doesn't). Confirm the wording with the user before logging.")]
#[IsDestructive(false)]
#[IsIdempotent(false)]
class LogActivity extends Tool
{
    use ActsForStaff;

    public function handle(Request $request, LogActivityAction $logActivity): Response|ResponseFactory
    {
        $user = $this->staff($request);
        $args = $request->validate([
            'lead_id' => ['required', 'integer'],
            'type' => ['required', Rule::enum(ActivityType::class)->except([ActivityType::StageChange])],
            'body' => ['required', 'string', 'min:2', 'max:4000'],
        ]);

        $lead = $this->visibleLead($user, $args['lead_id']);

        if (! $lead) {
            return Response::error("There's no lead #{$args['lead_id']} that you can see. Use search_leads to find one.");
        }

        // Seeing a lead and changing it can be different rights; check the policy like the UI does.
        Gate::forUser($user)->authorize('update', $lead);

        $activity = $logActivity->handle($lead, $user, ActivityType::from($args['type']), $args['body'], ['via' => 'mcp']);

        return Response::structured([
            'logged' => true,
            'activity_id' => $activity->id,
            'lead_id' => $lead->id,
            'type' => $activity->type->value,
            'resets_stale_clock' => $lead->fresh()?->last_contacted_at?->equalTo($activity->occurred_at) ?? false,
            'url' => route('leads.show', $lead),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        $types = array_values(array_filter(
            array_column(ActivityType::cases(), 'value'),
            fn (string $t) => $t !== ActivityType::StageChange->value,
        ));

        return [
            'lead_id' => $schema->integer()->required(),
            'type' => $schema->string()->enum($types)->required(),
            'body' => $schema->string()->required()->description('What happened, in plain words. Written to the timeline as is.'),
        ];
    }
}
