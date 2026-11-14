<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesLocalModelOptions;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Knowledge\PiiMasker;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Promptable;

/**
 * Drafts a follow-up email from the rep to the customer, based on the lead and its timeline.
 *
 * It's a draft, always: the UI puts it in an editable box and nothing is sent automatically.
 * The rep reads it, fixes it, and sends it themselves. That's the right amount of trust for
 * text that goes to a customer under the rep's name.
 */
#[MaxTokens(350)]
#[Temperature(0.6)]
#[Timeout(300)]
class FollowUpWriter implements Agent, HasProviderOptions
{
    use Promptable, UsesLocalModelOptions;

    public function __construct(private readonly User $rep) {}

    public function instructions(): string
    {
        $dealership = config('showroom.dealership');

        return <<<PROMPT
            You write follow-up emails for {$this->rep->name}, a salesperson at {$dealership}, a car dealership in Ontario.
            Write the email body only: no subject line, no placeholders in square brackets.
            - Warm, specific and short: 80 to 140 words. Write like a helpful person, not a brochure. Skip filler openers like "I hope this message finds you well"; start with the reason you're writing.
            - Refer to something concrete from the customer's history so it's clearly not a template.
            - If vehicles are listed, mention them by name with one detail from their listing that matters to this customer. Only quote numbers that appear above; never invent specs, prices, rates or promotions.
            - End with one clear, low-pressure next step (a test drive, a call, an appraisal), then sign off with "{$this->rep->name}" and "{$dealership}" on separate lines.
            PROMPT;
    }

    /**
     * The customer, the last few things that happened, and optionally the cars to suggest.
     * Timeline notes are masked like everything else that goes to a model: the email doesn't
     * need anyone's phone number, and the model shouldn't see it.
     *
     * @param  iterable<Vehicle>  $vehicles
     */
    public static function promptFor(Lead $lead, iterable $vehicles = []): string
    {
        $masker = app(PiiMasker::class);

        // activities() is newest first; take the latest five, then flip them so the model reads
        // the story in order.
        $timeline = $lead->activities()
            ->limit(5)
            ->get()
            ->reverse()
            ->map(fn (Activity $a) => "- {$a->occurred_at->format('M j')}, {$a->type->label()}: ".$masker->mask($a->body))
            ->implode("\n");

        $suggest = collect($vehicles)
            ->map(fn (Vehicle $v) => "- {$v->keyFacts()}")
            ->implode("\n");

        return implode("\n\n", array_filter([
            "Customer: {$lead->summary()}",
            "Customer's first name: ".strtok($lead->customer->name, ' '),
            $timeline !== '' ? "Recent history, oldest first:\n{$timeline}" : 'No contact yet; this is the first email.',
            $suggest !== '' ? "Vehicles to suggest:\n{$suggest}" : null,
            'Write the follow-up email.',
        ]));
    }
}
