<?php

namespace App\Mcp\Concerns;

use App\Models\Lead;
use App\Models\User;
use App\Services\Knowledge\PiiMasker;
use Illuminate\Auth\AuthenticationException;
use Laravel\Mcp\Request;

/**
 * Shared plumbing for the MCP tools: who's asking, and which leads they may see.
 *
 * An MCP client is just another way into the CRM, so it gets exactly the same rules as the web
 * UI. A rep's Claude Desktop sees that rep's leads, and nothing else.
 */
trait ActsForStaff
{
    protected function staff(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException('Connect with a Showroom access token (or start the stdio server with --user).');
        }

        return $user;
    }

    /**
     * A lead the user may see, or null. "Not found" and "not yours" give the same answer on
     * purpose, so a client can't probe for lead ids that belong to someone else.
     */
    protected function visibleLead(User $user, mixed $id): ?Lead
    {
        return Lead::visibleTo($user)
            ->with(['customer', 'assignee', 'vehicle'])
            ->find((int) $id);
    }

    /**
     * MCP results go to whichever model the client uses, which may well be a cloud service. So
     * free text is masked for phone numbers, emails and card numbers on the way out, the same
     * as it is before anything is embedded.
     */
    protected function mask(?string $text): ?string
    {
        return $text === null ? null : app(PiiMasker::class)->mask($text);
    }

    /**
     * @return array<string, mixed>
     */
    protected function leadCard(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'customer' => $lead->customer->name,
            'stage' => $lead->stage->value,
            'temperature' => $lead->temperature->value,
            'interest' => $this->mask($lead->interest),
            'budget_max' => $lead->budget_max,
            'trade_in' => $lead->trade_in,
            'rep' => $lead->assignee?->name,
            'last_contacted' => $lead->last_contacted_at?->toIso8601String(),
            'days_since_contact' => $lead->last_contacted_at ? (int) $lead->last_contacted_at->diffInDays(now()) : null,
            'next_follow_up' => $lead->next_follow_up_at?->toIso8601String(),
            'is_stale' => $lead->isStale(),
            'url' => route('leads.show', $lead),
        ];
    }
}
