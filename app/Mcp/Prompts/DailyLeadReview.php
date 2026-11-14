<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

/**
 * The morning routine as a reusable prompt: pick it from the client's prompt menu and it walks
 * the tools in a sensible order. It's the "how a good sales manager starts the day" checklist,
 * written down once.
 */
#[Name('daily_lead_review')]
#[Description('Start-of-day review: who needs a call today, what to say, and what stock to mention.')]
class DailyLeadReview extends Prompt
{
    public function handle(Request $request): Response
    {
        $count = max(1, min(10, (int) ($request->get('how_many') ?? 3)));
        $focus = trim((string) $request->get('focus', ''));

        $focusLine = $focus !== '' ? "\nFocus especially on: {$focus}.\n" : '';

        return Response::text(<<<PROMPT
            Help me plan my day in the Showroom CRM.
            {$focusLine}
            1. Call pipeline_summary and tell me in two sentences how the pipeline looks today.
            2. Call search_leads with stale_only=true and pick the {$count} leads that most need attention. Prefer hot and warm leads, and leads further along the pipeline.
            3. For each of those leads:
               - call get_customer_timeline and summarise where things stand in one or two lines;
               - call find_matching_vehicles and name the one or two best fits, with the fact that matters to this customer;
               - if a policy question is open in the timeline (trade-in, deposit, financing, warranty), answer it with search_knowledge_base and name the policy;
               - suggest the next step and draft a short opening line for the call or message.
            4. Finish with a checklist I can work through, most urgent first.

            Don't log anything to the CRM unless I ask you to. Link each lead by its url.
            PROMPT);
    }

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument('how_many', 'How many leads to go through (default 3).'),
            new Argument('focus', 'Anything to focus on, e.g. "EV buyers" or "leads in Negotiation".'),
        ];
    }
}
