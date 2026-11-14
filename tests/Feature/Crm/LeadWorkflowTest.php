<?php

use App\Enums\ActivityType;
use App\Enums\LeadStage;
use App\Models\Lead;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->rep = User::factory()->create();
    $this->lead = Lead::factory()->assignedTo($this->rep)->create([
        'last_contacted_at' => now()->subWeek(),
    ]);
});

it('shows a rep only their own leads on the board', function () {
    Lead::factory()->create(); // someone else's

    $this->actingAs($this->rep)
        ->get(route('leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leads/Index')
            ->has('leads.data', 1)
            ->where('leads.data.0.id', $this->lead->id));
});

it('filters the list by customer name', function () {
    $this->lead->customer->update(['first_name' => 'Zelda', 'last_name' => 'Okafor']);
    Lead::factory()->assignedTo($this->rep)->create();

    $this->actingAs($this->rep)
        ->get(route('leads.index', ['view' => 'list', 'q' => 'zelda oka']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('view', 'list')
            ->has('leads.data', 1));
});

it('returns 403 for a lead that belongs to someone else', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('leads.show', $this->lead))
        ->assertForbidden();
});

it('moves a lead to a new stage and records it on the timeline', function () {
    $this->actingAs($this->rep)
        ->patch(route('leads.stage.update', $this->lead), ['stage' => 'negotiation'])
        ->assertRedirect();

    $this->lead->refresh();

    expect($this->lead->stage)->toBe(LeadStage::Negotiation)
        ->and($this->lead->activities()->first()?->type)->toBe(ActivityType::StageChange);
});

it('stamps closed_at and asks why when a lead is lost', function () {
    $this->actingAs($this->rep)
        ->patch(route('leads.stage.update', $this->lead), ['stage' => 'lost'])
        ->assertSessionHasErrors('lost_reason');

    $this->actingAs($this->rep)
        ->patch(route('leads.stage.update', $this->lead), ['stage' => 'lost', 'lost_reason' => 'Bought elsewhere']);

    expect($this->lead->refresh()->closed_at)->not->toBeNull()
        ->and($this->lead->lost_reason)->toBe('Bought elsewhere');
});

it('resets the stale clock when the rep actually talks to the customer', function () {
    expect($this->lead->isStale())->toBeTrue();

    $this->actingAs($this->rep)
        ->post(route('leads.activities.store', $this->lead), ['type' => 'call', 'body' => 'Talked through financing options.'])
        ->assertRedirect();

    expect($this->lead->refresh()->isStale())->toBeFalse();
});

it('does not let a private note reset the stale clock', function () {
    $this->actingAs($this->rep)
        ->post(route('leads.activities.store', $this->lead), ['type' => 'note', 'body' => 'Follow up later.']);

    expect($this->lead->refresh()->isStale())->toBeTrue();
});

it('creates a lead and its customer, assigned to the rep who entered it', function () {
    $this->actingAs($this->rep)
        ->post(route('leads.store'), [
            'first_name' => 'Amara',
            'last_name' => 'Singh',
            'email' => 'amara@example.com',
            'source' => 'walk_in',
            'temperature' => 'hot',
            'interest' => 'Wants an EV with 400 km of range.',
            'budget_max' => 60000,
        ])
        ->assertRedirect();

    $lead = Lead::latest('id')->first();

    expect($lead->customer->name)->toBe('Amara Singh')
        ->and($lead->assigned_user_id)->toBe($this->rep->id)
        ->and($lead->stage)->toBe(LeadStage::New);
});

it('only lets a manager reassign a lead', function () {
    $other = User::factory()->create();

    $this->actingAs($this->rep)
        ->patch(route('leads.update', $this->lead), ['assigned_user_id' => $other->id])
        ->assertForbidden();

    $this->actingAs(User::factory()->manager()->create())
        ->patch(route('leads.update', $this->lead), ['assigned_user_id' => $other->id])
        ->assertRedirect();

    expect($this->lead->refresh()->assigned_user_id)->toBe($other->id);
});
